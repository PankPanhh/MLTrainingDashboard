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
            $response = Http::withBasicAuth($this->user, $this->token)
                ->get($this->baseUrl . '/crumbIssuer/api/json');

            if ($response->ok()) {
                $data = $response->json();
                $this->crumb = [
                    $data['crumbRequestField'] => $data['crumb']
                ];
                return $this->crumb;
            }
        } catch (\Exception $e) {
            Log::error("Jenkins crumb error: " . $e->getMessage());
        }

        return [];
    }
    
    public function triggerJob(string $jobName, array $parameters = [])
    {
        $url = $this->baseUrl . '/job/' . $jobName . '/build';
        if (!empty($parameters)) {
            $url .= 'WithParameters';
        }

        $request = Http::withBasicAuth($this->user, $this->token)
            ->withHeaders($this->getCrumb());

        if (!empty($parameters)) {
            $response = $request->post($url, $parameters);
        } else {
            $response = $request->post($url);
        }

        return $response->ok();
    }

    public function getJobStatus(string $jobName)
    {
        try {
            $url = $this->baseUrl . '/job/' . $jobName . '/lastBuild/api/json';
            $response = Http::withBasicAuth($this->user, $this->token)->get($url);

            if ($response->ok()) {
                $data = $response->json();
                return [
                    'building' => $data['building'] ?? false,
                    'result' => $data['result'] ?? null,
                    'timestamp' => $data['timestamp'] ?? null,
                ];
            }
        } catch (\Exception $e) {
            Log::error("Jenkins status error: " . $e->getMessage());
        }
        return null;
    }

    public function getJobLog(string $jobName)
    {
        try {
            $url = $this->baseUrl . '/job/' . $jobName . '/lastBuild/logText/progressiveText';
            $response = Http::withBasicAuth($this->user, $this->token)->get($url);

            return $response->ok() ? $response->body() : null;
        } catch (\Exception $e) {
            Log::error("Jenkins log error: " . $e->getMessage());
            return null;
        }
    }
}
