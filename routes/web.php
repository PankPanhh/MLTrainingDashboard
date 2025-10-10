<?php

use App\Http\Controllers\JenkinsController;
use App\Http\Controllers\MlJobController;
use Illuminate\Support\Facades\Route;

Route::get('/dashboard', function () {
    return view('dashboard');
})->name('dashboard');

Route::post('/jenkins/trigger/{jobName}', [JenkinsController::class, 'triggerWeb'])->name('jenkins.triggerWeb');
Route::get('/ml-jobs', [MlJobController::class, 'index'])->name('ml-jobs.index');
Route::get('/ml-jobs/{id}', [MlJobController::class, 'show'])->name('ml-jobs.show');