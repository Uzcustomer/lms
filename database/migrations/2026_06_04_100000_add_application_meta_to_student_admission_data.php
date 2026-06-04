<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * student_admission_data ga JSON import metadata uchun 3 ta ustun:
 *   - application_number: JSON "application_number" — ariza raqami (1016 kabi)
 *   - submitted_at: JSON "submitted_at" — abituriyent ariza topshirgan vaqt
 *   - files: JSON "files" — ko'chirilgan fayllar yo'llari, original nomlari va metadata
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_admission_data', function (Blueprint $table) {
            if (!Schema::hasColumn('student_admission_data', 'application_number')) {
                $table->string('application_number', 50)->nullable()->index();
            }
            if (!Schema::hasColumn('student_admission_data', 'submitted_at')) {
                $table->dateTime('submitted_at')->nullable();
            }
            if (!Schema::hasColumn('student_admission_data', 'files')) {
                $table->json('files')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('student_admission_data', function (Blueprint $table) {
            foreach (['application_number', 'submitted_at', 'files'] as $col) {
                if (Schema::hasColumn('student_admission_data', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
