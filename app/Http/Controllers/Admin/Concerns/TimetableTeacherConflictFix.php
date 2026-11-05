<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Models\Teacher;
use App\Models\TimetableBoard;
use App\Models\TimetableCard;
use App\Models\TimetableCardOverride;
use App\Services\Timetable\TimetableChangeApproval;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * O'qituvchi (o'qituvchi, fan mas'uli — TimetableChangeApproval::TEACHER_PROPOSAL_ROLES): to'qnashuvdagi darsini so'rovsiz joylash
 * (teacherConflictFix). Faqat o'z darsi (o'qituvchisi yoki mas'ul fani), amaliy (ma'ruza — avvalgidek faqat kafedra mudiri va adminlar),
 * bugungi va o'tgan darslar — yopiq. Shart:
 *   - dars shu haftada (haftalik) / shu kunda (sikl) JOYLANMAGAN yoki QIZIL to'qnashuvda: o'qituvchi band, guruh band, amaliyot ma'ruza bilan
 *     ustma-ust (sariq — xona band, sig'im, zalda ikki ma'ruza — hisoblanmaydi);
 *   - VA yangi joy to'liq toza: guruh, o'qituvchi, xona bo'sh, xona sig'adi, ogohlantirish va boshqa xona taklifisiz;
 *   → darhol qo'llanadi (so'rov yaratilmaydi), o'zgarishlar tarixiga "To'qnashuvni o'qituvchi hal qildi" bilan yoziladi, fan kafedrasi
 *     mudiriga xabar boradi. Aks holda — oddiy so'rov (avvalgidek). Kartada kutilayotgan so'rov bo'lsa — darhol joylash ham rad.
 * Shart har doim serverda qayta hisoblanadi (mijoz yuborgan "fix" belgisiga ishonilmaydi).
 */
trait TimetableTeacherConflictFix
{
    /** Qizil to'qnashuv turlari: o'qituvchi yoki guruh bir vaqtda ikki joyda, amaliyot ma'ruza bilan ustma-ust. */
    private static array $redConflictKinds = ['teacher_busy', 'group_busy', 'lecture_overlap'];

    /** Tarix izohi va xabar sarlavhasi. */
    public static function teacherFixNote(): string
    {
        return "To'qnashuvni o'qituvchi hal qildi";
    }

    /** O'qituvchi oqimi (faol rol — o'qituvchi yoki fan mas'uli). */
    private function teacherFixRole(Request $request): bool
    {
        return in_array($this->timetableActiveRole($request), TimetableChangeApproval::TEACHER_PROPOSAL_ROLES, true);
    }

    /** Karta foydalanuvchining o'z darsimi (o'qituvchisi yoki mas'ul fani). */
    private function teacherFixOwns(array $rights, $card): bool
    {
        return $rights['tid'] > 0 && ((int) $card->teacher_id === (int) $rights['tid'] || $this->responsibleMatch($rights['keys'], $card));
    }

    /**
     * Haftalik dars shu haftada "to'qnashuvdagi dars"mi: joylanmagan (bekor qilinmagan) yoki qizil to'qnashuvda — boshqa haftalik dars
     * bilan o'qituvchi / guruh band (weeklyConflicts) yoki sikl darsi bilan o'qituvchi band (unit-busy). Bugungi va o'tgan dars — chaqiruvchida
     * rad etiladi (suggestionApplyCheck sanasi, agenda "past").
     */
    private function weeklyLessonNeedsFix(TimetableBoard $board, TimetableCard $card, int $week): bool
    {
        $ov = TimetableCardOverride::where('card_id', $card->id)->where('week', $week)->first();
        if ($ov && $ov->cancelled) {
            return false;
        }
        $place = $ov && $ov->day && $ov->pair ? [(int) $ov->day, (int) $ov->pair] : ($card->day && $card->pair ? [(int) $card->day, (int) $card->pair] : null);
        if (!$place) {
            return true;
        }
        $card->setRelation('board', $board);
        $date = $this->weekStartDate($board, $card, $week)->addDays($place[0] - 1)->toDateString();
        foreach ((array) ($this->weeklyConflicts($board, null, [(int) $card->id => true], $week, true)[$card->id][$week]['items'] ?? []) as $it) {
            if (in_array($it['kind'] ?? '', self::$redConflictKinds, true)) {
                return true;
            }
        }
        foreach ($this->weeklyProposalBusy($card, $week, $place[0], $place[1], $date) as $m) {
            if (str_starts_with($m, "O'qituvchi band")) {
                return true;
            }
        }

        return false;
    }

    /** Bir darsning bo'laklari (bir fan — bo'lak harfisiz, amaliy, bir qamrov va guruhlar): yaxlit ko'chadi. */
    private function teacherFixSameUnit(TimetableCard $a, TimetableCard $b): bool
    {
        $base = fn (TimetableCard $c) => $this->normSubject((string) ($this->cycleSubjectVariantBase((string) $c->subject_name) ?? $c->subject_name));
        $groups = fn (TimetableCard $c) => collect($c->occupiedGroups())->map(fn ($g) => $this->groupNameKey((string) $g))->sort()->values()->all();

        return $a->training_type !== 'lecture' && $b->training_type !== 'lecture' && (string) $a->faculty_name === (string) $b->faculty_name
            && (string) $a->specialty_name === (string) $b->specialty_name && (int) $a->course === (int) $b->course
            && $base($a) === $base($b) && $groups($a) === $groups($b);
    }

    /**
     * Haftalik (Takliflar "move" amallari): o'qituvchi so'rovsiz qo'llay oladimi — null (ha) yoki sabab. Har (karta, hafta): o'z darsi,
     * amaliy, to'qnashuvdagi dars (yoki shu haftada to'qnashuvdagi darsning bo'lagi — dars yaxlit ko'chadi); yangi joy — hamma bo'lak ko'chgan
     * holatda (tranzaksiyada sinab) agendaWeekErrors bo'yicha toza, boshqa xona taklifisiz.
     */
    private function teacherConflictFixWeekly(TimetableBoard $board, array $acts, array $rights): ?string
    {
        $moves = [];
        foreach ($acts as $a) {
            $card = TimetableCard::where('board_id', $board->id)->find((int) ($a['card_id'] ?? 0));
            if (($a['op'] ?? '') !== 'move' || !$card) {
                return "So'rovsiz faqat darsni bo'sh vaqtga ko'chirish mumkin.";
            }
            if ($card->training_type === 'lecture') {
                return "Ma'ruzani surish — faqat kafedra mudiri va adminlar.";
            }
            if (!$this->teacherFixOwns($rights, $card)) {
                return 'Faqat o‘z darsingizni so‘rovsiz joylay olasiz.';
            }
            if (!empty($a['auditorium_code'])) {
                return 'Boshqa xona bilan ko‘chirish — so‘rov orqali.';
            }
            $card->setRelation('board', $board);
            $moves[] = [$card, (int) ($a['week'] ?? 0), (int) ($a['day'] ?? 0), (int) ($a['pair'] ?? 0)];
        }
        if (!$moves) {
            return "Ko'chiriladigan dars yo'q.";
        }
        $needs = [];
        foreach ($moves as [$card, $w]) {
            $needs[$card->id . '|' . $w] ??= $this->weeklyLessonNeedsFix($board, $card, $w);
        }
        foreach ($moves as [$card, $w]) {
            if (!$needs[$card->id . '|' . $w] && !collect($moves)->contains(fn ($m) => $m[1] === $w && $needs[$m[0]->id . '|' . $w]
                && $this->teacherFixSameUnit($m[0], $card))) {
                return '«' . $card->subject_name . '» (' . $w . "-hafta) joylashgan va to'qnashuvsiz.";
            }
        }
        // Amaliy blok yaxlit ko'chadi: asl joyida tegib turgan bo'laklar (bir fan, guruhlar, kun) — hammasi shu amallarda bo'lishi kerak.
        $orig = [];
        foreach ($moves as [$card, $w]) {
            $orig[$card->id . '|' . $w] = $this->suggestionPlace($card, $w);
        }
        foreach ($moves as [$card, $w]) {
            if ($why = $this->teacherFixBlockPart($board, $card, $w, $orig[$card->id . '|' . $w], $moves)) {
                return $why;
            }
        }
        $why = null;
        DB::beginTransaction();
        try {
            foreach ($moves as [$card, $w, $d, $p]) {
                TimetableCardOverride::updateOrCreate(['card_id' => $card->id, 'week' => $w], ['day' => $d, 'pair' => $p, 'start_half' => 0, 'cancelled' => false]);
            }
            foreach ($moves as [$card, $w, $d, $p]) {
                $grid = $this->cardGrid($board, $card);
                // Bitta kartani surish qoidasi (practiceSplitConflicts) o'rniga — yakuniy holatda blok yaxlitligi (eski va yangi kun):
                // bo'laklar birga ko'chganda ular bir-biriga tegib turadi, bu bo'linish emas.
                [$errors] = $d < 1 || $p < 1 || $d > (int) $grid['days'] || $p + $card->lenHalf() - 1 > (int) $grid['pairs']
                    ? [['Tanlangan vaqt jadval chegarasidan tashqarida.']] : $this->agendaWeekErrors($board, $card, $w, $d, $p, null, false);
                $errors = array_merge($errors, $this->practiceDaySplits($card, $w, array_values(array_unique(array_filter([$orig[$card->id . '|' . $w][0] ?? null, $d])))));
                if ($errors) {
                    $why = 'Yangi joy toza emas: ' . implode(' · ', array_values(array_unique($errors)));
                    break;
                }
            }
        } finally {
            DB::rollBack();
        }

        return $why;
    }

    /**
     * Ko'chiriladigan karta asl joyida amaliy blokning bir qismimi (shu kunda unga tegib yoki ustma-ust turgan bo'lak — bir fan, guruhlar)
     * va o'sha bo'lak shu amallarda ko'chmayaptimi — unda sabab (blok bo'linadi); aks holda null.
     */
    private function teacherFixBlockPart(TimetableBoard $board, TimetableCard $card, int $w, ?array $place, array $moves): ?string
    {
        if (!$place) {
            return null;
        }
        $moving = [];
        foreach ($moves as [$c, $mw]) {
            if ($mw === $w) {
                $moving[(int) $c->id] = true;
            }
        }
        $mine = $this->rangeFor($card, $place[1], (int) (TimetableCardOverride::where('card_id', $card->id)->where('week', $w)->value('start_half')
            ?? $card->start_half ?? 0));
        $sibs = TimetableCard::where('board_id', $board->id)->where('id', '!=', $card->id)->where('training_type', '!=', 'lecture')
            ->where('faculty_name', $card->faculty_name)->where('specialty_name', $card->specialty_name)->where('course', $card->course)->get()
            ->filter(fn (TimetableCard $o) => $this->teacherFixSameUnit($o, $card));
        foreach ($sibs as $o) {
            $op = $this->suggestionPlace($o, $w);
            if (!$op || $op[0] !== $place[0] || isset($moving[(int) $o->id])) {
                continue;
            }
            $r = $this->rangeFor($o, $op[1], (int) (TimetableCardOverride::where('card_id', $o->id)->where('week', $w)->value('start_half') ?? $o->start_half ?? 0));
            if ($r[1] === $mine[0] || $mine[1] === $r[0] || $this->halfOverlap($r, $mine)) {
                return '«' . $card->subject_name . '» (' . $w . "-hafta) — shu kungi amaliy blokning bir qismi («" . $o->subject_name . '» '
                    . $this->halfRangeTime($board, $r) . "): blok bo'linmasin — hamma bo'lagini birga ko'chiring.";
            }
        }

        return null;
    }

    /**
     * Sikl amaliyoti shu kunda qizil to'qnashuvdami: sikl darslari bilan (cycleConflicts, faqat shu sana — amaliyot tomoni) yoki haftalik
     * darslar bilan o'qituvchi / guruh band (hozirgi soatida). $x — cycleProposalUnits elementi. Bugungi va o'tgan kun — chaqiruvchida rad
     * etiladi (teacherProposalValidate sanasi, agenda "past").
     */
    private function cyclePracticeNeedsFix(TimetableBoard $board, array $x, string $date): bool
    {
        if (!isset($x['prac'][$date])) {
            return false;
        }
        [$units, $calendar] = $this->placedCycleUnits($board);
        foreach ((array) ($this->cycleConflicts($board, $units, $calendar, true, [$date => true])[$x['u']['key']]['items'] ?? []) as $it) {
            if (empty($it['lecture']) && in_array($it['kind'] ?? '', self::$redConflictKinds, true)) {
                return true;
            }
        }
        $k = array_search($date, $x['dates'], true);
        foreach ($this->cyclePracticeBusyErrors($board, [$x], [(string) $x['u']['unit'] => [$k => $date]], (int) $x['prac'][$date][0]) as $m) {
            if (str_starts_with($m, "O'qituvchi band") || str_starts_with($m, 'Guruh band')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Sikl amaliyoti: shu kunni so'rovsiz yozish mumkinmi — null (ha) yoki sabab. Hamma guruhcha (shu kunda amaliysi bor) o'z darsi,
     * kartalarida kutilayotgan so'rov yo'q va shu kunda to'qnashuvda. Yangi soat tozaligi (validateCyclePracticeProposal — har kun alohida)
     * chaqiruvchida tekshirilgan (cycle-time-proposal kiritmasi: teacherProposalValidate) — toza bo'lmasa so'rov ham yuborilmaydi.
     */
    private function teacherConflictFixCycleDay(TimetableBoard $board, array $units, array $p, string $date, array $rights, ?array $pending = null): ?string
    {
        if (self::cycleProposalIsLecture($p)) {
            return "Ma'ruzani surish — faqat kafedra mudiri va adminlar.";
        }
        $pending ??= $this->pendingCardRequests($board->id);
        $here = array_values(array_filter($units, fn ($x) => isset($x['prac'][$date])));
        if (!$here) {
            return 'Bu kuni amaliy dars yo‘q.';
        }
        foreach ($here as $x) {
            foreach ($x['u']['cards'] as $c) {
                if (!$this->teacherFixOwns($rights, $c)) {
                    return 'Faqat o‘z darsingizni so‘rovsiz joylay olasiz.';
                }
                if (isset($pending[(int) $c->id])) {
                    return "Bu kartaga allaqachon so'rov bor (#{$pending[(int) $c->id]['id']}, {$pending[(int) $c->id]['stage']}).";
                }
            }
            if (!$this->cyclePracticeNeedsFix($board, $x, $date)) {
                return '«' . $x['u']['subject'] . '» (' . $x['u']['group'] . ', ' . Carbon::parse($date)->format('d.m') . ") joylashgan va to'qnashuvsiz.";
            }
        }

        return null;
    }

    /**
     * Sikl amaliyoti so'rovi (cycle-time-proposal): har kun alohida — shu kunda amaliyot qizil to'qnashuvda va yangi soat toza bo'lsa — so'rovsiz
     * yoziladigan kun, aks holda so'rov kuni. Ma'ruza va o'qituvchi bo'lmagan rol — hammasi so'rov.
     * @return array{0: string[], 1: string[], 2: array<string, string>} [darhol kunlar, so'rov kunlari, so'rov kuni => sabab]
     */
    public function teacherCycleFixSplit(Request $request, TimetableBoard $board, array $units, array $data): array
    {
        $all = array_values(array_unique(array_merge(...array_values(array_map('array_values', $this->cycleProposalDays($units, $data)) ?: [[]]))));
        sort($all);
        if (self::cycleProposalIsLecture($data) || !$this->teacherFixRole($request)) {
            return [[], $all, []];
        }
        $rights = $this->suggestionRights($this->timetableActor($request));
        $pending = $this->pendingCardRequests($board->id);
        $fix = [];
        $rest = [];
        $why = [];
        foreach ($all as $d) {
            $w = $this->teacherConflictFixCycleDay($board, $units, $data, $d, $rights, $pending);
            if ($w === null) {
                $fix[] = $d;
            } else {
                $rest[] = $d;
                $why[$d] = $w;
            }
        }

        return [$fix, $rest, $why];
    }

    /**
     * Sikl amaliyoti: so'rovsiz kunlarni yozadi (bitta tranzaksiya), tarixga "To'qnashuvni o'qituvchi hal qildi" bilan yozadi va kafedra
     * mudiriga xabar beradi. $as — oqituvchi | fan_masuli. Yuborilganlar F.I.Sh.
     */
    public function teacherCycleFixApply(Request $request, TimetableBoard $board, array $units, array $data, array $dates, string $as): array
    {
        $p = ['dates' => $dates, 'date' => $dates[0], 'scope' => 'day'] + $data;
        $days = $this->cycleProposalDays($units, $p);
        $labels = $this->cycleProposalLabels($board, $units, $p);
        $svc = app(TimetableChangeApproval::class);
        $actor = $this->timetableActor($request);
        $payload = array_intersect_key($p, array_flip(['specialty_name', 'course', 'subject_name', 'group_name', 'units', 'date', 'dates', 'scope', 'start_hour']))
            + ['layer' => 'internal', 'view' => 'group', 'training_type' => 'practice'];
        $sub = Request::create('/', 'POST', $payload);
        $route = (new \Illuminate\Routing\Route('POST', '/', []))->name(self::TEACHER_CYCLE_ROUTE);
        $route->bind($sub);
        $route->setParameter('board', $board);
        $sub->setRouteResolver(fn () => $route);
        $draft = null;
        try {
            $draft = $svc->draft($sub, $actor, $board, $as);
        } catch (\Throwable $e) {
            Log::warning('[TeacherConflictFix] tarix: ' . $e->getMessage());
        }
        DB::transaction(fn () => $this->writeCyclePracticeStarts($units, $days, (int) $data['start_hour']));
        $cards = collect($units)->flatMap(fn ($x) => $x['u']['cards'])->values();
        $what = $labels['subject'] . ' · ' . $labels['groups'] . ' (amaliy, sikl): ' . ($labels['days'] ? $labels['when'] . ' → ' . $labels['new_time']
            : $labels['old'] . ' → ' . $labels['new']);
        if ($draft) {
            try {
                $draft['summary'] = $what . ' — ' . self::teacherFixNote();
                $draft['details'] = (array) ($draft['details'] ?? []) + ['flags' => [self::teacherFixNote()]]
                    + $svc->detailsFromCards(TimetableCard::whereIn('id', $cards->map(fn ($c) => (int) $c->id)->all())->get());
                $svc->record($draft);
            } catch (\Throwable $e) {
                Log::warning('[TeacherConflictFix] tarix: ' . $e->getMessage());
            }
        }

        return $this->teacherFixNotify($request, $board, $cards, $what);
    }

    /**
     * Darhol joylangandan keyin: tarix izohi ("To'qnashuvni o'qituvchi hal qildi" — TimetableAssignmentAccess yozuvga qo'shadi) va fan
     * kafedrasi mudiri(lar)iga xabar (LMS + Telegram, mavjud mexanizm). Yuborilganlar F.I.Sh.
     */
    private function teacherConflictFixDone(Request $request, TimetableBoard $board, array $acts): array
    {
        self::addHistoryNote($request, self::teacherFixNote());
        $cards = $this->suggestionCards($board, ['actions' => $acts]);
        $lines = [];
        foreach ($cards as $c) {
            foreach (array_unique(array_map(fn ($a) => (int) $a['week'], array_filter($acts, fn ($a) => (int) $a['card_id'] === (int) $c->id))) as $w) {
                $slot = $this->suggestionSlot($board, $c, $w);
                $lines[] = '«' . $c->subject_name . '» (' . implode(', ', $this->cardGroupsOf($c)) . ', ' . $w . '-hafta) → ' . $slot['text']
                    . ($slot['room'] !== '' ? ', xona ' . $slot['room'] : '');
            }
        }

        return $this->teacherFixNotify($request, $board, $cards, implode("\n", array_values(array_unique($lines))));
    }

    /** Fan kafedrasi mudiri(lar)iga xabar: kim, nima (har kafedra bir marta). Xato — faqat logda. */
    public function teacherFixNotify(Request $request, TimetableBoard $board, $cards, string $what): array
    {
        $actor = $this->timetableActor($request);
        $who = $actor instanceof Teacher ? trim((string) $actor->full_name) : '';
        $sent = [];
        foreach (collect($cards)->map(fn ($c) => $this->cardDepartmentId($c instanceof TimetableCard ? $c : TimetableCard::find($c->id)))->filter()->unique() as $dept) {
            try {
                $sent = array_merge($sent, app(TimetableChangeApproval::class)->notifyDepartmentHeadsNow((int) $dept,
                    self::teacherFixNote() . ' (so‘rovsiz)',
                    ($who !== '' ? $who . ' ' : "O'qituvchi ") . "to'qnashuvdagi darsini bo'sh vaqtga ko'chirdi:\n" . $what, (int) $board->id,
                    'to‘qnashuvni o‘qituvchi hal qildi'));
            } catch (\Throwable $e) {
                Log::warning('[TeacherConflictFix] xabar: ' . $e->getMessage());
            }
        }

        return array_values(array_unique($sent));
    }
}
