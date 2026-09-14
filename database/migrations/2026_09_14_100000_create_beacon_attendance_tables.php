<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Beacon-based attendance ("elektron davomat").
 *
 *  beacons                   — BLE beacon ↔ auditorium mapping
 *  device_tokens             — FCM push tokens for student/teacher phones
 *  presence_logs             — "student X heard beacon Y at T" (background reports)
 *  attendance_sessions       — a teacher opened attendance for one lesson slot
 *  attendance_session_groups — HEMIS groups covered by a session (lectures span several)
 *  attendance_confirmations  — per-student result inside a session
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beacons', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36);
            $table->unsignedInteger('major');
            $table->unsignedInteger('minor');
            $table->string('auditorium_code')->nullable()->index();
            $table->string('auditorium_name')->nullable();
            $table->string('label')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['uuid', 'major', 'minor']);
        });

        Schema::create('device_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('owner_type', 16); // student | teacher
            $table->unsignedBigInteger('owner_id');
            $table->string('token', 255)->unique();
            $table->string('platform', 16)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->index(['owner_type', 'owner_id']);
        });

        Schema::create('presence_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('beacon_id')->constrained()->cascadeOnDelete();
            $table->smallInteger('rssi')->nullable();
            $table->dateTime('seen_at');
            $table->string('source', 16)->default('background'); // background | foreground | confirm
            $table->timestamps();

            $table->index(['beacon_id', 'seen_at']);
            $table->index(['student_id', 'seen_at']);
        });

        Schema::create('attendance_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('teacher_hemis_id');
            $table->unsignedBigInteger('subject_id');
            $table->string('subject_name');
            $table->string('semester_code')->nullable();
            $table->date('lesson_date');
            $table->string('lesson_pair_code')->nullable();
            $table->string('lesson_pair_name')->nullable();
            $table->string('training_type_name')->nullable();
            $table->string('auditorium_code')->nullable();
            $table->string('auditorium_name')->nullable();
            $table->foreignId('beacon_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('opened_at');
            $table->dateTime('closes_at');
            $table->dateTime('closed_at')->nullable();
            $table->string('status', 16)->default('open'); // open | closed
            $table->timestamps();

            $table->index(['lesson_date', 'status']);
            $table->index(['teacher_id', 'lesson_date']);
        });

        Schema::create('attendance_session_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('attendance_sessions')->cascadeOnDelete();
            $table->unsignedBigInteger('group_hemis_id');
            $table->string('group_name')->nullable();

            $table->unique(['session_id', 'group_hemis_id']);
            $table->index('group_hemis_id');
        });

        Schema::create('attendance_confirmations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('attendance_sessions')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('student_hemis_id');
            $table->string('status', 16)->default('pending'); // pending | present | absent
            $table->string('decided_by', 16)->nullable();     // student | teacher | system
            $table->boolean('beacon_seen')->default(false);
            $table->smallInteger('rssi')->nullable();
            $table->boolean('notified')->default(false);
            $table->dateTime('confirmed_at')->nullable();
            $table->timestamps();

            $table->unique(['session_id', 'student_id']);
            $table->index(['student_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_confirmations');
        Schema::dropIfExists('attendance_session_groups');
        Schema::dropIfExists('attendance_sessions');
        Schema::dropIfExists('presence_logs');
        Schema::dropIfExists('device_tokens');
        Schema::dropIfExists('beacons');
    }
};
