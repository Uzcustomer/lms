<?php

namespace Tests\Feature\Retake;

use App\Models\Department;
use App\Models\RetakeApplication;
use App\Models\RetakeApplicationPeriod;
use App\Models\Specialty;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\Retake\RetakeApplicationService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Retake test'lari uchun bazaviy class.
 *
 * Mustaqil: boshqa migration'lardan ajralgan, faqat retake va kerakli
 * yordamchi jadvallarni in-memory SQLite'da yaratadi. Real loyiha schema'siga
 * mos.
 */
abstract class RetakeTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // SQLite in-memory connection
        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'foreign_key_constraints' => false,
        ]);
        Config::set('cache.default', 'array');
        Config::set('session.driver', 'array');
        Config::set('queue.default', 'sync');
        Config::set('app.url', 'http://localhost');

        // Storage uchun fake disk
        Storage::fake('local');

        $this->createSchema();
        $this->seedRoles();
    }

    /**
     * Kerakli jadvallarni in-memory'da yaratish.
     * Real loyiha migration'larining qisqartirilgan ko'rinishi.
     */
    private function createSchema(): void
    {
        // Spatie permission jadvallari
        Schema::create('permissions', function ($table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });

        Schema::create('roles', function ($table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });

        Schema::create('model_has_roles', function ($table) {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->primary(['role_id', 'model_id', 'model_type']);
        });

        Schema::create('model_has_permissions', function ($table) {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->primary(['permission_id', 'model_id', 'model_type']);
        });

        Schema::create('role_has_permissions', function ($table) {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
            $table->primary(['permission_id', 'role_id']);
        });

        // Departments
        Schema::create('departments', function ($table) {
            $table->id();
            $table->unsignedBigInteger('department_hemis_id');
            $table->string('name');
            $table->string('code')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // Specialties
        Schema::create('specialties', function ($table) {
            $table->id();
            $table->unsignedBigInteger('specialty_hemis_id')->unique();
            $table->string('code');
            $table->string('name');
            $table->timestamps();
        });

        // Students
        Schema::create('students', function ($table) {
            $table->id();
            $table->unsignedBigInteger('hemis_id')->unique();
            $table->string('full_name');
            $table->string('short_name')->nullable();
            $table->string('first_name')->nullable();
            $table->string('second_name')->nullable();
            $table->string('student_id_number')->unique();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->string('department_name')->nullable();
            $table->unsignedBigInteger('specialty_id')->nullable();
            $table->string('specialty_name')->nullable();
            $table->unsignedBigInteger('group_id')->nullable();
            $table->string('group_name')->nullable();
            $table->unsignedBigInteger('semester_id')->nullable();
            $table->string('semester_name')->nullable();
            $table->string('level_code')->nullable();
            $table->string('level_name')->nullable();
            $table->unsignedBigInteger('curriculum_id')->nullable();
            $table->timestamps();
        });

        // Teachers
        Schema::create('teachers', function ($table) {
            $table->id();
            $table->string('full_name');
            $table->string('short_name')->nullable();
            $table->unsignedBigInteger('hemis_id')->nullable();
            $table->unsignedBigInteger('department_hemis_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('login')->nullable();
            $table->string('password')->nullable();
            $table->timestamps();
        });

        // Dean faculties (pivot)
        Schema::create('dean_faculties', function ($table) {
            $table->id();
            $table->foreignId('teacher_id');
            $table->bigInteger('department_hemis_id');
            $table->timestamps();
            $table->unique(['teacher_id', 'department_hemis_id']);
        });

        // Academic records (HEMIS sync)
        Schema::create('academic_records', function ($table) {
            $table->id();
            $table->unsignedBigInteger('hemis_id')->unique();
            $table->unsignedBigInteger('curriculum_id')->nullable();
            $table->string('education_year')->nullable();
            $table->string('semester_id')->nullable();
            $table->unsignedBigInteger('student_id')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->string('semester_name')->nullable();
            $table->string('subject_name')->nullable();
            $table->string('credit')->nullable();
            $table->string('total_point')->nullable();
            $table->string('grade')->nullable();
            $table->boolean('finish_credit_status')->default(false);
            $table->boolean('retraining_status')->default(false);
            $table->timestamps();
        });

        // Retake jadvallari (asosiy migration'lar bilan bir xil)
        Schema::create('retake_application_periods', function ($table) {
            $table->id();
            $table->unsignedBigInteger('specialty_id');
            $table->unsignedTinyInteger('course');
            $table->unsignedBigInteger('semester_id');
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedBigInteger('created_by');
            $table->string('created_by_guard', 10)->default('web');
            $table->timestamps();
            $table->unique(['specialty_id', 'course', 'semester_id']);
        });

        Schema::create('retake_groups', function ($table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('subject_id');
            $table->string('subject_name');
            $table->unsignedBigInteger('semester_id');
            $table->string('semester_name')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedBigInteger('teacher_id');
            $table->unsignedInteger('max_students')->nullable();
            $table->string('status', 20)->default('forming');
            $table->unsignedBigInteger('created_by');
            $table->string('created_by_guard', 10)->default('web');
            $table->timestamps();
        });

        Schema::create('retake_applications', function ($table) {
            $table->id();
            $table->uuid('application_group_id');
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('subject_id');
            $table->string('subject_name');
            $table->unsignedBigInteger('semester_id');
            $table->string('semester_name')->nullable();
            $table->decimal('credit', 4, 2);
            $table->unsignedBigInteger('period_id');
            $table->string('receipt_path');
            $table->string('receipt_original_name');
            $table->unsignedInteger('receipt_size');
            $table->string('receipt_mime', 50);
            $table->text('student_note')->nullable();
            $table->string('generated_doc_path')->nullable();
            $table->string('dean_status', 20)->default('pending');
            $table->string('registrar_status', 20)->default('pending');
            $table->string('academic_dept_status', 20)->default('not_started');
            $table->unsignedBigInteger('dean_reviewed_by')->nullable();
            $table->string('dean_reviewed_by_guard', 10)->nullable();
            $table->timestamp('dean_reviewed_at')->nullable();
            $table->text('dean_rejection_reason')->nullable();
            $table->unsignedBigInteger('registrar_reviewed_by')->nullable();
            $table->string('registrar_reviewed_by_guard', 10)->nullable();
            $table->timestamp('registrar_reviewed_at')->nullable();
            $table->text('registrar_rejection_reason')->nullable();
            $table->unsignedBigInteger('academic_dept_reviewed_by')->nullable();
            $table->string('academic_dept_reviewed_by_guard', 10)->nullable();
            $table->timestamp('academic_dept_reviewed_at')->nullable();
            $table->text('academic_dept_rejection_reason')->nullable();
            $table->unsignedBigInteger('retake_group_id')->nullable();
            $table->uuid('verification_code')->nullable()->unique();
            $table->string('tasdiqnoma_pdf_path')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamps();
        });

        Schema::create('retake_application_logs', function ($table) {
            $table->id();
            $table->unsignedBigInteger('application_id');
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_guard', 10)->nullable();
            $table->string('action', 40);
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    private function seedRoles(): void
    {
        $roles = ['talaba', 'dekan', 'registrator_ofisi', 'oquv_bolimi', 'oquv_bolimi_boshligi', 'oqituvchi', 'admin', 'superadmin'];
        foreach ($roles as $name) {
            Role::create(['name' => $name, 'guard_name' => 'web']);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    // ─── Test helpers ─────────────────────────────────────────────────

    protected function makeStudent(array $overrides = []): Student
    {
        static $hemisCounter = 1000000;
        $hemisCounter++;

        $student = Student::create(array_merge([
            'hemis_id' => $hemisCounter,
            'full_name' => 'Test Talaba',
            'short_name' => 'T. T.',
            'first_name' => 'Test',
            'second_name' => 'Talaba',
            'student_id_number' => 'STD-' . $hemisCounter,
            'department_id' => 100,
            'department_name' => 'Davolash ishi',
            'specialty_id' => 12,
            'specialty_name' => 'Davolash ishi',
            'group_id' => 5001,
            'group_name' => '101',
            'semester_id' => 7,
            'semester_name' => '1-semestr',
            'level_code' => '11',
            'level_name' => '1-kurs',
        ], $overrides));

        $student->assignRole('talaba');
        return $student;
    }

    protected function makeTeacher(array $overrides = [], string $role = 'dekan', array $facultyIds = []): Teacher
    {
        static $counter = 1;
        $counter++;

        $teacher = Teacher::create(array_merge([
            'full_name' => "Test Teacher {$counter}",
            'short_name' => "T.T.{$counter}",
            'hemis_id' => 9000000 + $counter,
            'department_hemis_id' => 100,
            'is_active' => true,
            'login' => "teacher{$counter}",
            'password' => bcrypt('password'),
        ], $overrides));

        $teacher->assignRole($role);

        foreach ($facultyIds as $facultyId) {
            // Department yozuvi ham kerak — Teacher::dean_faculty_ids accessor
            // dean_faculties pivot'ni Department jadvali orqali pluck qiladi
            DB::table('departments')->updateOrInsert(
                ['department_hemis_id' => $facultyId],
                [
                    'name' => 'Test Department ' . $facultyId,
                    'active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );

            DB::table('dean_faculties')->insert([
                'teacher_id' => $teacher->id,
                'department_hemis_id' => $facultyId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $teacher;
    }

    /**
     * Talabaning qarzdor faniga (academic_records.retraining_status=true) yozuv qo'shish.
     */
    protected function makeDebt(Student $student, array $overrides = []): void
    {
        static $hemisCounter = 5000000;
        $hemisCounter++;

        DB::table('academic_records')->insert(array_merge([
            'hemis_id' => $hemisCounter,
            'student_id' => $student->hemis_id,
            'subject_id' => 100 + $hemisCounter % 100,
            'subject_name' => 'Anatomiya',
            'semester_id' => '7',
            'semester_name' => '1-semestr',
            'credit' => '6.0',
            'total_point' => '70',
            'grade' => '4.00',
            'retraining_status' => true,
            'finish_credit_status' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    /**
     * Talaba uchun faol qabul oynasi (idempotent — bir xil
     * specialty+course+semester bo'lsa mavjud yozuvni qaytaradi).
     */
    protected function makePeriod(?Student $student = null, array $overrides = []): RetakeApplicationPeriod
    {
        $unique = [
            'specialty_id' => $overrides['specialty_id'] ?? $student?->specialty_id ?? 12,
            'course' => $overrides['course'] ?? 1,
            'semester_id' => $overrides['semester_id'] ?? $student?->semester_id ?? 7,
        ];
        $defaults = array_merge($unique, [
            'start_date' => now()->subDays(2)->toDateString(),
            'end_date' => now()->addDays(7)->toDateString(),
            'created_by' => 1,
            'created_by_guard' => 'web',
        ]);
        return RetakeApplicationPeriod::firstOrCreate($unique, array_merge($defaults, $overrides));
    }

    protected function makeReceiptFile(): UploadedFile
    {
        // Fake PDF (DomPDF MIME magic bytes uchun haqiqiy PDF kerak)
        $tmp = tempnam(sys_get_temp_dir(), 'rcpt') . '.pdf';
        file_put_contents($tmp, '%PDF-1.4' . str_repeat("\n%test", 100) . "\n%%EOF");
        return new UploadedFile($tmp, 'kvitansiya.pdf', 'application/pdf', null, true);
    }
}
