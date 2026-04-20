<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('graduate_student_passports', function (Blueprint $table) {
            $table->string('first_name')->nullable()->after('student_id');
            $table->string('last_name')->nullable()->after('first_name');
            $table->string('father_name')->nullable()->after('last_name');
            $table->string('first_name_en')->nullable()->after('father_name');
            $table->string('last_name_en')->nullable()->after('first_name_en');
        });
    }

    public function down(): void
    {
        Schema::table('graduate_student_passports', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'last_name', 'father_name', 'first_name_en', 'last_name_en']);
        });
    }
};
