<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_admission_data', function (Blueprint $table) {
            $table->string('abituriyent_id', 20)->nullable()->after('passport_joy');
            $table->string('javoblar_varaqasi', 20)->nullable()->after('abituriyent_id');
            $table->string('talim_tili', 20)->nullable()->after('javoblar_varaqasi');
            $table->string('imtihon_alifbosi', 10)->nullable()->after('talim_tili');
            $table->string('tavsiya_turi')->nullable()->after('toplagan_ball');
        });
    }

    public function down(): void
    {
        Schema::table('student_admission_data', function (Blueprint $table) {
            $table->dropColumn(['abituriyent_id', 'javoblar_varaqasi', 'talim_tili', 'imtihon_alifbosi', 'tavsiya_turi']);
        });
    }
};
