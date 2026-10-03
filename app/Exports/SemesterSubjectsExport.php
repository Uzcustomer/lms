<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Tanlangan o'quv yilining kuzgi (toq) yoki bahorgi (juft) semestr fanlari.
 *
 * Har bir fakultet + yo'nalish — alohida varaq (masalan "Davolash ishi
 * (1-son)", "Davolash ishi (2-son)", "Pediatriya ishi", "Stomatologiya").
 * Varaq ichida kurslar bo'lim-bo'lim: fan nomi, nazorat (yopilish) turi,
 * jami soat va mashg'ulot turlari bo'yicha soatlar.
 *
 * Manba — o'quv rejasi (curriculum_subjects + semesters). Semestr kodi
 * juft (12, 14, …) bo'lsa bahorgi, toq (11, 13, …) bo'lsa kuzgi.
 */
class SemesterSubjectsExport implements WithMultipleSheets
{
    use Exportable;

    public function __construct(
        private string $educationYear,
        private bool $spring = true,
        private ?string $educationType = null,
    ) {
    }

    public function sheets(): array
    {
        $rows = DB::table('curriculum_subjects as cs')
            ->join('curricula as c', 'c.curricula_hemis_id', '=', 'cs.curricula_hemis_id')
            ->join('semesters as s', function ($join) {
                $join->on('s.curriculum_hemis_id', '=', 'c.curricula_hemis_id')
                    ->on('s.code', '=', 'cs.semester_code');
            })
            ->leftJoin('departments as f', 'f.department_hemis_id', '=', 'c.department_hemis_id')
            ->leftJoin('specialties as sp', 'sp.specialty_hemis_id', '=', 'c.specialty_hemis_id')
            ->where('s.education_year', $this->educationYear)
            ->whereRaw('CAST(s.code AS UNSIGNED) % 2 = ?', [$this->spring ? 0 : 1])
            ->where('cs.is_active', true)
            ->when($this->educationType, fn ($q) => $q->where('c.education_type_code', $this->educationType))
            ->select([
                'f.name as faculty_name',
                'sp.name as specialty_name',
                's.level_code',
                's.level_name',
                'cs.subject_name',
                'cs.closing_form',
                'cs.total_acload',
                'cs.subject_details',
            ])
            ->distinct()
            ->orderBy('f.name')
            ->orderBy('sp.name')
            ->orderByRaw('CAST(s.level_code AS UNSIGNED)')
            ->orderBy('cs.subject_name')
            ->get();

        // Fakultet + yo'nalish bo'yicha guruhlash, har biri bitta varaq
        $groups = [];
        foreach ($rows as $row) {
            $key = ($row->faculty_name ?? '') . '|' . ($row->specialty_name ?? '');
            $groups[$key]['faculty'] = $row->faculty_name ?? '';
            $groups[$key]['specialty'] = $row->specialty_name ?? "Yo'nalishsiz";
            $groups[$key]['rows'][] = $row;
        }

        // Bir xil yo'nalish bir nechta fakultetda bo'lsa — varaq nomiga fakultet qo'shiladi
        $specialtyCount = [];
        foreach ($groups as $g) {
            $specialtyCount[$g['specialty']] = ($specialtyCount[$g['specialty']] ?? 0) + 1;
        }

        $sheets = [];
        $usedTitles = [];
        foreach ($groups as $g) {
            $title = $g['specialty'];
            if (($specialtyCount[$g['specialty']] ?? 0) > 1 && $g['faculty'] !== '') {
                $title .= ' (' . $this->facultyShort($g['faculty']) . ')';
            }
            $sheets[] = new SemesterSubjectsSheet($this->uniqueSheetTitle($title, $usedTitles), $g['rows']);
        }

        if (empty($sheets)) {
            $sheets[] = new SemesterSubjectsSheet("Ma'lumot yo'q", []);
        }

        return $sheets;
    }

    /** "1-son davolash fakulteti" → "1-son" ; "Pediatriya" → "Pediatriya" */
    private function facultyShort(string $faculty): string
    {
        if (preg_match('/^\s*(\d+\s*-\s*son)/iu', $faculty, $m)) {
            return str_replace(' ', '', $m[1]);
        }

        return trim(preg_replace('/\s*fakulteti?\s*$/iu', '', $faculty)) ?: $faculty;
    }

    /** Excel varaq nomi: 31 belgidan oshmasin, taqiqlangan belgilarsiz, takrorlanmasin */
    private function uniqueSheetTitle(string $title, array &$used): string
    {
        $clean = trim(preg_replace('/[\\\\\/\?\*\[\]:]+/u', ' ', $title));
        $clean = mb_substr($clean, 0, 31);

        $candidate = $clean;
        $n = 2;
        while (isset($used[mb_strtolower($candidate)])) {
            $suffix = " ({$n})";
            $candidate = mb_substr($clean, 0, 31 - mb_strlen($suffix)) . $suffix;
            $n++;
        }
        $used[mb_strtolower($candidate)] = true;

        return $candidate;
    }
}
