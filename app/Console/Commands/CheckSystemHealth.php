<?php

namespace App\Console\Commands;

use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use App\Events\CaHealthChanged;
use App\Services\Notifications\NotificationEngine;
use App\Services\System\SchedulerTracker;

class CheckSystemHealth extends Command
{
    protected $signature = 'system:check-health';
    protected $description = 'Check CRL and OCSP health and send notifications';

    public function handle()
    {
        try {

            $this->info("Checking system health...");

            $baseUrl = config('services.ca.url');
            $publicOcspUrl = (string) Setting::getValue('ocsp_base_url');

            $intermediates = DB::table('certificates')
                ->where('type', 'intermediate')
                ->get();

            foreach ($intermediates as $int) {

                // 🔥 CRL CHECK
                $crlFile = pki_path('crl_int-' . $int->id . '.crl');
                $crlOk = file_exists($crlFile) && filesize($crlFile) > 0;

                // 🔥 CRL Status live broadcasten
                CaHealthChanged::dispatch(
                    'CRL (int-' . $int->id . ')',
                    $crlOk ? 'OK' : 'ERROR',
                    rtrim((string) Setting::getValue('crl_base_url'), '/')
                        . '/int-' . $int->id . '.crl'
                );

                if (!$crlOk) {

                    $this->error("CRL FAILED (int-{$int->id})");

                    app(NotificationEngine::class)->dispatch('crl_failed', [
                        'intermediate' => 'int-' . $int->id,
                        'crl_path' => $crlFile,
                    ]);
                }

                // 🔥 OCSP CHECK
                $ocspOk = true;

                try {
                    Http::timeout(2)->get($baseUrl . '/ocsp');
                } catch (\Exception $e) {
                    $ocspOk = false;
                }

                // 🔥 OCSP Status live broadcasten
                CaHealthChanged::dispatch(
                    'OCSP',
                    $ocspOk ? 'OK' : 'ERROR',
                    $publicOcspUrl
                );

                if (!$ocspOk) {

                    $this->error("OCSP FAILED");

                    app(NotificationEngine::class)->dispatch('ocsp_failed', [
                        'ocsp_url' => $publicOcspUrl,
                    ]);
                }
            }

            SchedulerTracker::success('system:check-health');
        } catch (\Exception $e) {

            SchedulerTracker::error('system:check-health', $e->getMessage());
            $this->error($e->getMessage());
        }

        $this->info("Done.");

        return 0;
    }
}
