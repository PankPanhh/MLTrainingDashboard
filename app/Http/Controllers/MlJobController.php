<?php

namespace App\Http\Controllers;

use App\Models\MlJob;
use Illuminate\Http\Request;
use App\Services\JenkinsService;
use Illuminate\Support\Facades\Log;

class MlJobController extends Controller
{
    protected $jenkins;

    public function __construct(JenkinsService $jenkins)
    {
        $this->jenkins = $jenkins;
    }

    /**
     * Trigger a Jenkins job from the web dashboard.
     */
    public function triggerJob(Request $request, $jobName)
    {
        $parameterNames = $request->input('parameter_names', []);
        $parameterValues = $request->input('parameter_values', []);
        $params = [];
        foreach ($parameterNames as $index => $name) {
            if (!empty($name) && isset($parameterValues[$index])) {
                $params[$name] = $parameterValues[$index];
            }
        }

        // Trigger and wait for Jenkins to assign a build number (via queue)
        $buildNumber = $this->jenkins->triggerJobAndWaitForBuildNumber($jobName, $params, 60);
        if ($buildNumber === null) {
            Log::error("Failed to trigger job or fetch build number", ['job' => $jobName, 'params' => $params]);
            return redirect()->back()->with('error', "Triggered $jobName but failed to determine build number.");
        }

        // Fetch build-specific status and log
        $status = $this->jenkins->getBuildStatus($jobName, $buildNumber);
        $log = $this->jenkins->getBuildLog($jobName, $buildNumber);

        // Try to fetch the actual parameters Jenkins recorded for this build
        $jenkinsParams = $this->jenkins->getBuildParameters($jobName, $buildNumber);
        if ($jenkinsParams !== null) {
            // Prefer Jenkins' recorded params (they reflect what Jenkins actually used)
            $params = $jenkinsParams;
        } else {
            // Log that we couldn't fetch params from Jenkins so we can debug
            Log::warning('Could not fetch parameters from Jenkins for build', ['job' => $jobName, 'build' => $buildNumber]);
        }

        $existingJob = MlJob::where('job_name', $jobName)
            ->where('build_number', $buildNumber)
            ->first();

        $recordData = [
            'job_name' => $jobName,
            'params' => $params,
            'build_number' => $buildNumber,
            'log' => $log,
        ];

        Log::info('TriggerJob debug', ['job' => $jobName, 'build' => $buildNumber, 'params_sent' => $request->all(), 'params_recorded' => $params]);

        // Convert Jenkins timestamps (milliseconds) to Carbon instances if available
        if ($status && !empty($status['timestamp'])) {
            try {
                $started = \Carbon\Carbon::createFromTimestampMs($status['timestamp'])->timezone(config('app.timezone'));
                $recordData['started_at'] = $started;
                // If duration is available and result present, set finished_at = started + duration
                if (!empty($status['duration'])) {
                    $finished = $started->copy()->addMilliseconds($status['duration']);
                    $recordData['finished_at'] = $finished;
                }
            } catch (\Exception $e) {
                // fallback: set started_at to now if conversion fails
                $recordData['started_at'] = now();
            }
        } else {
            $recordData['started_at'] = now();
        }

        if ($status) {
            if (!empty($status['building'])) {
                $recordData['status'] = 'running';
            } elseif (!empty($status['result'])) {
                $recordData['status'] = strtolower($status['result']);
                // If finished_at not set from duration calculation above, set to now
                if (empty($recordData['finished_at'])) {
                    $recordData['finished_at'] = now();
                }
            }
        } else {
            $recordData['status'] = 'queued';
        }

        if ($existingJob) {
            $existingJob->update($recordData);
        } else {
            MlJob::create($recordData);
        }

        $message = "$jobName triggered. Build #{$buildNumber} was assigned.";
        if ($status && !empty($status['building'])) $message .= ' Job is running.';
        if ($status && !empty($status['result'])) $message .= " Result: {$status['result']}";
        return redirect()->back()->with('message', $message);
    }

    /**
     * Return job status as JSON for AJAX calls from the dashboard.
     */
    public function getJobStatus($jobName)
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

    /**
     * Return job console log as plain text for AJAX calls from the dashboard.
     */
    public function getJobLog($jobName)
    {
        $log = $this->jenkins->getJobLog($jobName);
        if ($log !== null) {
            return response($log)->header('Content-Type', 'text/plain');
        }
        return response('Unable to fetch job log', 500);
    }
    public function index()
    {
        $jobs = MlJob::orderBy('started_at', 'desc')->get();
        return view('ml_jobs.index', compact('jobs'));
    }

    public function show($id)
    {
        $job = MlJob::findOrFail($id);
        return view('ml_jobs.show', compact('job'));
    }

    /**
     * Return status for a stored MlJob record (by id).
     */
    public function statusById($id)
    {
        $job = MlJob::findOrFail($id);
        if (!$job->build_number) {
            return response()->json(['error' => 'No build number associated with this job record'], 400);
        }
        $status = $this->jenkins->getBuildStatus($job->job_name, $job->build_number);
        if ($status) {
            return response()->json($status);
        }
        return response()->json(['error' => 'Unable to fetch build status'], 500);
    }

    /**
     * Return console log for a stored MlJob record (by id).
     */
    public function logById($id)
    {
        $job = MlJob::findOrFail($id);
        if (!$job->build_number) {
            return response('No build number associated with this job record', 400);
        }
        $log = $this->jenkins->getBuildLog($job->job_name, $job->build_number);
        if ($log !== null) {
            return response($log)->header('Content-Type', 'text/plain');
        }
        return response('Unable to fetch build log', 500);
    }
}