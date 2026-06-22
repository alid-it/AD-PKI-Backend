<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\App;
use App\Console\Commands\CheckExpiringCertificates;
use App\Console\Commands\CheckSystemHealth;
use App\Console\Commands\AutoRevokeExpired;
use App\Console\Commands\SetupInstall;


class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->commands([
            SetupInstall::class,
            AutoRevokeExpired::class,
            CheckExpiringCertificates::class,
            CheckSystemHealth::class,
        ]);
    }

    public function boot(): void
    {
        // 🔥 AUTO SCHEDULER START
        if (env('AUTO_SCHEDULER', false) && App::runningInConsole() === false) {

            $lockFile = storage_path('scheduler.lock');

            if (!file_exists($lockFile)) {

                // 🔥 starte Scheduler im Hintergrund
                exec('php artisan schedule:work > /dev/null 2>&1 &');

                file_put_contents($lockFile, 'running');
            }
        }
    }
}