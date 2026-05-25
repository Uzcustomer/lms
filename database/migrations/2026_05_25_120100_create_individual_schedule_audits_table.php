<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Individual imtihon sanasi qo'yish/o'chirish bo'yicha audit jurnali.
 * Har bir o'zgarish (set/update/clear) yoziladi: kim, qachon, qaysi
 * talabaga, qaysi fan/urinish, eski va yangi qiymat, izoh.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('individual_schedule_audits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('actor_user_id')->nullable()->comment('Kim qo\'shgan/o\'zgartirgan');
            $table->string('actor_guard', 32)->nullable()->comment('web|teacher');
            $table->string('actor_name')->nullable()->comment('Aktyor F.I.Sh. snapshot');
            $table->string('actor_role', 64)->nullable()->comment('Aktyor roli');
            $table->string('student_hemis_id', 32)->index();
            $table->string('student_name')->nullable();
            $table->string('group_hemis_id', 32)->index();
            $table->string('subject_id', 32)->index();
            $table->string('subject_name')->nullable();
            $table->string('semester_code', 32)->nullable();
            $table->unsignedTinyInteger('attempt')->comment('1, 2 yoki 3');
            $table->string('yn_type', 8)->comment('oski yoki test');
            $table->string('action', 16)->comment('set | update | clear');
            $table->date('old_date')->nullable();
            $table->time('old_time')->nullable();
            $table->date('new_date')->nullable();
            $table->time('new_time')->nullable();
            $table->text('note')->nullable();
            $table->boolean('override_warning')->default(false)
                ->comment('Eligibility OK bo\'lmasa ham majburan qo\'yilganmi');
            $table->json('eligibility_snapshot')->nullable()
                ->comment('Audit vaqtidagi eligibility holati (JN/MT, qarz, V, ...)');
            $table->timestamps();

            $table->index(['student_hemis_id', 'subject_id', 'semester_code'], 'isa_student_subj_sem');
            $table->index('actor_user_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('individual_schedule_audits');
    }
};
