<?php

namespace App\Services;

use App\Events\AuditLogCreated;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;

/**
 * AuditService
 *
 * Zentrale Stelle für alle Audit-Logs im AD-PKI System.
 *
 * Verwendung:
 *   AuditService::log('certificate.issued', $cert);
 *   AuditService::log('user.login', null, ['username' => 'ali']);
 *
 * Log-ID: eindeutige UUID pro Eintrag (in meta gespeichert)
 */
class AuditService
{
    // ---------------------------------------------------------------
    // Verfügbare Aktionen (Konstanten für Konsistenz)
    // ---------------------------------------------------------------

    // Auth
    const AUTH_LOGIN = 'auth.login';
    const AUTH_LOGIN_FAILED = 'auth.login.failed';
    const AUTH_LOGOUT = 'auth.logout';

    // Zertifikate
    const CERT_ISSUED = 'certificate.issued';
    const CERT_REQUESTED = 'certificate.requested';
    const CERT_APPROVED = 'certificate.approved';
    const CERT_REJECTED = 'certificate.rejected';
    const CERT_REVOKED = 'certificate.revoked';
    const CERT_DOWNLOAD_CRT = 'certificate.download.crt';
    const CERT_DOWNLOAD_KEY = 'certificate.download.key';
    const CERT_DOWNLOAD_P12 = 'certificate.download.p12';
    const CERT_DOWNLOAD_CHAIN = 'certificate.download.chain';
    const CERT_DOWNLOAD_CSR = 'certificate.download.csr';

    // CA
    const CA_ROOT_IMPORTED = 'ca.root.imported';
    const CA_INTERMEDIATE_IMPORTED = 'ca.intermediate.imported';
    const CA_CERT_DOWNLOADED = 'ca.cert.downloaded';
    const CA_CHAIN_DOWNLOADED = 'ca.chain.downloaded';

    // Benutzer
    const USER_CREATED = 'user.created';
    const USER_UPDATED = 'user.updated';
    const USER_DELETED = 'user.deleted';
    const USER_ROLE_CHANGED = 'user.role.changed';
    const USER_PERMS_CHANGED = 'user.permissions.changed';

    // Rollen
    const ROLE_CREATED = 'role.created';
    const ROLE_UPDATED = 'role.updated';
    const ROLE_DELETED = 'role.deleted';
    const ROLE_PERMS_CHANGED = 'role.permissions.changed';

    // Einstellungen
    const SETTINGS_CHANGED = 'settings.changed';

    // Notifications
    const NOTIFICATION_SETTINGS_CHANGED = 'notification.settings.changed';
    const NOTIFICATION_TEST_SENT = 'notification.test.sent';

    // Templates
    const TEMPLATE_CREATED = 'template.created';
    const TEMPLATE_DELETED = 'template.deleted';

    // ACME
    const ACME_CERT_ISSUED = 'acme.certificate.issued';
    const ACME_CERT_REVOKED = 'acme.certificate.revoked';
    const ACME_ACCOUNT_DEACTIVATED = 'acme.account.deactivated';

    // ---------------------------------------------------------------
    // Core Log Methode
    // ---------------------------------------------------------------

    /**
     * Schreibt einen Audit-Log Eintrag.
     *
     * @param string     $action      Aktion (z.B. AuditService::CERT_ISSUED)
     * @param Model|null $subject     Betroffenes Model (Certificate, User, ...)
     * @param array      $meta        Zusätzliche Informationen
     * @param bool       $anonymous   true = kein User-Kontext (z.B. Login-Fehler)
     */
    public static function log(
        string $action,
        ?Model $subject = null,
        array $meta = [],
        bool $anonymous = false
    ): void {
        $log = null;

        try {
            $meta['log_id'] = (string) Str::uuid();

            /** @var \App\Models\User|null $user */
            $user = $anonymous ? null : Auth::user();

            $log = AuditLog::create([
                'user_id' => $user?->getKey(),
                'action' => $action,
                'subject_type' => $subject ? class_basename($subject) : null,
                'subject_id' => $subject?->getKey(),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'meta' => $meta,
            ]);
        } catch (\Throwable) {
            return; // DB-Fehler → abbrechen
        }

        // 🔥 Broadcast außerhalb — eigener try/catch
        if ($log) {
            try {
                broadcast(new AuditLogCreated($log->load('user')));
            } catch (\Throwable) {
                // Broadcast-Fehler darf Log nicht blockieren
            }
        }
    }

    // ---------------------------------------------------------------
    // Convenience Methoden
    // ---------------------------------------------------------------

    public static function authLogin(string $username): void
    {
        self::log(self::AUTH_LOGIN, null, [
            'username' => $username,
        ], anonymous: false);
    }

    public static function authLoginFailed(string $username): void
    {
        self::log(self::AUTH_LOGIN_FAILED, null, [
            'username' => $username,
        ], anonymous: true);
    }

    public static function authLogout(): void
    {
        self::log(self::AUTH_LOGOUT);
    }

    public static function certDownload(Model $cert, string $type): void
    {
        $action = match ($type) {
            'key' => self::CERT_DOWNLOAD_KEY,
            'p12' => self::CERT_DOWNLOAD_P12,
            'fullchain' => self::CERT_DOWNLOAD_CHAIN,
            'csr' => self::CERT_DOWNLOAD_CSR,
            default => self::CERT_DOWNLOAD_CRT,
        };

        self::log($action, $cert, [
            'common_name' => $cert->common_name ?? null,
            'serial_number' => $cert->serial_number ?? null,
        ]);
    }

    public static function caRootImported(Model $cert): void
    {
        self::log(self::CA_ROOT_IMPORTED, $cert, [
            'common_name' => $cert->common_name,
            'serial_number' => $cert->serial_number,
        ]);
    }

    public static function caIntermediateImported(Model $cert): void
    {
        self::log(self::CA_INTERMEDIATE_IMPORTED, $cert, [
            'common_name' => $cert->common_name,
            'serial_number' => $cert->serial_number,
        ]);
    }

    public static function caCertDownloaded(Model $cert, string $filename): void
    {
        self::log(self::CA_CERT_DOWNLOADED, $cert, [
            'filename' => $filename,
            'common_name' => $cert->common_name,
        ]);
    }

    public static function caChainDownloaded(Model $cert): void
    {
        self::log(self::CA_CHAIN_DOWNLOADED, $cert, [
            'common_name' => $cert->common_name,
        ]);
    }
}
