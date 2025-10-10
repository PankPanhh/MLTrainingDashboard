<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Services\JenkinsService;
use App\Models\MlJob;
use Illuminate\Support\Facades\Log;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        \App\Console\Commands\UpdateJenkinsJobs::class,
    ];

    protected function schedule(Schedule $schedule)
    {
        $schedule->command('jenkins:update')->everyTwoMinutes();
    }
    // protected function schedule(Schedule $schedule)
    // {
    //     $schedule->call(function(){
    //         $jobs = \App\Models\MlJob::where('status','running')->get();
    //         $jenkins = app(\App\Services\JenkinsService::class);
    //         foreach($jobs as $job){
    //             $status = $jenkins->getJobStatus($job->job_name);
    //             if($status){
    //                 $job->status = $status['result'] ?? 'running';
    //                 $job->finished_at = now();
    //                 $job->log = $jenkins->getJobLog($job->job_name);
    //                 $job->save();
    //             }
    //         }
    //     })->everyTwoMinutes();
    // }

    protected function commands()
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}
