<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_admission_data', function (Blueprint $table) {
            $table->string('chet_til_sertifikat', 30)->nullable()->after('milliy_sertifikat');
            $table->string('chet_til_ball', 10)->nullable()->after('chet_til_sertifikat');
        });
    }

    public function down(): void
    {
        Schema::table('student_admission_data', function (Blueprint $table) {
            $table->dropColumn(['chet_til_sertifikat', 'chet_til_ball']);
        });
    }
};
