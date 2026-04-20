<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_visa_infos', function (Blueprint $table) {
            $table->date('entry_date')->nullable()->after('visa_issued_date');
        });
    }

    public function down(): void
    {
        Schema::table('student_visa_infos', function (Blueprint $table) {
            $table->dropColumn('entry_date');
        });
    }
};
