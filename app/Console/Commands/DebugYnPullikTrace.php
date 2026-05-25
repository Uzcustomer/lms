<?php

namespace App\Console\Commands;

use App\Services\JournalGradeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DebugYnPullikTrace extends Command
{
    protected $signature = 'debug:yn-pullik-trace
        {student_hemis_id : Talaba HEMIS ID}
        {group_hemis_id : Guruh HEMIS ID}
        {subject_id : Fan ID}
        {semester_code : Semester code}';

    protected $description = 'YN sahifasidagi pullik mantig\'ini AYNAN takrorlab, har bir bosqichni chiqaradi';

    public function handle(): int
    {
        $hid = (string) $this->argument('student_hemis_id');
        $gid = (string) $this->argument('group_hemis_id');
        $sid = (int) $this->argument('subject_id');
        $sem = (string) $this->argument('semester_code');
        $minLimit = 60;

        $this->info('=== STEP 0: INPUT ===');
        $this->line("hid={$hid}, group={$gid}, subject={$sid}, sem={$sem}");

        $student = DB::table('students')->where('hemis_id', $hid)
            ->select('full_name', 'group_id', 'curriculum_id', 'semester_code', 'level_code', 'student_status_code')
            ->first();
        $this->line('Student row: ' . json_encode($student, JSON_UNESCAPED_UNICODE));

        $subj = DB::table('curriculum_subjects')->where('subject_id', $sid)->value('subject_name');
        $this->line('Subject name: ' . ($subj ?? 'NOT FOUND'));

        $semInfo = DB::table('semesters')->where('code', $sem)
            ->select('code', 'name', 'education_year', 'start_date', 'end_date', 'current', 'curriculum_id')
            ->get();
        $this->line('Semesters with code=' . $sem . ': ' . $semInfo->count() . ' rows');
        foreach ($semInfo as $r) {
            $this->line('  ' . json_encode($r, JSON_UNESCAPED_UNICODE));
        }

        // STEP 1a: snapshot
        $this->info("\n=== STEP 1a: SNAPSHOT (yn_student_grades) ===");
        $hasYnSubEduYearCol = Schema::hasColumn('yn_submissions', 'education_year');
        $ynQuery = DB::table('yn_student_grades as ysg')
            ->join('yn_submissions as yns', 'yns.id', '=', 'ysg.yn_submission_id')
            ->where('ysg.student_hemis_id', $hid)
            ->where('yns.subject_id', $sid)
            ->where('yns.semester_code', $sem)
            ->where('yns.group_hemis_id', $gid);

        $ynRows = $ynQuery
            ->orderBy('ysg.created_at', 'desc')
            ->select('ysg.id as ysg_id', 'yns.id as yns_id', 'yns.attempt',
                $hasYnSubEduYearCol ? 'yns.education_year' : DB::raw('NULL as education_year'),
                'ysg.jn', 'ysg.mt', 'ysg.created_at')
            ->get();
        $this->line("Snapshot rows found: " . $ynRows->count());
        foreach ($ynRows as $r) {
            $this->line('  ' . json_encode($r));
        }

        $snapJn = null;
        $snapMt = null;
        if ($ynRows->isNotEmpty()) {
            $first = $ynRows->first();
            $jnInt = (int) $first->jn;
            $mtInt = (int) $first->mt;
            $snapJn = $jnInt > 0 ? $jnInt : null;
            $snapMt = $mtInt > 0 ? $mtInt : null;
        }
        $this->line("snapshot.jn (>0?int:null) = " . var_export($snapJn, true));
        $this->line("snapshot.mt (>0?int:null) = " . var_export($snapMt, true));

        // STEP 1b: live fallback
        $this->info("\n=== STEP 1b: LIVE FALLBACK (JournalGradeService) ===");
        $studentGroup = [$hid => $gid];
        $triples = [[$gid, $sid, $sem]];
        $liveJn = null;
        $liveMt = null;
        try {
            $live = JournalGradeService::computeJnMtBulk($triples, $studentGroup);
            $key = $gid . '|' . $sid . '|' . $sem;
            $this->line('JournalGradeService raw output: ' . json_encode($live[$key] ?? []));
            if (isset($live[$key][$hid])) {
                $liveJn = $live[$key][$hid]['jn'] ?? null;
                $liveMt = $live[$key][$hid]['mt'] ?? null;
            }
        } catch (\Throwable $e) {
            $this->error('JournalGradeService FAILED: ' . $e->getMessage());
            $this->line($e->getTraceAsString());
        }
        $this->line("live.jn = " . var_export($liveJn, true));
        $this->line("live.mt = " . var_export($liveMt, true));

        // STEP 1c: combined
        $jn = $snapJn !== null ? $snapJn : $liveJn;
        $mt = $snapMt !== null ? $snapMt : $liveMt;
        $this->info("\n=== STEP 1c: COMBINED (snapshot first, then live) ===");
        $this->line("final jn = " . var_export($jn, true));
        $this->line("final mt = " . var_export($mt, true));

        // STEP 2: davomat
        $this->info("\n=== STEP 2: DAVOMAT (attendances) ===");
        $absRows = DB::table('attendances')
            ->where('student_hemis_id', $hid)
            ->where('subject_id', $sid)
            ->where('semester_code', $sem)
            ->whereNotIn('training_type_code', [99, 100, 101, 102])
            ->selectRaw('training_type_code, COUNT(*) cnt, SUM(absent_off) total_off, SUM(CASE WHEN absent_off>0 THEN 1 ELSE 0 END) absent_rows')
            ->groupBy('training_type_code')
            ->get();
        $this->table(
            ['training_type_code', 'rows', 'total_off', 'absent_rows'],
            $absRows->map(fn($r) => [$r->training_type_code, $r->cnt, $r->total_off, $r->absent_rows])->toArray()
        );
        $absentOff = (float) DB::table('attendances')
            ->where('student_hemis_id', $hid)
            ->where('subject_id', $sid)
            ->where('semester_code', $sem)
            ->whereNotIn('training_type_code', [99, 100, 101, 102])
            ->sum('absent_off');
        $this->line("absentOff TOTAL = {$absentOff}");

        // STEP 3: aud hours
        $this->info("\n=== STEP 3: AUDITORIYA SOATLARI ===");
        $cs = DB::table('curriculum_subjects')
            ->where('subject_id', $sid)
            ->where('semester_code', $sem)
            ->select('curriculum_id', 'subject_details', 'total_acload')
            ->get();
        $this->line("curriculum_subjects rows: " . $cs->count());
        $aud = 0.0;
        foreach ($cs as $sr) {
            $details = is_string($sr->subject_details) ? json_decode($sr->subject_details, true) : $sr->subject_details;
            $rowAud = 0;
            if (is_array($details)) {
                foreach ($details as $d) {
                    $tc = (string) ($d['trainingType']['code'] ?? '');
                    if ($tc !== '' && $tc !== '17') {
                        $rowAud += (float) ($d['academic_load'] ?? 0);
                    }
                }
            }
            if ($rowAud <= 0) $rowAud = (float) ($sr->total_acload ?? 0);
            $this->line("  curr_id={$sr->curriculum_id}, aud={$rowAud}, total_acload={$sr->total_acload}");
            $aud = $rowAud;
        }
        $this->line("aud_hours = {$aud}");

        $davomatPct = $aud > 0 ? round(($absentOff / $aud) * 100, 2) : 0.0;
        $this->line("davomat_pct = {$davomatPct}%");

        // STEP 4: OSKI / Test
        $this->info("\n=== STEP 4: OSKI / TEST attempts ===");
        $exams = DB::table('student_grades')
            ->whereNull('deleted_at')
            ->where('student_hemis_id', $hid)
            ->where('subject_id', $sid)
            ->where('semester_code', $sem)
            ->whereIn('training_type_code', [101, 102, 103])
            ->select('id', 'training_type_code', 'attempt', 'grade', 'retake_grade', 'reason', 'lesson_date', 'quiz_result_id')
            ->orderBy('lesson_date')->orderBy('id')
            ->get();
        $this->table(
            ['id', 'ttc', 'att', 'grade', 'retake', 'reason', 'lesson_date', 'quiz_id'],
            $exams->map(fn($r) => [$r->id, $r->training_type_code, $r->attempt, $r->grade, $r->retake_grade, $r->reason, $r->lesson_date, $r->quiz_result_id])->toArray()
        );

        // STEP 5: pullik computation (faithful to controller line 1736-1738)
        $this->info("\n=== STEP 5: PULLIK COMPUTATION ===");
        $jnLow = ($jn !== null) && ($jn < $minLimit);
        $mtLow = ($mt !== null) && ($mt < $minLimit);
        $davHigh = $davomatPct >= 25;
        $isPullik = $jnLow || $mtLow || $davHigh;

        $this->line("jnLow  (jn < {$minLimit}) = " . ($jnLow ? 'TRUE' : 'false') . "  [jn=" . var_export($jn, true) . "]");
        $this->line("mtLow  (mt < {$minLimit}) = " . ($mtLow ? 'TRUE' : 'false') . "  [mt=" . var_export($mt, true) . "]");
        $this->line("davHigh (>=25%)           = " . ($davHigh ? 'TRUE' : 'false') . "  [pct={$davomatPct}]");
        $this->line('');
        if ($isPullik) {
            $this->error("=> isPullik = TRUE (sabab: " . implode('+', array_filter([
                $jnLow ? 'jnLow' : null,
                $mtLow ? 'mtLow' : null,
                $davHigh ? 'davHigh' : null,
            ])) . ")");
        } else {
            $this->info("=> isPullik = FALSE");
        }

        return self::SUCCESS;
    }
}
