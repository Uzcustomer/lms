<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dars birligiga o'qituvchi/xona biriktirishlari — kartalardan alohida.
 * Kartochkalar qayta yaratilganda (o'chirilib qayta qo'shiladi) biriktirishlar
 * shu jadvaldan tiklanadi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timetable_unit_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('board_id')->index();
            $table->string('unit_key', 40);              // sha1(fakultet|yo'nalish|kurs|fan|tur|oqim/guruh)
            $table->string('faculty_name')->nullable();
            $table->string('specialty_name');
            $table->unsignedTinyInteger('course');
            $table->string('subject_name');
            $table->string('training_type', 20);
            $table->string('scope_label')->nullable();   // ma'ruza — oqim, amaliy — guruh
            $table->unsignedBigInteger('teacher_id')->nullable();
            $table->string('teacher_name')->nullable();
            $table->string('auditorium_code', 50)->nullable();
            $table->string('auditorium_name')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['board_id', 'unit_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timetable_unit_assignments');
    }
};
