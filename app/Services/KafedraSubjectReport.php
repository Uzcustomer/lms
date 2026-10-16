<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * "Fanlar bo'yicha hisobot" (kafedra mudiri): kafedrada joriy semestrda o'tiladigan fanlar,
 * ularni o'qiyotgan guruhlar va talabalarning baholari.
 *
 * Fan birligi = fan + semestr + yo'nalish + kurs (bir nechta o'quv rejada bo'lishi mumkin).
 * Guruhlar — shu o'quv rejalarga bog'langan faol guruhlar; talabalar — guruhdagi
 * chetlashtirilmaganlar. JN — reyting usulida: kunlik baholar kun bo'yicha o'rtacha,
 * so'ng kunlar o'rtachasi (hammasi NB bo'lgan kun — 0).
 */
class KafedraSubjectReport
{
    private const LECTURE = 11;
    public const OTHER = [99 => 'MT', 100 => 'ON', 101 => 'OSKI', 102 => 'Test', 103 => 'Quiz'];

    /** Kafedraning joriy semestr fanlari (fan birliklari). */
    public static function units(int $deptId): Collection
    {
        $today = now()->toDateString();
        $rows = DB::table('curriculum_subjects as cs')
            ->join('semesters as s', function ($j) {
                $j->on('s.curriculum_hemis_id', '=', 'cs.curricula_hemis_id')->on('s.code', '=', 'cs.semester_code');
            })
            ->join('curricula as c', 'c.curricula_hemis_id', '=', 'cs.curricula_hemis_id')
            ->leftJoin('specialties as sp', 'sp.specialty_hemis_id', '=', 'c.specialty_hemis_id')
            ->where('cs.department_id', $deptId)
            ->where('cs.is_active', true)
            ->where(function ($q) use ($today) {
                // Joriy semestr — KTR sahifasidagi bilan bir xil
                $q->whereIn('s.semester_hemis_id', function ($sub) use ($today) {
                    $sub->select('semester_hemis_id')->from('curriculum_weeks')->groupBy('semester_hemis_id')
                        ->havingRaw('MIN(start_date) <= ? AND MAX(end_date) >= ?', [$today, $today]);
                })->orWhere('s.current', 1);
            })
            ->get(['cs.subject_id', 'cs.subject_name', 'cs.semester_code', 'cs.semester_name', 'cs.curricula_hemis_id',
                'cs.total_acload', 'sp.name as specialty_name', 's.level_code', 's.level_name']);

        $units = [];
        foreach ($rows as $r) {
            $key = self::unitKey($r->subject_id, $r->semester_code, (string) $r->specialty_name, (string) $r->level_code);
            $units[$key] ??= (object) [
                'key' => $key, 'subject_id' => (int) $r->subject_id, 'subject_name' => (string) $r->subject_name,
                'semester_code' => (string) $r->semester_code, 'semester_name' => (string) $r->semester_name,
                'specialty_name' => (string) $r->specialty_name, 'level_code' => (string) $r->level_code,
                'level_name' => (string) $r->level_name, 'hours' => (int) $r->total_acload, 'curricula' => [],
            ];
            $units[$key]->curricula[(int) $r->curricula_hemis_id] = true;
        }

        return collect($units)->sortBy(fn ($u) => [$u->level_code, $u->subject_name, $u->specialty_name])->values();
    }

    public static function unitKey($subjectId, $semesterCode, string $specialty, string $level): string
    {
        return $subjectId . '|' . $semesterCode . '|' . $level . '|' . md5($specialty);
    }

    /** Fan birligini guruhlari bilan: group_hemis_id => {name, students: [hemis_id => {full_name, id_number}]}. */
    public static function groups(object $unit): Collection
    {
        $groups = DB::table('groups')
            ->whereIn('curriculum_hemis_id', array_keys($unit->curricula))
            ->when(\Illuminate\Support\Facades\Schema::hasColumn('groups', 'active'), fn ($q) => $q->where('active', true))
            ->orderBy('name')
            ->get(['group_hemis_id', 'name', 'education_lang_name']);
        if ($groups->isEmpty()) {
            return collect();
        }
        $students = DB::table('students')
            ->whereIn('group_id', $groups->pluck('group_hemis_id'))
            ->where(fn ($q) => $q->whereNull('student_status_code')->orWhere('student_status_code', '!=', '60'))
            ->orderBy('full_name')
            ->get(['hemis_id', 'full_name', 'student_id_number', 'group_id'])
            ->groupBy('group_id');

        return $groups->map(fn ($g) => (object) [
            'group_hemis_id' => (int) $g->group_hemis_id, 'name' => (string) $g->name, 'lang' => (string) $g->education_lang_name,
            'students' => $students->get($g->group_hemis_id, collect())->values(),
        ])->filter(fn ($g) => $g->students->isNotEmpty())->values();
    }

    /**
     * Baholar: [student_hemis_id][subject_id] => ['days' => [Y-m-d => qiymat], 'jn' => float|null,
     * 'other' => [MT => [..]], 'teachers' => [nomi => soni]].
     */
    public static function grades(array $studentIds, array $subjectIds, array $semesterCodes, ?string $from = null, ?string $to = null): array
    {
        $out = [];
        if (!$studentIds || !$subjectIds) {
            return $out;
        }
        $acc = [];
        foreach (array_chunk($studentIds, 1000) as $chunk) {
            $q = DB::table('student_grades')
                ->whereIn('student_hemis_id', $chunk)
                ->whereIn('subject_id', $subjectIds)
                ->whereIn('semester_code', $semesterCodes)
                ->whereNull('deleted_at')
                ->where('training_type_code', '!=', self::LECTURE);
            if ($from || $to) {
                $f = ($from ?: '2000-01-01') . ' 00:00:00';
                $t = ($to ?: '2100-01-01') . ' 23:59:59';
                $q->where(function ($w) use ($f, $t) {
                    $w->whereBetween('lesson_date', [$f, $t])
                        ->orWhere(fn ($w) => $w->whereNull('lesson_date')->whereBetween('created_at', [$f, $t]));
                });
            }
            foreach ($q->orderBy('lesson_date')->cursor() as $g) {
                $st = (int) $g->student_hemis_id;
                $sub = (int) $g->subject_id;
                $value = self::value($g);
                $type = (int) $g->training_type_code;
                if (isset(self::OTHER[$type])) {
                    if ($value !== '') {
                        $out[$st][$sub]['other'][self::OTHER[$type]][] = $value;
                    }
                    continue;
                }
                $day = substr((string) ($g->lesson_date ?: $g->created_at), 0, 10);
                $a = &$acc[$st][$sub][$day];
                $a ??= ['sum' => 0.0, 'n' => 0, 'nb' => 0];
                $a['n']++;
                $value === 'NB' ? $a['nb']++ : $a['sum'] += (float) $value;
                unset($a);
                if ($g->employee_name) {
                    $out[$st][$sub]['teachers'][$g->employee_name] = ($out[$st][$sub]['teachers'][$g->employee_name] ?? 0) + 1;
                }
            }
        }
        foreach ($acc as $st => $subs) {
            foreach ($subs as $sub => $days) {
                ksort($days);
                $total = 0;
                $vals = [];
                foreach ($days as $day => $a) {
                    $v = $a['nb'] === $a['n'] ? 'NB' : (int) round($a['sum'] / $a['n']);
                    $vals[$day] = $v;
                    $total += $v === 'NB' ? 0 : $v;
                }
                $out[$st][$sub]['days'] = $vals;
                $out[$st][$sub]['jn'] = $days ? round($total / count($days), 1) : null;
            }
        }

        return $out;
    }

    private static function value($g)
    {
        if ($g->status === 'retake' && $g->retake_grade !== null) {
            return (float) $g->retake_grade;
        }
        if ($g->status === 'pending' && $g->reason === 'absent') {
            return 'NB';
        }

        return $g->grade !== null ? (float) $g->grade : '';
    }

    /** JN qiymatlari ro'yxatidan ko'rsatkichlar: o'rtacha va taqsimot. */
    public static function stats(array $jns): array
    {
        $jns = array_values(array_filter($jns, fn ($v) => $v !== null));
        $b = ['a' => 0, 'b' => 0, 'c' => 0, 'd' => 0];
        foreach ($jns as $v) {
            $v >= 90 ? $b['a']++ : ($v >= 70 ? $b['b']++ : ($v >= 60 ? $b['c']++ : $b['d']++));
        }

        return ['avg' => $jns ? round(array_sum($jns) / count($jns), 1) : null, 'graded' => count($jns)] + $b;
    }

    /** Eng ko'p baho qo'ygan o'qituvchi(lar). */
    public static function teachers(array $gradesByStudent, int $subjectId, iterable $studentIds): string
    {
        $count = [];
        foreach ($studentIds as $id) {
            foreach ($gradesByStudent[$id][$subjectId]['teachers'] ?? [] as $name => $n) {
                $count[$name] = ($count[$name] ?? 0) + $n;
            }
        }
        arsort($count);

        return implode(', ', array_slice(array_keys($count), 0, 2));
    }
}
