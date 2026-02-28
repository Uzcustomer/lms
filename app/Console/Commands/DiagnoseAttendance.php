<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class DiagnoseAttendance extends Command
{
    protected $signature = 'hemis:diagnose-attendance
        {--date= : Tekshirish sanasi (Y-m-d, default: bugun)}
        {--group= : Guruh nomi (masalan: d2/22-04a)}
        {--employee= : O\'qituvchi nomi qismi (masalan: ABULFAYZOV)}
        {--all : Barcha guruhlarni tekshirish}';

    protected $description = 'HEMIS API va lokal bazadagi davomat ma\'lumotlarini solishtirish diagnostikasi';

    public function handle()
    {
        $date = $this->option('date')
            ? Carbon::parse($this->option('date'))
            : Carbon::today();
        $dateStr = $date->toDateString();
        $groupFilter = $this->option('group');
        $employeeFilter = $this->option('employee');

        $this->newLine();
        $this->info("╔══════════════════════════════════════════════════════════════╗");
        $this->info("║   DAVOMAT DIAGNOSTIKASI: {$dateStr}                         ║");
        $this->info("╚══════════════════════════════════════════════════════════════╝");
        $this->newLine();

        // ═══════════════════════════════════════════════════════════════
        // 1-QADAM: Lokal bazadagi schedulelarni topish
        // ═══════════════════════════════════════════════════════════════
        $this->info("┌─ 1-QADAM: LOKAL BAZADAGI JADVALLAR (schedules) ─────────────┐");

        $scheduleQuery = DB::table('schedules')
            ->whereRaw('DATE(lesson_date) = ?', [$dateStr])
            ->whereNull('deleted_at')
            ->whereNotIn('training_type_code', config('app.attendance_excluded_training_types', [99, 100, 101, 102]));

        if ($groupFilter) {
            $scheduleQuery->where('group_name', 'LIKE', "%{$groupFilter}%");
        }
        if ($employeeFilter) {
            $scheduleQuery->where('employee_name', 'LIKE', "%{$employeeFilter}%");
        }

        $schedules = $scheduleQuery->select(
            'schedule_hemis_id', 'employee_id', 'employee_name',
            'subject_id', 'subject_name', 'group_id', 'group_name',
            'training_type_code', 'training_type_name',
            'lesson_pair_code', 'lesson_pair_start_time', 'lesson_pair_end_time',
            'lesson_date', 'deleted_at'
        )->get();

        if ($schedules->isEmpty()) {
            $this->warn("│  Hech qanday jadval topilmadi!");
            $this->warn("│  Filtrlar: sana={$dateStr}, guruh={$groupFilter}, xodim={$employeeFilter}");
            $this->info("└─────────────────────────────────────────────────────────────┘");
            return;
        }

        $this->info("│  Topilgan jadvallar: {$schedules->count()} ta");
        foreach ($schedules as $sch) {
            $pairTime = substr($sch->lesson_pair_start_time, 0, 5) . '-' . substr($sch->lesson_pair_end_time, 0, 5);
            $this->info("│");
            $this->info("│  schedule_hemis_id: {$sch->schedule_hemis_id}");
            $this->info("│  O'qituvchi: {$sch->employee_name} (ID: {$sch->employee_id})");
            $this->info("│  Fan:        {$sch->subject_name} (ID: {$sch->subject_id})");
            $this->info("│  Guruh:      {$sch->group_name} (ID: {$sch->group_id})");
            $this->info("│  Tur:        {$sch->training_type_name} (code: {$sch->training_type_code})");
            $this->info("│  Juftlik:    {$pairTime} (code: {$sch->lesson_pair_code})");
            $this->info("│  Sana:       {$sch->lesson_date}");
        }
        $this->info("└─────────────────────────────────────────────────────────────┘");
        $this->newLine();

        // ═══════════════════════════════════════════════════════════════
        // 2-QADAM: Lokal bazadagi attendance_controls
        // ═══════════════════════════════════════════════════════════════
        $this->info("┌─ 2-QADAM: LOKAL BAZADAGI DAVOMAT NAZORATI (attendance_controls) ──┐");

        $scheduleHemisIds = $schedules->pluck('schedule_hemis_id')->toArray();

        // 2a: subject_schedule_id orqali (1-usul)
        $acByScheduleId = DB::table('attendance_controls')
            ->whereIn('subject_schedule_id', $scheduleHemisIds)
            ->select('*')
            ->get();

        $this->info("│");
        $this->info("│  1-USUL (subject_schedule_id orqali):");
        $this->info("│  Izlanmoqda: subject_schedule_id IN (" . implode(', ', $scheduleHemisIds) . ")");
        $this->info("│  Topildi: {$acByScheduleId->count()} ta (soft-delete ham kiradi)");

        foreach ($acByScheduleId as $ac) {
            $status = [];
            if ($ac->deleted_at) $status[] = "SOFT-DELETED!";
            if ($ac->load <= 0) $status[] = "LOAD=0!";
            if ($ac->load > 0 && !$ac->deleted_at) $status[] = "OK";
            $statusStr = implode(', ', $status);

            $this->info("│    hemis_id: {$ac->hemis_id}");
            $this->info("│    subject_schedule_id: {$ac->subject_schedule_id}");
            $this->info("│    load: {$ac->load} | is_final: " . ($ac->is_final ? 'true' : 'false'));
            $this->info("│    deleted_at: " . ($ac->deleted_at ?? 'NULL'));
            $this->info("│    → Holat: {$statusStr}");
            $this->info("│");
        }

        // Agar 1-usulda topilmasa, deleted olanlarini ham ko'rsat
        $acByScheduleIdActive = $acByScheduleId->filter(fn($ac) => !$ac->deleted_at && $ac->load > 0);
        if ($acByScheduleIdActive->isEmpty()) {
            $this->warn("│  ⚠ 1-USUL: FAOL YOZUV TOPILMADI (load > 0 va deleted_at IS NULL)");
        } else {
            $this->info("│  ✓ 1-USUL: {$acByScheduleIdActive->count()} ta faol yozuv topildi");
        }

        $this->info("│");

        // 2b: Guruh va sana bo'yicha barcha yozuvlar (2-usul va umumiy)
        $acByGroup = DB::table('attendance_controls')
            ->whereRaw('DATE(lesson_date) = ?', [$dateStr]);

        if ($groupFilter) {
            $acByGroup->where('group_name', 'LIKE', "%{$groupFilter}%");
        }
        if ($employeeFilter) {
            $acByGroup->where('employee_name', 'LIKE', "%{$employeeFilter}%");
        }

        $acByGroupResults = $acByGroup->select('*')->get();

        $this->info("│  BARCHA davomat yozuvlari (guruh/sana/xodim bo'yicha):");
        $this->info("│  Topildi: {$acByGroupResults->count()} ta");

        foreach ($acByGroupResults as $ac) {
            $matchSchedule = in_array($ac->subject_schedule_id, $scheduleHemisIds) ? 'MATCH' : 'NO MATCH';
            $status = [];
            if ($ac->deleted_at) $status[] = "SOFT-DELETED";
            if ($ac->load <= 0) $status[] = "LOAD=0";
            if ($ac->load > 0 && !$ac->deleted_at) $status[] = "FAOL";

            $this->info("│    ───────────────────────────────────────");
            $this->info("│    hemis_id:             {$ac->hemis_id}");
            $this->info("│    subject_schedule_id:  {$ac->subject_schedule_id} ({$matchSchedule})");
            $this->info("│    employee_id:          {$ac->employee_id} ({$ac->employee_name})");
            $this->info("│    subject_id:           {$ac->subject_id} ({$ac->subject_name})");
            $this->info("│    group_id:             {$ac->group_id} ({$ac->group_name})");
            $this->info("│    training_type_code:   {$ac->training_type_code} ({$ac->training_type_name})");
            $this->info("│    lesson_pair_code:     {$ac->lesson_pair_code}");
            $this->info("│    lesson_date:          {$ac->lesson_date}");
            $this->info("│    load:                 {$ac->load}");
            $this->info("│    is_final:             " . ($ac->is_final ? 'true' : 'false'));
            $this->info("│    deleted_at:           " . ($ac->deleted_at ?? 'NULL'));
            $this->info("│    created_at:           {$ac->created_at}");
            $this->info("│    updated_at:           {$ac->updated_at}");
            $this->info("│    → " . implode(', ', $status));
        }

        // 2-usul composite key tekshirish
        $this->info("│");
        $this->info("│  2-USUL (composite key) tekshirish:");
        foreach ($schedules as $sch) {
            $compositeKey = "{$sch->employee_id}|{$sch->group_id}|{$sch->subject_id}|{$dateStr}|{$sch->training_type_code}|{$sch->lesson_pair_code}";
            $found = $acByGroupResults->first(function ($ac) use ($sch, $dateStr) {
                return !$ac->deleted_at
                    && $ac->load > 0
                    && $ac->employee_id == $sch->employee_id
                    && $ac->group_id == $sch->group_id
                    && $ac->subject_id == $sch->subject_id
                    && substr($ac->lesson_date, 0, 10) === $dateStr
                    && $ac->training_type_code == $sch->training_type_code
                    && $ac->lesson_pair_code == $sch->lesson_pair_code;
            });

            $foundStatus = $found ? '✓ TOPILDI' : '✗ TOPILMADI';
            $this->info("│    Key: {$compositeKey}");
            $this->info("│    → {$foundStatus}");

            // Qaysi maydon mos kelmayapti?
            if (!$found && $acByGroupResults->isNotEmpty()) {
                $this->warn("│    Qaysi maydonlar farq qiladi:");
                foreach ($acByGroupResults as $ac) {
                    if ($ac->deleted_at) continue;
                    $diffs = [];
                    if ($ac->employee_id != $sch->employee_id) $diffs[] = "employee_id ({$ac->employee_id} vs {$sch->employee_id})";
                    if ($ac->group_id != $sch->group_id) $diffs[] = "group_id ({$ac->group_id} vs {$sch->group_id})";
                    if ($ac->subject_id != $sch->subject_id) $diffs[] = "subject_id ({$ac->subject_id} vs {$sch->subject_id})";
                    if (substr($ac->lesson_date, 0, 10) !== $dateStr) $diffs[] = "lesson_date (" . substr($ac->lesson_date, 0, 10) . " vs {$dateStr})";
                    if ($ac->training_type_code != $sch->training_type_code) $diffs[] = "training_type_code ({$ac->training_type_code} vs {$sch->training_type_code})";
                    if ($ac->lesson_pair_code != $sch->lesson_pair_code) $diffs[] = "lesson_pair_code ({$ac->lesson_pair_code} vs {$sch->lesson_pair_code})";
                    if ($ac->load <= 0) $diffs[] = "load={$ac->load} (0 yoki kam)";

                    if (!empty($diffs)) {
                        $this->warn("│      AC hemis_id={$ac->hemis_id}: " . implode(', ', $diffs));
                    } else {
                        $this->info("│      AC hemis_id={$ac->hemis_id}: barcha maydonlar mos, lekin load={$ac->load}");
                    }
                }
            }
        }

        $this->info("└─────────────────────────────────────────────────────────────┘");
        $this->newLine();

        // ═══════════════════════════════════════════════════════════════
        // 3-QADAM: HEMIS API dan attendance-control-list so'rash
        // ═══════════════════════════════════════════════════════════════
        $this->info("┌─ 3-QADAM: HEMIS API DAN DAVOMAT NAZORATINI SO'RASH ────────┐");

        $token = config('services.hemis.token');
        $from = $date->copy()->startOfDay()->timestamp;
        $to = $date->copy()->endOfDay()->timestamp;

        $this->info("│  lesson_date_from: {$from} (" . date('Y-m-d H:i:s', $from) . ")");
        $this->info("│  lesson_date_to:   {$to} (" . date('Y-m-d H:i:s', $to) . ")");
        $this->info("│");

        $apiItems = $this->fetchAllFromHemis($token, $from, $to);

        if ($apiItems === false) {
            $this->error("│  HEMIS API xato qaytardi!");
            $this->info("└─────────────────────────────────────────────────────────────┘");
            return;
        }

        $this->info("│  HEMIS API dan jami: " . count($apiItems) . " ta yozuv");

        // Guruh bo'yicha filtrlaymiz
        $filteredItems = collect($apiItems)->filter(function ($item) use ($groupFilter, $employeeFilter) {
            $match = true;
            if ($groupFilter) {
                $groupName = $item['group']['name'] ?? '';
                $match = $match && str_contains($groupName, $groupFilter);
            }
            if ($employeeFilter) {
                $empName = $item['employee']['name'] ?? '';
                $match = $match && str_contains(mb_strtoupper($empName), mb_strtoupper($employeeFilter));
            }
            return $match;
        });

        $this->info("│  Filtrdan o'tganlar: {$filteredItems->count()} ta");
        $this->info("│");

        foreach ($filteredItems as $item) {
            $lessonTs = $item['lesson_date'] ?? null;
            $lessonDate = $lessonTs ? date('Y-m-d H:i:s', $lessonTs) : 'null';
            $subjectScheduleId = $item['_subject_schedule'] ?? 'NULL';
            $matchLocal = in_array($subjectScheduleId, $scheduleHemisIds) ? 'SCHEDULE BILAN MOS' : 'SCHEDULE DA YO\'Q!';

            $this->info("│  ───────────────────────────────────────");
            $this->info("│  HEMIS id:             {$item['id']}");
            $this->info("│  _subject_schedule:    {$subjectScheduleId} ({$matchLocal})");
            $this->info("│  employee:             " . ($item['employee']['id'] ?? '?') . " (" . ($item['employee']['name'] ?? '?') . ")");
            $this->info("│  subject:              " . ($item['subject']['id'] ?? '?') . " (" . ($item['subject']['name'] ?? '?') . ")");
            $this->info("│  group:                " . ($item['group']['id'] ?? '?') . " (" . ($item['group']['name'] ?? '?') . ")");
            $this->info("│  trainingType:         " . ($item['trainingType']['code'] ?? '?') . " (" . ($item['trainingType']['name'] ?? '?') . ")");
            $this->info("│  lessonPair:           " . ($item['lessonPair']['code'] ?? '?') . " (" . ($item['lessonPair']['start_time'] ?? '?') . " - " . ($item['lessonPair']['end_time'] ?? '?') . ")");
            $this->info("│  lesson_date:          {$lessonDate} (ts: " . ($lessonTs ?? 'null') . ")");
            $this->info("│  load:                 " . ($item['load'] ?? '?'));

            // Lokal bazada bor-yo'qligini tekshirish
            $localAc = DB::table('attendance_controls')
                ->where('hemis_id', $item['id'])
                ->first();

            if ($localAc) {
                $localStatus = $localAc->deleted_at ? 'SOFT-DELETED!' : ($localAc->load > 0 ? 'FAOL' : 'LOAD=0');
                $this->info("│  LOKAL BAZA:           BOR (id={$localAc->id}, load={$localAc->load}, {$localStatus})");
                $this->info("│    subject_schedule_id: {$localAc->subject_schedule_id}");
                $this->info("│    deleted_at:          " . ($localAc->deleted_at ?? 'NULL'));
            } else {
                $this->warn("│  LOKAL BAZA:           YO'Q — IMPORT QILINMAGAN!");
            }
        }

        if ($filteredItems->isEmpty()) {
            $this->warn("│  HEMIS API da ham bu guruh/xodim uchun yozuv topilmadi!");
            $this->warn("│  Ehtimol HEMIS da ham hali davomat belgilanmagan.");
        }

        $this->info("└─────────────────────────────────────────────────────────────┘");
        $this->newLine();

        // ═══════════════════════════════════════════════════════════════
        // 4-QADAM: XULOSA — nima uchun Yo'q ko'rsatayotganini aniqlash
        // ═══════════════════════════════════════════════════════════════
        $this->info("╔══════════════════════════════════════════════════════════════╗");
        $this->info("║   XULOSA VA SABAB TAHLILI                                   ║");
        $this->info("╚══════════════════════════════════════════════════════════════╝");
        $this->newLine();

        $issues = [];

        // Tekshirish 1: attendance_controls da umuman yozuv yo'qmi?
        if ($acByGroupResults->isEmpty()) {
            $issues[] = [
                'KRITIK',
                'attendance_controls jadvalida bu guruh/sana uchun UMUMAN yozuv yo\'q',
                'import:attendance-controls --date=' . $dateStr . ' buyrug\'ini ishga tushiring'
            ];
        }

        // Tekshirish 2: Yozuv bor lekin soft-deleted?
        $softDeleted = $acByGroupResults->filter(fn($ac) => $ac->deleted_at !== null);
        if ($softDeleted->isNotEmpty()) {
            $issues[] = [
                'MUHIM',
                $softDeleted->count() . ' ta yozuv soft-delete qilingan (deleted_at bor)',
                'FINAL import yozuvlarni o\'chirib, qaytadan yozmagan bo\'lishi mumkin'
            ];
        }

        // Tekshirish 3: load = 0?
        $zeroLoad = $acByGroupResults->filter(fn($ac) => !$ac->deleted_at && $ac->load <= 0);
        if ($zeroLoad->isNotEmpty()) {
            $issues[] = [
                'MUHIM',
                $zeroLoad->count() . ' ta yozuvda load = 0 (davomat belgilanmagan)',
                'HEMIS da davomat nazorati to\'ldirilmagan yoki noto\'g\'ri import qilingan'
            ];
        }

        // Tekshirish 4: subject_schedule_id mos kelmayaptimi?
        $mismatchedIds = $acByGroupResults->filter(function ($ac) use ($scheduleHemisIds) {
            return !$ac->deleted_at && $ac->load > 0 && !in_array($ac->subject_schedule_id, $scheduleHemisIds);
        });
        if ($mismatchedIds->isNotEmpty()) {
            $issues[] = [
                'MUHIM',
                'subject_schedule_id schedule_hemis_id ga mos kelmayapti: ' .
                $mismatchedIds->pluck('subject_schedule_id')->implode(', ') .
                ' vs [' . implode(', ', $scheduleHemisIds) . ']',
                'HEMIS da jadval ID o\'zgargan yoki boshqa jadval bilan bog\'langan'
            ];
        }

        // Tekshirish 5: HEMIS API da bor lekin lokal bazada yo'q?
        $hemisButNotLocal = $filteredItems->filter(function ($item) {
            $local = DB::table('attendance_controls')
                ->where('hemis_id', $item['id'])
                ->whereNull('deleted_at')
                ->first();
            return !$local;
        });
        if ($hemisButNotLocal->isNotEmpty()) {
            $issues[] = [
                'KRITIK',
                'HEMIS API da ' . $hemisButNotLocal->count() . ' ta yozuv bor, lekin lokal bazada yo\'q yoki o\'chirilgan',
                'import:attendance-controls --date=' . $dateStr . ' buyrug\'ini ishga tushiring'
            ];
        }

        // Tekshirish 6: ESKI/STALE schedulelar (HEMIS API da boshqa schedule_id bor)
        if ($filteredItems->isNotEmpty()) {
            $hemisScheduleIds = $filteredItems
                ->filter(fn($item) => isset($item['_subject_schedule']) && isset($item['lesson_date'])
                    && date('Y-m-d', $item['lesson_date']) === $dateStr)
                ->pluck('_subject_schedule')
                ->unique()
                ->values()
                ->toArray();

            $staleSchedules = $schedules->filter(function ($sch) use ($hemisScheduleIds, $attendanceByScheduleId, $attendanceByKey) {
                // HEMIS da bu schedule_hemis_id yo'q
                if (in_array($sch->schedule_hemis_id, $hemisScheduleIds)) {
                    return false;
                }
                // Va bu schedule da davomat ham yo'q
                $attKey = $sch->employee_id . '|' . $sch->group_id . '|' . $sch->subject_id
                    . '|' . date('Y-m-d', strtotime($sch->lesson_date))
                    . '|' . $sch->training_type_code . '|' . $sch->lesson_pair_code;
                return !isset($attendanceByScheduleId[$sch->schedule_hemis_id])
                    && !isset($attendanceByKey[$attKey]);
            });

            if ($staleSchedules->isNotEmpty()) {
                $staleIds = $staleSchedules->pluck('schedule_hemis_id')->implode(', ');
                $stalePairs = $staleSchedules->map(fn($s) => $s->lesson_pair_code . ' (' .
                    substr($s->lesson_pair_start_time, 0, 5) . '-' . substr($s->lesson_pair_end_time, 0, 5) . ')'
                )->implode(', ');
                $issues[] = [
                    'KRITIK',
                    "ESKI JADVALLAR: {$staleSchedules->count()} ta schedule HEMIS da yo'q, lekin lokal bazada hali mavjud.\n" .
                    "     schedule_hemis_id: [{$staleIds}]\n" .
                    "     Juftliklar: {$stalePairs}\n" .
                    "     HEMIS faqat bu IDlarni qaytarmoqda: [" . implode(', ', $hemisScheduleIds) . "]",
                    "Jadval importini qayta ishga tushiring:\n" .
                    "     php artisan schedule:import --from={$dateStr} --to={$dateStr}\n" .
                    "     yoki hisobot sahifasida \"Yangilash\" tugmasini bosing"
                ];
            }
        }

        // Tekshirish 7: Dublikat schedulelar (bir xil kalit, turli schedule_hemis_id)
        $keyGroups = [];
        foreach ($schedules as $sch) {
            $key = $sch->employee_id . '|' . $sch->group_id . '|' . $sch->subject_id
                 . '|' . $sch->training_type_code . '|' . $sch->lesson_pair_code;
            $keyGroups[$key][] = $sch->schedule_hemis_id;
        }
        $duplicateKeys = array_filter($keyGroups, fn($ids) => count($ids) > 1);
        if (!empty($duplicateKeys)) {
            foreach ($duplicateKeys as $key => $ids) {
                $issues[] = [
                    'OGOHLANTIRISH',
                    "Dublikat jadval: kalit [{$key}] uchun " . count($ids) . " ta schedule bor: [" . implode(', ', $ids) . "]",
                    "Jadval importini qayta ishga tushiring — HEMIS jadval o'zgargan bo'lishi mumkin"
                ];
            }
        }

        if (empty($issues)) {
            $this->info("✓ Hech qanday muammo topilmadi — davomat to'g'ri ko'rsatilishi kerak.");
            $this->info("  Agar hali ham Yo'q ko'rsatsa, sahifani yangilang yoki sync tugmasini bosing.");
        } else {
            foreach ($issues as $i => $issue) {
                $num = $i + 1;
                $this->error("  {$num}. [{$issue[0]}] {$issue[1]}");
                $this->warn("     → Yechim: {$issue[2]}");
                $this->newLine();
            }
        }

        return 0;
    }

    private function fetchAllFromHemis(string $token, int $from, int $to): array|false
    {
        $allItems = [];
        $page = 1;
        $totalPages = 1;

        do {
            $url = "https://student.ttatf.uz/rest/v1/data/attendance-control-list?limit=200&page={$page}&lesson_date_from={$from}&lesson_date_to={$to}";

            try {
                $response = Http::withoutVerifying()
                    ->withToken($token)
                    ->timeout(60)
                    ->get($url);

                if (!$response->successful()) {
                    $this->error("API HTTP xato: {$response->status()}");
                    return false;
                }

                $json = $response->json();
                if (!($json['success'] ?? false)) {
                    $this->error("API success=false qaytardi");
                    return false;
                }

                $items = $json['data']['items'] ?? [];
                $totalPages = $json['data']['pagination']['pageCount'] ?? 1;
                $allItems = array_merge($allItems, $items);
                $this->info("│  API sahifa {$page}/{$totalPages}: " . count($items) . " ta yozuv");

                if ($page < $totalPages) {
                    sleep(1);
                }
            } catch (\Exception $e) {
                $this->error("API xato: " . $e->getMessage());
                return false;
            }

            $page++;
        } while ($page <= $totalPages);

        return $allItems;
    }
}
