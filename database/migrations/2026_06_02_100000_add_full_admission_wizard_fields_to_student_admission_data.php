<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_admission_data', function (Blueprint $table) {
            if (!Schema::hasColumn('student_admission_data', 'millat_other')) {
                $table->string('millat_other')->nullable()->after('millat');
            }
            if (!Schema::hasColumn('student_admission_data', 'tugilgan_viloyat_text')) {
                $table->string('tugilgan_viloyat_text')->nullable()->after('tugulgan_tuman');
            }
            if (!Schema::hasColumn('student_admission_data', 'tugilgan_tuman_text')) {
                $table->string('tugilgan_tuman_text')->nullable()->after('tugilgan_viloyat_text');
            }
            if (!Schema::hasColumn('student_admission_data', 'talim_viloyat_text')) {
                $table->string('talim_viloyat_text')->nullable()->after('talim_tuman');
            }
            if (!Schema::hasColumn('student_admission_data', 'talim_tuman_text')) {
                $table->string('talim_tuman_text')->nullable()->after('talim_viloyat_text');
            }
            if (!Schema::hasColumn('student_admission_data', 'yashash_viloyat_text')) {
                $table->string('yashash_viloyat_text')->nullable()->after('yashash_manzil');
            }
            if (!Schema::hasColumn('student_admission_data', 'yashash_tuman_text')) {
                $table->string('yashash_tuman_text')->nullable()->after('yashash_viloyat_text');
            }
            if (!Schema::hasColumn('student_admission_data', 'kenglik')) {
                $table->decimal('kenglik', 10, 6)->nullable()->after('yashash_tuman_text');
            }
            if (!Schema::hasColumn('student_admission_data', 'uzunlik')) {
                $table->decimal('uzunlik', 10, 6)->nullable()->after('kenglik');
            }
            if (!Schema::hasColumn('student_admission_data', 'muassasa_turi')) {
                $table->string('muassasa_turi')->nullable()->after('muassasa_nomi');
            }
            if (!Schema::hasColumn('student_admission_data', 'prev_otm_nomi')) {
                $table->string('prev_otm_nomi')->nullable()->after('otm_nomi');
            }
            if (!Schema::hasColumn('student_admission_data', 'chet_tillari')) {
                $table->json('chet_tillari')->nullable()->after('chet_til_ball');
            }
            if (!Schema::hasColumn('student_admission_data', 'chet_til_boshqa')) {
                $table->string('chet_til_boshqa')->nullable()->after('chet_tillari');
            }

            if (!Schema::hasColumn('student_admission_data', 'd_kiritilgan')) {
                $table->boolean('d_kiritilgan')->default(false);
            }
            if (!Schema::hasColumn('student_admission_data', 'd_kiritilgan_turi')) {
                $table->string('d_kiritilgan_turi')->nullable();
            }
            if (!Schema::hasColumn('student_admission_data', 'd_oila_azosi')) {
                $table->boolean('d_oila_azosi')->default(false);
            }
            if (!Schema::hasColumn('student_admission_data', 'd_oila_turi')) {
                $table->string('d_oila_turi')->nullable();
            }
            if (!Schema::hasColumn('student_admission_data', 'kam_taminlangan')) {
                $table->boolean('kam_taminlangan')->default(false);
            }
            if (!Schema::hasColumn('student_admission_data', 'harbiy_qaytgan')) {
                $table->boolean('harbiy_qaytgan')->default(false);
            }
            if (!Schema::hasColumn('student_admission_data', 'nafaqa_oluvchi')) {
                $table->boolean('nafaqa_oluvchi')->default(false);
            }
            if (!Schema::hasColumn('student_admission_data', 'nogironligi')) {
                $table->boolean('nogironligi')->default(false);
            }
            if (!Schema::hasColumn('student_admission_data', 'nogiron_guruh')) {
                $table->string('nogiron_guruh')->nullable();
            }
            if (!Schema::hasColumn('student_admission_data', 'nogiron_toifa')) {
                $table->string('nogiron_toifa')->nullable();
            }
            if (!Schema::hasColumn('student_admission_data', 'nogiron_toifa_boshqa')) {
                $table->string('nogiron_toifa_boshqa')->nullable();
            }
            if (!Schema::hasColumn('student_admission_data', 'yetim_talaba')) {
                $table->boolean('yetim_talaba')->default(false);
            }
            if (!Schema::hasColumn('student_admission_data', 'yetim_turi')) {
                $table->string('yetim_turi')->nullable();
            }

            $achievements = [
                'davlat_mukofoti', 'kokrak_nishoni', 'prezident_stip', 'davlat_stip',
                'xalqaro_stip', 'resp_sport', 'xal_sport', 'resp_fan_olimp',
                'xal_fan_olimp', 'boshqa_yutuq',
            ];
            foreach ($achievements as $a) {
                if (!Schema::hasColumn('student_admission_data', $a)) {
                    $table->boolean($a)->default(false);
                }
                if (!Schema::hasColumn('student_admission_data', $a.'_desc')) {
                    $table->text($a.'_desc')->nullable();
                }
            }

            if (!Schema::hasColumn('student_admission_data', 'iqtidori')) {
                $table->json('iqtidori')->nullable();
            }
            if (!Schema::hasColumn('student_admission_data', 'iqtidori_boshqa')) {
                $table->string('iqtidori_boshqa')->nullable();
            }
            if (!Schema::hasColumn('student_admission_data', 'sport_qobiliyat')) {
                $table->json('sport_qobiliyat')->nullable();
            }
            if (!Schema::hasColumn('student_admission_data', 'sport_boshqa')) {
                $table->string('sport_boshqa')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('student_admission_data', function (Blueprint $table) {
            $cols = [
                'millat_other',
                'tugilgan_viloyat_text', 'tugilgan_tuman_text',
                'talim_viloyat_text', 'talim_tuman_text',
                'yashash_viloyat_text', 'yashash_tuman_text',
                'kenglik', 'uzunlik',
                'muassasa_turi', 'prev_otm_nomi',
                'chet_tillari', 'chet_til_boshqa',
                'd_kiritilgan', 'd_kiritilgan_turi', 'd_oila_azosi', 'd_oila_turi',
                'kam_taminlangan', 'harbiy_qaytgan', 'nafaqa_oluvchi',
                'nogironligi', 'nogiron_guruh', 'nogiron_toifa', 'nogiron_toifa_boshqa',
                'yetim_talaba', 'yetim_turi',
                'davlat_mukofoti', 'davlat_mukofoti_desc',
                'kokrak_nishoni', 'kokrak_nishoni_desc',
                'prezident_stip', 'prezident_stip_desc',
                'davlat_stip', 'davlat_stip_desc',
                'xalqaro_stip', 'xalqaro_stip_desc',
                'resp_sport', 'resp_sport_desc',
                'xal_sport', 'xal_sport_desc',
                'resp_fan_olimp', 'resp_fan_olimp_desc',
                'xal_fan_olimp', 'xal_fan_olimp_desc',
                'boshqa_yutuq', 'boshqa_yutuq_desc',
                'iqtidori', 'iqtidori_boshqa',
                'sport_qobiliyat', 'sport_boshqa',
            ];
            foreach ($cols as $c) {
                if (Schema::hasColumn('student_admission_data', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
