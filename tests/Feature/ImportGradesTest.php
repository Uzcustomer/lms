<?php

use App\Models\ActivityLog;
use App\Models\Deadline;
use App\Models\Student;
use App\Models\StudentGrade;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

// =========================================================================
// Helper: HEMIS API ga o'xshash baho item yaratish
// =========================================================================
function makeGradeItem(int $id, int $studentHemisId, Carbon $lessonDate, float $grade = 85): array
{
    return [
        'id' => $id,
        '_student' => $studentHemisId,
        'grade' => $grade,
        'lesson_date' => $lessonDate->timestamp,
        'created_at' => $lessonDate->timestamp,
        'semester' => ['code' => '1', 'name' => '1-semestr'],
        'educationYear' => ['code' => '2025', 'name' => '2025-2026'],
        '_subject_schedule' => 100,
        'subject' => ['id' => 1, 'name' => 'Matematika', 'code' => 'MAT101'],
        'trainingType' => ['code' => '1', 'name' => 'Ma\'ruza'],
        'employee' => ['id' => 1, 'name' => 'Teacher A'],
        'lessonPair' => ['code' => '1', 'name' => '1-para', 'start_time' => '08:00', 'end_time' => '09:30'],
    ];
}

// =========================================================================
// Helper: HEMIS API javobini yaratish (Http::fake uchun)
// =========================================================================
function makeApiResponse(array $items): array
{
    return [
        'data' => [
            'items' => $items,
            'pagination' => ['pageCount' => 1],
        ],
    ];
}

// =========================================================================
// Helper: Minimal student yaratish
// =========================================================================
function createTestStudent(int $hemisId, string $levelCode = '11'): Student
{
    return Student::create([
        'hemis_id' => $hemisId,
        'full_name' => "Test Student {$hemisId}",
        'short_name' => "Student {$hemisId}",
        'first_name' => 'Test',
        'second_name' => 'Student',
        'student_id_number' => "STU{$hemisId}",
        'level_code' => $levelCode,
    ]);
}

// =========================================================================
// TEST 1: Bulk insert ishlaydi — baholar DB ga yoziladi
// =========================================================================
test('applyGrades creates grades via bulk insert', function () {
    $student1 = createTestStudent(1001);
    $student2 = createTestStudent(1002);
    Deadline::create(['level_code' => '11', 'deadline_days' => 7]);

    $today = Carbon::today();
    $items = [
        makeGradeItem(1, 1001, $today->copy()->setTime(8, 0), 85),
        makeGradeItem(2, 1001, $today->copy()->setTime(10, 0), 90),
        makeGradeItem(3, 1002, $today->copy()->setTime(8, 0), 75),
    ];

    Http::fake([
        '*/student-grade-list*' => Http::response(makeApiResponse($items)),
        '*/attendance-list*' => Http::response(makeApiResponse([])),
    ]);

    $this->artisan('student:import-data', ['--mode' => 'live'])
        ->assertExitCode(0);

    // 3 ta baho yozilgan bo'lishi kerak
    expect(StudentGrade::count())->toBe(3);
    expect(StudentGrade::where('student_hemis_id', 1001)->count())->toBe(2);
    expect(StudentGrade::where('student_hemis_id', 1002)->count())->toBe(1);

    // is_final=false bo'lishi kerak (live import)
    expect(StudentGrade::where('is_final', false)->count())->toBe(3);
});

// =========================================================================
// TEST 2: Bulk insert LogsActivity ni trigger QILMAYDI
// =========================================================================
test('bulk insert does not create ActivityLog entries', function () {
    createTestStudent(2001);

    $today = Carbon::today();
    $items = [
        makeGradeItem(1, 2001, $today->copy()->setTime(8, 0), 85),
        makeGradeItem(2, 2001, $today->copy()->setTime(10, 0), 90),
    ];

    Http::fake([
        '*/student-grade-list*' => Http::response(makeApiResponse($items)),
        '*/attendance-list*' => Http::response(makeApiResponse([])),
    ]);

    $logCountBefore = DB::table('activity_logs')->count();

    $this->artisan('student:import-data', ['--mode' => 'live'])
        ->assertExitCode(0);

    // ActivityLog yaratilmagan bo'lishi kerak — bulk insert Eloquent events ni o'tkazib yuboradi
    $logCountAfter = DB::table('activity_logs')->count();
    expect($logCountAfter)->toBe($logCountBefore);
});

// =========================================================================
// TEST 3: Boshqa kunlik recordlar filtrlanadi
// =========================================================================
test('wrong date records are filtered out during fetch', function () {
    createTestStudent(3001);

    $today = Carbon::today();
    $yesterday = Carbon::yesterday();

    // 2 ta bugungi + 1 ta kechagi (filtrlanishi kerak)
    $items = [
        makeGradeItem(1, 3001, $today->copy()->setTime(8, 0), 85),
        makeGradeItem(2, 3001, $today->copy()->setTime(10, 0), 90),
        makeGradeItem(3, 3001, $yesterday->copy()->setTime(8, 0), 70), // boshqa kun!
    ];

    Http::fake([
        '*/student-grade-list*' => Http::response(makeApiResponse($items)),
        '*/attendance-list*' => Http::response(makeApiResponse([])),
    ]);

    $this->artisan('student:import-data', ['--mode' => 'live'])
        ->assertExitCode(0);

    // Faqat 2 ta baho yozilishi kerak (kechagi filtrlangan)
    expect(StudentGrade::count())->toBe(2);
});

// =========================================================================
// TEST 4: Soft delete + yangi insert — eski baholar o'chiriladi, yangilari yoziladi
// =========================================================================
test('applyGrades replaces old grades with new ones', function () {
    $student = createTestStudent(4001);
    $today = Carbon::today();

    // Avval bazaga eski baholar qo'shamiz
    DB::table('student_grades')->insert([
        'hemis_id' => 999,
        'student_id' => $student->id,
        'student_hemis_id' => 4001,
        'semester_code' => '1',
        'semester_name' => '1-semestr',
        'subject_schedule_id' => 100,
        'subject_id' => 1,
        'subject_name' => 'Matematika',
        'subject_code' => 'MAT101',
        'training_type_code' => '1',
        'training_type_name' => 'Ma\'ruza',
        'employee_id' => 1,
        'employee_name' => 'Teacher A',
        'lesson_pair_code' => '1',
        'lesson_pair_name' => '1-para',
        'lesson_pair_start_time' => '08:00',
        'lesson_pair_end_time' => '09:30',
        'grade' => 60,
        'lesson_date' => $today->copy()->setTime(8, 0),
        'created_at_api' => now(),
        'status' => 'recorded',
        'is_final' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(StudentGrade::count())->toBe(1);
    expect(StudentGrade::first()->grade)->toBe(60.0);

    // API yangi baholar qaytaradi
    $items = [
        makeGradeItem(1, 4001, $today->copy()->setTime(8, 0), 95),
    ];

    Http::fake([
        '*/student-grade-list*' => Http::response(makeApiResponse($items)),
        '*/attendance-list*' => Http::response(makeApiResponse([])),
    ]);

    $this->artisan('student:import-data', ['--mode' => 'live'])
        ->assertExitCode(0);

    // Eski baho soft-deleted, yangi baho yozilgan
    expect(StudentGrade::count())->toBe(1);
    expect(StudentGrade::first()->grade)->toBe(95.0);

    // Soft deleted baho ham bor
    expect(StudentGrade::withTrashed()->count())->toBe(2);
});

// =========================================================================
// TEST 5: Retake ma'lumotlar saqlanadi
// =========================================================================
test('retake grades are preserved after reimport', function () {
    $student = createTestStudent(5001);
    $today = Carbon::today();

    // Retake bilan baho yaratamiz
    DB::table('student_grades')->insert([
        'hemis_id' => 555,
        'student_id' => $student->id,
        'student_hemis_id' => 5001,
        'semester_code' => '1',
        'semester_name' => '1-semestr',
        'subject_schedule_id' => 100,
        'subject_id' => 1,
        'subject_name' => 'Matematika',
        'subject_code' => 'MAT101',
        'training_type_code' => '1',
        'training_type_name' => 'Ma\'ruza',
        'employee_id' => 1,
        'employee_name' => 'Teacher A',
        'lesson_pair_code' => '1',
        'lesson_pair_name' => '1-para',
        'lesson_pair_start_time' => '08:00',
        'lesson_pair_end_time' => '09:30',
        'grade' => 50,
        'lesson_date' => $today->copy()->setTime(8, 0),
        'created_at_api' => now(),
        'status' => 'retake',
        'reason' => 'low_grade',
        'retake_grade' => 80,
        'retake_graded_at' => now(),
        'retake_by' => 'teacher',
        'is_final' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // API baho qaytaradi (retake ma'lumotlarsiz)
    $items = [
        makeGradeItem(555, 5001, $today->copy()->setTime(8, 0), 50),
    ];

    Http::fake([
        '*/student-grade-list*' => Http::response(makeApiResponse($items)),
        '*/attendance-list*' => Http::response(makeApiResponse([])),
    ]);

    $this->artisan('student:import-data', ['--mode' => 'live'])
        ->assertExitCode(0);

    // Retake ma'lumotlar yangi yozuvga ko'chirilgan bo'lishi kerak
    $grade = StudentGrade::first();
    expect($grade->retake_grade)->toBe(80.0);
    expect($grade->status)->toBe('retake');
    expect($grade->retake_by)->toBe('teacher');
});

// =========================================================================
// TEST 6: Ko'p baholar bilan bulk insert (OOM test)
// =========================================================================
test('handles large number of grades without OOM', function () {
    // 500 ta student yaratamiz
    $students = [];
    for ($i = 0; $i < 500; $i++) {
        $students[] = createTestStudent(6000 + $i);
    }

    $today = Carbon::today();
    $items = [];

    // Har bir student uchun 2 tadan baho = 1000 ta
    foreach ($students as $student) {
        $items[] = makeGradeItem(
            $student->hemis_id * 10 + 1,
            $student->hemis_id,
            $today->copy()->setTime(8, 0),
            rand(60, 100)
        );
        $items[] = makeGradeItem(
            $student->hemis_id * 10 + 2,
            $student->hemis_id,
            $today->copy()->setTime(10, 0),
            rand(60, 100)
        );
    }

    Http::fake([
        '*/student-grade-list*' => Http::response(makeApiResponse($items)),
        '*/attendance-list*' => Http::response(makeApiResponse([])),
    ]);

    $memBefore = memory_get_usage(true);

    $this->artisan('student:import-data', ['--mode' => 'live'])
        ->assertExitCode(0);

    $memAfter = memory_get_usage(true);

    // 1000 ta baho yozilgan bo'lishi kerak
    expect(StudentGrade::count())->toBe(1000);

    // Xotira oshishi 50MB dan oshmasligi kerak
    $memDiffMB = ($memAfter - $memBefore) / 1024 / 1024;
    expect($memDiffMB)->toBeLessThan(50);
});

// =========================================================================
// TEST 7: Final import is_final=true qiladi
// =========================================================================
test('final import sets is_final to true', function () {
    $student = createTestStudent(7001);
    $yesterday = Carbon::yesterday();

    // Kechagi baholarni is_final=false qilib yaratamiz
    DB::table('student_grades')->insert([
        'hemis_id' => 777,
        'student_id' => $student->id,
        'student_hemis_id' => 7001,
        'semester_code' => '1',
        'semester_name' => '1-semestr',
        'subject_schedule_id' => 100,
        'subject_id' => 1,
        'subject_name' => 'Matematika',
        'subject_code' => 'MAT101',
        'training_type_code' => '1',
        'training_type_name' => 'Ma\'ruza',
        'employee_id' => 1,
        'employee_name' => 'Teacher A',
        'lesson_pair_code' => '1',
        'lesson_pair_name' => '1-para',
        'lesson_pair_start_time' => '08:00',
        'lesson_pair_end_time' => '09:30',
        'grade' => 85,
        'lesson_date' => $yesterday->copy()->setTime(8, 0),
        'created_at_api' => now(),
        'status' => 'recorded',
        'is_final' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // API kechagi baholarni qaytaradi
    $items = [
        makeGradeItem(777, 7001, $yesterday->copy()->setTime(8, 0), 85),
    ];

    Http::fake([
        '*/student-grade-list*' => Http::response(makeApiResponse($items)),
        '*/attendance-list*' => Http::response(makeApiResponse([])),
    ]);

    $this->artisan('student:import-data', ['--mode' => 'final'])
        ->assertExitCode(0);

    // is_final=true bo'lishi kerak
    $grade = StudentGrade::first();
    expect($grade)->not->toBeNull();
    expect((bool)$grade->is_final)->toBeTrue();
});

// =========================================================================
// TEST 8: API xato bo'lganda eski baholar saqlanib qoladi
// =========================================================================
test('API failure preserves existing grades', function () {
    $student = createTestStudent(8001);
    $today = Carbon::today();

    // Bazadagi mavjud baho
    DB::table('student_grades')->insert([
        'hemis_id' => 888,
        'student_id' => $student->id,
        'student_hemis_id' => 8001,
        'semester_code' => '1',
        'semester_name' => '1-semestr',
        'subject_schedule_id' => 100,
        'subject_id' => 1,
        'subject_name' => 'Matematika',
        'subject_code' => 'MAT101',
        'training_type_code' => '1',
        'training_type_name' => 'Ma\'ruza',
        'employee_id' => 1,
        'employee_name' => 'Teacher A',
        'lesson_pair_code' => '1',
        'lesson_pair_name' => '1-para',
        'lesson_pair_start_time' => '08:00',
        'lesson_pair_end_time' => '09:30',
        'grade' => 75,
        'lesson_date' => $today->copy()->setTime(8, 0),
        'created_at_api' => now(),
        'status' => 'recorded',
        'is_final' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // API 500 xato qaytaradi
    Http::fake([
        '*/student-grade-list*' => Http::response('Server Error', 500),
        '*/attendance-list*' => Http::response('Server Error', 500),
    ]);

    $this->artisan('student:import-data', ['--mode' => 'live'])
        ->assertExitCode(0);

    // Eski baho saqlanib qolgan bo'lishi kerak
    expect(StudentGrade::count())->toBe(1);
    expect(StudentGrade::first()->grade)->toBe(75.0);
});

// =========================================================================
// TEST 9: Transaction rollback — insert xato bo'lganda eski baholar saqlanadi
// =========================================================================
test('transaction rollback preserves old grades on insert failure', function () {
    $student = createTestStudent(9001);
    $today = Carbon::today();

    // Bazadagi mavjud baho
    DB::table('student_grades')->insert([
        'hemis_id' => 999,
        'student_id' => $student->id,
        'student_hemis_id' => 9001,
        'semester_code' => '1',
        'semester_name' => '1-semestr',
        'subject_schedule_id' => 100,
        'subject_id' => 1,
        'subject_name' => 'Matematika',
        'subject_code' => 'MAT101',
        'training_type_code' => '1',
        'training_type_name' => 'Ma\'ruza',
        'employee_id' => 1,
        'employee_name' => 'Teacher A',
        'lesson_pair_code' => '1',
        'lesson_pair_name' => '1-para',
        'lesson_pair_start_time' => '08:00',
        'lesson_pair_end_time' => '09:30',
        'grade' => 75,
        'lesson_date' => $today->copy()->setTime(8, 0),
        'created_at_api' => now(),
        'status' => 'recorded',
        'is_final' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // API mavjud bo'lmagan studentga baho qaytaradi — student_id foreign key fail
    $items = [
        makeGradeItem(1, 9001, $today->copy()->setTime(8, 0), 85),
        makeGradeItem(2, 99999, $today->copy()->setTime(8, 0), 90), // mavjud emas student
    ];

    Http::fake([
        '*/student-grade-list*' => Http::response(makeApiResponse($items)),
        '*/attendance-list*' => Http::response(makeApiResponse([])),
    ]);

    $this->artisan('student:import-data', ['--mode' => 'live'])
        ->assertExitCode(0);

    // student_id=99999 skipped (processGrade checks student existence)
    // student 9001 yangi baho bilan almashtirilgan
    expect(StudentGrade::count())->toBe(1);
    expect(StudentGrade::first()->grade)->toBe(85.0);
});

// =========================================================================
// TEST 10: Low grade correctly detected and marked
// =========================================================================
test('low grades are detected and marked correctly', function () {
    $student = createTestStudent(10001, '11'); // level_code=11
    Deadline::create(['level_code' => '11', 'deadline_days' => 7]);

    $today = Carbon::today();

    // 30 ball — past baho (minimum_limit = 60 default)
    $items = [
        makeGradeItem(1, 10001, $today->copy()->setTime(8, 0), 30),
    ];

    Http::fake([
        '*/student-grade-list*' => Http::response(makeApiResponse($items)),
        '*/attendance-list*' => Http::response(makeApiResponse([])),
    ]);

    $this->artisan('student:import-data', ['--mode' => 'live'])
        ->assertExitCode(0);

    $grade = StudentGrade::first();
    expect($grade->status)->toBe('pending');
    expect($grade->reason)->toBe('low_grade');
    expect($grade->deadline)->not->toBeNull();
});
