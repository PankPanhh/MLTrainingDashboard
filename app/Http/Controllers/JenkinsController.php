<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\JenkinsService;
use App\Models\MlJob;
use Illuminate\Support\Facades\Log;

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
            // Đợi lâu hơn để đảm bảo job được queue
            sleep(5);
            $status = $this->jenkins->getJobStatus($jobName);
            $log = $this->jenkins->getJobLog($jobName);
            
            // Thử lại tối đa 3 lần nếu build_number là null
            $attempts = 0;
            while (!$status || !isset($status['build_number']) && $attempts < 3) {
                sleep(2);
                $status = $this->jenkins->getJobStatus($jobName);
                $attempts++;
            }

            if (!$status || !isset($status['build_number'])) {
                Log::warning("Could not fetch build number for job", ['job' => $jobName]);
                return redirect()->back()->with('error', "Triggered $jobName but failed to fetch build number.");
            }

            // Kiểm tra bản ghi hiện có với build_number
            $existingJob = MlJob::where('job_name', $jobName)
                ->where('build_number', $status['build_number'])
                ->where('status', 'running')
                ->first();

            if (!$existingJob) {
                MlJob::create([
                    'job_name' => $jobName,
                    'status' => 'running',
                    'params' => $params,
                    'build_number' => $status['build_number'],
                    'log' => $log,
                    'started_at' => now(),
                ]);
            }

            $message = "$jobName triggered and queued successfully! Build #{$status['build_number']}";
            if ($status['building']) {
                $message .= " is now running.";
            } elseif ($status['result']) {
                $message .= " completed with result: {$status['result']}.";
            }
            return redirect()->back()->with('message', $message);
        }

        Log::error("Failed to trigger job in controller", [
            'job' => $jobName,
            'params' => $params
        ]);
        return redirect()->back()->with('error', "Failed to trigger $jobName. Check Jenkins configuration and logs.");
    }

    public function triggerApi(Request $request, $jobName)
    {
        $params = $request->input('parameters', []);
        $success = $this->jenkins->triggerJob($jobName, $params);
        if ($success) {
            sleep(5);
            $status = $this->jenkins->getJobStatus($jobName);
            $log = $this->jenkins->getJobLog($jobName);
            
            $attempts = 0;
            while (!$status || !isset($status['build_number']) && $attempts < 3) {
                sleep(2);
                $status = $this->jenkins->getJobStatus($jobName);
                $attempts++;
            }

            if (!$status || !isset($status['build_number'])) {
                Log::warning("Could not fetch build number for job", ['job' => $jobName]);
                return response()->json([
                    'job' => $jobName,
                    'triggered' => true,
                    'error' => 'Failed to fetch build number'
                ], 200);
            }

            $existingJob = MlJob::where('job_name', $jobName)
                ->where('build_number', $status['build_number'])
                ->where('status', 'running')
                ->first();

            if (!$existingJob) {
                MlJob::create([
                    'job_name' => $jobName,
                    'status' => 'running',
                    'params' => $params,
                    'build_number' => $status['build_number'],
                    'log' => $log,
                    'started_at' => now(),
                ]);
            }

            return response()->json([
                'job' => $jobName,
                'triggered' => true,
                'status' => $status,
                'message' => "Job $jobName queued successfully, Build #{$status['build_number']}"
            ]);
        }

        Log::error("Failed to trigger job in API", [
            'job' => $jobName,
            'params' => $params
        ]);
        return response()->json([
            'job' => $jobName,
            'triggered' => false,
            'error' => 'Failed to trigger job. Check Jenkins configuration.'
        ], 500);
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