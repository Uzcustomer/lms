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
    // Semestr boshi (2026-09-01): 2-hafta darslari hali oldinda — o'qituvchi so'rovi faqat ertangi va keyingi kunlarga.
    test()->travelTo(\Illuminate\Support\Carbon::parse('2026-09-01 09:00', 'Asia/Tashkent'));
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
    // O'quv bo'limi tashqi jadvalni tahrirlaydi (ext_day/ext_pair), ichki jadval o'zgarmaydi.
    expect($card->fresh()->only(['ext_day', 'ext_pair']))->toBe(['ext_day' => 4, 'ext_pair' => 2])
        ->and($row->status)->toBe('applied')
        ->and($row->requester_name)->toBe('OQUV BOLIMI')
        ->and($row->summary)->toContain('Tashqi jadval')->toContain('→ Payshanba, 2-para')
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


it('teacher proposal requires head then registrar even when board approval is off', function () {
    ['head' => $head, 'reg' => $reg, 'teacher' => $teacher, 'board' => $board, 'card' => $card] = approvalSetup(false);
    asRole($teacher, 'oqituvchi')->postJson(route('admin.timetable.cards.time-proposal', $card),
        ['week' => 2, 'day' => 3, 'pair' => 3, 'reason' => 'Boshqa vaqtda qulay'])
        ->assertOk()->assertJson(['pending_approval' => true]);
    $req = TimetableChangeRequest::sole();
    expect($req->approval_stage)->toBe('department')
        ->and($card->fresh()->day)->toBe(1)
        ->and(\App\Models\TimetableCardOverride::count())->toBe(0)
        ->and(TeacherNotification::where('teacher_id', $head->id)->count())->toBe(1)
        ->and(TeacherNotification::where('teacher_id', $reg->id)->count())->toBe(0);
    asRole($reg, 'registrator_ofisi')->postJson(route('admin.timetable.change-requests.approve', $req))->assertForbidden();
    asRole($teacher, 'oqituvchi')->postJson(route('admin.timetable.cards.week-override', $card),
        ['week' => 2, 'action' => 'move', 'day' => 3, 'pair' => 3])->assertForbidden();
    asRole($head, 'kafedra_mudiri')->getJson(route('admin.timetable.change-requests.index', $board))
        ->assertOk()->assertJsonPath('requests.0.can_review', true);
    asRole($head, 'kafedra_mudiri')->postJson(route('admin.timetable.change-requests.approve', $req))->assertOk();
    expect($req->fresh()->approval_stage)->toBe('registrar')
        ->and($req->fresh()->head_reviewer_id)->toBe($head->id)
        ->and(\App\Models\TimetableCardOverride::count())->toBe(0)
        ->and(TeacherNotification::where('teacher_id', $reg->id)->count())->toBe(1);
    asRole($head, 'kafedra_mudiri')->postJson(route('admin.timetable.change-requests.approve', $req))->assertForbidden();
    asRole($reg, 'registrator_ofisi')->postJson(route('admin.timetable.change-requests.approve', $req))->assertOk();
    expect($req->fresh()->status)->toBe('approved')
        ->and($card->fresh()->day)->toBe(1)
        ->and(\App\Models\TimetableCardOverride::sole()->only(['week', 'day', 'pair']))->toBe(['week' => 2, 'day' => 3, 'pair' => 3]);
});

it('teacher cannot propose for another teacher, change assignments, or choose invalid time', function () {
    ['head' => $head, 'teacher' => $teacher, 'card' => $card] = approvalSetup();
    $data = ['week' => 2, 'day' => 3, 'pair' => 3, 'reason' => 'Sabab'];
    $other = $card->replicate(); $other->teacher_id = $head->id; $other->save();
    asRole($teacher, 'oqituvchi')->postJson(route('admin.timetable.cards.time-proposal', $other), $data)->assertForbidden();
    asRole($teacher, 'oqituvchi')->postJson(route('admin.timetable.cards.time-proposal', $card),
        array_merge($data, ['week' => 30]))->assertUnprocessable();
    asRole($teacher, 'oqituvchi')->postJson(route('admin.timetable.cards.time-proposal', $card),
        array_merge($data, ['reason' => '']))->assertUnprocessable();
    asRole($teacher, 'oqituvchi')->postJson(route('admin.timetable.cards.time-proposal', $card),
        $data + ['teacher_id' => $head->id, 'layer' => 'external'])->assertOk();
    expect(TimetableChangeRequest::sole()->payload)->not->toHaveKeys(['teacher_id', 'layer'])
        ->and($card->fresh()->teacher_id)->toBe($teacher->id);
});

it('wrong department cannot review or see incoming teacher proposal and rejection leaves timetable alone', function () {
    ['head' => $head, 'teacher' => $teacher, 'board' => $board, 'card' => $card] = approvalSetup();
    asRole($teacher, 'oqituvchi')->postJson(route('admin.timetable.cards.time-proposal', $card),
        ['week' => 2, 'day' => 3, 'pair' => 3, 'reason' => 'Sabab'])->assertOk();
    $req = TimetableChangeRequest::sole();
    $head->forceFill(['head_department_hemis_id' => 999])->save();
    asRole($head, 'kafedra_mudiri')->getJson(route('admin.timetable.change-requests.index', $board))
        ->assertOk()->assertJsonCount(0, 'requests');
    asRole($head, 'kafedra_mudiri')->postJson(route('admin.timetable.change-requests.approve', $req))->assertForbidden();
    asRole($head, 'kafedra_mudiri')->postJson(route('admin.timetable.change-requests.reject', $req))->assertForbidden();
    $head->forceFill(['head_department_hemis_id' => null])->save();
    asRole($head, 'kafedra_mudiri')->postJson(route('admin.timetable.change-requests.reject', $req), ['note' => 'Mos emas'])->assertOk();
    expect($req->fresh()->status)->toBe('rejected')
        ->and(\App\Models\TimetableCardOverride::count())->toBe(0)
        ->and(TeacherNotification::where('teacher_id', $teacher->id)->latest('id')->first()->message)->toContain('Mos emas');
});

it('final approval rechecks conflicts and stale ownership', function () {
    ['head' => $head, 'reg' => $reg, 'teacher' => $teacher, 'card' => $card] = approvalSetup();
    asRole($teacher, 'oqituvchi')->postJson(route('admin.timetable.cards.time-proposal', $card),
        ['week' => 2, 'day' => 3, 'pair' => 3, 'reason' => 'Sabab'])->assertOk();
    $req = TimetableChangeRequest::sole();
    asRole($head, 'kafedra_mudiri')->postJson(route('admin.timetable.change-requests.approve', $req))->assertOk();
    $busy = $card->replicate(); $busy->day = 3; $busy->pair = 3; $busy->save();
    asRole($reg, 'registrator_ofisi')->postJson(route('admin.timetable.change-requests.approve', $req))->assertUnprocessable();
    expect($req->fresh()->status)->toBe('failed')->and(\App\Models\TimetableCardOverride::count())->toBe(0);
});

it('teacher can cancel a proposal after head approval, preventing application', function () {
    ['head' => $head, 'reg' => $reg, 'teacher' => $teacher, 'card' => $card] = approvalSetup();
    asRole($teacher, 'oqituvchi')->postJson(route('admin.timetable.cards.time-proposal', $card),
        ['week' => 2, 'day' => 3, 'pair' => 3, 'reason' => 'Sabab'])->assertOk();
    $req = TimetableChangeRequest::sole();
    asRole($head, 'kafedra_mudiri')->postJson(route('admin.timetable.change-requests.approve', $req))->assertOk();
    asRole($teacher, 'oqituvchi')->postJson(route('admin.timetable.change-requests.cancel', $req))->assertOk();
    asRole($reg, 'registrator_ofisi')->postJson(route('admin.timetable.change-requests.approve', $req))->assertForbidden();
    expect(\App\Models\TimetableCardOverride::count())->toBe(0);
});

it('pending proposals reserve group room and teacher until cancelled at either stage', function () {
    ['head' => $head, 'reg' => $reg, 'teacher' => $teacher, 'card' => $card] = approvalSetup(false);
    $second = $card->replicate();
    $second->teacher_id = $head->id; $second->teacher_name = $head->full_name;
    $second->subject_name = 'Boshqa fan'; $second->day = 4; $second->save();
    $head->assignRole('oqituvchi');
    $move = ['week' => 2, 'day' => 3, 'pair' => 3, 'reason' => 'Sabab'];
    asRole($teacher, 'oqituvchi')->postJson(route('admin.timetable.cards.time-proposal', $card), $move)->assertOk();
    $first = TimetableChangeRequest::sole();
    // Same group but a different teacher: pending booking must prevent another proposal.
    $conflict = asRole($head, 'oqituvchi')->postJson(route('admin.timetable.cards.time-proposal.check', $second), $move);
    $conflict->assertUnprocessable();
    expect($conflict->json('message'))->toContain('OQITUVCHI A.')->toContain('Gigiyena')->toContain('Tasdiqlash jarayonida');
    asRole($head, 'oqituvchi')->postJson(route('admin.timetable.cards.time-proposal', $second), $move)->assertUnprocessable();
    // Partial interval overlap is blocked as well, not only an identical start.
    asRole($head, 'oqituvchi')->postJson(route('admin.timetable.cards.time-proposal.check', $second),
        array_merge($move, ['pair' => 4]))->assertUnprocessable();
    asRole($head, 'kafedra_mudiri')->postJson(route('admin.timetable.change-requests.approve', $first))->assertOk();
    asRole($head, 'oqituvchi')->postJson(route('admin.timetable.cards.time-proposal.check', $second), $move)->assertUnprocessable();
    asRole($teacher, 'oqituvchi')->postJson(route('admin.timetable.change-requests.cancel', $first))->assertOk();
    asRole($head, 'oqituvchi')->postJson(route('admin.timetable.cards.time-proposal.check', $second), $move)->assertOk();
});

it('pending booking does not block unrelated groups teachers rooms or another week', function () {
    ['head' => $head, 'teacher' => $teacher, 'card' => $card] = approvalSetup();
    $other = $card->replicate();
    $other->teacher_id = $head->id; $other->teacher_name = $head->full_name;
    $other->group_name = 'OTHER-GROUP'; $other->day = 4; $other->save();
    $head->assignRole('oqituvchi');
    $move = ['week' => 2, 'day' => 3, 'pair' => 3, 'reason' => 'Sabab'];
    asRole($teacher, 'oqituvchi')->postJson(route('admin.timetable.cards.time-proposal', $card), $move)->assertOk();
    asRole($head, 'oqituvchi')->postJson(route('admin.timetable.cards.time-proposal.check', $other), $move)->assertOk();
    asRole($teacher, 'oqituvchi')->postJson(route('admin.timetable.cards.time-proposal.check', $card),
        array_merge($move, ['week' => 3]))->assertOk();
    // Updating one's own pending proposal replaces it, instead of conflicting with itself.
    asRole($teacher, 'oqituvchi')->postJson(route('admin.timetable.cards.time-proposal', $card),
        array_merge($move, ['pair' => 5]))->assertOk();
    expect(TimetableChangeRequest::where('status', 'pending')->count())->toBe(1)
        ->and(TimetableChangeRequest::where('status', 'cancelled')->count())->toBe(1);
});

it('existing timetable conflict is explained before a proposal is submitted', function () {
    ['teacher' => $teacher, 'card' => $card] = approvalSetup();
    $busy = $card->replicate(); $busy->subject_name = 'Band fan'; $busy->day = 3; $busy->pair = 3; $busy->save();
    $response = asRole($teacher, 'oqituvchi')->postJson(route('admin.timetable.cards.time-proposal.check', $card),
        ['week' => 2, 'day' => 3, 'pair' => 3]);
    $response->assertUnprocessable();
    expect($response->json('message'))->toContain('Guruh band')->toContain('Band fan');
    expect(TimetableChangeRequest::count())->toBe(0);
});

it('room and teacher reservations block even when the groups differ', function (string $resource) {
    ['head' => $head, 'teacher' => $teacher, 'card' => $card] = approvalSetup();
    $card->update(['auditorium_code' => '101', 'auditorium_name' => '101']);
    $other = $card->replicate();
    $other->group_name = 'OTHER'; $other->day = 4; $other->pair = 1;
    if ($resource === 'room') { $other->teacher_id = $head->id; $head->assignRole('oqituvchi'); }
    else { $other->auditorium_code = '102'; $other->auditorium_name = '102'; }
    $other->save();
    $move = ['week' => 2, 'day' => 3, 'pair' => 3, 'reason' => 'Sabab'];
    asRole($teacher, 'oqituvchi')->postJson(route('admin.timetable.cards.time-proposal', $card), $move)->assertOk();
    $actor = $resource === 'room' ? $head : $teacher;
    asRole($actor, 'oqituvchi')->postJson(route('admin.timetable.cards.time-proposal.check', $other), $move)->assertUnprocessable();
    asRole($head, 'kafedra_mudiri')->postJson(route('admin.timetable.change-requests.reject', TimetableChangeRequest::sole()),
        ['note' => 'Rad'])->assertOk();
    asRole($actor, 'oqituvchi')->postJson(route('admin.timetable.cards.time-proposal.check', $other), $move)->assertOk();
})->with(['room', 'teacher']);

it('final approval rejects a proposal if its source changes', function (string $field) {
    ['head' => $head, 'reg' => $reg, 'teacher' => $teacher, 'card' => $card] = approvalSetup();
    asRole($teacher, 'oqituvchi')->postJson(route('admin.timetable.cards.time-proposal', $card),
        ['week' => 2, 'day' => 3, 'pair' => 3, 'reason' => 'Sabab'])->assertOk();
    $req = TimetableChangeRequest::sole();
    asRole($head, 'kafedra_mudiri')->postJson(route('admin.timetable.change-requests.approve', $req))->assertOk();
    $card->update([$field => $field === 'teacher_id' ? $head->id : 5]);
    asRole($reg, 'registrator_ofisi')->postJson(route('admin.timetable.change-requests.approve', $req))->assertUnprocessable();
    expect($req->fresh()->status)->toBe('failed')->and(\App\Models\TimetableCardOverride::count())->toBe(0);
})->with(['teacher_id', 'day']);

it('teacher page exposes proposal and approval status without enabling direct editing', function () {
    ['teacher' => $teacher] = approvalSetup();
    $html = asRole($teacher, 'oqituvchi')->get(route('admin.timetable.index'))->assertOk()->getContent();
    expect($html)->toContain('id="changeReqBtn"')->toContain('const TIMETABLE_READ_ONLY = true')
        ->toContain('Taklif yuborish')->toContain('/time-proposal/check')->toContain('Kafedra mudiri tasdig‘ida');
});

it('kafedra mudiri bo\'sh darsga birinchi marta o\'qituvchi/xona biriktirsa tasdiqsiz, mavjudini almashtirsa tasdiq bilan', function () {
    ['head' => $head, 'teacher' => $teacher, 'card' => $card] = approvalSetup();
    $card->update(['teacher_id' => null, 'teacher_name' => null]);
    $own = $teacher->replicate()->forceFill(['hemis_id' => 777001, 'employee_id_number' => '777001', 'login' => 'own777001',
        'full_name' => 'YANGI O.', 'short_name' => 'YANGI O.', 'department_hemis_id' => 501, 'department' => 'Gigiyena kafedrasi']);
    $own->save();
    $teacher->forceFill(['department_hemis_id' => 501, 'department' => 'Gigiyena kafedrasi'])->save();
    \App\Models\Auditorium::create(['code' => 'R1', 'name' => '1-xona', 'active' => true, 'volume' => 30]);
    \App\Models\Auditorium::create(['code' => 'R2', 'name' => '2-xona', 'active' => true, 'volume' => 30]);
    $unit = ['faculty_name' => 'F1', 'specialty_name' => 'davolash ishi', 'course' => 2, 'subject_name' => 'Gigiyena',
        'training_type' => 'practice', 'group_name' => $card->group_name];

    // Birinchi marta — darhol
    asRole($head, 'kafedra_mudiri')->postJson(route('admin.timetable.assign-teacher', $card->board_id), $unit + ['teacher_id' => $teacher->id])
        ->assertOk()->assertJsonMissing(['pending_approval' => true]);
    asRole($head, 'kafedra_mudiri')->postJson(route('admin.timetable.assign-room', $card->board_id), $unit + ['auditorium_code' => 'R1'])
        ->assertOk()->assertJsonMissing(['pending_approval' => true]);
    expect($card->fresh()->only(['teacher_id', 'auditorium_code']))->toBe(['teacher_id' => $teacher->id, 'auditorium_code' => 'R1'])
        ->and(TimetableChangeRequest::where('status', 'pending')->count())->toBe(0);

    // Mavjudini almashtirish yoki olib tashlash — tasdiqqa
    asRole($head, 'kafedra_mudiri')->postJson(route('admin.timetable.assign-teacher', $card->board_id), $unit + ['teacher_id' => $own->id])
        ->assertOk()->assertJson(['pending_approval' => true]);
    asRole($head, 'kafedra_mudiri')->postJson(route('admin.timetable.assign-room', $card->board_id), $unit + ['auditorium_code' => 'R2'])
        ->assertOk()->assertJson(['pending_approval' => true]);
    asRole($head, 'kafedra_mudiri')->postJson(route('admin.timetable.assign-room', $card->board_id), $unit + ['auditorium_code' => null])
        ->assertOk()->assertJson(['pending_approval' => true]);
    expect($card->fresh()->only(['teacher_id', 'auditorium_code']))->toBe(['teacher_id' => $teacher->id, 'auditorium_code' => 'R1'])
        ->and(TimetableChangeRequest::where('status', 'pending')->count())->toBe(2)   // xona: oxirgi so'rov oldingisini almashtiradi
        ->and(TimetableChangeRequest::where('status', 'cancelled')->count())->toBe(1);
});

it('biriktirish Excel hisobotini registrator ofisi ham yuklaydi', function () {
    ['head' => $head, 'reg' => $reg, 'board' => $board] = approvalSetup();
    $res = asRole($reg, 'registrator_ofisi')->get(route('admin.timetable.assignment-report', $board))->assertOk();
    expect(substr($res->baseResponse->getFile()->getContent(), 0, 2))->toBe('PK');
    asRole($head, 'kafedra_mudiri')->get(route('admin.timetable.assignment-report', $board))->assertForbidden();
});

it('tasdiqsiz birinchi biriktirish mudirning shu dars bo\'yicha eski kutilayotgan so\'rovini bekor qiladi', function () {
    ['head' => $head, 'reg' => $reg, 'teacher' => $teacher, 'card' => $card] = approvalSetup();
    $teacher->forceFill(['department_hemis_id' => 501, 'department' => 'Gigiyena kafedrasi'])->save();
    $old = $teacher->replicate()->forceFill(['hemis_id' => 777002, 'employee_id_number' => '777002', 'login' => 'old777002',
        'full_name' => 'ESKI O.', 'short_name' => 'ESKI O.']);
    $old->save();
    $unit = ['faculty_name' => 'F1', 'specialty_name' => 'davolash ishi', 'course' => 2, 'subject_name' => 'Gigiyena',
        'training_type' => 'practice', 'group_name' => $card->group_name];

    // Darsda o'qituvchi bor — almashtirish tasdiqqa boradi.
    asRole($head, 'kafedra_mudiri')->postJson(route('admin.timetable.assign-teacher', $card->board_id), $unit + ['teacher_id' => $old->id])
        ->assertOk()->assertJson(['pending_approval' => true]);
    $stale = TimetableChangeRequest::where('status', 'pending')->sole();

    // Keyin o'qituvchi olib tashlandi (boshqa yo'l bilan) va mudir bo'sh darsga boshqasini darhol biriktirdi.
    $card->update(['teacher_id' => null, 'teacher_name' => null]);
    asRole($head, 'kafedra_mudiri')->postJson(route('admin.timetable.assign-teacher', $card->board_id), $unit + ['teacher_id' => $teacher->id])
        ->assertOk()->assertJsonMissing(['pending_approval' => true]);
    expect($card->fresh()->teacher_id)->toBe($teacher->id)
        ->and($stale->fresh()->status)->toBe('cancelled');

    // Eski so'rov endi tasdiqlanmaydi va yangi biriktiruvni bosib ketmaydi.
    asRole($reg, 'registrator_ofisi')->postJson(route('admin.timetable.change-requests.approve', $stale));
    expect($card->fresh()->teacher_id)->toBe($teacher->id)
        ->and($stale->fresh()->status)->toBe('cancelled');
});

it('sikl blokida ham bo\'sh darsga birinchi biriktirish tasdiqsiz, vaqt yoki mavjud o\'qituvchini o\'zgartirish tasdiq bilan', function () {
    ['head' => $head, 'teacher' => $teacher, 'card' => $card, 'board' => $board] = approvalSetup();
    $card->update(['teacher_id' => null, 'teacher_name' => null]);
    $teacher->forceFill(['department_hemis_id' => 501, 'department' => 'Gigiyena kafedrasi'])->save();
    \App\Models\Auditorium::create(['code' => 'R1', 'name' => '1-xona', 'active' => true, 'volume' => 30]);
    $placement = \App\Models\TimetableCyclePlacement::create(['board_id' => $board->id, 'specialty_name' => 'davolash ishi', 'course' => 2,
        'group_name' => 'F1 / 1-oqim', 'subject_name' => 'Gigiyena', 'start_index' => 0, 'training_type' => 'practice', 'lesson_time' => '08:30']);
    $flow = ['specialty_name' => 'davolash ishi', 'course' => 2, 'group_name' => 'F1 / 1-oqim', 'subject_name' => 'Gigiyena', 'training_type' => 'practice'];

    asRole($head, 'kafedra_mudiri')->postJson(route('admin.timetable.cycle-assign', $board), $flow + ['teacher_id' => $teacher->id, 'auditorium_code' => 'R1', 'lesson_time' => '08:30'])
        ->assertOk()->assertJsonMissing(['pending_approval' => true]);
    expect($card->fresh()->only(['teacher_id', 'auditorium_code']))->toBe(['teacher_id' => $teacher->id, 'auditorium_code' => 'R1'])
        ->and(TimetableChangeRequest::where('status', 'applied')->count())->toBe(1);

    // Vaqtni o'zgartirish yoki xonani olib tashlash — tasdiqqa.
    asRole($head, 'kafedra_mudiri')->postJson(route('admin.timetable.cycle-assign', $board), $flow + ['teacher_id' => $teacher->id, 'auditorium_code' => 'R1', 'lesson_time' => '10:00'])
        ->assertOk()->assertJson(['pending_approval' => true]);
    asRole($head, 'kafedra_mudiri')->postJson(route('admin.timetable.cycle-assign', $board), $flow + ['teacher_id' => $teacher->id, 'lesson_time' => '08:30'])
        ->assertOk()->assertJson(['pending_approval' => true]);
    expect($card->fresh()->only(['teacher_id', 'auditorium_code']))->toBe(['teacher_id' => $teacher->id, 'auditorium_code' => 'R1'])
        ->and($placement->fresh()->lesson_time)->toBe('08:30');
});

it('birinchi biriktirish doska qulfi ostida qayta tekshiriladi: qulfni kutayotganda dars band bo\'lsa — tasdiqqa', function () {
    ['head' => $head, 'teacher' => $teacher, 'card' => $card] = approvalSetup();
    $card->update(['teacher_id' => null, 'teacher_name' => null]);
    $teacher->forceFill(['department_hemis_id' => 501, 'department' => 'Gigiyena kafedrasi'])->save();
    $other = $teacher->replicate()->forceFill(['hemis_id' => 777003, 'employee_id_number' => '777003', 'login' => 'oth777003',
        'full_name' => 'BOSHQA O.', 'short_name' => 'BOSHQA O.']);
    $other->save();
    // Parallel so'rov: biz doska qulfini kutayotganimizda boshqasi shu darsga o'qituvchi yozib bo'ldi.
    $raced = false;
    DB::listen(function ($q) use (&$raced, $card, $other) {
        if (!$raced && str_contains($q->sql, 'timetable_boards') && str_contains(strtolower($q->sql), 'for update')) {
            $raced = true;
            DB::table('timetable_cards')->where('id', $card->id)->update(['teacher_id' => $other->id, 'teacher_name' => 'BOSHQA O.']);
        }
    });
    $unit = ['faculty_name' => 'F1', 'specialty_name' => 'davolash ishi', 'course' => 2, 'subject_name' => 'Gigiyena',
        'training_type' => 'practice', 'group_name' => $card->group_name];

    asRole($head, 'kafedra_mudiri')->postJson(route('admin.timetable.assign-teacher', $card->board_id), $unit + ['teacher_id' => $teacher->id])
        ->assertOk()->assertJson(['pending_approval' => true]);
    expect($raced)->toBeTrue()
        ->and($card->fresh()->teacher_id)->toBe($other->id);
});

it('birinchi biriktirish boshqa yo\'ldan yuborilgan, shu darsning shu maydonini o\'zgartiradigan eski so\'rovni ham bekor qiladi', function () {
    ['head' => $head, 'reg' => $reg, 'teacher' => $teacher, 'card' => $card, 'board' => $board] = approvalSetup();
    $card->update(['teacher_id' => null, 'teacher_name' => null, 'auditorium_code' => 'R1', 'auditorium_name' => '1-xona']);
    $teacher->forceFill(['department_hemis_id' => 501, 'department' => 'Gigiyena kafedrasi'])->save();
    $old = $teacher->replicate()->forceFill(['hemis_id' => 777004, 'employee_id_number' => '777004', 'login' => 'old777004',
        'full_name' => 'ESKI O.', 'short_name' => 'ESKI O.']);
    $old->save();
    \App\Models\Auditorium::create(['code' => 'R1', 'name' => '1-xona', 'active' => true, 'volume' => 30]);
    \App\Models\Auditorium::create(['code' => 'R2', 'name' => '2-xona', 'active' => true, 'volume' => 30]);
    \App\Models\TimetableCyclePlacement::create(['board_id' => $board->id, 'specialty_name' => 'davolash ishi', 'course' => 2,
        'group_name' => 'F1 / 1-oqim', 'subject_name' => 'Gigiyena', 'start_index' => 0, 'training_type' => 'practice', 'lesson_time' => '08:30']);
    $unit = ['faculty_name' => 'F1', 'specialty_name' => 'davolash ishi', 'course' => 2, 'subject_name' => 'Gigiyena',
        'training_type' => 'practice', 'group_name' => $card->group_name];

    // Sikl oynasidan: o'qituvchi + yangi vaqt — tasdiqqa. Biriktirish oynasidan: xonani almashtirish — tasdiqqa.
    asRole($head, 'kafedra_mudiri')->postJson(route('admin.timetable.cycle-assign', $board), ['specialty_name' => 'davolash ishi', 'course' => 2,
        'group_name' => 'F1 / 1-oqim', 'subject_name' => 'Gigiyena', 'training_type' => 'practice', 'teacher_id' => $old->id, 'auditorium_code' => 'R1', 'lesson_time' => '10:00'])
        ->assertOk()->assertJson(['pending_approval' => true]);
    asRole($head, 'kafedra_mudiri')->postJson(route('admin.timetable.assign-room', $board), $unit + ['auditorium_code' => 'R2'])
        ->assertOk()->assertJson(['pending_approval' => true]);
    $cycle = TimetableChangeRequest::where('route_name', 'admin.timetable.cycle-assign')->sole();
    $room = TimetableChangeRequest::where('route_name', 'admin.timetable.assign-room')->sole();

    // Bo'sh darsga o'qituvchi darhol: sikl so'rovi ham o'qituvchini yozadi — eskiradi; xona so'rovi o'qituvchiga tegmaydi — qoladi.
    asRole($head, 'kafedra_mudiri')->postJson(route('admin.timetable.assign-teacher', $board), $unit + ['teacher_id' => $teacher->id])
        ->assertOk()->assertJsonMissing(['pending_approval' => true]);
    expect($cycle->fresh()->status)->toBe('cancelled')
        ->and($room->fresh()->status)->toBe('pending');

    asRole($reg, 'registrator_ofisi')->postJson(route('admin.timetable.change-requests.approve', $cycle));
    expect($card->fresh()->teacher_id)->toBe($teacher->id);
});

// ---- O'qituvchi so'rovi: sana (ertadan), ikki bosqich, muddati o'tgan, xabarlar, sikl ----

/** Kartaning shu haftadagi dushanbasi (2-hafta) — sinov soatini shu kunga nisbatan qo'yish uchun. */
function tpMonday(TimetableCard $card, int $week = 2): \Illuminate\Support\Carbon
{
    $ctl = app(\App\Http\Controllers\Admin\TimetableController::class);

    return \Illuminate\Support\Carbon::parse((new ReflectionMethod($ctl, 'weekStartDate'))->invoke($ctl, $card->board, $card, $week)->toDateString(), 'Asia/Tashkent');
}

it('o\'qituvchi so\'rovi: bugungi yoki o\'tgan darsga va bugungi yangi sanaga so\'rov yuborib bo\'lmaydi (Asia/Tashkent)', function () {
    ['teacher' => $teacher, 'card' => $card] = approvalSetup();
    $monday = tpMonday($card);   // dars: dushanba
    $move = ['week' => 2, 'day' => 3, 'pair' => 3, 'reason' => 'Sabab'];
    test()->travelTo($monday->copy()->setTime(7, 0));
    $r = asRole($teacher, 'oqituvchi')->postJson(route('admin.timetable.cards.time-proposal', $card), $move)->assertUnprocessable();
    expect($r->json('message'))->toContain("Bugungi yoki o'tgan darsning")->and(TimetableChangeRequest::count())->toBe(0);
    asRole($teacher, 'oqituvchi')->postJson(route('admin.timetable.cards.time-proposal.check', $card), $move)->assertUnprocessable();
    // Dars chorshanbada, bugun seshanba (Toshkentda 00:30, UTC bo'yicha hali dushanba) — yangi kun seshanba bo'lsa rad.
    $card->update(['day' => 3, 'pair' => 1]);
    test()->travelTo(\Illuminate\Support\Carbon::parse($monday->copy()->addDay()->toDateString() . ' 00:30', 'Asia/Tashkent'));
    $r = asRole($teacher, 'oqituvchi')->postJson(route('admin.timetable.cards.time-proposal', $card), array_merge($move, ['day' => 2]))->assertUnprocessable();
    expect($r->json('message'))->toContain('kamida ertangi kun');
    // Ertangi kun — mumkin.
    asRole($teacher, 'oqituvchi')->postJson(route('admin.timetable.cards.time-proposal', $card), array_merge($move, ['day' => 4]))->assertOk();
});

it('o\'qituvchi so\'rovi: ertangi dars ikki bosqichdan o\'tadi, jadvalga tushadi; o\'qituvchiga har bosqich, talabalarga batafsil xabar', function () {
    ['head' => $head, 'reg' => $reg, 'teacher' => $teacher, 'board' => $board, 'card' => $card] = approvalSetup(false);
    $card->update(['auditorium_code' => '215', 'auditorium_name' => '215']);
    DB::table('students')->where('hemis_id', 9001)->update(['telegram_chat_id' => '777', 'telegram_verified_at' => now()]);
    config(['services.telegram.bot_token' => 'TOKEN']);
    \Illuminate\Support\Facades\Http::fake(['api.telegram.org/*' => \Illuminate\Support\Facades\Http::response(['ok' => true])]);
    $monday = tpMonday($card);
    test()->travelTo($monday->copy()->subDay()->setTime(10, 0));   // yakshanba: dushanbadagi dars — ertaga
    asRole($teacher, 'oqituvchi')->postJson(route('admin.timetable.cards.time-proposal', $card),
        ['week' => 2, 'day' => 3, 'pair' => 3, 'reason' => 'Konferensiya'])->assertOk()->assertJsonPath('pending_approval', true);
    $req = TimetableChangeRequest::sole();
    expect($req->approval_stage)->toBe('department')
        ->and($req->details['notice']['old'])->toBe('Dushanba, ' . $monday->format('d.m.Y') . ' 08:30–09:50')
        ->and($req->details['notice']['new'])->toBe('Chorshanba, ' . $monday->copy()->addDays(2)->format('d.m.Y') . ' 10:00–11:20');
    asRole($head, 'kafedra_mudiri')->postJson(route('admin.timetable.change-requests.approve', $req))->assertOk();
    expect(TeacherNotification::where('teacher_id', $teacher->id)->pluck('title')->all())->toBe(["Vaqt o'zgarishi: kafedra mudiri tasdiqladi"])
        ->and(\App\Models\TimetableCardOverride::count())->toBe(0);
    asRole($reg, 'registrator_ofisi')->postJson(route('admin.timetable.change-requests.approve', $req))->assertOk();
    $req->refresh();
    expect($req->status)->toBe('approved')
        ->and(\App\Models\TimetableCardOverride::sole()->only(['week', 'day', 'pair']))->toBe(['week' => 2, 'day' => 3, 'pair' => 3])
        ->and(array_column($req->details['steps'], 'action'))->toBe([
            "So'rov yuborildi: " . $req->details['notice']['old'] . ' → ' . $req->details['notice']['new'],
            'Kafedra mudiri tasdiqladi — registrator ofisiga yuborildi',
            "Registrator ofisi tasdiqladi — jadvalga qo'llandi"])
        ->and($req->details['steps'][0]['note'])->toBe('Konferensiya')
        ->and(TeacherNotification::where('teacher_id', $teacher->id)->latest('id')->value('title'))->toBe('Dars jadvali o\'zgarishi tasdiqlandi');
    $msg = StudentNotification::where('type', 'timetable_change')->sole();   // faqat F1 fakultetidagi talaba
    expect($msg->message)->toContain('Fan: Gigiyena')->toContain("O'qituvchi: OQITUVCHI A.")
        ->toContain('Eski vaqt: Dushanba, ' . $monday->format('d.m.Y') . ' 08:30–09:50')
        ->toContain('Yangi vaqt: Chorshanba, ' . $monday->copy()->addDays(2)->format('d.m.Y') . ' 10:00–11:20')->toContain('Xona: 215');
    \Illuminate\Support\Facades\Http::assertSent(fn ($r) => str_contains($r->url(), 'api.telegram.org') && ($r->data()['chat_id'] ?? null) == '777'
        && str_contains($r->data()['text'] ?? '', 'Yangi vaqt: Chorshanba'));
    // Tarix oynasi: eski → yangi va qadamlar.
    asRole($reg, 'registrator_ofisi')->getJson(route('admin.timetable.change-requests.index', $board))->assertOk()
        ->assertJsonPath('requests.0.notice.room', '215')->assertJsonCount(3, 'requests.0.steps');
});

it('o\'qituvchi so\'rovi: tasdiq kechiksa (dars sanasi o\'tib ketsa) — "Muddati o\'tgan", jadval o\'zgarmaydi, o\'qituvchiga xabar', function (string $stage) {
    ['head' => $head, 'reg' => $reg, 'teacher' => $teacher, 'card' => $card] = approvalSetup();
    $card->update(['day' => 4]);   // payshanba
    $monday = tpMonday($card);
    test()->travelTo($monday->copy()->setTime(9, 0));
    asRole($teacher, 'oqituvchi')->postJson(route('admin.timetable.cards.time-proposal', $card),
        ['week' => 2, 'day' => 3, 'pair' => 3, 'reason' => 'Sabab'])->assertOk();
    $req = TimetableChangeRequest::sole();
    if ($stage === 'registrar') {
        asRole($head, 'kafedra_mudiri')->postJson(route('admin.timetable.change-requests.approve', $req))->assertOk();
    }
    test()->travelTo($monday->copy()->addDays(2)->setTime(8, 0));   // chorshanba — yangi sana bugun
    $actor = $stage === 'registrar' ? [$reg, 'registrator_ofisi'] : [$head, 'kafedra_mudiri'];
    $r = asRole(...$actor)->postJson(route('admin.timetable.change-requests.approve', $req))->assertUnprocessable();
    expect($r->json('error'))->toContain("Muddati o'tgan")
        ->and($req->fresh()->status)->toBe('expired')
        ->and(\App\Models\TimetableCardOverride::count())->toBe(0)
        ->and(TeacherNotification::where('teacher_id', $teacher->id)->latest('id')->value('title'))->toBe("Vaqt o'zgarishi so'rovi muddati o'tgan")
        ->and(collect($req->fresh()->details['steps'])->last()['action'])->toBe("Muddati o'tgan");
})->with(['department', 'registrar']);

it('o\'qituvchi so\'rovi: to\'qnashuv (guruh, o\'qituvchi, amaliy blok bo\'linishi) bo\'lsa yuborilmaydi; kafedra mudiri bosqichida paydo bo\'lsa — qo\'llanmaydi', function () {
    ['head' => $head, 'teacher' => $teacher, 'card' => $card] = approvalSetup();
    $move = ['week' => 2, 'day' => 3, 'pair' => 3, 'reason' => 'Sabab'];
    // Boshqa fanning o'qituvchisi shu o'qituvchi (boshqa guruh) — yangi vaqtda band.
    $other = $card->replicate(); $other->group_name = 'BOSHQA'; $other->subject_name = 'Boshqa fan'; $other->day = 3; $other->pair = 3; $other->save();
    $r = asRole($teacher, 'oqituvchi')->postJson(route('admin.timetable.cards.time-proposal', $card), $move)->assertUnprocessable();
    expect($r->json('message'))->toContain("O'qituvchi band")->and(TimetableChangeRequest::count())->toBe(0);
    // Boshqa o'qituvchining boshqa guruh, boshqa xonadagi darsi shu vaqtda — to'siq emas.
    $other->update(['teacher_id' => $head->id, 'auditorium_code' => '999', 'auditorium_name' => '999']);
    asRole($teacher, 'oqituvchi')->postJson(route('admin.timetable.cards.time-proposal.check', $card), $move)->assertOk();
    $other->delete();
    // Bir kunlik amaliy blok (08:30 va 10:00 — ketma-ket) — bittasini yolg'iz ko'chirib bo'lmaydi.
    $tail = $card->replicate(); $tail->pair = 3; $tail->teacher_id = $head->id; $tail->save();
    $r = asRole($teacher, 'oqituvchi')->postJson(route('admin.timetable.cards.time-proposal', $card), $move)->assertUnprocessable();
    expect($r->json('message'))->toContain('amaliy blokning bir qismi')->toContain("bir kunda bir fandan amaliy soatlar bo'linmaydi");
    // Yangi kunda shu fandan amaliy bor (yopishmaydi) — ham rad.
    $tail->update(['day' => 3, 'pair' => 7]);
    $r = asRole($teacher, 'oqituvchi')->postJson(route('admin.timetable.cards.time-proposal', $card), $move)->assertUnprocessable();
    expect($r->json('message'))->toContain('Yangi kunda shu fandan boshqa amaliy dars bor');
    // Yopishib turadigan joyga (bitta blok bo'ladi) — mumkin.
    $tail->update(['day' => 3, 'pair' => 5]);
    asRole($teacher, 'oqituvchi')->postJson(route('admin.timetable.cards.time-proposal', $card), $move)->assertOk();
    // Kafedra mudiri tasdiqlayotganda shu vaqtni boshqa dars egallagan — qayta tekshiruv: qo'llanmaydi, o'qituvchiga xabar.
    $busy = $card->replicate(); $busy->subject_name = 'Band fan'; $busy->teacher_id = $head->id; $busy->day = 3; $busy->pair = 3; $busy->save();
    $req = TimetableChangeRequest::sole();
    asRole($head, 'kafedra_mudiri')->postJson(route('admin.timetable.change-requests.approve', $req))->assertUnprocessable();
    expect($req->fresh()->status)->toBe('failed')->and($req->fresh()->error)->toContain('Guruh band')
        ->and(TeacherNotification::where('teacher_id', $teacher->id)->latest('id')->value('title'))->toBe("Vaqt o'zgarishi qo'llanmadi");
});

it('o\'qituvchi so\'rovi: registrator rad etsa jadval o\'zgarmaydi; o\'qituvchi o\'zi kafedra mudiri bo\'lsa — to\'g\'ridan-to\'g\'ri registratorga', function () {
    ['head' => $head, 'reg' => $reg, 'teacher' => $teacher, 'card' => $card] = approvalSetup();
    $move = ['week' => 2, 'day' => 3, 'pair' => 3, 'reason' => 'Sabab'];
    asRole($teacher, 'oqituvchi')->postJson(route('admin.timetable.cards.time-proposal', $card), $move)->assertOk();
    $req = TimetableChangeRequest::sole();
    asRole($head, 'kafedra_mudiri')->postJson(route('admin.timetable.change-requests.approve', $req))->assertOk();
    asRole($reg, 'registrator_ofisi')->postJson(route('admin.timetable.change-requests.reject', $req), ['note' => 'Xona yo\'q'])->assertOk();
    expect($req->fresh()->status)->toBe('rejected')->and(\App\Models\TimetableCardOverride::count())->toBe(0)
        ->and(TeacherNotification::where('teacher_id', $teacher->id)->latest('id')->value('title'))->toBe('Dars jadvali o\'zgarishi rad etildi (registrator ofisi)')
        ->and(collect($req->fresh()->details['steps'])->last())->toMatchArray(['action' => 'Registrator ofisi rad etdi', 'note' => 'Xona yo\'q']);

    // Kafedra mudirining o'z darsi: 1-bosqich bo'lmaydi (o'zini o'zi tasdiqlamaydi).
    $head->assignRole('oqituvchi');
    $own = $card->replicate(); $own->teacher_id = $head->id; $own->teacher_name = $head->full_name; $own->group_name = 'MUDIR-GURUH'; $own->save();
    asRole($head, 'oqituvchi')->postJson(route('admin.timetable.cards.time-proposal', $own), array_merge($move, ['pair' => 5]))
        ->assertOk()->assertJsonPath('message', fn ($m) => str_contains($m, 'registrator ofisiga'));
    $mine = TimetableChangeRequest::latest('id')->first();
    expect($mine->approval_stage)->toBe('registrar')->and($mine->details['head_skipped'])->toBeTrue()
        ->and(TeacherNotification::where('teacher_id', $reg->id)->where('title', 'like', '%tasdiq kutilmoqda%')->count())->toBeGreaterThan(0);
    asRole($head, 'kafedra_mudiri')->postJson(route('admin.timetable.change-requests.approve', $mine))->assertForbidden();
    asRole($reg, 'registrator_ofisi')->postJson(route('admin.timetable.change-requests.approve', $mine))->assertOk();
    expect($mine->fresh()->status)->toBe('approved')
        ->and(\App\Models\TimetableCardOverride::where('card_id', $own->id)->sole()->only(['day', 'pair']))->toBe(['day' => 3, 'pair' => 5]);
});

/** 5-kurs sikli (Gigiyena, 2 kun): g5-01a — OQITUVCHI A., g5-01b — shu o'qituvchi, smenasi 13:30. */
function tpCycle(array $s): array
{
    $s['board']->update(['bell_schedule' => TimetableBoard::defaultBellSchedule(6)]);   // 6 para — kunda 12 soat qatori
    \App\Models\TimetableSubjectSetting::create(['board_id' => $s['board']->id, 'specialty_name' => 'davolash ishi', 'course' => 5,
        'subject_name' => 'Gigiyena', 'mode' => 'cycle', 'cycle_days' => 2]);
    $base = ['board_id' => $s['board']->id, 'faculty_name' => 'F1', 'specialty_name' => 'davolash ishi', 'course' => 5, 'oqim_label' => '1-oqim',
        'lang' => 'uz', 'students' => 10, 'len_half' => 2, 'subject_name' => 'Gigiyena', 'training_type' => 'practice', 'kafedra_name' => 'Gigiyena kafedrasi',
        'teacher_id' => $s['teacher']->id, 'teacher_name' => 'OQITUVCHI A.'];
    TimetableCard::create($base + ['group_name' => 'g5-01a', 'auditorium_code' => '301', 'auditorium_name' => '301']);
    $b = TimetableCard::create($base + ['group_name' => 'g5-01b', 'auditorium_code' => '302', 'auditorium_name' => '302']);
    $pl = \App\Models\TimetableCyclePlacement::create(['board_id' => $s['board']->id, 'specialty_name' => 'davolash ishi', 'course' => 5,
        'group_name' => 'F1 / 1-oqim', 'subject_name' => 'Gigiyena', 'start_index' => 7, 'training_type' => 'practice',
        'lecture_slots' => ['shift_g' => ['g5-01b' => 7]]]);
    DB::table('students')->insert(['hemis_id' => 9101, 'full_name' => 'S', 'short_name' => 'S', 'first_name' => 'S', 'second_name' => 'S', 'third_name' => 'S',
        'student_id_number' => '9101', 'group_name' => 'g5-01a', 'student_status_code' => 11, 'department_id' => 77, 'department_name' => 'F1',
        'created_at' => now(), 'updated_at' => now()]);
    $ctl = app(\App\Http\Controllers\Admin\TimetableController::class);
    $units = $ctl->cycleProposalUnits($s['board'], ['specialty_name' => 'davolash ishi', 'course' => 5, 'subject_name' => 'Gigiyena', 'group_name' => 'g5-01a'], null);

    return ['placement' => $pl, 'dates' => $units[0]['dates'], 'prac' => $units[0]['prac'], 'g5b' => $b];
}

it('o\'qituvchi so\'rovi (sikl): amaliy darsni shu kunga boshqa soatga — ikki bosqich, jadvalda kun rejasi o\'zgaradi; band soat va boshqa o\'qituvchi — rad', function () {
    $s = approvalSetup();
    ['placement' => $pl, 'dates' => $dates, 'prac' => $prac, 'g5b' => $g5b] = tpCycle($s);
    expect(count($dates))->toBe(2)->and($prac[$dates[1]])->toBe([1, 7]);   // 08:30 dan 6 soat
    test()->travelTo(\Illuminate\Support\Carbon::parse($dates[0] . ' 10:00', 'Asia/Tashkent'));   // 2-kun — ertaga
    $body = ['specialty_name' => 'davolash ishi', 'course' => 5, 'subject_name' => 'Gigiyena', 'group_name' => 'g5-01a', 'date' => $dates[1], 'scope' => 'day'];
    // 13:30 — o'qituvchi shu vaqtda g5-01b da (smena 13:30).
    $r = asRole($s['teacher'], 'oqituvchi')->postJson(route('admin.timetable.cycle-time-proposal.check', $s['board']), $body + ['start_hour' => 7])->assertUnprocessable();
    expect($r->json('message'))->toContain("O'qituvchi band");
    // Bugungi kun — rad; boshqa o'qituvchi — 403.
    asRole($s['teacher'], 'oqituvchi')->postJson(route('admin.timetable.cycle-time-proposal.check', $s['board']), array_merge($body, ['date' => $dates[0], 'start_hour' => 3]))
        ->assertUnprocessable()->assertJsonPath('message', fn ($m) => str_contains($m, "Bugungi yoki o'tgan"));
    $s['head']->assignRole('oqituvchi');
    asRole($s['head'], 'oqituvchi')->postJson(route('admin.timetable.cycle-time-proposal', $s['board']), $body + ['start_hour' => 3, 'reason' => 'x'])->assertForbidden();
    // Sig'maydi (6 soat 8-qatordan; kunda 12 qator) — rad.
    asRole($s['teacher'], 'oqituvchi')->postJson(route('admin.timetable.cycle-time-proposal.check', $s['board']), $body + ['start_hour' => 8])
        ->assertUnprocessable()->assertJsonPath('message', fn ($m) => str_contains($m, "sig‘maydi"));
    // 10:00 dan — bo'sh: so'rov → kafedra mudiri → registrator.
    // g5-01b boshqa o'qituvchiga o'tdi — 13:30 dan bo'sh: so'rov → kafedra mudiri → registrator.
    $g5b->update(['teacher_id' => $s['head']->id, 'teacher_name' => $s['head']->full_name]);
    request()->attributes->remove('timetable.cycle_units.' . $s['board']->id);
    asRole($s['teacher'], 'oqituvchi')->postJson(route('admin.timetable.cycle-time-proposal.check', $s['board']), $body + ['start_hour' => 7])
        ->assertOk()->assertJsonPath('new', \Illuminate\Support\Carbon::parse($dates[1])->format('d.m.Y') . ' (2-kun), 13:30–17:50')
        ->assertJsonPath('old', \Illuminate\Support\Carbon::parse($dates[1])->format('d.m.Y') . ' (2-kun), 08:30–13:20');
    asRole($s['teacher'], 'oqituvchi')->postJson(route('admin.timetable.cycle-time-proposal', $s['board']), $body + ['start_hour' => 7, 'reason' => 'Klinika'])->assertOk();
    $req = TimetableChangeRequest::sole();
    expect($req->route_name)->toBe('admin.timetable.cycle-practice-starts')->and($req->approval_stage)->toBe('department')
        ->and($req->payload['units'])->toBe(['g5-01a']);
    asRole($s['head'], 'kafedra_mudiri')->postJson(route('admin.timetable.change-requests.approve', $req))->assertOk();
    expect($pl->fresh()->lecture_slots)->not->toHaveKey('ps_g');
    asRole($s['reg'], 'registrator_ofisi')->postJson(route('admin.timetable.change-requests.approve', $req))->assertOk();
    expect($req->fresh()->status)->toBe('approved')
        ->and($pl->fresh()->lecture_slots['ps_g'])->toBe(['g5-01a' => [1 => 7]])
        ->and($pl->fresh()->lecture_slots['shift_g'])->toBe(['g5-01b' => 7]);
    request()->attributes->remove('timetable.cycle_units.' . $s['board']->id);
    $after = app(\App\Http\Controllers\Admin\TimetableController::class)->cycleProposalUnits($s['board'],
        ['specialty_name' => 'davolash ishi', 'course' => 5, 'subject_name' => 'Gigiyena', 'group_name' => 'g5-01a'], null)[0]['prac'];
    expect($after[$dates[0]])->toBe([1, 7])->and($after[$dates[1]])->toBe([7, 13]);
    expect(StudentNotification::where('student_id', DB::table('students')->where('hemis_id', 9101)->value('id'))->sole()->message)
        ->toContain('Fan: Gigiyena')->toContain('Yangi vaqt: ' . \Illuminate\Support\Carbon::parse($dates[1])->format('d.m.Y') . ' (2-kun), 13:30–17:50');
});

/** Jadvalga yozuv: majburiy (NOT NULL, sukutsiz) ustunlar soxta qiymat bilan. */
function tpInsert(string $table, array $data): void
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

/** Haftalik karta shu sanaga tushadigan hafta va kun. */
function tpWeekOf(TimetableCard $card, string $date): array
{
    for ($w = 1; $w <= 10; $w++) {
        $monday = tpMonday($card, $w);
        if ($monday->toDateString() <= $date && $date <= $monday->copy()->addDays(6)->toDateString()) {
            return [$w, \Illuminate\Support\Carbon::parse($date)->dayOfWeekIso];
        }
    }
    throw new RuntimeException('hafta topilmadi');
}

it('o\'qituvchi so\'rovi: yangi vaqtda o\'qituvchi sikl darsida band bo\'lsa (unit-busy) va vaqt o\'zgarmasa — yuborilmaydi', function () {
    $s = approvalSetup();
    ['dates' => $dates] = tpCycle($s);
    [$week, $day] = tpWeekOf($s['card'], $dates[1]);
    test()->travelTo(tpMonday($s['card'], $week)->subDay()->setTime(10, 0));   // haftalik dars (dushanba) ham, yangi kun ham oldinda
    // OQITUVCHI A. shu kuni 08:30–13:20 sikl amaliy darsida (g5-01a) — haftalik darsni 08:30 ga ko'chirib bo'lmaydi.
    $r = asRole($s['teacher'], 'oqituvchi')->postJson(route('admin.timetable.cards.time-proposal.check', $s['card']), ['week' => $week, 'day' => $day, 'pair' => 1])
        ->assertUnprocessable();
    expect($r->json('message'))->toContain("O'qituvchi band: g5-01a, Gigiyena");
    // 13:30 da shu o'qituvchi g5-01b (smena 13:30) sikl darsida — ham band.
    asRole($s['teacher'], 'oqituvchi')->postJson(route('admin.timetable.cards.time-proposal.check', $s['card']), ['week' => $week, 'day' => $day, 'pair' => 7])
        ->assertUnprocessable();
    // Hozirgi joyi — "o'zgarmaydi".
    [$w2] = tpWeekOf($s['card'], $dates[1]);
    $r = asRole($s['teacher'], 'oqituvchi')->postJson(route('admin.timetable.cards.time-proposal.check', $s['card']), ['week' => $w2 + 1, 'day' => 1, 'pair' => 1])
        ->assertUnprocessable();
    expect($r->json('message'))->toContain('hozirgisi bilan bir xil');
});

it('o\'qituvchi so\'rovi (sikl): ma\'ruza vaqti bilan, guruhning haftalik darsi bilan ustma-ust va o\'zgarmas vaqt — rad', function () {
    $s = approvalSetup();
    ['dates' => $dates, 'g5b' => $g5b] = tpCycle($s);
    $g5b->update(['teacher_id' => $s['head']->id, 'teacher_name' => $s['head']->full_name]);
    // KTR: 1-kun ma'ruza 2 soat + amaliy 4 soat, 2-kun amaliy 6 soat; oqim ma'ruzasi — kafedra mudiri.
    TimetableCard::create(['board_id' => $s['board']->id, 'faculty_name' => 'F1', 'specialty_name' => 'davolash ishi', 'course' => 5, 'oqim_label' => '1-oqim',
        'lang' => 'uz', 'students' => 20, 'len_half' => 2, 'subject_name' => 'Gigiyena', 'training_type' => 'lecture', 'group_name' => null,
        'group_names' => ['g5-01a', 'g5-01b'], 'kafedra_name' => 'Gigiyena kafedrasi', 'teacher_id' => $s['head']->id, 'teacher_name' => $s['head']->full_name,
        'auditorium_code' => 'L1', 'auditorium_name' => 'L1']);
    tpInsert('specialties', ['specialty_hemis_id' => 77, 'name' => 'davolash ishi']);
    tpInsert('curricula', ['curricula_hemis_id' => 260, 'specialty_hemis_id' => 77, 'department_hemis_id' => 1]);
    tpInsert('semesters', ['curriculum_hemis_id' => 260, 'code' => '19', 'education_year' => '2026', 'level_code' => '15']);
    tpInsert('curriculum_subjects', ['curriculum_subject_hemis_id' => 2, 'curricula_hemis_id' => 260, 'subject_id' => 900, 'subject_name' => 'Gigiyena',
        'semester_code' => '19', 'semester_name' => '9', 'department_id' => 501, 'department_name' => 'Gigiyena kafedrasi', 'at_semester' => 1,
        'subject_details' => json_encode([['trainingType' => ['code' => '11', 'name' => "Ma'ruza"], 'academic_load' => 2],
            ['trainingType' => ['code' => '13', 'name' => 'Amaliy'], 'academic_load' => 10]])]);
    DB::table('ktr_plans')->insert(['curriculum_subject_id' => DB::table('curriculum_subjects')->where('subject_id', 900)->value('id'), 'week_count' => 2,
        'plan_data' => json_encode(['hours' => ['1' => ['11' => 2, '13' => 4], '2' => ['13' => 6]]]), 'created_at' => now(), 'updated_at' => now()]);
    request()->attributes->remove('timetable.cycle_units.' . $s['board']->id);
    test()->travelTo(\Illuminate\Support\Carbon::parse($dates[0] . ' 07:00', 'Asia/Tashkent')->subDay());
    $body = ['specialty_name' => 'davolash ishi', 'course' => 5, 'subject_name' => 'Gigiyena', 'group_name' => 'g5-01a', 'date' => $dates[0], 'scope' => 'day'];
    $check = fn (int $h) => asRole($s['teacher'], 'oqituvchi')->postJson(route('admin.timetable.cycle-time-proposal.check', $s['board']), $body + ['start_hour' => $h]);
    // 1-kun: ma'ruza 08:30–09:50, amaliy 10:00–13:20 (4 soat).
    expect($check(2)->assertUnprocessable()->json('message'))->toContain('Ma’ruza vaqti bilan ustma-ust')->toContain('08:30–09:50');
    expect($check(3)->assertUnprocessable()->json('message'))->toContain('hozirgisi bilan bir xil');
    $check(7)->assertOk()->assertJsonPath('old', \Illuminate\Support\Carbon::parse($dates[0])->format('d.m.Y') . ' (1-kun), 10:00–13:20');
    // Guruhning shu kuni 13:30 da haftalik darsi bor (boshqa fan) — rad.
    $weekly = TimetableCard::create(['board_id' => $s['board']->id, 'faculty_name' => 'F1', 'specialty_name' => 'davolash ishi', 'course' => 5,
        'oqim_label' => '1-oqim', 'lang' => 'uz', 'students' => 10, 'len_half' => 2, 'subject_name' => 'Tarix', 'training_type' => 'practice',
        'group_name' => 'g5-01a', 'kafedra_name' => 'Tarix', 'teacher_id' => $s['head']->id, 'teacher_name' => 'X', 'pair' => 7, 'day' => 1]);
    [, $day] = tpWeekOf($weekly, $dates[0]);
    $weekly->update(['day' => $day]);
    expect($check(7)->assertUnprocessable()->json('message'))->toContain('Guruh band: g5-01a, Tarix');
    // "Shu kundan blok oxirigacha": 2-kun amaliy 6 soat (1-kun 4 soat) — 2-kunning oxirgi soatlariga tushadigan haftalik dars ham.
    $weekly->delete();
    $late = $weekly->replicate();
    $late->subject_name = 'Fizika';
    $late->pair = 11;
    [, $day2] = tpWeekOf($late, $dates[1]);
    $late->day = $day2;
    $late->save();
    $check(7)->assertOk();
    $rest = asRole($s['teacher'], 'oqituvchi')->postJson(route('admin.timetable.cycle-time-proposal.check', $s['board']), array_merge($body, ['scope' => 'rest', 'start_hour' => 7]));
    expect($rest->assertUnprocessable()->json('message'))->toContain('Guruh band: g5-01a, Fizika');
});

// ─── Fan mas'uli: mas'ul fanidagi istalgan o'qituvchi darsi (sikl) vaqtini o'zgartirishni so'rash ───

function fmTeacher(string $name, string $role): Teacher
{
    Role::findOrCreate($role, 'web');
    $t = new Teacher();
    $t->forceFill(['hemis_id' => random_int(100000, 999999), 'full_name' => $name, 'short_name' => $name, 'first_name' => $name, 'second_name' => $name,
        'employee_id_number' => (string) random_int(100000, 999999), 'birth_date' => '1980-01-01', 'year_of_enter' => 2010,
        'gender' => 'm', 'department' => 'Boshqa', 'employment_form' => 'x', 'employment_staff' => 'x', 'staff_position' => 'x',
        'employee_status' => 'x', 'employee_type' => 'x', 'contract_number' => '1', 'decree_number' => '1',
        'contract_date' => '2020-01-01', 'decree_date' => '2020-01-01',
        'status' => true, 'login' => 'u' . random_int(100000, 999999), 'password' => bcrypt('x')])->save();
    $t->assignRole($role);

    return $t;
}

/** O'qituvchini fanga mas'ul qiladi (5-kurs, 9-semestr: davolash ishi). */
function fmResponsible(Teacher $t, string $subject): int
{
    if (!DB::table('specialties')->where('specialty_hemis_id', 9001)->exists()) {
        DB::table('specialties')->insert(['specialty_hemis_id' => 9001, 'name' => 'Davolash ishi', 'code' => '60910200', 'locality_type_code' => '1',
            'locality_type_name' => 'x', 'education_type_code' => '11', 'education_type_name' => 'B']);
        DB::table('curricula')->insert(['curricula_hemis_id' => 8001, 'name' => 'R', 'specialty_hemis_id' => 9001, 'department_hemis_id' => 1,
            'education_year_code' => '2026', 'education_year_name' => '2026-2027', 'current' => 1, 'education_type_code' => '11', 'education_type_name' => 'B',
            'education_form_code' => '11', 'education_form_name' => 'K', 'marking_system_code' => '11', 'marking_system_name' => 'M',
            'marking_system_minimum_limit' => 60, 'marking_system_gpa_limit' => 2.4, 'semester_count' => 12, 'education_period' => 6]);
    }
    $csId = DB::table('curriculum_subjects')->insertGetId(['curriculum_subject_hemis_id' => random_int(10000, 99999), 'curricula_hemis_id' => 8001,
        'subject_id' => 1, 'subject_name' => $subject, 'subject_code' => 'G1', 'semester_code' => '19', 'semester_name' => '9-semestr', 'at_semester' => 1]);

    return DB::table('teacher_responsible_subjects')->insertGetId(['teacher_id' => $t->id, 'curriculum_subject_id' => $csId]);
}

/** Sikl: g5-01a (o'qituvchi A), g5-01b boshqa o'qituvchida — 13:30 dan bo'sh; 2-kun ertaga. */
function fmSetup(): array
{
    $s = approvalSetup();
    $c = tpCycle($s);
    $c['g5b']->update(['teacher_id' => $s['head']->id, 'teacher_name' => $s['head']->full_name]);
    request()->attributes->remove('timetable.cycle_units.' . $s['board']->id);
    test()->travelTo(\Illuminate\Support\Carbon::parse($c['dates'][0] . ' 10:00', 'Asia/Tashkent'));
    $s['fm'] = fmTeacher("FAN MAS'ULI", 'fan_masuli');
    $s['body'] = ['specialty_name' => 'davolash ishi', 'course' => 5, 'subject_name' => 'Gigiyena', 'group_name' => 'g5-01a',
        'date' => $c['dates'][1], 'scope' => 'day', 'start_hour' => 7];

    return $s + $c;
}

it('fan mas\'uli: o\'z fanidagi boshqa o\'qituvchi darsiga so\'rov — ikki bosqich, jadval o\'zgaradi; dars o\'qituvchisiga yaratilganda va natijada xabar', function () {
    $s = fmSetup();
    fmResponsible($s['fm'], 'Gigiyena');
    $plan = asRole($s['fm'], 'fan_masuli')->postJson(route('admin.timetable.cycle-plan', $s['board']), ['layer' => 'internal', 'courses' => [5]])->assertOk()->json('cycle_cards');
    expect(collect($plan)->firstWhere('group', 'g5-01a')['ask'])->toBeTrue();
    asRole($s['fm'], 'fan_masuli')->postJson(route('admin.timetable.cycle-time-proposal.check', $s['board']), $s['body'])->assertOk();
    asRole($s['fm'], 'fan_masuli')->postJson(route('admin.timetable.cycle-time-proposal', $s['board']), $s['body'] + ['reason' => 'Klinika'])
        ->assertOk()->assertJsonPath('pending_approval', true);
    $req = TimetableChangeRequest::sole();
    expect($req->requester_role)->toBe('fan_masuli')->and($req->requester_id)->toBe($s['fm']->id)
        ->and($req->approval_stage)->toBe('department')
        ->and($req->details['proposer'])->toBe(['as' => 'fan_masuli', 'teacher_id' => $s['fm']->id])
        ->and($req->details['lesson_teacher_ids'])->toBe([$s['teacher']->id])
        ->and($req->details['steps'][0]['action'])->toContain("(fan mas'uli)");
    expect(TeacherNotification::where('teacher_id', $s['teacher']->id)->pluck('title')->all())->toBe(["Fan mas'uli darsingiz vaqtini o'zgartirishni so'radi"]);
    expect(TeacherNotification::where('teacher_id', $s['head']->id)->value('title'))->toBe("Fan mas'uli vaqt o'zgarishini taklif qildi — tasdiq kutilmoqda");
    // Kafedra mudiri ko'radi va tasdiqlay oladi; dars o'qituvchisi ko'radi (tasdiqlamaydi, bekor qilmaydi).
    $row = asRole($s['head'], 'kafedra_mudiri')->getJson(route('admin.timetable.change-requests.index', $s['board']) . '?status=pending')->json('requests.0');
    expect($row['can_review'])->toBeTrue()->and($row['requester_role'])->toBe("Fan mas'uli");
    $row = asRole($s['teacher'], 'oqituvchi')->getJson(route('admin.timetable.change-requests.index', $s['board']) . '?status=pending')->json('requests');
    expect($row)->toHaveCount(1)->and($row[0]['mine'])->toBeFalse()->and($row[0]['can_review'])->toBeFalse();
    asRole($s['teacher'], 'oqituvchi')->postJson(route('admin.timetable.change-requests.cancel', $req))->assertForbidden();

    asRole($s['head'], 'kafedra_mudiri')->postJson(route('admin.timetable.change-requests.approve', $req))->assertOk();
    expect($s['placement']->fresh()->lecture_slots)->not->toHaveKey('ps_g');
    asRole($s['reg'], 'registrator_ofisi')->postJson(route('admin.timetable.change-requests.approve', $req))->assertOk();
    expect($req->fresh()->status)->toBe('approved')->and($s['placement']->fresh()->lecture_slots['ps_g'])->toBe(['g5-01a' => [1 => 7]]);
    expect(TeacherNotification::where('teacher_id', $s['teacher']->id)->pluck('title')->all())
        ->toBe(["Fan mas'uli darsingiz vaqtini o'zgartirishni so'radi", "Dars jadvali o'zgardi"]);
    expect(StudentNotification::where('student_id', DB::table('students')->where('hemis_id', 9101)->value('id'))->sole()->message)->toContain('Fan: Gigiyena');
});

it('fan mas\'uli: boshqa fanga — 403; oddiy o\'qituvchi boshqaning darsiga — 403; tugma ham chiqmaydi', function () {
    $s = fmSetup();
    fmResponsible($s['fm'], 'Anatomiya');
    $other = fmTeacher('OQITUVCHI B.', 'oqituvchi');
    foreach ([[$s['fm'], 'fan_masuli'], [$other, 'oqituvchi']] as [$who, $role]) {
        $plan = asRole($who, $role)->postJson(route('admin.timetable.cycle-plan', $s['board']), ['layer' => 'internal', 'courses' => [5]])->assertOk()->json('cycle_cards');
        expect(collect($plan)->firstWhere('group', 'g5-01a')['ask'])->toBeFalse();
        asRole($who, $role)->postJson(route('admin.timetable.cycle-time-proposal.check', $s['board']), $s['body'])->assertForbidden();
        asRole($who, $role)->postJson(route('admin.timetable.cycle-time-proposal', $s['board']), $s['body'] + ['reason' => 'x'])->assertForbidden();
    }
    // O'qituvchining o'z darsi — tugma bor.
    $plan = asRole($s['teacher'], 'oqituvchi')->postJson(route('admin.timetable.cycle-plan', $s['board']), ['layer' => 'internal', 'courses' => [5]])->assertOk()->json('cycle_cards');
    expect(collect($plan)->firstWhere('group', 'g5-01a')['ask'])->toBeTrue();
    expect(TimetableChangeRequest::count())->toBe(0);
});

it('fan mas\'uli: bugungi yoki o\'tgan darsga so\'rov — 422', function () {
    $s = fmSetup();
    fmResponsible($s['fm'], 'Gigiyena');
    asRole($s['fm'], 'fan_masuli')->postJson(route('admin.timetable.cycle-time-proposal', $s['board']), array_merge($s['body'], ['date' => $s['dates'][0], 'start_hour' => 3, 'reason' => 'x']))
        ->assertUnprocessable()->assertJsonPath('message', fn ($m) => str_contains($m, "Bugungi yoki o'tgan"));
    test()->travelTo(\Illuminate\Support\Carbon::parse($s['dates'][1] . ' 18:00', 'Asia/Tashkent')->addDay());
    asRole($s['fm'], 'fan_masuli')->postJson(route('admin.timetable.cycle-time-proposal.check', $s['board']), $s['body'])
        ->assertUnprocessable()->assertJsonPath('message', fn ($m) => str_contains($m, "Bugungi yoki o'tgan"));
    expect(TimetableChangeRequest::count())->toBe(0);
});

it('fan mas\'uli: mas\'ulligi olib tashlansa — tasdiqda qo\'llanmaydi; rad etish va bekor qilish dars o\'qituvchisiga xabar beradi', function () {
    $s = fmSetup();
    $link = fmResponsible($s['fm'], 'Gigiyena');
    $send = fn () => asRole($s['fm'], 'fan_masuli')->postJson(route('admin.timetable.cycle-time-proposal', $s['board']), $s['body'] + ['reason' => 'x'])->assertOk()->json('change_request_id');
    // Rad etish.
    $id = $send();
    asRole($s['head'], 'kafedra_mudiri')->postJson(route('admin.timetable.change-requests.reject', $id), ['note' => 'Kerak emas'])->assertOk();
    expect(TeacherNotification::where('teacher_id', $s['teacher']->id)->latest('id')->first())
        ->title->toBe("Darsingiz vaqtini o'zgartirish so'rovi rad etildi (kafedra mudiri)")->message->toContain('Izoh: Kerak emas');
    // Bekor qilish.
    $id = $send();
    asRole($s['fm'], 'fan_masuli')->postJson(route('admin.timetable.change-requests.cancel', $id))->assertOk();
    expect(TeacherNotification::where('teacher_id', $s['teacher']->id)->latest('id')->value('title'))->toBe("Darsingiz vaqtini o'zgartirish so'rovi bekor qilindi");
    // Mas'ullik olib tashlandi — kafedra mudiri bosqichida qo'llanmaydi.
    $id = $send();
    DB::table('teacher_responsible_subjects')->where('id', $link)->delete();
    asRole($s['head'], 'kafedra_mudiri')->postJson(route('admin.timetable.change-requests.approve', $id))->assertUnprocessable();
    expect(TimetableChangeRequest::find($id))->status->toBe('failed')->error->toContain("Dars biriktiruvi o'zgargan");
    expect(TeacherNotification::where('teacher_id', $s['teacher']->id)->latest('id')->value('title'))->toBe("Darsingiz vaqtini o'zgartirish so'rovi qo'llanmadi");
    expect($s['placement']->fresh()->lecture_slots)->not->toHaveKey('ps_g');
});

it('sikl kuni ↑/↓ bilan surilsa, tasdiqlangan amaliy boshlanishi (ps_g) ham shu kun bilan suriladi', function () {
    $s = approvalSetup(false);
    ['placement' => $pl, 'dates' => $dates] = tpCycle($s);
    $pl->update(['lecture_slots' => ['shift_g' => ['g5-01b' => 7], 'ps_g' => ['g5-01a' => [0 => 3, 1 => 5], 'g5-01b' => [1 => 3]]]]);
    $move = fn (array $extra) => asRole($s['head'], 'kafedra_mudiri')->postJson(route('admin.timetable.cycle-lecture-slots', $s['board']), [
        'specialty_name' => 'davolash ishi', 'course' => 5, 'subject_name' => 'Gigiyena', 'group_name' => 'F1 / 1-oqim', 'training_type' => 'practice',
        'view' => 'group', 'day_index' => 1] + $extra)->assertOk();
    $prac = function () use ($s, $dates) {
        request()->attributes->remove('timetable.cycle_units.' . $s['board']->id);
        return app(\App\Http\Controllers\Admin\TimetableController::class)->cycleProposalUnits($s['board'],
            ['specialty_name' => 'davolash ishi', 'course' => 5, 'subject_name' => 'Gigiyena', 'group_name' => 'g5-01a'], null)[0]['prac'][$dates[1]];
    };
    expect($prac())->toBe([5, 11]);
    // Guruhcha kuni 1 → 2: faqat shu guruhchaning amaliysi 5 → 6.
    $move(['sub_group' => 'g5-01a', 'start_hour' => 2]);
    expect($pl->fresh()->lecture_slots['ps_g'])->toBe(['g5-01a' => [0 => 3, 1 => 6], 'g5-01b' => [1 => 3]])->and($prac())->toBe([6, 12]);
    // Oqim kuni 1 → 2: o'zi surmagan guruhcha (g5-01b) 3 → 4; g5-01a (o'z kuni bor) o'zgarmaydi.
    $move(['start_hour' => 2]);
    expect($pl->fresh()->lecture_slots['ps_g'])->toBe(['g5-01a' => [0 => 3, 1 => 6], 'g5-01b' => [1 => 4]]);
    // Guruhcha 2 → 1 (oqim 2-soatda — o'z qiymati saqlanadi): 6 → 5.
    $move(['sub_group' => 'g5-01a', 'start_hour' => 1]);
    expect($pl->fresh()->lecture_slots['ps_g']['g5-01a'])->toBe([0 => 3, 1 => 5])->and($pl->fresh()->lecture_slots['shift_g'])->toBe(['g5-01b' => 7]);
});

it('sikl kun rejasi: amaliy o\'z soatida bo\'lsa kun boshini faqat ma\'ruza cheklaydi (↓ bilan ma\'ruza ham suriladi)', function () {
    $ctl = app(\App\Http\Controllers\Admin\TimetableController::class);
    $plan = fn (int $start, array $ps) => (new ReflectionMethod($ctl, 'cycleDayPlan'))->invoke($ctl,
        [['lec' => 2, 'prc' => 4, 'tl' => '', 'tp' => '']], null, 1, 0, [0], [0 => $start], 6, 12, $ps)[0];
    // Ma'ruza 7–8, amaliy 1–4 (tasdiqlangan): kun 8 ga surilsa — ma'ruza 8–9 (avval 7 da qisilib qolardi), amaliy 2.
    expect($plan(8, [0 => 2]))->toMatchArray(['start' => 8, 'ps' => 2, 'lec' => 2, 'prc' => 4]);
    expect($plan(11, [0 => 2]))->toMatchArray(['start' => 11, 'ps' => 2]);
    expect($plan(12, [0 => 2]))->toMatchArray(['start' => 11]);
    // Amaliy ma'ruzadan keyin (o'z soati yo'q) — ikkalasi kun ichida sig'ishi kerak: 12 - 6 + 1 = 7.
    expect($plan(8, []))->toMatchArray(['start' => 7, 'ps' => 9]);
});
