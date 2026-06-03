<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\ContractList;
use App\Models\CurriculumSubject;
use App\Models\MarkingSystemScore;
use App\Models\Setting;
use App\Models\Student;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * YN (Yakuniy Nazorat) ga ruxsat hisoblaydigan yagona service.
 *
 * AcademicScheduleController::generateYnOldiWord() ichidagi per-student "Ruxsat
 * / Shartli / X" qaroriga to'liq teng — bu yerda markazlashtirilgan, test
 * markazi UI / Moodle push / vaqt qo'yish endpointlari ham xuddi shu qarorga
 * tayanadi.
 *
 * Qoidalar (12-shakl):
 *   - JN < markingScore.effectiveLimit('jn')   → X (ruxsat yo'q)
 *   - MT < markingScore.effectiveLimit('mt')   → X
 *   - Davomat (sababsiz absent_off) ≥ 25%       → X
 *   - Kontrakt to'lovi joriy muddatga yetmasa    → Shartli (faqat boshqa
 *                                                  shartlar bajarilgan bo'lsa)
 *   - Hech biri tushmasa                         → Ruxsat
 */
class YnAdmissionService
{
    public const STATUS_RUXSAT  = 'Ruxsat';
    public const STATUS_SHARTLI = 'Shartli';
    public const STATUS_X       = 'X';

    public function __construct(private JnMtCalculator $jnMtCalculator)
    {
    }

    /**
     * Bitta guruh + fan + semestr uchun har bir talabaga ruxsat holatini hisoblaydi.
     *
     * @return array<string, array{
     *     status: string,        // Ruxsat | Shartli | X
     *     jn: ?int,
     *     mt: ?int,
     *     jn_limit: int,
     *     mt_limit: int,
     *     jn_failed: bool,
     *     mt_failed: bool,
     *     davomat_pct: float,
     *     davomat_failed: bool,
     *     contract_pct: ?int,
     *     contract_failed: bool,
     *     reasons: string[],
     * }>  hemis_id → admission info
     */
    public function computeForGroup(string $groupHemisId, string $subjectId, string $semesterCode): array
    {
        $students = Student::where('group_id', $groupHemisId)
            ->where('student_status_code', 11)
            ->get(['id', 'hemis_id']);

        if ($students->isEmpty()) {
            return [];
        }

        // Subject - guruh curriculum'i bo'yicha aniqlash. Bir xil subject_id va
        // semester_code bilan turli curricula'larda har xil academic_load bo'lishi
        // mumkin - shuning uchun guruh curriculum_hemis_id'iga moslab olamiz.
        // Jurnal ham shu tarzda ishlaydi.
        $curriculumHemisId = DB::table('groups')
            ->where('group_hemis_id', $groupHemisId)
            ->value('curriculum_hemis_id');
        $subjectQuery = CurriculumSubject::where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode);
        if ($curriculumHemisId) {
            $subjectQuery->where('curricula_hemis_id', $curriculumHemisId);
        }
        $subject = $subjectQuery->first();
        if (!$subject && $curriculumHemisId) {
            // Fallback: agar shu curriculum'da subject topilmasa, eski xulq
            $subject = CurriculumSubject::where('subject_id', $subjectId)
                ->where('semester_code', $semesterCode)
                ->first();
        }

        // Auditoriya soatlari — davomat foizini hisoblash uchun maxraj.
        // 17 = nazariy bo'lmagan (mustaqil ish) — hisobga olinmaydi.
        $auditoriumHours = 0;
        if ($subject && is_array($subject->subject_details)) {
            foreach ($subject->subject_details as $detail) {
                $tc = (string) (($detail['trainingType'] ?? [])['code'] ?? '');
                if ($tc !== '' && $tc !== '17') {
                    $auditoriumHours += (float) ($detail['academic_load'] ?? 0);
                }
            }
        }
        if ($auditoriumHours <= 0) {
            $auditoriumHours = (float) ($subject->total_acload ?? 0);
        }
        if ($auditoriumHours <= 0) {
            $auditoriumHours = 1; // 0 ga bo'linishdan saqlanish
        }

        // Live JN/MT — jurnaldagi "Ixcham" tab bilan bir xil mantiq.
        $liveGrades = $this->jnMtCalculator->computeForGroup(
            $groupHemisId,
            (int) $subjectId,
            $semesterCode
        );

        $hemisIds = $students->pluck('hemis_id')->all();

        // Davomat — jurnal (JournalController) bilan AYNI mantiqda hisoblanadi:
        // education_year_code bo'yicha filterlanadi (joriy o'quv yili) va group_id
        // qo'shimcha filtersiz - faqat (subject, sem, education_year). education_year_code
        // schedules jadvalidan oxirgi lesson_date asosida aniqlanadi (curriculum
        // education_year_code fallback bilan). Bu jurnaldagi davomat % bilan
        // aynan teng natija beradi.
        $educationYearCode = null;
        try {
            $scheduleEducationYear = DB::table('schedules')
                ->where('group_id', $groupHemisId)
                ->where('subject_id', $subjectId)
                ->where('semester_code', $semesterCode)
                ->whereNull('deleted_at')
                ->whereNotNull('lesson_date')
                ->whereNotNull('education_year_code')
                ->orderBy('lesson_date', 'desc')
                ->value('education_year_code');
            if ($scheduleEducationYear) {
                $educationYearCode = $scheduleEducationYear;
            } else {
                $curriculum = DB::table('groups as g')
                    ->join('curricula as c', 'c.curricula_hemis_id', '=', 'g.curriculum_hemis_id')
                    ->where('g.group_hemis_id', $groupHemisId)
                    ->value('c.education_year_code');
                $hasScheduleRows = DB::table('schedules')
                    ->where('group_id', $groupHemisId)
                    ->where('subject_id', $subjectId)
                    ->where('semester_code', $semesterCode)
                    ->whereNull('deleted_at')
                    ->whereNotNull('lesson_date')
                    ->exists();
                // Jadval qatorlari mavjud, lekin education_year_code NULL bo'lsa,
                // jadval ma'lumotlarini filterlamaslik uchun curriculum yil'ini ham
                // ishlatmaymiz (jurnal bilan AYNI mantiq).
                $educationYearCode = $hasScheduleRows ? null : $curriculum;
            }
        } catch (\Throwable $e) {}

        $absentMap = Attendance::query()
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->whereIn('student_hemis_id', $hemisIds)
            ->when($educationYearCode !== null, fn($q) => $q->where('education_year_code', $educationYearCode))
            ->whereNotIn('training_type_code', [99, 100, 101, 102])
            ->selectRaw('student_hemis_id, SUM(absent_off) as total_off')
            ->groupBy('student_hemis_id')
            ->pluck('total_off', 'student_hemis_id')
            ->toArray();

        // Kontrakt — bitta batch so'rov. edu_year prefiks (masalan "2025-2026")
        // generateYnOldiWord'dagi mantiqqa mos (year=2025 + edu_year like 2025-2026%).
        // TODO: yangi o'quv yili boshlanganda bu joyni umumiy "joriy o'quv yili"
        // ga ko'chirish kerak.
        $contractMap = ContractList::query()
            ->whereIn('student_hemis_id', $hemisIds)
            ->where('year', '2025')
            ->where('edu_year', 'like', '2025-2026%')
            ->get()
            ->keyBy('student_hemis_id');

        $contractThreshold = $this->resolveContractThreshold();

        $result = [];
        foreach ($students as $student) {
            $hemisId = (string) $student->hemis_id;

            $jn = $liveGrades[$hemisId]['jn'] ?? 0;
            $mt = $liveGrades[$hemisId]['mt'] ?? 0;

            $markingScore = MarkingSystemScore::getByStudentHemisId($hemisId);
            $jnLimit = $markingScore->effectiveLimit('jn');
            $mtLimit = $markingScore->effectiveLimit('mt');

            $absentOff = (float) ($absentMap[$hemisId] ?? 0);
            $davomatPct = round($absentOff * 100 / $auditoriumHours, 2);

            $contractPct = null;
            $contractFailed = false;
            $contract = $contractMap->get($hemisId);
            if ($contract && (float) $contract->edu_contract_sum > 0) {
                $contractPct = (int) round(($contract->paid_credit_amount / $contract->edu_contract_sum) * 100);
                if ($contractPct < $contractThreshold) {
                    $contractFailed = true;
                }
            }

            $jnFailed = ($jnLimit > 0) && ((int) $jn < $jnLimit);
            $mtFailed = ($mtLimit > 0) && ((int) $mt < $mtLimit);
            $davomatFailed = $davomatPct >= 25;

            $reasons = [];
            $status = self::STATUS_RUXSAT;

            if ($jnFailed) {
                $status = self::STATUS_X;
                $reasons[] = "JN {$jn} < {$jnLimit}";
            }
            if ($mtFailed) {
                $status = self::STATUS_X;
                $reasons[] = "MT {$mt} < {$mtLimit}";
            }
            if ($davomatFailed) {
                $status = self::STATUS_X;
                $reasons[] = "Davomat {$davomatPct}% ≥ 25%";
            }
            // Kontrakt faqat boshqa shartlar o'tgan bo'lsa "Shartli" beradi.
            if ($contractFailed && $status === self::STATUS_RUXSAT) {
                $status = self::STATUS_SHARTLI;
                $reasons[] = "Kontrakt {$contractPct}% < {$contractThreshold}%";
            }

            $result[$hemisId] = [
                'status'          => $status,
                'jn'              => (int) $jn,
                'mt'              => (int) $mt,
                'jn_limit'        => $jnLimit,
                'mt_limit'        => $mtLimit,
                'jn_failed'       => $jnFailed,
                'mt_failed'       => $mtFailed,
                'davomat_pct'     => $davomatPct,
                'davomat_failed'  => $davomatFailed,
                'contract_pct'    => $contractPct,
                'contract_failed' => $contractFailed,
                'reasons'         => $reasons,
            ];
        }

        return $result;
    }

    /**
     * Bitta talaba uchun qisqa shaklda holat. Endpointlardan ruxsat tekshirish
     * uchun ishlatiladi.
     */
    public function statusForStudent(string $studentHemisId, string $groupHemisId, string $subjectId, string $semesterCode): ?string
    {
        $map = $this->computeForGroup($groupHemisId, $subjectId, $semesterCode);
        return $map[$studentHemisId]['status'] ?? null;
    }

    /**
     * Joriy sana asosida kontrakt foiz chegarasini topadi (Setting'dagi
     * contract_cutoffs JSON ro'yxatidan birinchi muddati hali tugamagan
     * yozuvning percent qiymati).
     */
    private function resolveContractThreshold(): int
    {
        $defaults = json_encode([
            ['deadline' => '2025-10-01', 'percent' => 25],
            ['deadline' => '2026-01-01', 'percent' => 50],
            ['deadline' => '2026-03-01', 'percent' => 75],
            ['deadline' => '2026-05-01', 'percent' => 100],
        ]);
        $cutoffs = json_decode(Setting::get('contract_cutoffs', $defaults), true) ?: [];

        $now = time();
        foreach ($cutoffs as $cutoff) {
            $deadline = $cutoff['deadline'] ?? null;
            if (!$deadline) continue;
            if ($now <= strtotime($deadline . ' 23:59:59')) {
                return (int) ($cutoff['percent'] ?? 100);
            }
        }
        return 100; // barcha muddatlar o'tgan
    }
}
