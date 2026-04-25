<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_photos', function (Blueprint $table) {
            $table->unsignedBigInteger('uploaded_by_teacher_id')->nullable()->after('uploaded_by');
            $table->index('uploaded_by_teacher_id');
        });
    }

    public function down(): void
    {
        Schema::table('student_photos', function (Blueprint $table) {
            $table->dropIndex(['uploaded_by_teacher_id']);
            $table->dropColumn('uploaded_by_teacher_id');
        });
    }
};
