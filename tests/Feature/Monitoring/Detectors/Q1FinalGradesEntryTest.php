<?php

require_once __DIR__ . '/../helpers.php';

use App\Models\HemisExamGrade;
use App\Models\MonitoringIstisno;
use App\Services\Monitoring\DetectorRunner;
use Illuminate\Support\Facades\DB;

/*
 * Q-1: yakuniy nazorat baholari nazoratdan keyin 3 ish kuni ichida HEMISga; mas'ul — kafedra mudiri.
 */

beforeEach(function () {
    // Juma 2026-10-16 08:00
    $this->travelTo(now()->setDate(2026, 10, 16)->setTime(8, 0));
    $this->kafedraId = 61001;
    $this->head = monTeacher(['kafedra_mudiri'], ['head_department_hemis_id' => $this->kafedraId]);
    $this->group = monGroup(['curriculum_hemis_id' => 4401]);
    DB::table('curriculum_subjects')->insert([
        'curriculum_subject_hemis_id' => 1, 'curricula_hemis_id' => 4401, 'subject_id' => 3001,
        'subject_name' => 'Sinov fani', 'subject_code' => 'SF01', 'semester_code' => '13',
        'semester_name' => '3-semestr', 'at_semester' => 1, 'department_id' => $this->kafedraId,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $this->students = [
        monStudent(['group_id' => $this->group->group_hemis_id]),
        monStudent(['group_id' => $this->group->group_hemis_id]),
    ];
    $this->detector = monDetector('Q-1');
});

function q1Exam(object $group, string $oski, ?string $test = null): void
{
    DB::table('exam_schedules')->insert([
        'department_hemis_id' => (string) $group->department_hemis_id, 'specialty_hemis_id' => '1',
        'curriculum_hemis_id' => (string) $group->curriculum_hemis_id, 'semester_code' => '13',
        'group_hemis_id' => (string) $group->group_hemis_id, 'subject_id' => '3001', 'subject_name' => 'Sinov fani',
        'oski_date' => $oski, 'test_date' => $test, 'oski_na' => 0, 'test_na' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

function q1Grade(object $student, ?string $enteredAt = null): void
{
    HemisExamGrade::create([
        'hemis_record_id' => random_int(1, 1_000_000_000),
        'student_hemis_id' => (string) $student->hemis_id,
        'subject_id' => '3001',
        'semester_code' => '13',
        'exam_type_code' => '13',
        'grade' => 75,
        'first_seen_at' => $enteredAt ?? now(),
    ]);
}

test('muddat o\'tgan, baholar kiritilmagan — istisno kafedra mudiriga', function () {
    q1Exam($this->group, '2026-10-05', '2026-10-07'); // muddat: 07.10 + 3 ish kuni = 10.10

    app(DetectorRunner::class)->run($this->detector);

    $istisno = MonitoringIstisno::sole();
    expect($istisno->details['kiritilmagan'])->toBe(2)
        ->and($istisno->details['nazorat_sanasi'])->toBe('2026-10-07')
        ->and($istisno->isOwnedBy($this->head))->toBeTrue()
        ->and($istisno->department_hemis_id)->toBe($this->kafedraId);
});

test('barcha baholar kiritilgan — istisno yo\'q', function () {
    q1Exam($this->group, '2026-10-07');
    foreach ($this->students as $s) {
        q1Grade($s, '2026-10-08 10:00:00');
    }

    app(DetectorRunner::class)->run($this->detector);

    expect(MonitoringIstisno::count())->toBe(0);
});

test('3 ish kuni hali o\'tmagan — istisno yo\'q', function () {
    q1Exam($this->group, '2026-10-14'); // muddat 17.10 (yakshanba hisoblanmaydi)

    app(DetectorRunner::class)->run($this->detector);

    expect(MonitoringIstisno::count())->toBe(0);
});

test('kechikib kiritilsa: auto_close — yopiladi, keep_open — ochiq qoladi', function () {
    q1Exam($this->group, '2026-10-05');
    app(DetectorRunner::class)->run($this->detector);
    expect(MonitoringIstisno::sole()->status)->toBe('ochiq');

    foreach ($this->students as $s) {
        q1Grade($s, '2026-10-14 09:00:00'); // muddat 08.10 edi
    }
    app(DetectorRunner::class)->run($this->detector->fresh());
    expect(MonitoringIstisno::sole()->status)->toBe('yopildi');

    $keepOpen = monDetector('Q-1', ['late_completion' => 'keep_open']);
    app(DetectorRunner::class)->run($keepOpen);
    $istisno = MonitoringIstisno::sole();
    expect($istisno->status)->toBe('qayta_ochildi')
        ->and($istisno->details['kechikib_kiritilgan'])->toBe(2);
});
