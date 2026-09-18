<?php

namespace App\Exports;

use App\Models\CurriculumSubject;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * O'quv reja fanlari — "DB ma'lumotlari" sahifasidan eksport.
 *
 * Ustunlar map() orqali aniq sanab chiqiladi: modelni butunligicha yozish
 * mumkin emas, chunki subject_details va subject_exam_types massiv sifatida
 * o'qiladi (Excel katagiga massiv yozilmaydi — 500 xato beradi) va jadval
 * ustunlari sarlavhalar bilan bir tartibda emas.
 */
class CurriculumSubjectsExport implements FromQuery, WithMapping, WithHeadings, WithColumnWidths, WithStyles, WithChunkReading
{
    use Exportable;

    public function query()
    {
        return CurriculumSubject::query()
            ->select([
                'id', 'curriculum_subject_hemis_id', 'curricula_hemis_id', 'subject_id',
                'subject_name', 'subject_code', 'subject_type_code', 'subject_type_name',
                'subject_block_code', 'subject_block_name', 'semester_code', 'semester_name',
                'total_acload', 'credit', 'in_group', 'at_semester', 'is_active',
                'rating_grade_code', 'rating_grade_name', 'exam_finish_code', 'exam_finish_name',
                'department_id', 'department_name', 'created_at', 'updated_at',
            ])
            ->orderBy('curricula_hemis_id')
            ->orderBy('semester_code')
            ->orderBy('id');
    }

    public function map($row): array
    {
        return [
            $row->id,
            $row->curriculum_subject_hemis_id,
            $row->curricula_hemis_id,
            $row->subject_id,
            $row->subject_name,
            $row->subject_code,
            $row->subject_type_code,
            $row->subject_type_name,
            $row->subject_block_code,
            $row->subject_block_name,
            $row->semester_code,
            $row->semester_name,
            $row->total_acload,
            $row->credit,
            $row->in_group,
            $row->at_semester ? 'Ha' : "Yo'q",
            $row->is_active ? 'Ha' : "Yo'q",
            $row->rating_grade_code,
            $row->rating_grade_name,
            $row->exam_finish_code,
            $row->exam_finish_name,
            $row->department_id,
            $row->department_name,
            optional($row->created_at)->format('d.m.Y H:i'),
            optional($row->updated_at)->format('d.m.Y H:i'),
        ];
    }

    public function headings(): array
    {
        return [
            'ID',
            'Curriculum Subject HEMIS ID',
            'Curricula HEMIS ID',
            'Fan ID',
            'Fan nomi',
            'Fan kodi',
            'Fan turi kodi',
            'Fan turi nomi',
            'Fan bloki kodi',
            'Fan bloki nomi',
            'Semestr kodi',
            'Semestr nomi',
            'Jami soat',
            'Kredit',
            'Guruhda',
            'Semestrda',
            'Faol',
            'Reyting baho kodi',
            'Reyting baho nomi',
            'Yakuniy nazorat kodi',
            'Yakuniy nazorat nomi',
            'Kafedra ID',
            'Kafedra nomi',
            'Yaratilgan',
            'Yangilangan',
        ];
    }

    /**
     * Qat'iy kengliklar: ShouldAutoSize har bir katakni o'lchab chiqadi va
     * o'n minglab qatorda sekin ishlaydi hamda ko'p xotira oladi.
     */
    public function columnWidths(): array
    {
        return [
            'A' => 8,  'B' => 14, 'C' => 12, 'D' => 10, 'E' => 45, 'F' => 14,
            'G' => 10, 'H' => 22, 'I' => 10, 'J' => 22, 'K' => 10, 'L' => 14,
            'M' => 10, 'N' => 8,  'O' => 10, 'P' => 10, 'Q' => 8,  'R' => 10,
            'S' => 18, 'T' => 10, 'U' => 18, 'V' => 12, 'W' => 40, 'X' => 17,
            'Y' => 17,
        ];
    }

    public function chunkSize(): int
    {
        return 1000;
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '1a3268']],
            ],
        ];
    }
}
