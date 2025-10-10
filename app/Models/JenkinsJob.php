<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JenkinsJob extends Model
{
    protected $fillable = [
        'name',
        'last_triggered',
        'status',
    ];
}
