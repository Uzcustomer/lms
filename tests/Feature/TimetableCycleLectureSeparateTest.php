<?php

use App\Http\Controllers\Admin\TimetableController;
use App\Models\Teacher;
use App\Models\TimetableBoard;
use App\Models\TimetableCard;
use App\Models\TimetableCyclePlacement;
use App\Models\TimetableSubjectSetting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

// Sikl fanida alohida ma'ruza bloki ("ma'ruza alohida"): kunlik soatlar ro'yxati (SOATDA) fakultet / o'quv reja sozlamasida; ma'ruza —
// oqim qatorida alohida blok; amaliy blok — qolgan kunlar, ma'ruzasiz. Real misol: Xalqaro ta'lim, xd/23 (7-semestr), Ichki kasalliklar —
// ma'ruza [8,8,6,6,6] + amaliy 16 kun (jami 21); 2-son davolash — 18 (o'zgarmaydi).
beforeEach(fn () => $this->travelTo(Carbon::parse('2026-09-01 09:00', 'Asia/Tashkent')));

function lspTeacher(string $name, ?string $role = null): Teacher
{
    $t = new Teacher();
    $t->forceFill([
        'hemis_id' => random_int(100000, 999999), 'full_name' => $name, 'short_name' => $name, 'first_name' => $name, 'second_name' => $name,
        'employee_id_number' => (string) random_int(100000, 999999), 'birth_date' => '1980-01-01', 'year_of_enter' => 2010,
        'gender' => 'm', 'department' => 'Ichki kasalliklar kafedrasi', 'employment_form' => 'x', 'employment_staff' => 'x', 'staff_position' => 'x',
        'employee_status' => 'x', 'employee_type' => 'x', 'contract_number' => '1', 'decree_number' => '1',
        'contract_date' => '2020-01-01', 'decree_date' => '2020-01-01', 'status' => true, 'login' => 'u' . random_int(100000, 999999), 'password' => bcrypt('x'),
    ])->save();
    if ($role) {
        Role::findOrCreate($role, 'web');
        $t->assignRole($role);
    }

    return $t;
}

function lspAs(Teacher $t, string $role = 'admin')
{
    return test()->withoutVite()->actingAs($t, 'teacher')->withSession(['active_role' => $role]);
}

/**
 * Davolash ishi, 4-kurs: "Ichki kasalliklar" (sikl, umumiy 18; xd/23 — 21) va "Pediatriya" (sikl, 6). Oqimlar:
 *  - 2-son davolash / 1-oqim: d2/d23-01a, 01b (501); Xalqaro / 2-oqim: xd/23-01a, 01b (701).
 * Ma'ruza kartalari — oqim bo'yicha (o'qituvchi: MA'RUZACHI). Reja: ma'ruza 26, amaliy 100 soat.
 */
function lspSetup(): array
{
    Role::findOrCreate('admin', 'web');
    $board = TimetableBoard::create(['name' => 'T', 'academic_year' => '2026-2027', 'semester_parity' => 'kuzgi', 'days' => 6,
        'pairs_per_day' => 7, 'weeks' => 30, 'settings' => ['semester_start' => '2026-09-14']]);
    foreach ([['Ichki kasalliklar', 18], ['Pediatriya', 6]] as [$subject, $days]) {
        TimetableSubjectSetting::create(['board_id' => $board->id, 'specialty_name' => 'davolash ishi', 'course' => 4,
            'subject_name' => $subject, 'mode' => 'cycle', 'cycle_days' => $days]);
    }
    $mc = DB::table('manual_curricula')->insertGetId(['type' => 'ishchi', 'name' => 'x', 'specialty_name' => 'davolash ishi',
        'plan_year' => '2023/2024', 'level_code' => '14', 'created_at' => now(), 'updated_at' => now()]);
    foreach (['Ichki kasalliklar', 'Pediatriya'] as $subject) {
        DB::table('manual_curriculum_subjects')->insert(['manual_curriculum_id' => $mc, 'subject_name' => $subject,
            'semester' => 7, 'lecture' => 26, 'practice' => 100, 'created_at' => now(), 'updated_at' => now()]);
    }
    // Ma'ruzachilar — oqim bo'yicha (bir o'qituvchi ikki oqimda bo'lsa, haqiqiy to'qnashuv chiqadi).
    $lecturer = lspTeacher('MA\'RUZACHI');
    $lecturers = ['2-son davolash fakulteti' => lspTeacher('MA\'RUZACHI-2'), 'Xalqaro ta\'lim fakulteti' => $lecturer];
    $d2 = '2-son davolash fakulteti';
    $xd = 'Xalqaro ta\'lim fakulteti';
    $gid = 3000;
    foreach ([[$d2, '1-oqim', ['d2/d23-01a' => 501, 'd2/d23-01b' => 501]], [$xd, '2-oqim', ['xd/23-01a' => 701, 'xd/23-01b' => 701]]] as [$fac, $oqim, $groups]) {
        foreach ($groups as $g => $cid) {
            DB::table('groups')->insert(['group_hemis_id' => ++$gid, 'name' => $g, 'department_hemis_id' => 1, 'department_name' => $fac,
                'department_code' => 'F', 'department_structure_type_code' => '11', 'department_structure_type_name' => 'Fakultet',
                'department_locality_type_code' => '11', 'department_locality_type_name' => 'M', 'department_active' => true,
                'specialty_hemis_id' => 1, 'specialty_code' => 'S', 'specialty_name' => 'davolash ishi', 'education_lang_code' => '11',
                'education_lang_name' => 'uz', 'curriculum_hemis_id' => $cid, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
            foreach (['Ichki kasalliklar', 'Pediatriya'] as $subject) {
                TimetableCard::create(['board_id' => $board->id, 'faculty_name' => $fac, 'specialty_name' => 'davolash ishi', 'course' => 4,
                    'oqim_label' => $oqim, 'lang' => 'uz', 'len_half' => 2, 'weeks' => 30, 'subject_name' => $subject,
                    'kafedra_name' => $subject . ' kafedrasi', 'students' => 10, 'training_type' => 'practice', 'group_name' => $g]);
            }
        }
        foreach (['Ichki kasalliklar', 'Pediatriya'] as $subject) {
            TimetableCard::create(['board_id' => $board->id, 'faculty_name' => $fac, 'specialty_name' => 'davolash ishi', 'course' => 4,
                'oqim_label' => $oqim, 'lang' => 'uz', 'len_half' => 2, 'weeks' => 30, 'subject_name' => $subject,
                'kafedra_name' => $subject . ' kafedrasi', 'students' => 20, 'training_type' => 'lecture', 'group_names' => array_keys($groups),
                'teacher_id' => $lecturers[$fac]->id, 'teacher_name' => $lecturers[$fac]->full_name]);
        }
    }

    return ['board' => $board, 'admin' => lspTeacher('ADMIN', 'admin'), 'd2' => $d2, 'xd' => $xd, 'x2' => $xd . ' / 2-oqim', 'd2row' => $d2 . ' / 1-oqim',
        'lecturer' => $lecturer];
}

function lspSave(array $s, array $body)
{
    return lspAs($s['admin'])->postJson(route('admin.timetable.subject-setting.save', $s['board']), $body + ['specialty_name' => 'davolash ishi',
        'course' => 4, 'subject_name' => 'Ichki kasalliklar', 'mode' => 'cycle']);
}

/** xd/23: jami 21, ma'ruza alohida [8,8,6,6,6]. */
function lspFlag(array $s, string $hours = '8,8,6,6,6')
{
    return lspSave($s, ['faculty_name' => $s['xd'], 'curriculum_hemis_id' => 701, 'cycle_days' => 21, 'lecture' => 'on', 'lecture_hours' => $hours]);
}

function lspPlace(array $s, string $row, string $type, int $start, string $subject = 'Ichki kasalliklar', string $action = 'place', array $extra = [])
{
    return lspAs($s['admin'])->postJson(route('admin.timetable.cycle-place', $s['board']), $extra + [
        'specialty_name' => 'davolash ishi', 'course' => 4, 'group_name' => $row, 'subject_name' => $subject, 'training_type' => $type,
        'view' => 'flow', 'layer' => 'external', 'action' => $action, 'start_index' => $start, 'pair' => 1]);
}

function lspPlan(array $s, string $layer = 'external'): array
{
    return lspAs($s['admin'])->postJson(route('admin.timetable.cycle-plan', $s['board']), ['layer' => $layer, 'view' => 'flow'])->assertOk()->json();
}

/** Qator => [fan|tur => blok] (sikl rejasi). */
function lspBlocks(array $plan): array
{
    $out = [];
    foreach ($plan['rows'] as $row) {
        foreach ($row['blocks'] ?? [] as $b) {
            $out[$row['group']][$b['subject'] . '|' . $b['type']] = $b;
        }
    }

    return $out;
}

it('saqlash: kunlik soatlar 1..kun soatlari, ma\'ruza kunlari sikl kunlaridan kam; yig\'indi rejadan farq qilsa — ogohlantirish (taqiq emas)', function () {
    $s = lspSetup();
    $ok = lspFlag($s)->assertOk()->json();
    expect($ok['warnings'])->toBe(['Ma‘ruza soatlari yig‘indisi 34 — rejada 26 soat.']);
    $row = TimetableSubjectSetting::where('curriculum_hemis_id', 701)->first();
    expect([$row->cycle_days, $row->lecture_separate, $row->lecture_hours])->toBe([21, true, [8, 8, 6, 6, 6]]);
    // Kun to'ri — 7 para × 2 = 14 soat: 15 — rad; 0 va kasr — rad.
    lspFlag($s, '8,15')->assertUnprocessable()->assertJsonPath('error', 'Ma‘ruza kunlik soati 1 dan 14 gacha butun son bo‘lishi kerak (kun to‘ri — 14 soat): "15".');
    lspFlag($s, '8,0')->assertUnprocessable();
    lspFlag($s, '6,2.5')->assertUnprocessable();
    lspFlag($s, '')->assertUnprocessable();
    // 21 ta ma'ruza kuni — amaliyga kun qolmaydi.
    lspFlag($s, implode(',', array_fill(0, 21, 2)))->assertUnprocessable();
    // Yig'indi rejaga teng — ogohlantirishsiz; "inherit" — aniqlovchili yozuvda ma'ruza belgisi olib tashlanadi (kunlar qoladi).
    expect(lspFlag($s, '6,6,6,4,4')->assertOk()->json('warnings'))->toBe([]);
    lspSave($s, ['faculty_name' => $s['xd'], 'curriculum_hemis_id' => 701, 'cycle_days' => 21, 'lecture' => 'inherit'])->assertOk();
    expect(TimetableSubjectSetting::where('curriculum_hemis_id', 701)->first()->only(['cycle_days', 'lecture_separate']))
        ->toBe(['cycle_days' => 21, 'lecture_separate' => null]);
    // Ma'ruza sozlamasi yuborilmasa — o'zgarmaydi (Darslar qatoridagi oddiy saqlash).
    lspFlag($s)->assertOk();
    lspSave($s, ['faculty_name' => $s['xd'], 'curriculum_hemis_id' => 701, 'cycle_days' => 21])->assertOk();
    expect(TimetableSubjectSetting::where('curriculum_hemis_id', 701)->first()->lecture_hours)->toBe([8, 8, 6, 6, 6]);
    // "Darslar" ro'yxati va oyna: aniqlovchili yozuvda ma'ruza sozlamasi.
    $data = lspAs($s['admin'])->getJson(route('admin.timetable.boards.data', $s['board']))->assertOk()->json('subject_settings');
    $ik = collect($data)->firstWhere('subject_name', 'Ichki kasalliklar');
    expect($ik['lecture'])->toBeNull()->and($ik['scoped'][0]['lecture'])->toBe(['on' => true, 'hours' => [8, 8, 6, 6, 6]]);
    $scopes = lspAs($s['admin'])->getJson(route('admin.timetable.subject-setting.scopes', $s['board']) . '?' . http_build_query([
        'specialty_name' => 'davolash ishi', 'course' => 4, 'subject_name' => 'Ichki kasalliklar']))->assertOk()->json();
    expect([$scopes['day_hours'], (float) $scopes['plan_lecture_hours']])->toBe([14, 26.0])
        ->and(collect($scopes['faculties'])->firstWhere('name', $s['xd'])['curricula'][0]['lecture'])->toBe(['on' => true, 'hours' => [8, 8, 6, 6, 6]]);
});

it('xd/23: ma\'ruza bloki (5 kun, 34 soat) alohida, amaliy 16 kun; 2-son davolash — 18, ma\'ruza amaliy ichida (o\'zgarmaydi)', function () {
    $s = lspSetup();
    lspFlag($s)->assertOk();
    // Panel: xd/23 oqimiga ma'ruza kartasi (5 kun, 34 soat) va amaliy kartasi (16 kun); 2-son davolashda — faqat amaliy (18).
    $cards = collect(lspPlan($s)['cycle_cards'])->where('subject', 'Ichki kasalliklar');
    $x2 = $cards->where('group', $s['x2']);
    expect($x2->pluck('type')->sort()->values()->all())->toBe(['lecture', 'practice'])
        ->and($x2->firstWhere('type', 'lecture'))->toMatchArray(['days' => 5, 'hours' => 34, 'lecture_list' => [8, 8, 6, 6, 6], 'view' => 'flow', 'placed' => false])
        ->and($x2->firstWhere('type', 'practice'))->toMatchArray(['days' => 16, 'hours' => 100])
        ->and($cards->where('group', $s['d2row'])->map(fn ($c) => [$c['type'], $c['days'], $c['hours']])->values()->all())->toBe([['practice', 18, 126]]);
    // Ma'ruza 14.09 (indeks 0) dan, amaliy 16.10 (indeks 32) dan.
    expect(lspPlace($s, $s['x2'], 'lecture', 0)->assertOk()->json())->toMatchArray(['days' => 5, 'warnings' => []]);
    lspPlace($s, $s['x2'], 'practice', 32)->assertOk();
    lspPlace($s, $s['d2row'], 'practice', 0)->assertOk();
    $b = lspBlocks(lspPlan($s));
    expect($b[$s['x2']]['Ichki kasalliklar|lecture'])->toMatchArray(['from' => 0, 'to' => 4, 'days' => 5, 'hours' => 34, 'lecture_separate' => true,
            'lecture_note' => 'Ma‘ruza soatlari yig‘indisi 34 — rejada 26 soat.'])
        ->and($b[$s['x2']]['Ichki kasalliklar|practice'])->toMatchArray(['from' => 32, 'days' => 16, 'hours' => 100, 'lecture_separate' => true])
        ->and($b[$s['d2row']])->toHaveKey('Ichki kasalliklar|practice')->not->toHaveKey('Ichki kasalliklar|lecture')
        ->and($b[$s['d2row']]['Ichki kasalliklar|practice'])->toMatchArray(['days' => 18, 'lecture_separate' => false]);
    // Tashqi jadvalda xd/23 amaliyi uchun ma'ruza "soyasi" yaratilmadi (ma'ruza — o'z yozuvi); 2-son davolashda — avvalgidek.
    expect(TimetableCyclePlacement::where('group_name', $s['x2'])->where('training_type', 'lecture')->pluck('start_index')->all())->toBe([0])
        ->and(TimetableCyclePlacement::where('group_name', $s['d2row'])->where('training_type', 'lecture')->pluck('start_index')->all())->toBe([0]);
    // Ichki qatlam: har guruhcha qatorida ma'ruza bloki — M kataklar 8,8,6,6,6; amaliy bloki — ma'ruzasiz (har kun 6 soat amaliy).
    $in = lspBlocks(lspPlan($s, 'internal'));
    foreach (['xd/23-01a', 'xd/23-01b'] as $g) {
        $lec = $in[$g]['Ichki kasalliklar|lecture'];
        expect(array_map(fn ($d) => [$d['lec'], $d['prc'], $d['start']], array_values($lec['day_plan'])))->toBe([[8, 0, 1], [8, 0, 1], [6, 0, 1], [6, 0, 1], [6, 0, 1]]);
        $prc = $in[$g]['Ichki kasalliklar|practice'];
        expect(array_unique(array_column($prc['day_plan'], 'lec')))->toBe([0])->and(array_sum(array_column($prc['day_plan'], 'prc')))->toBe(96)
            ->and(count($prc['day_plan']))->toBe(16);
    }
    // 2-son davolash: ma'ruza amaliy blok ichida (birinchi kunlar 2 soat) — hozirgidek.
    expect(array_column(array_slice($in['d2/d23-01a']['Ichki kasalliklar|practice']['day_plan'], 0, 2), 'lec'))->toBe([2, 2]);
});

it('amaliy ko\'chsa ma\'ruza joyidan qo\'zg\'almaydi; amaliy ma\'ruza kunlariga — xato; boshqa fan amaliyi — ogohlantirish', function () {
    $s = lspSetup();
    lspFlag($s)->assertOk();
    lspPlace($s, $s['x2'], 'lecture', 0)->assertOk();
    lspPlace($s, $s['x2'], 'practice', 32)->assertOk();
    // Amaliyni ko'chirish (qayta joylash) va strelka bilan siljitish — ma'ruza 0 da qoladi.
    lspPlace($s, $s['x2'], 'practice', 40)->assertOk();
    lspPlace($s, $s['x2'], 'practice', 40, 'Ichki kasalliklar', 'shift', ['direction' => 1])->assertOk();
    $starts = fn () => TimetableCyclePlacement::where('group_name', $s['x2'])->where('subject_name', 'Ichki kasalliklar')->orderBy('training_type')
        ->pluck('start_index', 'training_type')->all();
    expect($starts())->toBe(['lecture' => 0, 'practice' => 42]);
    // Amaliy ma'ruza kunlariga (16.09) — xato, "Baribir" yo'q; ma'ruza amaliy ustiga — xato.
    $err = lspPlace($s, $s['x2'], 'practice', 2)->assertUnprocessable()->json();
    expect($err['code'])->toBe('lecture_clash')->and($err['error'])->toContain('ma‘ruza kunlari bilan kesishadi')->toContain('14.09–18.09');
    lspPlace($s, $s['x2'], 'practice', 2, 'Ichki kasalliklar', 'place', ['allow_overlap' => 1, 'overlap_days' => 30, 'ignore_gap' => 1])->assertUnprocessable();
    lspPlace($s, $s['x2'], 'lecture', 42)->assertUnprocessable()->assertJsonPath('code', 'lecture_clash');
    // Ma'ruzani siljitish — faqat o'zi (amaliy joyida); amaliy ustiga siljitib bo'lmaydi.
    lspPlace($s, $s['x2'], 'lecture', 0, 'Ichki kasalliklar', 'shift', ['direction' => 1, 'training_type' => 'lecture'])->assertOk();
    expect($starts())->toBe(['lecture' => 1, 'practice' => 42]);
    expect($starts())->toBe(['lecture' => 1, 'practice' => 42]);
    // Boshqa fan (Pediatriya) amaliyi shu oqim ma'ruza kunlariga — joylanadi, ogohlantirish bilan; jadvalda ma'ruza blokida ham.
    $ped = lspPlace($s, $s['x2'], 'practice', 3, 'Pediatriya')->assertOk()->json();
    expect($ped['warnings'])->toBe(['ma‘ruza kunlari: Ichki kasalliklar — guruh xd/23-01a, xd/23-01b shu kunlarda Pediatriya amaliyotida']);
    $b = lspBlocks(lspPlan($s));
    expect($b[$s['x2']]['Ichki kasalliklar|lecture']['lecture_warn'])
        ->toBe(['ma‘ruza kunlari: Ichki kasalliklar — guruh xd/23-01a, xd/23-01b shu kunlarda Pediatriya amaliyotida']);
    // Ma'ruzani Pediatriya ustiga ko'chirish — ham ogohlantirish (xato emas).
    $lec = lspPlace($s, $s['x2'], 'lecture', 4)->assertOk()->json();
    expect($lec['warnings'][0])->toBe('ma‘ruza kunlari: Ichki kasalliklar — guruh xd/23-01a, xd/23-01b shu kunlarda Pediatriya amaliyotida');
    // Amaliyni olib tashlash — ma'ruza qoladi.
    lspPlace($s, $s['x2'], 'practice', 42, 'Ichki kasalliklar', 'remove')->assertOk();
    expect($starts())->toBe(['lecture' => 4]);
});

it('bandlik va HEMIS: ma\'ruza darslari o\'z kunlarida (8,8,6,6,6 soat), amaliy kunlarida ma\'ruza yo\'q', function () {
    $s = lspSetup();
    lspFlag($s)->assertOk();
    lspPlace($s, $s['x2'], 'lecture', 0)->assertOk();
    lspPlace($s, $s['x2'], 'practice', 32)->assertOk();
    $ctl = app(TimetableController::class);
    [$units, $calendar] = (new ReflectionMethod($ctl, 'cycleInternalUnits'))->invoke($ctl, $s['board']);
    $units = array_values(array_filter($units, fn ($u) => $u['group'] === 'xd/23-01a' && $u['subject'] === 'Ichki kasalliklar'));
    $occ = (new ReflectionMethod($ctl, 'cycleOccupancy'))->invoke($ctl, $s['board'], $units, $calendar);
    $lec = array_values(array_filter($occ, fn ($e) => str_starts_with($e['load_key'], 'l')));
    expect(array_map(fn ($e) => [$e['date'], $e['to'] - $e['from'], $e['teacher_id']], $lec))->toBe([
        ['2026-09-14', 8, $s['lecturer']->id], ['2026-09-15', 8, $s['lecturer']->id], ['2026-09-16', 6, $s['lecturer']->id],
        ['2026-09-17', 6, $s['lecturer']->id], ['2026-09-18', 6, $s['lecturer']->id]]);
    $prc = array_values(array_filter($occ, fn ($e) => !str_starts_with($e['load_key'], 'l')));
    expect(count($prc))->toBe(16)->and($prc[0]['date'])->toBe('2026-10-16');
    // Haftalik bandlik (cycleBusyByDate): ma'ruza o'qituvchisi 14–18.09 da.
    $busy = (new ReflectionMethod($ctl, 'cycleBusyByDate'))->invoke($ctl, $s['board']);
    $lecBusy = [];
    foreach ($busy as $date => $items) {
        foreach ($items as $it) {
            if ((int) $it[2] === $s['lecturer']->id && str_contains($it[6], 'Ichki kasalliklar') && str_contains($it[6], $s['x2'])) {
                $lecBusy[$date] = $it[1] - $it[0];
            }
        }
    }
    ksort($lecBusy);
    expect($lecBusy)->toBe(['2026-09-14' => 8, '2026-09-15' => 8, '2026-09-16' => 6, '2026-09-17' => 6, '2026-09-18' => 6]);
    // HEMIS solishtirish: L — 5 kun (8,8,6,6,6), A — 16.10 dan; bir kunda ikkalasi yo'q.
    $cards = TimetableCard::where('board_id', $s['board']->id)->get();
    $lessons = (new ReflectionMethod($ctl, 'hemisCycleLessons'))->invoke($ctl, $s['board'], [], $cards);
    $mine = collect($lessons)->filter(fn ($v, $k) => str_contains($k, 'xd/23-01a'))->first();
    $ik = collect($mine)->first(fn ($v, $k) => str_contains($k, 'ichki'));
    $L = collect($ik)->where('part', 'L')->map(fn ($e) => [$e['date'], $e['len']])->values()->all();
    $A = collect($ik)->where('part', 'A');
    expect($L)->toBe([['2026-09-14', 8], ['2026-09-15', 8], ['2026-09-16', 6], ['2026-09-17', 6], ['2026-09-18', 6]])
        ->and($A->count())->toBe(16)->and($A->min('date'))->toBe('2026-10-16')
        ->and(array_intersect($A->pluck('date')->all(), array_column($L, 0)))->toBe([]);
});

it('mavjud joylashuv: belgi qo\'yilgach eski ma\'ruza yozuvi alohida blok bo\'lib qoladi va amaliy bilan birga ko\'chmaydi; saqlashdan oldin ro\'yxat', function () {
    $s = lspSetup();
    lspSave($s, ['faculty_name' => $s['xd'], 'curriculum_hemis_id' => 701, 'cycle_days' => 21])->assertOk();
    // Belgisiz: tashqi jadvalda amaliy bilan birga ma'ruza yozuvi (bir xil boshlanish).
    lspPlace($s, $s['x2'], 'practice', 10)->assertOk();
    expect(TimetableCyclePlacement::where('group_name', $s['x2'])->orderBy('training_type')->pluck('start_index', 'training_type')->all())
        ->toBe(['lecture' => 10, 'practice' => 10]);
    // Belgi qo'yishdan oldin (saqlanmaydi): amaliy bloki 21 → 16, eski ma'ruza yozuvi — 5 kunlik alohida blok.
    $dry = collect(lspSave($s, ['faculty_name' => $s['xd'], 'curriculum_hemis_id' => 701, 'cycle_days' => 21, 'lecture' => 'on',
        'lecture_hours' => '8,8,6,6,6', 'dry_run' => 1])->assertOk()->json('affected'))->map(fn ($a) => [$a['type'], $a['from'], $a['to']])->sortBy(0)->values()->all();
    expect($dry)->toBe([['lecture', 21, 5], ['practice', 21, 16]])
        ->and(TimetableSubjectSetting::where('curriculum_hemis_id', 701)->first()->lecture_separate)->toBeNull();
    lspFlag($s)->assertOk();
    $b = lspBlocks(lspPlan($s))[$s['x2']];
    expect([$b['Ichki kasalliklar|lecture']['from'], $b['Ichki kasalliklar|lecture']['days']])->toBe([10, 5])
        ->and($b['Ichki kasalliklar|practice']['lecture_clash'])->toBe('amaliy bloki ma‘ruza kunlari bilan kesishadi');
    // Amaliyni ma'ruzadan keyinga ko'chirish — ma'ruza 10 da qoladi; kesishuv belgisi yo'qoladi.
    lspPlace($s, $s['x2'], 'practice', 32)->assertOk();
    $b = lspBlocks(lspPlan($s))[$s['x2']];
    expect([$b['Ichki kasalliklar|lecture']['from'], $b['Ichki kasalliklar|practice']['from']])->toBe([10, 32])
        ->and($b['Ichki kasalliklar|practice'])->not->toHaveKey('lecture_clash');
});

it('regressiya: belgisi yo\'q fan va boshqa fakultet (2-son davolash) bloklari — kunlar, joylashuv, ma\'ruza joyi bit-bit o\'zgarmaydi', function () {
    $s = lspSetup();
    lspPlace($s, $s['d2row'], 'practice', 0)->assertOk();
    lspPlace($s, $s['d2row'], 'practice', 25, 'Pediatriya')->assertOk();
    $rowsOf = function (string $layer) use ($s) {
        $out = [];
        foreach (lspPlan($s, $layer)['rows'] as $row) {
            if (str_starts_with((string) $row['group'], 'd2/') || $row['group'] === $s['d2row']) {
                $out[$row['group']] = array_map(fn ($b) => array_diff_key($b, array_flip(['lecture_separate', 'lecture_list', 'lecture_note'])), $row['blocks']);
            }
        }

        return $out;
    };
    $before = [$rowsOf('external'), $rowsOf('internal')];
    lspFlag($s)->assertOk();
    lspPlace($s, $s['x2'], 'lecture', 0)->assertOk();
    lspPlace($s, $s['x2'], 'practice', 32)->assertOk();
    expect($before[0])->not->toBeEmpty()->and([$rowsOf('external'), $rowsOf('internal')])->toBe($before);
    expect(TimetableCyclePlacement::where('group_name', $s['d2row'])->orderBy('subject_name')->orderBy('training_type')
        ->get(['subject_name', 'training_type', 'start_index'])->map(fn ($p) => [$p->subject_name, $p->training_type, $p->start_index])->all())
        ->toBe([['Ichki kasalliklar', 'lecture', 0], ['Ichki kasalliklar', 'practice', 0], ['Pediatriya', 'lecture', 25], ['Pediatriya', 'practice', 25]]);
});
