<?php
use App\Http\Controllers\JenkinsController;

Route::prefix('jenkins')->group(function () {
    Route::get('/dashboard', [JenkinsController::class, 'dashboard'])
        ->name('jenkins.dashboard');

    Route::post('/trigger-web/{jobName}', [JenkinsController::class, 'triggerWeb'])
        ->name('jenkins.triggerWeb');
});
