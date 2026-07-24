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
use App\Models\TimetableSubjectSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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
     * Kartochkalarni QAYTA YARATMASDAN fan nomlarini ishchi rejadagi joriy
     * nomga yangilash (joylashuvlar saqlanadi). Moslashtirish yo'nalish+kurs+
     * normallashtirilgan fan nomi bo'yicha — masalan "Biokimyo 1,2" va
     * "Biokimyo" bir xil normaga tushadi, shuning uchun nom yangilanadi.
     */
    public function refreshSubjectNames(TimetableBoard $board)
    {
        $start = (int) substr($board->academic_year, 0, 4);
        $parityRem = $board->semester_parity === 'kuzgi' ? 1 : 0;

        // Eng so'nggi tahrirlangan avval — bir xil normadagi (mas. "Biokimyo" va
        // "Biokimyo 1,2") dublikatlarda foydalanuvchining oxirgi tahriri (yangi
        // nom) ustuvor bo'lsin.
        $subjects = DB::table('manual_curriculum_subjects as s')
            ->join('manual_curricula as mc', 'mc.id', '=', 's.manual_curriculum_id')
            ->where('mc.type', 'ishchi')
            ->whereNotNull('s.semester')
            ->whereRaw('MOD(s.semester, 2) = ?', [$parityRem])
            ->whereRaw("(CAST(SUBSTRING(mc.plan_year, 1, 4) AS UNSIGNED) + GREATEST(CAST(mc.level_code AS UNSIGNED) - 10, 0) - 1) = ?", [$start])
            ->orderByDesc('s.updated_at')
            ->orderByDesc('s.id')
            ->get(['mc.specialty_name', 'mc.level_code', 's.subject_name']);

        // Kalit: specKey|kurs|normFan => joriy ko'rinadigan nom (birinchi = eng yangi)
        $map = [];
        foreach ($subjects as $s) {
            $course = (int) $s->level_code >= 11 ? (int) $s->level_code - 10 : (int) $s->level_code;
            $key = $this->specKey($s->specialty_name) . '|' . $course . '|' . $this->normSubject((string) $s->subject_name);
            if (!isset($map[$key])) {
                $map[$key] = $s->subject_name;
            }
        }

        // Kafedra xaritasi ham yangilanadi (nom o'zgarsa kafedra ham to'g'rilansin)
        [$kafMap, $overrides] = $this->buildKafedraMap();

        $updated = 0;
        $touched = [];
        foreach (TimetableCard::where('board_id', $board->id)->get() as $c) {
            $key = $this->specKey($c->specialty_name) . '|' . (int) $c->course . '|' . $this->normSubject((string) $c->subject_name);
            $new = $map[$key] ?? null;
            if ($new === null || $new === $c->subject_name) {
                continue;
            }
            $c->subject_name = $new;
            $c->kafedra_name = $this->kafedraFor($overrides, $kafMap, $new) ?: $c->kafedra_name;
            $touched[] = $c;
            $updated++;
        }

        DB::transaction(function () use ($touched) {
            foreach ($touched as $c) {
                $c->save();
            }
        });

        return response()->json(['ok' => true, 'updated' => $updated]);
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

        // Panjaradan tashqarida qolgan joylashuvlarni bo'shatamiz. Yarim-slot (pair)
        // soni doska qo'ng'iroq jadvalidan olinadi, shuning uchun kunlar chegarasi bilan
        // birga o'sha son bo'yicha tozalanadi (yo'nalish pairs_per_day emas).
        $boardPairs = $board->pairCount();
        TimetableCard::where('board_id', $board->id)
            ->where('specialty_name', $data['specialty_name'])
            ->where('course', $data['course'])
            ->where(function ($q) use ($data, $boardPairs) {
                $q->where('day', '>', $data['days'])->orWhere('pair', '>', $boardPairs);
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
            'specialty_name'    => 'nullable|string|max:255',
            'course'            => 'nullable|integer|min:1|max:7',
            // Ko'p tanlovli qamrov (dropdown checkboxlaridan) — fakultet/yo'nalish/kurs massivlari
            'faculty_names'     => 'nullable|array',
            'faculty_names.*'   => 'nullable|string|max:255',
            'specialty_names'   => 'nullable|array',
            'specialty_names.*' => 'string|max:255',
            'courses'           => 'nullable|array',
            'courses.*'         => 'integer|min:1|max:7',
            'reset'          => 'nullable|boolean',
            'assign_rooms'   => 'nullable|boolean',
            'lecture_rooms'  => 'nullable|boolean',
            'training_type'  => 'nullable|in:lecture,practice',
        ]);
        // Qamrov to'plamlari: fakultet / yo'nalish / kurs (massiv yoki eski yakka param)
        [$facSet, $specSet, $courseSet] = $this->scopeSets($data);
        $inScope = function ($c) use ($facSet, $specSet, $courseSet) {
            if ($facSet !== null && !isset($facSet[(string) ($c->faculty_name ?? '')])) return false;
            if ($specSet !== null && !isset($specSet[(string) $c->specialty_name])) return false;
            if ($courseSet !== null && !isset($courseSet[(int) $c->course])) return false;
            return true;
        };
        $scopeType = $data['training_type'] ?? null;   // faqat ma'ruza yoki faqat amaliy
        $reset = (bool) ($data['reset'] ?? false);
        $assignRooms = (bool) ($data['assign_rooms'] ?? false);
        // Ma'ruza xonalari: ma'ruzalarga faqat ma'ruza tipidagi auditoriyalarni
        // to'qnashuvsiz biriktirish
        $lectureRooms = (bool) ($data['lecture_rooms'] ?? false);

        // Sozlamalar: bir fanning haftalik paralarini bir kunga / ketma-ket qo'yish
        $set = $board->settings ?? [];
        $sameDay = (bool) ($set['pair_same_day'] ?? false);
        $consecutive = (bool) ($set['pair_consecutive'] ?? false);
        // Auditoriya sig'imi toleransi (%) — oqim xona sig'imidan biroz katta
        // bo'lsa ham joylashtirishga ruxsat (mas. 120 o'rin — 125 oqim, 5%).
        // Katta farq (mas. 80 xona — 120 oqim) baribir rad etiladi.
        $roomTolPct = max(0, min(30, (int) ($set['room_tolerance_pct'] ?? 5)));
        $minVolFor = fn(TimetableCard $c) => (int) ceil((int) $c->students * (100 - $roomTolPct) / 100);

        // Reset — tanlangan qamrovdagi mavjud joylashuvlarni bo'shatamiz.
        // Qamrov: tanlangan fakultet/yo'nalish/kurs to'plamlari bo'yicha; hech
        // biri berilmasa — butun doska.
        if ($reset) {
            $q = TimetableCard::where('board_id', $board->id);
            $this->applyScopeToQuery($q, $facSet, $specSet, $courseSet);
            if ($scopeType !== null) {
                $q->where('training_type', $scopeType);
            }
            $q->update(['day' => null, 'pair' => null, 'auditorium_code' => null, 'auditorium_name' => null]);
        }

        // Panjara o'lchamlari (yo'nalish+kurs bo'yicha)
        $gridSettings = TimetableGridSetting::where('board_id', $board->id)->get()
            ->keyBy(fn($g) => $g->specialty_name . '|' . $g->course);
        // Yarim-slot (grid qatori) soni butun doska bo'yicha qo'ng'iroq jadvalidan
        // olinadi (bir "pair" = bir yarim-slot); yo'nalish bo'yicha faqat kun soni
        // (days) farq qilishi mumkin.
        $boardPairs = $board->pairCount();
        $dimsFor = function ($spec, $course) use ($gridSettings, $board, $boardPairs) {
            $g = $gridSettings[$spec . '|' . $course] ?? null;
            return [(int) ($g->days ?? $board->days), $boardPairs];
        };

        $all = TimetableCard::where('board_id', $board->id)->get();

        // Sikl (4-6 kurs) fanlari HAFTALIK panjaraga tushmaydi — ular sikl
        // kalendarida ketma-ket kunli blok bo'lib turadi (bir guruh N kun bir
        // fan). Shu sababli sikl fanlarini panjaradan bo'shatamiz va avtomatik
        // joylashda o'tkazib yuboramiz (aks holda para-para sochilib ketadi).
        $cycleSubjKeys = [];
        if (Schema::hasTable('timetable_subject_settings')) {
            foreach (TimetableSubjectSetting::where('board_id', $board->id)->where('mode', 'cycle')->get() as $s) {
                $cycleSubjKeys[mb_strtolower(trim((string) $s->specialty_name)) . '|' . (int) $s->course . '|' . mb_strtolower(trim((string) $s->subject_name))] = true;
            }
        }
        $isCycle = fn(TimetableCard $c) => isset($cycleSubjKeys[
            mb_strtolower(trim((string) $c->specialty_name)) . '|' . (int) $c->course . '|' . mb_strtolower(trim((string) $c->subject_name))
        ]);
        if (!empty($cycleSubjKeys)) {
            $cycleIds = $all->filter(fn($c) => $isCycle($c) && ($c->day || $c->pair) && $inScope($c))->pluck('id');
            if ($cycleIds->isNotEmpty()) {
                TimetableCard::whereIn('id', $cycleIds)
                    ->update(['day' => null, 'pair' => null, 'auditorium_code' => null, 'auditorium_name' => null]);
                foreach ($all as $c) {
                    if ($isCycle($c)) {
                        $c->day = null;
                        $c->pair = null;
                    }
                }
            }
        }

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
        $rooms = ($assignRooms || $lectureRooms)
            ? Auditorium::where('active', true)->orderBy('volume')->get(['code', 'name', 'volume', 'auditorium_type_name'])
            : collect();
        // Ma'ruza xonalari havzasi — tipida "ma'ruza" bo'lganlar (topilmasa — hammasi)
        $lecRooms = collect();
        if ($lectureRooms) {
            $lecRooms = $rooms->filter(fn($r) => mb_stripos((string) ($r->auditorium_type_name ?? ''), 'ruza') !== false)->values();
            if ($lecRooms->isEmpty()) {
                $lecRooms = $rooms;
            }
        }
        // Kartaga mos xona havzasini tanlash (ma'ruza → ma'ruza xonalari)
        $poolFor = function (TimetableCard $c) use ($assignRooms, $lectureRooms, $rooms, $lecRooms) {
            if ($lectureRooms && $c->training_type === 'lecture') {
                return $lecRooms;
            }
            return $assignRooms ? $rooms : collect();
        };

        // Joylanadigan kartalar — qamrovdagi bo'sh (joylashmagan)lar.
        // Sikl fanlari o'tkazib yuboriladi (ular panjaraga tushmaydi).
        $toPlace = $all->filter(function ($c) use ($scopeType, $inScope, $isCycle) {
            if ($c->day && $c->pair) {
                return false;
            }
            if ($isCycle($c)) {
                return false;
            }
            if ($scopeType !== null && $c->training_type !== $scopeType) {
                return false;
            }
            return $inScope($c);
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

        $subjDay = [];    // "spreadKey|day" => count (fan taqsimoti uchun)
        $subjSlots = [];  // "spreadKey" => [[day,pair],...] (klaster: bir kun/ketma-ket)
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

            $need = $this->parasNeeded($c);
            for ($d = 1; $d <= $days; $d++) {
                for ($p = 1; $p <= $pairs - $need + 1; $p++) {
                    // Qattiq: dars egallaydigan barcha paralar bo'sh bo'lishi kerak
                    $freeAll = true;
                    for ($i = 0; $i < $need; $i++) {
                        $gk = $c->specialty_name . '|' . $c->course . '|' . $d . '|' . ($p + $i);
                        if (!empty($groupBusy[$gk]) && array_intersect($groups, $groupBusy[$gk])) {
                            $freeAll = false;
                            break;
                        }
                        if ($c->teacher_id && !empty($teacherBusy[$c->teacher_id . '|' . $d . '|' . ($p + $i)])) {
                            $freeAll = false;
                            break;
                        }
                    }
                    if (!$freeAll) {
                        continue;
                    }
                    // Qattiq: auditoriya (sig'im yetarli + barcha paralarda bo'sh) —
                    // kartaga mos havzadan (ma'ruza → ma'ruza xonalari), to'qnashuvsiz.
                    $room = null;
                    $pool = $poolFor($c);
                    if ($pool->isNotEmpty()) {
                        foreach ($pool as $r) {
                            if ((int) ($r->volume ?? 0) < $minVolFor($c)) {
                                continue; // sig'im yetmaydi (tolerans hisobga olingan)
                            }
                            $roomFree = true;
                            for ($i = 0; $i < $need; $i++) {
                                if (!empty($roomBusy[$r->code . '|' . $d . '|' . ($p + $i)])) {
                                    $roomFree = false;
                                    break;
                                }
                            }
                            if (!$roomFree) {
                                continue;
                            }
                            $room = $r;
                            break; // sig'imi yetadigan eng kichik bo'sh xona
                        }
                        if (!$room) {
                            continue; // bu katakka mos bo'sh xona yo'q — boshqa katak
                        }
                    }
                    // Yumshoq jarima
                    $pen = $this->slotPenalty($c, $groups, $d, $p, $pairs, $groupBusy, $subjDay, $sameDay, $consecutive, $subjSlots);
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
            $c->start_half = 0;   // avto-joylash para chegarasidan boshlaydi
            if ($bestRoom) {
                $c->auditorium_code = $bestRoom->code;
                $c->auditorium_name = $bestRoom->name;
                $roomsAssigned++;
            }
            $this->markBusy($groupBusy, $teacherBusy, $roomBusy, $c);
            $skBase = $this->spreadKey($c);
            $subjDay[$skBase . '|' . $d] = ($subjDay[$skBase . '|' . $d] ?? 0) + 1;
            $subjSlots[$skBase][] = [$d, $p];
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

    /**
     * Dars egallaydigan yarim-slotlar soni. Bu modelda `pair` — yarim-slot
     * indeksi (grid qatori), shuning uchun dars len_half ta ketma-ket yarim-slotni
     * egallaydi (2 = to'liq para = 2 soat).
     */
    private function parasNeeded(TimetableCard $c): int
    {
        return $c->lenHalf();
    }

    /**
     * Joylashuvlarni bo'shatish (kartochkalarni panelga qaytarish) — qamrov
     * bo'yicha: yo'nalish+kurs / kurs (barcha yo'nalishlar) / butun doska.
     * Kartochkalar o'chirilmaydi; faqat kun/para/auditoriya tozalanadi.
     */
    public function unplaceAll(Request $request, TimetableBoard $board)
    {
        $data = $request->validate([
            'specialty_name'    => 'nullable|string|max:255',
            'course'            => 'nullable|integer|min:1|max:7',
            'faculty_names'     => 'nullable|array',
            'faculty_names.*'   => 'nullable|string|max:255',
            'specialty_names'   => 'nullable|array',
            'specialty_names.*' => 'string|max:255',
            'courses'           => 'nullable|array',
            'courses.*'         => 'integer|min:1|max:7',
            'training_type'  => 'nullable|in:lecture,practice',
        ]);

        [$facSet, $specSet, $courseSet] = $this->scopeSets($data);
        $q = TimetableCard::where('board_id', $board->id)
            ->where(function ($w) { $w->whereNotNull('day')->orWhereNotNull('pair'); });
        $this->applyScopeToQuery($q, $facSet, $specSet, $courseSet);
        if (!empty($data['training_type'])) {
            $q->where('training_type', $data['training_type']);
        }

        $count = (clone $q)->count();
        $q->update(['day' => null, 'pair' => null, 'auditorium_code' => null, 'auditorium_name' => null]);

        return response()->json(['ok' => true, 'unplaced' => $count]);
    }

    /**
     * Qamrov to'plamlarini so'rov ma'lumotidan tuzadi: fakultet/yo'nalish/kurs
     * massivlari (dropdown checkboxlaridan). Massivlar berilmasa eski yakka
     * specialty_name/course parametrlari bilan moslashadi. Qaytadi:
     * [facSet|null, specSet|null, courseSet|null] — har biri array_flip xarita
     * (yoki cheklovsizlik uchun null).
     */
    private function scopeSets(array $data): array
    {
        $facs = $data['faculty_names'] ?? null;
        $specs = $data['specialty_names'] ?? null;
        $courses = isset($data['courses']) ? array_map('intval', (array) $data['courses']) : null;

        // Massivlar yo'q — eski yakka parametrlarga qaytamiz
        if ($facs === null && $specs === null && $courses === null) {
            if (!empty($data['specialty_name'])) {
                $specs = [$data['specialty_name']];
                $courses = isset($data['course']) ? [(int) $data['course']] : null;
            } elseif (isset($data['course'])) {
                $courses = [(int) $data['course']];
            }
        }

        return [
            $facs !== null ? array_flip(array_map('strval', $facs)) : null,
            $specs !== null ? array_flip(array_map('strval', $specs)) : null,
            $courses !== null ? array_flip($courses) : null,
        ];
    }

    /** Qamrov to'plamlarini SQL so'roviga qo'llaydi (whereIn). */
    private function applyScopeToQuery($q, ?array $facSet, ?array $specSet, ?array $courseSet): void
    {
        if ($specSet !== null) {
            $q->whereIn('specialty_name', array_keys($specSet));
        }
        if ($courseSet !== null) {
            $q->whereIn('course', array_keys($courseSet));
        }
        if ($facSet !== null) {
            $vals = array_keys($facSet);
            $q->where(function ($w) use ($vals) {
                $w->whereIn('faculty_name', $vals);
                if (in_array('', $vals, true)) {
                    $w->orWhereNull('faculty_name');
                }
            });
        }
    }

    /**
     * Katakni band deb belgilash. Dars uzunligiga qarab len_half ta ketma-ket
     * yarim-slotni band qiladi (avto-joylash konfliktsiz bo'lishi uchun).
     */
    private function markBusy(array &$groupBusy, array &$teacherBusy, array &$roomBusy, TimetableCard $c): void
    {
        $need = $this->parasNeeded($c);
        for ($i = 0; $i < $need; $i++) {
            $p = (int) $c->pair + $i;
            $k = $c->specialty_name . '|' . $c->course . '|' . $c->day . '|' . $p;
            $groupBusy[$k] = array_merge($groupBusy[$k] ?? [], $c->occupiedGroups());
            if ($c->teacher_id) {
                $teacherBusy[$c->teacher_id . '|' . $c->day . '|' . $p] = true;
            }
            if ($c->auditorium_code) {
                $roomBusy[$c->auditorium_code . '|' . $c->day . '|' . $p] = true;
            }
        }
    }

    /** Fan taqsimoti kaliti: ma'ruza — oqim bo'yicha, amaliyot — guruhcha bo'yicha. */
    private function spreadKey(TimetableCard $c): string
    {
        $who = $c->training_type === 'lecture' ? ('L' . $c->oqim_label) : ('P' . $c->group_name);
        return $c->specialty_name . '|' . $c->course . '|' . $who . '|' . $this->normSubject((string) $c->subject_name);
    }

    /**
     * Katak jarimasi: oyna + fan taqsimoti + kun yuki + ertalab ustunligi.
     * $sameDay/$consecutive — sozlamalar: bir fanning paralarini bir kunga /
     * ketma-ket joylash (default — kunlar bo'ylab yoyish).
     */
    private function slotPenalty(TimetableCard $c, array $groups, int $d, int $p, int $pairs, array $groupBusy, array $subjDay, bool $sameDay = false, bool $consecutive = false, array $subjSlots = []): float
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

        $skBase = $this->spreadKey($c);
        if ($sameDay || $consecutive) {
            // Klaster rejimi: shu fan-guruhning oldin joylashgan paralari bilan
            // bir kun / ketma-ket bo'lishni rag'batlantiramiz.
            $slots = $subjSlots[$skBase] ?? [];
            $onSameDay = 0;
            $adjacent = false;
            foreach ($slots as [$sd, $sp]) {
                if ($sd === $d) {
                    $onSameDay++;
                    if (abs($sp - $p) === 1) {
                        $adjacent = true;
                    }
                }
            }
            if ($sameDay) {
                $pen -= $onSameDay * 25;                          // shu kunda — mukofot
                if (!empty($slots) && $onSameDay === 0) {
                    $pen += 15;                                   // yangi kun ochish — jarima
                }
            }
            if ($consecutive && !empty($slots)) {
                $pen += $adjacent ? -35 : 12;                    // ketma-ket bo'lsa — mukofot
            }
        } else {
            $pen += ($subjDay[$skBase . '|' . $d] ?? 0) * 6;      // default: kunlar bo'ylab yoyish
        }
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
            'start_half' => (int) ($c->start_half ?? 0),
            'len_half' => $c->lenHalf(),
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
            'subject_settings' => $this->subjectSettingsFor($board),
        ]);
    }

    /** Doskaning fan-rejim sozlamalari (hafta almashinuvi / sikl) — frontend uchun. */
    private function subjectSettingsFor(TimetableBoard $board): array
    {
        if (!Schema::hasTable('timetable_subject_settings')) {
            return [];
        }
        return TimetableSubjectSetting::where('board_id', $board->id)
            ->get(['specialty_name', 'course', 'subject_name', 'mode', 'rotation_group', 'occurrences', 'cycle_days'])
            ->map(fn($s) => [
                'specialty_name' => $s->specialty_name,
                'course'         => (int) $s->course,
                'subject_name'   => $s->subject_name,
                'mode'           => $s->mode,
                'rotation_group' => $s->rotation_group,
                'occurrences'    => $s->occurrences !== null ? (int) $s->occurrences : null,
                'cycle_days'     => $s->cycle_days !== null ? (int) $s->cycle_days : null,
            ])->all();
    }

    /**
     * Fan bo'yicha jadval rejimini saqlash (hafta almashinuvi / sikl).
     * normal rejim (barcha yordamchi maydonlar bo'sh) — yozuv o'chiriladi.
     */
    public function saveSubjectSetting(Request $request, TimetableBoard $board)
    {
        $data = $request->validate([
            'specialty_name' => 'required|string|max:255',
            'course'         => 'required|integer|min:1|max:7',
            'subject_name'   => 'required|string|max:255',
            'mode'           => 'required|in:normal,alternate,cycle',
            'rotation_group' => 'nullable|string|max:255',
            'occurrences'    => 'nullable|integer|min:1|max:60',
            'cycle_days'     => 'nullable|integer|min:1|max:120',
        ]);

        // Mavjud yozuvni katta-kichik harfga befarq topamiz — reja (mc) nomi
        // "Davolash ishi", karta/snapshot nomi "davolash ishi" bo'lishi mumkin;
        // dublikat yaratmaslik uchun mavjudini yangilaymiz.
        $existing = TimetableSubjectSetting::where('board_id', $board->id)
            ->where('course', (int) $data['course'])
            ->whereRaw('LOWER(TRIM(specialty_name)) = ?', [mb_strtolower(trim($data['specialty_name']))])
            ->whereRaw('LOWER(TRIM(subject_name)) = ?', [mb_strtolower(trim($data['subject_name']))])
            ->first();

        // normal — sozlama shart emas, mavjud yozuvni o'chiramiz
        if ($data['mode'] === 'normal') {
            if ($existing) {
                $existing->delete();
            }
            return response()->json(['ok' => true, 'mode' => 'normal']);
        }

        $values = [
            'mode'           => $data['mode'],
            'rotation_group' => $data['mode'] === 'alternate' ? ($data['rotation_group'] ?? null) : null,
            'occurrences'    => $data['mode'] === 'alternate' ? ($data['occurrences'] ?? null) : null,
            'cycle_days'     => $data['mode'] === 'cycle' ? ($data['cycle_days'] ?? null) : null,
        ];
        if ($existing) {
            $existing->update($values);
        } else {
            TimetableSubjectSetting::create(array_merge([
                'board_id'       => $board->id,
                'specialty_name' => $data['specialty_name'],
                'course'         => (int) $data['course'],
                'subject_name'   => $data['subject_name'],
            ], $values));
        }

        return response()->json(['ok' => true, 'mode' => $data['mode']]);
    }

    /** Guruhcha nomidan asosiy guruh (oxirgi kichik harf — a/b/c — olib tashlanadi). */
    private function baseGroup(string $gn): string
    {
        // "p22-02a" → "p22-02", "d1/23 10a" → "d1/23 10" (raqamdan keyingi bitta harf)
        return preg_replace('/([0-9])\s*[a-z]$/iu', '$1', trim($gn));
    }

    /**
     * Sikl (4-6 kurs) kalendar rejasi: sana × guruh, har guruh o'z sikl fanlarini
     * ketma-ket blok qilib o'taydi (guruhlar surilib — rotatsiya). Birlik: o'quv kuni.
     * Bu — birinchi versiya: bloklar ketma-ket, guruh indeksi bo'yicha aylantiriladi.
     */
    public function cyclePlan(Request $request, TimetableBoard $board)
    {
        $data = $request->validate([
            'start_date'        => 'nullable|date',
            'faculty_names'     => 'nullable|array',
            'faculty_names.*'   => 'nullable|string|max:255',
            'specialty_names'   => 'nullable|array',
            'specialty_names.*' => 'string|max:255',
            'courses'           => 'nullable|array',
            'courses.*'         => 'integer|min:1|max:7',
        ]);
        [$facSet, $specSet, $courseSet] = $this->scopeSets($data);
        $inScope = function ($c) use ($facSet, $specSet, $courseSet) {
            if ($facSet !== null && !isset($facSet[(string) ($c->faculty_name ?? '')])) return false;
            if ($specSet !== null && !isset($specSet[(string) $c->specialty_name])) return false;
            if ($courseSet !== null && !isset($courseSet[(int) $c->course])) return false;
            return true;
        };

        // Semestr boshlanish sanasi (so'rovdan / sozlamadan / o'quv yilidan)
        $set = $board->settings ?? [];
        $start = $data['start_date'] ?? ($set['semester_start'] ?? null);
        if (!$start) {
            $yearStart = (int) preg_replace('/\D.*$/', '', (string) $board->academic_year);
            if ($yearStart < 2000) {
                $yearStart = (int) date('Y');
            }
            $start = $board->semester_parity === 'bahorgi'
                ? sprintf('%04d-02-01', $yearStart + 1)
                : sprintf('%04d-09-01', $yearStart);
        }
        $startC = Carbon::parse($start)->startOfDay();
        if (($set['semester_start'] ?? null) !== $startC->toDateString()) {
            $set['semester_start'] = $startC->toDateString();
            $board->update(['settings' => $set]);
        }

        // O'quv kunlari kalendari: haftasiga board->days ta ish kuni (Dush=1..),
        // yakshanba (va board->days dan keyingi kunlar) o'tkazib yuboriladi.
        $D = max(1, (int) $board->days);
        $W = max(1, (int) $board->weeks);
        $dates = [];
        $cur = $startC->copy();
        $guard = 0;
        while (count($dates) < $W * $D && $guard < $W * 7 + 30) {
            if ((int) $cur->dayOfWeekIso <= $D) {
                $dates[] = $cur->copy();
            }
            $cur->addDay();
            $guard++;
        }
        $totalDays = count($dates);

        // Nomlarni normallashtiramiz — reja (mc) va karta/snapshot nomlari katta-kichik
        // harf/bo'shliqda farq qilishi mumkin (mas. "Davolash ishi" ↔ "davolash ishi").
        $normKey = fn($spec, $course, $subj) =>
            mb_strtolower(trim((string) $spec)) . '|' . (int) $course . '|' . mb_strtolower(trim((string) $subj));

        // Sikl fanlari: normalized(spec|course|subject) => cycle_days
        // Barcha sikl sozlamalarini (doska bo'yicha) olamiz — qamrovni kartalar
        // (inScope) o'zi cheklaydi, shu sabab bu yerda scope filtri shart emas.
        $cycleKey = [];
        if (Schema::hasTable('timetable_subject_settings')) {
            foreach (TimetableSubjectSetting::where('board_id', $board->id)->where('mode', 'cycle')->get() as $s) {
                $cycleKey[$normKey($s->specialty_name, $s->course, $s->subject_name)] = max(1, (int) ($s->cycle_days ?? 1));
            }
        }

        // Guruhlar (asosiy guruh bo'yicha) va ularning sikl fanlari — kartalardan
        $byGroup = [];
        if (!empty($cycleKey)) {
            foreach (TimetableCard::where('board_id', $board->id)->get() as $c) {
                if ($c->training_type !== 'practice' || !$c->group_name || !$inScope($c)) {
                    continue;
                }
                $ck = $normKey($c->specialty_name, $c->course, $c->subject_name);
                if (!isset($cycleKey[$ck])) {
                    continue;
                }
                $base = $this->baseGroup($c->group_name);
                if (!isset($byGroup[$base])) {
                    $byGroup[$base] = ['name' => $base, 'subs' => [], 'members' => [],
                        'faculty' => $c->faculty_name, 'specialty' => $c->specialty_name, 'course' => (int) $c->course];
                }
                $byGroup[$base]['subs'][$c->subject_name] = $cycleKey[$ck];
                $byGroup[$base]['members'][$c->group_name] = true;
            }
        }

        // Fanlar global tartibi (nomi bo'yicha) — rotatsiya uchun
        $allSubs = [];
        foreach ($byGroup as $g) {
            foreach ($g['subs'] as $sn => $dd) {
                $allSubs[$sn] = $dd;
            }
        }
        ksort($allSubs);
        $subOrder = array_keys($allSubs);

        $groups = array_values($byGroup);
        usort($groups, fn($a, $b) => strnatcmp($a['name'], $b['name']));

        $rows = [];
        foreach ($groups as $gi => $g) {
            // Shu guruh fanlari, global tartibda, guruh indeksi bo'yicha aylantirilgan
            $order = array_merge(array_slice($subOrder, $gi % max(1, count($subOrder))),
                                 array_slice($subOrder, 0, $gi % max(1, count($subOrder))));
            $blocks = [];
            $idx = 0;
            foreach ($order as $sn) {
                if (!isset($g['subs'][$sn])) {
                    continue;
                }
                if ($idx >= $totalDays) {
                    break;
                }
                $days = max(1, (int) $g['subs'][$sn]);
                $to = min($idx + $days - 1, $totalDays - 1);
                $blocks[] = ['subject' => $sn, 'from' => $idx, 'to' => $to, 'days' => $to - $idx + 1];
                $idx = $to + 1;
            }
            $members = array_keys($g['members']);
            sort($members, SORT_NATURAL);
            $rows[] = [
                'group'     => $g['name'],
                'subgroups' => $members,
                'faculty'   => $g['faculty'],
                'specialty' => $g['specialty'],
                'course'    => $g['course'],
                'blocks'    => $blocks,
            ];
        }

        return response()->json([
            'start_date' => $startC->toDateString(),
            'total_days' => $totalDays,
            'dates'      => array_map(fn($d) => ['d' => $d->format('d.m'), 'iso' => $d->toDateString(), 'dow' => (int) $d->dayOfWeekIso], $dates),
            'subjects'   => array_map(fn($sn) => ['name' => $sn, 'days' => $allSubs[$sn]], $subOrder),
            'rows'       => $rows,
        ]);
    }

    /**
     * Ekrandagi Excel jadvalini (HTML) haqiqiy .xlsx faylga aylantirib beradi.
     * Shu bilan Excel "fayl formati kengaytmага mos emas" ogohlantirishi chiqmaydi.
     * Kataklar ranglari inline (hex) — PhpSpreadsheet HTML o'quvchisi ularni saqlaydi;
     * birlashtirilgan kataklar colspan/rowspan orqali merge bo'ladi.
     */
    public function excelExport(Request $request, TimetableBoard $board)
    {
        $data = $request->validate([
            'html'     => 'required|string',
            'filename' => 'nullable|string|max:150',
        ]);

        @ini_set('memory_limit', '512M');
        @set_time_limit(120);

        $base = preg_replace('/[^\w\-]+/u', '_', (string) ($data['filename'] ?? 'dars-jadvali')) ?: 'dars-jadvali';
        $tmp = tempnam(sys_get_temp_dir(), 'ttx');
        $tmpHtml = $tmp . '.html';
        @rename($tmp, $tmpHtml);
        file_put_contents($tmpHtml, $data['html']);

        try {
            $reader = new \PhpOffice\PhpSpreadsheet\Reader\Html();
            $spreadsheet = $reader->load($tmpHtml);
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Dars jadvali');

            // Chegara chiziqlari — HTML <style> dagi border qoidasi o'quvchi
            // tomonidan qo'llanmaydi, shuning uchun butun diapazonga qo'lda beramiz.
            $dim = $sheet->calculateWorksheetDimension();
            if ($dim && strpos($dim, ':') !== false) {
                $sheet->getStyle($dim)->getBorders()->getAllBorders()
                    ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN)
                    ->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FF888888'));
                $sheet->getStyle($dim)->getAlignment()->setVertical(
                    \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER
                );
            }

            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

            return response()->streamDownload(function () use ($writer) {
                $writer->save('php://output');
            }, $base . '.xlsx', [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]);
        } catch (\Throwable $e) {
            Log::warning('Timetable excel-export xatosi: ' . $e->getMessage());
            return response()->json(['error' => 'Excel yaratishda xatolik: ' . $e->getMessage()], 500);
        } finally {
            @unlink($tmpHtml);
        }
    }

    /**
     * Hafta bo'yicha dars istisnosi: shu haftada ko'chirish / bekor qilish / shablonga qaytarish.
     * Faqat tanlangan haftaga ta'sir qiladi — boshqa haftalar shablon bo'yicha qoladi.
     */
    public function weekOverride(Request $request, TimetableCard $card)
    {
        $data = $request->validate([
            'week'       => 'required|integer|min:1|max:30',
            'action'     => 'required|in:move,cancel,reset',
            'day'        => 'nullable|integer|min:1|max:10',
            'pair'       => 'nullable|integer|min:1|max:10',
            'start_half' => 'nullable|integer|min:0|max:1',
        ]);
        $week = (int) $data['week'];
        $startHalf = (int) ($data['start_half'] ?? 0);

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
        $conflicts = $this->findWeekConflicts($card, $week, $day, $pair, $startHalf);
        if (!empty($conflicts)) {
            return response()->json(['error' => implode(' · ', $conflicts)], 422);
        }
        TimetableCardOverride::updateOrCreate(
            ['card_id' => $card->id, 'week' => $week],
            ['day' => $day, 'pair' => $pair, 'start_half' => $startHalf, 'cancelled' => false]
        );
        return response()->json(['ok' => true]);
    }

    /** Tanlangan haftadagi effektiv joylashuvlar bo'yicha konflikt tekshiruvi (yarim-slot oralig'i). */
    private function findWeekConflicts(TimetableCard $card, int $week, int $day, int $pair, int $startHalf = 0): array
    {
        $ovr = TimetableCardOverride::whereHas('card', fn($q) => $q->where('board_id', $card->board_id))
            ->where('week', $week)->get()->keyBy('card_id');
        $others = TimetableCard::where('board_id', $card->board_id)->where('id', '!=', $card->id)->get();

        $myRange = $this->rangeFor($card, $pair, $startHalf);
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
                $osh = (int) ($ov->start_half ?? 0);
            } else {
                $od = $o->day;
                $op = $o->pair;
                $osh = (int) ($o->start_half ?? 0);
            }
            if (!$od || !$op || (int) $od !== $day) {
                continue;
            }
            if (!$this->halfOverlap($myRange, $this->rangeFor($o, (int) $op, $osh))) {
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
            // Yarim-slot soni doska qo'ng'iroq jadvalidan (yo'nalish bo'yicha bir xil)
            'pairs' => $board->pairCount(),
        ];
    }

    /** Kartochkani joylash/ko'chirish/olib tashlash — konflikt tekshiruvi bilan. */
    public function placeCard(Request $request, TimetableCard $card)
    {
        $board = $card->board;
        $grid = $this->gridFor($board, $card->specialty_name, (int) $card->course);
        $data = $request->validate([
            'day'        => 'nullable|integer|min:1|max:' . $grid['days'],
            'pair'       => 'nullable|integer|min:1|max:' . $grid['pairs'],
            'start_half' => 'nullable|integer|min:0|max:1',
        ]);

        $day = $data['day'] ?? null;
        $pair = $data['pair'] ?? null;
        $startHalf = (int) ($data['start_half'] ?? 0);

        if ($day && $pair) {
            $conflicts = $this->findConflicts($card, $day, $pair, $startHalf);
            if (!empty($conflicts)) {
                return response()->json(['error' => implode(' · ', $conflicts)], 422);
            }
        }

        $card->update([
            'day' => $day, 'pair' => $pair,
            'start_half' => $day && $pair ? $startHalf : 0,
        ]);
        return response()->json(['ok' => true]);
    }

    /** Ikki yarim-slot oralig'i kesishadimi: [a1,a2) va [b1,b2). */
    private function halfOverlap(array $a, array $b): bool
    {
        return $a[0] < $b[1] && $b[0] < $a[1];
    }

    /** Kartaning `pair` (yarim-slot) da yarim-slot oralig'i: [pair-1, pair-1+len_half). */
    private function rangeFor(TimetableCard $card, int $pair, int $startHalf = 0): array
    {
        $s = $pair - 1;
        return [$s, $s + $card->lenHalf()];
    }

    private function findConflicts(TimetableCard $card, int $day, int $pair, int $startHalf = 0): array
    {
        // Shu kundagi barcha joylashgan kartalar (para bo'yicha emas — oraliq kesishuvi bilan)
        $others = TimetableCard::where('board_id', $card->board_id)
            ->where('id', '!=', $card->id)
            ->where('day', $day)->whereNotNull('pair')
            ->get();

        $myRange = $this->rangeFor($card, $pair, $startHalf);
        $myGroups = $card->occupiedGroups();
        $errors = [];
        foreach ($others as $o) {
            $oRange = $o->halfRange();
            if (!$oRange || !$this->halfOverlap($myRange, $oRange)) {
                continue;
            }
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
            'len_half'        => 'nullable|integer|min:1|max:4',
            'start_half'      => 'nullable|integer|min:0|max:1',
        ]);

        if (array_key_exists('len_half', $data) && $data['len_half']) {
            $card->len_half = (int) $data['len_half'];
        }
        if (array_key_exists('start_half', $data) && $data['start_half'] !== null && $card->day && $card->pair) {
            $card->start_half = (int) $data['start_half'];
        }
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

        // Joylashgan bo'lsa — yangi rekvizit/uzunlik bilan konflikt tekshiramiz
        if ($card->day && $card->pair) {
            $conflicts = $this->findConflicts($card, $card->day, $card->pair, (int) ($card->start_half ?? 0));
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
            'len_half' => $card->lenHalf(),
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
