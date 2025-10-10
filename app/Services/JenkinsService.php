<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class JenkinsService
{
    protected $baseUrl;
    protected $user;
    protected $token;
    protected $crumb;

    public function __construct()
    {
        $this->baseUrl = config('services.jenkins.url');
        $this->user = config('services.jenkins.user');
        $this->token = config('services.jenkins.token');
    }

    protected function getCrumb()
    {
        if ($this->crumb) return $this->crumb;

        try {
            $res = Http::withBasicAuth($this->user, $this->token)
                ->get($this->baseUrl . '/crumbIssuer/api/json');
            if ($res->ok()) {
                $data = $res->json();
                $this->crumb = [$data['crumbRequestField'] => $data['crumb']];
            }
        } catch (\Exception $e) {
            Log::error("Failed to get Jenkins crumb", ['error' => $e->getMessage()]);
        }
        return $this->crumb ?? [];
    }

    // Thêm phương thức public để lấy crumb headers
    public function getCrumbHeaders()
    {
        return $this->getCrumb();
    }

    public function triggerJob(string $jobName, array $parameters = [])
    {
        $url = $this->baseUrl . '/job/' . $jobName . '/build';
        if (!empty($parameters)) {
            $url .= 'WithParameters';
            $queryString = http_build_query($parameters);
            $url .= '?' . $queryString;
        }

        try {
            $request = Http::withBasicAuth($this->user, $this->token)
                ->withHeaders($this->getCrumb());
            Log::info("Triggering Jenkins job", [
                'job' => $jobName,
                'parameters' => $parameters,
                'url' => $url
            ]);
            $res = $request->post($url);
            
            if ($res->status() >= 200 && $res->status() < 400) {
                Log::info("Jenkins job triggered successfully", [
                    'job' => $jobName,
                    'status' => $res->status(),
                    'location' => $res->header('Location') ?? 'No Location header'
                ]);
                return true;
            }
            
            Log::error("Jenkins job trigger failed", [
                'job' => $jobName,
                'status' => $res->status(),
                'body' => $res->body()
            ]);
            return false;
        } catch (\Exception $e) {
            Log::error("Trigger job failed", [
                'job' => $jobName,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function getJobStatus(string $jobName)
    {
        try {
            $url = $this->baseUrl . '/job/' . $jobName . '/lastBuild/api/json';
            $res = Http::withBasicAuth($this->user, $this->token)->get($url);
            if ($res->ok()) {
                $data = $res->json();
                return [
                    'building' => $data['building'] ?? false,
                    'result' => $data['result'] ?? null,
                    'timestamp' => $data['timestamp'] ?? null,
                    'build_number' => $data['number'] ?? null,
                ];
            }
            Log::error("Get job status failed", [
                'job' => $jobName,
                'status' => $res->status(),
                'body' => $res->body()
            ]);
        } catch (\Exception $e) {
            Log::error("Get job status failed", [
                'job' => $jobName,
                'error' => $e->getMessage()
            ]);
        }
        return null;
    }

    public function getJobLog(string $jobName)
    {
        try {
            $url = $this->baseUrl . '/job/' . $jobName . '/lastBuild/logText/progressiveText';
            $res = Http::withBasicAuth($this->user, $this->token)->get($url);
            if ($res->ok()) return $res->body();
            Log::error("Get job log failed", [
                'job' => $jobName,
                'status' => $res->status(),
                'body' => $res->body()
            ]);
        } catch (\Exception $e) {
            Log::error("Get job log failed", [
                'job' => $jobName,
                'error' => $e->getMessage()
            ]);
        }
        return null;
    }
}