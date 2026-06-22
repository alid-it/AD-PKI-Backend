# AD-PKI Backend

[English](README.md) · **Deutsch**

[![PHP](https://img.shields.io/badge/PHP-8.4-777BB4)](https://www.php.net/)
[![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20)](https://laravel.com/)
[![PostgreSQL](https://img.shields.io/badge/PostgreSQL-17-336791)](https://www.postgresql.org/)
[![License: AGPL v3](https://img.shields.io/badge/License-AGPL%20v3-blue.svg)](LICENSE)

Die zentrale Management- und API-Schicht der AD-PKI-Plattform. Sie übernimmt Authentifizierung, RBAC, Zertifikats-Workflows, Audit-Logging, ACME-Management, Benachrichtigungen und die Kommunikation mit dem kryptografischen AD-PKI CA-Service.

Das Backend führt selbst **keine** kryptografischen Operationen aus. Alle Signiervorgänge werden an den CA-Service delegiert.

## Funktionen

- **Zertifikatsverwaltung** — Zertifikate per CSR-Upload oder Formular beantragen, Anträge genehmigen oder ablehnen, Zertifikate widerrufen und im PEM- oder PKCS#12-Format herunterladen
- **CA-Verwaltung** — Root- und Intermediate-CAs importieren und Zertifikatsketten abrufen
- **ACME-Integration** — Einstellungen bereitstellen, Zertifikate persistieren und widerrufen sowie Accounts für einen externen ACME/CA-Service verwalten
- **Protokoll-Proxys** — OCSP-, CRL- und RFC-3161-Timestamp-Anfragen weiterleiten
- **Zugriffskontrolle** — Rollen, granulare Berechtigungen und Teams mit Mandantentrennung verwalten
- **Audit-Logging** — Alle sicherheitsrelevanten Aktionen protokollieren
- **Benachrichtigungen** — E-Mail-, Webhook- und Telegram-Benachrichtigungen mit konfigurierbaren Event-Templates versenden
- **Echtzeit-Updates** — Zertifikatsstatus und Systemzustand über Laravel Reverb übertragen
- **Geplante Jobs** — Zertifikatsabläufe erkennen, automatische Widerrufe durchführen und den Systemzustand prüfen

## Architektur

```text
Frontend (Vue)  ─┐
                  ├──►  AD-PKI Backend (Laravel, dieses Repository)  ──►  AD-PKI CA-Service (Go)
ACME-Clients  ───┘            │
                               ▼
                          PostgreSQL
```

- **Backend (dieses Repository):** Übernimmt Authentifizierung, RBAC, Policy-Entscheidungen, Metadaten, Audit-Logging, Benachrichtigungen und Echtzeit-Updates.
- **CA-Service (separates Go-Repository):** Signiert Zertifikate, verwaltet Private Keys und verarbeitet OCSP-, CRL- und TSA-Anfragen kryptografisch.
- **Kommunikation:** Das Backend authentifiziert sich beim CA-Service über ein Shared Secret im `X-CA-Token`-Header.

## Technologie-Stack

- PHP 8.4+
- Laravel 13
- PostgreSQL
- Laravel Sanctum für Token-Authentifizierung
- Laravel Reverb für WebSockets und Echtzeit-Broadcasting
- Vite für Asset-Builds

## Einrichtung

### Voraussetzungen

- PHP 8.4+
- Composer
- PostgreSQL
- Node.js für den Asset-Build
- Ein laufender AD-PKI CA-Service

### Installation

```bash
composer install
cp .env.example .env
php artisan key:generate
# .env anpassen: DB_*, CA_URL, CA_TOKEN, REVERB_*, MAIL_* etc.
php artisan migrate --seed
npm install && npm run build
```

### Entwicklung

Server, Queue-Worker, Logs und Vite parallel ausführen:

```bash
composer dev
```

Den Reverb-Server separat starten:

```bash
php artisan reverb:start
```

## Konfiguration

Alle benötigten Umgebungsvariablen sind in [`.env.example`](.env.example) dokumentiert, darunter:

| Variable | Beschreibung |
| --- | --- |
| `CA_URL` / `CA_TOKEN` | Verbindung zum CA-Service |
| `DB_*` | PostgreSQL-Zugangsdaten |
| `REVERB_*` | Interne und öffentliche WebSocket-Endpunkte |
| `MAIL_*` | E-Mail-Versand für Benachrichtigungen |

Zur Laufzeit veränderliche Einstellungen—darunter Webhook- und Telegram-Zugangsdaten, CRL/OCSP-Basis-URLs und Policies—werden über die `settings`-Tabelle und das Admin-Frontend verwaltet, nicht über `.env`.

## Dokumentation

Eine detaillierte technische Beschreibung der API, des Datenmodells, von RBAC und der Hintergrundprozesse befindet sich in [`docs/BACKEND.md`](docs/BACKEND.md).

## Sicherheit

- Private Keys werden niemals in der Backend-Datenbank gespeichert.
- Alle Signiervorgänge finden ausschließlich im CA-Service statt.
- Das Backend signiert niemals selbst Zertifikate. Alle kryptografischen Operationen werden an den AD-PKI CA-Service delegiert.
- Sicherheitsrelevante Funde sind vertraulich an die Maintainer zu melden, nicht als öffentliches Issue.
