<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * YN urinishlar (asosiy/12a/12b) ni izlash uchun schema kengaytirilishi.
 *
 * - student_grades.attempt: 1 = asosiy urinish, 2 = 12a (1-urinish), 3 = 12b (2-urinish).
 *   Sababli retake yozuvlari ham mos urinishga tegishli bo'ladi.
 * - yn_submissions: har urinish alohida submission. attempt + status + finalize.
 * - excuse_grade_openings: sababli ariza qaysi urinishga va qaysi qism (jn/mt/oski/test).
 * - yn_form_corrections: yakuniy bo'lgan shakldan keyin kelgan tuzatishlar audit'i.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('student_grades') && !Schema::hasColumn('student_grades', 'attempt')) {
            Schema::table('student_grades', function (Blueprint $table) {
                $table->unsignedTinyInteger('attempt')->default(1)->after('retake_was_sababli');
                $table->index(['student_hemis_id', 'subject_id', 'semester_code', 'attempt'], 'sg_student_subj_sem_attempt_idx');
            });
        }

        if (Schema::hasTable('yn_submissions')) {
            Schema::table('yn_submissions', function (Blueprint $table) {
                if (!Schema::hasColumn('yn_submissions', 'attempt')) {
                    $table->unsignedTinyInteger('attempt')->default(1)->after('group_hemis_id');
                }
                if (!Schema::hasColumn('yn_submissions', 'status')) {
                    $table->string('status', 20)->default('draft')->after('attempt');
                }
                if (!Schema::hasColumn('yn_submissions', 'finalized_at')) {
                    $table->timestamp('finalized_at')->nullable()->after('status');
                }
                if (!Schema::hasColumn('yn_submissions', 'finalized_by_id')) {
                    $table->unsignedBigInteger('finalized_by_id')->nullable()->after('finalized_at');
                    $table->string('finalized_by_guard', 20)->nullable()->after('finalized_by_id');
                }
            });

            // Mavjud unique cheklovni attempt bilan kengaytirish
            try {
                Schema::table('yn_submissions', function (Blueprint $table) {
                    $table->dropUnique('yn_submissions_unique');
                });
            } catch (\Throwable $e) {
                // unique allaqachon olib tashlangan bo'lishi mumkin
            }

            try {
                Schema::table('yn_submissions', function (Blueprint $table) {
                    $table->unique(['subject_id', 'semester_code', 'group_hemis_id', 'attempt'], 'yn_submissions_attempt_unique');
                });
            } catch (\Throwable $e) {
                // dublikat indeks
            }
        }

        if (Schema::hasTable('excuse_grade_openings')) {
            Schema::table('excuse_grade_openings', function (Blueprint $table) {
                if (!Schema::hasColumn('excuse_grade_openings', 'attempt')) {
                    $table->unsignedTinyInteger('attempt')->default(1)->after('subject_id');
                }
                if (!Schema::hasColumn('excuse_grade_openings', 'assessment_type')) {
                    // jn / mt / oski / test — qaysi qism uchun ochilgan; null = umumiy (orqa muvofiqlik)
                    $table->string('assessment_type', 20)->nullable()->after('attempt');
                }
            });
        }

        if (!Schema::hasTable('yn_form_corrections')) {
            Schema::create('yn_form_corrections', function (Blueprint $table) {
                $table->id();
                $table->string('subject_id');
                $table->string('semester_code');
                $table->string('group_hemis_id');
                $table->string('student_hemis_id');
                $table->unsignedTinyInteger('attempt')->default(1);
                $table->string('correction_type', 50);
                // Tahminiy turlar:
                //  moved_to_qoshimcha   — sababli retake orqali qo'shimchaga ko'chdi
                //  removed_from_12a     — sababli orqali asosiyga qaytdi (12a dan chiqdi)
                //  removed_from_12b     — sababli orqali 12a ga qaytdi (12b dan chiqdi)
                //  late_sababli         — yakuniy bo'lgandan keyin sababli keldi
                //  passed_via_qoshimcha — qo'shimchaga tushib o'tdi
                $table->string('from_form', 30)->nullable();
                $table->string('to_form', 30)->nullable();
                $table->unsignedBigInteger('absence_excuse_id')->nullable();
                $table->text('reason')->nullable();
                $table->timestamp('performed_at')->nullable();
                $table->unsignedBigInteger('performed_by_id')->nullable();
                $table->string('performed_by_guard', 20)->nullable();
                $table->string('performed_by_name')->nullable();
                $table->timestamps();

                $table->index(['subject_id', 'semester_code', 'group_hemis_id'], 'ynfc_group_subj_idx');
                $table->index(['student_hemis_id', 'subject_id'], 'ynfc_student_subj_idx');
                $table->index('absence_excuse_id', 'ynfc_excuse_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('yn_form_corrections');

        if (Schema::hasTable('excuse_grade_openings')) {
            Schema::table('excuse_grade_openings', function (Blueprint $table) {
                if (Schema::hasColumn('excuse_grade_openings', 'assessment_type')) {
                    $table->dropColumn('assessment_type');
                }
                if (Schema::hasColumn('excuse_grade_openings', 'attempt')) {
                    $table->dropColumn('attempt');
                }
            });
        }

        if (Schema::hasTable('yn_submissions')) {
            try {
                Schema::table('yn_submissions', function (Blueprint $table) {
                    $table->dropUnique('yn_submissions_attempt_unique');
                });
            } catch (\Throwable $e) {}

            try {
                Schema::table('yn_submissions', function (Blueprint $table) {
                    $table->unique(['subject_id', 'semester_code', 'group_hemis_id'], 'yn_submissions_unique');
                });
            } catch (\Throwable $e) {}

            Schema::table('yn_submissions', function (Blueprint $table) {
                foreach (['finalized_by_guard', 'finalized_by_id', 'finalized_at', 'status', 'attempt'] as $col) {
                    if (Schema::hasColumn('yn_submissions', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('student_grades') && Schema::hasColumn('student_grades', 'attempt')) {
            Schema::table('student_grades', function (Blueprint $table) {
                try { $table->dropIndex('sg_student_subj_sem_attempt_idx'); } catch (\Throwable $e) {}
                $table->dropColumn('attempt');
            });
        }
    }
};
