<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HEMIS solishtirishdagi to'siqlar bo'yicha so'rovlar: o'qituvchi va kafedra mudiriga Telegram orqali yuboriladi,
 * javobi olinadi va faqat shu so'rov doirasida dars xonasi / vaqti / kuni o'zgartiriladi.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('timetable_block_tickets')) {
            return;
        }
        Schema::create('timetable_block_tickets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('board_id')->index();
            $table->unsignedBigInteger('card_id')->index();
            $table->unsignedSmallInteger('week');
            $table->date('lesson_date');
            $table->string('start_time', 5);
            $table->string('end_time', 5);
            $table->string('block_type', 20);            // room | teacher | other
            $table->text('block_text')->nullable();
            $table->json('blocker')->nullable();         // band qilgan dars: manba, guruh, fan, o'qituvchi, xona, vaqt
            $table->string('group_name')->nullable();
            $table->string('subject_name')->nullable();
            $table->string('kafedra_name')->nullable();
            $table->string('status', 30)->default('open')->index();   // open | resolved | pending_registrar | cancelled
            $table->unsignedBigInteger('teacher_id')->nullable();
            $table->unsignedBigInteger('head_id')->nullable();
            $table->json('recipients')->nullable();      // [{teacher_id, name, role, chat_id, message_id, response, note, at}]
            $table->json('resolution')->nullable();      // {kind, auditorium, day, pair, by, at}
            $table->unsignedBigInteger('change_request_id')->nullable();
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->string('created_by_name')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['card_id', 'week', 'block_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timetable_block_tickets');
    }
};
