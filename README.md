# AD-PKI Backend

**English** · [Deutsch](README.de.md)

[![PHP](https://img.shields.io/badge/PHP-8.4-777BB4)](https://www.php.net/)
[![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20)](https://laravel.com/)
[![PostgreSQL](https://img.shields.io/badge/PostgreSQL-17-336791)](https://www.postgresql.org/)
[![License: AGPL v3](https://img.shields.io/badge/License-AGPL%20v3-blue.svg)](LICENSE)

The central management and API layer of the AD-PKI platform. It handles authentication, RBAC, certificate workflows, audit logging, ACME management, notifications, and communication with the cryptographic AD-PKI CA service.

The backend does **not** perform cryptographic operations. All signing operations are delegated to the CA service.

## Features

- **Certificate management** — Request certificates through CSR upload or a form, approve or reject requests, revoke certificates, and download certificates in PEM or PKCS#12 format
- **CA management** — Import root and intermediate CAs and retrieve certificate chains
- **ACME integration** — Provide settings, persist and revoke certificates, and manage accounts for an external ACME/CA service
- **Protocol proxies** — Proxy OCSP, CRL, and RFC 3161 timestamp requests
- **Access control** — Manage roles, granular permissions, and teams with tenant isolation
- **Audit logging** — Record all security-relevant actions
- **Notifications** — Send email, webhook, and Telegram notifications using configurable event templates
- **Real-time updates** — Broadcast certificate status and system health through Laravel Reverb
- **Scheduled jobs** — Detect certificate expiration, perform automatic revocation, and check system health

## Architecture

```text
Frontend (Vue)  ─┐
                  ├──►  AD-PKI Backend (Laravel, this repository)  ──►  AD-PKI CA Service (Go)
ACME clients  ───┘            │
                               ▼
                          PostgreSQL
```

- **Backend (this repository):** Handles authentication, RBAC, policy decisions, metadata, auditing, notifications, and real-time updates.
- **CA service (separate Go repository):** Signs certificates, manages private keys, and cryptographically handles OCSP, CRL, and TSA requests.
- **Communication:** The backend authenticates with the CA service using a shared secret in the `X-CA-Token` header.

## Technology Stack

- PHP 8.4+
- Laravel 13
- PostgreSQL
- Laravel Sanctum for token authentication
- Laravel Reverb for WebSocket and real-time broadcasting
- Vite for asset builds

## Setup

### Requirements

- PHP 8.4+
- Composer
- PostgreSQL
- Node.js for building assets
- A running AD-PKI CA service

### Installation

```bash
composer install
cp .env.example .env
php artisan key:generate
# Configure .env: DB_*, CA_URL, CA_TOKEN, REVERB_*, MAIL_*, etc.
php artisan migrate --seed
npm install && npm run build
```

### Development

Run the server, queue worker, logs, and Vite in parallel:

```bash
composer dev
```

Start the Reverb server separately:

```bash
php artisan reverb:start
```

## Configuration

All required environment variables are documented in [`.env.example`](.env.example), including:

| Variable | Description |
| --- | --- |
| `CA_URL` / `CA_TOKEN` | CA service connection |
| `DB_*` | PostgreSQL credentials |
| `REVERB_*` | Internal and public WebSocket endpoints |
| `MAIL_*` | Email delivery for notifications |

Runtime settings—including webhook and Telegram credentials, CRL/OCSP base URLs, and policies—are managed through the `settings` table and the admin frontend, not through `.env`.

## Documentation

See [`docs/BACKEND.md`](docs/BACKEND.md) for a detailed technical description of the API, data model, RBAC, and background processes.

## Security

- Private keys are never stored in the backend database.
- All signing operations take place exclusively in the CA service.
- The backend never signs certificates directly. All cryptographic operations are delegated to the AD-PKI CA service.
- Report security-related findings privately to the maintainers rather than opening a public issue.