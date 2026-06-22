<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 🔥 users.team_id
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('team_id')
                ->nullable()
                ->after('role')
                ->constrained('teams')
                ->onDelete('set null');
        });

        // 🔥 certificates.team_id
        Schema::table('certificates', function (Blueprint $table) {
            $table->foreignId('team_id')
                ->nullable()
                ->after('requested_by')
                ->constrained('teams')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['team_id']);
            $table->dropColumn('team_id');
        });

        Schema::table('certificates', function (Blueprint $table) {
            $table->dropForeign(['team_id']);
            $table->dropColumn('team_id');
        });
    }
};