<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_admission_data', function (Blueprint $table) {
            // Qo'shimcha tug'ilgan/yashash/ta'lim joy "_text" maydonlari
            // (manual kiritilgan viloyat/tuman matnlari)
            $table->string('millat_other')->nullable()->after('millat');
            $table->string('tugilgan_viloyat_text')->nullable()->after('tugilgan_viloyat');
            $table->string('tugilgan_tuman_text')->nullable()->after('tugulgan_tuman');
            $table->string('yashash_viloyat_text')->nullable()->after('yashash_viloyat');
            $table->string('yashash_tuman_text')->nullable()->after('yashash_tuman');
            $table->string('talim_viloyat_text')->nullable()->after('talim_viloyat');
            $table->string('talim_tuman_text')->nullable()->after('talim_tuman');

            // Geografik koordinata (yashash manzili uchun)
            $table->string('kenglik', 30)->nullable()->after('vaqtinchalik_manzil');
            $table->string('uzunlik', 30)->nullable()->after('kenglik');

            // Avval tugatgan OTM
            $table->string('prev_otm_nomi')->nullable()->after('oliy_malumot');

            // Muassasa turi (O'rta maktab / Litsey / Kollej va h.k.)
            $table->string('muassasa_turi')->nullable()->after('muassasa_nomi');

            // Massivlar (JSON ustunlar)
            $table->json('chet_tillari')->nullable()->after('chet_til_ball');
            $table->string('chet_til_boshqa')->nullable()->after('chet_tillari');

            // ─── IMTIYOZLAR / MUKOFOTLAR ─────────────────────────────────
            // "0"/"1" string formatda saqlanadi (JSON dan kelganidek)

            // Davlatga kiritilgan
            $table->string('d_kiritilgan', 2)->nullable();
            $table->string('d_kiritilgan_turi')->nullable();

            // Davlatga kiritilgan oila a'zosi
            $table->string('d_oila_azosi', 2)->nullable();
            $table->string('d_oila_turi')->nullable();

            // Boshqa imtiyozlar
            $table->string('kam_taminlangan', 2)->nullable();
            $table->string('harbiy_qaytgan', 2)->nullable();
            $table->string('nafaqa_oluvchi', 2)->nullable();

            // Nogironlik
            $table->string('nogironligi', 2)->nullable();
            $table->string('nogiron_guruh', 10)->nullable();
            $table->string('nogiron_toifa')->nullable();
            $table->string('nogiron_toifa_boshqa')->nullable();

            // Yetim talaba
            $table->string('yetim_talaba', 2)->nullable();
            $table->string('yetim_turi')->nullable();

            // Davlat mukofoti
            $table->string('davlat_mukofoti', 2)->nullable();
            $table->text('davlat_mukofoti_desc')->nullable();

            // Ko'krak nishoni
            $table->string('kokrak_nishoni', 2)->nullable();
            $table->text('kokrak_nishoni_desc')->nullable();

            // Stipendiyalar
            $table->string('prezident_stip', 2)->nullable();
            $table->text('prezident_stip_desc')->nullable();
            $table->string('davlat_stip', 2)->nullable();
            $table->text('davlat_stip_desc')->nullable();
            $table->string('xalqaro_stip', 2)->nullable();
            $table->text('xalqaro_stip_desc')->nullable();

            // Sport yutuqlari
            $table->string('resp_sport', 2)->nullable();
            $table->text('resp_sport_desc')->nullable();
            $table->string('xal_sport', 2)->nullable();
            $table->text('xal_sport_desc')->nullable();

            // Fan olimpiadalari
            $table->string('resp_fan_olimp', 2)->nullable();
            $table->text('resp_fan_olimp_desc')->nullable();
            $table->string('xal_fan_olimp', 2)->nullable();
            $table->text('xal_fan_olimp_desc')->nullable();

            // Boshqa yutuq
            $table->string('boshqa_yutuq', 2)->nullable();
            $table->text('boshqa_yutuq_desc')->nullable();

            // ─── QOBILIYAT VA QIZIQISH ───────────────────────────────────
            $table->json('iqtidori')->nullable();
            $table->string('iqtidori_boshqa')->nullable();
            $table->json('sport_qobiliyat')->nullable();
            $table->string('sport_qobiliyat_boshqa')->nullable();
            $table->string('sport_boshqa')->nullable();

            // ─── METADATA (import izi) ───────────────────────────────────
            $table->string('application_number', 20)->nullable()->index();
            $table->timestamp('admission_submitted_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('student_admission_data', function (Blueprint $table) {
            $table->dropColumn([
                'millat_other',
                'tugilgan_viloyat_text', 'tugilgan_tuman_text',
                'yashash_viloyat_text', 'yashash_tuman_text',
                'talim_viloyat_text', 'talim_tuman_text',
                'kenglik', 'uzunlik',
                'prev_otm_nomi', 'muassasa_turi',
                'chet_tillari', 'chet_til_boshqa',
                'd_kiritilgan', 'd_kiritilgan_turi',
                'd_oila_azosi', 'd_oila_turi',
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
                'sport_qobiliyat', 'sport_qobiliyat_boshqa', 'sport_boshqa',
                'application_number', 'admission_submitted_at',
            ]);
        });
    }
};
