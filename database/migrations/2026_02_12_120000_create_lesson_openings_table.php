<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_openings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('group_hemis_id');
            $table->unsignedBigInteger('subject_id');
            $table->string('semester_code');
            $table->date('lesson_date');
            $table->string('file_path')->nullable();
            $table->string('file_original_name')->nullable();
            $table->unsignedBigInteger('opened_by_id');
            $table->string('opened_by_name');
            $table->string('opened_by_guard')->default('teacher');
            $table->dateTime('deadline');
            $table->string('status')->default('active');
            $table->timestamps();

            $table->index(['group_hemis_id', 'subject_id', 'semester_code'], 'lo_group_subject_semester_idx');
            $table->index(['status', 'deadline']);
        });

        // Dars ochish uchun muddat sozlamasi (kunlar)
        DB::table('settings')->insertOrIgnore([
            'key' => 'lesson_opening_days',
            'value' => '3',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_openings');
        DB::table('settings')->where('key', 'lesson_opening_days')->delete();
    }
};
