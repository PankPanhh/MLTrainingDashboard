<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\JenkinsService;
use App\Models\MlJob;

class UpdateJenkinsJobs extends Command
{
    protected $signature = 'jenkins:update';
    protected $description = 'Update status and logs of Jenkins jobs';

    protected $jenkins;

    public function __construct(JenkinsService $jenkins)
    {
        parent::__construct();
        $this->jenkins = $jenkins;
    }

    public function handle()
    {
        $jobs = MlJob::where('status', 'running')->get();
        foreach ($jobs as $job) {
            $status = $this->jenkins->getJobStatus($job->job_name);
            if ($status && !$status['building']) {
                $job->update([
                    'status' => $status['result'] ?? 'UNKNOWN',
                    'log' => $this->jenkins->getJobLog($job->job_name),
                    'build_number' => $status['build_number'] ?? $job->build_number,
                    'finished_at' => $status['timestamp'] ? now()->setTimestamp($status['timestamp'] / 1000) : now(),
                ]);
            }
        }
        $this->info('Jenkins jobs updated successfully.');
    }
}