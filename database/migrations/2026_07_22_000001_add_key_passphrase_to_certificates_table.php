<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            // Verschlüsselte Passphrase für passwortgeschützte Intermediate-Keys.
            // encrypted-Cast im Model erledigt die eigentliche Ver-/Entschlüsselung.
            $table->text('key_passphrase')->nullable()->after('key_path');
        });
    }

    public function down(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->dropColumn('key_passphrase');
        });
    }
};
