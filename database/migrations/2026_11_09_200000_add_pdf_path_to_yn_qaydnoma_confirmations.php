<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dekan tasdiqlaganda qaydnoma PDF i avtomatik tayyorlanadi (public disk) — Vedomost tekshirishda
 * shu fayl yuklab olinadi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('yn_qaydnoma_confirmations', function (Blueprint $table) {
            $table->string('pdf_path')->nullable()->after('document_verification_id');
        });
    }

    public function down(): void
    {
        Schema::table('yn_qaydnoma_confirmations', function (Blueprint $table) {
            $table->dropColumn('pdf_path');
        });
    }
};
