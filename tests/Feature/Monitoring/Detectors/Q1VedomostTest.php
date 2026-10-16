<?php

require_once __DIR__ . '/../helpers.php';

use App\Models\MonitoringIstisno;
use App\Services\Monitoring\DetectorRunner;
use Illuminate\Support\Facades\DB;

/*
 * Q-1, vedomost rejimi (standart): YN (yoki oxirgi dars) sanasi + 3 ish kuni ichida vedomost mark'ka yuklanganmi.
 */

beforeEach(function () {
    // Juma 2026-10-16 08:00
    $this->travelTo(now()->setDate(2026, 10, 16)->setTime(8, 0));
    $this->head = monTeacher(['kafedra_mudiri']);
    $this->group = monGroup();
    $this->detector = monDetector('Q-1');
});

function q1Vedomost(object $group, array $attrs = []): int
{
    static $subject = 7000;
    $subject++;

    return DB::table('vedomost_submissions')->insertGetId(array_merge([
        'education_year' => '2026', 'semester_code' => '13', 'group_hemis_id' => $group->group_hemis_id,
        'group_name' => 'Sinov guruhi', 'subject_id' => $subject, 'subject_name' => 'Sinov fani ' . $subject,
        'department_hemis_id' => 61001, 'form_type' => '12', 'base_type' => 'exam',
        'base_date' => '2026-10-07', 'deadline' => '2026-10-10', 'status' => 'pending',
        'created_at' => now(), 'updated_at' => now(),
    ], $attrs));
}

test('muddat o\'tgan, vedomost yuklanmagan — istisno vedomostdagi kafedra mudiriga', function () {
    $id = q1Vedomost($this->group, ['kafedra_mudiri_hemis_id' => $this->head->hemis_id, 'kafedra_mudiri_name' => $this->head->full_name]);

    app(DetectorRunner::class)->run($this->detector);

    $istisno = MonitoringIstisno::sole();
    expect($istisno->fingerprint)->toBe("Q-1:v:{$id}")
        ->and($istisno->details['yuklangan'])->toBe('yuklanmagan')
        ->and($istisno->details['nazorat_sanasi'])->toBe('2026-10-07')
        ->and($istisno->isOwnedBy($this->head))->toBeTrue()
        ->and($istisno->faculty_hemis_id)->toBe((int) $this->group->department_hemis_id);
});

test('o\'z vaqtida yuklangan, muddati hali o\'tmagan yoki davrdan tashqari — istisno yo\'q', function () {
    q1Vedomost($this->group, ['uploaded_at' => '2026-10-09 15:00:00', 'status' => 'approved']);
    q1Vedomost($this->group, ['base_date' => '2026-10-14', 'deadline' => '2026-10-17']);
    q1Vedomost($this->group, ['base_date' => '2026-07-01', 'deadline' => '2026-07-04']);

    app(DetectorRunner::class)->run($this->detector);

    expect(MonitoringIstisno::count())->toBe(0);
});

test('kechikib yuklansa: auto_close — yopiladi, keep_open — ochiq qoladi', function () {
    $id = q1Vedomost($this->group);
    app(DetectorRunner::class)->run($this->detector);
    expect(MonitoringIstisno::sole()->status)->toBe('ochiq');

    DB::table('vedomost_submissions')->where('id', $id)->update(['uploaded_at' => '2026-10-14 10:00:00', 'status' => 'received']);
    app(DetectorRunner::class)->run($this->detector->fresh());
    expect(MonitoringIstisno::sole()->status)->toBe('yopildi');

    app(DetectorRunner::class)->run(monDetector('Q-1', ['late_completion' => 'keep_open']));
    expect(MonitoringIstisno::sole()->status)->toBe('qayta_ochildi');
});

test('HEMIS rejimidan qolgan istisnolar vedomost rejimiga o\'tganda yopiladi', function () {
    DB::table('exam_schedules')->insert([
        'department_hemis_id' => (string) $this->group->department_hemis_id, 'specialty_hemis_id' => '1',
        'curriculum_hemis_id' => (string) $this->group->curriculum_hemis_id, 'semester_code' => '13',
        'group_hemis_id' => (string) $this->group->group_hemis_id, 'subject_id' => '3001', 'subject_name' => 'Sinov fani',
        'oski_date' => '2026-10-05', 'oski_na' => 0, 'test_na' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
    monStudent(['group_id' => $this->group->group_hemis_id]);
    app(DetectorRunner::class)->run(monDetector('Q-1', ['check_against' => 'hemis']));
    expect(MonitoringIstisno::sole()->status)->toBe('ochiq');

    app(DetectorRunner::class)->run(monDetector('Q-1', ['check_against' => 'vedomost']));

    expect(MonitoringIstisno::sole()->status)->toBe('yopildi');
});
