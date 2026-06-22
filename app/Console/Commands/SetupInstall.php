<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class SetupInstall extends Command
{
    protected $signature = 'setup:install';
    protected $description = 'Install AD-PKI system (initial jobs, scheduler check)';

    public function handle()
    {
        $this->info('🚀 AD-PKI Setup gestartet...');

        $timerActive = trim((string) shell_exec('systemctl is-active adpki-scheduler.timer 2>/dev/null')) === 'active';

        if ($timerActive) {
            $this->info('✅ adpki-scheduler.timer ist aktiv.');
        } else {
            $this->warn('⚠️  adpki-scheduler.timer ist NICHT aktiv.');
            $this->line('   Bitte prüfen: systemctl status adpki-scheduler.timer');
        }

        $this->info('🚀 Führe initiale Jobs aus...');
        Artisan::call('certificates:check-expiring');
        Artisan::call('certs:auto-revoke');
        Artisan::call('system:check-health');
        $this->info('✅ Initiale Jobs wurden ausgeführt!');

        $this->line('');
        $this->line('👉 Scheduler läuft über systemd (adpki-scheduler.timer)');
        $this->line('👉 Laravel führt die Jobs jede Minute über schedule:run aus');

        return 0;
    }
}