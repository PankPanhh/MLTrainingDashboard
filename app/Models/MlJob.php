<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MlJob extends Model
{
    protected $fillable = [
        'job_name',
        'status',
        'params',
        'log',
        'build_number',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'params' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];
}