<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('face_id_logs', function (Blueprint $table) {
            $table->string('attempt_type', 30)->nullable()->after('student_id_number')->index();
            $table->unsignedBigInteger('target_student_id')->nullable()->after('student_id')->index();
            $table->string('target_student_id_number', 50)->nullable()->after('attempt_type')->index();

            $table->foreign('target_student_id')
                ->references('id')
                ->on('students')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('face_id_logs', function (Blueprint $table) {
            $table->dropForeign(['target_student_id']);
            $table->dropColumn([
                'attempt_type',
                'target_student_id',
                'target_student_id_number',
            ]);
        });
    }
};
