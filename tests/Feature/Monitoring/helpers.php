<?php

/*
 * Monitoring testlari uchun soxta ma'lumot yaratish (faqat Faker / o'ylab topilgan qiymatlar).
 * Funksiyalar `mon` prefiksi bilan — boshqa test fayllaridagi yordamchilar bilan to'qnashmasin.
 */

use App\Models\MonitoringDetector;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

if (!function_exists('monSeq')) {
    function monSeq(): int
    {
        static $n = 0;

        return ++$n;
    }

    function monRoles(): void
    {
        foreach (\App\Enums\ProjectRole::cases() as $role) {
            Role::firstOrCreate(['name' => $role->value, 'guard_name' => 'web']);
        }
    }

    /**
     * Katalogdagi detektor (migratsiya seed qilgan), kerak bo'lsa parametrlar bilan.
     * Testlar «jonli» rejimni tekshiradi: katalogdagi xavfsiz ishga tushirish (warning_mode) o'chiriladi.
     */
    function monDetector(string $code, array $params = [], array $attrs = []): MonitoringDetector
    {
        $attrs += ['warning_mode' => false];
        $detector = MonitoringDetector::where('code', $code)->firstOrFail();
        if ($params !== []) {
            $attrs['params'] = array_merge($detector->params ?? [], $params);
        }
        if ($attrs !== []) {
            $detector->forceFill($attrs)->save();
        }

        return $detector->fresh();
    }

    function monFaculty(array $attrs = []): int
    {
        $id = $attrs['department_hemis_id'] ?? 5000 + monSeq();
        DB::table('departments')->insert(array_merge([
            'department_hemis_id' => $id,
            'name' => 'Sinov fakulteti ' . $id,
            'code' => 'F' . $id,
            'structure_type_code' => '11',
            'structure_type_name' => 'Fakultet',
            'locality_type_code' => '11',
            'locality_type_name' => 'Mahalliy',
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ], $attrs));

        return $id;
    }

    function monGroup(array $attrs = []): object
    {
        $id = $attrs['group_hemis_id'] ?? 7000 + monSeq();
        $row = array_merge([
            'group_hemis_id' => $id,
            'name' => 'SG-' . $id,
            'department_hemis_id' => 1,
            'department_name' => 'Sinov fakulteti',
            'department_code' => 'F1',
            'department_structure_type_code' => '11',
            'department_structure_type_name' => 'Fakultet',
            'department_locality_type_code' => '11',
            'department_locality_type_name' => 'Mahalliy',
            'department_active' => 1,
            'specialty_hemis_id' => 1,
            'specialty_code' => '60910200',
            'specialty_name' => 'Sinov yo\'nalishi',
            'education_lang_code' => '11',
            'education_lang_name' => 'O\'zbek',
            'curriculum_hemis_id' => 900,
            'created_at' => now(),
            'updated_at' => now(),
        ], $attrs);
        DB::table('groups')->insert($row);

        return (object) $row;
    }

    function monStudent(array $attrs = []): object
    {
        $n = monSeq();
        $row = array_merge([
            'hemis_id' => 100000 + $n,
            'full_name' => fake()->lastName() . ' ' . fake()->firstName() . ' ' . $n,
            'short_name' => 'Sinov ' . $n,
            'first_name' => fake()->firstName(),
            'second_name' => fake()->lastName(),
            'student_id_number' => 'ST' . (500000 + $n),
            'student_status_code' => '11',
            'student_status_name' => "O'qimoqda",
            'semester_code' => '13',
            'semester_name' => '3-semestr',
            'level_code' => '12',
            'group_id' => 1,
            'group_name' => 'SG-1',
            'department_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ], $attrs);
        $row['id'] = DB::table('students')->insertGetId($row);

        return (object) $row;
    }

    /** Talabaga sababsiz (absent_off) soat yozish — bitta yozuv. */
    function monAbsence(object $student, int $offHours, string $date = '2026-10-05', array $attrs = []): void
    {
        $n = monSeq();
        DB::table('attendances')->insert(array_merge([
            'hemis_id' => 800000 + $n,
            'subject_schedule_id' => 600000 + $n,
            'student_id' => $student->id,
            'student_hemis_id' => $student->hemis_id,
            'student_name' => $student->full_name,
            'employee_id' => 1,
            'employee_name' => 'Sinov O\'qituvchi',
            'subject_id' => 3001,
            'subject_name' => 'Sinov fani',
            'subject_code' => 'SF01',
            'education_year_code' => '2026',
            'education_year_name' => '2026-2027',
            'education_year_current' => 1,
            'semester_code' => $student->semester_code,
            'semester_name' => $student->semester_name,
            'group_id' => $student->group_id,
            'group_name' => $student->group_name,
            'education_lang_code' => '11',
            'education_lang_name' => 'O\'zbek',
            'training_type_code' => '13',
            'training_type_name' => 'Amaliy',
            'lesson_pair_code' => '11',
            'lesson_pair_name' => '1-juftlik',
            'lesson_pair_start_time' => '08:30',
            'lesson_pair_end_time' => '09:50',
            'absent_on' => 0,
            'absent_off' => $offHours,
            'lesson_date' => $date . ' 08:30:00',
            'status' => 'absent',
            'created_at' => now(),
            'updated_at' => now(),
        ], $attrs));
    }

    function monTeacher(array $roles = [], array $attrs = []): Teacher
    {
        $n = monSeq();
        $teacher = Teacher::forceCreate(array_merge([
            'hemis_id' => 950000 + $n,
            'full_name' => 'Sinov Xodim ' . $n,
            'short_name' => 'Sinov X. ' . $n,
            'first_name' => 'Sinov',
            'second_name' => 'Xodim' . $n,
            'employee_id_number' => 'M-' . (800000 + $n),
            'birth_date' => '1990-01-15',
            'year_of_enter' => 2020,
            'gender' => 'Erkak',
            'department' => 'Sinov kafedrasi',
            'employment_form' => 'Asosiy',
            'employment_staff' => '1',
            'staff_position' => 'Assistent',
            'employee_status' => 'Ishlamoqda',
            'employee_type' => 'O\'qituvchi',
            'contract_number' => 'C-' . $n,
            'decree_number' => 'D-' . $n,
            'contract_date' => '2020-09-01',
            'decree_date' => '2020-09-01',
            'login' => 'monsinov' . $n,
            'password' => 'parol-12345',
            'is_active' => true,
        ], $attrs));
        if ($roles !== []) {
            monRoles();
            $teacher->syncRoles($roles);
        }

        return $teacher;
    }

    function monUser(array $roles): User
    {
        $n = monSeq();
        monRoles();
        $user = User::forceCreate([
            'name' => 'Sinov Admin ' . $n,
            'email' => "monadmin{$n}@example.test",
            'password' => bcrypt('parol-12345'),
        ]);
        $user->syncRoles($roles);

        return $user;
    }

    function monDean(int $facultyHemisId): Teacher
    {
        $dean = monTeacher(['dekan']);
        DB::table('dean_faculties')->insert([
            'teacher_id' => $dean->id,
            'department_hemis_id' => $facultyHemisId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $dean;
    }

    function monOrder(array $attrs = []): \App\Models\MonitoringOrder
    {
        $n = monSeq();
        $attrs = array_merge([
            'order_number' => 'B-' . $n,
            'order_date' => '2026-10-05',
            'order_type' => 'chetlashtirish',
            'order_type_raw' => 'Talabalar safidan chetlashtirish',
            'student_hemis_id' => null,
            'student_id_number' => null,
            'full_name' => 'Sinov Talaba ' . $n,
        ], $attrs);
        $attrs['dedupe_key'] = \App\Models\MonitoringOrder::makeDedupeKey(
            $attrs['order_number'], (string) $attrs['order_date'], $attrs['student_hemis_id'], $attrs['student_id_number'], $attrs['full_name']
        );

        return \App\Models\MonitoringOrder::create($attrs);
    }
}
