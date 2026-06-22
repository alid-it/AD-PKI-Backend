<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Certificate;
use Carbon\Carbon;
use App\Services\CA\GoCAService;
use App\Services\AuditService;
use App\Services\System\SchedulerTracker;

class AutoRevokeExpired extends Command
{
    protected $signature = 'certs:auto-revoke';
    protected $description = 'Automatically revoke expired certificates';

    public function handle()
    {
        try {

            $expiredCerts = Certificate::where('revoked', false)
                ->where('valid_to', '<', Carbon::now())
                ->get();

            $revokedSomething = false;

            foreach ($expiredCerts as $cert) {

                if (in_array($cert->type, ['root', 'intermediate'])) {
                    continue;
                }

                $cert->revoked = true;
                $cert->revoked_at = now();
                $cert->revocation_reason = 'expired';
                $cert->save();

                // 🔥 System-Audit — kein User-Kontext (anonymous: true)
                AuditService::log(AuditService::CERT_REVOKED, $cert, [
                    'common_name'   => $cert->common_name,
                    'serial_number' => $cert->serial_number,
                    'reason'        => 'expired',
                    'source'        => 'scheduler',
                ], anonymous: true);

                $revokedSomething = true;

                $this->info("Revoked expired cert: {$cert->serial_number}");
            }

            if ($revokedSomething) {
                try {
                    app(GoCAService::class)->clearOcspCache();
                } catch (\Exception $e) {
                    // optional
                }
            }

            SchedulerTracker::success('certs:auto-revoke');
        } catch (\Exception $e) {

            SchedulerTracker::error('certs:auto-revoke', $e->getMessage());
            $this->error($e->getMessage());
        }

        return 0;
    }
}
