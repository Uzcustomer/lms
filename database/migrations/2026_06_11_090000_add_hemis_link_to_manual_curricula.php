<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('manual_curricula', function (Blueprint $table) {
            // HEMIS'dagi o'quv reja bilan bog'lanish (cascade tanlov orqali)
            $table->unsignedBigInteger('curricula_hemis_id')->nullable()->index()->after('plan_year');
            $table->string('level_code')->nullable()->after('curricula_hemis_id');
            $table->string('semester_code')->nullable()->after('level_code');
            $table->string('education_type_name')->nullable()->after('semester_code');
        });
    }

    public function down(): void
    {
        Schema::table('manual_curricula', function (Blueprint $table) {
            $table->dropColumn(['curricula_hemis_id', 'level_code', 'semester_code', 'education_type_name']);
        });
    }
};
