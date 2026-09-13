<?php

use App\Http\Controllers\Admin\TimetableController;
use App\Models\OqimSnapshot;
use App\Models\SubjectKafedraOverride;
use App\Models\TimetableBoard;
use App\Models\TimetableCard;
use App\Models\TimetableGridSetting;
use App\Models\TimetableSubjectSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * Klinik fanlar — UMUMIY karta: ma'ruza va amaliy bitta guruh kartasida yaratiladi
 * (o'quv bo'limi katta jadvalni tuzadi), kafedra karta ichida haftalar bo'yicha
 * ma'ruza/amaliyga ajratadi (lecture_weeks) va ma'ruza uchun o'qituvchi/xona beradi.
 */
const MIXED_TEST_WEEKS = 15;

function mixedTestBoard(array $attrs = []): TimetableBoard
{
    $board = TimetableBoard::create(array_merge([
        'name' => 'Test', 'academic_year' => '2026-2027', 'semester_parity' => 'kuzgi', 'kind' => 'real',
        'days' => 6, 'pairs_per_day' => 6, 'weeks' => MIXED_TEST_WEEKS,
    ], $attrs));
    TimetableGridSetting::create([
        'board_id' => $board->id, 'faculty_name' => '1-son davolash',
        'specialty_name' => 'Davolash ishi', 'course' => 3,
        'days' => 6, 'pairs_per_day' => 6, 'weeks' => MIXED_TEST_WEEKS,
    ]);

    return $board;
}

/** Ishchi reja (2024/2025 + 3-kurs − 1 = 2026 o'quv yili) va tasdiqlangan oqim snapshoti. */
function mixedTestCurriculum(array $subjects): void
{
    $curriculumId = DB::table('manual_curricula')->insertGetId([
        'type' => 'ishchi', 'name' => 'Davolash ishi ishchi', 'specialty_name' => 'Davolash ishi',
        'plan_year' => '2024/2025', 'level_code' => '3', 'created_at' => now(), 'updated_at' => now(),
    ]);
    foreach ($subjects as $s) {
        DB::table('manual_curriculum_subjects')->insert(array_merge([
            'manual_curriculum_id' => $curriculumId, 'kurs' => 3, 'semester' => 5,
            'lecture' => 0, 'practice' => 0, 'laboratory' => 0, 'seminar' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ], $s));
    }

    OqimSnapshot::create([
        'context_key' => 'test', 'status' => 'approved', 'approved_at' => now(),
        'context' => ['faculty' => '', 'projection' => 0],
        'data' => [[
            'merge_key' => 'x|Davolash ishi', 'title' => 'Davolash ishi', 'department_name' => '1-son davolash',
            'courses' => [[
                'level_code' => 3,
                'oqims' => [[
                    'label' => '1-oqim', 'lang' => 'uz', 'total' => 20,
                    'rows' => [['name' => 'd1/d24-01a', 'count' => 10], ['name' => 'd1/d24-01b', 'count' => 10]],
                ]],
            ]],
        ]],
    ]);
}

function mixedTestCard(TimetableBoard $board, array $attrs): TimetableCard
{
    return TimetableCard::create(array_merge([
        'board_id' => $board->id, 'faculty_name' => '1-son davolash',
        'specialty_name' => 'Davolash ishi', 'course' => 3,
        'oqim_label' => '1-oqim', 'lang' => 'uz',
        'training_type' => 'practice', 'is_mixed' => true,
        'subject_name' => 'Ichki kasalliklar propedevtikasi', 'kafedra_name' => 'Ichki kasalliklar',
        'students' => 10, 'len_half' => 2, 'start_half' => 0, 'weeks' => MIXED_TEST_WEEKS,
    ], $attrs));
}

function mixedTestTeacher(int $hemisId, string $name, string $department = 'Ichki kasalliklar'): int
{
    return DB::table('teachers')->insert(array_filter([
        'hemis_id' => $hemisId, 'full_name' => $name, 'short_name' => $name,
        'first_name' => $name, 'second_name' => $name, 'employee_id_number' => 'E' . $hemisId,
        'birth_date' => '1980-01-01', 'year_of_enter' => 2010, 'gender' => 'Erkak',
        'department' => $department, 'department_hemis_id' => 500,
        'employment_form' => 'asosiy', 'employment_staff' => '1', 'staff_position' => 'dotsent',
        'employee_status' => 'ishlayapti', 'employee_type' => 'oqituvchi',
        'contract_number' => 'C' . $hemisId, 'decree_number' => 'D' . $hemisId,
        'contract_date' => '2020-01-01', 'decree_date' => '2020-01-01',
        'created_at' => now(), 'updated_at' => now(),
    ], fn($v) => $v !== null)) ? (int) DB::table('teachers')->where('hemis_id', $hemisId)->value('id') : 0;
}

function mixedController(): TimetableController
{
    return new TimetableController();
}

/** Guruhning fan bo'yicha jami soati: Σ (karta uzunligi × faol haftalar). */
function mixedTestHours(TimetableBoard $board, string $subject, string $group): int
{
    $total = 0;
    foreach (TimetableCard::where('board_id', $board->id)->where('subject_name', $subject)->where('group_name', $group)->get() as $c) {
        $cancelled = DB::table('timetable_card_overrides')->where('card_id', $c->id)->where('cancelled', true)->count();
        $total += $c->lenHalf() * (MIXED_TEST_WEEKS - $cancelled);
    }
    return $total;
}

// ───────────────────────── Kartochka yaratish ─────────────────────────

test('klinik fan uchun ma\'ruza va amaliy bitta umumiy kartada yaratiladi', function () {
    $board = mixedTestBoard();
    mixedTestCurriculum([
        ['subject_name' => 'Ichki kasalliklar propedevtikasi', 'lecture' => 12, 'practice' => 60],
        ['subject_name' => 'Biokimyo', 'lecture' => 10, 'practice' => 30],
    ]);

    $response = mixedController()->generateCards($board);
    expect($response->getStatusCode())->toBe(200);

    // Klinik fan: oqim ma'ruza kartasi YO'Q, barcha kartalar umumiy (is_mixed) guruh kartalari
    $clinical = TimetableCard::where('board_id', $board->id)->where('subject_name', 'Ichki kasalliklar propedevtikasi')->get();
    expect($clinical)->not->toBeEmpty();
    expect($clinical->where('training_type', 'lecture')->count())->toBe(0);
    expect($clinical->every(fn($c) => $c->is_mixed && $c->training_type === 'practice' && $c->group_name))->toBeTrue();
    expect($clinical->every(fn($c) => $c->lecture_weeks === null))->toBeTrue();
    // Guruhning soati = ma'ruza + amaliy (12 + 60 = 72)
    expect(mixedTestHours($board, 'Ichki kasalliklar propedevtikasi', 'd1/d24-01a'))->toBe(72);
    expect(mixedTestHours($board, 'Ichki kasalliklar propedevtikasi', 'd1/d24-01b'))->toBe(72);

    // Oddiy fan — avvalgidek: oqimga ma'ruza kartasi + guruhlarga amaliy kartalar
    $plain = TimetableCard::where('board_id', $board->id)->where('subject_name', 'Biokimyo')->get();
    expect($plain->where('training_type', 'lecture')->count())->toBe(1);
    expect($plain->where('training_type', 'practice')->count())->toBeGreaterThan(0);
    expect($plain->every(fn($c) => !$c->is_mixed))->toBeTrue();
});

test('qo\'lda "klinik emas" belgisi umumiy kartani o\'chiradi, "klinik" belgisi oddiy fanni umumiy qiladi', function () {
    $board = mixedTestBoard();
    mixedTestCurriculum([
        ['subject_name' => 'Ichki kasalliklar propedevtikasi', 'lecture' => 12, 'practice' => 60],
        ['subject_name' => 'Biokimyo', 'lecture' => 10, 'practice' => 30],
    ]);
    SubjectKafedraOverride::create(['norm_name' => 'ichki kasalliklar propedevtikasi', 'kafedra_name' => '', 'is_clinical' => 0]);
    SubjectKafedraOverride::create(['norm_name' => 'biokimyo', 'kafedra_name' => '', 'is_clinical' => 1]);

    mixedController()->generateCards($board);

    $clinical = TimetableCard::where('board_id', $board->id)->where('subject_name', 'Ichki kasalliklar propedevtikasi')->get();
    expect($clinical->where('training_type', 'lecture')->count())->toBe(1);
    expect($clinical->every(fn($c) => !$c->is_mixed))->toBeTrue();

    $plain = TimetableCard::where('board_id', $board->id)->where('subject_name', 'Biokimyo')->get();
    expect($plain->where('training_type', 'lecture')->count())->toBe(0);
    expect($plain->every(fn($c) => $c->is_mixed))->toBeTrue();
    expect(mixedTestHours($board, 'Biokimyo', 'd1/d24-01a'))->toBe(40);
});

test('sikl rejimidagi klinik fan avvalgidek ma\'ruza/amaliy alohida yaratiladi', function () {
    $board = mixedTestBoard();
    mixedTestCurriculum([
        ['subject_name' => 'Ichki kasalliklar propedevtikasi', 'lecture' => 12, 'practice' => 60],
    ]);
    TimetableSubjectSetting::create([
        'board_id' => $board->id, 'specialty_name' => 'Davolash ishi', 'course' => 3,
        'subject_name' => 'Ichki kasalliklar propedevtikasi', 'mode' => 'cycle',
    ]);

    mixedController()->generateCards($board);

    $cards = TimetableCard::where('board_id', $board->id)->get();
    expect($cards->where('training_type', 'lecture')->count())->toBe(1);
    expect($cards->every(fn($c) => !$c->is_mixed))->toBeTrue();
});

test('doska ma\'lumotida umumiy karta va reja soati qaytadi', function () {
    $board = mixedTestBoard();
    mixedTestCurriculum([
        ['subject_name' => 'Ichki kasalliklar propedevtikasi', 'lecture' => 12, 'practice' => 60],
    ]);
    mixedController()->generateCards($board);

    $data = mixedController()->data($board)->getData(true);
    $card = collect($data['cards'])->first();
    expect($card['is_mixed'])->toBeTrue();
    expect($card['lecture_weeks'])->toBeNull();
    expect($data['plan_hours']['Davolash ishi|3|Ichki kasalliklar propedevtikasi'])->toBe(['lecture' => 12.0, 'practice' => 60.0]);
});

// ───────────────────────── Kafedra ajratishi ─────────────────────────

test('ajratish ma\'ruza haftalarini, o\'qituvchini va xonani oqimning shu vaqtdagi kartalariga yozadi', function () {
    $board = mixedTestBoard();
    $teacher = mixedTestTeacher(1001, 'Professor');
    DB::table('auditoriums')->insert(['code' => 'L1', 'name' => '№ 1 ma\'ruza zali', 'volume' => 100, 'active' => 1, 'created_at' => now(), 'updated_at' => now()]);

    $a = mixedTestCard($board, ['group_name' => 'd1/d24-01a', 'day' => 1, 'pair' => 1]);
    $b = mixedTestCard($board, ['group_name' => 'd1/d24-01b', 'day' => 1, 'pair' => 1]);
    $other = mixedTestCard($board, ['group_name' => 'd1/d24-01a', 'day' => 1, 'pair' => 3]);   // boshqa slot — tegilmaydi

    $res = mixedController()->splitCard(Request::create('/', 'POST', [
        'lecture_weeks' => '1,3,5,99', 'lecture_teacher_id' => $teacher, 'lecture_auditorium_code' => 'L1', 'apply_flow' => 1,
    ]), $a);
    expect($res->getStatusCode())->toBe(200);
    expect($res->getData(true)['updated'])->toBe(2);

    foreach ([$a, $b] as $c) {
        $c->refresh();
        expect($c->lectureWeekList())->toBe([1, 3, 5]);          // 99 — haftalar sonidan tashqarida, tashlab yuboriladi
        expect((int) $c->lecture_teacher_id)->toBe($teacher);
        expect($c->lecture_auditorium_code)->toBe('L1');
        expect($c->isLectureWeek(3))->toBeTrue();
        expect($c->isLectureWeek(2))->toBeFalse();
        expect($c->teacher_id)->toBeNull();                       // amaliy rekvizitlari o'zgarmaydi
    }
    expect($other->fresh()->lecture_weeks)->toBeNull();

    // reset — ajratish bekor qilinadi
    mixedController()->splitCard(Request::create('/', 'POST', ['reset' => 1]), $a);
    expect($a->fresh()->lecture_weeks)->toBeNull();
    expect($a->fresh()->lecture_teacher_id)->toBeNull();
});

test('ajratish oddiy kartaga qo\'llanmaydi', function () {
    $board = mixedTestBoard();
    $card = mixedTestCard($board, ['is_mixed' => false, 'group_name' => 'd1/d24-01a']);

    $res = mixedController()->splitCard(Request::create('/', 'POST', ['lecture_weeks' => '1']), $card);
    expect($res->getStatusCode())->toBe(422);
});

test('ma\'ruza o\'qituvchisi ma\'ruza haftasida band bo\'lsa ajratish rad etiladi, bir oqimning ma\'ruzasi esa umumiy', function () {
    $board = mixedTestBoard();
    $teacher = mixedTestTeacher(1002, 'Professor');

    $a = mixedTestCard($board, ['group_name' => 'd1/d24-01a', 'day' => 2, 'pair' => 1]);
    // Boshqa fan, boshqa guruh, xuddi shu vaqt — o'qituvchisi (amaliy) o'sha professor
    mixedTestCard($board, ['is_mixed' => false, 'subject_name' => 'Biokimyo', 'group_name' => 'd1/d24-02a',
        'oqim_label' => '2-oqim', 'day' => 2, 'pair' => 1, 'teacher_id' => $teacher, 'teacher_name' => 'Professor']);

    $res = mixedController()->splitCard(Request::create('/', 'POST', [
        'lecture_weeks' => '1,2', 'lecture_teacher_id' => $teacher,
    ]), $a);
    expect($res->getStatusCode())->toBe(422);
    expect($res->getData(true)['error'])->toContain("O'qituvchi band");
    expect($a->fresh()->lecture_weeks)->toBeNull();

    // Ma'ruzasiz haftalarda o'sha o'qituvchi band emas — 3-hafta ma'ruza bo'lishi mumkin
    $res = mixedController()->splitCard(Request::create('/', 'POST', [
        'lecture_weeks' => '3', 'lecture_teacher_id' => $teacher,
    ]), $a);
    expect($res->getStatusCode())->toBe(422);   // Biokimyo har hafta o'tiladi — 3-haftada ham band

    // Bir oqimning ikkinchi guruhi shu vaqtda o'sha professor bilan ma'ruza — bu bitta dars, to'qnashuv emas
    $sibling = mixedTestCard($board, ['group_name' => 'd1/d24-01b', 'day' => 3, 'pair' => 1,
        'lecture_weeks' => [4], 'lecture_teacher_id' => $teacher, 'lecture_teacher_name' => 'Professor']);
    $c = mixedTestCard($board, ['group_name' => 'd1/d24-01a', 'day' => 3, 'pair' => 1]);
    $res = mixedController()->splitCard(Request::create('/', 'POST', [
        'lecture_weeks' => '4', 'lecture_teacher_id' => $teacher,
    ]), $c);
    expect($res->getStatusCode())->toBe(200);
    expect($c->fresh()->lectureWeekList())->toBe([4]);
    expect($sibling->fresh()->lectureWeekList())->toBe([4]);
});

test('hafta bo\'yicha ko\'chirishda ma\'ruza haftasida ma\'ruza o\'qituvchisi hisobga olinadi', function () {
    $board = mixedTestBoard();
    $teacher = mixedTestTeacher(1003, 'Professor');

    // A: 2-haftada ma'ruza (professor), Dushanba 1-para
    mixedTestCard($board, ['group_name' => 'd1/d24-01a', 'day' => 1, 'pair' => 1,
        'lecture_weeks' => [2], 'lecture_teacher_id' => $teacher, 'lecture_teacher_name' => 'Professor']);
    // C: boshqa oqim/fan, amaliy o'qituvchisi — o'sha professor; Seshanba 1-para
    $c = mixedTestCard($board, ['is_mixed' => false, 'subject_name' => 'Biokimyo', 'group_name' => 'd1/d24-02a',
        'oqim_label' => '2-oqim', 'day' => 2, 'pair' => 1, 'teacher_id' => $teacher, 'teacher_name' => 'Professor']);

    // 2-haftada C ni A ning slotiga ko'chirish — professor ma'ruzada band
    $res = mixedController()->weekOverride(Request::create('/', 'POST', ['week' => 2, 'action' => 'move', 'day' => 1, 'pair' => 1]), $c);
    expect($res->getStatusCode())->toBe(422);
    // 3-haftada A amaliy (professor emas) — ko'chirish mumkin
    $res = mixedController()->weekOverride(Request::create('/', 'POST', ['week' => 3, 'action' => 'move', 'day' => 1, 'pair' => 1]), $c);
    expect($res->getStatusCode())->toBe(200);
});

test('biriktirish matritsasi umumiy kartaning ma\'ruza rekvizitlarini oqim kesimida alohida birlik qilib beradi', function () {
    $board = mixedTestBoard();
    $teacher = mixedTestTeacher(1004, 'Professor');
    mixedTestCard($board, ['group_name' => 'd1/d24-01a', 'day' => 1, 'pair' => 1, 'lecture_weeks' => [1]]);
    mixedTestCard($board, ['group_name' => 'd1/d24-01b']);

    $units = collect(mixedController()->teacherUnits($board)->getData(true)['units']);
    $lecture = $units->first(fn($u) => ($u['persona'] ?? null) === 'lecture');
    $practice = $units->where('persona', 'practice');
    expect($lecture)->not->toBeNull();
    expect($lecture['training_type'])->toBe('lecture');
    expect($lecture['oqim_label'])->toBe('1-oqim');
    expect($lecture['cards'])->toBe(2);
    expect($lecture['split_cards'])->toBe(1);
    expect($lecture['students'])->toBe(20);
    expect($practice->count())->toBe(2);

    // Ma'ruza o'qituvchisi oqimning barcha umumiy kartalariga yoziladi, amaliy o'qituvchi o'zgarmaydi
    $res = mixedController()->assignTeacher(Request::create('/', 'POST', [
        'faculty_name' => '1-son davolash', 'specialty_name' => 'Davolash ishi', 'course' => 3,
        'subject_name' => 'Ichki kasalliklar propedevtikasi', 'training_type' => 'lecture', 'persona' => 'lecture',
        'oqim_label' => '1-oqim', 'teacher_id' => $teacher,
    ]), $board);
    expect($res->getData(true)['affected'])->toBe(2);
    $cards = TimetableCard::where('board_id', $board->id)->get();
    expect($cards->every(fn($c) => (int) $c->lecture_teacher_id === $teacher && $c->teacher_id === null))->toBeTrue();
});
