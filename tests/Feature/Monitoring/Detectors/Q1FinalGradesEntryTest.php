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

function q1ResitComponent(object $student, int $attempt, string $date): void
{
    DB::table('student_grades')->insert([
        'hemis_id' => random_int(1, 1_000_000_000), 'student_id' => $student->id, 'student_hemis_id' => $student->hemis_id,
        'semester_code' => '13', 'semester_name' => '3-semestr', 'subject_schedule_id' => 0, 'subject_id' => 3001,
        'subject_name' => 'Sinov fani', 'subject_code' => 'SF01', 'training_type_code' => '101', 'training_type_name' => 'OSKI',
        'employee_id' => 1, 'employee_name' => 'Sinov', 'lesson_pair_code' => '11', 'lesson_pair_name' => '1',
        'lesson_pair_start_time' => '08:30', 'lesson_pair_end_time' => '09:50', 'lesson_date' => $date . ' 09:00:00',
        'created_at_api' => $date . ' 12:00:00', 'grade' => 70, 'attempt' => $attempt,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

function q1ResitExam(object $group, string $main, string $resit): void
{
    q1Exam($group, $main);
    DB::table('exam_schedules')->where('group_hemis_id', (string) $group->group_hemis_id)->update(['oski_resit_date' => $resit]);
}

test('qayta topshirish: qatnashgan talabaning bahosi 3 ish kunida kiritilmagan — alohida istisno', function () {
    q1ResitExam($this->group, '2026-09-21', '2026-10-05');
    foreach ($this->students as $s) {
        q1Grade($s, '2026-09-22 10:00:00'); // asosiy urinish o'z vaqtida
    }
    q1ResitComponent($this->students[0], 2, '2026-10-05'); // faqat bittasi qayta topshirgan

    app(DetectorRunner::class)->run($this->detector);

    $istisno = MonitoringIstisno::sole();
    expect($istisno->fingerprint)->toEndWith(':u2')
        ->and($istisno->details['urinish'])->toBe('1-qayta topshirish')
        ->and($istisno->details['talabalar'])->toBe(1)
        ->and($istisno->details['kiritilmagan'])->toBe(1)
        ->and($istisno->subject_label)->toContain('qayta topshirish 1');
});

test('qayta topshirish bahosi HEMISga kiritilgan bo\'lsa — istisno yo\'q', function () {
    q1ResitExam($this->group, '2026-09-21', '2026-10-05');
    foreach ($this->students as $s) {
        q1Grade($s, '2026-09-22 10:00:00');
    }
    q1ResitComponent($this->students[0], 2, '2026-10-05');
    HemisExamGrade::create([
        'hemis_record_id' => random_int(1, 1_000_000_000), 'student_hemis_id' => (string) $this->students[0]->hemis_id,
        'subject_id' => '3001', 'semester_code' => '13', 'exam_type_code' => '13', 'grade' => 64,
        'exam_date' => '2026-10-05 09:00:00', 'first_seen_at' => '2026-10-06 03:00:00',
    ]);

    app(DetectorRunner::class)->run($this->detector);

    expect(MonitoringIstisno::count())->toBe(0);
});

test('mark\'da qayta topshirgan talaba bo\'lmasa yoki include_resits o\'chirilgan bo\'lsa — istisno yo\'q', function () {
    q1ResitExam($this->group, '2026-09-21', '2026-10-05');
    foreach ($this->students as $s) {
        q1Grade($s, '2026-09-22 10:00:00');
    }

    app(DetectorRunner::class)->run($this->detector);
    expect(MonitoringIstisno::count())->toBe(0);

    q1ResitComponent($this->students[0], 2, '2026-10-05');
    app(DetectorRunner::class)->run(monDetector('Q-1', ['include_resits' => false]));
    expect(MonitoringIstisno::count())->toBe(0);
});

test('eski 103 yozuvi faqat quiz turi OSKI/YN test bo\'lsa qayta topshirish hisoblanadi', function () {
    q1ResitExam($this->group, '2026-09-21', '2026-10-05');
    foreach ($this->students as $s) {
        q1Grade($s, '2026-09-22 10:00:00');
    }
    $otherQuiz = DB::table('hemis_quiz_results')->insertGetId(['attempt_id' => 1, 'quiz_type' => 'Oraliq nazorat', 'created_at' => now(), 'updated_at' => now()]);
    q1ResitComponent($this->students[0], 2, '2026-10-05');
    DB::table('student_grades')->where('student_hemis_id', $this->students[0]->hemis_id)->where('attempt', 2)
        ->update(['training_type_code' => '103', 'quiz_result_id' => $otherQuiz]);

    app(DetectorRunner::class)->run($this->detector);
    expect(MonitoringIstisno::count())->toBe(0);

    $ynQuiz = DB::table('hemis_quiz_results')->insertGetId(['attempt_id' => 2, 'quiz_type' => 'YN test (uzb)', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('student_grades')->where('student_hemis_id', $this->students[0]->hemis_id)->where('attempt', 2)
        ->update(['quiz_result_id' => $ynQuiz]);

    app(DetectorRunner::class)->run($this->detector);
    expect(MonitoringIstisno::sole()->fingerprint)->toEndWith(':u2');
});

function q1IndividualExam(object $group, object $student, array $dates): void
{
    DB::table('exam_schedules')->insert(array_merge([
        'department_hemis_id' => (string) $group->department_hemis_id, 'specialty_hemis_id' => '1',
        'curriculum_hemis_id' => (string) $group->curriculum_hemis_id, 'semester_code' => '13',
        'group_hemis_id' => (string) $group->group_hemis_id, 'student_hemis_id' => (string) $student->hemis_id,
        'subject_id' => '3001', 'subject_name' => 'Sinov fani', 'oski_na' => 0, 'test_na' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ], $dates));
}

test('individual qayta topshirish sanasi (guruhda yo\'q) ham tekshiriladi', function () {
    q1Exam($this->group, '2026-09-21');
    foreach ($this->students as $s) {
        q1Grade($s, '2026-09-22 10:00:00');
    }
    q1IndividualExam($this->group, $this->students[0], ['oski_resit_date' => '2026-10-05']);
    q1ResitComponent($this->students[0], 2, '2026-10-05');

    app(DetectorRunner::class)->run($this->detector);

    $istisno = MonitoringIstisno::sole();
    expect($istisno->fingerprint)->toEndWith(':20261005:u2')
        ->and($istisno->details['talabalar'])->toBe(1)
        ->and($istisno->details['kiritilmagan'])->toBe(1);
});

test('individual asosiy sana: talaba guruh sanasida emas, o\'z sanasi bo\'yicha tekshiriladi', function () {
    q1Exam($this->group, '2026-10-05');
    q1Grade($this->students[1], '2026-10-06 10:00:00');
    q1IndividualExam($this->group, $this->students[0], ['oski_date' => '2026-10-14']); // muddat 17.10

    app(DetectorRunner::class)->run($this->detector);
    expect(MonitoringIstisno::count())->toBe(0);

    $this->travelTo(now()->setDate(2026, 10, 19)->setTime(8, 0));
    app(DetectorRunner::class)->run($this->detector->fresh());

    $istisno = MonitoringIstisno::sole();
    expect($istisno->details['nazorat_sanasi'])->toBe('2026-10-14')
        ->and($istisno->details['talabalar'])->toBe(1)
        ->and($istisno->details['kiritilmagan'])->toBe(1);
});
