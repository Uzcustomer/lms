<?php

use App\Http\Controllers\Admin\TimetableController;
use App\Models\TimetableBoard;
use App\Models\TimetableCard;
use App\Models\TimetableGridSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(RefreshDatabase::class);

/**
 * Ikki fanning ma'ruzasi bitta slotni bo'lishsa (biri toq, ikkinchisi juft
 * haftalarda), har fanning o'rin bosuvchi amaliyi o'z ma'ruzasining slotiga
 * tusha olmaydi — u slot boshqa fanning ma'ruzasi bilan band. Shu sababli
 * shablonda fan bo'laklari sochilib qoladi.
 *
 * Haftalik zichlash (compactWeek) buni HAFTA ICHIDA to'g'rilaydi: darslar fan
 * bo'yicha guruhlanib, kun boshidan qayta tiziladi. Shablon o'zgarmaydi —
 * faqat shu haftaga istisno yoziladi.
 */
const COMPACT_TOTAL_WEEKS = 15;

function compactTestBoard(): TimetableBoard
{
    foreach (range(1, 4) as $i) {
        DB::table('auditoriums')->insert(['code' => "R$i", 'name' => "№ 10$i", 'volume' => 40,
            'active' => 1, 'created_at' => now(), 'updated_at' => now()]);
    }

    $board = TimetableBoard::create([
        'name' => 'Test', 'academic_year' => '2026-2027', 'semester' => 'kuzgi',
        'days' => 6, 'pairs_per_day' => 12, 'weeks' => COMPACT_TOTAL_WEEKS,
        'settings' => ['pair_same_day' => true, 'pair_consecutive' => true],
    ]);
    TimetableGridSetting::create([
        'board_id' => $board->id, 'faculty_name' => 'F1',
        'specialty_name' => 'davolash ishi', 'course' => 2,
        'days' => 6, 'pairs_per_day' => 12, 'weeks' => COMPACT_TOTAL_WEEKS,
    ]);

    return $board;
}

function compactTestCard(TimetableBoard $board, array $attrs, ?array $activeWeeks = null): TimetableCard
{
    $card = TimetableCard::create(array_merge([
        'board_id' => $board->id, 'faculty_name' => 'F1',
        'specialty_name' => 'davolash ishi', 'course' => 2,
        'oqim_label' => '1-oqim', 'lang' => 'uz',
        'students' => 20, 'len_half' => 2, 'start_half' => 0,
    ], $attrs));

    if ($activeWeeks !== null) {
        $rows = [];
        foreach (range(1, COMPACT_TOTAL_WEEKS) as $week) {
            if (!in_array($week, $activeWeeks, true)) {
                $rows[] = ['card_id' => $card->id, 'week' => $week, 'day' => null, 'pair' => null,
                    'cancelled' => true, 'created_at' => now(), 'updated_at' => now()];
            }
        }
        if ($rows) {
            DB::table('timetable_card_overrides')->insert($rows);
        }
    }

    return $card;
}

/** Shu haftada guruh egallagan yarim-slotlar: [slot => fan nomi]. */
function compactTestWeekLayout(array $cards, int $week): array
{
    $layout = [];
    foreach ($cards as $card) {
        $card = $card->fresh();
        $override = DB::table('timetable_card_overrides')
            ->where('card_id', $card->id)->where('week', $week)->first();
        if ($override && $override->cancelled) {
            continue;
        }
        $pair = ($override && $override->day) ? (int) $override->pair : (int) $card->pair;
        if (!$pair) {
            continue;
        }
        for ($i = 0; $i < $card->len_half; $i++) {
            $layout[$pair + $i] = $card->subject_name;
        }
    }
    ksort($layout);

    return $layout;
}

test('haftalik zichlash darslarni fan bo\'yicha guruhlab kun boshiga tizadi', function () {
    $board = compactTestBoard();

    // Gigiyena: 5 hafta 2 s ma'ruza + 1 s amaliy; 10 hafta 3 s amaliy
    $gigiyenaLectureWeeks = [2, 4, 6, 8, 10];
    $gigiyenaStandInWeeks = array_values(array_diff(range(1, COMPACT_TOTAL_WEEKS), $gigiyenaLectureWeeks));
    // Psixologiya: 6 hafta ma'ruza (toq), qolgan 9 haftada o'rin bosuvchi amaliy
    $psixologiyaLectureWeeks = [1, 3, 5, 7, 9, 11];
    $psixologiyaStandInWeeks = array_values(array_diff(range(1, COMPACT_TOTAL_WEEKS), $psixologiyaLectureWeeks));

    $cards = [
        compactTestCard($board, ['training_type' => 'lecture', 'group_names' => ['g-01a'],
            'subject_name' => 'Gigiyena', 'weeks' => count($gigiyenaLectureWeeks),
            'teacher_id' => 1], $gigiyenaLectureWeeks),
        compactTestCard($board, ['training_type' => 'practice', 'group_name' => 'g-01a',
            'subject_name' => 'Gigiyena', 'len_half' => 1,
            'weeks' => COMPACT_TOTAL_WEEKS, 'teacher_id' => 1]),
        compactTestCard($board, ['training_type' => 'practice', 'group_name' => 'g-01a',
            'subject_name' => 'Gigiyena', 'weeks' => count($gigiyenaStandInWeeks),
            'teacher_id' => 1], $gigiyenaStandInWeeks),
        compactTestCard($board, ['training_type' => 'lecture', 'group_names' => ['g-01a'],
            'subject_name' => 'Psixologiya', 'weeks' => count($psixologiyaLectureWeeks),
            'teacher_id' => 2], $psixologiyaLectureWeeks),
        compactTestCard($board, ['training_type' => 'practice', 'group_name' => 'g-01a',
            'subject_name' => 'Psixologiya', 'len_half' => 1,
            'weeks' => COMPACT_TOTAL_WEEKS, 'teacher_id' => 2]),
        compactTestCard($board, ['training_type' => 'practice', 'group_name' => 'g-01a',
            'subject_name' => 'Psixologiya', 'weeks' => count($psixologiyaStandInWeeks),
            'teacher_id' => 2], $psixologiyaStandInWeeks),
    ];

    $controller = new TimetableController();
    $controller->autoPlace(
        Request::create('/', 'POST', ['reset' => true, 'assign_rooms' => true]),
        $board
    );
    foreach (range(1, COMPACT_TOTAL_WEEKS) as $week) {
        $controller->compactWeek(Request::create('/', 'POST', ['week' => $week]), $board);
    }

    // 1-hafta — Psixologiya ma'ruzali, 2-hafta — Gigiyena ma'ruzali
    foreach ([1, 2] as $week) {
        $layout = compactTestWeekLayout($cards, $week);
        $slots = array_keys($layout);

        // Kun boshidan uzluksiz: bo'sh para (oyna) qolmaydi
        expect($slots)->toBe(range(1, count($slots)));

        // Har fan yaxlit: bo'laklari orasida boshqa fan turmaydi
        foreach (['Gigiyena', 'Psixologiya'] as $subject) {
            $own = array_keys(array_filter($layout, fn($name) => $name === $subject));
            expect($own)->not->toBeEmpty();
            expect($own)->toBe(range(min($own), max($own)));
        }
    }
});

test('zichlash shablonni o\'zgartirmaydi — faqat hafta istisnosi yoziladi', function () {
    $board = compactTestBoard();
    $lectureWeeks = [1, 3, 5, 7, 9];
    $lecture = compactTestCard($board, ['training_type' => 'lecture', 'group_names' => ['g-01a'],
        'subject_name' => 'Anatomiya', 'weeks' => count($lectureWeeks), 'teacher_id' => 1], $lectureWeeks);
    $practice = compactTestCard($board, ['training_type' => 'practice', 'group_name' => 'g-01a',
        'subject_name' => 'Anatomiya', 'weeks' => COMPACT_TOTAL_WEEKS, 'teacher_id' => 1]);

    $controller = new TimetableController();
    $controller->autoPlace(Request::create('/', 'POST', ['reset' => true]), $board);

    $templateBefore = [
        $lecture->fresh()->only(['day', 'pair']),
        $practice->fresh()->only(['day', 'pair']),
    ];

    $controller->compactWeek(Request::create('/', 'POST', ['week' => 2]), $board);

    expect([$lecture->fresh()->only(['day', 'pair']), $practice->fresh()->only(['day', 'pair'])])
        ->toBe($templateBefore);
});
