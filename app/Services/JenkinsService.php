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

    /**
     * Trigger a job and wait for Jenkins to assign a build number via the queue item.
     * Returns build number on success, or null on failure/timeout.
     */
    public function triggerJobAndWaitForBuildNumber(string $jobName, array $parameters = [], int $timeoutSeconds = 60)
    {
        $url = $this->baseUrl . '/job/' . $jobName . '/build' . (!empty($parameters) ? 'WithParameters' : '');

        try {
            $request = Http::withBasicAuth($this->user, $this->token)
                ->withHeaders($this->getCrumb());
            Log::info('Triggering job (wait) payload', ['job' => $jobName, 'payload' => $parameters, 'url' => $url]);
            // Jenkins expects form-encoded parameters for buildWithParameters.
            // Use asForm() so Guzzle sends application/x-www-form-urlencoded rather than JSON.
            $res = $request->asForm()->post($url, $parameters);

            if (!($res->status() >= 200 && $res->status() < 400)) {
                Log::error('Jenkins trigger failed', ['job' => $jobName, 'status' => $res->status(), 'body' => $res->body()]);
                return null;
            }

            $location = $res->header('Location');
            if (!$location) {
                Log::warning('No Location header returned from Jenkins when triggering job; falling back to lastBuild', ['job' => $jobName]);
                $status = $this->getJobStatus($jobName);
                return $status['build_number'] ?? null;
            }

            $queueApi = rtrim($location, '/') . '/api/json';
            $start = time();
            while (time() - $start < $timeoutSeconds) {
                try {
                    $qRes = Http::withBasicAuth($this->user, $this->token)->get($queueApi);
                    if ($qRes->ok()) {
                        $qData = $qRes->json();
                        Log::debug('Jenkins queue item', ['job' => $jobName, 'queue' => $qData]);
                        if (isset($qData['executable']['number'])) {
                            return $qData['executable']['number'];
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning('Error polling Jenkins queue item', ['error' => $e->getMessage(), 'job' => $jobName]);
                }
                sleep(2);
            }

            Log::warning('Timed out waiting for Jenkins to assign build number', ['job' => $jobName]);
            return null;
        } catch (\Exception $e) {
            Log::error('Trigger and wait failed', ['job' => $jobName, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Get status for a specific build number of a job.
     */
    public function getBuildStatus(string $jobName, $buildNumber)
    {
        try {
            $url = $this->baseUrl . '/job/' . $jobName . '/' . $buildNumber . '/api/json';
            $res = Http::withBasicAuth($this->user, $this->token)->get($url);
            if ($res->ok()) {
                $data = $res->json();
                return [
                    'building' => $data['building'] ?? false,
                    'result' => $data['result'] ?? null,
                    'timestamp' => $data['timestamp'] ?? null,
                    'duration' => $data['duration'] ?? null,
                    'build_number' => $data['number'] ?? $buildNumber,
                ];
            }
            Log::error('Get build status failed', ['job' => $jobName, 'build' => $buildNumber, 'status' => $res->status(), 'body' => $res->body()]);
        } catch (\Exception $e) {
            Log::error('Get build status failed', ['job' => $jobName, 'build' => $buildNumber, 'error' => $e->getMessage()]);
        }
        return null;
    }

    /**
     * Get console log for a specific build number.
     */
    public function getBuildLog(string $jobName, $buildNumber)
    {
        try {
            $url = $this->baseUrl . '/job/' . $jobName . '/' . $buildNumber . '/logText/progressiveText';
            $res = Http::withBasicAuth($this->user, $this->token)->get($url);
            if ($res->ok()) return $res->body();
            Log::error('Get build log failed', ['job' => $jobName, 'build' => $buildNumber, 'status' => $res->status(), 'body' => $res->body()]);
        } catch (\Exception $e) {
            Log::error('Get build log failed', ['job' => $jobName, 'build' => $buildNumber, 'error' => $e->getMessage()]);
        }
        return null;
    }

    /**
     * Extract parameters used for a specific build from Jenkins build API.
     * Returns associative array of name => value, or null on failure/no params.
     */
    public function getBuildParameters(string $jobName, $buildNumber)
    {
        $url = $this->baseUrl . '/job/' . $jobName . '/' . $buildNumber . '/api/json';
        $tries = 0;
        $maxTries = 15; // try for ~30 seconds (sleep 2s between tries)
        while ($tries < $maxTries) {
            try {
                $res = Http::withBasicAuth($this->user, $this->token)->get($url);
                if ($res->ok()) {
                    $data = $res->json();
                    Log::debug('Raw build JSON for parameters', ['job' => $jobName, 'build' => $buildNumber, 'json' => $data]);
                    if (!empty($data['actions']) && is_array($data['actions'])) {
                        foreach ($data['actions'] as $action) {
                            if (isset($action['parameters']) && is_array($action['parameters'])) {
                                $out = [];
                                foreach ($action['parameters'] as $p) {
                                    if (isset($p['name'])) {
                                        $out[$p['name']] = $p['value'] ?? null;
                                    }
                                }
                                return $out;
                            }
                        }
                    }
                } else {
                    Log::warning('Failed to fetch build parameters', ['job' => $jobName, 'build' => $buildNumber, 'status' => $res->status()]);
                }
            } catch (\Exception $e) {
                Log::error('Error fetching build parameters', ['job' => $jobName, 'build' => $buildNumber, 'error' => $e->getMessage()]);
            }
            $tries++;
            sleep(2);
        }
        return null;
    }
}