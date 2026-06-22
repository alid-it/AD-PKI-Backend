<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        // 🔥 Zertifikate laufen bald ab
        $schedule->command('certificates:check-expiring')
            ->everyFiveMinutes()
            ->withoutOverlapping();

        // 🔥 Auto revoke
        $schedule->command('certs:auto-revoke')
            ->everyTenMinutes()
            ->withoutOverlapping();

        // 🔥 System Health (CRL / OCSP)
        $schedule->command('system:check-health')
            ->everyMinute()
            ->withoutOverlapping();
    }

    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');
    }
}
