<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // teachers jadvaliga eval_qr_token qo'shish
        Schema::table('teachers', function (Blueprint $table) {
            $table->string('eval_qr_token', 64)->nullable()->unique()->after('assigned_firm');
        });

        // staff_evaluations jadvalini teacher_id ga o'zgartirish
        Schema::table('staff_evaluations', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->renameColumn('user_id', 'teacher_id');
        });

        Schema::table('staff_evaluations', function (Blueprint $table) {
            $table->foreign('teacher_id')->references('id')->on('teachers')->cascadeOnDelete();
        });

        // users dan eval_qr_token ni olib tashlash
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('eval_qr_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('eval_qr_token', 64)->nullable()->unique()->after('telegram_chat_id');
        });

        Schema::table('staff_evaluations', function (Blueprint $table) {
            $table->dropForeign(['teacher_id']);
            $table->renameColumn('teacher_id', 'user_id');
        });

        Schema::table('staff_evaluations', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('teachers', function (Blueprint $table) {
            $table->dropColumn('eval_qr_token');
        });
    }
};
