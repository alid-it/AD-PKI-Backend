<?php

namespace App\Services\System;

use App\Models\SchedulerRun;

class SchedulerTracker
{
    public static function success(string $command): void
    {
        SchedulerRun::updateOrCreate(
            ['command' => $command],
            [
                'last_run_at' => now(),
                'status' => 'OK',
                'message' => null,
            ]
        );
    }

    public static function error(string $command, string $message): void
    {
        SchedulerRun::updateOrCreate(
            ['command' => $command],
            [
                'last_run_at' => now(),
                'status' => 'ERROR',
                'message' => $message,
            ]
        );
    }
}