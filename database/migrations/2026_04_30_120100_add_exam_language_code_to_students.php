<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('students', 'exam_language_code')) {
            Schema::table('students', function (Blueprint $table) {
                $table->string('exam_language_code', 10)->nullable()->after('language_name');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('students', 'exam_language_code')) {
            Schema::table('students', function (Blueprint $table) {
                $table->dropColumn('exam_language_code');
            });
        }
    }
};
