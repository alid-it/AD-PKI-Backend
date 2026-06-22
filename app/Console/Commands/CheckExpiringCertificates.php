<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Certificate;
use Carbon\Carbon;
use App\Services\Notifications\NotificationEngine;
use App\Services\System\SchedulerTracker;

class CheckExpiringCertificates extends Command
{
    protected $signature = 'certificates:check-expiring {days=30}';
    protected $description = 'Check for expiring certificates and send notifications';

    public function handle()
    {
        try {

            $days = (int) $this->argument('days');

            $this->info("Checking certificates expiring in {$days} days...");

            $threshold = Carbon::now()->addDays($days);

            $certs = Certificate::where('revoked', false)
                ->where('type', '!=', 'root')
                ->where('valid_to', '<=', $threshold)
                ->get();

            foreach ($certs as $cert) {

                $this->info("Expiring: {$cert->common_name}");

                app(NotificationEngine::class)->dispatch('certificate_expiring', [
                    'common_name' => $cert->common_name,
                    'serial' => $cert->serial_number,
                    'valid_to' => Carbon::parse($cert->valid_to)->format('d.m.Y'),
                    'certificate_download' => url("/api/certificates/{$cert->id}/download?type=crt"),
                ]);
            }

            $this->info("Done. Found {$certs->count()} certificates.");

            SchedulerTracker::success('certificates:check-expiring');

        } catch (\Exception $e) {

            SchedulerTracker::error('certificates:check-expiring', $e->getMessage());
            $this->error($e->getMessage());

        }

        return 0;
    }
}