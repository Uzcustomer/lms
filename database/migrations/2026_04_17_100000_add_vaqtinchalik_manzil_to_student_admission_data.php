<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_admission_data', function (Blueprint $table) {
            $table->string('vaqtinchalik_manzil')->nullable()->after('yashash_manzil');
            $table->string('talim_davlat')->nullable()->after('ortalacha_ball');
            $table->string('talim_viloyat')->nullable()->after('talim_davlat');
            $table->string('talim_tuman')->nullable()->after('talim_viloyat');
            $table->string('oqigan_yili_boshi', 4)->nullable()->after('talim_tuman');
            $table->string('oqigan_yili_tugashi', 4)->nullable()->after('oqigan_yili_boshi');
        });
    }

    public function down(): void
    {
        Schema::table('student_admission_data', function (Blueprint $table) {
            $table->dropColumn([
                'vaqtinchalik_manzil', 'talim_davlat', 'talim_viloyat',
                'talim_tuman', 'oqigan_yili_boshi', 'oqigan_yili_tugashi',
            ]);
        });
    }
};
