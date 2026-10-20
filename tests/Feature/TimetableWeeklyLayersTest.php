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
