<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bir bulk operatsiya orqali yaratilgan oynalarga umumiy "batch" ID beradi —
 * shu orqali "qaysi fakultetlar bir paytda tanlangan" ekanini ko'rsata olamiz.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('retake_application_windows', function (Blueprint $table) {
            $table->string('creation_batch_id', 64)->nullable()->index()->after('created_by_name');
        });
    }

    public function down(): void
    {
        Schema::table('retake_application_windows', function (Blueprint $table) {
            $table->dropIndex(['creation_batch_id']);
            $table->dropColumn('creation_batch_id');
        });
    }
};
