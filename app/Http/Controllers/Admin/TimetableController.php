<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Auditorium;
use App\Models\OqimSnapshot;
use App\Models\Teacher;
use App\Models\TimetableBoard;
use App\Models\TimetableCard;
use App\Models\TimetableCardOverride;
use App\Models\TimetableGridSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Dars jadvali tuzish (aSc Timetables uslubida).
 *
 * Oqim tuzilishi tasdiqlangan OqimSnapshot'dan, fanlar ishchi rejalardan
 * olinadi. Har fanning haftalik parasi hisoblanib "dars kartochkalari"
 * yaratiladi: ma'ruza — oqimga (oqimdagi barcha guruhchalarni band qiladi),
 * amaliy — har guruhchaga alohida. Kartochkalar panjaraga qo'lda joylanadi,
 * har joylashda guruh/o'qituvchi/auditoriya konfliktlari tekshiriladi.
 */
class TimetableController extends Controller
{
    public function index()
    {
        $boards = TimetableBoard::withCount('cards')->orderByDesc('id')->get();

        // O'quv yillari — ishchi rejalardan (plan_year boshi + kurs - 1)
        $years = DB::table('manual_curricula')
            ->where('type', 'ishchi')->whereNotNull('plan_year')->whereNotNull('level_code')
            ->get(['plan_year', 'level_code'])
            ->map(function ($c) {
                $start = (int) substr($c->plan_year, 0, 4);
                $course = (int) $c->level_code >= 11 ? (int) $c->level_code - 10 : (int) $c->level_code;
                if (!$start || $course < 1) {
                    return null;
                }
                $as = $start + $course - 1;
                return $as . '-' . ($as + 1);
            })->filter()->unique()->sortDesc()->values();

        $faculties = \App\Models\Department::where('structure_type_code', 11)
            ->where('active', true)->orderBy('name')->get(['id', 'name']);

        return view('admin.timetable.index', compact('boards', 'years', 'faculties'));
    }

    public function storeBoard(Request $request)
    {
        $data = $request->validate([
            'academic_year'   => 'required|string|max:20',
            'semester_parity' => 'required|in:kuzgi,bahorgi',
            'kind'            => 'required|in:plan,real',
            'faculty_id'      => 'nullable|integer',
            'days'            => 'required|integer|min:1|max:7',
            'pairs_per_day'   => 'required|integer|min:1|max:10',
            'weeks'           => 'required|integer|min:1|max:30',
        ]);

        $facName = $data['faculty_id']
            ? optional(\App\Models\Department::find($data['faculty_id']))->name
            : null;

        $board = TimetableBoard::create([
            'name'            => $data['academic_year'] . ' · ' . ($data['semester_parity'] === 'kuzgi' ? 'Kuzgi' : 'Bahorgi')
                                 . ' · ' . ($data['kind'] === 'plan' ? 'Reja' : 'Real')
                                 . ($facName ? ' · ' . $facName : ' · Barcha fakultetlar'),
            'academic_year'   => $data['academic_year'],
            'semester_parity' => $data['semester_parity'],
            'kind'            => $data['kind'],
            'faculty_id'      => $data['faculty_id'] ?? null,
            'faculty_name'    => $facName,
            'days'            => $data['days'],
            'pairs_per_day'   => $data['pairs_per_day'],
            'weeks'           => $data['weeks'],
            // Umumiy sozlamalar sukut qiymatlari
            'institution_name' => $facName,
            'bell_schedule'    => TimetableBoard::defaultBellSchedule((int) $data['pairs_per_day']),
            'day_names'        => array_slice(TimetableBoard::DEFAULT_DAY_NAMES, 0, (int) $data['days']),
            'settings'         => ['days_off' => ['Yakshanba'], 'allow_zero' => false, 'show_day_number' => false],
            'created_by'      => Auth::id(),
        ]);

        return response()->json(['ok' => true, 'board_id' => $board->id]);
    }

    public function destroyBoard(TimetableBoard $board)
    {
        $board->delete();
        return response()->json(['ok' => true]);
    }

    // ── Fan nomi normallashtirish + kafedra xaritasi (fanlar moduli bilan bir xil mantiq) ──

    private function normSubject(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $s = str_replace(["'", '’', 'ʻ', 'ʼ', '`', '´'], '', $s);
        $s = preg_replace('/[.,;:()\-\–\/]/u', ' ', $s);
        $s = preg_replace('/\b\d+([.,]\d+)?\b/u', ' ', $s);
        $s = preg_replace('/\s+/u', ' ', $s);
        return trim($s);
    }

    private function kafedraFor(array $overrides, array $kafMap, string $subject): ?string
    {
        $k = $this->normSubject($subject);
        return ($overrides[$k] ?? null) ?: ($kafMap[$k] ?? null);
    }

    /** Fan → kafedra xaritasi: [$kafMap, $overrides] (assembleRows va subjects uchun umumiy). */
    private function buildKafedraMap(): array
    {
        $kafRows = DB::table('curriculum_subjects')
            ->whereNotNull('department_name')->where('department_name', '!=', '')
            ->selectRaw('subject_name, department_name, COUNT(*) as c')
            ->groupBy('subject_name', 'department_name')->get();
        $acc = [];
        foreach ($kafRows as $r) {
            $k = $this->normSubject($r->subject_name);
            if ($k !== '') {
                $acc[$k][$r->department_name] = ($acc[$k][$r->department_name] ?? 0) + (int) $r->c;
            }
        }
        $kafMap = [];
        foreach ($acc as $k => $deps) {
            arsort($deps);
            $kafMap[$k] = array_key_first($deps);
        }
        $overrides = DB::table('subject_kafedra_overrides')
            ->where('kafedra_name', '!=', '')
            ->pluck('kafedra_name', 'norm_name')->all();

        return [$kafMap, $overrides];
    }

    private function specKey(?string $name): string
    {
        return preg_replace('/[^a-z0-9]/u', '', mb_strtolower(trim((string) $name)));
    }

    /** Tasdiqlangan oqim snapshotlari (fakultet kontekstida dedup — eng so'nggisi). */
    private function boardSnapshots(TimetableBoard $board): array
    {
        $q = OqimSnapshot::where('status', 'approved');
        if ($board->kind === 'plan') {
            $q->where('context->projection', 1)
              ->where('context->academic_year', $board->academic_year);
        } else {
            $q->where(function ($w) {
                $w->whereNull('context->projection')->orWhere('context->projection', 0);
            });
        }
        if ($board->faculty_id) {
            $q->where('context->faculty', (string) $board->faculty_id);
        }
        $byFaculty = [];
        foreach ($q->get() as $snap) {
            $fk = (string) ($snap->context['faculty'] ?? '');
            if (!isset($byFaculty[$fk]) || $snap->approved_at > $byFaculty[$fk]->approved_at) {
                $byFaculty[$fk] = $snap;
            }
        }
        if (count($byFaculty) > 1) {
            unset($byFaculty['']);
        }
        return $byFaculty;
    }

    /**
     * Kartochka qatorlarini yig'ish. $filterSpecKey/$filterCourse berilsa — faqat
     * o'sha yo'nalish+kurs. Haftalik para har yo'nalish+kurs uchun alohida
     * sozlanadigan hafta soniga qarab hisoblanadi. $specsFound — topilgan
     * (yo'nalish, kurs) larni yig'adi (grid sozlamalarini yaratish uchun).
     * Snapshot topilmasa null qaytaradi.
     */
    private function assembleRows(TimetableBoard $board, ?string $filterSpecKey, ?int $filterCourse, array &$specsFound): ?array
    {
        $byFaculty = $this->boardSnapshots($board);
        if (empty($byFaculty)) {
            return null;
        }

        // Fanlar: o'quv yili + semestr juft/toqligi bo'yicha
        $start = (int) substr($board->academic_year, 0, 4);
        $parityRem = $board->semester_parity === 'kuzgi' ? 1 : 0;
        $subjects = DB::table('manual_curriculum_subjects as s')
            ->join('manual_curricula as mc', 'mc.id', '=', 's.manual_curriculum_id')
            ->where('mc.type', 'ishchi')
            ->whereNotNull('s.semester')
            ->whereRaw('MOD(s.semester, 2) = ?', [$parityRem])
            ->whereRaw("(CAST(SUBSTRING(mc.plan_year, 1, 4) AS UNSIGNED) + GREATEST(CAST(mc.level_code AS UNSIGNED) - 10, 0) - 1) = ?", [$start])
            ->groupBy('mc.specialty_name', 'mc.level_code', 's.subject_name')
            ->selectRaw("mc.specialty_name, mc.level_code, s.subject_name,
                MAX(s.lecture) as lecture,
                MAX(s.practice) as practice, MAX(s.laboratory) as laboratory, MAX(s.seminar) as seminar")
            ->get();

        // Kafedra xaritasi
        [$kafMap, $overrides] = $this->buildKafedraMap();

        // Fanlarni yo'nalish+kurs bo'yicha guruhlash
        $subjBySpec = [];
        foreach ($subjects as $s) {
            $course = (int) $s->level_code >= 11 ? (int) $s->level_code - 10 : (int) $s->level_code;
            $subjBySpec[$this->specKey($s->specialty_name)][$course][] = $s;
        }

        // Har yo'nalish+kurs uchun hafta soni (alohida sozlama yoki doska sukut qiymati)
        $gset = TimetableGridSetting::where('board_id', $board->id)->get()
            ->mapWithKeys(fn($g) => [$this->specKey($g->specialty_name) . '|' . $g->course => (int) $g->weeks])
            ->all();

        // Fakultet id → nomi (snapshot fakultet kontekstidan kartaga yozish uchun)
        $facMap = \App\Models\Department::where('structure_type_code', 11)
            ->pluck('name', 'id')->all();

        $now = now();
        $rows = [];
        $paras = function ($hours, $weeks) {
            $h = (float) $hours;
            if ($h <= 0) {
                return 0;
            }
            return max(1, (int) round($h / max(1, $weeks) / 2)); // 1 para = 2 akademik soat
        };

        foreach ($byFaculty as $fk => $snap) {
            $facName = $facMap[(int) $fk] ?? ($facMap[$fk] ?? null);
            foreach ($snap->data ?? [] as $bl) {
                $specName = trim(explode('|', $bl['merge_key'] ?? '')[1] ?? '') ?: ($bl['title'] ?? '');
                $sk = $this->specKey($specName);
                // HAQIQIY fakultet — blokning department_name'i (snapshot faculty
                // konteksti "Barcha fakultetlar"da bo'sh bo'lgani uchun undan olamiz).
                $blockFac = trim((string) ($bl['department_name'] ?? '')) ?: $facName;
                foreach ($bl['courses'] ?? [] as $co) {
                    $lvl = (int) ($co['level_code'] ?? 0);
                    $course = $lvl >= 11 ? $lvl - 10 : $lvl;
                    if ($filterSpecKey !== null && ($sk !== $filterSpecKey || $course !== $filterCourse)) {
                        continue;
                    }
                    $subs = $subjBySpec[$sk][$course] ?? null;
                    if (!$subs) {
                        continue;
                    }
                    $specsFound[$sk . '|' . $course] = ['name' => $specName, 'course' => $course];
                    $weeks = $gset[$sk . '|' . $course] ?? (int) $board->weeks;
                    foreach ($co['oqims'] ?? [] as $oq) {
                        $groupNames = array_values(array_filter(array_map(
                            fn($r) => trim((string) ($r['name'] ?? '')), $oq['rows'] ?? []
                        )));
                        if (empty($groupNames)) {
                            continue;
                        }
                        $oqTotal = (int) ($oq['total'] ?? 0);
                        foreach ($subs as $s) {
                            $kaf = $this->kafedraFor($overrides, $kafMap, $s->subject_name);
                            for ($i = 0; $i < $paras($s->lecture, $weeks); $i++) {
                                $rows[] = [
                                    'board_id' => $board->id,
                                    'specialty_name' => $specName, 'course' => $course, 'faculty_name' => $blockFac,
                                    'oqim_label' => $oq['label'] ?? null, 'lang' => $oq['lang'] ?? 'uz',
                                    'training_type' => 'lecture',
                                    'group_name' => null, 'group_names' => json_encode($groupNames),
                                    'subject_name' => $s->subject_name, 'kafedra_name' => $kaf,
                                    'students' => $oqTotal,
                                    'created_at' => $now, 'updated_at' => $now,
                                ];
                            }
                            $pw = $paras((float) $s->practice + (float) $s->laboratory + (float) $s->seminar, $weeks);
                            if ($pw > 0) {
                                foreach ($oq['rows'] ?? [] as $gr) {
                                    $gn = trim((string) ($gr['name'] ?? ''));
                                    if ($gn === '') {
                                        continue;
                                    }
                                    for ($i = 0; $i < $pw; $i++) {
                                        $rows[] = [
                                            'board_id' => $board->id,
                                            'specialty_name' => $specName, 'course' => $course, 'faculty_name' => $blockFac,
                                            'oqim_label' => $oq['label'] ?? null, 'lang' => $oq['lang'] ?? 'uz',
                                            'training_type' => 'practice',
                                            'group_name' => $gn, 'group_names' => null,
                                            'subject_name' => $s->subject_name, 'kafedra_name' => $kaf,
                                            'students' => (int) ($gr['count'] ?? 0),
                                            'created_at' => $now, 'updated_at' => $now,
                                        ];
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        return $rows;
    }

    /** Migratsiya kechiksa — timetable_cards da hali yo'q ustunlarni insert qatorlaridan olib tashlash. */
    private function stripUnsupportedColumns(array $rows): array
    {
        if (empty($rows) || Schema::hasColumn('timetable_cards', 'faculty_name')) {
            return $rows;
        }
        return array_map(function ($r) {
            unset($r['faculty_name']);
            return $r;
        }, $rows);
    }

    /** Topilgan yo'nalish+kurslar uchun grid sozlamasini (bo'lmasa) doska sukutidan yaratish. */
    private function ensureGridSettings(TimetableBoard $board, array $specsFound): void
    {
        foreach ($specsFound as $info) {
            TimetableGridSetting::firstOrCreate(
                ['board_id' => $board->id, 'specialty_name' => $info['name'], 'course' => $info['course']],
                ['days' => $board->days, 'pairs_per_day' => $board->pairs_per_day, 'weeks' => $board->weeks]
            );
        }
    }

    /**
     * Kartochkalarni yaratish: tasdiqlangan oqim + ishchi reja fanlari.
     * Mavjud kartochkalar o'chirilib qaytadan yaratiladi (joylashuvlar yo'qoladi).
     */
    public function generateCards(TimetableBoard $board)
    {
        $specsFound = [];
        $rows = $this->assembleRows($board, null, null, $specsFound);
        if ($rows === null) {
            return response()->json(['error' => "Tasdiqlangan oqim topilmadi. Avval Oqim sahifasida "
                . ($board->kind === 'plan' ? "kelasi yil (reja) oqimini" : "joriy oqimni") . " tasdiqlang."], 422);
        }
        $rows = $this->stripUnsupportedColumns($rows);

        DB::transaction(function () use ($board, $rows, $specsFound) {
            TimetableCard::where('board_id', $board->id)->delete();
            foreach (array_chunk($rows, 500) as $chunk) {
                TimetableCard::insert($chunk);
            }
            $this->ensureGridSettings($board, $specsFound);
        });

        // Fakultet nomini to'ldiramiz (snapshot bloklaridagi department_name →
        // oqim guruhlari orqali). Generatsiya blockFac'ni yozadi; bu esa
        // eski/qo'lda holatlar uchun himoya (faqat NULL qatorlar).
        $this->backfillFacultyNames($board);

        return response()->json(['ok' => true, 'created' => count($rows)]);
    }

    /**
     * Dars kartochkalariga fakultet nomini SNAPSHOT ma'lumotidan to'ldirish.
     *
     * Snapshot bloki `department_name` = HAQIQIY fakultet; blok ichidagi har
     * oqimning guruhlari o'sha fakultetга tegishli. Shundan guruh → fakultet
     * xaritasini quramiz:
     *  - amaliy karta: `group_name` bo'yicha;
     *  - ma'ruza karta (group_name = NULL): `group_names` ichidagi birinchi
     *    tanilgan guruh bo'yicha.
     * Faqat NULL qiymatlar yangilanadi. Yo'nalish (specialty_name) bir nechta
     * fakultetга umumiy bo'lganda ham guruh orqali to'g'ri ajraladi.
     */
    private function backfillFacultyNames(TimetableBoard $board): void
    {
        if (!Schema::hasColumn('timetable_cards', 'faculty_name')) {
            return;
        }
        try {
            // 1) Guruh nomi → fakultet (snapshot bloklaridan)
            $groupFac = [];
            foreach ($this->boardSnapshots($board) as $snap) {
                foreach ($snap->data ?? [] as $bl) {
                    $fac = trim((string) ($bl['department_name'] ?? ''));
                    if ($fac === '') {
                        continue;
                    }
                    foreach ($bl['courses'] ?? [] as $co) {
                        foreach ($co['oqims'] ?? [] as $oq) {
                            foreach ($oq['rows'] ?? [] as $gr) {
                                $gn = trim((string) ($gr['name'] ?? ''));
                                if ($gn !== '') {
                                    $groupFac[$gn] = $fac;
                                }
                            }
                        }
                    }
                }
            }
            if (empty($groupFac)) {
                return;
            }

            // 2) Amaliy kartalar — guruh nomi bo'yicha
            foreach ($groupFac as $gn => $fac) {
                DB::table('timetable_cards')->where('board_id', $board->id)
                    ->where('group_name', $gn)->whereNull('faculty_name')
                    ->update(['faculty_name' => $fac]);
            }

            // 3) Ma'ruza kartalar (group_name = NULL) — group_names ichidagi
            //    birinchi tanilgan guruh bo'yicha
            $lecs = DB::table('timetable_cards')->where('board_id', $board->id)
                ->whereNull('faculty_name')->whereNotNull('group_names')
                ->select('id', 'group_names')->get();
            $idsByFac = [];
            foreach ($lecs as $c) {
                $names = json_decode($c->group_names, true) ?: [];
                foreach ($names as $gn) {
                    $gn = trim((string) $gn);
                    if (isset($groupFac[$gn])) {
                        $idsByFac[$groupFac[$gn]][] = $c->id;
                        break;
                    }
                }
            }
            foreach ($idsByFac as $fac => $ids) {
                foreach (array_chunk($ids, 500) as $chunk) {
                    DB::table('timetable_cards')->whereIn('id', $chunk)
                        ->update(['faculty_name' => $fac]);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('backfillFacultyNames: ' . $e->getMessage());
        }
    }

    /** Yo'nalish+kurs uchun panjara sozlamasini saqlash (kun/para/hafta). */
    public function saveGrid(Request $request, TimetableBoard $board)
    {
        $data = $request->validate([
            'specialty_name' => 'required|string|max:255',
            'course'         => 'required|integer|min:1|max:7',
            'days'           => 'required|integer|min:1|max:7',
            'pairs_per_day'  => 'required|integer|min:1|max:10',
            'weeks'          => 'required|integer|min:1|max:30',
        ]);

        $gs = TimetableGridSetting::firstOrNew([
            'board_id' => $board->id,
            'specialty_name' => $data['specialty_name'],
            'course' => $data['course'],
        ]);
        $weeksChanged = $gs->exists && (int) $gs->weeks !== (int) $data['weeks'];
        $gs->fill([
            'days' => $data['days'],
            'pairs_per_day' => $data['pairs_per_day'],
            'weeks' => $data['weeks'],
        ])->save();

        // Panjaradan tashqarida qolgan joylashuvlarni bo'shatamiz
        TimetableCard::where('board_id', $board->id)
            ->where('specialty_name', $data['specialty_name'])
            ->where('course', $data['course'])
            ->where(function ($q) use ($data) {
                $q->where('day', '>', $data['days'])->orWhere('pair', '>', $data['pairs_per_day']);
            })
            ->update(['day' => null, 'pair' => null]);

        // Hafta soni o'zgargan bo'lsa — shu yo'nalishning kartochkalari qayta yaratiladi
        if ($weeksChanged) {
            $sf = [];
            $rows = $this->stripUnsupportedColumns($this->assembleRows($board, $this->specKey($data['specialty_name']), (int) $data['course'], $sf) ?? []);
            DB::transaction(function () use ($board, $data, $rows) {
                TimetableCard::where('board_id', $board->id)
                    ->where('specialty_name', $data['specialty_name'])
                    ->where('course', $data['course'])->delete();
                foreach (array_chunk($rows ?? [], 500) as $chunk) {
                    TimetableCard::insert($chunk);
                }
            });
        }

        return response()->json(['ok' => true, 'regenerated' => $weeksChanged]);
    }

    /**
     * Avtomatik (optimal) joylashtirish — aSc Timetables uslubidagi generator.
     *
     * Qattiq cheklovlar (hech qachon buzilmaydi):
     *   - guruh bir vaqtda ikki darsda bo'lmaydi (yo'nalish+kurs ichida);
     *   - o'qituvchi biriktirilgan bo'lsa — bir vaqtda bitta darsda (butun doska);
     *   - auditoriya biriktirilsa — sig'imi yetarli va bir vaqtda bo'sh xona.
     * Yumshoq cheklovlar (jarima minimallashtiriladi):
     *   - guruhda "oyna" (bo'sh para) bo'lmasligi — kun ichida paralar zich;
     *   - bir fanni hafta bo'ylab teng taqsimlash (bir kunga to'planmasin);
     *   - kunlar bo'ylab yukni tekislash + ertalabki paralarga ustunlik.
     *
     * Ochko'z (greedy) + jarima baholash: har karta eng kam jarimali bo'sh
     * katakka qo'yiladi. Qo'lda joylashtirilgan kartalar (reset=0 bo'lsa)
     * qo'zg'atilmaydi — ular band katak sifatida hisobga olinadi.
     */
    public function autoPlace(Request $request, TimetableBoard $board)
    {
        $data = $request->validate([
            'specialty_name' => 'nullable|string|max:255',
            'course'         => 'nullable|integer|min:1|max:7',
            'reset'          => 'nullable|boolean',
            'assign_rooms'   => 'nullable|boolean',
            'training_type'  => 'nullable|in:lecture,practice',
        ]);
        $scopeSpec = $data['specialty_name'] ?? null;
        $scopeCourse = isset($data['course']) ? (int) $data['course'] : null;
        $scopeType = $data['training_type'] ?? null;   // faqat ma'ruza yoki faqat amaliy
        $reset = (bool) ($data['reset'] ?? false);
        $assignRooms = (bool) ($data['assign_rooms'] ?? false);

        // Reset — tanlangan qamrovdagi mavjud joylashuvlarni bo'shatamiz.
        // Qamrov: yo'nalish+kurs (scopeSpec) → faqat o'sha; aks holda kurs
        // berilsa (scopeSpec=null, scopeCourse!=null) → shu kursning barcha
        // yo'nalishlari; ikkalasi ham null → butun doska.
        if ($reset) {
            $q = TimetableCard::where('board_id', $board->id);
            if ($scopeSpec !== null) {
                $q->where('specialty_name', $scopeSpec)->where('course', $scopeCourse);
            } elseif ($scopeCourse !== null) {
                $q->where('course', $scopeCourse);
            }
            if ($scopeType !== null) {
                $q->where('training_type', $scopeType);
            }
            $q->update(['day' => null, 'pair' => null, 'auditorium_code' => null, 'auditorium_name' => null]);
        }

        // Panjara o'lchamlari (yo'nalish+kurs bo'yicha)
        $gridSettings = TimetableGridSetting::where('board_id', $board->id)->get()
            ->keyBy(fn($g) => $g->specialty_name . '|' . $g->course);
        $dimsFor = function ($spec, $course) use ($gridSettings, $board) {
            $g = $gridSettings[$spec . '|' . $course] ?? null;
            return [(int) ($g->days ?? $board->days), (int) ($g->pairs_per_day ?? $board->pairs_per_day)];
        };

        $all = TimetableCard::where('board_id', $board->id)->get();

        // Band kataklar — joylashgan (fiks) kartalardan
        $groupBusy = [];   // "spec|course|day|pair" => [group,...]
        $teacherBusy = []; // "teacher_id|day|pair" => true
        $roomBusy = [];    // "code|day|pair" => true
        foreach ($all as $c) {
            if ($c->day && $c->pair) {
                $this->markBusy($groupBusy, $teacherBusy, $roomBusy, $c);
            }
        }

        // Auditoriya havzasi (sig'im o'sish tartibida — zich joylash uchun)
        $rooms = $assignRooms
            ? Auditorium::where('active', true)->orderBy('volume')->get(['code', 'name', 'volume'])
            : collect();

        // Joylanadigan kartalar — qamrovdagi bo'sh (joylashmagan)lar
        $toPlace = $all->filter(function ($c) use ($scopeSpec, $scopeCourse, $scopeType) {
            if ($c->day && $c->pair) {
                return false;
            }
            if ($scopeType !== null && $c->training_type !== $scopeType) {
                return false;
            }
            if ($scopeSpec !== null) {
                return $c->specialty_name === $scopeSpec && (int) $c->course === $scopeCourse;
            }
            // Yo'nalish berilmagan: kurs berilsa — shu kursning barcha
            // yo'nalishlari; aks holda butun doska.
            return $scopeCourse === null || (int) $c->course === $scopeCourse;
        });

        // Tartib: eng ko'p cheklovli avval — ma'ruza (ko'p guruh band qiladi),
        // ko'proq guruh, ko'proq talaba
        $toPlace = $toPlace->sort(function ($a, $b) {
            $ka = [$a->specialty_name, (int) $a->course, $a->training_type === 'lecture' ? 0 : 1,
                   -count($a->occupiedGroups()), -(int) $a->students];
            $kb = [$b->specialty_name, (int) $b->course, $b->training_type === 'lecture' ? 0 : 1,
                   -count($b->occupiedGroups()), -(int) $b->students];
            return $ka <=> $kb;
        })->values();

        $subjDay = [];  // "spreadKey|day" => count (fan taqsimoti uchun)
        $placed = 0;
        $unplaced = 0;
        $roomsAssigned = 0;
        $touched = [];

        foreach ($toPlace as $c) {
            [$days, $pairs] = $dimsFor($c->specialty_name, (int) $c->course);
            $groups = $c->occupiedGroups();
            $best = null;
            $bestPen = INF;
            $bestRoom = null;

            for ($d = 1; $d <= $days; $d++) {
                for ($p = 1; $p <= $pairs; $p++) {
                    // Qattiq: guruh bandligi
                    $gk = $c->specialty_name . '|' . $c->course . '|' . $d . '|' . $p;
                    if (!empty($groupBusy[$gk]) && array_intersect($groups, $groupBusy[$gk])) {
                        continue;
                    }
                    // Qattiq: o'qituvchi bandligi (biriktirilgan bo'lsa)
                    if ($c->teacher_id && !empty($teacherBusy[$c->teacher_id . '|' . $d . '|' . $p])) {
                        continue;
                    }
                    // Qattiq: auditoriya (sig'im yetarli + bo'sh)
                    $room = null;
                    if ($assignRooms) {
                        foreach ($rooms as $r) {
                            if ((int) ($r->volume ?? 0) < (int) $c->students) {
                                continue;
                            }
                            if (!empty($roomBusy[$r->code . '|' . $d . '|' . $p])) {
                                continue;
                            }
                            $room = $r;
                            break; // sig'imi yetadigan eng kichik bo'sh xona
                        }
                        if (!$room) {
                            continue; // bu katakka mos xona yo'q
                        }
                    }
                    // Yumshoq jarima
                    $pen = $this->slotPenalty($c, $groups, $d, $p, $pairs, $groupBusy, $subjDay);
                    if ($pen < $bestPen) {
                        $bestPen = $pen;
                        $best = [$d, $p];
                        $bestRoom = $room;
                    }
                }
            }

            if ($best === null) {
                $unplaced++;
                continue;
            }
            [$d, $p] = $best;
            $c->day = $d;
            $c->pair = $p;
            if ($assignRooms && $bestRoom) {
                $c->auditorium_code = $bestRoom->code;
                $c->auditorium_name = $bestRoom->name;
                $roomsAssigned++;
            }
            $this->markBusy($groupBusy, $teacherBusy, $roomBusy, $c);
            $sk = $this->spreadKey($c) . '|' . $d;
            $subjDay[$sk] = ($subjDay[$sk] ?? 0) + 1;
            $touched[] = $c;
            $placed++;
        }

        DB::transaction(function () use ($touched) {
            foreach ($touched as $c) {
                $c->save();
            }
        });

        return response()->json([
            'ok' => true,
            'placed' => $placed,
            'unplaced' => $unplaced,
            'rooms_assigned' => $roomsAssigned,
        ]);
    }

    /** Katakni band deb belgilash (guruh/o'qituvchi/auditoriya xaritalarida). */
    private function markBusy(array &$groupBusy, array &$teacherBusy, array &$roomBusy, TimetableCard $c): void
    {
        $k = $c->specialty_name . '|' . $c->course . '|' . $c->day . '|' . $c->pair;
        $groupBusy[$k] = array_merge($groupBusy[$k] ?? [], $c->occupiedGroups());
        if ($c->teacher_id) {
            $teacherBusy[$c->teacher_id . '|' . $c->day . '|' . $c->pair] = true;
        }
        if ($c->auditorium_code) {
            $roomBusy[$c->auditorium_code . '|' . $c->day . '|' . $c->pair] = true;
        }
    }

    /** Fan taqsimoti kaliti: ma'ruza — oqim bo'yicha, amaliyot — guruhcha bo'yicha. */
    private function spreadKey(TimetableCard $c): string
    {
        $who = $c->training_type === 'lecture' ? ('L' . $c->oqim_label) : ('P' . $c->group_name);
        return $c->specialty_name . '|' . $c->course . '|' . $who . '|' . $this->normSubject((string) $c->subject_name);
    }

    /** Katak jarimasi: oyna + fan taqsimoti + kun yuki + ertalab ustunligi. */
    private function slotPenalty(TimetableCard $c, array $groups, int $d, int $p, int $pairs, array $groupBusy, array $subjDay): float
    {
        $spc = $c->specialty_name . '|' . $c->course;
        $pen = ($p - 1) * 0.2; // ertalabki paralarga yengil ustunlik
        foreach ($groups as $g) {
            $used = [$p => true];
            for ($pp = 1; $pp <= $pairs; $pp++) {
                if ($pp === $p) {
                    continue;
                }
                $busy = $groupBusy[$spc . '|' . $d . '|' . $pp] ?? [];
                if (in_array($g, $busy, true)) {
                    $used[$pp] = true;
                }
            }
            $keys = array_keys($used);
            $holes = (max($keys) - min($keys) + 1) - count($keys);
            $pen += $holes * 10;               // oyna — eng og'ir jarima
            $pen += (count($keys) - 1) * 1;    // kun yukini kunlar bo'ylab tekislash
        }
        $sk = $this->spreadKey($c) . '|' . $d;
        $pen += ($subjDay[$sk] ?? 0) * 6;      // shu fan shu kunda takrorlansa — jarima
        return $pen;
    }

    /** Doska ma'lumotlari: barcha kartochkalar (konflikt tekshiruvi butun doska bo'ylab). */
    public function data(TimetableBoard $board)
    {
        $cards = TimetableCard::where('board_id', $board->id)->get()->map(fn($c) => [
            'id' => $c->id,
            'specialty_name' => $c->specialty_name,
            'course' => $c->course,
            'faculty_name' => $c->faculty_name,
            'oqim_label' => $c->oqim_label,
            'lang' => $c->lang,
            'training_type' => $c->training_type,
            'group_name' => $c->group_name,
            'group_names' => $c->group_names,
            'subject_name' => $c->subject_name,
            'kafedra_name' => $c->kafedra_name,
            'students' => $c->students,
            'teacher_id' => $c->teacher_id,
            'teacher_name' => $c->teacher_name,
            'auditorium_code' => $c->auditorium_code,
            'auditorium_name' => $c->auditorium_name,
            'day' => $c->day,
            'pair' => $c->pair,
        ]);

        $grids = TimetableGridSetting::where('board_id', $board->id)
            ->get(['specialty_name', 'course', 'days', 'pairs_per_day', 'weeks']);

        // Hafta bo'yicha istisnolar (individual haftalar) — migratsiya kechiksa bo'sh
        $overrides = collect();
        if (Schema::hasTable('timetable_card_overrides')) {
            $overrides = DB::table('timetable_card_overrides as o')
                ->join('timetable_cards as c', 'c.id', '=', 'o.card_id')
                ->where('c.board_id', $board->id)
                ->get(['o.card_id', 'o.week', 'o.day', 'o.pair', 'o.cancelled'])
                ->map(fn($o) => [
                    'card_id'   => (int) $o->card_id,
                    'week'      => (int) $o->week,
                    'day'       => $o->day !== null ? (int) $o->day : null,
                    'pair'      => $o->pair !== null ? (int) $o->pair : null,
                    'cancelled' => (bool) $o->cancelled,
                ]);
        }

        return response()->json([
            'board' => array_merge(
                $board->only(['id', 'name', 'institution_name', 'days', 'pairs_per_day', 'weeks',
                    'academic_year', 'semester_parity', 'kind']),
                [
                    'bell_schedule' => $board->bell_schedule ?: TimetableBoard::defaultBellSchedule((int) $board->pairs_per_day),
                    'day_names'     => $board->day_names ?: array_slice(TimetableBoard::DEFAULT_DAY_NAMES, 0, (int) $board->days),
                    'settings'      => $board->settings ?: [],
                ]
            ),
            'cards' => $cards,
            'grids' => $grids,
            'overrides' => $overrides,
        ]);
    }

    /**
     * Hafta bo'yicha dars istisnosi: shu haftada ko'chirish / bekor qilish / shablonga qaytarish.
     * Faqat tanlangan haftaga ta'sir qiladi — boshqa haftalar shablon bo'yicha qoladi.
     */
    public function weekOverride(Request $request, TimetableCard $card)
    {
        $data = $request->validate([
            'week'   => 'required|integer|min:1|max:30',
            'action' => 'required|in:move,cancel,reset',
            'day'    => 'nullable|integer|min:1|max:10',
            'pair'   => 'nullable|integer|min:1|max:10',
        ]);
        $week = (int) $data['week'];

        if ($data['action'] === 'reset') {
            TimetableCardOverride::where('card_id', $card->id)->where('week', $week)->delete();
            return response()->json(['ok' => true]);
        }

        if ($data['action'] === 'cancel') {
            TimetableCardOverride::updateOrCreate(
                ['card_id' => $card->id, 'week' => $week],
                ['day' => null, 'pair' => null, 'cancelled' => true]
            );
            return response()->json(['ok' => true]);
        }

        // move — tanlangan haftadagi konfliktni tekshiramiz
        $day = $data['day'] ?? null;
        $pair = $data['pair'] ?? null;
        if (!$day || !$pair) {
            return response()->json(['error' => 'Kun va para ko\'rsatilishi kerak'], 422);
        }
        $conflicts = $this->findWeekConflicts($card, $week, $day, $pair);
        if (!empty($conflicts)) {
            return response()->json(['error' => implode(' · ', $conflicts)], 422);
        }
        TimetableCardOverride::updateOrCreate(
            ['card_id' => $card->id, 'week' => $week],
            ['day' => $day, 'pair' => $pair, 'cancelled' => false]
        );
        return response()->json(['ok' => true]);
    }

    /** Tanlangan haftadagi effektiv joylashuvlar bo'yicha konflikt tekshiruvi. */
    private function findWeekConflicts(TimetableCard $card, int $week, int $day, int $pair): array
    {
        $ovr = TimetableCardOverride::whereHas('card', fn($q) => $q->where('board_id', $card->board_id))
            ->where('week', $week)->get()->keyBy('card_id');
        $others = TimetableCard::where('board_id', $card->board_id)->where('id', '!=', $card->id)->get();

        $myGroups = $card->occupiedGroups();
        $errors = [];
        foreach ($others as $o) {
            $ov = $ovr->get($o->id);
            if ($ov) {
                if ($ov->cancelled) {
                    continue;
                }
                $od = $ov->day;
                $op = $ov->pair;
            } else {
                $od = $o->day;
                $op = $o->pair;
            }
            if ((int) $od !== $day || (int) $op !== $pair) {
                continue;
            }
            if ($o->specialty_name === $card->specialty_name && (int) $o->course === (int) $card->course) {
                $overlap = array_intersect($myGroups, $o->occupiedGroups());
                if (!empty($overlap)) {
                    $errors[] = 'Guruh band: ' . implode(',', $overlap) . ' (' . $o->subject_name . ')';
                }
            }
            if ($card->teacher_id && $o->teacher_id && (int) $o->teacher_id === (int) $card->teacher_id) {
                $errors[] = "O'qituvchi band: " . $o->teacher_name . ' (' . $o->subject_name . ')';
            }
            if ($card->auditorium_code && $o->auditorium_code === $card->auditorium_code) {
                $errors[] = 'Auditoriya band: ' . $o->auditorium_name . ' (' . $o->subject_name . ')';
            }
        }
        return array_unique($errors);
    }

    /** Yo'nalish+kurs uchun panjara o'lchami (alohida sozlama yoki doska sukuti). */
    private function gridFor(TimetableBoard $board, string $specialty, int $course): array
    {
        $gs = TimetableGridSetting::where('board_id', $board->id)
            ->where('specialty_name', $specialty)->where('course', $course)->first();
        return [
            'days'  => $gs->days ?? $board->days,
            'pairs' => $gs->pairs_per_day ?? $board->pairs_per_day,
        ];
    }

    /** Kartochkani joylash/ko'chirish/olib tashlash — konflikt tekshiruvi bilan. */
    public function placeCard(Request $request, TimetableCard $card)
    {
        $board = $card->board;
        $grid = $this->gridFor($board, $card->specialty_name, (int) $card->course);
        $data = $request->validate([
            'day'  => 'nullable|integer|min:1|max:' . $grid['days'],
            'pair' => 'nullable|integer|min:1|max:' . $grid['pairs'],
        ]);

        $day = $data['day'] ?? null;
        $pair = $data['pair'] ?? null;

        if ($day && $pair) {
            $conflicts = $this->findConflicts($card, $day, $pair);
            if (!empty($conflicts)) {
                return response()->json(['error' => implode(' · ', $conflicts)], 422);
            }
        }

        $card->update(['day' => $day, 'pair' => $pair]);
        return response()->json(['ok' => true]);
    }

    private function findConflicts(TimetableCard $card, int $day, int $pair): array
    {
        $others = TimetableCard::where('board_id', $card->board_id)
            ->where('id', '!=', $card->id)
            ->where('day', $day)->where('pair', $pair)
            ->get();

        $myGroups = $card->occupiedGroups();
        $errors = [];
        foreach ($others as $o) {
            // Guruh konflikti — bir yo'nalish+kurs ichida
            if ($o->specialty_name === $card->specialty_name && (int) $o->course === (int) $card->course) {
                $overlap = array_intersect($myGroups, $o->occupiedGroups());
                if (!empty($overlap)) {
                    $errors[] = 'Guruh band: ' . implode(',', $overlap) . ' (' . $o->subject_name . ')';
                }
            }
            // O'qituvchi konflikti — butun doska bo'ylab
            if ($card->teacher_id && $o->teacher_id && (int) $o->teacher_id === (int) $card->teacher_id) {
                $errors[] = "O'qituvchi band: " . $o->teacher_name . ' (' . $o->subject_name . ')';
            }
            // Auditoriya konflikti — butun doska bo'ylab
            if ($card->auditorium_code && $o->auditorium_code === $card->auditorium_code) {
                $errors[] = 'Auditoriya band: ' . $o->auditorium_name . ' (' . $o->subject_name . ')';
            }
        }
        return array_unique($errors);
    }

    /** Kartochka rekvizitlari: o'qituvchi / auditoriya biriktirish. */
    public function updateCard(Request $request, TimetableCard $card)
    {
        $data = $request->validate([
            'teacher_id'      => 'nullable|integer',
            'auditorium_code' => 'nullable|string|max:50',
        ]);

        if (array_key_exists('teacher_id', $data)) {
            if ($data['teacher_id']) {
                $t = Teacher::find($data['teacher_id']);
                $card->teacher_id = $t?->id;
                $card->teacher_name = $t?->short_name ?: $t?->full_name;
            } else {
                $card->teacher_id = null;
                $card->teacher_name = null;
            }
        }
        if (array_key_exists('auditorium_code', $data)) {
            if ($data['auditorium_code']) {
                $a = Auditorium::where('code', $data['auditorium_code'])->first();
                $card->auditorium_code = $a?->code;
                $card->auditorium_name = $a?->name;
            } else {
                $card->auditorium_code = null;
                $card->auditorium_name = null;
            }
        }

        // Joylashgan bo'lsa — yangi rekvizit bilan konflikt tekshiramiz
        if ($card->day && $card->pair) {
            $conflicts = $this->findConflicts($card, $card->day, $card->pair);
            if (!empty($conflicts)) {
                return response()->json(['error' => implode(' · ', $conflicts)], 422);
            }
        }

        $card->save();
        return response()->json([
            'ok' => true,
            'teacher_name' => $card->teacher_name,
            'auditorium_name' => $card->auditorium_name,
            'auditorium_code' => $card->auditorium_code,
        ]);
    }

    /** O'qituvchilar (kafedra nomi bo'yicha filtrlash mumkin). */
    public function teachers(Request $request)
    {
        $q = Teacher::query()->whereNotNull('full_name');
        if ($request->filled('kafedra')) {
            $q->where('department', 'like', '%' . $request->kafedra . '%');
        }
        if ($request->filled('search')) {
            $q->where('full_name', 'like', '%' . $request->search . '%');
        }
        return response()->json(
            $q->orderBy('full_name')->limit(100)->get(['id', 'full_name', 'short_name', 'department', 'lavozim'])
        );
    }

    // ══════════════════════════════════════════════════════════════════════
    //  aSc Timetables uslubidagi boshqaruv dialoglari: Fanlar, Guruhlar,
    //  Auditoriyalar, O'qituvchilar. Har biri ro'yxat + qidiruv, auditoriya
    //  esa to'liq CRUD + Excel import.
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Fanlar dialogi — doskaning o'quv yili + semestr juftligi bo'yicha
     * ishchi rejalardagi fanlar, yo'nalish+kurs kesimida (Excel "fanlar
     * royxati" varag'i uslubida). Har fan uchun ma'ruza/amaliy/laboratoriya
     * soatlari va kafedra ko'rsatiladi.
     */
    public function subjects(TimetableBoard $board)
    {
        $start = (int) substr($board->academic_year, 0, 4);
        $parityRem = $board->semester_parity === 'kuzgi' ? 1 : 0;

        $rows = DB::table('manual_curriculum_subjects as s')
            ->join('manual_curricula as mc', 'mc.id', '=', 's.manual_curriculum_id')
            ->where('mc.type', 'ishchi')
            ->whereNotNull('s.semester')
            ->whereRaw('MOD(s.semester, 2) = ?', [$parityRem])
            ->whereRaw("(CAST(SUBSTRING(mc.plan_year, 1, 4) AS UNSIGNED) + GREATEST(CAST(mc.level_code AS UNSIGNED) - 10, 0) - 1) = ?", [$start])
            ->groupBy('mc.specialty_name', 'mc.level_code', 's.semester', 's.subject_name')
            ->selectRaw("mc.specialty_name, mc.level_code, s.semester, s.subject_name,
                MAX(s.lecture) as lecture, MAX(s.practice) as practice,
                MAX(s.laboratory) as laboratory, MAX(s.seminar) as seminar")
            ->orderBy('mc.specialty_name')->orderBy('mc.level_code')->orderBy('s.subject_name')
            ->get();

        [$kafMap, $overrides] = $this->buildKafedraMap();
        $weeks = max(1, (int) $board->weeks);

        $out = [];
        foreach ($rows as $r) {
            $course = (int) $r->level_code >= 11 ? (int) $r->level_code - 10 : (int) $r->level_code;
            $lec = (float) $r->lecture;
            $prc = (float) $r->practice + (float) $r->laboratory + (float) $r->seminar;
            $out[] = [
                'specialty_name' => $r->specialty_name,
                'course'         => $course,
                'semester'       => (int) $r->semester,
                'subject_name'   => $r->subject_name,
                'kafedra_name'   => $this->kafedraFor($overrides, $kafMap, $r->subject_name),
                'lecture'        => $lec,
                'practice'       => (float) $r->practice,
                'laboratory'     => (float) $r->laboratory,
                'seminar'        => (float) $r->seminar,
                // Haftalik para (1 para = 2 akademik soat)
                'lec_pairs'      => $lec > 0 ? max(1, (int) round($lec / $weeks / 2)) : 0,
                'prc_pairs'      => $prc > 0 ? max(1, (int) round($prc / $weeks / 2)) : 0,
            ];
        }

        return response()->json(['weeks' => $weeks, 'subjects' => $out]);
    }

    /**
     * Guruhlar dialogi — doskaning tasdiqlangan oqim snapshotlaridagi
     * guruhchalar (yo'nalish+kurs+oqim+til kesimida, talaba soni bilan).
     */
    public function groups(TimetableBoard $board)
    {
        $byFaculty = $this->boardSnapshots($board);
        $out = [];
        foreach ($byFaculty as $snap) {
            foreach ($snap->data ?? [] as $bl) {
                $specName = trim(explode('|', $bl['merge_key'] ?? '')[1] ?? '') ?: ($bl['title'] ?? '');
                foreach ($bl['courses'] ?? [] as $co) {
                    $lvl = (int) ($co['level_code'] ?? 0);
                    $course = $lvl >= 11 ? $lvl - 10 : $lvl;
                    foreach ($co['oqims'] ?? [] as $oq) {
                        foreach ($oq['rows'] ?? [] as $gr) {
                            $gn = trim((string) ($gr['name'] ?? ''));
                            if ($gn === '') {
                                continue;
                            }
                            $out[] = [
                                'group_name'     => $gn,
                                'specialty_name' => $specName,
                                'course'         => $course,
                                'oqim_label'     => $oq['label'] ?? null,
                                'lang'           => $oq['lang'] ?? 'uz',
                                'students'       => (int) ($gr['count'] ?? 0),
                            ];
                        }
                    }
                }
            }
        }
        usort($out, fn($a, $b) => [$a['specialty_name'], $a['course'], $a['group_name']]
            <=> [$b['specialty_name'], $b['course'], $b['group_name']]);

        return response()->json(['groups' => $out]);
    }

    /** Auditoriyalar ro'yxati (dialog uchun — barcha maydonlar). */
    public function auditoriums()
    {
        return response()->json(
            Auditorium::orderBy('active', 'desc')->orderBy('name')
                ->get(['id', 'code', 'name', 'volume', 'active', 'auditorium_type_name', 'building_name'])
        );
    }

    /** Yangi auditoriya qo'shish. */
    public function storeAuditorium(Request $request)
    {
        $data = $this->validateAuditorium($request);
        $a = Auditorium::create($data);
        return response()->json(['ok' => true, 'auditorium' => $a]);
    }

    /** Auditoriyani tahrirlash. */
    public function updateAuditorium(Request $request, Auditorium $auditorium)
    {
        $data = $this->validateAuditorium($request, $auditorium->id);
        $auditorium->update($data);
        return response()->json(['ok' => true, 'auditorium' => $auditorium]);
    }

    /** Auditoriyani o'chirish (kartochkalarda ishlatilsa faqat nofaollashadi). */
    public function destroyAuditorium(Auditorium $auditorium)
    {
        $used = TimetableCard::where('auditorium_code', $auditorium->code)->exists();
        if ($used) {
            $auditorium->update(['active' => false]);
            return response()->json(['ok' => true, 'deactivated' => true]);
        }
        $auditorium->delete();
        return response()->json(['ok' => true, 'deactivated' => false]);
    }

    private function validateAuditorium(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'code'                 => 'required|string|max:50|unique:auditoriums,code' . ($ignoreId ? ',' . $ignoreId : ''),
            'name'                 => 'required|string|max:255',
            'volume'               => 'required|integer|min:0|max:2000',
            'active'               => 'nullable|boolean',
            'building_name'        => 'nullable|string|max:255',
            'auditorium_type_name' => 'nullable|string|max:255',
        ]);
    }

    /**
     * Auditoriyalarni Excel/CSV dan import qilish. Kutilgan sarlavhalar
     * (kichik harf, bo'sh joy "_"): kod | nomi | sigim | bino | turi.
     * Mavjud kod yangilanadi, yo'q kod qo'shiladi (upsert).
     */
    public function importAuditoriums(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls,csv,txt']);

        $import = new \App\Imports\AuditoriumImport();
        \Maatwebsite\Excel\Facades\Excel::import($import, $request->file('file'));

        return response()->json([
            'ok' => true,
            'imported' => $import->imported,
            'updated' => $import->updated,
            'errors' => $import->errors,
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════
    //  O'qituvchi biriktirish: dars birliklari (subject × oqim/guruh) bo'yicha
    //  ommaviy biriktirish. Bir birlikning barcha (haftalik takror) kartalari
    //  bitta o'qituvchiga tegishli bo'ladi.
    // ══════════════════════════════════════════════════════════════════════

    /** Karta uchun dars birligi kaliti (ma'ruza — oqim; amaliy — guruhcha). */
    private function unitKey(TimetableCard $c): string
    {
        $scope = $c->training_type === 'lecture' ? ('L|' . $c->oqim_label) : ('P|' . $c->group_name);
        return implode('¦', [$c->specialty_name, $c->course, $c->subject_name, $c->training_type, $scope]);
    }

    /** Doskadagi dars birliklari + joriy o'qituvchi (biriktirish matritsasi uchun). */
    public function teacherUnits(TimetableBoard $board)
    {
        $cards = TimetableCard::where('board_id', $board->id)->get();
        $units = [];
        foreach ($cards as $c) {
            $k = $this->unitKey($c);
            if (!isset($units[$k])) {
                $units[$k] = [
                    'specialty_name' => $c->specialty_name, 'course' => (int) $c->course,
                    'subject_name'   => $c->subject_name, 'training_type' => $c->training_type,
                    'oqim_label'     => $c->oqim_label, 'group_name' => $c->group_name,
                    'kafedra_name'   => $c->kafedra_name, 'lang' => $c->lang,
                    'students'       => (int) $c->students, 'cards' => 0,
                    'placed'         => 0,
                    'teacher_id'     => $c->teacher_id, 'teacher_name' => $c->teacher_name,
                    'teacher_mixed'  => false,
                ];
            }
            $units[$k]['cards']++;
            if ($c->day && $c->pair) {
                $units[$k]['placed']++;
            }
            if ($units[$k]['teacher_id'] !== $c->teacher_id) {
                $units[$k]['teacher_mixed'] = true;
            }
        }
        $out = array_values($units);
        usort($out, fn($a, $b) => [$a['specialty_name'], $a['course'], $b['training_type'], $a['subject_name'], (string) $a['oqim_label'], (string) $a['group_name']]
            <=> [$b['specialty_name'], $b['course'], $a['training_type'], $b['subject_name'], (string) $b['oqim_label'], (string) $b['group_name']]);

        return response()->json(['units' => $out]);
    }

    /** Dars birligiga o'qituvchini ommaviy biriktirish (barcha kartalariga). */
    public function assignTeacher(Request $request, TimetableBoard $board)
    {
        $data = $request->validate([
            'specialty_name' => 'required|string|max:255',
            'course'         => 'required|integer|min:1|max:7',
            'subject_name'   => 'required|string|max:255',
            'training_type'  => 'required|in:lecture,practice',
            'oqim_label'     => 'nullable|string|max:50',
            'group_name'     => 'nullable|string|max:255',
            'teacher_id'     => 'nullable|integer',
        ]);

        $q = TimetableCard::where('board_id', $board->id)
            ->where('specialty_name', $data['specialty_name'])
            ->where('course', $data['course'])
            ->where('subject_name', $data['subject_name'])
            ->where('training_type', $data['training_type']);
        if ($data['training_type'] === 'lecture') {
            isset($data['oqim_label']) ? $q->where('oqim_label', $data['oqim_label']) : $q->whereNull('oqim_label');
        } else {
            isset($data['group_name']) ? $q->where('group_name', $data['group_name']) : $q->whereNull('group_name');
        }

        $teacherName = null;
        if (!empty($data['teacher_id'])) {
            $t = Teacher::find($data['teacher_id']);
            $teacherName = $t?->short_name ?: $t?->full_name;
            $affected = $q->update(['teacher_id' => $t?->id, 'teacher_name' => $teacherName]);
        } else {
            $affected = $q->update(['teacher_id' => null, 'teacher_name' => null]);
        }

        return response()->json(['ok' => true, 'teacher_name' => $teacherName, 'affected' => $affected]);
    }

    // ══════════════════════════════════════════════════════════════════════
    //  Umumiy sozlamalar (aSc "Установки" uslubida): muassasa nomi, kunlar,
    //  dam olish kunlari va qo'ng'iroqlar jadvali (juftliklar vaqtlari).
    // ══════════════════════════════════════════════════════════════════════

    /** Doska sozlamalarini o'qish (default qiymatlar bilan to'ldirib). */
    public function settings(TimetableBoard $board)
    {
        return response()->json([
            'institution_name' => $board->institution_name ?: $board->faculty_name,
            'academic_year'    => $board->academic_year,
            'days'             => (int) $board->days,
            'pairs_per_day'    => (int) $board->pairs_per_day,
            'weeks'            => (int) $board->weeks,
            'day_names'        => $board->day_names ?: array_slice(TimetableBoard::DEFAULT_DAY_NAMES, 0, (int) $board->days),
            'bell_schedule'    => $board->bell_schedule ?: TimetableBoard::defaultBellSchedule((int) $board->pairs_per_day),
            'settings'         => $board->settings ?: ['days_off' => ['Yakshanba'], 'allow_zero' => false, 'show_day_number' => false],
        ]);
    }

    /**
     * Sozlamalarni saqlash. Qo'ng'iroqlar jadvalidagi "pair" (juftlik) elementlar
     * soni kuniga para soni sifatida saqlanadi; panjaradan tashqarida qolgan
     * joylashuvlar bo'shatiladi.
     */
    public function saveSettings(Request $request, TimetableBoard $board)
    {
        $data = $request->validate([
            'institution_name'      => 'nullable|string|max:255',
            'days'                  => 'required|integer|min:1|max:7',
            'day_names'             => 'nullable|array',
            'day_names.*'           => 'nullable|string|max:40',
            'bell_schedule'         => 'required|array|min:1',
            'bell_schedule.*.type'  => 'required|in:pair,break',
            'bell_schedule.*.name'  => 'nullable|string|max:40',
            'bell_schedule.*.abbr'  => 'nullable|string|max:15',
            'bell_schedule.*.start' => 'nullable|string|max:5',
            'bell_schedule.*.end'   => 'nullable|string|max:5',
            'bell_schedule.*.print' => 'nullable|boolean',
            'settings'              => 'nullable|array',
        ]);

        // Juftliklarni qayta raqamlaymiz; para soni = "pair" elementlar soni
        $pairNo = 0;
        $schedule = array_map(function ($it) use (&$pairNo) {
            $type = $it['type'] === 'pair' ? 'pair' : 'break';
            return [
                'type'  => $type,
                'no'    => $type === 'pair' ? ++$pairNo : null,
                'name'  => trim((string) ($it['name'] ?? '')) ?: ($type === 'pair' ? $pairNo . '-para' : 'Tanaffus'),
                'abbr'  => trim((string) ($it['abbr'] ?? '')),
                'start' => trim((string) ($it['start'] ?? '')),
                'end'   => trim((string) ($it['end'] ?? '')),
                'print' => (bool) ($it['print'] ?? true),
            ];
        }, $data['bell_schedule']);

        $pairsPerDay = max(1, $pairNo);

        $board->update([
            'institution_name' => $data['institution_name'] ?? null,
            'days'             => $data['days'],
            'pairs_per_day'    => $pairsPerDay,
            'day_names'        => array_values(array_slice($data['day_names'] ?? TimetableBoard::DEFAULT_DAY_NAMES, 0, $data['days'])),
            'bell_schedule'    => $schedule,
            'settings'         => $data['settings'] ?? $board->settings,
        ]);

        // Yangi o'lchamdan tashqarida qolgan joylashuvlarni bo'shatamiz
        TimetableCard::where('board_id', $board->id)
            ->where(function ($q) use ($data, $pairsPerDay) {
                $q->where('day', '>', $data['days'])->orWhere('pair', '>', $pairsPerDay);
            })
            ->update(['day' => null, 'pair' => null]);

        return response()->json(['ok' => true, 'pairs_per_day' => $pairsPerDay]);
    }
}
