<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_admission_data', function (Blueprint $table) {
            $table->string('vaqtinchalik_manzil')->nullable()->after('yashash_manzil');
        });
    }

    public function down(): void
    {
        Schema::table('student_admission_data', function (Blueprint $table) {
            $table->dropColumn('vaqtinchalik_manzil');
        });
    }
};
