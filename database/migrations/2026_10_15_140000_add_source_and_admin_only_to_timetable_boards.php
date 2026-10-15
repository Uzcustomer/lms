<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doska: fanlar manbai (ishchi reja yoki HEMIS o'quv rejasi — KTR sahifasidagi fanlar)
 * va faqat admin ko'radigan (sinov) doska belgisi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('timetable_boards', function (Blueprint $table) {
            $table->string('subject_source', 20)->default('ishchi')->after('kind');
            $table->boolean('admin_only')->default(false)->after('subject_source');
        });
    }

    public function down(): void
    {
        Schema::table('timetable_boards', function (Blueprint $table) {
            $table->dropColumn(['subject_source', 'admin_only']);
        });
    }
};
