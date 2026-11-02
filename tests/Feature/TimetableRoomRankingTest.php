<?php

use App\Models\Auditorium;
use App\Models\Teacher;
use App\Models\TimetableBoard;
use App\Models\TimetableCard;
use App\Models\TimetableGridSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;

/**
 * Bo'sh xonalar reytingi (TimetableRoomRanking) — biriktirish oynasi (unit-busy → room_rank) orqali: odatning ikki darajasi
 * (shu guruh / shu fanning boshqa guruhlari), nom prefiksi bo'yicha bino, faolsiz hozirgi xona, xona turi mosligi, kafedra maydoni.
 */
function rrSetup(): array
{
    Role::findOrCreate('admin', 'web');
    $admin = new Teacher();
    $admin->forceFill([
        'hemis_id' => random_int(100000, 999999), 'full_name' => 'ADMIN', 'short_name' => 'ADMIN', 'first_name' => 'A', 'second_name' => 'A',
        'employee_id_number' => (string) random_int(100000, 999999), 'birth_date' => '1980-01-01', 'year_of_enter' => 2010,
        'gender' => 'm', 'department' => 'Boshqa', 'employment_form' => 'x', 'employment_staff' => 'x', 'staff_position' => 'x',
        'employee_status' => 'x', 'employee_type' => 'x', 'contract_number' => '1', 'decree_number' => '1',
        'contract_date' => '2020-01-01', 'decree_date' => '2020-01-01', 'status' => true, 'login' => 'u' . random_int(100000, 999999), 'password' => bcrypt('x'),
    ])->save();
    $admin->assignRole('admin');
    $board = TimetableBoard::create(['name' => 'T', 'academic_year' => '2026-2027', 'semester_parity' => 'kuzgi', 'days' => 6,
        'pairs_per_day' => 12, 'weeks' => 15, 'settings' => ['semester_start' => '2026-09-07']]);
    $card = function (array $x) use ($board) {
        $fac = ($x['specialty_name'] ?? 'Pediatriya') === 'Pediatriya' ? 'Pediatriya fakulteti' : '1-son davolash ishi fakulteti';
        TimetableGridSetting::firstOrCreate(['board_id' => $board->id, 'faculty_name' => $fac, 'specialty_name' => $x['specialty_name'] ?? 'Pediatriya',
            'course' => $x['course'] ?? 5], ['days' => 6, 'pairs_per_day' => 12, 'weeks' => 15]);

        return TimetableCard::create(array_merge(['board_id' => $board->id, 'faculty_name' => $fac, 'specialty_name' => 'Pediatriya', 'course' => 5,
            'oqim_label' => '1-oqim', 'lang' => 'uz', 'students' => 12, 'training_type' => 'practice', 'len_half' => 2, 'weeks' => 15,
            'subject_name' => 'Klinik farmakologiya', 'kafedra_name' => 'Farmakologiya kafedrasi'], $x));
    };
    $room = fn (string $code, string $name, int $vol = 30, array $x = []) => Auditorium::create(['code' => $code, 'name' => $name, 'volume' => $vol,
        'active' => true] + $x);
    $rank = function (TimetableCard $unit) use ($admin, $board) {
        $params = ['faculty_name' => $unit->faculty_name, 'specialty_name' => $unit->specialty_name, 'course' => $unit->course,
            'subject_name' => $unit->subject_name, 'training_type' => $unit->training_type]
            + ($unit->training_type === 'lecture' ? ['oqim_label' => $unit->oqim_label] : ['group_name' => $unit->group_name]);

        return test()->withoutVite()->actingAs($admin, 'teacher')->withSession(['active_role' => 'admin'])
            ->getJson(route('admin.timetable.unit-busy', ['board' => $board->id] + $params))->assertOk()->json('room_rank');
    };

    return compact('admin', 'board', 'card', 'room', 'rank');
}

it('reyting: shu fanning boshqa guruhlari xonalari ("shu fan xonasi", chastota bo\'yicha) — p/p22-05c Klinik farmakologiya', function () {
    $s = rrSetup();
    foreach (['437', '249', '440', '439', '430'] as $n) {
        ($s['room'])('K' . $n, 'Tibbiyot klasteri, ' . $n, 30, ['building_id' => 'VKTTM', 'building_name' => 'VKTTM']);
    }
    ($s['room'])('A100', 'A-100', 30);                          // alifbo bo'yicha birinchi
    ($s['room'])('B101', '2-bino, 101', 30, ['building_id' => 'VKTTM']);
    ($s['room'])('Z900', "Ma'ruza zali", 150, ['auditorium_type_name' => "Ma'ruza"]);
    // Klinik farmakologiya (Pediatriya, 5-kurs, amaliy) boshqa guruhlari: 437 — 3 guruh, 249 — 2, qolganlari — 1 tadan.
    foreach ([['p/p22-01a', 'K437'], ['p/p22-01b', 'K437'], ['p/p22-02a', 'K437'], ['p/p22-02b', 'K249'], ['p/p22-03a', 'K249'],
        ['p/p22-03b', 'K440'], ['p/p22-04a', 'K439'], ['p/p22-04b', 'K430']] as [$g, $code]) {
        ($s['card'])(['group_name' => $g, 'auditorium_code' => $code, 'auditorium_name' => $code]);
    }
    // Boshqa fan yoki boshqa tur (ma'ruza) yoki boshqa kurs xonasi — "shu fan xonasi" emas.
    ($s['card'])(['group_name' => 'p/p22-01a', 'subject_name' => 'Pediatriya', 'auditorium_code' => 'B101']);
    ($s['card'])(['group_name' => 'p/p22-01a', 'training_type' => 'lecture', 'group_names' => ['p/p22-01a'], 'auditorium_code' => 'A100']);
    ($s['card'])(['group_name' => 'p/p23-01a', 'course' => 4, 'auditorium_code' => 'Z900']);
    $unit = ($s['card'])(['group_name' => 'p/p22-05c']);

    $r = ($s['rank'])($unit);
    expect(array_slice($r['order'], 0, 5))->toBe(['K437', 'K249', 'K430', 'K439', 'K440'])
        ->and($r['why'])->toBe(['K437' => 'shu fan xonasi', 'K249' => 'shu fan xonasi', 'K430' => 'shu fan xonasi', 'K439' => 'shu fan xonasi',
            'K440' => 'shu fan xonasi']);

    // Shu guruhning o'z odati (boshqa darsi 440 da) — "shu fan xonasi"dan ham yuqori.
    ($s['card'])(['group_name' => 'p/p22-05c', 'day' => 3, 'pair' => 1, 'auditorium_code' => 'K440']);
    $r = ($s['rank'])($unit);
    expect(array_slice($r['order'], 0, 3))->toBe(['K440', 'K437', 'K249'])->and($r['why']['K440'])->toBe('shu fan odatda shu yerda');
});

it('reyting: "shu fan xonasi" o\'qituvchiga biriktirilgan va kafedra xonasidan keyin, shu binodan oldin', function () {
    $s = rrSetup();
    $t = Teacher::query()->first();
    ($s['room'])('K437', 'Morfologiya, 437', 30, ['building_id' => 'MORF']);              // boshqa binoda — faqat "shu fan xonasi"
    ($s['room'])('K401', 'Tibbiyot klasteri, 401', 30, ['building_id' => 'VKTTM']);   // hozirgi xona binosida
    ($s['room'])('T1', 'T-1', 30);                                                    // o'qituvchiga biriktirilgan
    ($s['room'])('F1', 'F-1', 30, ['department_name' => 'Farmakologiya kafedrasi']);   // kafedra xonasi
    ($s['room'])('CUR', 'Tibbiyot klasteri, 410', 30, ['building_id' => 'VKTTM']);
    \App\Models\AuditoriumTeacher::create(['auditorium_id' => Auditorium::where('code', 'T1')->value('id'), 'teacher_id' => $t->id, 'is_general' => false]);
    ($s['card'])(['group_name' => 'p/p22-01a', 'auditorium_code' => 'K437']);
    $unit = ($s['card'])(['group_name' => 'p/p22-05c', 'auditorium_code' => 'CUR', 'teacher_id' => $t->id, 'teacher_name' => 'ADMIN']);

    $r = ($s['rank'])($unit);
    expect($r['order'])->toBe(['CUR', 'T1', 'F1', 'K437', 'K401'])
        ->and($r['why'])->toBe(['CUR' => 'shu fan odatda shu yerda', 'T1' => "o'qituvchiga biriktirilgan", 'F1' => 'kafedra xonasi',
            'K437' => 'shu fan xonasi', 'K401' => 'shu bino']);
});

it('reyting: bino — nom prefiksi (blok/bo\'lim) bir xili avval; hozirgi xona faolsiz bo\'lsa building_id bo\'yicha keng tavsiya yo\'q', function () {
    $s = rrSetup();
    $vk = ['building_id' => 'VKTTM', 'building_name' => 'VKTTM'];
    ($s['room'])('CUR', 'Tibbiyot klasteri, 437', 30, $vk);
    ($s['room'])('T512', 'Tibbiyot klasteri, 512', 30, $vk);
    ($s['room'])('T401', 'Tibbiyot klasteri, 401', 30, $vk);
    ($s['room'])('B415', '2-bino, 415', 30, $vk);   // qavati 437 bilan bir xil, lekin boshqa blok — prefiksdoshlardan keyin
    ($s['room'])('LOR', "LOR bo'limi, 12", 30, $vk);
    ($s['room'])('X100', 'Boshqa, 100', 30, ['building_id' => 'B2']);
    $unit = ($s['card'])(['group_name' => 'p/p22-05c', 'subject_name' => 'Pediatriya', 'kafedra_name' => 'Pediatriya kafedrasi',
        'auditorium_code' => 'CUR']);

    // Faol hozirgi xona: avval shu blok ("Tibbiyot klasteri" — qavati yaqini oldin: 401, 512), so'ng shu building_id (2-bino, LOR), oxirida boshqa bino.
    $r = ($s['rank'])($unit);
    expect($r['order'])->toBe(['CUR', 'T401', 'T512', 'B415', 'LOR', 'X100'])
        ->and($r['why'])->toBe(['CUR' => 'shu fan odatda shu yerda', 'T401' => 'shu bino', 'T512' => 'shu bino', 'B415' => 'shu bino', 'LOR' => 'shu bino']);

    // Hozirgi xona faolsiz: faqat nom prefiksi bo'yicha (shu blok); VKTTM ning boshqa bloklari — "shu bino" emas (keng tavsiya yo'q).
    Auditorium::where('code', 'CUR')->update(['active' => false]);
    $r = ($s['rank'])($unit);
    expect(array_slice($r['order'], 0, 2))->toBe(['T401', 'T512'])
        ->and($r['why'])->toBe(['T401' => 'shu bino', 'T512' => 'shu bino'])
        ->and($r['order'])->not->toContain('CUR');
});

it('reyting: xona turi — amaliyotga amaliyot xonasi ma\'ruza zalidan oldin (d1/d22-05a), ma\'ruzaga — aksincha; turi noma\'lum — betaraf', function () {
    $s = rrSetup();
    ($s['room'])('LZ', "Ma'ruza zali 2", 14, ['auditorium_type_name' => "Ma’ruza"]);   // boshqa apostrof ham
    ($s['room'])('AX', 'Amaliyot xonasi 3', 30, ['auditorium_type_name' => "Amaliy mashg'ulot"]);
    ($s['room'])('AX2', 'Amaliyot xonasi 1', 12, ['auditorium_type_name' => "Amaliy mashg'ulot"]);
    ($s['room'])('UNK', 'Xona 7', 13);
    $d = ['specialty_name' => 'Davolash ishi', 'course' => 3, 'subject_name' => 'Patologik anatomiya', 'kafedra_name' => 'Patologik anatomiya kafedrasi'];
    $practice = ($s['card'])($d + ['group_name' => 'd1/d22-05a']);
    // Sig'im bo'yicha 14 o'rinli ma'ruza zali 30 o'rinli amaliyot xonasidan yaqin — lekin amaliy darsga ma'ruza zali shu daraja ichida oxirida.
    expect(($s['rank'])($practice)['order'])->toBe(['AX2', 'UNK', 'AX', 'LZ']);

    $lecture = ($s['card'])($d + ['group_name' => null, 'training_type' => 'lecture', 'oqim_label' => '5-oqim', 'group_names' => ['d1/d22-05a']]);
    // Ma'ruzaga: ma'ruza zali (va turi noma'lum xona) avval, amaliyot xonalari — oxirida (12 o'rinli aynan sig'sa ham).
    expect(($s['rank'])($lecture)['order'])->toBe(['UNK', 'LZ', 'AX2', 'AX']);

    // Har daraja ichida: yuqori darajadagi ma'ruza zali (kafedra xonasi) pastki darajadagi amaliyot xonasidan baribir oldin.
    Auditorium::where('code', 'LZ')->update(['department_name' => 'Patologik anatomiya kafedrasi']);
    expect(($s['rank'])($practice)['order'])->toBe(['LZ', 'AX2', 'UNK', 'AX']);
});

it('reyting: kafedra maydoni — HEMIS sinxronizatsiyasi (import:auditoriums) uni to\'ldirmaydi va o\'chirmaydi; maydoni bor xona birinchi, bo\'sh bo\'lsa sababsiz', function () {
    $s = rrSetup();
    // HEMIS xonalari (kafedrasiz) va kafedra mudiri LMS'da qo'shgan xona (kafedra maydoni bilan).
    ($s['room'])('H101', '101', 30);
    ($s['room'])('H102', '102', 30);
    ($s['room'])('KF1', 'Farmakologiya xonasi', 60, ['department_hemis_id' => 777, 'department_name' => 'Farmakologiya kafedrasi', 'created_by_teacher_id' => 1]);
    DB::table('departments')->insert(['department_hemis_id' => 777, 'name' => 'Farmakologiya kafedrasi', 'code' => 'FK', 'structure_type_code' => 12,
        'structure_type_name' => 'Kafedra', 'locality_type_code' => 11, 'locality_type_name' => 'M', 'active' => 1, 'created_at' => now(), 'updated_at' => now()]);
    // HEMIS ro'yxatida KF1 ham bor (masalan, keyin HEMIS'ga ham kiritilgan) — kafedra maydoni HEMIS javobida yo'q.
    $item = fn ($code, $name) => ['code' => $code, 'name' => $name, 'volume' => 40, 'active' => true, 'building' => ['id' => 'VKTTM', 'name' => 'VKTTM'],
        'auditoriumType' => ['code' => '12', 'name' => "Amaliy mashg'ulot"]];
    Http::fake(['*auditorium-list*' => Http::response(['success' => true, 'data' => ['items' => [$item('H101', '101'), $item('KF1', 'Farmakologiya xonasi')],
        'pagination' => ['pageCount' => 1]]]), '*' => Http::response(['ok' => true])]);
    test()->artisan('import:auditoriums')->assertSuccessful();
    expect(Auditorium::where('code', 'KF1')->first()->only(['department_hemis_id', 'department_name', 'volume']))
        ->toBe(['department_hemis_id' => 777, 'department_name' => 'Farmakologiya kafedrasi', 'volume' => 40])
        ->and(Auditorium::where('code', 'H101')->value('department_hemis_id'))->toBeNull();

    $unit = ($s['card'])(['group_name' => 'p/p22-05c']);
    $r = ($s['rank'])($unit);
    expect($r['order'][0])->toBe('KF1')->and($r['why'])->toBe(['KF1' => 'kafedra xonasi']);

    // Kafedra maydoni hech bir xonada yo'q — mezon sababsiz o'tkaziladi (sig'im bo'yicha, "kafedra xonasi" yorlig'isiz).
    Auditorium::where('code', 'KF1')->update(['department_hemis_id' => null, 'department_name' => null]);
    $r = ($s['rank'])($unit);
    expect($r['why'])->toBe([])->and($r['order'])->toBe(['H102', 'H101', 'KF1']);
});
