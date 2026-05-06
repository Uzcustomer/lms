<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DebugYnPullikStudent extends Command
{
    protected $signature = 'debug:yn-pullik-student
        {student_hemis_id : Talaba HEMIS ID}
        {group_hemis_id : Guruh HEMIS ID}
        {subject_id : Fan ID}
        {semester_code : Semester code}';

    protected $description = 'YN pullik statusi nega chiqqanini aniq manbalar bilan chiqaradi';

    public function handle(): int
    {
        $hid = (string) $this->argument('student_hemis_id');
        $gid = (string) $this->argument('group_hemis_id');
        $sid = (int) $this->argument('subject_id');
        $sem = (string) $this->argument('semester_code');

        $minLimit = 60;

        $year = DB::table('semesters')->where('semester_code', $sem)->value('education_year');

        $ynQ = DB::table('yn_student_grades as ysg')
            ->join('yn_submissions as yns', 'yns.id', '=', 'ysg.yn_submission_id')
            ->where('ysg.student_hemis_id', $hid)
            ->where('yns.group_hemis_id', $gid)
            ->where('yns.subject_id', $sid)
            ->where('yns.semester_code', $sem);

        if (Schema::hasColumn('yn_submissions', 'education_year') && $year) {
            $ynQ->where('yns.education_year', $year);
        }

        $ynRows = $ynQ->orderByDesc('ysg.created_at')
            ->select('ysg.id', 'ysg.jn', 'ysg.mt', 'ysg.created_at', 'yns.id as yn_submission_id', 'yns.attempt', 'yns.education_year')
            ->limit(10)
            ->get();

        $latestSnapshot = $ynRows->first();

        $jnAvg = DB::table('student_grades')
            ->whereNull('deleted_at')
            ->where('student_hemis_id', $hid)
            ->where('subject_id', $sid)
            ->where('semester_code', $sem)
            ->whereNotIn('training_type_code', [11, 99, 100, 101, 102, 103])
            ->whereRaw('COALESCE(retake_grade, grade) IS NOT NULL')
            ->avg(DB::raw('COALESCE(retake_grade, grade)'));

        $manualMtRows = DB::table('student_grades')
            ->whereNull('deleted_at')
            ->where('student_hemis_id', $hid)
            ->where('subject_id', $sid)
            ->where('semester_code', $sem)
            ->where('training_type_code', 99)
            ->whereNull('lesson_date')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->select('id', 'grade', 'retake_grade', 'updated_at', 'created_at')
            ->limit(10)
            ->get();

        $mtEffective = optional($manualMtRows->first())->retake_grade ?? optional($manualMtRows->first())->grade;

        $jn = $latestSnapshot ? (((int)$latestSnapshot->jn) > 0 ? (int)$latestSnapshot->jn : null) : null;
        $mt = $latestSnapshot ? (((int)$latestSnapshot->mt) > 0 ? (int)$latestSnapshot->mt : null) : null;

        if ($jn === null && $jnAvg !== null) {
            $jn = (int) round((float) $jnAvg, 0, PHP_ROUND_HALF_UP);
        }
        if ($mt === null && $mtEffective !== null) {
            $mt = (int) round((float) $mtEffective, 0, PHP_ROUND_HALF_UP);
        }

        $absentOff = (float) DB::table('attendances')
            ->where('student_hemis_id', $hid)
            ->where('subject_id', $sid)
            ->where('semester_code', $sem)
            ->whereNotIn('training_type_code', [99, 100, 101, 102])
            ->sum('absent_off');

        $subject = DB::table('curriculum_subjects')
            ->where('subject_id', $sid)
            ->where('semester_code', $sem)
            ->select('subject_details', 'total_acload')
            ->first();

        $aud = 0.0;
        if ($subject) {
            $details = is_string($subject->subject_details) ? json_decode($subject->subject_details, true) : $subject->subject_details;
            if (is_array($details)) {
                foreach ($details as $d) {
                    $tc = (string) ($d['trainingType']['code'] ?? '');
                    if ($tc !== '17') {
                        $aud += (float) ($d['academic_load'] ?? 0);
                    }
                }
            }
            if ($aud <= 0) {
                $aud = (float) ($subject->total_acload ?? 0);
            }
        }

        $davomatPct = $aud > 0 ? round(($absentOff / $aud) * 100, 2) : 0.0;

        $jnLow = ($jn !== null) && ($jn < $minLimit);
        $mtLow = ($mt !== null) && ($mt < $minLimit);
        $isPullik = $jnLow || $mtLow || ($davomatPct >= 25);

        $this->info('=== INPUT ===');
        $this->line("student_hemis_id={$hid}, group={$gid}, subject={$sid}, sem={$sem}, year=" . ($year ?? 'null'));

        $this->info('=== SNAPSHOT (yn_student_grades) latest first ===');
        $this->table(['ysg_id','sub_id','attempt','edu_year','jn','mt','created_at'], $ynRows->map(fn($r)=>[
            $r->id,$r->yn_submission_id,$r->attempt,$r->education_year,$r->jn,$r->mt,$r->created_at
        ])->toArray());

        $this->info('=== LIVE SOURCES ===');
        $this->line('JN avg (fallback): ' . ($jnAvg !== null ? round((float)$jnAvg,2) : 'null'));
        $this->table(['mt_id','grade','retake_grade','effective','updated_at','created_at'], $manualMtRows->map(fn($r)=>[
            $r->id,$r->grade,$r->retake_grade,($r->retake_grade ?? $r->grade),$r->updated_at,$r->created_at
        ])->toArray());

        $this->info('=== DAVOMAT ===');
        $this->line("absent_off={$absentOff}, aud_hours={$aud}, davomat_pct={$davomatPct}");

        $this->info('=== FINAL FLAGS ===');
        $this->line('jn=' . var_export($jn, true) . ', mt=' . var_export($mt, true));
        $this->line('jnLow=' . ($jnLow ? 'true' : 'false') . ', mtLow=' . ($mtLow ? 'true' : 'false') . ', davomat>=25=' . ($davomatPct >= 25 ? 'true' : 'false'));
        $this->line('isPullik=' . ($isPullik ? 'true' : 'false'));

        return self::SUCCESS;
    }
}
