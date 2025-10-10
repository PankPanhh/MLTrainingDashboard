<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\JenkinsService;
use App\Models\MlJob;

class JenkinsController extends Controller
{
    protected $jenkins;

    public function __construct(JenkinsService $jenkins)
    {
        $this->jenkins = $jenkins;
    }

    public function triggerWeb(Request $request, $jobName)
    {
        $parameterNames = $request->input('parameter_names', []);
        $parameterValues = $request->input('parameter_values', []);
        $params = [];
        foreach ($parameterNames as $index => $name) {
            if (!empty($name) && isset($parameterValues[$index])) {
                $params[$name] = $parameterValues[$index];
            }
        }

        $success = $this->jenkins->triggerJob($jobName, $params);
        if ($success) {
            $status = $this->jenkins->getJobStatus($jobName);
            MlJob::create([
                'job_name' => $jobName,
                'status' => 'running',
                'params' => $params,
                'build_number' => $status['build_number'] ?? null,
                'log' => $this->jenkins->getJobLog($jobName),
                'started_at' => now(),
            ]);
            return redirect()->back()->with('message', "$jobName triggered!");
        }
        return redirect()->back()->with('error', "Failed to trigger $jobName");
    }

    public function triggerApi(Request $request, $jobName)
    {
        $params = $request->input('parameters', []);
        $success = $this->jenkins->triggerJob($jobName, $params);
        if ($success) {
            $status = $this->jenkins->getJobStatus($jobName);
            MlJob::create([
                'job_name' => $jobName,
                'status' => 'running',
                'params' => $params,
                'build_number' => $status['build_number'] ?? null,
                'log' => $this->jenkins->getJobLog($jobName),
                'started_at' => now(),
            ]);
            return response()->json(['job' => $jobName, 'triggered' => true]);
        }
        return response()->json(['job' => $jobName, 'triggered' => false], 500);
    }

    public function status($jobName)
    {
        $status = $this->jenkins->getJobStatus($jobName);
        if ($status) {
            return response()->json([
                'building' => $status['building'] ?? false,
                'result' => $status['result'] ?? null,
                'timestamp' => $status['timestamp'] ?? null,
                'build_number' => $status['build_number'] ?? null,
            ]);
        }
        return response()->json(['error' => 'Unable to fetch job status'], 500);
    }

    public function log($jobName)
    {
        $log = $this->jenkins->getJobLog($jobName);
        if ($log !== null) {
            return response($log)->header('Content-Type', 'text/plain');
        }
        return response('Unable to fetch job log', 500);
    }
}