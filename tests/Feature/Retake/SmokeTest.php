<?php

namespace Tests\Feature\Retake;

it('sets up retake schema and roles', function () {
    expect(\Spatie\Permission\Models\Role::count())->toBe(8);
    expect(\Illuminate\Support\Facades\Schema::hasTable('retake_applications'))->toBeTrue();
    expect(\Illuminate\Support\Facades\Schema::hasTable('retake_application_periods'))->toBeTrue();
    expect(\Illuminate\Support\Facades\Schema::hasTable('retake_groups'))->toBeTrue();
    expect(\Illuminate\Support\Facades\Schema::hasTable('academic_records'))->toBeTrue();
});

it('makeStudent creates student with talaba role', function () {
    $student = $this->makeStudent();
    expect($student->hasRole('talaba'))->toBeTrue();
    expect($student->department_id)->toBe(100);
});

it('makeTeacher creates dean with faculty', function () {
    $teacher = $this->makeTeacher(role: 'dekan', facultyIds: [100, 200]);
    expect($teacher->hasRole('dekan'))->toBeTrue();
    expect(\Illuminate\Support\Facades\DB::table('dean_faculties')
        ->where('teacher_id', $teacher->id)
        ->count())->toBe(2);
});
