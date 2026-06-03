<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('curriculum_subjects', function (Blueprint $table) {
            // Fanning yopilish shakli:
            //   oski       — faqat OSKI (og'zaki) topshiriladi
            //   test       — faqat yakuniy test topshiriladi
            //   oski_test  — ikkalasi ham
            //   none       — yakuniy nazorat yo'q (amaliyot, internship va h.k.)
            //   NULL       — hali belgilanmagan; yn kuni sahifasida qo'lda UX qoladi
            $table->string('closing_form', 20)->nullable()->after('subject_exam_types');
            $table->index('closing_form', 'idx_cs_closing_form');
        });
    }

    public function down(): void
    {
        Schema::table('curriculum_subjects', function (Blueprint $table) {
            $table->dropIndex('idx_cs_closing_form');
            $table->dropColumn('closing_form');
        });
    }
};
