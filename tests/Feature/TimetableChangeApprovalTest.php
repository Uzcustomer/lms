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
        'department_id' => 77, 'department_name' => 'F1', 'created_at' => now(), 'updated_at' => now(),
    ]);
    // Boshqa fakultetdagi bir xil nomli guruh talabasi — unga xabar bormasligi kerak.
    DB::table('students')->insert([
        'hemis_id' => 9002, 'full_name' => 'TALABA IKKI', 'short_name' => 'TALABA I.', 'first_name' => 'I', 'second_name' => 'T',
        'third_name' => 'X', 'student_id_number' => '9002', 'group_name' => 'd/d25-01a', 'student_status_code' => 11,
        'department_id' => 78, 'department_name' => 'F2', 'created_at' => now(), 'updated_at' => now(),
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
        // Sinov bajarish kartani xotirada ko'chiradi — tavsifda baribir eski joy turadi.
        ->and($req->summary)->toContain('Dushanba, 1-para → Seshanba, 3-para')
        ->and($req->details['groups'])->toContain('d/d25-01a (o\'z)');
    expect(TeacherNotification::where('teacher_id', $reg->id)->count())->toBe(1);

    // Shu kartaga yangi so'rov — eskisi almashtiriladi
    asRole($head, 'kafedra_mudiri')
        ->postJson(route('admin.timetable.cards.place', $card), ['day' => 3, 'pair' => 5])
        ->assertJson(['pending_approval' => true]);
    expect(TimetableChangeRequest::where('status', 'pending')->count())->toBe(1)
        ->and(TimetableChangeRequest::where('status', 'cancelled')->count())->toBe(1);
    $req = TimetableChangeRequest::where('status', 'pending')->sole();
    expect($req->summary)->toContain('Dushanba, 1-para → Chorshanba, 5-para');

    // Kafedra mudiri tasdiqlay olmaydi
    asRole($head, 'kafedra_mudiri')
        ->postJson(route('admin.timetable.change-requests.approve', $req))->assertForbidden();

    asRole($reg, 'registrator_ofisi')
        ->postJson(route('admin.timetable.change-requests.approve', $req))
        ->assertOk()->assertJson(['ok' => true]);

    expect($card->fresh()->only(['day', 'pair']))->toBe(['day' => 3, 'pair' => 5])
        ->and($req->fresh()->status)->toBe('approved')
        ->and(StudentNotification::where('type', 'timetable_change')->pluck('student_id')->all())
        ->toBe([DB::table('students')->where('hemis_id', 9001)->value('id')])
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
    // So'rov emas — tarix uchun "applied" yozuvi.
    $row = TimetableChangeRequest::sole();
    expect($card->fresh()->day)->toBe(2)
        ->and($row->status)->toBe('applied')
        ->and($row->requester_role)->toBe('kafedra_mudiri')
        ->and($row->summary)->toContain('Dushanba, 1-para → Seshanba, 3-para');
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
});

it('kafedra mudiri sahifasida "So\'rovlar" va "Kafedraniki" bor, tasdiqlash huquqi yo\'q', function () {
    ['head' => $head] = approvalSetup();

    $html = asRole($head, 'kafedra_mudiri')->get(route('admin.timetable.index'))->assertOk()->getContent();
    expect($html)->toContain('id="changeReqBtn"')->toContain('id="mineKafedra"')->toContain('const TIMETABLE_CAN_APPROVE = false');
});

it('tasdiqsiz o\'zgarishlar tarixda qoladi, xato amal yozilmaydi', function () {
    ['card' => $card] = approvalSetup();
    $oquv = new Teacher();
    $oquv->forceFill([
        'hemis_id' => 777001, 'full_name' => 'OQUV BOLIMI', 'short_name' => 'OQUV', 'first_name' => 'O', 'second_name' => 'B',
        'employee_id_number' => '777001', 'birth_date' => '1980-01-01', 'year_of_enter' => 2010,
        'gender' => 'm', 'department' => 'O\'quv bo\'limi', 'employment_form' => 'x', 'employment_staff' => 'x', 'staff_position' => 'x',
        'employee_status' => 'x', 'employee_type' => 'x', 'contract_number' => '1', 'decree_number' => '1',
        'contract_date' => '2020-01-01', 'decree_date' => '2020-01-01',
        'status' => true, 'login' => 'oquv777001', 'password' => bcrypt('x'),
    ])->save();
    $oquv->assignRole('oquv_bolimi');

    // Doskada tasdiq rejimi yoqilgan, lekin o'quv bo'limi uchun u amal qilmaydi — darhol qo'llanadi.
    asRole($oquv, 'oquv_bolimi')
        ->postJson(route('admin.timetable.cards.place', $card), ['day' => 4, 'pair' => 2])
        ->assertOk()->assertJsonMissing(['pending_approval' => true]);
    asRole($oquv, 'oquv_bolimi')
        ->postJson(route('admin.timetable.cards.place', $card), ['day' => 99, 'pair' => 2])
        ->assertStatus(422);

    $row = TimetableChangeRequest::sole();
    expect($card->fresh()->only(['day', 'pair']))->toBe(['day' => 4, 'pair' => 2])
        ->and($row->status)->toBe('applied')
        ->and($row->requester_name)->toBe('OQUV BOLIMI')
        ->and($row->summary)->toContain('Dushanba, 1-para → Payshanba, 2-para')
        // Tasdiqsiz o'zgarish registratorga xabar yubormaydi.
        ->and(TeacherNotification::count())->toBe(0);
});

it('tarixni o\'quv bo\'limi to\'liq, kafedra mudiri faqat o\'zinikini sahifalab ko\'radi', function () {
    ['head' => $head, 'reg' => $reg, 'board' => $board, 'card' => $card] = approvalSetup(false);

    foreach ([2, 3, 4] as $day) {
        asRole($head, 'kafedra_mudiri')->postJson(route('admin.timetable.cards.place', $card), ['day' => $day, 'pair' => 1])->assertOk();
    }
    // Registratorning (boshqa foydalanuvchining) o'zgarishi
    asRole($reg, 'registrator_ofisi')->postJson(route('admin.timetable.cards.place', $card), ['day' => 5, 'pair' => 1])->assertOk();
    expect(TimetableChangeRequest::where('status', 'applied')->count())->toBe(4);

    $all = asRole($reg, 'registrator_ofisi')->getJson(route('admin.timetable.change-requests.index', $board))->assertOk();
    expect($all->json('requests'))->toHaveCount(4)->and($all->json('has_more'))->toBeFalse()
        ->and($all->json('requests.0.summary'))->toContain('Payshanba, 1-para → Juma, 1-para');

    $own = asRole($head, 'kafedra_mudiri')->getJson(route('admin.timetable.change-requests.index', $board))->assertOk();
    expect(collect($own->json('requests'))->pluck('requester_name')->unique()->all())->toBe(['KAFEDRA MUDIRI'])
        ->and($own->json('requests'))->toHaveCount(3)
        ->and($own->json('requests.0.mine'))->toBeTrue();

    $older = asRole($head, 'kafedra_mudiri')
        ->getJson(route('admin.timetable.change-requests.index', ['board' => $board, 'before_id' => $own->json('requests.1.id')]))->assertOk();
    expect($older->json('requests'))->toHaveCount(1)
        ->and($older->json('requests.0.id'))->toBe($own->json('requests.2.id'));
});

it('doska sozlamalari saqlanganda tasdiq rejimi, ranglar va bayramlar yo\'qolmaydi', function () {
    ['reg' => $reg, 'board' => $board] = approvalSetup();
    $board->update(['settings' => [
        'approval_required' => true, 'subject_colors' => ['gigiyena' => '#ff0000'],
        'holidays' => ['2026-11-01'], 'days_off' => ['Yakshanba'],
    ]]);

    asRole($reg, 'registrator_ofisi')->post(route('admin.timetable.settings.save', $board), [
        'days' => 6,
        'bell_schedule' => [['type' => 'pair', 'name' => '1-para']],
        // days_off yuborilmadi — hammasi olib tashlangan
        'settings' => ['allow_zero' => 1, 'show_day_number' => 0, 'approval_required' => 0],
    ])->assertOk();

    $settings = $board->fresh()->settings;
    expect($settings['approval_required'])->toBeTrue()
        ->and($settings['subject_colors'])->toBe(['gigiyena' => '#ff0000'])
        ->and($settings['holidays'])->toBe(['2026-11-01'])
        ->and($settings['days_off'])->toBe([])
        ->and((int) $settings['allow_zero'])->toBe(1);
});

it('parallel bekor qilingan so\'rov tasdiqlanmaydi va qayta rad etilmaydi', function () {
    ['head' => $head, 'reg' => $reg, 'card' => $card] = approvalSetup();

    asRole($head, 'kafedra_mudiri')->postJson(route('admin.timetable.cards.place', $card), ['day' => 2, 'pair' => 3]);
    // Registrator so'rovni ochib turibdi (xotirada — "pending"), shu payt mudir uni bekor qiladi.
    $stale = TimetableChangeRequest::sole();
    asRole($head, 'kafedra_mudiri')->postJson(route('admin.timetable.change-requests.cancel', $stale))->assertOk();
    expect($stale->status)->toBe('pending');

    $svc = app(\App\Services\Timetable\TimetableChangeApproval::class);
    expect($svc->apply($stale, $reg))->toMatchArray(['ok' => false, 'stale' => true])
        ->and($svc->reject($stale, $reg, 'kech'))->toBeFalse()
        ->and($svc->cancel($stale))->toBeFalse();

    expect($card->fresh()->only(['day', 'pair']))->toBe(['day' => 1, 'pair' => 1])
        ->and($stale->fresh()->status)->toBe('cancelled')
        ->and($stale->fresh()->review_note)->toBe('Yuboruvchi bekor qildi')
        ->and(TeacherNotification::where('teacher_id', $head->id)->count())->toBe(0);

    asRole($reg, 'registrator_ofisi')->postJson(route('admin.timetable.change-requests.approve', $stale))
        ->assertStatus(422)->assertJson(['error' => 'Bu so\'rov allaqachon ko\'rib chiqilgan.']);
    asRole($head, 'kafedra_mudiri')->postJson(route('admin.timetable.change-requests.cancel', $stale))
        ->assertStatus(422);
});

it('KTR bo\'yicha haftalar tarixga yoziladi, sinov (dry_run) yozilmaydi', function () {
    ['reg' => $reg, 'board' => $board] = approvalSetup();
    $scope = ['faculty_names' => ['F1'], 'specialty_names' => ['davolash ishi'], 'courses' => [2]];

    asRole($reg, 'registrator_ofisi')->postJson(route('admin.timetable.ktr-weeks', $board), $scope + ['dry_run' => 1])
        ->assertOk()->assertJson(['dry_run' => true]);
    expect(TimetableChangeRequest::count())->toBe(0);

    asRole($reg, 'registrator_ofisi')->postJson(route('admin.timetable.ktr-weeks', $board), $scope)->assertOk();
    $row = TimetableChangeRequest::sole();
    expect($row->status)->toBe('applied')
        ->and($row->summary)->toBe('Haftalar KTR bo\'yicha belgilandi: F1, davolash ishi, 2-kurs');
});
