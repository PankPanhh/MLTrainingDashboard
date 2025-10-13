<?php

namespace App\Services;

use App\Models\MlJob;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class JobService
{
    protected $jenkins;

    public function __construct(JenkinsService $jenkins)
    {
        $this->jenkins = $jenkins;
    }

    /**
     * Trigger a Jenkins job, wait for build number, fetch status/log/params and record to DB.
     * Returns a standardized array: [status, message, data]
     */
    public function triggerAndRecord(string $jobName, array $params = [], int $timeoutSeconds = 60): array
    {
        Log::info('JobService.triggerAndRecord called', ['job' => $jobName, 'params' => $params, 'timeout' => $timeoutSeconds]);

        // Trigger and wait for build number
        $buildNumber = $this->jenkins->triggerJobAndWaitForBuildNumber($jobName, $params, $timeoutSeconds);
        if ($buildNumber === null) {
            Log::error('Failed to trigger job or fetch build number', ['job' => $jobName, 'params' => $params]);
            return [
                'status' => 'error',
                'message' => "Triggered $jobName but failed to determine build number.",
                'data' => null,
            ];
        }
        $status = $this->jenkins->getBuildStatus($jobName, $buildNumber);
        $log = $this->jenkins->getBuildLog($jobName, $buildNumber);
        $jenkinsParams = $this->jenkins->getBuildParameters($jobName, $buildNumber);

        Log::debug('Jenkins responses', ['job' => $jobName, 'build' => $buildNumber, 'status' => $status, 'log_present' => $log !== null, 'params' => $jenkinsParams]);
        if ($jenkinsParams !== null) {
            $params = $jenkinsParams;
        } else {
            Log::warning('Could not fetch parameters from Jenkins for build', ['job' => $jobName, 'build' => $buildNumber]);
        }

        $recordData = [
            'job_name' => $jobName,
            'params' => $params,
            'build_number' => $buildNumber,
            'log' => $log,
        ];

        // Convert timestamps and determine status/started/finished
        if ($status && !empty($status['timestamp'])) {
            try {
                $started = Carbon::createFromTimestampMs($status['timestamp'])->timezone(config('app.timezone'));
                $recordData['started_at'] = $started;
                if (!empty($status['duration'])) {
                    $finished = $started->copy()->addMilliseconds($status['duration']);
                    $recordData['finished_at'] = $finished;
                }
            } catch (\Exception $e) {
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
                if (empty($recordData['finished_at'])) {
                    $recordData['finished_at'] = now();
                }
            }
        } else {
            $recordData['status'] = 'queued';
        }

        $existingJob = MlJob::where('job_name', $jobName)
            ->where('build_number', $buildNumber)
            ->first();

        if ($existingJob) {
            $existingJob->update($recordData);
            $jobRecord = $existingJob;
        } else {
            $jobRecord = MlJob::create($recordData);
        }

        Log::info('MlJob recorded', ['job_record_id' => $jobRecord->id ?? null, 'job' => $jobName, 'build' => $buildNumber, 'recordData' => $recordData]);

        $message = "$jobName triggered. Build #{$buildNumber} was assigned.";
        if ($status && !empty($status['building'])) $message .= ' Job is running.';
        if ($status && !empty($status['result'])) $message .= " Result: {$status['result']}";

        return [
            'status' => 'success',
            'message' => $message,
            'data' => [
                'build_number' => $buildNumber,
                'job' => $jobRecord,
                'jenkins_status' => $status,
            ],
        ];
    }

    public function getJobStatusFromJenkins(string $jobName): array
    {
        Log::info('JobService.getJobStatusFromJenkins called', ['job' => $jobName]);
        $status = $this->jenkins->getJobStatus($jobName);
        Log::debug('Jenkins getJobStatus result', ['job' => $jobName, 'status' => $status]);
        if ($status) {
            return ['status' => 'success', 'message' => 'Fetched job status', 'data' => $status];
        }
        return ['status' => 'error', 'message' => 'Unable to fetch job status', 'data' => null];
    }

    public function getJobLogFromJenkins(string $jobName): array
    {
        Log::info('JobService.getJobLogFromJenkins called', ['job' => $jobName]);
        $log = $this->jenkins->getJobLog($jobName);
        Log::debug('Jenkins getJobLog result', ['job' => $jobName, 'log_present' => $log !== null]);
        if ($log !== null) {
            return ['status' => 'success', 'message' => 'Fetched job log', 'data' => ['log' => $log]];
        }
        return ['status' => 'error', 'message' => 'Unable to fetch job log', 'data' => null];
    }

    public function listStoredJobs(): array
    {
        $jobs = MlJob::orderBy('started_at', 'desc')->get();
        Log::debug('JobService.listStoredJobs', ['count' => $jobs->count()]);
        return ['status' => 'success', 'message' => 'Fetched stored jobs', 'data' => $jobs];
    }

    public function getStoredJobById($id): array
    {
        Log::debug('JobService.getStoredJobById', ['id' => $id]);
        $job = MlJob::find($id);
        if (!$job) return ['status' => 'error', 'message' => 'Job not found', 'data' => null];
        return ['status' => 'success', 'message' => 'Fetched job', 'data' => $job];
    }

    public function getStoredJobStatusById($id): array
    {
        Log::debug('JobService.getStoredJobStatusById', ['id' => $id]);
        $job = MlJob::find($id);
        if (!$job) return ['status' => 'error', 'message' => 'Job not found', 'data' => null];
        if (!$job->build_number) return ['status' => 'error', 'message' => 'No build number associated with this job record', 'data' => null];

        $status = $this->jenkins->getBuildStatus($job->job_name, $job->build_number);
        Log::debug('Jenkins getBuildStatus for stored job', ['job' => $job->job_name, 'build' => $job->build_number, 'status' => $status]);
        if ($status) return ['status' => 'success', 'message' => 'Fetched build status', 'data' => $status];
        return ['status' => 'error', 'message' => 'Unable to fetch build status', 'data' => null];
    }

    public function getStoredJobLogById($id): array
    {
        Log::debug('JobService.getStoredJobLogById', ['id' => $id]);
        $job = MlJob::find($id);
        if (!$job) return ['status' => 'error', 'message' => 'Job not found', 'data' => null];
        if (!$job->build_number) return ['status' => 'error', 'message' => 'No build number associated with this job record', 'data' => null];

        $log = $this->jenkins->getBuildLog($job->job_name, $job->build_number);
        Log::debug('Jenkins getBuildLog for stored job', ['job' => $job->job_name, 'build' => $job->build_number, 'log_present' => $log !== null]);
        if ($log !== null) return ['status' => 'success', 'message' => 'Fetched build log', 'data' => ['log' => $log]];
        return ['status' => 'error', 'message' => 'Unable to fetch build log', 'data' => null];
    }
}
