<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Auditorium;
use App\Models\OqimSnapshot;
use App\Models\Teacher;
use App\Models\TimetableBoard;
use App\Models\TimetableCard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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

    private function specKey(?string $name): string
    {
        return preg_replace('/[^a-z0-9]/u', '', mb_strtolower(trim((string) $name)));
    }

    /**
     * Kartochkalarni yaratish: tasdiqlangan oqim + ishchi reja fanlari.
     * Mavjud kartochkalar o'chirilib qaytadan yaratiladi (joylashuvlar yo'qoladi) —
     * shuning uchun UI tasdiqlash so'raydi.
     */
    public function generateCards(TimetableBoard $board)
    {
        // 1) Tasdiqlangan oqim snapshotlari (fakultet kontekstida dedup — eng so'nggisi)
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
        if (empty($byFaculty)) {
            return response()->json(['error' => "Tasdiqlangan oqim topilmadi. Avval Oqim sahifasida "
                . ($board->kind === 'plan' ? "kelasi yil (reja) oqimini" : "joriy oqimni") . " tasdiqlang."], 422);
        }

        // 2) Fanlar: o'quv yili + semestr juft/toqligi bo'yicha
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

        // Fanlarni yo'nalish+kurs bo'yicha guruhlash
        $subjBySpec = [];
        foreach ($subjects as $s) {
            $course = (int) $s->level_code >= 11 ? (int) $s->level_code - 10 : (int) $s->level_code;
            $subjBySpec[$this->specKey($s->specialty_name)][$course][] = $s;
        }

        // 3) Kartochkalar
        $weeks = max(1, (int) $board->weeks);
        $now = now();
        $rows = [];
        $paras = function ($hours) use ($weeks) {
            $h = (float) $hours;
            if ($h <= 0) {
                return 0;
            }
            return max(1, (int) round($h / $weeks / 2)); // 1 para = 2 akademik soat
        };

        foreach ($byFaculty as $snap) {
            foreach ($snap->data ?? [] as $bl) {
                $specName = trim(explode('|', $bl['merge_key'] ?? '')[1] ?? '') ?: ($bl['title'] ?? '');
                $sk = $this->specKey($specName);
                foreach ($bl['courses'] ?? [] as $co) {
                    $lvl = (int) ($co['level_code'] ?? 0);
                    $course = $lvl >= 11 ? $lvl - 10 : $lvl;
                    $subs = $subjBySpec[$sk][$course] ?? null;
                    if (!$subs) {
                        continue;
                    }
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
                            // Ma'ruza — oqimga
                            for ($i = 0; $i < $paras($s->lecture); $i++) {
                                $rows[] = [
                                    'board_id' => $board->id,
                                    'specialty_name' => $specName, 'course' => $course,
                                    'oqim_label' => $oq['label'] ?? null, 'lang' => $oq['lang'] ?? 'uz',
                                    'training_type' => 'lecture',
                                    'group_name' => null, 'group_names' => json_encode($groupNames),
                                    'subject_name' => $s->subject_name, 'kafedra_name' => $kaf,
                                    'students' => $oqTotal,
                                    'created_at' => $now, 'updated_at' => $now,
                                ];
                            }
                            // Amaliy (amaliy+lab+seminar) — har guruhchaga
                            $pw = $paras((float) $s->practice + (float) $s->laboratory + (float) $s->seminar);
                            if ($pw > 0) {
                                foreach ($oq['rows'] ?? [] as $gr) {
                                    $gn = trim((string) ($gr['name'] ?? ''));
                                    if ($gn === '') {
                                        continue;
                                    }
                                    for ($i = 0; $i < $pw; $i++) {
                                        $rows[] = [
                                            'board_id' => $board->id,
                                            'specialty_name' => $specName, 'course' => $course,
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

        DB::transaction(function () use ($board, $rows) {
            TimetableCard::where('board_id', $board->id)->delete();
            foreach (array_chunk($rows, 500) as $chunk) {
                TimetableCard::insert($chunk);
            }
        });

        return response()->json(['ok' => true, 'created' => count($rows)]);
    }

    /** Doska ma'lumotlari: barcha kartochkalar (konflikt tekshiruvi butun doska bo'ylab). */
    public function data(TimetableBoard $board)
    {
        $cards = TimetableCard::where('board_id', $board->id)->get()->map(fn($c) => [
            'id' => $c->id,
            'specialty_name' => $c->specialty_name,
            'course' => $c->course,
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

        return response()->json([
            'board' => $board->only(['id', 'name', 'days', 'pairs_per_day', 'weeks', 'academic_year', 'semester_parity', 'kind']),
            'cards' => $cards,
        ]);
    }

    /** Kartochkani joylash/ko'chirish/olib tashlash — konflikt tekshiruvi bilan. */
    public function placeCard(Request $request, TimetableCard $card)
    {
        $board = $card->board;
        $data = $request->validate([
            'day'  => 'nullable|integer|min:1|max:' . $board->days,
            'pair' => 'nullable|integer|min:1|max:' . $board->pairs_per_day,
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
            $q->orderBy('full_name')->limit(100)->get(['id', 'full_name', 'department', 'lavozim'])
        );
    }

    /** Auditoriyalar ro'yxati. */
    public function auditoriums()
    {
        return response()->json(
            Auditorium::where('active', true)->orderBy('name')
                ->get(['code', 'name', 'volume', 'auditorium_type_name', 'building_name'])
        );
    }
}
