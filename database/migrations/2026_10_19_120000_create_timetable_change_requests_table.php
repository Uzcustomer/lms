<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Dars jadvali o'zgarishlari: doskada "tasdiq talab qilinadi" yoqilgan bo'lsa,
 * kafedra mudirining o'zgarishi darhol qo'llanmaydi — registrator ofisi tasdiqlagach
 * qo'llanadi va tegishli talaba/o'qituvchi/bo'limlarga xabar boradi. Tasdiqsiz
 * qo'llangan o'zgarishlar ham tarix uchun "applied" holatida yoziladi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timetable_change_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('board_id')->index();
            $table->unsignedBigInteger('requester_id')->nullable()->index();   // teachers.id yoki users.id
            $table->string('requester_type')->nullable();                     // Teacher | User (morph)
            $table->string('requester_name')->nullable();
            $table->string('requester_role', 40)->nullable();                 // o'zgartirgan paytdagi faol rol
            $table->string('kafedra_name')->nullable();
            $table->string('route_name', 100);
            $table->string('target_key', 191)->index();   // bir xil joyga qayta so'rov — eskisini almashtiradi
            $table->json('params')->nullable();            // route parametrlari (board, card)
            $table->json('payload');                       // asl so'rov ma'lumoti
            $table->string('kind', 40);                    // hour | move | cycle | teacher | room | unplace
            $table->string('subject_name')->nullable();
            $table->text('summary');                       // odam o'qiydigan tavsif
            $table->json('details')->nullable();           // guruhlar, fakultet, o'qituvchi ...
            $table->string('status', 20)->default('pending')->index();   // pending|approved|rejected|failed|cancelled|applied
            $table->unsignedBigInteger('reviewer_id')->nullable();
            $table->string('reviewer_name')->nullable();
            $table->text('review_note')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timetable_change_requests');
    }
};
