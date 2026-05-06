<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "YN kunini belgilash" sahifasi va shunga bog'liq joylar
 * (computeStudentAttemptStatuses, loadScheduleData) uchun
 * eng ko'p ishlatiladigan WHERE+JOIN ustunlariga composite indeks qo'shadi.
 */
return new class extends Migration {
    public function up(): void
    {
        $this->addIndex('exam_schedules', 'idx_examsched_grp_subj_sem', ['group_hemis_id', 'subject_id', 'semester_code']);
        $this->addIndex('student_grades', 'idx_stugrades_stu_subj_sem', ['student_hemis_id', 'subject_id', 'semester_code']);
        $this->addIndex('student_grades', 'idx_stugrades_subj_sem_ttype', ['subject_id', 'semester_code', 'training_type_code']);
        $this->addIndex('attendances', 'idx_attendances_stu_subj_sem', ['student_hemis_id', 'subject_id', 'semester_code']);
        $this->addIndex('yn_submissions', 'idx_ynsub_grp_subj_sem', ['group_hemis_id', 'subject_id', 'semester_code']);
        $this->addIndex('curriculum_subjects', 'idx_currsub_subj_sem', ['subject_id', 'semester_code']);
    }

    public function down(): void
    {
        $this->dropIndex('exam_schedules', 'idx_examsched_grp_subj_sem');
        $this->dropIndex('student_grades', 'idx_stugrades_stu_subj_sem');
        $this->dropIndex('student_grades', 'idx_stugrades_subj_sem_ttype');
        $this->dropIndex('attendances', 'idx_attendances_stu_subj_sem');
        $this->dropIndex('yn_submissions', 'idx_ynsub_grp_subj_sem');
        $this->dropIndex('curriculum_subjects', 'idx_currsub_subj_sem');
    }

    private function addIndex(string $table, string $name, array $cols): void
    {
        if (!Schema::hasTable($table)) {
            return;
        }
        foreach ($cols as $c) {
            if (!Schema::hasColumn($table, $c)) {
                return;
            }
        }
        $exists = collect(DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$name]))->isNotEmpty();
        if ($exists) {
            return;
        }
        Schema::table($table, function (Blueprint $t) use ($cols, $name) {
            $t->index($cols, $name);
        });
    }

    private function dropIndex(string $table, string $name): void
    {
        if (!Schema::hasTable($table)) {
            return;
        }
        $exists = collect(DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$name]))->isNotEmpty();
        if (!$exists) {
            return;
        }
        Schema::table($table, function (Blueprint $t) use ($name) {
            $t->dropIndex($name);
        });
    }
};
