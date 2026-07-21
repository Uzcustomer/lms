<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dars jadvali tuzish (aSc Timetables uslubida).
 *
 * timetable_boards — bitta jadval "doskasi": o'quv yili + semestr (kuzgi/
 * bahorgi) + oqim manbai (reja/real) + panjara sozlamalari (kun/para/hafta).
 *
 * timetable_cards — dars kartochkalari: fan + oqim yoki guruhcha + turi
 * (ma'ruza/amaliy) + o'qituvchi + auditoriya. day/pair NULL bo'lsa —
 * joylashtirilmagan (yon panelda turadi), to'ldirilsa — jadvaldagi katak.
 * Har kartochka haftasiga 1 para dars; fanning haftalik parasi nechta
 * bo'lsa, shuncha kartochka yaratiladi.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('timetable_boards', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('academic_year', 20)->index();
            $table->string('semester_parity', 10)->default('kuzgi'); // kuzgi (toq) | bahorgi (juft)
            $table->string('kind', 10)->default('plan');             // plan | real (oqim manbai)
            $table->unsignedBigInteger('faculty_id')->nullable();
            $table->string('faculty_name')->nullable();
            $table->unsignedTinyInteger('days')->default(6);          // haftada kunlar
            $table->unsignedTinyInteger('pairs_per_day')->default(6); // kuniga paralar
            $table->unsignedTinyInteger('weeks')->default(18);        // semestr haftalari
            $table->string('status', 20)->default('draft');           // draft | approved
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('timetable_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('board_id')->constrained('timetable_boards')->cascadeOnDelete();
            $table->string('specialty_name')->index();
            $table->unsignedTinyInteger('course');
            $table->string('oqim_label', 50)->nullable();   // masalan "1-oqim"
            $table->string('lang', 10)->default('uz');
            $table->string('training_type', 20);            // lecture | practice
            $table->string('group_name')->nullable();       // amaliy: guruhcha nomi; ma'ruza: NULL
            $table->json('group_names')->nullable();        // ma'ruza: oqimdagi barcha guruhchalar
            $table->string('subject_name');
            $table->string('kafedra_name')->nullable();
            $table->unsignedSmallInteger('students')->default(0);
            $table->unsignedBigInteger('teacher_id')->nullable();
            $table->string('teacher_name')->nullable();
            $table->string('auditorium_code', 50)->nullable();
            $table->string('auditorium_name')->nullable();
            $table->unsignedTinyInteger('day')->nullable();  // 1..days (NULL = joylashtirilmagan)
            $table->unsignedTinyInteger('pair')->nullable(); // 1..pairs_per_day
            $table->timestamps();

            $table->index(['board_id', 'day', 'pair']);
            $table->index(['board_id', 'specialty_name', 'course']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timetable_cards');
        Schema::dropIfExists('timetable_boards');
    }
};
