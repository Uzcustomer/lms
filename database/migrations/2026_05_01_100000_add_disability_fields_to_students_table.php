<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('students', 'disability_duration')) {
            Schema::table('students', function (Blueprint $table) {
                $table->dropColumn('disability_duration');
            });
        }

        if (!Schema::hasTable('student_disability_infos')) {
            Schema::create('student_disability_infos', function (Blueprint $table) {
                $table->id();
                $table->foreignId('student_id')->unique()->constrained('students')->cascadeOnDelete();
                $table->date('examined_at')->nullable();
                $table->string('disability_group', 50)->nullable();
                $table->string('disability_reason', 500)->nullable();
                $table->string('disability_duration', 100)->nullable();
                $table->date('reexamination_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('student_disability_infos');
    }
};
