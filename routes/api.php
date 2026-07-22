<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AuthController;

use App\Http\Controllers\CertificateController;
use App\Http\Controllers\CAController;
use App\Http\Controllers\CertificateDownloadController;
use App\Http\Controllers\CA\CAReadController;
use App\Http\Controllers\CRLController;
use App\Http\Controllers\InternalACMECAController;


use App\Http\Controllers\SettingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SystemController;
use App\Http\Controllers\SetupController;



use App\Http\Controllers\Frontend\CertificateController as FrontendCertificateController;
use App\Http\Controllers\Frontend\CAController as FrontendCAController;
use App\Http\Controllers\Frontend\CertificateTemplateController;
use App\Http\Controllers\Frontend\NotificationController;
use App\Http\Controllers\Frontend\NotificationTestController;
use App\Http\Controllers\Frontend\PermissionController;
use App\Http\Controllers\Frontend\UserController;
use App\Http\Controllers\Frontend\RoleController;
use App\Http\Controllers\Frontend\AuditLogController;
use App\Http\Controllers\Frontend\UserPreferenceController;
use App\Http\Controllers\Frontend\TeamController;
use App\Http\Controllers\Frontend\PublicConfigController;
use App\Http\Controllers\Frontend\UserSettingsController;



use App\Http\Controllers\DnsLookupController;
use App\Http\Controllers\NotificationEventController;
use App\Http\Controllers\CertificateRequestController;
use App\Http\Controllers\OCSPProxyController;
use App\Http\Controllers\TimestampProxyController;
use App\Http\Controllers\TSAController;

// =====================================================
// 🔓 PUBLIC
// =====================================================

Route::post('/login', [AuthController::class, 'login']);
Route::get('/crl/{id}.pem', [CRLController::class, 'show']);
Route::get('/config', function () {
    return response()->json([
        'reverb_key' => config('reverb.apps.apps.0.key'),
        'reverb_host' => env('REVERB_PUBLIC_HOST', request()->getHost()),
        'reverb_port' => (int) env('REVERB_PUBLIC_PORT', 6001),
        'reverb_scheme' => env('REVERB_PUBLIC_SCHEME', request()->getScheme()),
    ]);
});

// 🔥 Root CA Download — public (für Trust-Verteilung)
Route::get('/ca/root/download', [CAReadController::class, 'root']);
Route::get('/ca/intermediate/{id}/download', [CAReadController::class, 'intermediate']);

Route::get('/public/config', [PublicConfigController::class, 'index']);

// 🔥 OCSP Proxy — public
Route::post('/ocsp', [OCSPProxyController::class, 'handle']);
Route::get('/ocsp',  [OCSPProxyController::class, 'handle']);
// 🔥 Timestamp — public
Route::post('/timestamp', [TimestampProxyController::class, 'handle']);
Route::get('/timestamp',  [TimestampProxyController::class, 'handle']);

// 🔥 INTERN — Go CA Service
Route::middleware('ca.token')->prefix('internal')->group(function () {
    Route::get('/acme-settings', [InternalACMECAController::class, 'acmeSettings']);
    Route::post('/acme-certificate', [InternalACMECAController::class, 'storeCertificate']);
    Route::post('/acme/revoke', [InternalACMECAController::class, 'revokeByCertificate']);

    // Setup Import
    Route::post('/import-root', [CAController::class, 'importRoot']);
    Route::post('/import-intermediate', [CAController::class, 'importIntermediate']);

    // Passphrase für verschlüsselte Intermediate-Keys (nur Go CA-Core)
    Route::get('/intermediate/{id}/passphrase', [CAController::class, 'passphrase']);

    // Setup
    Route::post('/setup/create-admin', [SetupController::class, 'createAdmin']);
    Route::get('/crl/revoked', [CertificateController::class, 'revokedList']);
});


// =====================================================
// 🔐 AUTHENTICATED
// =====================================================

Route::middleware('auth:sanctum')->group(function () {

    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me/preferences/{key}', [UserPreferenceController::class, 'get']);
    Route::post('/me/preferences', [UserPreferenceController::class, 'set']);
    Route::post('/dns/lookup', [DnsLookupController::class, 'lookup']);



    // =====================================================
    // 🔥 GO / CA ENDPOINTS
    // =====================================================

    Route::prefix('ca')->group(function () {


        // CA Import nur Admin/Settings
        Route::post('/import-root', [CAController::class, 'importRoot'])
            ->middleware('permission:settings.manage');

        Route::post('/import-intermediate', [CAController::class, 'importIntermediate'])
            ->middleware('permission:settings.manage');

        // Wird beim Zertifikat erstellen gebraucht
        Route::get('/intermediate/default', [CAController::class, 'defaultIntermediate'])
            ->middleware('permission:certificate.request,certificate.create');

        // CA ansehen
        Route::get('/root', [CAReadController::class, 'root'])
            ->middleware('permission:certificate.view');

        Route::get('/intermediate/{id}', [CAReadController::class, 'intermediate'])
            ->middleware('permission:certificate.view');

        Route::get('/intermediate/{id}/chain', [CAReadController::class, 'intermediateChain'])
            ->middleware('permission:certificate.view');

        Route::get('/intermediate', [CAReadController::class, 'latestIntermediate'])
            ->middleware('permission:certificate.view');
    });

    Route::prefix('certificates')->group(function () {

        // CSR Upload: beantragen oder direkt erstellen
        Route::post('/', [CertificateController::class, 'create'])
            ->middleware('permission:certificate.request,certificate.create');

        // Formular: beantragen oder direkt erstellen
        Route::post('/from-data', [CertificateController::class, 'createFromData'])
            ->middleware('permission:certificate.request,certificate.create');

        // Widerrufen
        Route::post('/{id}/revoke', [CertificateController::class, 'revoke'])
            ->middleware('permission:certificate.revoke');

        // Revoked-Liste
        Route::get('/revoked', [CertificateController::class, 'revokedList'])
            ->middleware('permission:certificate.view');

        // Downloads
        Route::get('/{id}/download', [CertificateDownloadController::class, 'download'])
            ->middleware('permission:certificate.download');

        Route::get('/{id}/download-p12', [CertificateDownloadController::class, 'downloadP12'])
            ->middleware('permission:certificate.download');
    });

    // =====================================================
    // 🖥️ FRONTEND ENDPOINTS
    // =====================================================

    Route::get('/dashboard', [DashboardController::class, 'index']);

    Route::get('/user/settings', [UserSettingsController::class, 'show']);
    Route::put('/user/settings', [UserSettingsController::class, 'update']);

    Route::get('/certificates', [FrontendCertificateController::class, 'index'])
        ->middleware('permission:certificate.view');

    Route::get('/ca', [FrontendCAController::class, 'index'])
        ->middleware('permission:certificate.view');

    Route::get('/system/info', [SystemController::class, 'systemInfo']);

    // SETTINGS
    Route::get('/settings', [SettingController::class, 'index'])
        ->middleware('permission:settings.manage');
    Route::get('/settings/{key}', [SettingController::class, 'get']);
    Route::post('/settings', [SettingController::class, 'set'])
        ->middleware('permission:settings.manage');
    Route::post('/system/ntp', [SystemController::class, 'setNtpServer'])
        ->middleware('permission:settings.manage');

    Route::get('/audit-logs', [AuditLogController::class, 'index'])
        ->middleware('permission:settings.manage');
    Route::get('/audit-logs/actions', [AuditLogController::class, 'actions'])
        ->middleware('permission:settings.manage');

    Route::post('/tsa/generate', [TSAController::class, 'generate'])
        ->middleware('permission:settings.manage');

    Route::get('/tsa/status', [TSAController::class, 'status'])
        ->middleware('permission:settings.manage');

    Route::get('/acme/accounts', [InternalACMECAController::class, 'acmeAccounts'])
        ->middleware('permission:settings.manage');

    Route::get('/acme/account-domains', [InternalACMECAController::class, 'acmeAccountDomains'])
        ->middleware('permission:settings.manage');

    Route::post('/acme/accounts/{id}/deactivate', [InternalACMECAController::class, 'deactivateAccount'])
        ->middleware('permission:settings.manage');


    // TEMPLATES
    Route::get('/templates', [CertificateTemplateController::class, 'index'])
        ->middleware('permission:certificate.request,certificate.create');

    Route::post('/templates', [CertificateTemplateController::class, 'store'])
        ->middleware('permission:certificate.request,certificate.create');

    Route::delete('/templates/{id}', [CertificateTemplateController::class, 'destroy'])
        ->middleware('permission:certificate.request,certificate.create');

    // USERS
    Route::get('/users', [UserController::class, 'index'])
        ->middleware('permission:user.view');

    Route::post('/users', [UserController::class, 'store'])
        ->middleware('permission:user.create');

    Route::put('/users/{user}', [UserController::class, 'update'])
        ->middleware('permission:user.update');

    Route::delete('/users/{user}', [UserController::class, 'destroy'])
        ->middleware('permission:user.delete');

    // ROLES
    Route::get('/roles', [RoleController::class, 'index'])
        ->middleware('permission:user.view,settings.manage');

    Route::post('/roles', [PermissionController::class, 'createRole'])
        ->middleware('permission:settings.manage');

    Route::delete('/roles/{id}', [PermissionController::class, 'deleteRole'])
        ->middleware('permission:settings.manage');

    Route::get('/permissions', [PermissionController::class, 'index'])
        ->middleware('permission:settings.manage');

    Route::get('/users/{id}/permissions', [PermissionController::class, 'userPermissions'])
        ->middleware('permission:settings.manage');

    Route::post('/users/{id}/permissions', [PermissionController::class, 'saveUserPermissions'])
        ->middleware('permission:settings.manage');

    Route::get('/roles/{id}/permissions', [PermissionController::class, 'rolePermissions'])
        ->middleware('permission:settings.manage');

    Route::post('/roles/{id}/permissions', [PermissionController::class, 'saveRolePermissions'])
        ->middleware('permission:settings.manage');

    // NOTIFICATIONS
    Route::get('/notifications', [NotificationController::class, 'index'])
        ->middleware('permission:settings.manage');

    Route::post('/notifications/settings', [NotificationController::class, 'saveSettings'])
        ->middleware('permission:settings.manage');

    Route::post('/notifications/events', [NotificationController::class, 'saveEvents'])
        ->middleware('permission:settings.manage');

    Route::post('/notifications/test/{channel}', [NotificationTestController::class, 'test'])
        ->middleware('permission:settings.manage');

    Route::get('/notifications/events', [NotificationEventController::class, 'index'])
        ->middleware('permission:settings.manage');

    Route::post('/notifications/events/{id}/recipients', [NotificationEventController::class, 'updateRecipients'])
        ->middleware('permission:settings.manage');

    // CERTIFICATE REQUESTS
    Route::get('/certificate-requests', [CertificateRequestController::class, 'index'])
        ->middleware('permission:certificate.approve');

    Route::post('/certificate-requests/{id}/approve', [CertificateRequestController::class, 'approve'])
        ->middleware('permission:certificate.approve');

    Route::post('/certificate-requests/{id}/reject', [CertificateRequestController::class, 'reject'])
        ->middleware('permission:certificate.approve');

    Route::patch('/certificates/{id}/team', [CertificateController::class, 'assignTeam'])
        ->middleware('permission:settings.manage');

    // Teams
    Route::get('/teams', [TeamController::class, 'index'])
        ->middleware('permission:settings.manage');
    Route::post('/teams', [TeamController::class, 'store'])
        ->middleware('permission:settings.manage');
    Route::put('/teams/{id}', [TeamController::class, 'update'])
        ->middleware('permission:settings.manage');
    Route::delete('/teams/{id}', [TeamController::class, 'destroy'])
        ->middleware('permission:settings.manage');
});
