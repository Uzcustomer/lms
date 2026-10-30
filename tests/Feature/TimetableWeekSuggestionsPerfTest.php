<?php

use App\Models\Teacher;
use App\Models\TimetableBoard;
use App\Models\TimetableCard;
use App\Models\TimetableCardOverride;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * 3000 kartali sintetik doska (deterministik): 3 fakultet × 6 yo'nalish × 3 kurs, 300 guruh, 10 kafedra × 25 o'qituvchi
 * (20 tasi band, 5 tasida dars kam — o'qituvchini almashtirish taklifi uchun), 150 xona; ~10% karta shablonda joylanmagan
 * (tashqi jadvaldagi joyi bor), bir qismi ba'zi haftalarda bekor yoki ko'chirilgan.
 */
function sugPerfBoard(): array
{
    mt_srand(42);
    Role::findOrCreate('superadmin', 'web');
    $board = TimetableBoard::create(['name' => 'P', 'academic_year' => '2026-2027', 'semester_parity' => 'kuzgi', 'days' => 6,
        'pairs_per_day' => 12, 'weeks' => 16, 'settings' => ['semester_start' => '2026-09-14']]);
    $now = now();
    $teachers = [];
    for ($k = 1; $k <= 10; $k++) {
        for ($i = 1; $i <= 25; $i++) {
            $n = 'T' . $k . '-' . $i;
            $teachers[] = ['hemis_id' => 10000 + $k * 100 + $i, 'full_name' => $n, 'short_name' => $n, 'first_name' => $n, 'second_name' => $n,
                'employee_id_number' => (string) (20000 + $k * 100 + $i), 'birth_date' => '1980-01-01', 'year_of_enter' => 2010, 'gender' => 'm',
                'department' => 'K' . $k . ' kafedrasi', 'employment_form' => 'x', 'employment_staff' => 'x', 'staff_position' => 'x',
                'employee_status' => 'x', 'employee_type' => 'x', 'contract_number' => '1', 'decree_number' => '1', 'contract_date' => '2020-01-01',
                'decree_date' => '2020-01-01', 'status' => true, 'login' => 'perf' . $k . '_' . $i, 'password' => 'x', 'created_at' => $now, 'updated_at' => $now];
        }
    }
    Teacher::insert($teachers);
    $tids = Teacher::where('login', 'like', 'perf%')->orderBy('id')->pluck('id')->all();
    $rows = [];
    for ($i = 0; $i < 3000; $i++) {
        $k = $i % 10;
        $tid = $tids[$k * 25 + (mt_rand(1, 100) <= 4 ? mt_rand(20, 24) : mt_rand(0, 19))];
        $placed = mt_rand(1, 10) > 1;
        $d = mt_rand(1, 6);
        $p = 1 + 2 * mt_rand(0, 4);
        $rows[] = ['board_id' => $board->id, 'faculty_name' => 'F' . ($i % 3 + 1), 'specialty_name' => 'spec' . ($i % 6), 'course' => 1 + ($i % 9) % 3,
            'oqim_label' => 'o' . ($i % 40), 'lang' => 'uz', 'students' => 12, 'training_type' => 'practice', 'len_half' => 2, 'weeks' => 16,
            'subject_name' => 'S' . ($i % 40), 'group_name' => 'g' . sprintf('%03d', $i % 300), 'kafedra_name' => 'K' . ($k + 1) . ' kafedrasi',
            'teacher_id' => $tid, 'teacher_name' => 'T', 'auditorium_code' => 'R' . mt_rand(1, 150), 'auditorium_name' => 'R',
            'day' => $placed ? $d : null, 'pair' => $placed ? $p : null, 'ext_day' => $d, 'ext_pair' => $p, 'created_at' => $now, 'updated_at' => $now];
    }
    foreach (array_chunk($rows, 500) as $chunk) {
        TimetableCard::insert($chunk);
    }
    $ids = TimetableCard::where('board_id', $board->id)->orderBy('id')->pluck('id')->all();
    $ov = [];
    foreach ($ids as $n => $id) {
        if ($n % 7 === 0) {
            $ov[] = ['card_id' => $id, 'week' => 1 + $n % 16, 'cancelled' => true, 'day' => null, 'pair' => null, 'start_half' => 0,
                'auditorium_code' => null, 'created_at' => $now, 'updated_at' => $now];
        } elseif ($n % 11 === 0) {
            // Ko'chirilgan haftaning yarmida — shu haftaga berilgan boshqa xona
            $ov[] = ['card_id' => $id, 'week' => 1 + $n % 16, 'cancelled' => false, 'day' => mt_rand(1, 6), 'pair' => 1 + 2 * mt_rand(0, 4), 'start_half' => 0,
                'auditorium_code' => $n % 2 ? 'R' . mt_rand(1, 150) : null, 'created_at' => $now, 'updated_at' => $now];
        }
    }
    foreach (array_chunk($ov, 500) as $chunk) {
        TimetableCardOverride::insert($chunk);
    }
    $admin = Teacher::find($tids[0]);
    $admin->assignRole('superadmin');

    return compact('board', 'admin');
}

it('takliflar: 3000 kartali doskada so\'rov 10 soniyadan oshmaydi', function () {
    $s = sugPerfBoard();
    $t = microtime(true);
    $res = test()->withoutVite()->actingAs($s['admin'], 'teacher')->withSession(['active_role' => 'superadmin'])
        ->getJson(route('admin.timetable.boards.week-suggestions', $s['board']) . '?week=7&faculty_names[]=F1&specialty_names[]=spec0&courses[]=1')
        ->assertOk()->json();
    $sec = microtime(true) - $t;
    fwrite(STDERR, sprintf("\nweek-suggestions: %.1fs, %d karta, %d taklif\n", $sec, $res['shown'], collect($res['cards'])->sum(fn ($c) => count($c['suggestions']))));
    expect($res['shown'])->toBeGreaterThan(10)->and($sec)->toBeLessThan(10.0);
});

it('findWeekConflictsIndexed — eski findWeekConflicts bilan 200 ta tasodifiy (karta, hafta, kun, para) uchun aynan bir xil', function () {
    $s = sugPerfBoard();
    $ctl = app(\App\Http\Controllers\Admin\TimetableController::class);
    $call = fn (string $m, ...$a) => (new ReflectionMethod($ctl, $m))->invoke($ctl, ...$a);
    $state = $call('occupancyState', $s['board']->id);
    $ids = array_keys($state['cards']);
    mt_srand(7);
    $nonEmpty = 0;
    for ($i = 0; $i < 200; $i++) {
        $card = $state['cards'][$ids[mt_rand(0, count($ids) - 1)]];
        // Ba'zan boshqa o'qituvchi yoki xona bilan (takliflardagi kabi klon)
        if ($i % 4 === 1) {
            $card = clone $card;
            $card->teacher_id = $state['cards'][$ids[mt_rand(0, count($ids) - 1)]]->teacher_id;
        } elseif ($i % 4 === 2) {
            $card = clone $card;
            $card->auditorium_code = 'R' . mt_rand(1, 150);
        }
        [$w, $d, $p] = [mt_rand(1, 16), mt_rand(1, 6), 1 + 2 * mt_rand(0, 4)];
        $old = $call('findWeekConflicts', $card, $w, $d, $p, 0, $state);
        $new = $call('findWeekConflictsIndexed', $card, $w, $d, $p, 0, $state);
        expect($new)->toBe($old);
        $nonEmpty += $old ? 1 : 0;
    }
    // Tekshiruv bo'sh natijalar bilan cheklanib qolmasin
    expect($nonEmpty)->toBeGreaterThan(40);

    // Yo'naltirilgan holatlar — haftaga berilgan xona: (1) boshqa karta o'sha xona bilan o'sha vaqtda (xona indeksida haftalik
    // xona bo'lishi kerak); (2) kartaning o'zi, shu haftadagi xonasini shablonda band qilgan karta turgan vaqtda.
    $targeted = [];
    foreach ($state['ov'] as $aid => $weeks) {
        foreach ($weeks as $w => $o) {
            if (!$o->auditorium_code || $o->cancelled || !$o->day || $o->auditorium_code === $state['cards'][$aid]->auditorium_code) {
                continue;
            }
            $other = clone $state['cards'][$ids[(count($targeted) * 37 + 11) % count($ids)]];
            $other->auditorium_code = $o->auditorium_code;
            $targeted[] = [$other, $w, (int) $o->day, (int) $o->pair];
            $b = collect($state['cards'])->first(fn ($x) => (int) $x->id !== (int) $aid && $x->auditorium_code === $o->auditorium_code
                && $x->day && $x->pair && !isset($state['ov'][$x->id][$w]));
            if ($b) {
                $targeted[] = [$state['cards'][$aid], $w, (int) $b->day, (int) $b->pair];
            }
        }
    }
    $roomHits = 0;
    foreach ($targeted as [$card, $w, $d, $p]) {
        $old = $call('findWeekConflicts', $card, $w, $d, $p, 0, $state);
        expect($call('findWeekConflictsIndexed', $card, $w, $d, $p, 0, $state))->toBe($old);
        $roomHits += (bool) array_filter($old, fn ($m) => str_starts_with($m, 'Auditoriya band'));
    }
    expect(count($targeted))->toBeGreaterThan(20)->and($roomHits)->toBeGreaterThan(10);
});

it('takliflar: optimizatsiya natijani o\'zgartirmaydi (eski yo\'l bilan aynan bir xil)', function () {
    $s = sugPerfBoard();
    // Xonalar noyob — faqat o'qituvchi band bo'lgan joylar ko'payadi va 6-qadam (o'qituvchini almashtirish) ham tekshiriladi
    TimetableCard::where('board_id', $s['board']->id)->update(['auditorium_code' => DB::raw("CONCAT('U', id)")]);
    // Qamrovdagi joylanmaganlarning yarmi — fan sheriklarisiz (bitta kartali dars): 6-qadam faqat shunday darsga taklif qiladi.
    // Tanlov id bo'yicha emas, tartib bo'yicha (id'lar boshqa testlardan keyin farq qiladi).
    $scope = fn () => TimetableCard::where('board_id', $s['board']->id)->where(['faculty_name' => 'F1', 'specialty_name' => 'spec0', 'course' => 1]);
    $single = $scope()->whereNull('day')->orderBy('id')->pluck('id')->filter(fn ($id, $i) => $i % 2 === 0);
    // Shablonda joylashgan, faqat 7-haftada joyidan olingan yakka darslar: o'qituvchi qolgan 15 haftada ham bo'sh bo'lishi kerak
    $moved = $scope()->whereNotNull('day')->orderBy('id')->pluck('id')->filter(fn ($id, $i) => $i % 12 === 7)->take(8);
    TimetableCard::whereIn('id', $single->merge($moved))->update(['subject_name' => DB::raw("CONCAT('Yakka ', id)")]);
    foreach ($moved as $id) {
        \App\Models\TimetableCardOverride::updateOrCreate(['card_id' => $id, 'week' => 7], ['day' => null, 'pair' => null, 'cancelled' => false]);
    }
    $url = route('admin.timetable.boards.week-suggestions', $s['board']) . '?week=7&faculty_names[]=F1&specialty_names[]=spec0&courses[]=1&limit=20';
    $run = function (bool $legacy) use ($s, $url) {
        $ctl = app(\App\Http\Controllers\Admin\TimetableController::class);
        (new ReflectionProperty($ctl, 'weekSuggestionsLegacy'))->setValue($ctl, $legacy);
        app()->instance(\App\Http\Controllers\Admin\TimetableController::class, $ctl);

        return test()->withoutVite()->actingAs($s['admin'], 'teacher')->withSession(['active_role' => 'superadmin'])->getJson($url)->assertOk()->json();
    };
    $legacy = $run(true);
    $fast = $run(false);
    expect($fast)->toBe($legacy)
        ->and(collect($fast['cards'])->sum(fn ($c) => count($c['suggestions'])))->toBeGreaterThan(20)
        ->and(collect($fast['cards'])->flatMap(fn ($c) => array_column($c['suggestions'], 'type'))->unique()->sort()->values()->all())
        ->toContain('teacher')->toContain('slot');
});

it('takliflar: vaqt byudjeti tugasa — qolgan kartalarga faqat tez qadamlar, partial; ?card_id= — shu karta uchun to\'liq', function () {
    $s = sugPerfBoard();
    $base = route('admin.timetable.boards.week-suggestions', $s['board']) . '?week=7&faculty_names[]=F1&specialty_names[]=spec0&courses[]=1&limit=12';
    $as = fn () => test()->withoutVite()->actingAs($s['admin'], 'teacher')->withSession(['active_role' => 'superadmin']);
    $full = $as()->getJson($base)->assertOk()->json();
    expect($full['partial'])->toBeFalse()->and($full['message'])->toBeNull();

    config(['timetable.week_suggestions_budget' => 0]);
    $cut = $as()->getJson($base)->assertOk()->json();
    $slow = ['blocker', 'day', 'week', 'teacher'];
    expect($cut['partial'])->toBeTrue()
        ->and($cut['message'])->toBe(count($cut['cards']) . " ta karta uchun to'liq takliflar hisoblanmadi")
        ->and(collect($cut['cards'])->every(fn ($c) => $c['partial'] || $c['suggestions'] === []))->toBeTrue()
        ->and(collect($cut['cards'])->flatMap(fn ($c) => array_column($c['suggestions'], 'type'))->intersect($slow)->all())->toBe([]);

    // Bitta karta — byudjetdan qat'i nazar to'liq; natijasi to'liq so'rovdagi bilan bir xil
    $card = collect($full['cards'])->first(fn ($c) => collect($c['suggestions'])->pluck('type')->intersect($slow)->isNotEmpty());
    $one = $as()->getJson($base . '&card_id=' . $card['id'])->assertOk()->json();
    expect($one['partial'])->toBeFalse()->and($one['cards'])->toHaveCount(1)
        ->and($one['cards'][0])->toBe($card);
});
