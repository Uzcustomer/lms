<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Tanlangan o'quv yilining kuzgi (toq) yoki bahorgi (juft) semestrida har bir
 * fakultet → yo'nalish → kurs qaysi fanlarni o'qigani: fan, yopilish shakli,
 * jami soat, kredit va mashg'ulot turlari bo'yicha soatlar.
 *
 * Manba — o'quv rejasi (curriculum_subjects + semesters). Semestr kodi
 * juft (12, 14, …) bo'lsa bahorgi, toq (11, 13, …) bo'lsa kuzgi.
 */
class SemesterSubjectsExport implements FromCollection, WithHeadings, WithStyles, WithTitle, ShouldAutoSize
{
    use Exportable;

    private const CLOSING_FORMS = [
        'oski' => 'Faqat OSKI',
        'test' => 'Faqat Test',
        'oski_test' => 'OSKI + Test',
        'normativ' => 'Normativ',
        'sinov' => 'Sinov (test)',
        'none' => "Yo'q",
    ];

    /** Mashg'ulot turlari ustunlarining tartibi (nomidagi kalit so'z bo'yicha) */
    private const TYPE_ORDER = ['maruza', 'amaliy', 'laboratoriya', 'klinik', 'seminar', 'mustaqil'];

    private const FIXED_HEADINGS = [
        '#', "Ta'lim turi", 'Fakultet', "Yo'nalish", 'Kurs', 'Semestr', "O'quv rejasi",
        'Fan', 'Yopilish shakli', 'Jami soat', 'Kredit',
    ];

    /** @var array<string, string> type code => name (tartiblangan) */
    private array $types = [];

    private int $dataRows = 0;

    /** Bir marta hisoblanadi: sarlavha ham, ma'lumot ham shundan oladi */
    private ?Collection $built = null;

    public function __construct(
        private string $educationYear,
        private bool $spring = true,
        private ?string $educationType = null,
    ) {
    }

    public function title(): string
    {
        return ($this->spring ? 'Bahorgi' : 'Kuzgi') . ' ' . $this->educationYear;
    }

    public function collection(): Collection
    {
        return $this->build();
    }

    /**
     * Laravel Excel sarlavhalarni ma'lumotdan OLDIN so'raydi, mashg'ulot
     * turlari ustunlari esa ma'lumotdan chiqadi — shuning uchun ikkalasi ham
     * shu yerda bir marta tayyorlanadi.
     */
    private function build(): Collection
    {
        if ($this->built !== null) {
            return $this->built;
        }

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
                'c.education_type_name',
                'f.name as faculty_name',
                'sp.name as specialty_name',
                's.level_code',
                's.level_name',
                's.code as semester_code',
                's.name as semester_name',
                'c.name as curriculum_name',
                'cs.subject_name',
                'cs.closing_form',
                'cs.total_acload',
                'cs.credit',
                'cs.subject_details',
            ])
            ->distinct()
            ->orderBy('f.name')
            ->orderBy('sp.name')
            ->orderBy('s.level_code')
            ->orderBy('c.name')
            ->orderBy('cs.subject_name')
            ->get();

        // Mashg'ulot turlari — barcha fanlardan yig'iladi, keyin ustun bo'ladi
        $loads = [];
        $types = [];
        foreach ($rows as $i => $row) {
            $details = is_string($row->subject_details) ? json_decode($row->subject_details, true) : $row->subject_details;
            $loads[$i] = [];
            if (is_array($details)) {
                foreach ($details as $d) {
                    $code = (string) ($d['trainingType']['code'] ?? '');
                    $name = (string) ($d['trainingType']['name'] ?? '');
                    if ($code === '' || $name === '') {
                        continue;
                    }
                    $types[$code] = $name;
                    $loads[$i][$code] = (int) ($d['academic_load'] ?? 0);
                }
            }
        }
        $this->types = $this->sortTypes($types);

        $out = collect();
        foreach ($rows as $i => $row) {
            $cells = [
                $i + 1,
                $row->education_type_name ?? '-',
                $row->faculty_name ?? '-',
                $row->specialty_name ?? '-',
                $row->level_name ?? $row->level_code,
                $row->semester_name ?? $row->semester_code,
                $row->curriculum_name ?? '-',
                $row->subject_name ?? '-',
                $row->closing_form ? (self::CLOSING_FORMS[$row->closing_form] ?? $row->closing_form) : 'Belgilanmagan',
                $row->total_acload ?? '',
                $row->credit ?? '',
            ];
            foreach (array_keys($this->types) as $code) {
                $hours = $loads[$i][$code] ?? 0;
                $cells[] = $hours > 0 ? $hours : '';
            }
            $out->push($cells);
        }

        $this->dataRows = $out->count();

        return $this->built = $out;
    }

    public function headings(): array
    {
        $this->build();

        return array_merge(self::FIXED_HEADINGS, array_values($this->types));
    }

    public function styles(Worksheet $sheet): array
    {
        $this->build();
        $lastCol = count(self::FIXED_HEADINGS) + count($this->types);
        $endLetter = Coordinate::stringFromColumnIndex($lastCol);
        $lastRow = $this->dataRows + 1;

        $sheet->getStyle("A1:{$endLetter}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '1A3268']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(40);
        $sheet->freezePane('I2');

        if ($this->dataRows > 0) {
            // Yopilish shakli — och sariq, soatlar markazda
            $sheet->getStyle("I2:I{$lastRow}")->getFill()->setFillType('solid')->getStartColor()->setRGB('FFF2CC');
            $sheet->getStyle("J2:{$endLetter}{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("A1:{$endLetter}{$lastRow}")->getBorders()->getAllBorders()
                ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('BFBFBF');
        }

        return [];
    }

    /** Ma'ruza, amaliy, laboratoriya… tartibida; qolganlari oxirida */
    private function sortTypes(array $types): array
    {
        $normalize = fn ($str) => preg_replace('/[^a-z\x{0400}-\x{04FF}]/u', '', mb_strtolower($str));
        $position = function (string $name) use ($normalize): int {
            $n = $normalize($name);
            foreach (self::TYPE_ORDER as $i => $keyword) {
                if (str_contains($n, $keyword)) {
                    return $i;
                }
            }

            return count(self::TYPE_ORDER);
        };

        uasort($types, fn ($a, $b) => $position($a) <=> $position($b) ?: strcmp($a, $b));

        return $types;
    }
}
