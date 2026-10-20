<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 14.10.2026 dagi GradeScheduleAlign xatosini tuzatish: HEMIS bir kunning ikki
 * juftligidagi baholarni bitta jadval yozuviga bog'lagan joylarda baholar bitta
 * juftlikka yig'ilib, ikkinchi juftlik jurnalda bo'sh qolgan.
 *
 * HEMIS'dan shu kunning baholari o'qiladi va LMS'dagi yozuv (hemis_id bo'yicha)
 * HEMIS juftligiga qaytariladi — faqat yig'ilgan katakdagi (talabada shu fan,
 * sana, juftlikda boshqa faol baho ham bor) yozuvlar. Hech narsa o'chirilmaydi
 * yoki qayta yozilmaydi; boshqa guruh va kataklarga tegilmaydi.
 */
class RepairCollapsedGradePairs extends Command
{
    protected $signature = 'grades:repair-collapsed
        {--date= : Sana (Y-m-d), majburiy}
        {--group= : Guruh nomi (masalan d2/d22-08c); berilmasa — shu kunning barcha guruhlari}
        {--dry-run : Hech narsani yozmasdan faqat ko\'rsatish}';

    protected $description = "Bitta juftlikka yig'ilib qolgan HEMIS baholarini HEMIS'dagi o'z juftligiga qaytaradi";

    public function handle(): int
    {
        $dateStr = (string) $this->option('date');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr)) {
            $this->error('--date=Y-m-d kerak');

            return self::FAILURE;
        }
        $date = Carbon::parse($dateStr);
        $group = trim((string) $this->option('group'));
        $dry = (bool) $this->option('dry-run');

        // Guruh talabalari (berilgan bo'lsa)
        $studentFilter = null;
        if ($group !== '') {
            $studentFilter = DB::table('students')->where('group_name', $group)->pluck('full_name', 'hemis_id')->all();
            if (!$studentFilter) {
                $this->error("Guruh topilmadi yoki talabasi yo'q: {$group}");

                return self::FAILURE;
            }
            $this->info("Guruh {$group}: " . count($studentFilter) . ' talaba');
        }

        $items = $this->fetchDay($date);
        if ($items === null) {
            return self::FAILURE;
        }
        if ($studentFilter !== null) {
            $items = array_values(array_filter($items, fn ($i) => isset($studentFilter[(string) ($i['_student'] ?? '')])));
        }
        $this->info("HEMIS: shu kun uchun " . count($items) . ' ta baho' . ($group !== '' ? " ({$group})" : ''));

        $rows = [];
        foreach ($items as $item) {
            $hemisPair = (string) ($item['lessonPair']['code'] ?? '');
            if ($hemisPair === '') {
                continue;
            }

            $grade = DB::table('student_grades')
                ->where('hemis_id', $item['id'])
                ->whereNull('deleted_at')
                ->first(['id', 'student_hemis_id', 'subject_id', 'subject_name', 'lesson_date', 'lesson_pair_code', 'grade', 'employee_name']);
            if (!$grade || (string) $grade->lesson_pair_code === $hemisPair) {
                continue;
            }

            // Faqat yig'ilgan katak: shu joyda talabaning shu fandan boshqa faol bahosi ham bor
            $collapsed = DB::table('student_grades')
                ->where('student_hemis_id', $grade->student_hemis_id)
                ->where('subject_id', $grade->subject_id)
                ->whereDate('lesson_date', substr((string) $grade->lesson_date, 0, 10))
                ->where('lesson_pair_code', $grade->lesson_pair_code)
                ->whereNull('deleted_at')
                ->where('id', '<>', $grade->id)
                ->exists();
            if (!$collapsed) {
                continue;
            }

            // HEMIS juftligidagi katak bo'sh bo'lishi kerak — aks holda yana ustma-ust tushadi
            $targetBusy = DB::table('student_grades')
                ->where('student_hemis_id', $grade->student_hemis_id)
                ->where('subject_id', $grade->subject_id)
                ->whereDate('lesson_date', substr((string) $grade->lesson_date, 0, 10))
                ->where('lesson_pair_code', $hemisPair)
                ->whereNull('deleted_at')
                ->exists();

            $rows[] = [
                'id' => $grade->id,
                'name' => $studentFilter[(string) $grade->student_hemis_id] ?? $grade->student_hemis_id,
                'subject' => mb_substr((string) $grade->subject_name, 0, 30),
                'teacher' => $grade->employee_name,
                'grade' => $grade->grade,
                'from' => $grade->lesson_pair_code,
                'to' => $hemisPair,
                'to_name' => (string) ($item['lessonPair']['name'] ?? ''),
                'busy' => $targetBusy,
            ];
        }

        if (!$rows) {
            $this->info("Tuzatiladigan yig'ilgan baho yo'q.");

            return self::SUCCESS;
        }

        $this->table(
            ['Talaba', 'Fan', "O'qituvchi", 'Baho', 'Hozir', 'HEMIS', 'Holat'],
            array_map(fn ($r) => [$r['name'], $r['subject'], $r['teacher'], $r['grade'], $r['from'], $r['to'], $r['busy'] ? "o'tkazilmaydi (band)" : 'qaytariladi'], $rows)
        );

        $todo = array_values(array_filter($rows, fn ($r) => !$r['busy']));
        if ($dry) {
            $this->warn('Dry-run: ' . count($todo) . " ta baho qaytariladi (hech narsa yozilmadi).");

            return self::SUCCESS;
        }

        foreach ($todo as $r) {
            DB::table('student_grades')->where('id', $r['id'])->update([
                'lesson_pair_code' => $r['to'],
                'lesson_pair_name' => $r['to_name'] !== '' ? $r['to_name'] : DB::raw('lesson_pair_name'),
            ]);
        }

        Log::info('[grades:repair-collapsed] juftliklar qaytarildi', ['date' => $dateStr, 'group' => $group ?: null, 'count' => count($todo)]);
        $this->info(count($todo) . " ta baho HEMIS juftligiga qaytarildi.");

        return self::SUCCESS;
    }

    /** HEMIS student-grade-list: shu kun (UTC chegaralari, import bilan bir xil). */
    private function fetchDay(Carbon $date): ?array
    {
        $base = rtrim((string) config('services.hemis.base_url'), '/');
        $token = (string) config('services.hemis.token');
        $from = Carbon::parse($date->toDateString(), 'UTC')->startOfDay()->timestamp;
        $to = Carbon::parse($date->toDateString(), 'UTC')->endOfDay()->timestamp;

        $all = [];
        $page = 1;
        $pages = 1;
        do {
            $resp = null;
            for ($try = 1; $try <= 4; $try++) {
                try {
                    $resp = Http::connectTimeout(30)->timeout(60)->withoutVerifying()->withToken($token)
                        ->get("{$base}/v1/data/student-grade-list", [
                            'limit' => 200, 'page' => $page, 'lesson_date_from' => $from, 'lesson_date_to' => $to,
                        ]);
                    if ($resp->successful()) {
                        break;
                    }
                } catch (\Throwable $e) {
                    $resp = null;
                }
                sleep(2 * $try);
            }
            if (!$resp || !$resp->successful()) {
                $this->error("HEMIS javob bermadi (sahifa {$page}).");

                return null;
            }
            foreach ($resp->json('data.items') ?? [] as $item) {
                if (Carbon::createFromTimestamp($item['lesson_date'])->isSameDay($date)) {
                    $all[] = $item;
                }
            }
            $pages = (int) ($resp->json('data.pagination.pageCount') ?? 1);
            $this->line("HEMIS sahifa {$page}/{$pages}");
            $page++;
        } while ($page <= $pages);

        return $all;
    }
}
