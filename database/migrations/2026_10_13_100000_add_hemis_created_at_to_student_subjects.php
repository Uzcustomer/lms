<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fan talabaga HEMISda qachon biriktirilgani (student-subject-list created_at).
 * LMS ning created_at ustuni import vaqtini bildiradi (katta importlarda
 * hammasida bir xil), bu ustun esa haqiqiy biriktirilgan vaqtni — kech
 * qo'shilgan talabaning qaysi darslarga baho kutilmasligini aniqlash uchun.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('student_subjects') || Schema::hasColumn('student_subjects', 'hemis_created_at')) {
            return;
        }

        Schema::table('student_subjects', function (Blueprint $table) {
            $table->timestamp('hemis_created_at')->nullable()->after('education_year');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('student_subjects', 'hemis_created_at')) {
            Schema::table('student_subjects', function (Blueprint $table) {
                $table->dropColumn('hemis_created_at');
            });
        }
    }
};
