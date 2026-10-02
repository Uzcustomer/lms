<?php

namespace App\Exports;

use App\Models\Teacher;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * "DB ma'lumotlar" bo'limi uchun xodimlar jadvali — HEMIS id'lari bilan.
 *
 * Parol, login kodi va Telegram tasdiq kodi kabi maxfiy maydonlar
 * chiqarilmaydi: fayl qo'ldan qo'lga o'tadi.
 *
 * Ustunlar aniq sanab o'tiladi (model->toArray() emas): jadvalga yangi
 * ustun qo'shilsa fayl o'z-o'zidan o'zgarib ketmasin va maxfiy maydon
 * tasodifan chiqib qolmasin.
 */
class TeachersDbExport implements FromQuery, ShouldAutoSize, WithChunkReading, WithHeadings, WithMapping, WithStyles
{
    use Exportable;

    /** Excelga chiqadigan ustunlar — sarlavha bilan bir tartibda */
    private const COLUMNS = [
        'id',
        'hemis_id',
        'meta_id',
        'employee_id_number',
        'full_name',
        'short_name',
        'first_name',
        'second_name',
        'third_name',
        'birth_date',
        'gender',
        'department_hemis_id',
        'department',
        'staff_position',
        'lavozim',
        'specialty',
        'employment_form',
        'employment_staff',
        'employee_status',
        'employee_type',
        'contract_number',
        'contract_date',
        'decree_number',
        'decree_date',
        'year_of_enter',
        'login',
        'status',
        'is_active',
        'role',
        'phone',
        'telegram_chat_id',
        'telegram_username',
        'telegram_verified_at',
        'assigned_firm',
        'image',
        'created_at',
        'updated_at',
    ];

    public function query()
    {
        return Teacher::query()
            ->select(self::COLUMNS)
            ->orderBy('full_name');
    }

    public function headings(): array
    {
        return [
            'ID',
            'HEMIS ID',
            'Meta ID',
            'Xodim ID raqami',
            'F.I.Sh',
            'Qisqa ism',
            'Ism',
            'Familiya',
            'Otasining ismi',
            'Tug\'ilgan sana',
            'Jinsi',
            'Kafedra HEMIS ID',
            'Kafedra',
            'Shtat lavozimi',
            'Lavozimi (LMS)',
            'Mutaxassisligi',
            'Bandlik shakli',
            'Shtat turi',
            'Xodim holati',
            'Xodim turi',
            'Shartnoma raqami',
            'Shartnoma sanasi',
            'Buyruq raqami',
            'Buyruq sanasi',
            'Ishga kirgan yili',
            'Login',
            'Status',
            'Faol',
            'Rol (eski maydon)',
            'Telefon',
            'Telegram chat ID',
            'Telegram username',
            'Telegram tasdiqlangan',
            'Biriktirilgan firma',
            'Rasm',
            'Yaratilgan',
            'Yangilangan',
        ];
    }

    /** @param  Teacher  $teacher */
    public function map($teacher): array
    {
        $date = fn ($value) => $value ? \Carbon\Carbon::parse($value)->format('d.m.Y') : '';
        $dateTime = fn ($value) => $value ? \Carbon\Carbon::parse($value)->format('d.m.Y H:i') : '';

        return [
            $teacher->id,
            $teacher->hemis_id,
            $teacher->meta_id,
            $teacher->employee_id_number,
            $teacher->full_name,
            $teacher->short_name,
            $teacher->first_name,
            $teacher->second_name,
            $teacher->third_name,
            $date($teacher->birth_date),
            $teacher->gender,
            $teacher->department_hemis_id,
            $teacher->department,
            $teacher->staff_position,
            $teacher->lavozim,
            $teacher->specialty,
            $teacher->employment_form,
            $teacher->employment_staff,
            $teacher->employee_status,
            $teacher->employee_type,
            $teacher->contract_number,
            $date($teacher->contract_date),
            $teacher->decree_number,
            $date($teacher->decree_date),
            $teacher->year_of_enter,
            $teacher->login,
            $teacher->status,
            $teacher->is_active ? 'Ha' : "Yo'q",
            $teacher->role,
            $teacher->phone,
            $teacher->telegram_chat_id,
            $teacher->telegram_username,
            $dateTime($teacher->telegram_verified_at),
            $teacher->assigned_firm,
            $teacher->image,
            $dateTime($teacher->created_at),
            $dateTime($teacher->updated_at),
        ];
    }

    public function chunkSize(): int
    {
        return 500;
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
