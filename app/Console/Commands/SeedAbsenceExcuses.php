<?php

namespace App\Console\Commands;

use App\Models\AbsenceExcuse;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SeedAbsenceExcuses extends Command
{
    protected $signature = 'absence:seed';
    protected $description = 'Seed absence excuses from predefined data';

    public function handle()
    {
        $records = [
            ['368221100090', 'kasallik', '28.01.2026', '02.02.2026', '11SuV 000522226', 'Kasallik (vaqtincha mehnatga layoqatsizlik)', 'N.Annaqulova'],
            ['368251100241', 'kasallik', '26.01.2026', '31.01.2026', '09SaV 000239726', 'Kasallik (vaqtincha mehnatga layoqatsizlik)', 'A.Sharipov'],
            ['368211100307', 'kasallik', '24.01.2026', '31.01.2026', '06QV 000032926', 'Kasallik (vaqtincha mehnatga layoqatsizlik)', 'A.Sharipov'],
            ['368211100603', 'yaqin_qarindosh', '27.01.2026', '30.01.2026', '№06994352', 'Yaqin qarindoshning to\'yi yoki vafoti', 'A.Sharipov'],
            ['368221100078', 'kasallik', '26.01.2026', '30.01.2026', '11SuV 000491626', 'Kasallik (vaqtincha mehnatga layoqatsizlik)', 'A.Sharipov'],
            ['368211100338', 'musobaqa_tadbir', '20.02.2026', '20.02.2026', null, 'Musobaqalar va tadbirlarda qatnashish', 'A.Sharipov'],
            ['368211100526', 'homiladorlik', '19.02.2026', '28.02.2026', '11SuV 001587526', 'Homiladorlik va tug\'ruq', 'N.Annaqulova'],
            ['368221100090', 'kasallik', '24.02.2026', '28.02.2026', '11SuV 001809126', 'Kasallik (vaqtincha mehnatga layoqatsizlik)', 'N.Annaqulova'],
            ['368241100809', 'kasallik', '10.02.2026', '23.02.2026', '11SuV 001049626', 'Kasallik (vaqtincha mehnatga layoqatsizlik)', 'N.Annaqulova'],
            ['368231100601', 'musobaqa_tadbir', '16.02.2026', '21.02.2026', '0248/2026', 'Musobaqalar va tadbirlarda qatnashish', 'M.Berdiyarova'],
            ['368231100825', 'kasallik', '16.02.2026', '21.02.2026', '09SaV 000844126', 'Kasallik (vaqtincha mehnatga layoqatsizlik)', 'A.G\'oziyeva'],
            ['368231100612', 'musobaqa_tadbir', '16.02.2026', '21.02.2026', '0248/2026', 'Musobaqalar va tadbirlarda qatnashish', 'N.Annaqulova'],
            ['368221100757', 'kasallik', '26.02.2026', '27.02.2026', '11SuV 001930526', 'Kasallik (vaqtincha mehnatga layoqatsizlik)', 'N.Annaqulova'],
            ['368211100694', 'kasallik', '20.01.2026', '21.02.2026', '11SuV 001860526', 'Kasallik (vaqtincha mehnatga layoqatsizlik)', 'N.Annaqulova'],
            ['368251100074', 'kasallik', '09.02.2026', '21.02.2026', '11SuV 001270626 va 11SuV 001318126', 'Kasallik (vaqtincha mehnatga layoqatsizlik)', 'N.Annaqulova'],
            ['368221100249', 'kasallik', '23.02.2026', '26.02.2026', '09SaV 000780726', 'Kasallik (vaqtincha mehnatga layoqatsizlik)', 'N.Annaqulova'],
            ['368231100652', 'musobaqa_tadbir', '16.02.2026', '21.02.2026', '0248/2026', 'Musobaqalar va tadbirlarda qatnashish', 'N.Annaqulova'],
            ['368231100318', 'musobaqa_tadbir', '16.02.2026', '21.02.2026', '0248/2026', 'Musobaqalar va tadbirlarda qatnashish', 'A.G\'oziyeva'],
            ['368241100492', 'musobaqa_tadbir', '16.02.2026', '21.02.2026', '0248/2026', 'Musobaqalar va tadbirlarda qatnashish', 'A.G\'oziyeva'],
            ['368241100569', 'kasallik', '09.02.2026', '18.02.2026', '06QV 000022426', 'Kasallik (vaqtincha mehnatga layoqatsizlik)', 'N.Annaqulova'],
            ['368211100763', 'kasallik', '26.01.2026', '16.02.2026', '11SuV 001598526', 'Kasallik (vaqtincha mehnatga layoqatsizlik)', 'Super admin'],
            ['368211100142', 'musobaqa_tadbir', '16.02.2026', '21.02.2026', '0248/2026', 'Musobaqalar va tadbirlarda qatnashish', 'N.Annaqulova'],
            ['368251100961', 'kasallik', '10.02.2026', '21.02.2026', '11SuV 001738926', 'Kasallik (vaqtincha mehnatga layoqatsizlik)', 'N.Annaqulova'],
            ['368211100676', 'kasallik', '23.02.2026', '24.02.2026', '11SuV 001859626', 'Kasallik (vaqtincha mehnatga layoqatsizlik)', 'M.Berdiyarova'],
            ['368251101407', 'kasallik', '20.02.2026', '24.02.2026', '11SuV 001662626', 'Kasallik (vaqtincha mehnatga layoqatsizlik)', 'M.Berdiyarova'],
            ['368231100401', 'kasallik', '19.02.2026', '23.02.2026', '11SuV 001577326', 'Kasallik (vaqtincha mehnatga layoqatsizlik)', 'M.O\'rolov'],
            ['368211100460', 'musobaqa_tadbir', '16.02.2026', '21.02.2026', '0248/2026', 'Musobaqalar va tadbirlarda qatnashish', 'N.Annaqulova'],
            ['368221100081', 'musobaqa_tadbir', '16.02.2026', '21.02.2026', '0248/2026', 'Musobaqalar va tadbirlarda qatnashish', 'M.O\'rolov'],
            ['368221100216', 'musobaqa_tadbir', '16.02.2026', '21.02.2026', '0248/2026', 'Musobaqalar va tadbirlarda qatnashish', 'N.Annaqulova'],
            ['368231100626', 'musobaqa_tadbir', '16.02.2026', '21.02.2026', '0248/2026', 'Musobaqalar va tadbirlarda qatnashish', 'N.Annaqulova'],
            ['368231100703', 'musobaqa_tadbir', '16.02.2026', '21.02.2026', '0248/2026', 'Musobaqalar va tadbirlarda qatnashish', 'N.Annaqulova'],
            ['368241100923', 'kasallik', '17.02.2026', '18.02.2026', '11SuV 001393626', 'Kasallik (vaqtincha mehnatga layoqatsizlik)', 'M.Berdiyarova'],
            ['368211100135', 'musobaqa_tadbir', '16.02.2026', '21.02.2026', '0248/2026', 'Musobaqalar va tadbirlarda qatnashish', 'N.Annaqulova'],
            ['368221100224', 'musobaqa_tadbir', '16.02.2026', '21.02.2026', '0248/2026', 'Musobaqalar va tadbirlarda qatnashish', 'N.Annaqulova'],
            ['368221100511', 'kasallik', '26.01.2026', '19.02.2026', '11SuV 000314426', 'Kasallik (vaqtincha mehnatga layoqatsizlik)', 'N.Annaqulova'],
            ['368231100472', 'musobaqa_tadbir', '16.02.2026', '21.02.2026', '0248/2026', 'Musobaqalar va tadbirlarda qatnashish', 'N.Annaqulova'],
            ['368211100635', 'musobaqa_tadbir', '16.02.2026', '21.02.2026', '0248/2026', 'Musobaqalar va tadbirlarda qatnashish', 'N.Annaqulova'],
            ['368241100220', 'kasallik', '14.02.2026', '14.02.2026', '11SuV 001288326', 'Kasallik (vaqtincha mehnatga layoqatsizlik)', 'N.Annaqulova'],
            ['368211100468', 'musobaqa_tadbir', '16.02.2026', '21.02.2026', '0248/2026', 'Musobaqalar va tadbirlarda qatnashish', 'N.Annaqulova'],
            ['368211100209', 'musobaqa_tadbir', '16.02.2026', '21.02.2026', '0248/2026', 'Musobaqalar va tadbirlarda qatnashish', 'N.Annaqulova'],
            ['368221100047', 'musobaqa_tadbir', '16.02.2026', '21.02.2026', '0248/2026', 'Musobaqalar va tadbirlarda qatnashish', 'N.Annaqulova'],
            ['368221100199', 'musobaqa_tadbir', '16.02.2026', '21.02.2026', '0248/2026', 'Musobaqalar va tadbirlarda qatnashish', 'N.Annaqulova'],
            ['368211100449', 'musobaqa_tadbir', '16.02.2026', '21.02.2026', '0248/2026', 'Musobaqalar va tadbirlarda qatnashish', 'N.Annaqulova'],
            ['368211100102', 'homiladorlik', '16.02.2026', '20.02.2026', '11SuV 001648526', 'Homiladorlik va tug\'ruq', 'N.Annaqulova'],
            ['368251100868', 'kasallik', '19.02.2026', '20.02.2026', '06QV 000046326', 'Kasallik (vaqtincha mehnatga layoqatsizlik)', 'M.Berdiyarova'],
            ['368241100811', 'nikoh_toyi', '09.02.2026', '19.02.2026', '№ 07168768', 'Talabaning nikoh to\'yi', 'A.G\'oziyeva'],
            ['368231100382', 'kasallik', '09.02.2026', '14.02.2026', '11SuV 001284926', 'Kasallik (vaqtincha mehnatga layoqatsizlik)', 'N.Annaqulova'],
            ['368221100441', 'kasallik', '09.02.2026', '19.02.2026', '11SuV 001534626', 'Kasallik (vaqtincha mehnatga layoqatsizlik)', 'N.Annaqulova'],
            ['368251101385', 'kasallik', '11.02.2026', '11.02.2026', '11SuV 001135926', 'Kasallik (vaqtincha mehnatga layoqatsizlik)', 'M.Berdiyarova'],
            ['368211100221', 'kasallik', '09.02.2026', '10.02.2026', '08NmV 000268726', 'Kasallik (vaqtincha mehnatga layoqatsizlik)', 'D.Botirov'],
            ['368221100088', 'kasallik', '14.02.2026', '17.02.2026', '11SuV 001507926', 'Kasallik (vaqtincha mehnatga layoqatsizlik)', 'A.G\'oziyeva'],
            ['368211100585', 'kasallik', '12.02.2026', '14.02.2026', '01TSH 000496726', 'Kasallik (vaqtincha mehnatga layoqatsizlik)', 'D.Botirov'],
            ['368211100332', 'kasallik', '11.02.2026', '14.02.2026', '11SuV 001112626', 'Kasallik (vaqtincha mehnatga layoqatsizlik)', 'D.Botirov'],
            ['368211100676', 'kasallik', '09.02.2026', '10.02.2026', '11SuV 001068523', 'Kasallik (vaqtincha mehnatga layoqatsizlik)', 'D.Botirov'],
            ['368211100243', 'kasallik', '12.02.2026', '14.02.2026', '06QV 000020626', 'Kasallik (vaqtincha mehnatga layoqatsizlik)', 'D.Botirov'],
            ['368211100200', 'kasallik', '06.02.2026', '10.02.2026', '11SuV 001333926', 'Kasallik (vaqtincha mehnatga layoqatsizlik)', 'D.Botirov'],
            ['368211100595', 'homiladorlik', '06.02.2026', '10.02.2026', '11SuV 000866226', 'Homiladorlik va tug\'ruq', 'M.Berdiyarova'],
            ['368251100187', 'kasallik', '09.02.2026', '11.02.2026', '11SuV 000949626', 'Kasallik (vaqtincha mehnatga layoqatsizlik)', 'N.Annaqulova'],
            ['368211100651', 'kasallik', '03.02.2026', '11.02.2026', '11SuV 001192726', 'Kasallik (vaqtincha mehnatga layoqatsizlik)', 'D.Botirov'],
            ['368251101241', 'kasallik', '31.01.2026', '06.02.2026', '01TSH 002443126', 'Kasallik (vaqtincha mehnatga layoqatsizlik)', 'D.Botirov'],
            ['368251101107', 'kasallik', '26.01.2026', '09.02.2026', '02QR 000160526', 'Kasallik (vaqtincha mehnatga layoqatsizlik)', 'M.Berdiyarova'],
            ['368221100675', 'kasallik', '06.02.2026', '09.02.2026', '11SuV 000872726', 'Kasallik (vaqtincha mehnatga layoqatsizlik)', 'M.Berdiyarova'],
            ['368211100103', 'homiladorlik', '28.01.2026', '04.02.2026', 'T 50649671', 'Homiladorlik va tug\'ruq', 'D.Botirov'],
            ['368231100063', 'kasallik', '26.01.2026', '05.02.2026', '11SuV 000860626', 'Kasallik (vaqtincha mehnatga layoqatsizlik)', 'A.G\'oziyeva'],
            ['368211100045', 'kasallik', '28.01.2026', '31.01.2026', '11SuV 000878026', 'Kasallik (vaqtincha mehnatga layoqatsizlik)', 'M.Berdiyarova'],
            ['368211100595', 'homiladorlik', '30.01.2026', '06.02.2026', '11SuV 000882626', 'Homiladorlik va tug\'ruq', 'M.Berdiyarova'],
            ['368231100497', 'kasallik', '02.02.2026', '07.02.2026', '11SuV 000973726', 'Kasallik (vaqtincha mehnatga layoqatsizlik)', 'A.G\'oziyeva'],
            ['368251100868', 'kasallik', '02.02.2026', '04.02.2026', '06QV 000081826', 'Kasallik (vaqtincha mehnatga layoqatsizlik)', 'M.Berdiyarova'],
            ['368231100693', 'yaqin_qarindosh', '02.02.2026', '06.02.2026', 'V 50717117', 'Yaqin qarindoshning to\'yi yoki vafoti', 'A.G\'oziyeva'],
            ['368231100222', 'nikoh_toyi', '16.02.2026', '18.02.2026', '7522575', 'Talabaning nikoh to\'yi', 'M.Samadov'],
            ['368211100489', 'kasallik', '25.02.2026', '27.02.2026', '11SuV 001860126', 'Kasallik (vaqtincha mehnatga layoqatsizlik)', 'M.Samadov'],
            ['368211100489', 'kasallik', '19.02.2026', '21.02.2026', '11SuV 001571126', 'Kasallik (vaqtincha mehnatga layoqatsizlik)', 'M.Samadov'],
        ];

        $imported = 0;
        $skipped = 0;
        $errors = [];

        foreach ($records as $i => $r) {
            $hemisId = $r[0];
            $reason = $r[1];
            $startDate = Carbon::createFromFormat('d.m.Y', $r[2])->startOfDay();
            $endDate = Carbon::createFromFormat('d.m.Y', $r[3])->startOfDay();
            $docNumber = $r[4];
            $description = $r[5];
            $reviewerName = $r[6];

            // student_id_number bo'yicha qidirish (368... raqamlar)
            $student = Student::where('student_id_number', $hemisId)->first();
            if (!$student) {
                $student = Student::where('hemis_id', $hemisId)->first();
            }
            if (!$student) {
                $errors[] = "Qator " . ($i + 1) . ": Talaba topilmadi student_id_number/hemis_id {$hemisId}";
                continue;
            }
            $actualHemisId = $student->hemis_id;

            // Dublikat tekshiruvi
            $exists = AbsenceExcuse::where('student_hemis_id', $actualHemisId)
                ->where('reason', $reason)
                ->where('start_date', $startDate)
                ->where('end_date', $endDate)
                ->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            AbsenceExcuse::create([
                'student_id' => $student->id,
                'student_hemis_id' => $actualHemisId,
                'student_full_name' => $student->full_name ?? $student->short_name,
                'group_name' => $student->group_name,
                'department_name' => $student->department_name,
                'reason' => $reason,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'doc_number' => $docNumber,
                'description' => $description,
                'status' => 'approved',
                'reviewed_by_name' => $reviewerName,
                'reviewed_at' => now(),
                'verification_token' => Str::uuid()->toString(),
            ]);
            $imported++;
        }

        $this->info("Imported: {$imported}");
        if ($skipped > 0) {
            $this->warn("Skipped (dublikat): {$skipped}");
        }
        if (count($errors) > 0) {
            foreach ($errors as $err) {
                $this->error($err);
            }
        }

        return 0;
    }
}
