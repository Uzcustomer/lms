<?php

namespace App\Console\Commands;

use App\Models\CurriculumSubjectTeacher;
use App\Models\Teacher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DebugTeacherJournal extends Command
{
    protected $signature = 'debug:teacher-journal
                            {search : O\'qituvchi nomi, teachers.id yoki hemis_id}
                            {--group-name=21-09 : Qidirilayotgan guruh nomi (masalan: 21-09)}';
    protected $description = 'O\'qituvchiga biriktirilgan guruhlarni tashxislash - jurnal ko\'rinmasligi sababini aniqlash';

    public function handle()
    {
        $search = $this->argument('search');
        $groupNameSearch = $this->option('group-name');

        // O'qituvchini topish: ism, id yoki hemis_id bo'yicha
        $employeeHemisId = $this->resolveTeacher($search);
        if (!$employeeHemisId) {
            return 1;
        }

        $this->newLine();
        $this->info("╔══════════════════════════════════════════════════════════════╗");
        $this->info("║  JOURNAL DEBUG: O'qituvchi guruh ko'rinmasligi tashxisi     ║");
        $this->info("╚══════════════════════════════════════════════════════════════╝");
        $this->newLine();

        // ═══════════════════════════════════════════════════
        // 1-MANBA: curriculum_subject_teachers
        // ═══════════════════════════════════════════════════
        $this->info("━━━ 1-MANBA: curriculum_subject_teachers jadvali ━━━");
        $records = CurriculumSubjectTeacher::where('employee_id', $employeeHemisId)->get();
        $this->line("  O'qituvchi HEMIS ID: {$employeeHemisId}");
        $this->line("  Jami yozuvlar soni:  {$records->count()}");

        if ($records->isEmpty()) {
            $this->error("  ❌ curriculum_subject_teachers da bu o'qituvchi uchun yozuv TOPILMADI!");
            $this->newLine();
        } else {
            // Jadval ko'rinishida chiqarish
            $this->newLine();
            $tableData = $records->map(fn($r) => [
                $r->id,
                $r->subject_name ? mb_substr($r->subject_name, 0, 30) : '-',
                is_null($r->group_id) ? '⚠ NULL' : $r->group_id,
                $r->curriculum_id ?? '-',
                $r->training_type_name ? mb_substr($r->training_type_name, 0, 15) : '-',
                $r->active ? 'Ha' : 'Yo\'q',
            ])->toArray();

            $this->table(
                ['ID', 'Fan nomi', 'group_id', 'curriculum_id', 'Tur', 'Faol'],
                $tableData
            );
        }

        // NULL group_id bo'lganlar
        $nullGroupRecords = $records->filter(fn($r) => is_null($r->group_id) || $r->group_id === '' || $r->group_id === 0);
        $this->newLine();
        if ($nullGroupRecords->isNotEmpty()) {
            $this->warn("  ⚠ NULL group_id bo'lgan yozuvlar: {$nullGroupRecords->count()} ta");
            $this->warn("  Bu yozuvlar ->filter() tufayli group_ids dan TUSHIB QOLADI!");
            $this->newLine();
            foreach ($nullGroupRecords as $r) {
                $this->warn("    • [{$r->id}] {$r->subject_name} | group_id=" . var_export($r->group_id, true) . " | curriculum_id={$r->curriculum_id} | {$r->training_type_name}");
            }
        } else {
            $this->info("  ✅ NULL group_id bo'lgan yozuvlar yo'q");
        }

        // filter() dan keyin
        $groupIdsFromCst = $records->pluck('group_id')->unique()->filter()->values()->toArray();
        $subjectIdsFromCst = $records->pluck('subject_id')->unique()->filter()->values()->toArray();
        $allGroupIdsRaw = $records->pluck('group_id')->unique()->values()->toArray();

        $this->newLine();
        $this->info("━━━ filter() dan keyin ━━━");
        $this->line("  group_ids (filter dan OLDIN):  " . json_encode($allGroupIdsRaw));
        $this->line("  group_ids (filter dan KEYIN):  " . json_encode($groupIdsFromCst));
        $this->line("  subject_ids:                   " . json_encode($subjectIdsFromCst));
        $filteredOut = count($allGroupIdsRaw) - count($groupIdsFromCst);
        if ($filteredOut > 0) {
            $this->error("  ❌ filter() {$filteredOut} ta group_id ni O'CHIRIB TASHLADI!");
        }

        // ═══════════════════════════════════════════════════
        // 2-MANBA: schedules fallback
        // ═══════════════════════════════════════════════════
        $this->newLine(2);
        $this->info("━━━ 2-MANBA: schedules (dars jadvali) fallback ━━━");

        $teacherCombos = DB::table('schedules')
            ->where('employee_id', $employeeHemisId)
            ->where('education_year_current', true)
            ->whereNull('deleted_at')
            ->select('subject_id', 'group_id')
            ->selectRaw('COUNT(*) as lesson_count')
            ->groupBy('subject_id', 'group_id')
            ->get();

        $this->line("  Dars jadvali kombinatsiyalari: {$teacherCombos->count()} ta");

        $scheduleGroupIds = [];
        $scheduleSubjectIds = [];

        if ($teacherCombos->isNotEmpty()) {
            $scheduleTable = $teacherCombos->map(fn($c) => [
                $c->subject_id,
                $c->group_id,
                $c->lesson_count,
            ])->toArray();
            $this->table(['subject_id', 'group_id', 'Dars soni'], $scheduleTable);

            // Har bir combo uchun kim primary ekanini tekshirish
            $comboSubjectIds = $teacherCombos->pluck('subject_id')->unique()->toArray();
            $comboGroupIds = $teacherCombos->pluck('group_id')->unique()->toArray();

            $allStats = DB::table('schedules')
                ->where('education_year_current', true)
                ->whereNull('deleted_at')
                ->whereIn('subject_id', $comboSubjectIds)
                ->whereIn('group_id', $comboGroupIds)
                ->select('subject_id', 'group_id', 'employee_id')
                ->selectRaw('COUNT(*) as lesson_count')
                ->selectRaw('MAX(lesson_date) as last_lesson')
                ->groupBy('subject_id', 'group_id', 'employee_id')
                ->get();

            $statsByCombo = $allStats->groupBy(fn($item) => $item->subject_id . '-' . $item->group_id);
            $comboKeys = $teacherCombos->map(fn($c) => $c->subject_id . '-' . $c->group_id)->toArray();

            $this->newLine();
            $this->info("  Primary o'qituvchi tekshiruvi:");
            foreach ($statsByCombo as $key => $teachers) {
                if (!in_array($key, $comboKeys)) continue;

                $primary = $teachers->sort(function ($a, $b) {
                    if ($a->lesson_count !== $b->lesson_count) {
                        return $b->lesson_count - $a->lesson_count;
                    }
                    return strcmp($b->last_lesson ?? '', $a->last_lesson ?? '');
                })->first();

                $isPrimary = $primary && $primary->employee_id == $employeeHemisId;
                $icon = $isPrimary ? '✅' : '❌';

                if ($isPrimary) {
                    $scheduleSubjectIds[] = $primary->subject_id;
                    $scheduleGroupIds[] = $primary->group_id;
                }

                $this->line("    {$icon} [{$key}] Primary: employee={$primary->employee_id}, dars_soni={$primary->lesson_count}" .
                    ($isPrimary ? '' : " (SIZ EMASSIZ! Siz: {$employeeHemisId})"));

                if (!$isPrimary && $teachers->count() > 1) {
                    foreach ($teachers as $t) {
                        $marker = $t->employee_id == $employeeHemisId ? ' ← SIZ' : '';
                        $this->line("      • employee={$t->employee_id}, dars_soni={$t->lesson_count}, oxirgi={$t->last_lesson}{$marker}");
                    }
                }
            }
        } else {
            $this->warn("  ⚠ schedules da bu o'qituvchi uchun dars TOPILMADI");
        }

        // ═══════════════════════════════════════════════════
        // YAKUNIY NATIJA
        // ═══════════════════════════════════════════════════
        $finalSubjectIds = array_values(array_unique(array_merge($subjectIdsFromCst, $scheduleSubjectIds)));
        $finalGroupIds = array_values(array_unique(array_merge($groupIdsFromCst, $scheduleGroupIds)));

        $this->newLine(2);
        $this->info("━━━ YAKUNIY BIRLASHTIRILGAN NATIJA ━━━");
        $this->line("  subject_ids: " . json_encode($finalSubjectIds) . " (" . count($finalSubjectIds) . " ta)");
        $this->line("  group_ids:   " . json_encode($finalGroupIds) . " (" . count($finalGroupIds) . " ta)");

        // Groups jadvalidan mos guruhlar
        if (!empty($finalGroupIds)) {
            $matchingGroups = DB::table('groups')
                ->whereIn('group_hemis_id', $finalGroupIds)
                ->where('active', true)
                ->where('department_active', true)
                ->select('id', 'name', 'group_hemis_id')
                ->orderBy('name')
                ->get();

            $this->newLine();
            $this->info("  O'qituvchiga ko'rinadigan guruhlar:");
            foreach ($matchingGroups as $g) {
                $this->line("    • {$g->name} (hemis_id: {$g->group_hemis_id})");
            }
        }

        // ═══════════════════════════════════════════════════
        // MAQSADLI GURUHNI MAXSUS QIDIRISH
        // ═══════════════════════════════════════════════════
        $this->newLine(2);
        $this->info("━━━ MAQSADLI GURUH QIDIRISH: *{$groupNameSearch}* ━━━");

        $targetGroups = DB::table('groups')
            ->where('name', 'like', "%{$groupNameSearch}%")
            ->select('id', 'name', 'group_hemis_id', 'active', 'department_active', 'curriculum_hemis_id')
            ->get();

        if ($targetGroups->isEmpty()) {
            $this->error("  ❌ '{$groupNameSearch}' nomli guruh groups jadvalida TOPILMADI");
        } else {
            foreach ($targetGroups as $tg) {
                $isVisible = in_array($tg->group_hemis_id, $finalGroupIds);
                $icon = $isVisible ? '✅' : '❌';

                $this->newLine();
                $this->line("  {$icon} Guruh: {$tg->name}");
                $this->line("     group_hemis_id:    {$tg->group_hemis_id}");
                $this->line("     active:            " . ($tg->active ? 'Ha' : 'Yo\'q'));
                $this->line("     department_active: " . ($tg->department_active ? 'Ha' : 'Yo\'q'));
                $this->line("     curriculum_id:     {$tg->curriculum_hemis_id}");
                $this->line("     Ko'rinadi:         " . ($isVisible ? 'HA ✅' : 'YO\'Q ❌'));

                // curriculum_subject_teachers da shu guruh bilan yozuv bormi?
                $cstDirect = CurriculumSubjectTeacher::where('employee_id', $employeeHemisId)
                    ->where('group_id', $tg->group_hemis_id)
                    ->get();
                $this->line("     CST da to'g'ridan-to'g'ri: {$cstDirect->count()} ta yozuv");

                // curriculum_id orqali qidirish (NULL group_id bo'lishi mumkin)
                $cstByCurriculum = CurriculumSubjectTeacher::where('employee_id', $employeeHemisId)
                    ->where('curriculum_id', $tg->curriculum_hemis_id)
                    ->get();
                if ($cstByCurriculum->isNotEmpty()) {
                    $this->line("     CST da curriculum_id={$tg->curriculum_hemis_id} orqali:");
                    foreach ($cstByCurriculum as $c) {
                        $gidStatus = is_null($c->group_id) ? '⚠ NULL' : $c->group_id;
                        $this->line("       • [{$c->id}] {$c->subject_name} | group_id={$gidStatus} | {$c->training_type_name}");
                    }
                } else {
                    $this->line("     CST da curriculum_id={$tg->curriculum_hemis_id} orqali: topilmadi");
                }

                // schedules da shu guruh bilan dars bormi?
                $schedulesForGroup = DB::table('schedules')
                    ->where('employee_id', $employeeHemisId)
                    ->where('group_id', $tg->group_hemis_id)
                    ->where('education_year_current', true)
                    ->whereNull('deleted_at')
                    ->selectRaw('COUNT(*) as cnt')
                    ->value('cnt');
                $this->line("     Schedules da dars soni: {$schedulesForGroup}");

                if ($schedulesForGroup > 0) {
                    // Boshqa o'qituvchilar ham shu guruhda dars o'tadimi?
                    $otherTeachers = DB::table('schedules')
                        ->where('group_id', $tg->group_hemis_id)
                        ->where('education_year_current', true)
                        ->whereNull('deleted_at')
                        ->select('employee_id')
                        ->selectRaw('COUNT(*) as cnt')
                        ->groupBy('employee_id')
                        ->orderByDesc('cnt')
                        ->get();
                    if ($otherTeachers->count() > 1) {
                        $this->line("     Shu guruhdagi barcha o'qituvchilar:");
                        foreach ($otherTeachers as $ot) {
                            $youMarker = $ot->employee_id == $employeeHemisId ? ' ← SIZ' : '';
                            $this->line("       • employee_id={$ot->employee_id}, dars_soni={$ot->cnt}{$youMarker}");
                        }
                    }
                }

                // Semestr bormi?
                $currentSemester = DB::table('semesters')
                    ->where('curriculum_hemis_id', $tg->curriculum_hemis_id)
                    ->where('current', true)
                    ->select('code', 'name')
                    ->first();
                $this->line("     Joriy semestr:     " . ($currentSemester ? "{$currentSemester->name} (code: {$currentSemester->code})" : '⚠ YO\'Q'));
            }
        }

        // ═══════════════════════════════════════════════════
        // XULOSA
        // ═══════════════════════════════════════════════════
        $this->newLine(2);
        $this->info("╔══════════════════════════════════════════════════════════════╗");
        $this->info("║  XULOSA                                                     ║");
        $this->info("╚══════════════════════════════════════════════════════════════╝");

        $problems = [];

        if ($nullGroupRecords->isNotEmpty()) {
            $problems[] = "HEMIS API dan group_id NULL kelgan: {$nullGroupRecords->count()} ta yozuv. filter() ularni o'chirib tashlaydi.";
        }

        if ($targetGroups->isNotEmpty()) {
            foreach ($targetGroups as $tg) {
                $inFinal = in_array($tg->group_hemis_id, $finalGroupIds);
                if (!$inFinal) {
                    // Sababni aniqlash
                    $hasCstDirect = CurriculumSubjectTeacher::where('employee_id', $employeeHemisId)
                        ->where('group_id', $tg->group_hemis_id)->exists();
                    $hasCstNull = CurriculumSubjectTeacher::where('employee_id', $employeeHemisId)
                        ->where('curriculum_id', $tg->curriculum_hemis_id)
                        ->whereNull('group_id')->exists();
                    $hasSchedule = DB::table('schedules')
                        ->where('employee_id', $employeeHemisId)
                        ->where('group_id', $tg->group_hemis_id)
                        ->where('education_year_current', true)
                        ->whereNull('deleted_at')
                        ->exists();

                    if ($hasCstNull) {
                        $problems[] = "'{$tg->name}': CST da bor, LEKIN group_id=NULL → filter() o'chirib tashlagan.";
                        $problems[] = "YECHIM: CST dagi group_id ni to'g'rilash yoki NULL group_id uchun curriculum_id orqali guruhni aniqlash.";
                    } elseif (!$hasCstDirect && !$hasSchedule) {
                        $problems[] = "'{$tg->name}': Na CST da, na schedules da bu guruh TOPILMADI.";
                    } elseif ($hasSchedule && !$hasCstDirect) {
                        $problems[] = "'{$tg->name}': schedules da bor, lekin boshqa o'qituvchi ko'proq dars o'tgan → primary emas.";
                    }
                }
            }
        }

        if (empty($problems)) {
            $this->info("  ✅ Aniq muammo topilmadi. Guruh ko'rinishi kerak.");
        } else {
            foreach ($problems as $i => $p) {
                $num = $i + 1;
                $this->error("  {$num}. {$p}");
            }
        }

        $this->newLine();

        return 0;
    }

    /**
     * O'qituvchini ism, teachers.id yoki hemis_id bo'yicha topish
     */
    private function resolveTeacher(string $search): ?int
    {
        // 1. Agar son bo'lsa - avval teachers.id, keyin hemis_id sifatida qidirish
        if (is_numeric($search)) {
            $numericId = (int) $search;

            // teachers.id bo'yicha
            $teacher = Teacher::find($numericId);
            if ($teacher) {
                $this->info("  O'qituvchi topildi (teachers.id={$numericId}):");
                $this->line("    Ism:      {$teacher->name}");
                $this->line("    HEMIS ID: {$teacher->hemis_id}");
                $this->line("    Login:    {$teacher->login}");
                return (int) $teacher->hemis_id;
            }

            // hemis_id bo'yicha
            $teacher = Teacher::where('hemis_id', $numericId)->first();
            if ($teacher) {
                $this->info("  O'qituvchi topildi (hemis_id={$numericId}):");
                $this->line("    Ism:      {$teacher->name}");
                $this->line("    ID:       {$teacher->id}");
                $this->line("    Login:    {$teacher->login}");
                return (int) $teacher->hemis_id;
            }

            $this->error("  ❌ ID={$numericId} bo'yicha o'qituvchi topilmadi (teachers.id ham, hemis_id ham)");
            return null;
        }

        // 2. Ism bo'yicha qidirish
        $teachers = Teacher::where('full_name', 'like', "%{$search}%")->limit(10)->get();

        if ($teachers->isEmpty()) {
            $this->error("  ❌ '{$search}' nomli o'qituvchi topilmadi");
            return null;
        }

        if ($teachers->count() === 1) {
            $teacher = $teachers->first();
            $this->info("  O'qituvchi topildi:");
            $this->line("    Ism:      {$teacher->name}");
            $this->line("    ID:       {$teacher->id}");
            $this->line("    HEMIS ID: {$teacher->hemis_id}");
            return (int) $teacher->hemis_id;
        }

        // Bir nechta topilsa — ro'yxat ko'rsatish
        $this->warn("  '{$search}' bo'yicha {$teachers->count()} ta o'qituvchi topildi:");
        $tableData = $teachers->map(fn($t) => [
            $t->id,
            $t->hemis_id,
            $t->name,
            $t->login ?? '-',
        ])->toArray();
        $this->table(['ID', 'HEMIS ID', 'Ism', 'Login'], $tableData);

        $selectedId = $this->ask('Qaysi o\'qituvchini tanlaysiz? (teachers.id kiriting)');
        $selected = $teachers->firstWhere('id', $selectedId);
        if (!$selected) {
            $this->error("  ❌ ID={$selectedId} topilmadi");
            return null;
        }

        return (int) $selected->hemis_id;
    }
}
