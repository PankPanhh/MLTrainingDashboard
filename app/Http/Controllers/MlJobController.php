<?php

namespace App\Http\Controllers;

use App\Models\MlJob;
use Illuminate\Http\Request;
use App\Services\JenkinsService;
use App\Services\JobService;
use Illuminate\Support\Facades\Log;

class MlJobController extends Controller
{
    protected $jenkins;
    protected $jobService;

    public function __construct(JenkinsService $jenkins, JobService $jobService)
    {
        $this->jenkins = $jenkins;
        $this->jobService = $jobService;
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

        $result = $this->jobService->triggerAndRecord($jobName, $params, 60);

        // Redirect back with standardized flash messages
        if ($result['status'] === 'success') {
            return redirect()->back()->with('message', $result['message']);
        }
        return redirect()->back()->with('error', $result['message']);
    }

    /**
     * Return job status as JSON for AJAX calls from the dashboard.
     */
    public function getJobStatus($jobName)
    {
        $result = $this->jobService->getJobStatusFromJenkins($jobName);
        if ($result['status'] === 'success') {
            return response()->json(['status' => 'success', 'message' => $result['message'], 'data' => $result['data']]);
        }
        return response()->json(['status' => 'error', 'message' => $result['message']], 500);
    }

    /**
     * Return job console log as plain text for AJAX calls from the dashboard.
     */
    public function getJobLog($jobName)
    {
        $result = $this->jobService->getJobLogFromJenkins($jobName);
        if ($result['status'] === 'success') {
            return response($result['data']['log'])->header('Content-Type', 'text/plain');
        }
        return response($result['message'], 500);
    }
    public function index()
    {
        $result = $this->jobService->listStoredJobs();
        $jobs = $result['data'] ?? collect();
        return view('ml_jobs.index', compact('jobs'));
    }

    public function show($id)
    {
        $result = $this->jobService->getStoredJobById($id);
        if ($result['status'] === 'error') {
            abort(404, $result['message']);
        }
        $job = $result['data'];
        return view('ml_jobs.show', compact('job'));
    }

    /**
     * Return status for a stored MlJob record (by id).
     */
    public function statusById($id)
    {
        $result = $this->jobService->getStoredJobStatusById($id);
        if ($result['status'] === 'success') {
            return response()->json(['status' => 'success', 'message' => $result['message'], 'data' => $result['data']]);
        }
        $code = $result['message'] === 'Job not found' ? 404 : 500;
        if ($result['message'] === 'No build number associated with this job record') $code = 400;
        return response()->json(['status' => 'error', 'message' => $result['message']], $code);
    }

    /**
     * Return console log for a stored MlJob record (by id).
     */
    public function logById($id)
    {
        $result = $this->jobService->getStoredJobLogById($id);
        if ($result['status'] === 'success') {
            return response($result['data']['log'])->header('Content-Type', 'text/plain');
        }
        $code = 500;
        if ($result['message'] === 'Job not found') $code = 404;
        if ($result['message'] === 'No build number associated with this job record') $code = 400;
        return response($result['message'], $code);
    }
}