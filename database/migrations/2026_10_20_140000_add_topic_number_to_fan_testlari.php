<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Test to'plamining mavzu raqami (KTR bo'yicha: 1-mavzu, 2-mavzu ...).
 * Mavjud to'plamlarda bo'sh — o'qituvchi tahrirlab qo'yib chiqadi.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('fan_testlari', 'topic_number')) {
            return;
        }
        Schema::table('fan_testlari', function (Blueprint $table) {
            $table->unsignedSmallInteger('topic_number')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('fan_testlari', function (Blueprint $table) {
            $table->dropColumn('topic_number');
        });
    }
};
