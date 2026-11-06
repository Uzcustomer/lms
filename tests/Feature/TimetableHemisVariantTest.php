<?php

use App\Models\Teacher;
use App\Models\TimetableBoard;
use App\Models\TimetableCard;
use App\Models\TimetableGridSetting;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

// HEMIS solishtirish — bo'lakli fanlar ("Fan (a)/(b)/(c)"): bo'lak uchun birlik faqat guruh uni o'qisa (LMS kartasi, HEMIS darsi —
// balanslash belgisisiz — yoki talaba biriktiruvi); umumiy ma'ruza — faqat bitta (ega) bo'lakda; o'qilmaydigan bo'lakning ertangi
// HEMIS darslari — o'chirishga (not_studied), ogohlantirish bilan.

/** Jadvalga yozuv: majburiy (NOT NULL, sukutsiz) ustunlar soxta qiymat bilan to'ldiriladi. */
function hvInsert(string $table, array $data): void
{
    foreach (DB::select('SHOW COLUMNS FROM `' . $table . '`') as $col) {
        if ($col->Field === 'id' || array_key_exists($col->Field, $data) || $col->Null === 'YES' || $col->Default !== null) {
            continue;
        }
        $t = strtolower($col->Type);
        $data[$col->Field] = match (true) {
            str_contains($t, 'int') || str_contains($t, 'decimal') || str_contains($t, 'double') => 0,
            str_contains($t, 'datetime') || str_contains($t, 'timestamp') => now(),
            str_contains($t, 'date') => '2026-01-01',
            str_contains($t, 'json') => '[]',
            default => 'x',
        };
    }
    DB::table($table)->insert($data);
}

function hvTeacher(string $name, int $hemisId, array $extra = []): Teacher
{
    $t = new Teacher();
    $t->forceFill(array_merge([
        'hemis_id' => $hemisId, 'full_name' => $name, 'short_name' => $name, 'first_name' => $name, 'second_name' => $name,
        'employee_id_number' => (string) $hemisId, 'birth_date' => '1980-01-01', 'year_of_enter' => 2010,
        'gender' => 'm', 'department' => 'Boshqa', 'employment_form' => 'x', 'employment_staff' => 'x', 'staff_position' => 'x',
        'employee_status' => 'x', 'employee_type' => 'x', 'contract_number' => '1', 'decree_number' => '1',
        'contract_date' => '2020-01-01', 'decree_date' => '2020-01-01', 'is_active' => true,
        'status' => true, 'login' => 'hv' . $hemisId, 'password' => bcrypt('x'),
    ], $extra))->save();

    return $t;
}

// Bo'laklar subject_id: (a) 701, (b) 702, (c) 703.
const HV_SUBJECTS = ['a' => 701, 'b' => 702, 'c' => 703];

/**
 * 1-kurs, guruh p/st26-01a (HEMIS 1596, reja 250, 11-semestr), doska 14.09 dan 4 hafta. O'quv rejasida "Ichki kasalliklar (a)/(b)/(c)":
 * ma'ruza (11) + amaliy (13), KTR har hafta 2 + 2 soat. LMS: umumiy ma'ruza kartasi (asosiy nom, seshanba 08:30, LEKTOR).
 */
function hvSetup(): array
{
    Role::findOrCreate('admin', 'web');
    $reg = hvTeacher('REG', 4001);
    $reg->assignRole('admin');
    $teacher = hvTeacher('USTOZ', 5001, ['department_hemis_id' => 777]);
    $lector = hvTeacher('LEKTOR', 5003, ['department_hemis_id' => 777]);

    $board = TimetableBoard::create(['name' => 'T', 'academic_year' => '2026-2027', 'semester_parity' => 'kuzgi', 'days' => 6, 'pairs_per_day' => 12,
        'weeks' => 4, 'settings' => ['semester_start' => '2026-09-14']]);
    TimetableGridSetting::create(['board_id' => $board->id, 'faculty_name' => 'F1', 'specialty_name' => 'stomatologiya', 'course' => 1,
        'days' => 6, 'pairs_per_day' => 12, 'weeks' => 4]);
    hvInsert('groups', ['group_hemis_id' => 1596, 'name' => 'p/st26-01a', 'curriculum_hemis_id' => 250, 'department_hemis_id' => 1,
        'specialty_hemis_id' => 1, 'department_name' => 'F1']);
    $details = json_encode([['trainingType' => ['code' => '11', 'name' => "Ma'ruza"], 'academic_load' => 8],
        ['trainingType' => ['code' => '13', 'name' => 'Amaliy'], 'academic_load' => 8]]);
    $cs = [];
    foreach (HV_SUBJECTS as $l => $sid) {
        hvInsert('curriculum_subjects', ['curriculum_subject_hemis_id' => $sid, 'curricula_hemis_id' => 250, 'subject_id' => $sid,
            'subject_name' => "Ichki kasalliklar ({$l})", 'semester_code' => '11', 'semester_name' => '1', 'subject_details' => $details,
            'department_id' => 501, 'department_name' => 'Ichki', 'at_semester' => 1]);
        $cs[$l] = DB::table('curriculum_subjects')->where('subject_id', $sid)->value('id');
        DB::table('ktr_plans')->insert(['curriculum_subject_id' => $cs[$l], 'week_count' => 4,
            'plan_data' => json_encode(['hours' => array_fill_keys(['1', '2', '3', '4'], ['11' => 2, '13' => 2])]),
            'created_at' => now(), 'updated_at' => now()]);
    }
    $base = ['board_id' => $board->id, 'faculty_name' => 'F1', 'specialty_name' => 'stomatologiya', 'course' => 1, 'oqim_label' => '1-oqim',
        'lang' => 'rus', 'students' => 10, 'len_half' => 2, 'weeks' => 4, 'kafedra_name' => 'Ichki'];
    TimetableCard::create($base + ['training_type' => 'lecture', 'subject_name' => 'Ichki kasalliklar', 'group_name' => null,
        'group_names' => ['p/st26-01a (rus)'], 'day' => 2, 'pair' => 1, 'teacher_id' => $lector->id, 'teacher_name' => 'LEKTOR',
        'auditorium_code' => 'Y1', 'auditorium_name' => 'Y301']);

    return compact('reg', 'board', 'teacher', 'base', 'cs');
}

/** Bo'lakning amaliy kartasi (guruhda): $day kuni $pair-para. */
function hvPractice(array $s, string $letter, int $day, int $pair = 3): TimetableCard
{
    return TimetableCard::create($s['base'] + ['training_type' => 'practice', 'subject_name' => "Ichki kasalliklar ({$letter})",
        'group_name' => 'p/st26-01a (rus)', 'day' => $day, 'pair' => $pair, 'teacher_id' => $s['teacher']->id, 'teacher_name' => 'USTOZ',
        'auditorium_code' => '71', 'auditorium_name' => '423']);
}

/** HEMIS darsi (guruh 1596): bo'lak harfi, sana, boshlanish, tur (11 ma'ruza / 13 amaliy). */
function hvHemis(int $id, string $letter, string $date, string $start = '10:00', string $code = '13'): void
{
    $end = ['08:30' => '09:50', '10:00' => '11:20', '12:00' => '13:20', '13:30' => '14:50'][$start];
    hvInsert('schedules', ['schedule_hemis_id' => $id, 'subject_id' => HV_SUBJECTS[$letter], 'subject_name' => "Ichki kasalliklar ({$letter})",
        'semester_code' => '11', 'group_id' => 1596, 'group_name' => 'p/st26-01a', 'training_type_code' => $code,
        'training_type_name' => $code === '11' ? "Ma'ruza" : 'Amaliy', 'lesson_date' => $date . ' 00:00:00',
        'lesson_pair_start_time' => $start . ':00', 'lesson_pair_end_time' => $end . ':00', 'employee_id' => 5001, 'employee_name' => 'E5001',
        'auditorium_code' => '71', 'auditorium_name' => '423']);
}

/** LMS balanslash belgisi (avvalgi solishtirish HEMIS'ga yozgan dars). */
function hvBalanceMark(array $s, string $letter, string $date, string $start = '10:00', string $code = '13'): void
{
    $end = ['08:30' => '09:50', '10:00' => '11:20', '12:00' => '13:20', '13:30' => '14:50'][$start];
    DB::table('hemis_balance_lessons')->insert(['board_id' => $s['board']->id, 'group_hemis_id' => 1596, 'subject_id' => HV_SUBJECTS[$letter],
        'lesson_date' => $date, 'start_time' => $start, 'end_time' => $end, 'training_type_code' => $code, 'week' => 1,
        'created_at' => now(), 'updated_at' => now()]);
}

/** Talabaning bo'lakka biriktiruvi (student_subjects). */
function hvStudent(string $letter, int $hemisId = 880001): void
{
    if (!DB::table('students')->where('hemis_id', $hemisId)->exists()) {
        hvInsert('students', ['hemis_id' => $hemisId, 'group_name' => 'p/st26-01a', 'student_status_code' => 11]);
    }
    hvInsert('student_subjects', ['student_hemis_id' => $hemisId, 'subject_name' => "Ichki kasalliklar ({$letter})",
        'curriculum_subject_hemis_id' => HV_SUBJECTS[$letter], 'education_year' => '2026-2027']);
}

function hvRun(array $s, string $today = '2026-09-19', int $until = 0): array
{
    return test()->withoutVite()->actingAs($s['reg'], 'teacher')->withSession(['active_role' => 'admin'])
        ->postJson(route('admin.timetable.hemis-sync', $s['board']), ['courses' => [1], 'today' => $today, 'until_week' => $until])
        ->assertOk()->json();
}

function hvGroup(array $res): array
{
    return collect($res['groups'])->firstWhere('group', 'p/st26-01a');
}

function hvUnit(array $g, string $letter): ?array
{
    return collect($g['units'])->firstWhere('subject_name', "Ichki kasalliklar ({$letter})");
}

it("bo'lak: guruh faqat (b) ni o'qiydi — faqat (b) birligi (ma'ruza bilan), (a)/(c) — not_studied, balanslashsiz", function () {
    $s = hvSetup();
    hvPractice($s, 'b', 1);
    $g = hvGroup(hvRun($s));

    expect(array_column($g['units'], 'subject_name'))->toBe(['Ichki kasalliklar (b)']);
    $b = hvUnit($g, 'b');
    expect($b['codes'])->toBe(['11' => 'L', '13' => 'A'])
        ->and($b['lecture_note'] ?? null)->toBeNull()
        // Ma'ruza (seshanba 08:30) va amaliy (dushanba 10:00): 2–4-hafta + o'tgan 1-hafta uchun bittadan balanslash — faqat (b) KTR'i bo'yicha.
        ->and(collect($b['actions'])->where('op', 'create')->countBy('code')->sortKeys()->all())->toBe(['11' => 4, '13' => 4])
        ->and(collect($b['actions'])->where('balance', true)->count())->toBe(2);
    $skip = collect($g['skipped'])->keyBy('subject_name');
    foreach (['a', 'c'] as $l) {
        expect($skip["Ichki kasalliklar ({$l})"])->toBe(['subject_name' => "Ichki kasalliklar ({$l})",
            'reason' => "guruh bu bo'lakni o'qimaydi (karta yo‘q · HEMIS darsi yo‘q · talaba biriktirilmagan)", 'kind' => 'not_studied',
            'checks' => ['karta yo‘q', 'HEMIS darsi yo‘q', 'talaba biriktirilmagan']]);
    }
    expect($skip->has('Ichki kasalliklar (b)'))->toBeFalse();
});

it("bo'lak: guruh (a) va (c) ni o'qiydi — ikkala birlik, ma'ruza faqat (a) da; (c) da KTR ma'ruza soati ham, LMS ma'ruzasi ham yo'q", function () {
    $s = hvSetup();
    hvPractice($s, 'a', 1);
    hvPractice($s, 'c', 3);
    $g = hvGroup(hvRun($s));

    expect(array_column($g['units'], 'subject_name'))->toBe(['Ichki kasalliklar (a)', 'Ichki kasalliklar (c)']);
    $a = hvUnit($g, 'a');
    $c = hvUnit($g, 'c');
    expect($a['codes'])->toBe(['11' => 'L', '13' => 'A'])
        ->and(collect($a['actions'])->where('code', '11')->count())->toBe(4)
        ->and($c['codes'])->toBe(['13' => 'A'])
        ->and($c['code_names'])->toBe(['13' => 'Amaliy'])
        ->and(array_map('strval', array_keys($c['before']['ktr_hours'])))->toBe(['13'])
        ->and($c['before']['ktr_count']['L'] ?? 0)->toBe(0)
        ->and(collect($c['actions'])->pluck('code')->unique()->values()->all())->toBe(['13'])
        ->and($c['lecture_note'])->toBe("ma'ruza — (a) bo'lagida hisoblanadi");
    expect(collect($g['skipped'])->where('kind', 'not_studied')->pluck('subject_name')->all())->toBe(['Ichki kasalliklar (b)']);
});

it("bo'lak: umumiy ma'ruza egasi — HEMIS'da ma'ruzasi ko'p bo'lak; boshqasidagi HEMIS ma'ruzalariga tegilmaydi (guruh bandligi)", function () {
    $s = hvSetup();
    hvPractice($s, 'a', 1);
    hvPractice($s, 'c', 3);
    // (c): o'tgan 3 ta ma'ruza; (a): 1 ta o'tgan va 1 ta ertangi ma'ruza (seshanba 08:30 — LMS ma'ruzasi bilan bir vaqtda).
    hvHemis(31, 'c', '2026-09-15', '08:30', '11');
    hvHemis(32, 'c', '2026-09-16', '08:30', '11');
    hvHemis(33, 'c', '2026-09-18', '08:30', '11');
    hvHemis(41, 'a', '2026-09-17', '08:30', '11');
    hvHemis(42, 'a', '2026-09-22', '08:30', '11');
    $g = hvGroup(hvRun($s));

    $a = hvUnit($g, 'a');
    $c = hvUnit($g, 'c');
    expect($c['codes'])->toBe(['11' => 'L', '13' => 'A'])
        ->and($c['lecture_note'] ?? null)->toBeNull()
        ->and($a['codes'])->toBe(['13' => 'A'])
        ->and($a['lecture_note'])->toBe("ma'ruza — (c) bo'lagida hisoblanadi")
        // (a) ning HEMIS ma'ruzalari — amal yo'q (o'chirilmaydi ham).
        ->and(collect($a['actions'])->pluck('hemis_id')->filter()->all())->not->toContain(41)->not->toContain(42)
        ->and(collect($c['actions'])->pluck('hemis_id')->filter()->all())->not->toContain(42);
});

it("bo'lak: HEMIS'da (a) darsi bor, LMS'da (a) amaliy kartasi yo'q — birlik bor, joylanmagan haftalar lms_unplaced qoidasi bilan", function () {
    $s = hvSetup();
    hvPractice($s, 'b', 1);
    hvHemis(51, 'a', '2026-09-16');   // o'tgan — dalil
    hvHemis(52, 'a', '2026-09-30');   // 3-hafta — LMS'da (a) amaliysi yo'q
    $g = hvGroup(hvRun($s));

    $a = hvUnit($g, 'a');
    expect($a)->not->toBeNull()
        // Ma'ruza egasi — harf tartibida (a) (HEMIS ma'ruzasi hech birida yo'q).
        ->and($a['codes'])->toBe(['11' => 'L', '13' => 'A'])
        ->and(hvUnit($g, 'b')['codes'])->toBe(['13' => 'A'])
        ->and(collect($a['actions'])->firstWhere('hemis_id', 52))->toMatchArray(['op' => 'delete', 'reason_code' => 'lms_unplaced']);
    expect(collect($g['skipped'])->where('kind', 'not_studied')->pluck('subject_name')->all())->toBe(['Ichki kasalliklar (c)']);
});

it("bo'lak: balanslash belgisi bor HEMIS yozuvi dalil emas — ertangisi not_studied bilan o'chiriladi (muddat ichida), ogohlantirishda", function () {
    $s = hvSetup();
    hvPractice($s, 'b', 1);
    // (c): avvalgi noto'g'ri sinxron yozgan balanslash darslari — biri o'tgan, ikkitasi ertangi (2- va 4-hafta).
    foreach ([[61, '2026-09-17'], [62, '2026-09-24'], [63, '2026-10-08']] as [$id, $date]) {
        hvHemis($id, 'c', $date);
        hvBalanceMark($s, 'c', $date);
    }
    $res = hvRun($s, '2026-09-19', 3);
    $g = hvGroup($res);

    expect(collect($g['skipped'])->firstWhere('subject_name', 'Ichki kasalliklar (c)'))
        ->toMatchArray(['kind' => 'not_studied', 'checks' => ['karta yo‘q', 'HEMIS darsi yo‘q', 'talaba biriktirilmagan']]);
    $c = hvUnit($g, 'c');
    expect($c)->not->toBeNull()
        ->and($c['not_studied'])->toBeTrue()
        ->and($c['not_studied_checks'])->toBe(['karta yo‘q', 'HEMIS darsi yo‘q', 'talaba biriktirilmagan'])
        ->and($c['period'])->toBeNull()
        ->and($c['fixes'])->toBe([])
        ->and($c['before']['hours_diff'])->toBe([])
        // Faqat o'chirish: muddat (3-hafta) ichidagisi — hozir, 4-haftadagisi — keyinroq; o'tgan darsga tegilmaydi.
        ->and(array_map(fn ($a) => [$a['op'], $a['hemis_id'], $a['reason_code'], $a['reason'], $a['week']], $c['actions']))
        ->toBe([['delete', 62, 'not_studied', "guruh bu bo'lakni o'qimaydi", 2]])
        ->and($c['actions_later'])->toBe(1)
        ->and($res['unplaced_deletes'])->toBe(['count' => 1, 'by_reason' => ['lms_unplaced' => 0, 'not_studied' => 1], 'by_unit' => [
            ['group' => 'p/st26-01a', 'subject' => 'Ichki kasalliklar (c)', 'part' => 'Amaliy qism', 'reason_code' => 'not_studied', 'weeks' => '2', 'count' => 1]]]);
    // (b) birligi balanslanmaydi (c) darslari hisobiga: (c) yozuvlari (b) rejasiga kirmaydi.
    expect(collect(hvUnit($g, 'b')['actions'])->pluck('hemis_id')->filter()->intersect([61, 62, 63])->all())->toBe([]);
});

it("bo'lak: talaba biriktiruvi (student_subjects) — qo'shimcha dalil: kartasi yo'q bo'lsa ham bo'lak o'qiladi", function () {
    $s = hvSetup();
    hvPractice($s, 'b', 1);
    hvStudent('c');
    $g = hvGroup(hvRun($s));

    expect(hvUnit($g, 'c'))->toBeNull();   // LMS kartasi va HEMIS darsi yo'q — birlik uchun reja yo'q (avvalgidek)
    expect(collect($g['skipped'])->firstWhere('subject_name', 'Ichki kasalliklar (c)')['kind'] ?? null)->not->toBe('not_studied')
        ->and(collect($g['skipped'])->firstWhere('subject_name', 'Ichki kasalliklar (a)'))
        ->toMatchArray(['kind' => 'not_studied', 'checks' => ['karta yo‘q', 'HEMIS darsi yo‘q', 'talaba biriktirilmagan']]);
});

it("bo'laksiz fan — avvalgidek (regressiya): ma'ruza va amaliy bitta birlikda, skipped'da not_studied yo'q", function () {
    $s = hvSetup();
    DB::table('curriculum_subjects')->where('subject_id', 702)->update(['subject_name' => 'Ichki kasalliklar']);
    DB::table('curriculum_subjects')->whereIn('subject_id', [701, 703])->delete();
    TimetableCard::create($s['base'] + ['training_type' => 'practice', 'subject_name' => 'Ichki kasalliklar', 'group_name' => 'p/st26-01a (rus)',
        'day' => 1, 'pair' => 3, 'teacher_id' => $s['teacher']->id, 'teacher_name' => 'USTOZ', 'auditorium_code' => '71', 'auditorium_name' => '423']);
    $g = hvGroup(hvRun($s));

    expect(array_column($g['units'], 'subject_name'))->toBe(['Ichki kasalliklar'])
        ->and($g['units'][0]['codes'])->toBe(['11' => 'L', '13' => 'A'])
        ->and($g['units'][0]['lecture_note'] ?? null)->toBeNull()
        ->and(collect($g['skipped'])->where('kind', 'not_studied')->all())->toBe([]);
});

/**
 * 5-kurs sikl fani "Pediatriya" (2 kun, 21.09 dan): o'quv rejasida "Pediatriya (a)" (901) va "(b)" (902) — ma'ruza (11) + amaliy (13),
 * KTR: 1-kun 2 soat ma'ruza + 4 soat amaliy, 2-kun 6 soat amaliy. LMS: guruh g5-01a da faqat (b) amaliy sikl kartasi, umumiy ma'ruza
 * (asosiy nom).
 */
function hvCycleSetup(array $s): void
{
    // Sikl sozlamasi — kartalar nomi bo'yicha: bo'laklar va umumiy ma'ruza (asosiy nom).
    foreach (['Pediatriya', 'Pediatriya (a)', 'Pediatriya (b)'] as $name) {
        \App\Models\TimetableSubjectSetting::create(['board_id' => $s['board']->id, 'specialty_name' => 'davolash ishi', 'course' => 5,
            'subject_name' => $name, 'mode' => 'cycle', 'cycle_days' => 2]);
    }
    $lector = Teacher::where('full_name', 'LEKTOR')->first();
    $base = ['board_id' => $s['board']->id, 'faculty_name' => 'F1', 'specialty_name' => 'davolash ishi', 'course' => 5, 'oqim_label' => '1-oqim',
        'lang' => 'uz', 'students' => 10, 'len_half' => 2, 'weeks' => 15, 'kafedra_name' => 'P'];
    TimetableCard::create($base + ['training_type' => 'practice', 'subject_name' => 'Pediatriya (b)', 'group_name' => 'g5-01a',
        'teacher_id' => $s['teacher']->id, 'teacher_name' => 'USTOZ', 'auditorium_code' => '71', 'auditorium_name' => '423']);
    TimetableCard::create($base + ['training_type' => 'lecture', 'subject_name' => 'Pediatriya', 'group_name' => null, 'group_names' => ['g5-01a'],
        'teacher_id' => $lector->id, 'teacher_name' => 'LEKTOR', 'auditorium_code' => 'Y1', 'auditorium_name' => 'Y301']);
    \App\Models\TimetableCyclePlacement::create(['board_id' => $s['board']->id, 'specialty_name' => 'davolash ishi', 'course' => 5,
        'group_name' => 'F1 / 1-oqim', 'subject_name' => 'Pediatriya (b)', 'start_index' => 7, 'training_type' => 'practice']);   // 21.09
    hvInsert('specialties', ['specialty_hemis_id' => 77, 'name' => 'davolash ishi']);
    hvInsert('curricula', ['curricula_hemis_id' => 260, 'specialty_hemis_id' => 77, 'department_hemis_id' => 1]);
    hvInsert('semesters', ['curriculum_hemis_id' => 260, 'code' => '19', 'education_year' => '2026', 'level_code' => '15']);
    hvInsert('groups', ['group_hemis_id' => 2500, 'name' => 'g5-01a', 'curriculum_hemis_id' => 260, 'department_hemis_id' => 1, 'specialty_hemis_id' => 77]);
    foreach (['a' => 901, 'b' => 902] as $l => $sid) {
        hvInsert('curriculum_subjects', ['curriculum_subject_hemis_id' => $sid, 'curricula_hemis_id' => 260, 'subject_id' => $sid,
            'subject_name' => "Pediatriya ({$l})", 'semester_code' => '19', 'semester_name' => '9', 'department_id' => 501, 'department_name' => 'Pediatriya',
            'at_semester' => 1, 'subject_details' => json_encode([['trainingType' => ['code' => '11', 'name' => "Ma'ruza"], 'academic_load' => 2],
                ['trainingType' => ['code' => '13', 'name' => 'Amaliy'], 'academic_load' => 10]])]);
        DB::table('ktr_plans')->insert(['curriculum_subject_id' => DB::table('curriculum_subjects')->where('subject_id', $sid)->value('id'), 'week_count' => 2,
            'plan_data' => json_encode(['hours' => ['1' => ['11' => 2, '13' => 4], '2' => ['13' => 6]]]), 'created_at' => now(), 'updated_at' => now()]);
    }
}

function hvCycleHemis(int $id, string $letter, string $date, string $start, string $code): void
{
    $end = ['08:30' => '09:50', '10:00' => '11:20', '12:00' => '13:20', '13:30' => '14:50'][$start];
    hvInsert('schedules', ['schedule_hemis_id' => $id, 'subject_id' => ['a' => 901, 'b' => 902][$letter], 'subject_name' => "Pediatriya ({$letter})",
        'semester_code' => '19', 'group_id' => 2500, 'group_name' => 'g5-01a', 'training_type_code' => $code,
        'training_type_name' => $code === '11' ? "Ma'ruza" : 'Amaliy', 'lesson_date' => $date . ' 00:00:00', 'lesson_pair_start_time' => $start . ':00',
        'lesson_pair_end_time' => $end . ':00', 'employee_id' => 5001, 'employee_name' => 'E5001', 'auditorium_code' => '71', 'auditorium_name' => '423']);
}

function hvCycleRun(array $s): array
{
    $res = test()->withoutVite()->actingAs($s['reg'], 'teacher')->withSession(['active_role' => 'admin'])
        ->postJson(route('admin.timetable.hemis-sync', $s['board']), ['courses' => [5], 'today' => '2026-09-19', 'until_week' => 0])
        ->assertOk()->json();

    return ['res' => $res, 'g' => collect($res['groups'])->firstWhere('group', 'g5-01a')];
}

it("bo'lak, sikl: guruh (b) siklini o'qiydi — (b) birligi (sikl, ma'ruza bilan), (a) — not_studied; (a) ning belgili ertangi darsi o'chiriladi", function () {
    $s = hvSetup();
    hvCycleSetup($s);
    DB::table('hemis_balance_lessons')->insert(['board_id' => $s['board']->id, 'group_hemis_id' => 2500, 'subject_id' => 901,
        'lesson_date' => '2026-09-24', 'start_time' => '10:00', 'end_time' => '11:20', 'training_type_code' => '13', 'week' => 1,
        'created_at' => now(), 'updated_at' => now()]);
    hvCycleHemis(71, 'a', '2026-09-24', '10:00', '13');
    ['res' => $res, 'g' => $g] = hvCycleRun($s);

    $b = collect($g['units'])->firstWhere('subject_name', 'Pediatriya (b)');
    expect($b['cycle'])->toBeTrue()
        ->and($b['codes'])->toBe(['11' => 'L', '13' => 'A'])
        ->and(collect($b['actions'])->where('code', '11')->pluck('op')->all())->toBe(['create']);
    expect(collect($g['skipped'])->firstWhere('subject_name', 'Pediatriya (a)'))->toMatchArray(['kind' => 'not_studied',
        'reason' => "guruh bu bo'lakni o'qimaydi (karta yo‘q · HEMIS darsi yo‘q · talaba biriktirilmagan)"]);
    $a = collect($g['units'])->firstWhere('subject_name', 'Pediatriya (a)');
    expect($a['not_studied'])->toBeTrue()
        ->and(array_map(fn ($x) => [$x['op'], $x['hemis_id'], $x['reason_code']], $a['actions']))->toBe([['delete', 71, 'not_studied']])
        ->and($res['unplaced_deletes']['by_reason'])->toBe(['lms_unplaced' => 0, 'not_studied' => 1]);
});

it("bo'lak, sikl: ma'ruza egasi (a) (HEMIS ma'ruzalari) — (b) sikl blokida ma'ruza yo'q, izoh bilan; (a) — joylanmagan (not_studied emas)", function () {
    $s = hvSetup();
    hvCycleSetup($s);
    hvCycleHemis(81, 'a', '2026-09-15', '08:30', '11');
    hvCycleHemis(82, 'a', '2026-09-16', '08:30', '11');
    ['g' => $g] = hvCycleRun($s);

    $b = collect($g['units'])->firstWhere('subject_name', 'Pediatriya (b)');
    expect($b['cycle'])->toBeTrue()
        ->and($b['codes'])->toBe(['13' => 'A'])
        ->and(collect($b['actions'])->pluck('code')->unique()->values()->all())->toBe(['13'])
        ->and($b['lecture_note'])->toBe("ma'ruza — (a) bo'lagida hisoblanadi");
    expect(collect($g['skipped'])->firstWhere('subject_name', 'Pediatriya (a)')['kind'])->toBe('unplaced');
});

it("bo'lak, Excel: o'qimaydigan bo'lak Balansga chiqmaydi — \"Amallar\"da faqat o'chirish (sababi bilan); ma'ruza izohi — Izohlarda", function () {
    $s = hvSetup();
    hvPractice($s, 'a', 1);
    hvPractice($s, 'c', 3);
    hvHemis(91, 'b', '2026-09-24');
    hvBalanceMark($s, 'b', '2026-09-24');
    $file = tempnam(sys_get_temp_dir(), 'hv') . '.xlsx';
    file_put_contents($file, test()->withoutVite()->actingAs($s['reg'], 'teacher')->withSession(['active_role' => 'admin'])
        ->post(route('admin.timetable.hemis-sync.export', $s['board']), ['courses' => [1], 'today' => '2026-09-19', 'until_week' => 0])
        ->assertOk()->baseResponse->getFile()->getContent());
    $book = \PhpOffice\PhpSpreadsheet\IOFactory::load($file);
    @unlink($file);
    $bal = collect($book->getSheetByName('Balans')->toArray())->slice(1);
    $act = collect($book->getSheetByName('Amallar')->toArray())->slice(1);

    expect($bal->pluck(1)->unique()->values()->all())->toBe(['Ichki kasalliklar (a)', 'Ichki kasalliklar (c)'])
        ->and($bal->where(1, 'Ichki kasalliklar (c)')->pluck(3)->all())->toBe(['Amaliy'])
        ->and($bal->where(1, 'Ichki kasalliklar (c)')->first()[15])->toBe("ma'ruza — (a) bo'lagida hisoblanadi")
        ->and($act->where(1, 'Ichki kasalliklar (b)')->map(fn ($r) => [$r[2], $r[3], $r[10], $r[14]])->values()->all())
        ->toBe([["O'chirish", '2026-09-24', '91', "guruh bu bo'lakni o'qimaydi"]]);
});
