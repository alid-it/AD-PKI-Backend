# AD-PKI Backend — Technische Dokumentation

## Verzeichnisstruktur

| Pfad | Inhalt |
|---|---|
| `app/Http/Controllers` | API-Controller, unterteilt in Root- (CA/ACME/Setup), `Frontend/`- (UI-CRUD) und `CA/`-Controller |
| `app/Http/Middleware` | `CATokenMiddleware` (CA-Service-Auth), `CheckPermission` (RBAC) |
| `app/Models` | Eloquent-Modelle: User, Role, Permission, Certificate, Team, Setting, AuditLog, NotificationEvent, ... |
| `app/Services/CA` | `GoCAService` — HTTP-Client zum externen CA-Service |
| `app/Services/Notifications` | Notification-Engine, Channels (Mail/Webhook/Telegram), Template-Rendering |
| `app/Services/System` | Scheduler-Tracking |
| `app/Console/Commands` | Geplante Befehle (Ablauf-Check, Auto-Revoke, Health-Check, Setup) |
| `app/Events` | Broadcast-Events (`AuditLogCreated`, `CaHealthChanged`) |
| `routes/api.php` | Sämtliche API-Routen |
| `routes/channels.php` | Broadcast-Channel-Autorisierung |
| `config/services.php` | `services.ca.url` / `services.ca.token` |
| `database/migrations` | Initial-Schema + inkrementelle Erweiterungen (Teams, Preferences, ACME) |
| `database/seeders` | Rollen, Permissions, Rollen-Permission-Zuordnung, Notification-Events |

## API-Übersicht

### Öffentlich (keine Auth)
- `POST /login`
- `GET /crl/{id}.pem`
- `GET /config` (Reverb-Connection-Parameter für das Frontend)
- `GET|POST /ocsp`, `GET|POST /timestamp`

### Intern — CA-Service → Backend (`middleware('ca.token')`, Prefix `/internal`)
Authentifiziert über Shared-Secret-Header `X-CA-Token` (siehe `CATokenMiddleware`).
- `GET /internal/acme-settings`
- `POST /internal/acme-certificate`
- `POST /internal/acme/revoke`
- `POST /internal/import-root`, `POST /internal/import-intermediate`
- `POST /internal/setup/create-admin`
- `GET /internal/crl/revoked`

### Authentifiziert (`auth:sanctum`)
Alle weiteren Endpunkte erfordern ein gültiges Sanctum-Token; viele zusätzlich
eine Permission über `middleware('permission:<key>[,<key>...]')` (OR-Verknüpfung
bei mehreren Keys). Bereiche: `/me`, `/me/preferences`, `/dns/lookup`,
`/ca/*`, `/certificates/*`, `/dashboard`, `/system/*`, `/settings/*`,
`/audit-logs/*`, `/tsa/*`, `/acme/*`, `/templates/*`, `/users/*`, `/roles/*`,
`/permissions/*`, `/notifications/*`, `/certificate-requests/*`, `/teams/*`.

Genaue Permission-Zuordnung siehe `routes/api.php` direkt — wird hier
bewusst nicht dupliziert, um Drift zwischen Doku und Code zu vermeiden.

## RBAC-Modell

- **Rollen** (`roles`) besitzen Permissions über `role_permissions`.
- **User** können zusätzlich direkte Permissions über `user_permissions`
  erhalten (`User::hasPermission()` prüft beide Quellen).
- **Teams** (`teams`, `users.team_id`, `certificates.team_id`) ermöglichen
  Mandantentrennung; Sichtbarkeits-Permissions unterscheiden zwischen
  `certificate.view`, `certificate.view.team` und `certificate.view.all`.
- Beispiel-Permission-Keys: `certificate.request`, `certificate.create`,
  `certificate.revoke`, `certificate.download`, `certificate.approve`,
  `user.view`, `user.create`, `user.update`, `user.delete`,
  `settings.manage`.

## Zertifikats-Datenmodell

Tabelle `certificates` (Auswahl wichtiger Felder):

- Identität: `type`, `common_name`, `san`, `serial_number`
- Gültigkeit: `valid_from`, `valid_to`
- Hierarchie: `parent_id` (Self-Reference für Chain)
- Dateipfade (außerhalb der DB liegend): `crt_path`, `key_path`,
  `chain_path`, `crl_path`
- Status: `status` (`issued` default), `revoked`, `revoked_at`,
  `revocation_reason`
- Schlüsselparameter: `key_type`, `key_size`, `curve`
- Genehmigungs-Workflow: `requested_by`, `approved_by`/`approved_at`,
  `rejected_by`/`rejected_at`/`rejection_reason`, `request_data` (JSONB)
- ACME: `is_acme`, `acme_account_id`
- Mandant: `team_id`

Workflow: `requested` → `approved`/`rejected` → (bei approved) `issued`
→ ggf. `revoked`.

## CA-Service-Schnittstelle (`GoCAService`)

HTTP-Client mit Header `X-CA-Token` gegen `CA_URL`. Methoden:

- `signCsr()` — signiert eine eingereichte CSR
- `signFromData()` — erstellt Schlüssel+Zertifikat serverseitig aus
  Formulardaten
- `signAcmeCsr()` — signiert CSRs aus dem ACME-Flow
- `importRoot()` / `importIntermediate()` — importiert CA-Material
- `clearOcspCache()` — invalidiert den OCSP-Cache des CA-Service

CRL-/OCSP-URLs werden dynamisch aus den `settings`-Einträgen
`crl_base_url` / `ocsp_base_url` plus dem `crl_path` des jeweiligen
Intermediate-Zertifikats zusammengesetzt.

## ACME-Flow

1. ACME-Service fragt `GET /internal/acme-settings` ab (Intermediate,
   Validity, DNS-Server, CRL/OCSP-URLs).
2. Nach Signierung meldet der ACME-Service das Zertifikat über
   `POST /internal/acme-certificate` zurück an das Backend.
3. Revokes laufen über `POST /internal/acme/revoke`.
4. Das Admin-Frontend verwaltet ACME-Accounts über
   `/acme/accounts`, `/acme/account-domains`,
   `/acme/accounts/{id}/deactivate`.
5. Events `acme_certificate_issued`, `acme_certificate_revoked`,
   `acme_account_deactivated` lösen konfigurierbare Benachrichtigungen aus.

## Benachrichtigungssystem

- `NotificationEngine` orchestriert Versand über mehrere Channels:
  `MailChannel`, `WebhookChannel`, `TelegramChannel`.
- Zugangsdaten/Endpunkte (Webhook-URL/-Secret, Telegram-Bot-Token/-Chat-ID,
  Mail-Konfiguration) liegen **nicht** in `.env`, sondern in der
  `settings`-Tabelle und werden über das Admin-Frontend gepflegt.
- `notification_events` definiert je Event Titel-/Nachrichten-Templates
  und welche Channels aktiv sind; `notification_event_recipients` ordnet
  Events Empfänger-Rollen zu.
- `TemplateRenderer` / `TemplateVariableProvider` füllen Platzhalter in den
  Templates mit Kontextdaten (z.B. Zertifikatsdetails).

## Geplante Jobs (Scheduler)

Definiert in `app/Console/Kernel.php`:

| Befehl | Intervall | Zweck |
|---|---|---|
| `certificates:check-expiring` | alle 5 Minuten | erkennt bald ablaufende Zertifikate, löst Benachrichtigungen aus |
| `certs:auto-revoke` | alle 10 Minuten | widerruft automatisch abgelaufene/markierte Zertifikate |
| `system:check-health` | jede Minute | prüft CRL-/OCSP-Erreichbarkeit, NTP/DNS-Status |

Jeder Lauf wird in der Tabelle `scheduler_runs` (Status + letzte
Ausführung) protokolliert. Aktivierung über `AUTO_SCHEDULER` in `.env`.

## Broadcasting / Reverb

- `BROADCAST_CONNECTION=reverb`, eigener WebSocket-Server.
- Getrennte interne (`REVERB_HOST/PORT/SCHEME`) und öffentliche
  (`REVERB_PUBLIC_HOST/PORT/SCHEME`) Konfiguration, da Laravel intern
  direkt mit Reverb spricht, während Browser-Clients ggf. über einen
  Reverse-Proxy/öffentlichen Hostnamen verbinden.
- Events: `CaHealthChanged` (Channel `system-health`, öffentlich lesbar),
  `AuditLogCreated`. Private User-Channel-Autorisierung in
  `routes/channels.php`.
- `GET /config` liefert dem Frontend die zur Verbindung nötigen
  öffentlichen Parameter (App-Key ist beim Pusher-kompatiblen Protokoll
  bewusst öffentlich, kein Secret).

## Bekannte Einschränkungen

- `tests/` enthält bislang nur die Laravel-Default-Stubs, keine
  projektspezifischen Tests.
