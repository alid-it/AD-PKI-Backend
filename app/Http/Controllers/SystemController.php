<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Models\SchedulerRun;
use App\Models\Setting;
use App\Services\CA\GoCAService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class SystemController extends Controller
{
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.ca.url'), '/');
    }

    public function systemInfo()
    {
        $go = $this->getGoSystemInfo();

        $pgVersion = 'unknown';
        try {
            $pgRaw = DB::select("SELECT version()")[0]->version ?? '';
            preg_match('/PostgreSQL\s([\d\.]+)/', $pgRaw, $matches);
            $pgVersion = $matches[1] ?? 'unknown';
        } catch (\Exception $e) {
            $pgVersion = 'unknown';
        }

        $nodeVersion = trim((string) shell_exec('node -v 2>/dev/null')) ?: 'not installed';

        $data = [
            ['component' => 'PHP', 'version' => phpversion()],
            ['component' => 'Laravel', 'version' => app()->version()],
            ['component' => 'PostgreSQL', 'version' => $pgVersion],
            ['component' => 'Node', 'version' => $nodeVersion],
        ];

        foreach ($go as $item) {
            if (!is_array($item))
                continue;

            $entry = [
                'component' => $this->cleanValue($item['component'] ?? 'CA Core'),
                'version' => $this->cleanValue($item['version'] ?? 'unknown'),
            ];

            if (isset($item['detail']))
                $entry['detail'] = $this->cleanValue($item['detail']);
            if (isset($item['url']))
                $entry['url'] = $this->cleanValue($item['url']);

            $data[] = $entry;
        }

        // =====================================================
        // 🔥 HEALTH CHECKS
        // =====================================================

        $dbOk = false;
        try {
            DB::select('SELECT 1');
            $dbOk = true;
        } catch (\Exception $e) {
        }

        $data[] = [
            'component' => 'Health: Datenbank',
            'version' => $dbOk ? 'OK' : 'ERROR',
        ];

        $data[] = [
            'component' => 'Health: CA Service',
            'version' => !empty($go) ? 'OK' : 'ERROR',
        ];

        $smtpOk = false;
        try {
            $host = Setting::getValue('mail_host');
            $port = (int) (Setting::getValue('mail_port') ?: 587);

            if ($host) {
                $socket = @fsockopen($host, $port, $errno, $errstr, 3);
                if ($socket) {
                    fclose($socket);
                    $smtpOk = true;
                }
            }
        } catch (\Exception $e) {
        }

        $data[] = [
            'component' => 'Health: SMTP',
            'version' => $smtpOk ? 'OK' : 'ERROR',
        ];

        $storageEntry = collect($go)->firstWhere('component', 'Storage');
        $data[] = [
            'component' => 'Health: Storage',
            'version' => $storageEntry['version'] ?? 'ERROR',
            'detail' => $storageEntry['detail'] ?? 'Keine Daten vom CA-Service',
        ];

        $ntp = $this->systemNtpStatus();

        $data[] = [
            'component' => 'Health: NTP',
            'version' => $ntp['ok'] ? 'OK' : 'ERROR',
            'detail' => $ntp['detail'],
        ];

        // 🔹 TSA Status
        try {
            $tsaResponse = GoCAService::client()->timeout(3)->get($this->baseUrl . '/tsa/status');
            $tsaData = $tsaResponse->json();

            if ($tsaData['exists'] ?? false) {
                $tsaValidTo = Carbon::parse($tsaData['valid_to']);
                $daysLeft = (int) Carbon::now()->diffInDays($tsaValidTo, false);
                $tsaStatus = $daysLeft > 30 ? 'OK' : ($daysLeft > 0 ? 'WARN' : 'ERROR');
                $tsaDetail = $daysLeft > 0
                    ? ($tsaData['common_name'] ?? 'TSA') . ' · ' . $daysLeft . ' Tage'
                    : 'Abgelaufen';
            } else {
                $tsaStatus = 'ERROR';
                $tsaDetail = 'Kein TSA Zertifikat vorhanden';
            }
        } catch (\Exception $e) {
            $tsaStatus = 'ERROR';
            $tsaDetail = 'TSA nicht erreichbar';
        }

        $data[] = [
            'component' => 'Health: TSA',
            'version' => $tsaStatus,
            'detail' => $tsaDetail,
        ];

        // =====================================================
        // 🔥 CA ZERTIFIKATSABLAUF
        // =====================================================

        $root = Certificate::where('type', 'root')->first();

        if ($root && $root->valid_to) {
            $daysLeft = (int) Carbon::now()->diffInDays(Carbon::parse($root->valid_to), false);
            $data[] = [
                'component' => 'Health: Root CA Ablauf',
                'version' => $daysLeft > 30 ? 'OK' : ($daysLeft > 0 ? 'WARN' : 'ERROR'),
                'detail' => $daysLeft > 0 ? $daysLeft . ' Tage' : 'Abgelaufen',
            ];
        }

        $intermediates = Certificate::where('type', 'intermediate')->get();

        foreach ($intermediates as $int) {
            if (!$int->valid_to)
                continue;

            $daysLeft = (int) Carbon::now()->diffInDays(Carbon::parse($int->valid_to), false);
            $data[] = [
                'component' => 'Health: Int-CA ' . $int->id . ' Ablauf',
                'version' => $daysLeft > 30 ? 'OK' : ($daysLeft > 0 ? 'WARN' : 'ERROR'),
                'detail' => $daysLeft > 0 ? $daysLeft . ' Tage' : 'Abgelaufen',
            ];
        }

        // =====================================================
        // 🔥 SCHEDULER JOBS
        // =====================================================

        $jobs = [
            'certificates:check-expiring',
            'certs:auto-revoke',
            'system:check-health',
        ];

        if (SchedulerRun::count() === 0) {
            Artisan::call('certificates:check-expiring');
            Artisan::call('certs:auto-revoke');
            Artisan::call('system:check-health');
        }

        foreach ($jobs as $job) {
            $run = SchedulerRun::where('command', $job)->first();
            $status = $run ? strtoupper(trim($run->status)) : 'UNKNOWN';

            $data[] = [
                'component' => 'Job: ' . $job,
                'version' => $status,
                'last_run' => $run?->last_run,
                'error' => $run?->error,
            ];
        }

        // =====================================================
        // 🔥 CA STATUS: CRL + OCSP
        // =====================================================

        foreach ($intermediates as $int) {
            $crlUrl = url('/api/' . ltrim((string) $int->crl_path, '/'));
            $ocspUrl = $this->baseUrl . '/ocsp';

            $crlFile = pki_path('crl_int-' . $int->id . '.pem');
            $crlStatus = file_exists($crlFile) && filesize($crlFile) > 0;

            $ocspStatus = true;
            try {
                // 🔥 Mit Token
                GoCAService::client()
                    ->timeout(1)
                    ->connectTimeout(1)
                    ->get($ocspUrl);
            } catch (\Exception $e) {
                $ocspStatus = false;
            }

            $data[] = [
                'component' => 'CRL (int-' . $int->id . ')',
                'version' => $crlStatus ? 'OK' : 'ERROR',
                'url' => $crlUrl,
            ];

            $data[] = [
                'component' => 'OCSP',
                'version' => $ocspStatus ? 'OK' : 'ERROR',
                'url' => $ocspUrl,
            ];
        }

        return response()->json($data);
    }

    // =====================================================
    // 🔥 NTP SERVER SETZEN
    // =====================================================

    public function setNtpServer(Request $request)
    {
        $validated = $request->validate([
            'ntp_server' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-zA-Z0-9._:-]+$/',
            ],
        ]);

        $ntpServer = $validated['ntp_server'];

        try {
            // 🔥 Mit Token
            $response = GoCAService::client()
                ->timeout(15)
                ->connectTimeout(3)
                ->post($this->baseUrl . '/system/ntp', [
                    'ntp_server' => $ntpServer,
                ]);

            if (!$response->successful()) {
                return response()->json([
                    'message_key' => 'backend.system.ntp_set_failed',
                    'error' => $this->cleanValue($response->body()),
                ], 500);
            }

            Setting::setValue('ntp_server', $ntpServer);

            return response()->json([
                'message_key' => 'backend.system.ntp_set_success',
                'ntp_server' => $ntpServer,
                'go' => $response->json(),
                'status' => $this->systemNtpStatus(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message_key' => 'backend.system.ca_core_unreachable',
                'error' => $this->cleanValue($e->getMessage()),
            ], 500);
        }
    }

    // =====================================================
    // 🔥 GO SYSTEMINFO
    // =====================================================

    private function getGoSystemInfo(): array
    {
        try {
            // 🔥 Mit Token
            $response = GoCAService::client()
                ->timeout(2)
                ->connectTimeout(1)
                ->get($this->baseUrl . '/system/info');

            if (!$response->successful())
                return [];

            $json = $response->json();
            return is_array($json) ? $json : [];
        } catch (\Exception $e) {
            return [];
        }
    }

    // =====================================================
    // 🔥 SYSTEM NTP STATUS
    // =====================================================

    private function systemNtpStatus(): array
    {
        $chronyActive = trim((string) shell_exec('systemctl is-active chrony 2>/dev/null')) === 'active';

        if ($chronyActive) {
            return $this->chronyNtpStatus();
        }

        return $this->timesyncdNtpStatus();
    }

    private function chronyNtpStatus(): array
    {
        try {
            $tracking = (string) shell_exec('chronyc tracking 2>/dev/null');

            if (empty($tracking)) {
                return [
                    'ok' => false,
                    'server' => null,
                    'detail' => 'chrony liefert keinen Status',
                ];
            }

            preg_match('/Reference ID\s*:\s*\S+\s*\(([^)]+)\)/', $tracking, $refMatch);
            preg_match('/Leap status\s*:\s*(.+)/', $tracking, $leapMatch);

            $server = $this->cleanValue($refMatch[1] ?? '');
            $leapStatus = $this->cleanValue($leapMatch[1] ?? '');

            $configuredServer = $this->cleanValue(Setting::getValue('ntp_server'));
            $activeServer = $server ?: ($configuredServer ?: null);

            $synced = $leapStatus === 'Normal' && $server !== '';

            if ($synced) {
                return [
                    'ok' => true,
                    'server' => $activeServer,
                    'detail' => 'Synchronisiert (chrony) · ' . ($activeServer ?: 'unbekannter Server'),
                ];
            }

            return [
                'ok' => false,
                'server' => $activeServer,
                'detail' => 'Nicht synchronisiert (chrony)' . ($activeServer ? ' · ' . $activeServer : ''),
            ];
        } catch (\Exception $e) {
            return [
                'ok' => false,
                'server' => null,
                'detail' => 'chrony-Status konnte nicht gelesen werden',
            ];
        }
    }

    private function timesyncdNtpStatus(): array
    {
        try {
            $synced = $this->cleanValue(shell_exec(
                'timedatectl show -p NTPSynchronized --value 2>/dev/null'
            ));

            $serverName = $this->cleanValue(shell_exec(
                'timedatectl show-timesync --property=ServerName --value 2>/dev/null'
            ));

            $serverAddress = $this->cleanValue(shell_exec(
                'timedatectl show-timesync --property=ServerAddress --value 2>/dev/null'
            ));

            $configuredServer = $this->cleanValue(Setting::getValue('ntp_server'));
            $activeServer = $serverName ?: ($serverAddress ?: null);

            if ($synced === 'yes') {
                return [
                    'ok' => true,
                    'server' => $activeServer,
                    'detail' => 'Synchronisiert (timesyncd) · ' . ($activeServer ?: 'unbekannter Server'),
                ];
            }

            if ($activeServer) {
                return [
                    'ok' => false,
                    'server' => $activeServer,
                    'detail' => 'Nicht synchronisiert (timesyncd) · aktiver Server: ' . $activeServer,
                ];
            }

            if ($configuredServer) {
                return [
                    'ok' => false,
                    'server' => $configuredServer,
                    'detail' => 'Nicht synchronisiert (timesyncd) · konfiguriert: ' . $configuredServer,
                ];
            }

            return [
                'ok' => false,
                'server' => null,
                'detail' => 'Nicht synchronisiert (timesyncd) · kein NTP-Server aktiv',
            ];
        } catch (\Exception $e) {
            return [
                'ok' => false,
                'server' => null,
                'detail' => 'timesyncd-Status konnte nicht gelesen werden',
            ];
        }
    }

    // =====================================================
    // 🔥 UDP NTP CHECK (Fallback)
    // =====================================================

    private function checkNtp(string $server, int $timeout = 3): array
    {
        try {
            $socket = @fsockopen('udp://' . $server, 123, $errno, $errstr, $timeout);
            if (!$socket)
                return ['ok' => false, 'offset' => null];

            stream_set_timeout($socket, $timeout);
            $packet = "\x1b" . str_repeat("\0", 47);
            fwrite($socket, $packet);
            $response = fread($socket, 48);
            fclose($socket);

            if (strlen($response) < 48)
                return ['ok' => false, 'offset' => null];

            $data = unpack('N2', substr($response, 40, 8));
            if (!$data || !isset($data[1]))
                return ['ok' => false, 'offset' => null];

            $unixTime = $data[1] - 2208988800;
            $offset = round(($unixTime - time()) * 1000, 1);

            return ['ok' => true, 'offset' => $offset];
        } catch (\Exception $e) {
            return ['ok' => false, 'offset' => null];
        }
    }

    // =====================================================
    // 🔥 SAFE STRING CLEANUP
    // =====================================================

    private function cleanValue(mixed $value): string
    {
        return trim(str_replace(["\r", "\n"], ' ', (string) $value));
    }
}
