<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\JenkinsService;

class JenkinsController extends Controller
{
    protected $jenkins;

    public function __construct(JenkinsService $jenkins)
    {
        $this->jenkins = $jenkins;
    }

    // Web dashboard
    public function dashboard()
    {
        $jobName = 'MyJob';
        $status = $this->jenkins->getJobStatus($jobName);
        $log = $this->jenkins->getJobLog($jobName);

        return view('jenkins.dashboard', compact('jobName', 'status', 'log'));
    }


    // Trigger job từ web form
    public function triggerWeb(Request $request, $jobName)
    {
        $parameters = $request->input('parameters', []); // ['PARAM1' => 'panh']
        $success = $this->jenkins->triggerJob($jobName, $parameters);

        return redirect()->route('jenkins.dashboard')
            ->with('message', "$jobName triggered successfully!")
            ->with('status', $success ? 'SUCCESS' : 'FAILED');
    }

    // API: trigger job
    public function triggerApi(Request $request, $jobName)
    {
        $parameters = $request->input('parameters', []);
        $success = $this->jenkins->triggerJob($jobName, $parameters);

        return response()->json([
            'job' => $jobName,
            'parameters' => $parameters,
            'triggered' => $success,
            'message' => $success ? 'Job triggered successfully' : 'Job trigger failed'
        ]);
    }

    // API: get status
    public function statusApi($jobName)
    {
        $status = $this->jenkins->getJobStatus($jobName);

        return response()->json([
            'job' => $jobName,
            'status' => $status
        ]);
    }

    // API: get log
    public function logApi($jobName)
    {
        $log = $this->jenkins->getJobLog($jobName);

        return response()->json([
            'job' => $jobName,
            'log' => $log
        ]);
    }
}
