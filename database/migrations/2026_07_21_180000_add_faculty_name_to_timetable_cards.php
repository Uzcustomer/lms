<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dars kartochkasiga fakultet nomi — panjara/Excel sarlavhasida fakultetlarni
 * alohida ustun bloklari sifatida ko'rsatish uchun (Excel dars jadvali kabi:
 * "1-SON DAVOLASH FAKULTETI", "PEDIATRIYA FAKULTETI" va h.k.).
 * Qiymat tasdiqlangan oqim snapshotining fakultet kontekstidan olinadi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('timetable_cards', function (Blueprint $table) {
            $table->string('faculty_name')->nullable()->after('course');
        });
    }

    public function down(): void
    {
        Schema::table('timetable_cards', function (Blueprint $table) {
            $table->dropColumn('faculty_name');
        });
    }
};
