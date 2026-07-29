<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auditoriums', function (Blueprint $table) {
            $table->unsignedBigInteger('department_hemis_id')->nullable()->after('auditorium_type_name')->index();
            $table->string('department_name')->nullable()->after('department_hemis_id');
            $table->unsignedBigInteger('created_by_teacher_id')->nullable()->after('department_name')->index();
        });
    }

    public function down(): void
    {
        Schema::table('auditoriums', function (Blueprint $table) {
            $table->dropIndex(['department_hemis_id']);
            $table->dropIndex(['created_by_teacher_id']);
            $table->dropColumn(['department_hemis_id', 'department_name', 'created_by_teacher_id']);
        });
    }
};
