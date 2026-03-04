<?php

namespace Database\Seeders;

use App\Models\AbsenceExcuse;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AbsenceExcuseSeeder extends Seeder
{
    public function run(): void
    {
        $records = [
            ['368221100090', 'kasallik', '28.01.2026', '02.02.2026', '11SuV 000522226', 'Kasallik (vaqtincha mehnatga layoqatsizlik)'],
            ['368251100241', 'kasallik', '26.01.2026', '31.01.2026', '09SaV 000239726', 'Kasallik (vaqtincha mehnatga layoqatsizlik)'],
            ['368211100307', 'kasallik', '24.01.2026', '31.01.2026', '06QaV 000032926', 'Kasallik (vaqtincha mehnatga layoqatsizlik)'],
            ['368211100603', 'yaqin_qarindosh', '27.01.2026', '30.01.2026', '№06994352', 'Yaqin qarindoshning to\'yi yoki vafoti'],
            ['368221100078', 'kasallik', '26.01.2026', '30.01.2026', '11SuV 000491626', 'Kasallik (vaqtincha mehnatga layoqatsizlik)'],
            ['368211100338', 'musobaqa_tadbir', '20.02.2026', '20.02.2026', null, 'Musobaqalar va tadbirlarda qatnashish'],
            ['368211100526', 'homiladorlik', '19.02.2026', '28.02.2026', '11SuV 001587526', 'Homiladorlik va tug\'ruq'],
            ['368221100090', 'kasallik', '24.02.2026', '28.02.2026', '11SuV 001809126', 'Kasallik (vaqtincha mehnatga layoqatsizlik)'],
            ['368241100809', 'kasallik', '10.02.2026', '23.02.2026', '11SuV 001049626', 'Kasallik (vaqtincha mehnatga layoqatsizlik)'],
            ['368231100601', 'musobaqa_tadbir', '16.02.2026', '21.02.2026', '0248/2026', 'Musobaqalar va tadbirlarda qatnashish'],
            ['368231100825', 'kasallik', '16.02.2026', '21.02.2026', '09SaV 000844126', 'Kasallik (vaqtincha mehnatga layoqatsizlik)'],
            ['368231100612', 'musobaqa_tadbir', '16.02.2026', '21.02.2026', '0248/2026', 'Musobaqalar va tadbirlarda qatnashish'],
            ['368221100757', 'kasallik', '26.02.2026', '27.02.2026', '11SuV 001930526', 'Kasallik (vaqtincha mehnatga layoqatsizlik)'],
            ['368211100694', 'kasallik', '20.01.2026', '21.02.2026', '11SuV 001860526', 'Kasallik (vaqtincha mehnatga layoqatsizlik)'],
            ['368251100074', 'kasallik', '09.02.2026', '21.02.2026', '11SuV 001270626 va 11SuV 001318126', 'Kasallik (vaqtincha mehnatga layoqatsizlik)'],
            ['368221100249', 'kasallik', '23.02.2026', '26.02.2026', '09SaV 000780726', 'Kasallik (vaqtincha mehnatga layoqatsizlik)'],
            ['368231100652', 'musobaqa_tadbir', '16.02.2026', '21.02.2026', '0248/2026', 'Musobaqalar va tadbirlarda qatnashish'],
            ['368231100318', 'musobaqa_tadbir', '16.02.2026', '21.02.2026', '0248/2026', 'Musobaqalar va tadbirlarda qatnashish'],
            ['368241100492', 'musobaqa_tadbir', '16.02.2026', '21.02.2026', '0248/2026', 'Musobaqalar va tadbirlarda qatnashish'],
            ['368241100569', 'kasallik', '09.02.2026', '18.02.2026', '06QV 000022426', 'Kasallik (vaqtincha mehnatga layoqatsizlik)'],
            ['368211100763', 'kasallik', '26.01.2026', '16.02.2026', '11SuV 001598526', 'Kasallik (vaqtincha mehnatga layoqatsizlik)'],
            ['368211100142', 'musobaqa_tadbir', '16.02.2026', '21.02.2026', '0248/2026', 'Musobaqalar va tadbirlarda qatnashish'],
            ['368251100961', 'kasallik', '10.02.2026', '21.02.2026', '11SuV 001738926', 'Kasallik (vaqtincha mehnatga layoqatsizlik)'],
            ['368211100676', 'kasallik', '23.02.2026', '24.02.2026', '11SuV 001859626', 'Kasallik (vaqtincha mehnatga layoqatsizlik)'],
            ['368251101407', 'kasallik', '20.02.2026', '24.02.2026', '11SuV 001662626', 'Kasallik (vaqtincha mehnatga layoqatsizlik)'],
            ['368231100401', 'kasallik', '19.02.2026', '23.02.2026', '11SuV 001577326', 'Kasallik (vaqtincha mehnatga layoqatsizlik)'],
            ['368211100460', 'musobaqa_tadbir', '16.02.2026', '21.02.2026', '0248/2026', 'Musobaqalar va tadbirlarda qatnashish'],
            ['368221100081', 'musobaqa_tadbir', '16.02.2026', '21.02.2026', '0248/2026', 'Musobaqalar va tadbirlarda qatnashish'],
            ['368221100216', 'musobaqa_tadbir', '16.02.2026', '21.02.2026', '0248/2026', 'Musobaqalar va tadbirlarda qatnashish'],
            ['368231100626', 'musobaqa_tadbir', '16.02.2026', '21.02.2026', '0248/2026', 'Musobaqalar va tadbirlarda qatnashish'],
            ['368231100703', 'musobaqa_tadbir', '16.02.2026', '21.02.2026', '0248/2026', 'Musobaqalar va tadbirlarda qatnashish'],
            ['368241100923', 'kasallik', '17.02.2026', '18.02.2026', '11SuV 001393626', 'Kasallik (vaqtincha mehnatga layoqatsizlik)'],
            ['368211100135', 'musobaqa_tadbir', '16.02.2026', '21.02.2026', '0248/2026', 'Musobaqalar va tadbirlarda qatnashish'],
            ['368221100224', 'musobaqa_tadbir', '16.02.2026', '21.02.2026', '0248/2026', 'Musobaqalar va tadbirlarda qatnashish'],
            ['368221100511', 'kasallik', '26.01.2026', '19.02.2026', '11SuV 000314426', 'Kasallik (vaqtincha mehnatga layoqatsizlik)'],
            ['368231100472', 'musobaqa_tadbir', '16.02.2026', '21.02.2026', '0248/2026', 'Musobaqalar va tadbirlarda qatnashish'],
            ['368211100635', 'musobaqa_tadbir', '16.02.2026', '21.02.2026', '0248/2026', 'Musobaqalar va tadbirlarda qatnashish'],
            ['368241100220', 'kasallik', '14.02.2026', '14.02.2026', '11SuV 001288326', 'Kasallik (vaqtincha mehnatga layoqatsizlik)'],
            ['368211100468', 'musobaqa_tadbir', '16.02.2026', '21.02.2026', '0248/2026', 'Musobaqalar va tadbirlarda qatnashish'],
            ['368211100209', 'musobaqa_tadbir', '16.02.2026', '21.02.2026', '0248/2026', 'Musobaqalar va tadbirlarda qatnashish'],
            ['368221100047', 'musobaqa_tadbir', '16.02.2026', '21.02.2026', '0248/2026', 'Musobaqalar va tadbirlarda qatnashish'],
            ['368221100199', 'musobaqa_tadbir', '16.02.2026', '21.02.2026', '0248/2026', 'Musobaqalar va tadbirlarda qatnashish'],
            ['368211100449', 'musobaqa_tadbir', '16.02.2026', '21.02.2026', '0248/2026', 'Musobaqalar va tadbirlarda qatnashish'],
            ['368211100102', 'homiladorlik', '16.02.2026', '20.02.2026', '11SuV 001648526', 'Homiladorlik va tug\'ruq'],
            ['368251100868', 'kasallik', '19.02.2026', '20.02.2026', '06QV 000046326', 'Kasallik (vaqtincha mehnatga layoqatsizlik)'],
            ['368241100811', 'nikoh_toyi', '09.02.2026', '19.02.2026', '№ 07168768', 'Talabaning nikoh to\'yi'],
            ['368231100382', 'kasallik', '09.02.2026', '14.02.2026', '11SuV 001284926', 'Kasallik (vaqtincha mehnatga layoqatsizlik)'],
            ['368221100441', 'kasallik', '09.02.2026', '19.02.2026', '11SuV 001534626', 'Kasallik (vaqtincha mehnatga layoqatsizlik)'],
            ['368251101385', 'kasallik', '11.02.2026', '11.02.2026', '11SuV 001135926', 'Kasallik (vaqtincha mehnatga layoqatsizlik)'],
            ['368211100221', 'kasallik', '09.02.2026', '10.02.2026', '08NmV 000268726', 'Kasallik (vaqtincha mehnatga layoqatsizlik)'],
            ['368221100088', 'kasallik', '14.02.2026', '17.02.2026', '11SuV 001507926', 'Kasallik (vaqtincha mehnatga layoqatsizlik)'],
            ['368211100585', 'kasallik', '12.02.2026', '14.02.2026', '01TSH 000496726', 'Kasallik (vaqtincha mehnatga layoqatsizlik)'],
            ['368211100332', 'kasallik', '11.02.2026', '14.02.2026', '11SuV 001112626', 'Kasallik (vaqtincha mehnatga layoqatsizlik)'],
            ['368211100676', 'kasallik', '09.02.2026', '10.02.2026', '11SuV 001068523', 'Kasallik (vaqtincha mehnatga layoqatsizlik)'],
            ['368211100243', 'kasallik', '12.02.2026', '14.02.2026', '06QV 000020626', 'Kasallik (vaqtincha mehnatga layoqatsizlik)'],
            ['368211100200', 'kasallik', '06.02.2026', '10.02.2026', '11SuV 001333926', 'Kasallik (vaqtincha mehnatga layoqatsizlik)'],
            ['368211100595', 'homiladorlik', '06.02.2026', '10.02.2026', '11SuV 000866226', 'Homiladorlik va tug\'ruq'],
            ['368251100187', 'kasallik', '09.02.2026', '11.02.2026', '11SuV 000949626', 'Kasallik (vaqtincha mehnatga layoqatsizlik)'],
            ['368211100651', 'kasallik', '03.02.2026', '11.02.2026', '11SuV 001192726', 'Kasallik (vaqtincha mehnatga layoqatsizlik)'],
            ['368251101241', 'kasallik', '31.01.2026', '06.02.2026', '01TSH 002443126', 'Kasallik (vaqtincha mehnatga layoqatsizlik)'],
            ['368251101107', 'kasallik', '26.01.2026', '09.02.2026', '02QR 000160526', 'Kasallik (vaqtincha mehnatga layoqatsizlik)'],
            ['368221100675', 'kasallik', '06.02.2026', '09.02.2026', '11SuV 000872726', 'Kasallik (vaqtincha mehnatga layoqatsizlik)'],
            ['368211100103', 'homiladorlik', '28.01.2026', '04.02.2026', 'T 50649671', 'Homiladorlik va tug\'ruq'],
            ['368231100063', 'kasallik', '26.01.2026', '05.02.2026', '11SuV 000860626', 'Kasallik (vaqtincha mehnatga layoqatsizlik)'],
            ['368211100045', 'kasallik', '28.01.2026', '31.01.2026', '11SuV 000878026', 'Kasallik (vaqtincha mehnatga layoqatsizlik)'],
            ['368211100595', 'homiladorlik', '30.01.2026', '06.02.2026', '11SuV 000882626', 'Homiladorlik va tug\'ruq'],
            ['368231100497', 'kasallik', '02.02.2026', '07.02.2026', '11SuV 000973726', 'Kasallik (vaqtincha mehnatga layoqatsizlik)'],
            ['368251100868', 'kasallik', '02.02.2026', '04.02.2026', '06QV 000081826', 'Kasallik (vaqtincha mehnatga layoqatsizlik)'],
            ['368231100693', 'yaqin_qarindosh', '02.02.2026', '06.02.2026', 'V 50717117', 'Yaqin qarindoshning to\'yi yoki vafoti'],
            ['368231100222', 'nikoh_toyi', '16.02.2026', '18.02.2026', '7522575', 'Talabaning nikoh to\'yi'],
            ['368211100489', 'kasallik', '25.02.2026', '27.02.2026', '11SuV 001860126', 'Kasallik (vaqtincha mehnatga layoqatsizlik)'],
            ['368211100489', 'kasallik', '19.02.2026', '21.02.2026', '11SuV 001571126', 'Kasallik (vaqtincha mehnatga layoqatsizlik)'],
        ];

        $imported = 0;
        $skipped = 0;
        $errors = [];

        foreach ($records as $index => $record) {
            [$hemisId, $reason, $startDate, $endDate, $docNumber, $description] = $record;

            $student = Student::where('student_id_number', $hemisId)->first();
            if (!$student) {
                $errors[] = "Qator " . ($index + 1) . ": Talaba topilmadi - student_id_number: {$hemisId}";
                continue;
            }

            $start = Carbon::createFromFormat('d.m.Y', $startDate)->startOfDay();
            $end = Carbon::createFromFormat('d.m.Y', $endDate)->startOfDay();

            // Dublikat tekshiruvi
            $exists = AbsenceExcuse::where('student_hemis_id', $hemisId)
                ->where('reason', $reason)
                ->where('start_date', $start)
                ->where('end_date', $end)
                ->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            AbsenceExcuse::create([
                'student_id' => $student->id,
                'student_hemis_id' => $student->hemis_id ?? $hemisId,
                'student_full_name' => $student->full_name ?? $student->short_name,
                'group_name' => $student->group_name,
                'department_name' => $student->department_name,
                'reason' => $reason,
                'start_date' => $start,
                'end_date' => $end,
                'doc_number' => $docNumber,
                'description' => $description,
                'file_path' => '',
                'file_original_name' => '',
                'status' => 'approved',
                'reviewed_at' => now(),
                'verification_token' => Str::uuid()->toString(),
            ]);

            $imported++;
        }

        $this->command->info("Import yakunlandi: {$imported} ta qo'shildi, {$skipped} ta dublikat o'tkazib yuborildi.");

        if (!empty($errors)) {
            $this->command->warn("Xatoliklar:");
            foreach ($errors as $error) {
                $this->command->warn("  - {$error}");
            }
        }
    }
}
