<?php

use App\Models\StudentNotification;
use App\Models\Teacher;
use App\Models\TeacherNotification;
use App\Models\TimetableBoard;
use App\Models\TimetableCard;
use App\Models\TimetableChangeRequest;
use App\Models\TimetableGridSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * Doskada "tasdiq talab qilinadi" yoqilgan bo'lsa kafedra mudirining o'zgarishi
 * so'rov bo'lib saqlanadi, registrator tasdiqlagach qo'llanadi va xabar boradi.
 */
function approvalSetup(bool $approval = true): array
{
    foreach (['kafedra_mudiri', 'registrator_ofisi', 'oqituvchi', 'oquv_bolimi', 'dekan'] as $r) {
        Role::findOrCreate($r, 'web');
    }
    DB::table('departments')->insert([
        'department_hemis_id' => 501, 'name' => 'Gigiyena kafedrasi', 'code' => 'G1', 'structure_type_code' => 12,
        'structure_type_name' => 'Kafedra', 'locality_type_code' => 11, 'locality_type_name' => 'Mahalliy', 'active' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);
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
    $head = $mk('KAFEDRA MUDIRI', 'kafedra_mudiri', ['department_hemis_id' => 501, 'department' => 'Gigiyena kafedrasi']);
    $reg = $mk('REGISTRATOR', 'registrator_ofisi');
    $teacher = $mk('OQITUVCHI A.', 'oqituvchi');

    $board = TimetableBoard::create([
        'name' => 'Test', 'academic_year' => '2026-2027', 'semester_parity' => 'kuzgi',
        'days' => 6, 'pairs_per_day' => 12, 'weeks' => 10,
        'settings' => ['approval_required' => $approval],
    ]);
    TimetableGridSetting::create([
        'board_id' => $board->id, 'faculty_name' => 'F1', 'specialty_name' => 'davolash ishi', 'course' => 2,
        'days' => 6, 'pairs_per_day' => 12, 'weeks' => 10,
    ]);
    $card = TimetableCard::create([
        'board_id' => $board->id, 'faculty_name' => 'F1', 'specialty_name' => 'davolash ishi', 'course' => 2,
        'oqim_label' => '1-oqim', 'lang' => 'uz', 'students' => 15, 'len_half' => 2, 'start_half' => 0,
        'subject_name' => 'Gigiyena', 'training_type' => 'practice', 'group_name' => 'd/d25-01a (o\'z)',
        'kafedra_name' => 'Gigiyena kafedrasi', 'teacher_id' => $teacher->id, 'teacher_name' => 'OQITUVCHI A.',
        'day' => 1, 'pair' => 1,
    ]);
    DB::table('students')->insert([
        'hemis_id' => 9001, 'full_name' => 'TALABA BIR', 'short_name' => 'TALABA B.', 'first_name' => 'B', 'second_name' => 'T',
        'third_name' => 'X', 'student_id_number' => '9001', 'group_name' => 'd/d25-01a', 'student_status_code' => 11,
        'department_id' => 77, 'created_at' => now(), 'updated_at' => now(),
    ]);

    return compact('head', 'reg', 'teacher', 'board', 'card');
}

function asRole(Teacher $t, string $role)
{
    return test()->withoutVite()->actingAs($t, 'teacher')->withSession(['active_role' => $role]);
}

it('kafedra mudiri o\'zgarishi so\'rov bo\'ladi, registrator tasdiqlagach qo\'llanadi', function () {
    ['head' => $head, 'reg' => $reg, 'teacher' => $teacher, 'card' => $card] = approvalSetup();

    $res = asRole($head, 'kafedra_mudiri')
        ->postJson(route('admin.timetable.cards.place', $card), ['day' => 2, 'pair' => 3]);
    $res->assertOk()->assertJson(['pending_approval' => true]);

    expect($card->fresh()->only(['day', 'pair']))->toBe(['day' => 1, 'pair' => 1]);
    $req = TimetableChangeRequest::sole();
    expect($req->status)->toBe('pending')
        ->and($req->summary)->toContain('Gigiyena')
        ->and($req->details['groups'])->toContain('d/d25-01a (o\'z)');
    expect(TeacherNotification::where('teacher_id', $reg->id)->count())->toBe(1);

    // Shu kartaga yangi so'rov — eskisi almashtiriladi
    asRole($head, 'kafedra_mudiri')
        ->postJson(route('admin.timetable.cards.place', $card), ['day' => 3, 'pair' => 5])
        ->assertJson(['pending_approval' => true]);
    expect(TimetableChangeRequest::where('status', 'pending')->count())->toBe(1)
        ->and(TimetableChangeRequest::where('status', 'cancelled')->count())->toBe(1);
    $req = TimetableChangeRequest::where('status', 'pending')->sole();

    // Kafedra mudiri tasdiqlay olmaydi
    asRole($head, 'kafedra_mudiri')
        ->postJson(route('admin.timetable.change-requests.approve', $req))->assertForbidden();

    asRole($reg, 'registrator_ofisi')
        ->postJson(route('admin.timetable.change-requests.approve', $req))
        ->assertOk()->assertJson(['ok' => true]);

    expect($card->fresh()->only(['day', 'pair']))->toBe(['day' => 3, 'pair' => 5])
        ->and($req->fresh()->status)->toBe('approved')
        ->and(StudentNotification::where('type', 'timetable_change')->count())->toBe(1)
        ->and(TeacherNotification::where('teacher_id', $teacher->id)->where('type', 'timetable_change')->count())->toBe(1)
        ->and(TeacherNotification::where('teacher_id', $head->id)->count())->toBe(1);
});

it('rad etilgan so\'rov jadvalni o\'zgartirmaydi', function () {
    ['head' => $head, 'reg' => $reg, 'card' => $card] = approvalSetup();

    asRole($head, 'kafedra_mudiri')->postJson(route('admin.timetable.cards.place', $card), ['day' => 2, 'pair' => 3]);
    $req = TimetableChangeRequest::sole();
    asRole($reg, 'registrator_ofisi')
        ->postJson(route('admin.timetable.change-requests.reject', $req), ['note' => 'Xona band'])
        ->assertOk();

    expect($card->fresh()->only(['day', 'pair']))->toBe(['day' => 1, 'pair' => 1])
        ->and($req->fresh()->status)->toBe('rejected')
        ->and(TeacherNotification::where('teacher_id', $head->id)->first()->message)->toContain('Xona band');
});

it('xato o\'zgarish so\'rov bo\'lmaydi — xato darhol qaytadi', function () {
    ['head' => $head, 'card' => $card] = approvalSetup();

    asRole($head, 'kafedra_mudiri')
        ->postJson(route('admin.timetable.cards.place', $card), ['day' => 99, 'pair' => 3])
        ->assertStatus(422);
    expect(TimetableChangeRequest::count())->toBe(0)
        ->and($card->fresh()->day)->toBe(1);
});

it('tasdiq rejimi o\'chiq bo\'lsa o\'zgarish darhol qo\'llanadi', function () {
    ['head' => $head, 'card' => $card] = approvalSetup(false);

    asRole($head, 'kafedra_mudiri')
        ->postJson(route('admin.timetable.cards.place', $card), ['day' => 2, 'pair' => 3])
        ->assertOk()->assertJsonMissing(['pending_approval' => true]);
    expect($card->fresh()->day)->toBe(2)->and(TimetableChangeRequest::count())->toBe(0);
});

it('kafedra mudiri o\'z so\'rovini bekor qiladi, registrator rejimni yoqadi', function () {
    ['head' => $head, 'reg' => $reg, 'board' => $board, 'card' => $card] = approvalSetup(false);

    asRole($head, 'kafedra_mudiri')
        ->postJson(route('admin.timetable.change-requests.mode', $board), ['approval_required' => 1])->assertForbidden();
    asRole($reg, 'registrator_ofisi')
        ->postJson(route('admin.timetable.change-requests.mode', $board), ['approval_required' => 1])->assertOk();
    expect($board->fresh()->settings['approval_required'])->toBeTrue();

    asRole($head, 'kafedra_mudiri')->postJson(route('admin.timetable.cards.place', $card), ['day' => 2, 'pair' => 3]);
    $req = TimetableChangeRequest::sole();
    asRole($head, 'kafedra_mudiri')
        ->getJson(route('admin.timetable.change-requests.index', $board))
        ->assertOk()->assertJsonPath('requests.0.id', $req->id)->assertJsonPath('approval_required', true);
    asRole($head, 'kafedra_mudiri')
        ->postJson(route('admin.timetable.change-requests.cancel', $req))->assertOk();
    expect($req->fresh()->status)->toBe('cancelled');
});

it('sahifa registrator va kafedra mudiri uchun "So\'rovlar" tugmasi bilan ochiladi', function () {
    ['head' => $head, 'reg' => $reg] = approvalSetup();

    $html = asRole($reg, 'registrator_ofisi')->get(route('admin.timetable.index'))->assertOk()->getContent();
    expect($html)->toContain('id="changeReqBtn"')->toContain('const TIMETABLE_CAN_APPROVE = true');
    @file_put_contents(sys_get_temp_dir() . '/tt_reg.html', $html);
});

it('kafedra mudiri sahifasida "So\'rovlar" va "Kafedraniki" bor, tasdiqlash huquqi yo\'q', function () {
    ['head' => $head] = approvalSetup();

    $html = asRole($head, 'kafedra_mudiri')->get(route('admin.timetable.index'))->assertOk()->getContent();
    expect($html)->toContain('id="changeReqBtn"')->toContain('id="mineKafedra"')->toContain('const TIMETABLE_CAN_APPROVE = false');
    @file_put_contents(sys_get_temp_dir() . '/tt_head.html', $html);
});
