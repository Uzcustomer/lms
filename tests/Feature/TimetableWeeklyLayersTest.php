<?php

use App\Models\Teacher;
use App\Models\TimetableBoard;
use App\Models\TimetableCard;
use App\Models\TimetableGridSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/** Haftalik jadval: tashqi (o'quv bo'limi, ext_day/ext_pair) va ichki (kafedra, day/pair) qatlamlar. */
function layersSetup(): array
{
    foreach (['kafedra_mudiri', 'oquv_bolimi', 'superadmin'] as $r) {
        Role::findOrCreate($r, 'web');
    }
    DB::table('departments')->insert(['department_hemis_id' => 501, 'name' => 'Gigiyena kafedrasi', 'code' => 'G1', 'structure_type_code' => 12,
        'structure_type_name' => 'Kafedra', 'locality_type_code' => 11, 'locality_type_name' => 'M', 'active' => 1, 'created_at' => now(), 'updated_at' => now()]);
    $mk = function (string $name, string $role, array $extra = []) {
        $t = new Teacher();
        $t->forceFill(array_merge([
            'hemis_id' => random_int(100000, 999999), 'full_name' => $name, 'short_name' => $name, 'first_name' => $name, 'second_name' => $name,
            'employee_id_number' => (string) random_int(100000, 999999), 'birth_date' => '1980-01-01', 'year_of_enter' => 2010,
            'gender' => 'm', 'department' => 'Boshqa', 'employment_form' => 'x', 'employment_staff' => 'x', 'staff_position' => 'x',
            'employee_status' => 'x', 'employee_type' => 'x', 'contract_number' => '1', 'decree_number' => '1',
            'contract_date' => '2020-01-01', 'decree_date' => '2020-01-01',
            'status' => true, 'login' => 'u' . random_int(100000, 999999), 'password' => bcrypt('x'),
        ], $extra))->save();
        $t->assignRole($role);

        return $t;
    };
    $head = $mk('MUDIR', 'kafedra_mudiri', ['department_hemis_id' => 501]);
    $oquv = $mk('OQUV', 'oquv_bolimi');
    $admin = $mk('ADMIN', 'superadmin');
    $board = TimetableBoard::create(['name' => 'T', 'academic_year' => '2026-2027', 'semester_parity' => 'kuzgi', 'days' => 6, 'pairs_per_day' => 12, 'weeks' => 15]);
    TimetableGridSetting::create(['board_id' => $board->id, 'faculty_name' => 'F1', 'specialty_name' => 'davolash ishi', 'course' => 2,
        'days' => 6, 'pairs_per_day' => 12, 'weeks' => 15]);
    $base = ['board_id' => $board->id, 'faculty_name' => 'F1', 'specialty_name' => 'davolash ishi', 'course' => 2, 'oqim_label' => '1-oqim',
        'lang' => 'uz', 'students' => 15, 'training_type' => 'practice', 'len_half' => 2, 'weeks' => 15];
    $own = TimetableCard::create($base + ['subject_name' => 'Gigiyena', 'group_name' => 'g-01a', 'kafedra_name' => 'Gigiyena kafedrasi']);
    $foreign = TimetableCard::create($base + ['subject_name' => 'Anatomiya', 'group_name' => 'g-01a', 'kafedra_name' => 'Anatomiya kafedrasi']);

    return compact('head', 'oquv', 'admin', 'board', 'own', 'foreign');
}

function layerAs(Teacher $t, string $role)
{
    return test()->withoutVite()->actingAs($t, 'teacher')->withSession(['active_role' => $role]);
}

it('o\'quv bo\'limi tashqi jadvalga qo\'yadi, ichki jadvalga tegmaydi', function () {
    $s = layersSetup();
    layerAs($s['oquv'], 'oquv_bolimi')->postJson(route('admin.timetable.cards.place', $s['own']), ['day' => 2, 'pair' => 3, 'layer' => 'external'])->assertOk();
    $c = $s['own']->fresh();
    expect([$c->ext_day, $c->ext_pair, $c->day])->toBe([2, 3, null]);

    // Tashqida guruh bandligi tekshiriladi
    layerAs($s['oquv'], 'oquv_bolimi')->postJson(route('admin.timetable.cards.place', $s['foreign']), ['day' => 2, 'pair' => 4, 'layer' => 'external'])
        ->assertStatus(422);
    // Ichki jadvalni o'quv bo'limi tahrirlay olmaydi
    layerAs($s['oquv'], 'oquv_bolimi')->postJson(route('admin.timetable.cards.place', $s['own']), ['day' => 1, 'pair' => 1, 'layer' => 'internal'])
        ->assertForbidden();
});

it('kafedra mudiri faqat o\'z kafedrasi kartasini ichki jadvalga qo\'yadi', function () {
    $s = layersSetup();
    layerAs($s['head'], 'kafedra_mudiri')->postJson(route('admin.timetable.cards.place', $s['own']), ['day' => 1, 'pair' => 1])->assertOk();
    expect($s['own']->fresh()->day)->toBe(1)->and($s['own']->fresh()->ext_day)->toBeNull();

    layerAs($s['head'], 'kafedra_mudiri')->postJson(route('admin.timetable.cards.place', $s['foreign']), ['day' => 3, 'pair' => 1])->assertForbidden();
    layerAs($s['head'], 'kafedra_mudiri')->postJson(route('admin.timetable.cards.place', $s['own']), ['day' => 2, 'pair' => 1, 'layer' => 'external'])->assertForbidden();
});

it('admin hozirgi joylashuvni tashqi qatlamga ko\'chirib, ichkini bo\'shatadi', function () {
    $s = layersSetup();
    $s['own']->update(['day' => 4, 'pair' => 7]);
    layerAs($s['admin'], 'superadmin')->postJson(route('admin.timetable.weekly-layer-copy', $s['board']), ['courses' => [2], 'clear_internal' => 1])
        ->assertOk()->assertJson(['copied' => 1]);
    $c = $s['own']->fresh();
    expect([$c->ext_day, $c->ext_pair, $c->day, $c->pair])->toBe([4, 7, null, null]);

    layerAs($s['head'], 'kafedra_mudiri')->postJson(route('admin.timetable.weekly-layer-copy', $s['board']), ['courses' => [2]])->assertForbidden();
});

it('qatlamga ko\'chirish va tashqi bo\'shatish tarixga yoziladi, dry_run yozilmaydi', function () {
    $s = layersSetup();
    $s['own']->update(['day' => 4, 'pair' => 7]);
    layerAs($s['admin'], 'superadmin')->postJson(route('admin.timetable.weekly-layer-copy', $s['board']), ['courses' => [2], 'dry_run' => 1])
        ->assertOk()->assertJson(['dry_run' => true]);
    expect(\App\Models\TimetableChangeRequest::count())->toBe(0);

    layerAs($s['admin'], 'superadmin')->postJson(route('admin.timetable.weekly-layer-copy', $s['board']), ['courses' => [2], 'clear_internal' => 1])->assertOk();
    layerAs($s['oquv'], 'oquv_bolimi')->postJson(route('admin.timetable.boards.unplace', $s['board']), ['courses' => [2], 'layer' => 'external'])->assertOk();

    expect(\App\Models\TimetableChangeRequest::orderBy('id')->pluck('summary')->all())->toBe([
        'Haftalik joylashuv tashqi jadvalga ko\'chirildi, ichki jadval bo\'shatildi: 2-kurs',
        'Tashqi jadval · Joylashuvlarni bo\'shatish: 2-kurs',
    ]);
});

it('kafedrasi aniqlanmagan mudir ichki jadvalni o\'zgartira olmaydi (yopiq holat)', function () {
    $s = layersSetup();
    $lost = new Teacher();
    $lost->forceFill([
        'hemis_id' => 990001, 'full_name' => 'YOQOLGAN', 'short_name' => 'Y', 'first_name' => 'Y', 'second_name' => 'Y',
        'employee_id_number' => '990001', 'birth_date' => '1980-01-01', 'year_of_enter' => 2010,
        'gender' => 'm', 'department' => '', 'employment_form' => 'x', 'employment_staff' => 'x', 'staff_position' => 'x',
        'employee_status' => 'x', 'employee_type' => 'x', 'contract_number' => '1', 'decree_number' => '1',
        'contract_date' => '2020-01-01', 'decree_date' => '2020-01-01', 'status' => true, 'login' => 'lost990001', 'password' => bcrypt('x'),
    ])->save();
    $lost->assignRole('kafedra_mudiri');
    $s['foreign']->update(['day' => 2, 'pair' => 1]);

    layerAs($lost, 'kafedra_mudiri')->postJson(route('admin.timetable.cards.place', $s['foreign']), ['day' => 3, 'pair' => 1])->assertForbidden();
    layerAs($lost, 'kafedra_mudiri')->postJson(route('admin.timetable.boards.unplace', $s['board']), ['courses' => [2], 'layer' => 'internal'])->assertForbidden();
    expect($s['foreign']->fresh()->only(['day', 'pair']))->toBe(['day' => 2, 'pair' => 1]);
});

it('qayta ko\'chirishda ichkidan olingan kartaning eski tashqi joyi tozalanadi', function () {
    $s = layersSetup();
    $s['own']->update(['ext_day' => 2, 'ext_pair' => 3]);            // ichkida joylashmagan
    $s['foreign']->update(['day' => 1, 'pair' => 1]);

    layerAs($s['admin'], 'superadmin')->postJson(route('admin.timetable.weekly-layer-copy', $s['board']), ['courses' => [2]])
        ->assertOk()->assertJson(['copied' => 1]);

    expect($s['own']->fresh()->only(['ext_day', 'ext_pair']))->toBe(['ext_day' => null, 'ext_pair' => null])
        ->and($s['foreign']->fresh()->only(['ext_day', 'ext_pair', 'day']))->toBe(['ext_day' => 1, 'ext_pair' => 1, 'day' => 1]);
});

it('joylanmagan kartalar eksporti tanlangan qatlam bo\'yicha', function () {
    $s = layersSetup();
    $s['own']->update(['ext_day' => 2, 'ext_pair' => 3, 'teacher_name' => 'MAXFIY O.']);   // tashqida joylashgan, ichkida yo'q
    $s['foreign']->update(['day' => 1, 'pair' => 1]);                                       // ichkida joylashgan, tashqida yo'q

    $rowsOf = function (string $layer) use ($s) {
        $res = layerAs($s['admin'], 'superadmin')->get(route('admin.timetable.boards.unplaced-export', $s['board']) . '?layer=' . $layer)->assertOk();
        $file = tempnam(sys_get_temp_dir(), 'unp') . '.xlsx';
        file_put_contents($file, $res->streamedContent());
        $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file)->getActiveSheet();
        @unlink($file);

        return collect($sheet->toArray())->slice(2)->map(fn ($r) => [$r[6], $r[10]])->values()->all();
    };

    expect($rowsOf('external'))->toBe([['Anatomiya', '—']])
        ->and($rowsOf('internal'))->toBe([['Gigiyena', 'MAXFIY O.']]);
});

it('tashqi jadvalda haftalari kesishmaydigan kartalar bir katakni bo\'lisha oladi', function () {
    $s = layersSetup();
    // own: 1-7 hafta, foreign: 8-15 hafta ("BKP 7 / Umumiy xirurgiya 8" kabi)
    foreach (range(8, 15) as $w) {
        \App\Models\TimetableCardOverride::create(['card_id' => $s['own']->id, 'week' => $w, 'cancelled' => true]);
    }
    foreach (range(1, 7) as $w) {
        \App\Models\TimetableCardOverride::create(['card_id' => $s['foreign']->id, 'week' => $w, 'cancelled' => true]);
    }
    layerAs($s['oquv'], 'oquv_bolimi')->postJson(route('admin.timetable.cards.place', $s['own']), ['day' => 2, 'pair' => 3, 'layer' => 'external'])->assertOk();
    layerAs($s['oquv'], 'oquv_bolimi')->postJson(route('admin.timetable.cards.place', $s['foreign']), ['day' => 2, 'pair' => 3, 'layer' => 'external'])->assertOk();

    // Bitta umumiy hafta bo'lsa — konflikt
    \App\Models\TimetableCardOverride::where('card_id', $s['foreign']->id)->where('week', 7)->delete();
    layerAs($s['oquv'], 'oquv_bolimi')->postJson(route('admin.timetable.cards.place', $s['foreign']), ['day' => 2, 'pair' => 4, 'layer' => 'external'])->assertStatus(422);
});

it('haftalar kesishmasi doska emas, yo\'nalish panjarasi haftalari bo\'yicha', function () {
    $s = layersSetup();
    // Doska 18 hafta, yo'nalish panjarasi 15 hafta: istisnolar 15-haftagacha yoziladi,
    // 16-18-haftalar bu kartalar uchun yo'q — ularni "umumiy faol hafta" deb hisoblamaslik kerak.
    $s['board']->update(['weeks' => 18]);
    foreach (range(8, 15) as $w) {
        \App\Models\TimetableCardOverride::create(['card_id' => $s['own']->id, 'week' => $w, 'cancelled' => true]);
    }
    foreach (range(1, 7) as $w) {
        \App\Models\TimetableCardOverride::create(['card_id' => $s['foreign']->id, 'week' => $w, 'cancelled' => true]);
    }
    layerAs($s['oquv'], 'oquv_bolimi')->postJson(route('admin.timetable.cards.place', $s['own']), ['day' => 2, 'pair' => 3, 'layer' => 'external'])->assertOk();
    layerAs($s['oquv'], 'oquv_bolimi')->postJson(route('admin.timetable.cards.place', $s['foreign']), ['day' => 2, 'pair' => 3, 'layer' => 'external'])->assertOk();
});

it('bitta fanning turli bo\'laklari bir katakda parallel tura oladi, boshqa fan esa yo\'q', function () {
    $s = layersSetup();
    $s['own']->update(['subject_name' => 'Terapiya (a)']);
    $s['foreign']->update(['subject_name' => 'Terapiya (c)']);
    layerAs($s['admin'], 'superadmin')->postJson(route('admin.timetable.cards.place', $s['own']), ['day' => 2, 'pair' => 3, 'layer' => 'external'])->assertOk();
    layerAs($s['admin'], 'superadmin')->postJson(route('admin.timetable.cards.place', $s['foreign']), ['day' => 2, 'pair' => 3, 'layer' => 'external'])->assertOk();
    // ichki jadval, alohida hafta
    layerAs($s['admin'], 'superadmin')->postJson(route('admin.timetable.cards.week-override', $s['own']), ['week' => 1, 'action' => 'move', 'day' => 1, 'pair' => 1])->assertOk();
    layerAs($s['admin'], 'superadmin')->postJson(route('admin.timetable.cards.week-override', $s['foreign']), ['week' => 1, 'action' => 'move', 'day' => 1, 'pair' => 1])->assertOk();

    // Boshqa fan (bo'lak emas) — guruh band
    $other = \App\Models\TimetableCard::create($s['own']->only(['board_id', 'faculty_name', 'specialty_name', 'course', 'oqim_label', 'lang', 'students', 'training_type', 'len_half', 'weeks', 'group_name'])
        + ['subject_name' => 'Xirurgiya (b)', 'kafedra_name' => 'X']);
    layerAs($s['admin'], 'superadmin')->postJson(route('admin.timetable.cards.place', $other), ['day' => 2, 'pair' => 3, 'layer' => 'external'])->assertStatus(422);
});

/** HEMIS biriktiruvi: [guruh => talabalar soni] shu fanni o'qiydi (student_subjects). */
function assignVariantStudents(string $subject, array $perGroup): void
{
    static $seq = 900000;
    foreach ($perGroup as $g => $n) {
        for ($i = 0; $i < $n; $i++) {
            $hid = ++$seq;
            DB::table('students')->insert(['hemis_id' => $hid, 'full_name' => 'S', 'short_name' => 'S', 'first_name' => 'S', 'second_name' => 'S',
                'student_id_number' => (string) $hid, 'group_name' => $g . " (o'z)", 'student_status_code' => 11]);
            DB::table('student_subjects')->insert(['student_hemis_id' => $hid, 'curriculum_subject_hemis_id' => 1, 'subject_name' => $subject, 'education_year' => '2026']);
        }
    }
}

it('aralash (c) bo\'lak kartasini asos guruhlar bo\'yicha bo\'ladi, joylashuv va haftalar saqlanadi', function () {
    $s = layersSetup();
    $s['board']->forceFill(['subject_source' => 'hemis'])->save();
    assignVariantStudents('IKP (c)', ['g-02a' => 3, 'g-02b' => 2, 'g-03a' => 4, 'g-03b' => 1]);
    $c = \App\Models\TimetableCard::create($s['own']->only(['board_id', 'faculty_name', 'specialty_name', 'course', 'oqim_label', 'lang', 'training_type', 'len_half', 'weeks'])
        + ['subject_name' => 'IKP (c)', 'kafedra_name' => 'X', 'students' => 40, 'teacher_id' => 7, 'teacher_name' => 'T',
            'group_name' => 'g-02a + g-02b + g-03a + g-03b', 'group_names' => ['g-02a', 'g-02b', 'g-03a', 'g-03b'], 'ext_day' => 1, 'ext_pair' => 3]);
    \App\Models\TimetableCardOverride::create(['card_id' => $c->id, 'week' => 9, 'cancelled' => true]);
    \App\Models\TimetableCardOverride::create(['card_id' => $c->id, 'week' => 2, 'day' => 1, 'pair' => 5, 'cancelled' => false]);

    layerAs($s['admin'], 'superadmin')->postJson(route('admin.timetable.boards.split-variant-mixed', $s['board']), ['courses' => [2], 'dry_run' => 1])
        ->assertOk()->assertJson(['cards' => 1, 'created' => 0]);
    expect(\App\Models\TimetableChangeRequest::count())->toBe(0);
    layerAs($s['admin'], 'superadmin')->postJson(route('admin.timetable.boards.split-variant-mixed', $s['board']), ['courses' => [2]])
        ->assertOk()->assertJson(['cards' => 1, 'created' => 1]);
    $log = \App\Models\TimetableChangeRequest::sole();
    expect($log->status)->toBe('applied')
        ->and($log->summary)->toBe('Aralash bo\'lak kartalari asos guruhlar bo\'yicha bo\'lindi: 2-kurs');

    $c->refresh();
    expect($c->group_names)->toBe(['g-02a', 'g-02b'])->and($c->students)->toBe(5);
    $copy = \App\Models\TimetableCard::where('subject_name', 'IKP (c)')->where('id', '!=', $c->id)->first();
    expect($copy->group_names)->toBe(['g-03a', 'g-03b'])
        ->and($copy->students)->toBe(5)
        ->and([$copy->ext_day, $copy->ext_pair, $copy->teacher_id])->toBe([1, 3, null])
        ->and(\App\Models\TimetableCardOverride::where('card_id', $copy->id)->count())->toBe(2);
});

it('biriktiruvsiz juftlangan bo\'lak kartasi va ishchi reja doskasi bo\'linmaydi', function () {
    $s = layersSetup();
    // Harf qoidasi bilan ataylab juftlangan amaliy (seminar / katta guruh) — HEMIS biriktiruvi yo'q
    $c = \App\Models\TimetableCard::create($s['own']->only(['board_id', 'faculty_name', 'specialty_name', 'course', 'oqim_label', 'lang', 'training_type', 'len_half', 'weeks'])
        + ['subject_name' => 'Pediatriya (a)', 'kafedra_name' => 'X', 'students' => 30,
            'group_name' => 'g-01a + g-02a', 'group_names' => ['g-01a', 'g-02a']]);

    layerAs($s['admin'], 'superadmin')->postJson(route('admin.timetable.boards.split-variant-mixed', $s['board']), ['courses' => [2]])
        ->assertStatus(422);

    $s['board']->forceFill(['subject_source' => 'hemis'])->save();
    layerAs($s['admin'], 'superadmin')->postJson(route('admin.timetable.boards.split-variant-mixed', $s['board']), ['courses' => [2]])
        ->assertOk()->assertJson(['cards' => 0, 'created' => 0]);
    expect($c->fresh()->group_names)->toBe(['g-01a', 'g-02a'])
        ->and(\App\Models\TimetableCard::where('subject_name', 'Pediatriya (a)')->count())->toBe(1);
});
