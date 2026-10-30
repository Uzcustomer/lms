<?php

use App\Models\Teacher;
use App\Models\TimetableBoard;
use App\Models\TimetableCard;
use App\Models\TimetableCardOverride;
use Spatie\Permission\Models\Role;

function urTeacher(string $name, string $role): Teacher
{
    Role::findOrCreate($role, 'web');
    $t = new Teacher();
    $t->forceFill([
        'hemis_id' => random_int(100000, 999999), 'full_name' => $name, 'short_name' => $name, 'first_name' => $name, 'second_name' => $name,
        'employee_id_number' => (string) random_int(100000, 999999), 'birth_date' => '1980-01-01', 'year_of_enter' => 2010,
        'gender' => 'm', 'department' => 'x', 'employment_form' => 'x', 'employment_staff' => 'x', 'staff_position' => 'x',
        'employee_status' => 'x', 'employee_type' => 'x', 'contract_number' => '1', 'decree_number' => '1',
        'contract_date' => '2020-01-01', 'decree_date' => '2020-01-01', 'status' => true, 'login' => 'u' . random_int(100000, 999999), 'password' => bcrypt('x'),
    ])->save();
    $t->assignRole($role);

    return $t;
}

// Yuklab olingan XLSX: varaq nomi => qatorlar (A1 dan, qiymatlar).
function urSheets($response): array
{
    $file = tempnam(sys_get_temp_dir(), 'ur') . '.xlsx';
    file_put_contents($file, $response->baseResponse->getFile()->getContent());
    $book = \PhpOffice\PhpSpreadsheet\IOFactory::load($file);
    @unlink($file);
    $out = [];
    foreach ($book->getAllSheets() as $sh) {
        $out[$sh->getTitle()] = $sh->toArray(null, false, false, false);
    }
    $sum = $book->getSheetByName('Yig\'ma');
    $det = $book->getSheetByName('Batafsil (haftalik)');
    $out['_style'] = ['fill_D2' => $sum->getStyle('D2')->getFill()->getStartColor()->getRGB(),
        'bold' => [$sum->getStyle('A2')->getFont()->getBold(), $sum->getStyle('A3')->getFont()->getBold(), $sum->getStyle('H4')->getFont()->getBold()],
        'filter' => $det->getAutoFilter()->getRange(), 'freeze' => $det->getFreezePane()];

    return $out;
}

beforeEach(function () {
    $this->board = TimetableBoard::create(['name' => 'T', 'academic_year' => '2026-2027', 'semester_parity' => 'kuzgi', 'days' => 6,
        'pairs_per_day' => 12, 'weeks' => 4, 'settings' => ['semester_start' => '2026-09-14']]);
    $base = ['board_id' => $this->board->id, 'oqim_label' => '1-oqim', 'lang' => 'uz', 'students' => 10, 'training_type' => 'practice',
        'len_half' => 2, 'weeks' => 4, 'kafedra_name' => 'K'];
    // F1: Anatomiya — shablonda joyi yo'q; 3-hafta bekor, 4-hafta ko'chirilgan → 1- va 2-hafta joylanmagan
    $this->a = TimetableCard::create($base + ['faculty_name' => 'F1', 'specialty_name' => 'davolash ishi', 'course' => 1,
        'subject_name' => 'Anatomiya', 'group_name' => 'd-01']);
    TimetableCardOverride::create(['card_id' => $this->a->id, 'week' => 3, 'cancelled' => true]);
    TimetableCardOverride::create(['card_id' => $this->a->id, 'week' => 4, 'day' => 2, 'pair' => 3, 'start_half' => 0, 'cancelled' => false]);
    // F2: Fiziologiya — shablonda joyi yo'q, lekin har haftaga ko'chirib qo'yilgan → joylangan
    $b = TimetableCard::create($base + ['faculty_name' => 'F2', 'specialty_name' => 'pediatriya ishi', 'course' => 2,
        'subject_name' => 'Fiziologiya', 'group_name' => 'p-01']);
    foreach ([1, 2, 3, 4] as $w) {
        TimetableCardOverride::create(['card_id' => $b->id, 'week' => $w, 'day' => 3, 'pair' => 1, 'start_half' => 0, 'cancelled' => false]);
    }
    // F2: Pediatriya — sikl fani (2 kun), ichki sikl jadvalida joylanmagan
    \App\Models\TimetableSubjectSetting::create(['board_id' => $this->board->id, 'specialty_name' => 'pediatriya ishi', 'course' => 5,
        'subject_name' => 'Pediatriya', 'mode' => 'cycle', 'cycle_days' => 2]);
    TimetableCard::create($base + ['faculty_name' => 'F2', 'specialty_name' => 'pediatriya ishi', 'course' => 5,
        'subject_name' => 'Pediatriya', 'group_name' => 'p5-01a', 'weeks' => 15]);
    $this->report = fn (string $role, array $query = []) => test()->withoutVite()->actingAs(urTeacher('U' . $role, $role), 'teacher')
        ->withSession(['active_role' => $role])
        ->get(route('admin.timetable.boards.unplaced-report', ['board' => $this->board->id] + $query));
});

it('butun doska: yig\'ma, batafsil (sikl fanisiz), sikl va fanlar varaqlari; tanlangan fakultet hisobga olinmaydi', function () {
    // Ekranda F2 tanlangan bo'lsa ham — butun doska
    $resp = ($this->report)('registrator_ofisi', ['faculty_names' => ['F2'], 'layer' => 'internal'])->assertOk();
    expect($resp->headers->get('content-disposition'))->toContain('joylanmagan_kartalar_butun_doska_' . now()->format('Y-m-d') . '.xlsx');
    $x = urSheets($resp);

    expect(array_keys($x))->toBe(['Yig\'ma', 'Batafsil (haftalik)', 'Sikl (joylanmagan)', 'Fanlar bo\'yicha', '_style'])
        // Qiymatli katak rangli; fakultet jamisi va umumiy jami — qalin; batafsilda avtofiltr va muzlatilgan sarlavha
        ->and($x['_style'])->toMatchArray(['bold' => [false, true, true], 'filter' => 'A1:M3', 'freeze' => 'A2'])
        ->and($x['_style']['fill_D2'])->not->toBe('FFFFFF')
        // Yig'ma: F1 / davolash ishi / 1-kurs — 1- va 2-haftada bittadan; 0 — bo'sh; fakultet jamisi va umumiy jami
        ->and($x['Yig\'ma'])->toBe([
            ['Fakultet', 'Yo\'nalish', 'Kurs', '1-hafta', '2-hafta', '3-hafta', '4-hafta', 'Jami'],
            ['F1', 'davolash ishi', 1, 1, 1, null, null, 2],
            ['F1 — jami', null, null, 1, 1, null, null, 2],
            ['Jami', null, null, 1, 1, null, null, 2],
        ])
        // Batafsil: faqat haftalik karta-haftalar — sikl fani (Pediatriya) yo'q
        ->and(array_map(fn ($r) => [$r[0], $r[5], $r[6], $r[7], $r[8]], array_slice($x['Batafsil (haftalik)'], 1)))
        ->toBe([['F1', 'Anatomiya', 'Amaliy', 1, 2], ['F1', 'Anatomiya', 'Amaliy', 2, 2]])
        // Sikl varag'ida — joylanmagan sikl kartasi
        ->and(array_map(fn ($r) => [$r[0], $r[1], $r[2], $r[4], $r[5]], array_slice($x['Sikl (joylanmagan)'], 1)))
        ->toBe([['F2', 'pediatriya ishi', 5, 'Pediatriya', 2]])
        ->and(array_slice($x['Fanlar bo\'yicha'], 1))->toBe([['F1', 'davolash ishi', 1, 'Anatomiya', 2, '1–2']]);
});

it('hafta oralig\'i, tashqi qatlam va ruxsat', function () {
    // 2-haftadan: faqat 2-hafta
    $x = urSheets(($this->report)('oquv_bolimi', ['from_week' => 2])->assertOk());
    expect(array_column(array_slice($x['Batafsil (haftalik)'], 1), 7))->toBe([2]);
    // Tashqi qatlam: haftaga ko'chirishlar hisobga olinmaydi (faqat bekor qilingan hafta) — Fiziologiya ham joylanmagan
    $this->a->update(['ext_day' => 1, 'ext_pair' => 1]);
    $x = urSheets(($this->report)('admin', ['layer' => 'external'])->assertOk());
    expect(array_map(fn ($r) => $r[5] . ' ' . $r[7], array_slice($x['Batafsil (haftalik)'], 1)))
        ->toBe(['Fiziologiya 1', 'Fiziologiya 2', 'Fiziologiya 3', 'Fiziologiya 4']);
    // Hisobotga ruxsati yo'q rollar
    ($this->report)('oqituvchi')->assertForbidden();
    ($this->report)('kafedra_mudiri')->assertForbidden();
    ($this->report)('kichik_admin')->assertForbidden();
});
