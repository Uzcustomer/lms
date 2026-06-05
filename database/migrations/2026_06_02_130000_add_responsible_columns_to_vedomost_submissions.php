<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vedomost_submissions', function (Blueprint $table) {
            $table->string('teacher_phone')->nullable()->after('teacher_name');

            $table->unsignedBigInteger('fan_masuli_hemis_id')->nullable()->after('teacher_phone');
            $table->string('fan_masuli_name')->nullable()->after('fan_masuli_hemis_id');
            $table->string('fan_masuli_phone')->nullable()->after('fan_masuli_name');

            $table->unsignedBigInteger('kafedra_mudiri_hemis_id')->nullable()->after('fan_masuli_phone');
            $table->string('kafedra_mudiri_name')->nullable()->after('kafedra_mudiri_hemis_id');
            $table->string('kafedra_mudiri_phone')->nullable()->after('kafedra_mudiri_name');
        });
    }

    public function down(): void
    {
        Schema::table('vedomost_submissions', function (Blueprint $table) {
            $table->dropColumn([
                'teacher_phone',
                'fan_masuli_hemis_id',
                'fan_masuli_name',
                'fan_masuli_phone',
                'kafedra_mudiri_hemis_id',
                'kafedra_mudiri_name',
                'kafedra_mudiri_phone',
            ]);
        });
    }
};
