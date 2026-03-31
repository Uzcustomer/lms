<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_visa_infos', function (Blueprint $table) {
            $table->date('passport_issued_date')->nullable()->after('passport_number');
        });
    }

    public function down(): void
    {
        Schema::table('student_visa_infos', function (Blueprint $table) {
            $table->dropColumn('passport_issued_date');
        });
    }
};
