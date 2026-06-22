<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('test_subjects', function (Blueprint $table) {
            $table->unsignedBigInteger('faculty_hemis_id')->nullable()->after('name')->index();
            $table->string('faculty_name')->nullable()->after('faculty_hemis_id');
        });
    }

    public function down(): void
    {
        Schema::table('test_subjects', function (Blueprint $table) {
            $table->dropColumn(['faculty_hemis_id', 'faculty_name']);
        });
    }
};
