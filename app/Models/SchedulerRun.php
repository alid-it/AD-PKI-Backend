<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchedulerRun extends Model
{
    protected $table = 'scheduler_runs'; // ✅ FIX

    protected $fillable = [
        'command',
        'last_run_at',
        'status',
        'message',
    ];
}