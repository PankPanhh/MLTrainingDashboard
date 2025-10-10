<?php

namespace App\Http\Controllers;

use App\Models\MlJob;

class MlJobController extends Controller
{
    public function index()
    {
        $jobs = MlJob::orderBy('started_at', 'desc')->get();
        return view('ml_jobs.index', compact('jobs'));
    }

    public function show($id)
    {
        $job = MlJob::findOrFail($id);
        return view('ml_jobs.show', compact('job'));
    }
}