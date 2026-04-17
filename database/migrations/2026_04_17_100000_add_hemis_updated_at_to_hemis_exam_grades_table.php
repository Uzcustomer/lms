<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hemis_exam_grades', function (Blueprint $table) {
            $table->unsignedBigInteger('hemis_updated_at')->nullable()->after('exam_schedule_id');
        });
    }

    public function down(): void
    {
        Schema::table('hemis_exam_grades', function (Blueprint $table) {
            $table->dropColumn('hemis_updated_at');
        });
    }
};
