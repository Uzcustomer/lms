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

it('biriktiruvi yo\'q bo\'lak talabalar ulushini asl kartadan oladi', function () {
    $s = layersSetup();
    $s['board']->forceFill(['subject_source' => 'hemis'])->save();
    // Faqat 02a/02b biriktirilgan; 03a/03b uchun HEMIS ma'lumoti yo'q
    assignVariantStudents('Nevrologiya (c)', ['g-02a' => 3, 'g-02b' => 2]);
    $c = \App\Models\TimetableCard::create($s['own']->only(['board_id', 'faculty_name', 'specialty_name', 'course', 'oqim_label', 'lang', 'training_type', 'len_half', 'weeks'])
        + ['subject_name' => 'Nevrologiya (c)', 'kafedra_name' => 'X', 'students' => 40,
            'group_name' => 'g-02a + g-02b + g-03a + g-03b', 'group_names' => ['g-02a', 'g-02b', 'g-03a', 'g-03b']]);

    layerAs($s['admin'], 'superadmin')->postJson(route('admin.timetable.boards.split-variant-mixed', $s['board']), ['courses' => [2]])
        ->assertOk()->assertJson(['cards' => 1, 'created' => 1]);
    $copy = \App\Models\TimetableCard::where('subject_name', 'Nevrologiya (c)')->where('id', '!=', $c->id)->sole();
    expect($c->fresh()->students)->toBe(5)
        ->and($copy->group_names)->toBe(['g-03a', 'g-03b'])
        ->and($copy->students)->toBe(20);
});

it('bo\'lakli fan ma\'ruzalari bitta umumiy ma\'ruzaga birlashadi', function () {
    $s = layersSetup();
    $base = $s['own']->only(['board_id', 'faculty_name', 'specialty_name', 'course', 'oqim_label', 'lang', 'len_half', 'weeks']) + ['training_type' => 'lecture', 'kafedra_name' => 'X', 'ext_day' => 1, 'ext_pair' => 1];
    $a = \App\Models\TimetableCard::create($base + ['subject_name' => 'IKP (a)', 'students' => 40, 'group_names' => ['g-02a', 'g-03a']]);
    $b = \App\Models\TimetableCard::create($base + ['subject_name' => 'IKP (b)', 'students' => 40, 'group_names' => ['g-02b', 'g-03b']]);
    $c = \App\Models\TimetableCard::create($base + ['subject_name' => 'IKP (c)', 'students' => 20, 'group_names' => ['g-02a', 'g-02b', 'g-03a', 'g-03b']]);
    foreach ([$a, $b, $c] as $card) {
        \App\Models\TimetableCardOverride::create(['card_id' => $card->id, 'week' => 9, 'cancelled' => true]);
    }

    layerAs($s['admin'], 'superadmin')->postJson(route('admin.timetable.boards.merge-variant-lectures', $s['board']), ['courses' => [2], 'dry_run' => 1])
        ->assertOk()->assertJson(['lectures' => 1, 'removed' => 0]);
    expect(\App\Models\TimetableChangeRequest::count())->toBe(0);
    layerAs($s['admin'], 'superadmin')->postJson(route('admin.timetable.boards.merge-variant-lectures', $s['board']), ['courses' => [2]])
        ->assertOk()->assertJson(['lectures' => 1, 'removed' => 2, 'skipped' => []]);
    $a->refresh();
    expect($a->subject_name)->toBe('IKP')->and($a->students)->toBe(100)
        ->and(collect($a->group_names)->sort()->values()->all())->toBe(['g-02a', 'g-02b', 'g-03a', 'g-03b'])
        ->and(\App\Models\TimetableCard::whereIn('id', [$b->id, $c->id])->count())->toBe(0)
        ->and(\App\Models\TimetableCardOverride::where('card_id', $a->id)->count())->toBe(1)
        ->and(\App\Models\TimetableChangeRequest::sole()->summary)->toBe('Bo\'lakli fan ma\'ruzalari birlashtirildi: 2-kurs');
});

it('turli vaqtdagi yoki turli haftadagi bo\'lak ma\'ruzalari birlashtirilmaydi; bir xil guruhli bo\'laklar soni qo\'shilmaydi', function () {
    $s = layersSetup();
    $base = $s['own']->only(['board_id', 'faculty_name', 'specialty_name', 'course', 'oqim_label', 'lang', 'len_half', 'weeks']) + ['training_type' => 'lecture', 'kafedra_name' => 'X'];
    // Turli vaqtda joylashgan — qoldiriladigan kartaga qo'shilsa, (b) guruhlari tekshiruvsiz boshqa vaqtga o'tardi
    $a = \App\Models\TimetableCard::create($base + ['subject_name' => 'Fiziologiya (a)', 'students' => 20, 'group_names' => ['g-02a'], 'day' => 1, 'pair' => 1]);
    $b = \App\Models\TimetableCard::create($base + ['subject_name' => 'Fiziologiya (b)', 'students' => 20, 'group_names' => ['g-02b'], 'day' => 2, 'pair' => 5]);
    // Turli haftalarda (toq / juft)
    $x = \App\Models\TimetableCard::create($base + ['subject_name' => 'Biokimyo (a)', 'students' => 20, 'group_names' => ['g-02a']]);
    $y = \App\Models\TimetableCard::create($base + ['subject_name' => 'Biokimyo (b)', 'students' => 20, 'group_names' => ['g-02b']]);
    \App\Models\TimetableCardOverride::create(['card_id' => $x->id, 'week' => 2, 'cancelled' => true]);
    \App\Models\TimetableCardOverride::create(['card_id' => $y->id, 'week' => 1, 'cancelled' => true]);
    // Ishchi reja: har bo'lakda butun oqim — bir xil guruhlar, bir xil son
    $p = \App\Models\TimetableCard::create($base + ['subject_name' => 'Anatomiya (a)', 'students' => 30, 'group_names' => ['g-02a', 'g-02b']]);
    $q = \App\Models\TimetableCard::create($base + ['subject_name' => 'Anatomiya (b)', 'students' => 30, 'group_names' => ['g-02b', 'g-02a']]);

    $r = layerAs($s['admin'], 'superadmin')->postJson(route('admin.timetable.boards.merge-variant-lectures', $s['board']), ['courses' => [2]])
        ->assertOk()->assertJson(['lectures' => 1, 'removed' => 1]);
    expect($r->json('skipped'))->toHaveCount(2)
        ->and(\App\Models\TimetableCard::whereIn('id', [$a->id, $b->id, $x->id, $y->id])->count())->toBe(4)
        ->and($a->fresh()->subject_name)->toBe('Fiziologiya (a)')
        ->and($p->fresh()->subject_name)->toBe('Anatomiya')
        ->and($p->fresh()->students)->toBe(30)
        ->and(\App\Models\TimetableCard::find($q->id))->toBeNull();
});

it('bo\'lakli fanning qo\'shimcha amaliysi umumiy ma\'ruzasiz haftalarga tushadi', function () {
    $s = layersSetup();
    $base = $s['own']->only(['board_id', 'faculty_name', 'specialty_name', 'course', 'oqim_label', 'lang', 'len_half']) + ['kafedra_name' => 'X', 'students' => 10];
    $lec = \App\Models\TimetableCard::create($base + ['subject_name' => 'IKP', 'training_type' => 'lecture', 'group_names' => ['g-02a', 'g-02b'], 'weeks' => 5]);
    $prc = \App\Models\TimetableCard::create($base + ['subject_name' => 'IKP (a)', 'training_type' => 'practice', 'group_name' => 'g-02a', 'weeks' => 10]);

    $m = new ReflectionMethod(\App\Http\Controllers\Admin\TimetableController::class, 'assignCardWeeks');
    $m->setAccessible(true);
    $m->invoke(app(\App\Http\Controllers\Admin\TimetableController::class), $s['board']);

    $cancelled = fn ($c) => \App\Models\TimetableCardOverride::where('card_id', $c->id)->where('cancelled', true)->orderBy('week')->pluck('week')->all();
    $lecActive = array_values(array_diff(range(1, 15), $cancelled($lec)));
    expect($lecActive)->toHaveCount(5)
        ->and($cancelled($prc))->toBe($lecActive);
});

it('sikl rejimidagi bo\'lakli fan ma\'ruzalari bo\'lak nomi bilan qoladi (sikl kalendari shu nom bilan qidiradi)', function () {
    $s = layersSetup();
    foreach (['Gistologiya (a)', 'Gistologiya (b)'] as $subject) {
        \App\Models\TimetableSubjectSetting::create(['board_id' => $s['board']->id, 'specialty_name' => 'davolash ishi', 'course' => 2,
            'subject_name' => $subject, 'mode' => 'cycle', 'cycle_days' => 5]);
    }
    $base = $s['own']->only(['board_id', 'faculty_name', 'specialty_name', 'course', 'oqim_label', 'lang', 'len_half', 'weeks']) + ['training_type' => 'lecture', 'kafedra_name' => 'X', 'students' => 20];
    $a = \App\Models\TimetableCard::create($base + ['subject_name' => 'Gistologiya (a)', 'group_names' => ['g-02a']]);
    $b = \App\Models\TimetableCard::create($base + ['subject_name' => 'Gistologiya (b)', 'group_names' => ['g-02b']]);

    layerAs($s['admin'], 'superadmin')->postJson(route('admin.timetable.boards.merge-variant-lectures', $s['board']), ['courses' => [2]])
        ->assertOk()->assertJson(['lectures' => 0, 'removed' => 0]);
    expect($a->fresh()->subject_name)->toBe('Gistologiya (a)')->and($b->fresh()->subject_name)->toBe('Gistologiya (b)');
});

it('HEMIS biriktiruvi bilan bitta guruhdan bo\'lingan bo\'lak ma\'ruzalari birlashganda ulushlar qo\'shiladi', function () {
    $s = layersSetup();
    $s['board']->forceFill(['subject_source' => 'hemis'])->save();
    // g-02 talabalari (a) va (b) bo'laklariga bo'lingan — ikkala ma'ruza ham shu guruh bilan
    assignVariantStudents('Patologiya (a)', ['g-02' => 10]);
    assignVariantStudents('Patologiya (b)', ['g-02' => 10]);
    $base = $s['own']->only(['board_id', 'faculty_name', 'specialty_name', 'course', 'oqim_label', 'lang', 'len_half', 'weeks']) + ['training_type' => 'lecture', 'kafedra_name' => 'X'];
    $a = \App\Models\TimetableCard::create($base + ['subject_name' => 'Patologiya (a)', 'students' => 10, 'group_names' => ['g-02']]);
    \App\Models\TimetableCard::create($base + ['subject_name' => 'Patologiya (b)', 'students' => 10, 'group_names' => ['g-02']]);

    layerAs($s['admin'], 'superadmin')->postJson(route('admin.timetable.boards.merge-variant-lectures', $s['board']), ['courses' => [2]])
        ->assertOk()->assertJson(['lectures' => 1, 'removed' => 1]);
    expect($a->fresh()->subject_name)->toBe('Patologiya')->and($a->fresh()->students)->toBe(20);
});

it('boshqa fakultetdagi bir xil nomli guruhning HEMIS biriktiruvi birlashtirishda hisobga olinmaydi', function () {
    $s = layersSetup();
    $s['board']->forceFill(['subject_source' => 'hemis'])->save();
    // "g-07" nomi ikki fakultetda: F1 — 100-reja (biriktiruv yo'q), F2 — 200-reja (biriktiruv bor)
    foreach ([['F1', 100, 1], ['F2', 200, 2]] as [$fac, $cur, $id]) {
        DB::table('groups')->insert(['group_hemis_id' => 7000 + $id, 'name' => "g-07 (o'z)", 'department_hemis_id' => $id, 'department_name' => $fac,
            'department_code' => 'D' . $id, 'department_structure_type_code' => '11', 'department_structure_type_name' => 'Fakultet',
            'department_locality_type_code' => '11', 'department_locality_type_name' => 'M', 'department_active' => 1, 'specialty_hemis_id' => 1,
            'specialty_code' => 'S', 'specialty_name' => 'davolash ishi', 'education_lang_code' => '11', 'education_lang_name' => 'uz',
            'curriculum_hemis_id' => $cur, 'active' => 1]);
    }
    DB::table('curriculum_subjects')->insert(['curriculum_subject_hemis_id' => 901, 'curricula_hemis_id' => 200, 'subject_id' => 1,
        'subject_name' => 'Mikrobiologiya (a)', 'subject_code' => 'M', 'semester_code' => '13', 'semester_name' => '3', 'at_semester' => 1]);
    foreach (['Mikrobiologiya (a)', 'Mikrobiologiya (b)'] as $i => $subject) {
        $hid = 950000 + $i;
        DB::table('students')->insert(['hemis_id' => $hid, 'full_name' => 'S', 'short_name' => 'S', 'first_name' => 'S', 'second_name' => 'S',
            'student_id_number' => (string) $hid, 'group_name' => "g-07 (o'z)", 'student_status_code' => 11]);
        DB::table('student_subjects')->insert(['student_hemis_id' => $hid, 'curriculum_subject_hemis_id' => 901, 'subject_name' => $subject, 'education_year' => '2026']);
    }
    // F1 kartalari — biriktiruvsiz: har bo'lakda butun oqim (30)
    $base = $s['own']->only(['board_id', 'faculty_name', 'specialty_name', 'course', 'oqim_label', 'lang', 'len_half', 'weeks']) + ['training_type' => 'lecture', 'kafedra_name' => 'X'];
    $a = \App\Models\TimetableCard::create($base + ['subject_name' => 'Mikrobiologiya (a)', 'students' => 30, 'group_names' => ['g-07']]);
    \App\Models\TimetableCard::create($base + ['subject_name' => 'Mikrobiologiya (b)', 'students' => 30, 'group_names' => ['g-07']]);

    layerAs($s['admin'], 'superadmin')->postJson(route('admin.timetable.boards.merge-variant-lectures', $s['board']), ['courses' => [2]])
        ->assertOk()->assertJson(['lectures' => 1, 'removed' => 1]);
    expect($a->fresh()->students)->toBe(30);
});

it('3 soatlik amaliy karta 2 + 1 soatlik kartalarga bo\'linadi, joyi va haftalari davomi bilan', function () {
    $s = layersSetup();
    $s['own']->update(['len_half' => 3, 'ext_day' => 2, 'ext_pair' => 3, 'teacher_id' => 5, 'teacher_name' => 'T']);
    \App\Models\TimetableCardOverride::create(['card_id' => $s['own']->id, 'week' => 4, 'day' => 1, 'pair' => 5, 'cancelled' => false]);
    \App\Models\TimetableCardOverride::create(['card_id' => $s['own']->id, 'week' => 9, 'cancelled' => true]);

    layerAs($s['admin'], 'superadmin')->postJson(route('admin.timetable.boards.split-long-practice', $s['board']), ['courses' => [2]])
        ->assertOk()->assertJson(['cards' => 1, 'created' => 1]);
    expect($s['own']->fresh()->len_half)->toBe(2);
    $copy = \App\Models\TimetableCard::where('subject_name', 'Gigiyena')->where('id', '!=', $s['own']->id)->first();
    expect([$copy->len_half, $copy->ext_day, $copy->ext_pair, $copy->teacher_id])->toBe([1, 2, 5, 5]);
    $o = \App\Models\TimetableCardOverride::where('card_id', $copy->id)->where('week', 4)->first();
    expect([$o->day, $o->pair])->toBe([1, 7])
        ->and(\App\Models\TimetableCardOverride::where('card_id', $copy->id)->where('week', 9)->value('cancelled'))->toBeTrue();
});

it('haftalik darsni qo\'yganda o\'qituvchi shu sanada sikl darsida band bo\'lsa — ogohlantirish va batafsil band xabari', function () {
    $s = layersSetup();
    $s['board']->update(['settings' => ['semester_start' => '2026-09-14']]);
    \App\Models\TimetableSubjectSetting::create(['board_id' => $s['board']->id, 'specialty_name' => 'davolash ishi', 'course' => 5,
        'subject_name' => 'Pediatriya', 'mode' => 'cycle', 'cycle_days' => 5]);
    \App\Models\TimetableCard::create(['board_id' => $s['board']->id, 'faculty_name' => 'F1', 'specialty_name' => 'davolash ishi', 'course' => 5,
        'oqim_label' => '1-oqim', 'lang' => 'uz', 'students' => 10, 'training_type' => 'practice', 'len_half' => 2, 'weeks' => 15,
        'subject_name' => 'Pediatriya', 'group_name' => 'g5-01a', 'kafedra_name' => 'P', 'teacher_id' => 77, 'teacher_name' => 'SIKL T.']);
    \App\Models\TimetableCyclePlacement::create(['board_id' => $s['board']->id, 'specialty_name' => 'davolash ishi', 'course' => 5,
        'group_name' => 'F1 / 1-oqim', 'subject_name' => 'Pediatriya', 'start_index' => 0, 'training_type' => 'practice']);
    $s['own']->update(['teacher_id' => 77, 'teacher_name' => 'SIKL T.']);

    $res = layerAs($s['admin'], 'superadmin')->postJson(route('admin.timetable.cards.week-override', $s['own']), ['week' => 1, 'action' => 'move', 'day' => 1, 'pair' => 1])
        ->assertOk()->json();
    expect($res['warnings'] ?? [])->toHaveCount(1)
        ->and($res['warnings'][0])->toContain('14.09.2026')->toContain('Pediatriya');

    // Batafsil band xabari: boshqa karta shu o'qituvchi bilan
    $s['foreign']->update(['teacher_id' => 77, 'teacher_name' => 'SIKL T.', 'group_name' => 'g-02a']);
    $err = layerAs($s['admin'], 'superadmin')->postJson(route('admin.timetable.cards.week-override', $s['foreign']), ['week' => 1, 'action' => 'move', 'day' => 1, 'pair' => 1])
        ->assertStatus(422)->json('error');
    expect($err)->toContain("O'qituvchi band: SIKL T. — F1, 2-kurs · g-01a · Gigiyena (amaliy)");
});

it('sikl oqimidagi ikkinchi guruh o‘qituvchisini ham tekshiradi va birinchi so‘rovdan keyin bandlikni yangilaydi', function () {
    $s = layersSetup();
    $s['board']->update(['settings' => ['semester_start' => '2026-09-14']]);
    $base = ['board_id' => $s['board']->id, 'faculty_name' => 'F1', 'specialty_name' => 'davolash ishi', 'course' => 5,
        'oqim_label' => '1-oqim', 'lang' => 'uz', 'students' => 10, 'training_type' => 'practice', 'len_half' => 2,
        'weeks' => 15, 'subject_name' => 'Pediatriya', 'kafedra_name' => 'P'];
    TimetableCard::create($base + ['group_name' => 'g5-01a', 'teacher_id' => 76, 'teacher_name' => 'BIRINCHI']);
    $second = TimetableCard::create($base + ['group_name' => 'g5-01b', 'teacher_id' => 77, 'teacher_name' => 'IKKINCHI']);
    \App\Models\TimetableCyclePlacement::create(['board_id' => $s['board']->id, 'specialty_name' => 'davolash ishi', 'course' => 5,
        'group_name' => 'F1 / 1-oqim', 'subject_name' => 'Pediatriya', 'start_index' => 0, 'training_type' => 'practice']);
    $s['own']->update(['teacher_id' => 77]);
    $url = route('admin.timetable.cards.week-override', $s['own']);
    $move = ['week' => 1, 'action' => 'move', 'day' => 1, 'pair' => 1];
    $response = layerAs($s['admin'], 'superadmin')->postJson($url, $move)->assertOk();
    expect($response->json('warnings'))->toHaveCount(1)
        ->and($response->json('warnings.0'))->toContain('IKKINCHI');
    $second->update(['teacher_id' => null, 'teacher_name' => null]);
    layerAs($s['admin'], 'superadmin')->postJson($url, $move)->assertOk()->assertJsonMissingPath('warnings');
});

it('uzun amaliy kartalarni bo‘lish dry run va qayta bajarishda xavfsiz, kafedra uchun taqiqlangan', function () {
    $s = layersSetup();
    $s['own']->update(['len_half' => 4, 'day' => 1, 'pair' => 3]);
    $url = route('admin.timetable.boards.split-long-practice', $s['board']);
    layerAs($s['head'], 'kafedra_mudiri')->postJson($url)->assertForbidden();
    layerAs($s['admin'], 'superadmin')->postJson($url, ['dry_run' => true])->assertOk()->assertJson(['cards' => 1, 'created' => 0]);
    expect($s['own']->fresh()->len_half)->toBe(4);
    layerAs($s['admin'], 'superadmin')->postJson($url)->assertOk()->assertJson(['cards' => 1, 'created' => 1]);
    $copy = TimetableCard::where('board_id', $s['board']->id)->where('subject_name', 'Gigiyena')->where('id', '!=', $s['own']->id)->sole();
    expect([$copy->len_half, $copy->day, $copy->pair])->toBe([2, 1, 5]);
    layerAs($s['admin'], 'superadmin')->postJson($url)->assertOk()->assertJson(['cards' => 0, 'created' => 0]);
});

it('jadval sahifasida boshlanish vaqti tanlovi chiqadi', function () {
    $s = layersSetup();
    layerAs($s['admin'], 'superadmin')->get(route('admin.timetable.index'))->assertOk()
        ->assertSee('id="cmStartSlot"', false)->assertSee('Boshlanish vaqti');
});

it('avval birlashtirilgan umumiy ma\'ruzaga keyin qo\'shilgan bo\'lak ma\'ruzalari qo\'shiladi', function () {
    $s = layersSetup();
    $base = $s['own']->only(['board_id', 'faculty_name', 'specialty_name', 'course', 'oqim_label', 'lang', 'len_half', 'weeks']) + ['training_type' => 'lecture', 'kafedra_name' => 'X', 'ext_day' => 2, 'ext_pair' => 1];
    $m = \App\Models\TimetableCard::create($base + ['subject_name' => 'Klinik stomatologiya', 'students' => 40, 'group_names' => ['g-02a']]);
    $b = \App\Models\TimetableCard::create($base + ['subject_name' => 'Klinik stomatologiya (b)', 'students' => 30, 'group_names' => ['g-02b']]);
    $c = \App\Models\TimetableCard::create($base + ['subject_name' => 'Klinik stomatologiya (c)', 'students' => 20, 'group_names' => ['g-02a', 'g-02b']]);
    $other = \App\Models\TimetableCard::create($base + ['subject_name' => 'Anatomiya', 'students' => 50, 'group_names' => ['g-02a']]);

    layerAs($s['admin'], 'superadmin')->postJson(route('admin.timetable.boards.merge-variant-lectures', $s['board']), ['courses' => [2]])
        ->assertOk()->assertJson(['lectures' => 1, 'removed' => 2]);
    $m->refresh();
    expect($m->subject_name)->toBe('Klinik stomatologiya')->and($m->students)->toBe(90)
        ->and(collect($m->group_names)->sort()->values()->all())->toBe(['g-02a', 'g-02b'])
        ->and(\App\Models\TimetableCard::whereIn('id', [$b->id, $c->id])->count())->toBe(0)
        ->and($other->fresh())->not->toBeNull();
});

it('fan nomlarini yangilash — rejada imlo xatosi tuzatilgan nomni (bo\'lak harfi bilan) topadi', function () {
    $s = layersSetup();
    $mc = DB::table('manual_curricula')->insertGetId(['type' => 'ishchi', 'name' => 'x', 'specialty_name' => 'Davolash ishi', 'plan_year' => '2025/2026', 'level_code' => '12', 'created_at' => now(), 'updated_at' => now()]);
    foreach (['Klinik restavratsion stomatologiya (a)', 'Klinik restavratsion stomatologiya (b)', 'Gigiyena'] as $n) {
        DB::table('manual_curriculum_subjects')->insert(['manual_curriculum_id' => $mc, 'subject_name' => $n, 'semester' => 3, 'created_at' => now(), 'updated_at' => now()]);
    }
    $base = $s['own']->only(['board_id', 'faculty_name', 'specialty_name', 'course', 'oqim_label', 'lang', 'len_half', 'weeks', 'students']);
    $pa = TimetableCard::create($base + ['training_type' => 'practice', 'subject_name' => 'Klinik restavratsion stamatologiya (a)', 'group_name' => 'g-02a']);
    $lec = TimetableCard::create($base + ['training_type' => 'lecture', 'subject_name' => 'Klinik restavratsion stamatologiya', 'group_names' => ['g-02a']]);
    $pb = TimetableCard::create($base + ['training_type' => 'practice', 'subject_name' => 'Klinik restavratsion stomatologiya (b)', 'group_name' => 'g-02b']);

    layerAs($s['admin'], 'superadmin')->postJson(route('admin.timetable.boards.refresh-names', $s['board']))->assertOk();
    expect($pa->fresh()->subject_name)->toBe('Klinik restavratsion stomatologiya (a)')
        ->and($lec->fresh()->subject_name)->toBe('Klinik restavratsion stomatologiya')
        ->and($pb->fresh()->subject_name)->toBe('Klinik restavratsion stomatologiya (b)')
        ->and($s['own']->fresh()->subject_name)->toBe('Gigiyena')
        ->and($s['foreign']->fresh()->subject_name)->toBe('Anatomiya');
});

function refreshNamePlan(string $specialty, array $names): void
{
    $id = DB::table('manual_curricula')->insertGetId(['type' => 'ishchi', 'name' => 'Sinov reja', 'specialty_name' => $specialty,
        'plan_year' => '2025/2026', 'level_code' => '12', 'created_at' => now(), 'updated_at' => now()]);
    foreach ($names as $name) {
        DB::table('manual_curriculum_subjects')->insert(['manual_curriculum_id' => $id, 'subject_name' => $name,
            'semester' => 3, 'created_at' => now(), 'updated_at' => now()]);
    }
}

it('imlo mosligi noaniq bo‘lsa yoki faqat boshqa bo‘lak harfi topilsa nomni saqlaydi', function () {
    $s = layersSetup();
    refreshNamePlan('Davolash ishi', ['Klinik restavratsion stomatologiya (a)', 'Klinik restavratsion stimatologiya (a)']);
    $s['own']->update(['subject_name' => 'Klinik restavratsion stamatologiya (a)']);
    $s['foreign']->update(['subject_name' => 'Klinik restavratsion stamatologiya (b)']);
    $url = route('admin.timetable.boards.refresh-names', $s['board']);
    layerAs($s['admin'], 'superadmin')->postJson($url)->assertOk();
    expect($s['own']->fresh()->subject_name)->toBe('Klinik restavratsion stamatologiya (a)')
        ->and($s['foreign']->fresh()->subject_name)->toBe('Klinik restavratsion stamatologiya (b)');
});

it('fakultetda noaniq imlo mosligini boshqa fakultet nomi bilan almashtirmaydi', function () {
    $s = layersSetup();
    $s['own']->update(['faculty_name' => '1-son davolash', 'subject_name' => 'Klinik restavratsion stamatologiya']);
    refreshNamePlan('1-son Davolash ishi', ['Klinik restavratsion stomotologiya', 'Klinik restavratsion stimitologiya']);
    refreshNamePlan('2-son Davolash ishi', ['Klinik restavratsion stomatologiya']);
    layerAs($s['admin'], 'superadmin')->postJson(route('admin.timetable.boards.refresh-names', $s['board']))->assertOk();
    expect($s['own']->fresh()->subject_name)->toBe('Klinik restavratsion stamatologiya');
});

it('kirill yozuvida ikki harfli imlo tuzatishini ham topadi va joylashuvni saqlaydi', function () {
    $s = layersSetup();
    refreshNamePlan('Davolash ishi', ['Клиник реставрацион стоматология (а)']);
    $s['own']->update(['subject_name' => 'Клиник реставрацион стямятология (а)', 'day' => 2, 'pair' => 3,
        'ext_day' => 4, 'ext_pair' => 5, 'teacher_id' => 77, 'teacher_name' => 'Sinov']);
    \App\Models\TimetableCardOverride::create(['card_id' => $s['own']->id, 'week' => 3, 'cancelled' => true]);
    layerAs($s['admin'], 'superadmin')->postJson(route('admin.timetable.boards.refresh-names', $s['board']))->assertOk();
    $card = $s['own']->fresh();
    expect($card->subject_name)->toBe('Клиник реставрацион стоматология (а)')
        ->and([$card->day, $card->pair, $card->ext_day, $card->ext_pair, $card->teacher_id])->toBe([2, 3, 4, 5, 77])
        ->and(\App\Models\TimetableCardOverride::where('card_id', $card->id)->where('week', 3)->value('cancelled'))->toBeTrue();
});

it('umumiy ma’ruzaga qo‘shish dry run va qayta bajarishda xavfsiz, turli haftalarni birlashtirmaydi', function () {
    $s = layersSetup();
    $base = $s['own']->only(['board_id', 'faculty_name', 'specialty_name', 'course', 'oqim_label', 'lang', 'len_half', 'weeks'])
        + ['training_type' => 'lecture', 'students' => 20, 'ext_day' => 2, 'ext_pair' => 1];
    $merged = TimetableCard::create($base + ['subject_name' => 'Klinik stomatologiya', 'group_names' => ['g-02a']]);
    $part = TimetableCard::create($base + ['subject_name' => 'Klinik stomatologiya (b)', 'group_names' => ['g-02b']]);
    $url = route('admin.timetable.boards.merge-variant-lectures', $s['board']);
    layerAs($s['head'], 'kafedra_mudiri')->postJson($url)->assertForbidden();
    layerAs($s['admin'], 'superadmin')->postJson($url, ['courses' => [2], 'dry_run' => true])->assertOk()->assertJson(['lectures' => 1, 'removed' => 0]);
    expect($part->fresh())->not->toBeNull()->and($merged->fresh()->group_names)->toBe(['g-02a']);
    $ov = \App\Models\TimetableCardOverride::create(['card_id' => $merged->id, 'week' => 3, 'cancelled' => true]);
    $response = layerAs($s['admin'], 'superadmin')->postJson($url, ['courses' => [2]])->assertOk()->assertJson(['lectures' => 0, 'removed' => 0]);
    expect($response->json('skipped'))->toHaveCount(1);
    $ov->delete();
    layerAs($s['admin'], 'superadmin')->postJson($url, ['courses' => [2]])->assertOk()->assertJson(['lectures' => 1, 'removed' => 1]);
    layerAs($s['admin'], 'superadmin')->postJson($url, ['courses' => [2]])->assertOk()->assertJson(['lectures' => 0, 'removed' => 0]);
    expect($merged->fresh()->ext_pair)->toBe(1)->and($part->fresh())->toBeNull();
});

it('fan mas\'uli doskani faqat ko\'radi va "Mas\'ul fanlarim" uchun o\'z fanlari kartalarini oladi', function () {
    $s = layersSetup();
    Role::findOrCreate('fan_masuli', 'web');
    $t = $s['head'];
    $t->assignRole('fan_masuli');
    DB::table('specialties')->insert(['specialty_hemis_id' => 9001, 'name' => 'Davolash ishi', 'code' => '60910200', 'locality_type_code' => '1',
        'locality_type_name' => 'x', 'education_type_code' => '11', 'education_type_name' => 'B']);
    DB::table('curricula')->insert(['curricula_hemis_id' => 8001, 'name' => 'R', 'specialty_hemis_id' => 9001, 'department_hemis_id' => 1,
        'education_year_code' => '2026', 'education_year_name' => '2026-2027', 'current' => 1, 'education_type_code' => '11', 'education_type_name' => 'B',
        'education_form_code' => '11', 'education_form_name' => 'K', 'marking_system_code' => '11', 'marking_system_name' => 'M',
        'marking_system_minimum_limit' => 60, 'marking_system_gpa_limit' => 2.4, 'semester_count' => 12, 'education_period' => 6]);
    $csId = DB::table('curriculum_subjects')->insertGetId(['curriculum_subject_hemis_id' => 7001, 'curricula_hemis_id' => 8001, 'subject_id' => 1,
        'subject_name' => 'Gigiyena (a)', 'subject_code' => 'G1', 'semester_code' => '13', 'semester_name' => '3-semestr', 'at_semester' => 1]);
    DB::table('teacher_responsible_subjects')->insert(['teacher_id' => $t->id, 'curriculum_subject_id' => $csId]);
    $lec = TimetableCard::create($s['own']->only(['board_id', 'faculty_name', 'specialty_name', 'course', 'oqim_label', 'lang', 'len_half', 'weeks', 'students'])
        + ['training_type' => 'lecture', 'subject_name' => 'Gigiyena', 'group_names' => ['g-01a'], 'kafedra_name' => 'X']);
    $prc = TimetableCard::create($s['own']->only(['board_id', 'faculty_name', 'specialty_name', 'course', 'oqim_label', 'lang', 'len_half', 'weeks', 'students'])
        + ['training_type' => 'practice', 'subject_name' => 'Gigiyena (a)', 'group_name' => 'g-01a', 'kafedra_name' => 'X']);

    $res = layerAs($t, 'fan_masuli')->getJson(route('admin.timetable.boards.data', $s['board']))->assertOk()->json();
    expect(collect($res['my_subject_card_ids'])->sort()->values()->all())->toBe(collect([$s['own']->id, $lec->id, $prc->id])->sort()->values()->all())
        ->and($res['my_subject_card_ids'])->not->toContain($s['foreign']->id);
    layerAs($t, 'fan_masuli')->get(route('admin.timetable.index'))->assertOk()->assertSee("Mas'ul fanlarim", false);
    layerAs($t, 'fan_masuli')->postJson(route('admin.timetable.cards.place', $s['own']), ['day' => 1, 'pair' => 1, 'layer' => 'external'])->assertForbidden();
});

function responsibleScopeSetup(): array
{
    $s = layersSetup();
    Role::findOrCreate('fan_masuli', 'web');
    $s['head']->assignRole('fan_masuli');
    DB::table('specialties')->insert(['specialty_hemis_id' => 9001, 'name' => 'Davolash ishi', 'code' => '60910200', 'locality_type_code' => '1',
        'locality_type_name' => 'x', 'education_type_code' => '11', 'education_type_name' => 'B']);
    DB::table('curricula')->insert(['curricula_hemis_id' => 8001, 'name' => 'R', 'specialty_hemis_id' => 9001, 'department_hemis_id' => 501,
        'education_year_code' => '2026', 'education_year_name' => '2026-2027', 'current' => 1, 'education_type_code' => '11', 'education_type_name' => 'B',
        'education_form_code' => '11', 'education_form_name' => 'K', 'marking_system_code' => '11', 'marking_system_name' => 'M',
        'marking_system_minimum_limit' => 60, 'marking_system_gpa_limit' => 2.4, 'semester_count' => 12, 'education_period' => 6]);
    DB::table('departments')->where('department_hemis_id', 501)->update(['name' => 'F1']);
    $s['responsible_subject_id'] = DB::table('curriculum_subjects')->insertGetId(['curriculum_subject_hemis_id' => 7001, 'curricula_hemis_id' => 8001, 'subject_id' => 1,
        'subject_name' => 'Gigiyena (a)', 'subject_code' => 'G1', 'semester_code' => '13', 'semester_name' => '3-semestr', 'at_semester' => 1]);
    DB::table('teacher_responsible_subjects')->insert(['teacher_id' => $s['head']->id, 'curriculum_subject_id' => $s['responsible_subject_id']]);
    return $s;
}

it('mas’ul fanlar filtri biriktirish olib tashlanganda yangilanadi', function () {
    $s = responsibleScopeSetup();
    $url = route('admin.timetable.boards.data', $s['board']);
    layerAs($s['head'], 'fan_masuli')->getJson($url)->assertOk()->assertJsonPath('my_subject_card_ids', [$s['own']->id]);
    DB::table('teacher_responsible_subjects')->where('teacher_id', $s['head']->id)->delete();
    layerAs($s['head'], 'fan_masuli')->getJson($url)->assertOk()->assertJsonPath('my_subject_card_ids', []);
    layerAs($s['head'], 'fan_masuli')->get(route('admin.timetable.index'))->assertOk()
        ->assertSee('id="mineSubjects"', false)->assertSee('const TIMETABLE_HAS_SUBJECTS = true', false)
        ->assertSee('id="weeklyExcel"', false);
});

it('mas’ul fanlar filtri boshqa fakultetdagi ayni nomli kartani olmaydi', function () {
    $s = responsibleScopeSetup();
    $other = $s['own']->replicate();
    $other->faculty_name = 'F2';
    $other->save();
    $res = layerAs($s['head'], 'fan_masuli')->getJson(route('admin.timetable.boards.data', $s['board']))->assertOk();
    expect($res->json('my_subject_card_ids'))->toContain($s['own']->id)->not->toContain($other->id);
});

it('mas’ul fanlar filtri siklda ham ishlaydi va bo‘sh biriktirishda jadvalni kengaytirmaydi', function (string $layer) {
    $s = responsibleScopeSetup();
    $s['own']->update(['course' => 5]);
    $s['foreign']->update(['course' => 5]);
    DB::table('curriculum_subjects')->where('id', $s['responsible_subject_id'])->update(['semester_code' => '19']);
    foreach (['Gigiyena', 'Anatomiya'] as $name) {
        \App\Models\TimetableSubjectSetting::create(['board_id' => $s['board']->id, 'specialty_name' => 'davolash ishi',
            'course' => 5, 'subject_name' => $name, 'mode' => 'cycle', 'cycle_days' => 2]);
    }
    $url = route('admin.timetable.cycle-plan', $s['board']);
    $body = ['subjects' => 1, 'layer' => $layer, 'view' => 'flow'];
    $res = layerAs($s['head'], 'fan_masuli')->postJson($url, $body)->assertOk();
    expect(collect($res->json('cycle_cards'))->pluck('subject')->unique()->values()->all())->toBe(['Gigiyena'])
        ->and($res->json('can_edit'))->toBeFalse()
        ->and($s['board']->fresh()->settings)->toBe($s['board']->settings);
    DB::table('teacher_responsible_subjects')->where('teacher_id', $s['head']->id)->delete();
    $empty = layerAs($s['head'], 'fan_masuli')->postJson($url, $body)->assertOk();
    expect($empty->json('cycle_cards'))->toBe([])->and($empty->json('rows'))->toBe([]);
})->with(['internal', 'external']);

it('faqat ko‘rish roli jadvalni o‘zgartira olmaydi va admin doskasini ko‘rmaydi', function (string $role) {
    $s = layersSetup();
    Role::findOrCreate($role, 'web');
    $s['head']->assignRole($role);
    $urls = [
        ['admin.timetable.cards.place', $s['own']],
        ['admin.timetable.cards.update', $s['own']],
        ['admin.timetable.cards.week-override', $s['own']],
        ['admin.timetable.boards.unplace', $s['board']],
        ['admin.timetable.boards.refresh-names', $s['board']],
        ['admin.timetable.cycle-place', $s['board']],
    ];
    foreach ($urls as [$route, $model]) {
        layerAs($s['head'], $role)->postJson(route($route, $model), [])->assertForbidden();
    }
    $s['board']->update(['admin_only' => true]);
    layerAs($s['head'], $role)->getJson(route('admin.timetable.boards.data', $s['board']))->assertForbidden();
    layerAs($s['head'], $role)->postJson(route('admin.timetable.boards.excel-export', $s['board']), [])->assertForbidden();
    expect($s['own']->fresh()->day)->toBeNull();
})->with(['fan_masuli', 'oqituvchi']);

it('haftalik Excel barcha jadval rollari uchun haqiqiy xlsx faylini yaratadi', function (string $role) {
    $s = layersSetup();
    Role::findOrCreate($role, 'web');
    $s['head']->assignRole($role);
    $text = "[A] Gigiyena\nO‘qituvchi: Sinov\nXona: 101";
    $payload = ['sheet_title' => 'Haftalik ichki', 'landscape' => 1, 'freeze_rows' => 1, 'freeze_cols' => 3,
        'rows' => [
            [['t' => 'Kun'], ['t' => 'Para'], ['t' => 'Vaqt'], ['t' => 'Guruh']],
            [['t' => 'Dushanba'], ['t' => 'II'], ['t' => '12:00'], ['t' => $text, 'rs' => 2]],
            [['t' => ''], ['t' => ''], ['t' => '12:40']],
        ]];
    $response = layerAs($s['head'], $role)->post(route('admin.timetable.boards.excel-export', $s['board']),
        ['title' => 'Haftalik · 4-hafta', 'filename' => 'haftalik_4', 'payload' => json_encode($payload)])->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
        ->assertDownload('haftalik_4.xlsx');
    $file = tempnam(sys_get_temp_dir(), 'weekly-xlsx-');
    try {
        file_put_contents($file, $response->streamedContent());
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file);
        $sheet = $spreadsheet->getActiveSheet();
        expect($sheet->getTitle())->toBe('Haftalik ichki')->and($sheet->getCell('A1')->getValue())->toBe('Haftalik · 4-hafta')
            ->and($sheet->getCell('D4')->getValue())->toBe($text)->and($sheet->getMergeCells())->toHaveKey('D4:D5')
            ->and($sheet->getFreezePane())->toBe('D4')
            ->and($sheet->getPageSetup()->getOrientation())->toBe('landscape');
        $spreadsheet->disconnectWorksheets();
    } finally {
        unlink($file);
    }
})->with(['fan_masuli', 'oqituvchi', 'kafedra_mudiri', 'oquv_bolimi', 'oquv_bolimi_boshligi', 'registrator_ofisi', 'kichik_admin', 'admin', 'superadmin']);

function responsibleActor(array $s, string $guard, bool $linked = true)
{
    if ($guard === 'teacher') {
        return test()->withoutVite()->actingAs($s['head'], 'teacher')->withSession(['active_role' => 'fan_masuli']);
    }
    $s['head']->update(['login' => 'responsible@example.test']);
    $user = \App\Models\User::factory()->create(['email' => $linked ? 'responsible@example.test' : 'unlinked@example.test']);
    $user->assignRole('fan_masuli');
    return test()->withoutVite()->actingAs($user, 'web')->withSession(['active_role' => 'fan_masuli']);
}

it('web fan mas’uli email orqali o‘qituvchiga bog‘lanadi va qamrov tugmalarini ko‘radi', function () {
    $s = responsibleScopeSetup();
    $actor = responsibleActor($s, 'web');
    $actor->getJson(route('admin.timetable.boards.data', $s['board']))->assertOk()
        ->assertJsonPath('my_subject_card_ids', [$s['own']->id]);
    $actor->get(route('admin.timetable.index'))->assertOk()
        ->assertSee('const TIMETABLE_TEACHER_ID = ' . $s['head']->id, false)
        ->assertSee('id="mineAll"', false)->assertSee('id="mineSubjects"', false)
        ->assertSee('const TIMETABLE_HAS_SUBJECTS = true', false);
    $actor->postJson(route('admin.timetable.cards.place', $s['own']), ['day' => 1, 'pair' => 1])->assertForbidden();
});

it('bog‘lanmagan web fan mas’uli boshqa o‘qituvchining biriktirishini olmaydi', function () {
    $s = responsibleScopeSetup();
    $actor = responsibleActor($s, 'web', false);
    $actor->getJson(route('admin.timetable.boards.data', $s['board']))->assertOk()->assertJsonPath('my_subject_card_ids', []);
    $actor->get(route('admin.timetable.index'))->assertOk()->assertSee('const TIMETABLE_TEACHER_ID = null', false);
});

it('mas’ul bo‘lak aniq moslanadi, umumiy ma’ruza ko‘rinadi va boshqa bo‘lak yopiq qoladi', function (string $guard, string $assigned, array $variants) {
    $s = responsibleScopeSetup();
    DB::table('curriculum_subjects')->where('id', $s['responsible_subject_id'])->update(['subject_name' => $assigned]);
    $s['own']->update(['subject_name' => 'Gigiyena (a)']);
    $s['foreign']->update(['subject_name' => 'Gigiyena (b)']);
    $lecture = $s['own']->replicate();
    $lecture->fill(['subject_name' => 'Gigiyena', 'training_type' => 'lecture', 'group_name' => null, 'group_names' => ['g-01a']])->save();
    $actor = responsibleActor($s, $guard);
    $res = $actor->getJson(route('admin.timetable.boards.data', $s['board']))->assertOk();
    $expected = [$lecture->id];
    if (in_array('a', $variants, true)) $expected[] = $s['own']->id;
    if (in_array('b', $variants, true)) $expected[] = $s['foreign']->id;
    expect(collect($res->json('my_subject_card_ids'))->sort()->values()->all())->toBe(collect($expected)->sort()->values()->all());
})->with([
    ['teacher', 'Gigiyena (a)', ['a']],
    ['web', 'Gigiyena (a)', ['a']],
    ['teacher', 'Gigiyena', []],
    ['web', 'Gigiyena', []],
]);

it('ichki siklda faqat biriktirilgan bo‘lak ko‘rinadi', function (string $guard) {
    $s = responsibleScopeSetup();
    DB::table('curriculum_subjects')->where('id', $s['responsible_subject_id'])->update(['semester_code' => '19']);
    $s['own']->update(['course' => 5, 'subject_name' => 'Gigiyena (a)']);
    $s['foreign']->update(['course' => 5, 'subject_name' => 'Gigiyena (b)']);
    foreach (['Gigiyena (a)', 'Gigiyena (b)'] as $name) {
        \App\Models\TimetableSubjectSetting::create(['board_id' => $s['board']->id, 'specialty_name' => 'davolash ishi',
            'course' => 5, 'subject_name' => $name, 'mode' => 'cycle', 'cycle_days' => 2]);
    }
    $actor = responsibleActor($s, $guard);
    $response = $actor->postJson(route('admin.timetable.cycle-plan', $s['board']), ['subjects' => 1, 'layer' => 'internal'])->assertOk();
    expect(collect($response->json('cycle_cards'))->pluck('subject')->unique()->values()->all())->toBe(['Gigiyena (a)'])
        ->and($response->json('can_edit'))->toBeFalse();
})->with(['teacher', 'web']);

it('tashqi siklda boshqa mas’ul bo‘lakning joylashuvi tanlangan bo‘lakni almashtirmaydi', function (string $guard) {
    $s = responsibleScopeSetup();
    DB::table('curriculum_subjects')->where('id', $s['responsible_subject_id'])->update(['semester_code' => '19']);
    $s['own']->update(['course' => 5, 'subject_name' => 'Gigiyena (a)']);
    $s['foreign']->update(['course' => 5, 'subject_name' => 'Gigiyena (b)']);
    foreach (['Gigiyena (a)' => 0, 'Gigiyena (b)' => 6] as $name => $start) {
        \App\Models\TimetableSubjectSetting::create(['board_id' => $s['board']->id, 'specialty_name' => 'davolash ishi',
            'course' => 5, 'subject_name' => $name, 'mode' => 'cycle', 'cycle_days' => 2]);
        \App\Models\TimetableCyclePlacement::create(['board_id' => $s['board']->id, 'specialty_name' => 'davolash ishi',
            'course' => 5, 'group_name' => 'F1 / 1-oqim', 'subject_name' => $name, 'start_index' => $start, 'training_type' => 'practice']);
    }
    $actor = responsibleActor($s, $guard);
    $response = $actor->postJson(route('admin.timetable.cycle-plan', $s['board']), ['subjects' => 1, 'layer' => 'external'])->assertOk();
    expect($response->json('rows'))->toHaveCount(1)
        ->and($response->json('rows.0.blocks'))->toHaveCount(1)
        ->and($response->json('rows.0.blocks.0.from'))->toBe(0);
})->with(['teacher', 'web']);

it('panjara: hafta soni o\'zgarsa kartochkalar so\'ralmasa saqlanadi; o\'z boshlanishi va ta\'til haftalari bilan sikl ogohlantirishi sanasi', function () {
    $s = layersSetup();
    $s['own']->update(['ext_day' => 1, 'ext_pair' => 1]);
    layerAs($s['admin'], 'superadmin')->postJson(route('admin.timetable.boards.grid', $s['board']), [
        'faculty_name' => 'F1', 'specialty_name' => 'davolash ishi', 'course' => 2, 'days' => 6, 'pairs_per_day' => 6, 'weeks' => 18,
        'start_date' => '2026-10-12',
    ])->assertOk()->assertJson(['regenerated' => false]);
    expect($s['own']->fresh()->ext_day)->toBe(1)
        ->and(TimetableGridSetting::where('board_id', $s['board']->id)->first()->start_date->toDateString())->toBe('2026-10-12');

    $s['board']->update(['settings' => ['semester_start' => '2026-09-14', 'weekly_breaks' => [['from' => '2026-12-28', 'to' => '2027-01-09']]]]);
    $ctl = app(\App\Http\Controllers\Admin\TimetableController::class);
    $m = new ReflectionMethod($ctl, 'weekStartDate');
    $m->setAccessible(true);
    // 12.10 dan: 1–11-haftalar 12.10–21.12, ta'til (28.12, 04.01) o'tkaziladi → 12-hafta 11.01
    expect($m->invoke($ctl, $s['board']->fresh(), $s['own']->fresh(), 1)->toDateString())->toBe('2026-10-12')
        ->and($m->invoke($ctl, $s['board']->fresh(), $s['own']->fresh(), 11)->toDateString())->toBe('2026-12-21')
        ->and($m->invoke($ctl, $s['board']->fresh(), $s['own']->fresh(), 12)->toDateString())->toBe('2027-01-11');
});

it('oqimga keyin qo\'shilgan guruh: amaliy yangi karta, ma\'ruza mavjud kartaga qo\'shiladi (joyi saqlanadi)', function () {
    $s = layersSetup();
    $base = $s['own']->only(['board_id', 'faculty_name', 'specialty_name', 'course', 'oqim_label', 'lang', 'len_half', 'weeks']);
    $lec = TimetableCard::create($base + ['training_type' => 'lecture', 'subject_name' => 'IKP', 'students' => 30, 'group_names' => ['g-01a', 'g-01b'],
        'ext_day' => 2, 'ext_pair' => 1]);
    $cards = TimetableCard::where('board_id', $s['board']->id)->get();
    $rows = [
        $base + ['training_type' => 'lecture', 'subject_name' => 'IKP (a)', 'students' => 45, 'group_name' => null, 'group_names' => json_encode(['g-01a', 'g-01b', 'g-01c'])],
        $base + ['training_type' => 'practice', 'subject_name' => 'IKP (a)', 'students' => 15, 'group_name' => 'g-01c', 'group_names' => null],
        $base + ['training_type' => 'practice', 'subject_name' => 'IKP (a)', 'students' => 15, 'group_name' => 'g-01a', 'group_names' => null],
        $base + ['training_type' => 'practice', 'subject_name' => 'IKP (c)', 'students' => 15, 'group_name' => 'g-01a + g-01c', 'group_names' => null],
        $base + ['training_type' => 'lecture', 'subject_name' => 'Yangi fan', 'students' => 15, 'group_name' => null, 'group_names' => json_encode(['g-02a'])],
    ];
    $ctl = app(\App\Http\Controllers\Admin\TimetableController::class);
    $m = new ReflectionMethod($ctl, 'planMissingGroupCards');
    $m->setAccessible(true);
    [$insert, $grow, $mixed, $new] = $m->invoke($ctl, $rows, $cards);
    expect(array_keys($new))->toBe(['g-01c', 'g-02a'])
        ->and(count($insert))->toBe(2)
        ->and($insert[0]['group_name'])->toBe('g-01c')
        ->and(array_keys($grow))->toBe([$lec->id])
        ->and($grow[$lec->id][0])->toBe(['g-01a', 'g-01b', 'g-01c'])
        ->and($mixed)->toHaveCount(1);
});

it('yangi guruhlar boshqa fakultetdagi bir xil nomli guruh bilan adashtirilmaydi; mos ma\'ruzasiz aralash ma\'ruza qo\'shilmaydi', function () {
    $s = layersSetup();
    $base = $s['own']->only(['board_id', 'specialty_name', 'course', 'oqim_label', 'lang', 'len_half', 'weeks']);
    // F1 da g-01a guruhi va uning "IKP" ma'ruzasi bor
    $lec = TimetableCard::create($base + ['faculty_name' => 'F1', 'training_type' => 'lecture', 'subject_name' => 'IKP', 'students' => 30, 'group_names' => ['g-01a', 'g-01b']]);
    $cards = TimetableCard::where('board_id', $s['board']->id)->get();
    $rows = [
        // F2 dagi bir xil nomli g-01a — yangi guruh
        $base + ['faculty_name' => 'F2', 'training_type' => 'practice', 'subject_name' => 'IKP', 'students' => 15, 'group_name' => 'g-01a', 'group_names' => null],
        $base + ['faculty_name' => 'F2', 'training_type' => 'lecture', 'subject_name' => 'IKP', 'students' => 30, 'group_name' => null, 'group_names' => json_encode(['g-01a', 'g-01x'])],
        // F1: eski g-01a + yangi g-01z, lekin "Fiziologiya" ma'ruzasi yo'q — ikkinchi ma'ruza yaratilmasin
        $base + ['faculty_name' => 'F1', 'training_type' => 'lecture', 'subject_name' => 'Fiziologiya', 'students' => 30, 'group_name' => null, 'group_names' => json_encode(['g-01a', 'g-01z'])],
    ];
    $ctl = app(\App\Http\Controllers\Admin\TimetableController::class);
    $m = new ReflectionMethod($ctl, 'planMissingGroupCards');
    $m->setAccessible(true);
    [$insert, $grow, $mixed] = $m->invoke($ctl, $rows, $cards);
    expect(array_map(fn ($r) => $r['faculty_name'] . ':' . $r['training_type'], $insert))->toBe(['F2:practice', 'F2:lecture'])
        ->and($grow)->toBe([])
        ->and($mixed)->toHaveCount(1)
        ->and($lec->fresh()->group_names)->toBe(['g-01a', 'g-01b']);
});

it('yangi guruhlar uchun kartochka qo\'shish tarixga yoziladi, sinov (dry_run) yozilmaydi', function () {
    $req = function (array $params) {
        $r = \Illuminate\Http\Request::create('/x', 'POST', $params);
        $route = (new \Illuminate\Routing\Route('POST', 'x', []))->name('admin.timetable.boards.add-missing-groups');
        $r->setRouteResolver(fn () => $route);

        return $r;
    };
    expect(\App\Services\Timetable\TimetableChangeApproval::isChange($req([])))->toBeTrue()
        ->and(\App\Services\Timetable\TimetableChangeApproval::isChange($req(['dry_run' => 1])))->toBeFalse();
});

it('kartochka yaratish: ikki fakultetdagi bir xil nomli guruhning (1K-01a) har biriga amaliy karta yaratiladi', function () {
    $board = TimetableBoard::create(['name' => 'G', 'academic_year' => '2026-2027', 'semester_parity' => 'kuzgi', 'days' => 6, 'pairs_per_day' => 12, 'weeks' => 15]);
    $mc = DB::table('manual_curricula')->insertGetId(['type' => 'ishchi', 'name' => 'R', 'specialty_name' => 'Davolash ishi', 'plan_year' => '2026',
        'level_code' => '11', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('manual_curriculum_subjects')->insert(['manual_curriculum_id' => $mc, 'subject_name' => 'Anatomiya', 'semester' => 1,
        'lecture' => 0, 'practice' => 30, 'laboratory' => 0, 'seminar' => 0]);
    // 1-kurs guruh nomlari avtomatik — "1K-01a (o'z)" ikkala davolash fakultetida bor
    $block = fn (string $fac) => ['merge_key' => 'x|Davolash ishi', 'title' => 'Davolash ishi', 'department_name' => $fac,
        'courses' => [['level_code' => 11, 'oqims' => [['label' => '1-oqim', 'lang' => 'uz', 'total' => 15, 'rows' => [['name' => "1K-01a (o'z)", 'count' => 15]]]]]]];
    // Bir fakultetning bloki takror kelsa (snapshotda bir guruh bir necha blokda) — karta bir marta
    \App\Models\OqimSnapshot::create(['context_key' => 'k', 'context' => [], 'data' => [$block('1-son davolash'), $block('1-son davolash'), $block('2-son davolash')],
        'status' => 'approved', 'approved_at' => now()]);

    $ctl = app(\App\Http\Controllers\Admin\TimetableController::class);
    $m = new ReflectionMethod($ctl, 'assembleRows');
    $m->setAccessible(true);
    $found = [];
    $rows = $m->invokeArgs($ctl, [$board, null, null, &$found]);
    $perFaculty = array_count_values(array_map(fn ($r) => $r['faculty_name'], array_filter($rows, fn ($r) => $r['training_type'] === 'practice')));
    expect(array_keys($perFaculty))->toBe(['1-son davolash', '2-son davolash'])
        ->and($perFaculty['1-son davolash'])->toBe($perFaculty['2-son davolash']);
});

it('ma\'ruzaga yangi guruh qo\'shilganda talabalar soni qatordagi haqiqiy jami bo\'yicha', function () {
    $s = layersSetup();
    $base = $s['own']->only(['board_id', 'faculty_name', 'specialty_name', 'course', 'oqim_label', 'lang', 'len_half', 'weeks']);
    $lec = TimetableCard::create($base + ['training_type' => 'lecture', 'subject_name' => 'Biologiya', 'students' => 10, 'group_names' => ['g-01a']]);
    // yangi g-01x guruhida 30 talaba: qator jami 40 (guruhlar teng emas)
    $rows = [$base + ['training_type' => 'lecture', 'subject_name' => 'Biologiya', 'students' => 40, 'group_name' => null, 'group_names' => json_encode(['g-01a', 'g-01x'])]];
    $ctl = app(\App\Http\Controllers\Admin\TimetableController::class);
    $m = new ReflectionMethod($ctl, 'planMissingGroupCards');
    $m->setAccessible(true);
    [, $grow] = $m->invoke($ctl, $rows, TimetableCard::where('board_id', $s['board']->id)->get());
    expect($grow[$lec->id][0])->toBe(['g-01a', 'g-01x'])
        ->and($grow[$lec->id][2])->toBe(40);
});
