<?php

use App\Http\Controllers\JenkinsController;
use App\Http\Controllers\MlJobController;
use Illuminate\Support\Facades\Route;

Route::get('/dashboard', function () {
    return view('dashboard');
})->name('dashboard');

Route::post('/jenkins/trigger/{jobName}', [JenkinsController::class, 'triggerWeb'])->name('jenkins.triggerWeb');
// MlJobController routes for dashboard-triggered actions (mirror JenkinsController)
Route::post('/ml/trigger/{jobName}', [MlJobController::class, 'triggerJob'])->name('ml.triggerJob');
Route::get('/ml/status/{jobName}', [MlJobController::class, 'getJobStatus'])->name('ml.getJobStatus');
Route::get('/ml/log/{jobName}', [MlJobController::class, 'getJobLog'])->name('ml.getJobLog');
Route::get('/ml-jobs', [MlJobController::class, 'index'])->name('ml-jobs.index');
Route::get('/ml-jobs/{id}', [MlJobController::class, 'show'])->name('ml-jobs.show');
// MlJob record-specific endpoints
Route::get('/ml-jobs/{id}/status', [MlJobController::class, 'statusById'])->name('ml-jobs.status');
Route::get('/ml-jobs/{id}/log', [MlJobController::class, 'logById'])->name('ml-jobs.log');