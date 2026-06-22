<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AD-PKI Initial Schema Migration
 * Basiert auf adpki_schema_v1.sql (Stand: 2026-05-10)
 * Ersetzt alle vorherigen einzelnen Migrations.
 * Neu hinzugefügt: audit_logs Tabelle
 */
return new class extends Migration
{
    public function up(): void
    {
        // ---------------------------------------------------------------
        // Cache
        // ---------------------------------------------------------------
        Schema::create('cache', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value');
            $table->integer('expiration');

            $table->index('expiration', 'cache_expiration_index');
        });

        Schema::create('cache_locks', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('owner');
            $table->integer('expiration');

            $table->index('expiration', 'cache_locks_expiration_index');
        });

        // ---------------------------------------------------------------
        // Sessions
        // ---------------------------------------------------------------
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->text('payload');
            $table->integer('last_activity')->index();
        });

        // ---------------------------------------------------------------
        // Jobs / Queue
        // ---------------------------------------------------------------
        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });

        Schema::create('job_batches', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->integer('total_jobs');
            $table->integer('pending_jobs');
            $table->integer('failed_jobs');
            $table->longText('failed_job_ids');
            $table->mediumText('options')->nullable();
            $table->integer('cancelled_at')->nullable();
            $table->integer('created_at');
            $table->integer('finished_at')->nullable();
        });

        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });

        // ---------------------------------------------------------------
        // Password Reset Tokens
        // ---------------------------------------------------------------
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        // ---------------------------------------------------------------
        // Roles
        // ---------------------------------------------------------------
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        // ---------------------------------------------------------------
        // Users
        // ---------------------------------------------------------------
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('username')->unique();
            $table->string('firstname');
            $table->string('lastname');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->foreignId('role_id')->nullable()->constrained('roles')->nullOnDelete();
            $table->rememberToken();
            $table->timestamps();
        });

        // ---------------------------------------------------------------
        // Personal Access Tokens (Sanctum)
        // ---------------------------------------------------------------
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->text('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index('expires_at', 'personal_access_tokens_expires_at_index');
        });

        // ---------------------------------------------------------------
        // Permissions & RBAC
        // ---------------------------------------------------------------
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('label');
            $table->timestamps();
        });

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();

            $table->unique(['role_id', 'permission_id']);
        });

        Schema::create('user_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();

            $table->unique(['user_id', 'permission_id']);
        });

        // ---------------------------------------------------------------
        // Settings
        // ---------------------------------------------------------------
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        // ---------------------------------------------------------------
        // Certificate Templates
        // ---------------------------------------------------------------
        Schema::create('certificate_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('ou')->nullable();
            $table->string('organization')->nullable();
            $table->string('locality')->nullable();
            $table->string('state')->nullable();
            $table->string('country', 5)->nullable();
            $table->string('email')->nullable();
            $table->timestamps();
        });

        // ---------------------------------------------------------------
        // Certificates
        // ---------------------------------------------------------------
        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('common_name');
            $table->text('san')->nullable();
            $table->string('serial_number')->nullable()->unique();
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_to')->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('certificates')->nullOnDelete();
            $table->boolean('is_acme')->default(false);
            $table->string('crt_path')->nullable();
            $table->string('key_path')->nullable();
            $table->string('chain_path')->nullable();
            $table->string('crl_path')->nullable();
            $table->boolean('revoked')->default(false);
            $table->timestamp('revoked_at')->nullable();
            $table->string('revocation_reason')->nullable();
            $table->string('key_type', 20)->nullable();
            $table->integer('key_size')->nullable();
            $table->string('curve', 20)->nullable();
            $table->string('status')->default('issued');

            // Approval Workflow
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->jsonb('request_data')->nullable();

            $table->timestamps();
        });

        // ---------------------------------------------------------------
        // Notification Events
        // ---------------------------------------------------------------
        Schema::create('notification_events', function (Blueprint $table) {
            $table->id();
            $table->string('event')->unique();
            $table->boolean('enabled')->default(true);
            $table->boolean('mail')->default(true);
            $table->boolean('webhook')->default(true);
            $table->boolean('telegram')->default(true);
            $table->text('title_template')->nullable();
            $table->text('message_template')->nullable();
            $table->string('recipient_type')->default('admin');
            $table->text('recipient_value')->nullable();
            $table->timestamps();
        });

        Schema::create('notification_event_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_event_id')
                ->constrained('notification_events')
                ->cascadeOnDelete();
            $table->string('role');
            $table->timestamps();
        });

        // ---------------------------------------------------------------
        // Scheduler Runs
        // ---------------------------------------------------------------
        Schema::create('scheduler_runs', function (Blueprint $table) {
            $table->id();
            $table->string('command')->unique();
            $table->timestamp('last_run_at')->nullable();
            $table->string('status')->default('OK');
            $table->text('message')->nullable();
            $table->timestamps();
        });

        // ---------------------------------------------------------------
        // Audit Logs (NEU)
        // ---------------------------------------------------------------
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 100);        // z.B. certificate.download.key
            $table->string('subject_type', 100)->nullable(); // z.B. Certificate
            $table->bigInteger('subject_id')->nullable();    // z.B. cert ID
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->jsonb('meta')->nullable();               // zusätzliche Infos
            $table->timestamp('created_at')->useCurrent();

            // Indizes für häufige Abfragen in der WebUI
            $table->index('user_id');
            $table->index('action');
            $table->index(['subject_type', 'subject_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        // Reihenfolge beachten wegen Foreign Keys
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('scheduler_runs');
        Schema::dropIfExists('notification_event_recipients');
        Schema::dropIfExists('notification_events');
        Schema::dropIfExists('certificates');
        Schema::dropIfExists('certificate_templates');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('user_permissions');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('users');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('failed_jobs');
        Schema::dropIfExists('job_batches');
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('cache_locks');
        Schema::dropIfExists('cache');
    }
};
