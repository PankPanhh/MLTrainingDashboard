<?php

use App\Http\Controllers\JenkinsController;
use Illuminate\Support\Facades\Route;

Route::prefix('jenkins')->group(function () {
    Route::post('/trigger/{jobName}', [JenkinsController::class, 'triggerApi']);
    Route::get('/status/{jobName}', [JenkinsController::class, 'status']);
    Route::get('/log/{jobName}', [JenkinsController::class, 'log']);
});