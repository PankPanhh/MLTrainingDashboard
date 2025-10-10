<?php

use App\Http\Controllers\JenkinsController;

Route::prefix('jenkins')->group(function () {
    Route::post('/trigger/{jobName}', [JenkinsController::class, 'triggerApi']);
    Route::get('/status/{jobName}', [JenkinsController::class, 'statusApi']);
    Route::get('/log/{jobName}', [JenkinsController::class, 'logApi']);
});
