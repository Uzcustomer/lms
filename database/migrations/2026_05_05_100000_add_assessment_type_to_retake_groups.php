<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('retake_groups', function (Blueprint $table) {
            // Guruhning baholash turi:
            //   oske       — faqat OSKE topshiriladi
            //   test       — faqat TEST topshiriladi
            //   oske_test  — avval OSKE keyin TEST
            //   sinov_fan  — sinov fan
            $table->string('assessment_type', 20)->nullable()->after('end_date');

            // OSKE va TEST sanalari (faqat kun — vaqtni Test markazi belgilaydi)
            $table->date('oske_date')->nullable()->after('assessment_type');
            $table->date('test_date')->nullable()->after('oske_date');
        });
    }

    public function down(): void
    {
        Schema::table('retake_groups', function (Blueprint $table) {
            $table->dropColumn(['assessment_type', 'oske_date', 'test_date']);
        });
    }
};
