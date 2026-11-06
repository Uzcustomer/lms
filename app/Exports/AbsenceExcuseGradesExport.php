<?php

namespace App\Exports;

use App\Models\AbsenceExcuse;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Tasdiqlangan sababli arizalar — sabab davridagi baholar fan bo'yicha (har ariza × fan = bitta qator).
 *   "HEMIS'da qo'yilgan" — talaba darsda bo'lib olgan oddiy baho (NB emas);
 *   "Markda qo'yilgan"   — NB ustiga LMS'da qo'yilgan baho (otrabotka yoki sababli ariza bilan ochilgan katak);
 *   "Bahosiz NB"         — hali baho qo'yilmagan NB lar soni.
 * Fanlar — guruh dars jadvalidagi (ma'ruzasiz) va talabaning shu davrdagi baholaridagi fanlar.
 */
class AbsenceExcuseGradesExport implements FromArray, WithHeadings, WithStyles, WithTitle, WithColumnWidths
{
    private const TYPE_LABELS = [99 => 'MT', 100 => 'ON', 101 => 'OSKI', 102 => 'Test', 103 => 'Test'];

    private int $rowCount = 0;

    /** @param Collection<int, AbsenceExcuse> $excuses */
    public function __construct(private Collection $excuses) {}

    public function headings(): array
    {
        return [
            '#', 'Talaba F.I.SH.', 'HEMIS ID', 'Fakultet', 'Guruh', 'Ariza №', 'Sabab', 'Sabab davri', 'Tasdiqlagan',
            'Fan', 'Darslar soni (davrda)', "HEMIS'da qo'yilgan", "Markda qo'yilgan (NB ustiga)", 'Bahosiz NB',
        ];
    }

    public function array(): array
    {
        $rows = [];
        $n = 0;
        foreach ($this->excuses as $excuse) {
            $from = Carbon::parse($excuse->start_date)->startOfDay();
            $to = Carbon::parse($excuse->end_date)->endOfDay();
            $groupId = $excuse->student?->group_id
                ?? Student::where('hemis_id', $excuse->student_hemis_id)->value('group_id');

            $base = [
                $excuse->student_full_name,
                $excuse->student_hemis_id,
                $excuse->department_name ?? $excuse->student?->department_name ?? '',
                $excuse->group_name,
                $excuse->id,
                AbsenceExcuse::REASONS[$excuse->reason]['label'] ?? $excuse->reason,
                $from->format('d.m.Y') . ' – ' . $to->format('d.m.Y'),
                $excuse->reviewed_by_name ?? '',
            ];

            $subjects = $this->subjects($excuse->student_hemis_id, $groupId, $from, $to);
            if ($subjects === []) {
                $rows[] = array_merge([++$n], $base, ["Bu davrda dars yo'q", 0, '', '', 0]);
                continue;
            }
            foreach ($subjects as $s) {
                $rows[] = array_merge([++$n], $base, [
                    $s['name'], $s['lessons'],
                    implode('; ', $s['hemis']), implode('; ', $s['mark']), $s['nb'],
                ]);
            }
        }
        $this->rowCount = count($rows);

        return $rows;
    }

    /** @return array<int, array{name: string, lessons: int, hemis: string[], mark: string[], nb: int}> */
    private function subjects(string $hemisId, $groupId, Carbon $from, Carbon $to): array
    {
        $subjects = [];
        $pairNames = [];   // lesson_pair_code => juftlik nomi (jadvaldan)
        if ($groupId) {
            $lessons = DB::table('schedules')
                ->whereNull('deleted_at')
                ->where('group_id', $groupId)
                ->whereBetween('lesson_date', [$from, $to])
                ->where('training_type_code', '!=', 11)
                ->get(['subject_id', 'subject_name', 'lesson_date', 'lesson_pair_code', 'lesson_pair_name']);
            foreach ($lessons as $l) {
                $pairNames[(string) $l->lesson_pair_code] ??= $l->lesson_pair_name;
            }
            $lessons
                ->groupBy(fn ($r) => (string) $r->subject_id)
                ->each(function ($lessons, $subjectId) use (&$subjects) {
                    $subjects[$subjectId] = [
                        'name' => trim($lessons->first()->subject_name),
                        'lessons' => $lessons->unique(fn ($r) => substr((string) $r->lesson_date, 0, 10) . '|' . $r->lesson_pair_code)->count(),
                        'hemis' => [], 'mark' => [], 'nb' => 0,
                    ];
                });
        }

        $grades = DB::table('student_grades')
            ->whereNull('deleted_at')
            ->where('student_hemis_id', $hemisId)
            ->whereBetween('lesson_date', [$from, $to])
            ->where('training_type_code', '!=', 11)
            ->orderBy('lesson_date')
            ->orderBy('lesson_pair_code')
            ->get(['subject_id', 'subject_name', 'lesson_date', 'lesson_pair_code', 'lesson_pair_name', 'training_type_code', 'grade', 'retake_grade', 'reason']);

        foreach ($grades as $g) {
            $key = (string) $g->subject_id;
            $subjects[$key] ??= ['name' => trim($g->subject_name), 'lessons' => 0, 'hemis' => [], 'mark' => [], 'nb' => 0];
            $label = Carbon::parse($g->lesson_date)->format('d.m')
                . (isset(self::TYPE_LABELS[(int) $g->training_type_code])
                    ? ' ' . self::TYPE_LABELS[(int) $g->training_type_code]
                    : ', ' . $this->pairLabel($g, $pairNames));

            if ($g->reason === 'absent') {
                // NB: ustiga LMS'da baho qo'yilgan bo'lsa — Markda (otrabotka retake_grade da yoki ochilgan katak grade da)
                $value = $g->retake_grade ?? $g->grade;
                if ($value !== null) {
                    $subjects[$key]['mark'][] = $label . ': ' . $this->num($value);
                } else {
                    $subjects[$key]['nb']++;
                }
            } elseif ($g->grade !== null) {
                $subjects[$key]['hemis'][] = $label . ': ' . $this->num($g->grade);
            }
        }

        uasort($subjects, fn ($a, $b) => strcmp($a['name'], $b['name']));

        return array_values($subjects);
    }

    /**
     * "1-juftlik": juftlik nomidagi raqam (baho yozuvidan, bo'lmasa jadvaldan — LMS'da yaratilgan
     * yozuvlarda nom "Manual" bo'lishi mumkin); topilmasa — HEMIS juftlik kodi.
     */
    private function pairLabel(object $g, array $pairNames): string
    {
        foreach ([$g->lesson_pair_name ?? null, $pairNames[(string) $g->lesson_pair_code] ?? null] as $name) {
            if ($name !== null && preg_match('/\d+/', (string) $name, $m)) {
                return (int) $m[0] . '-juftlik';
            }
        }

        return 'juftlik ' . $g->lesson_pair_code;
    }

    private function num($v): string
    {
        return rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
    }

    public function columnWidths(): array
    {
        return ['A' => 6, 'B' => 32, 'C' => 12, 'D' => 18, 'E' => 18, 'F' => 9, 'G' => 30, 'H' => 23, 'I' => 28,
            'J' => 38, 'K' => 12, 'L' => 45, 'M' => 45, 'N' => 11];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->getStyle('A1:N1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '1E3A8A']],
            'alignment' => ['horizontal' => 'center', 'vertical' => 'center', 'wrapText' => true],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(32);
        $sheet->freezePane('A2');
        $last = max($sheet->getHighestRow(), $this->rowCount + 1);
        // Har bir katak chegarasi ko'rinsin (rangli ustunlarda Excel to'r chiziqlari yo'qoladi)
        $sheet->getStyle("A1:N{$last}")->getBorders()->getAllBorders()
            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN)->getColor()->setRGB('CBD5E1');
        if ($last > 1) {
            $sheet->setAutoFilter("A1:N{$last}");
            $sheet->getStyle("A2:N{$last}")->getAlignment()->setVertical('top');
            $sheet->getStyle("L2:M{$last}")->getAlignment()->setWrapText(true);
            $sheet->getStyle("L2:L{$last}")->getFill()->applyFromArray(['fillType' => 'solid', 'startColor' => ['rgb' => 'EFF6FF']]);
            $sheet->getStyle("M2:M{$last}")->getFill()->applyFromArray(['fillType' => 'solid', 'startColor' => ['rgb' => 'ECFDF5']]);
        }

        return [];
    }

    public function title(): string
    {
        return 'Baholar';
    }
}
