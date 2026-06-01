<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentAdmissionData extends Model
{
    protected $table = 'student_admission_data';

    protected $fillable = [
        'student_id',
        'familya', 'ism', 'otasining_ismi', 'tugilgan_sana', 'jshshir', 'jinsi',
        'tel1', 'tel2', 'email', 'millat', 'millat_other',
        'tugilgan_davlat', 'tugilgan_viloyat', 'tugulgan_tuman',
        'tugilgan_viloyat_text', 'tugilgan_tuman_text',
        'doimiy_manzil',
        'yashash_davlat', 'yashash_viloyat', 'yashash_tuman', 'yashash_manzil',
        'yashash_viloyat_text', 'yashash_tuman_text',
        'vaqtinchalik_manzil', 'kenglik', 'uzunlik',
        'passport_seriya', 'passport_raqam', 'passport_sana', 'passport_joy',
        'abituriyent_id', 'javoblar_varaqasi', 'talim_tili', 'imtihon_alifbosi',
        'oliy_malumot', 'prev_otm_nomi', 'otm_nomi',
        'talim_turi', 'talim_shakli', 'mutaxassislik', 'hozirgi_talim_turi',
        'toplagan_ball', 'tavsiya_turi', 'tolov_shakli',
        'muassasa_nomi', 'muassasa_turi', 'hujjat_seriya', 'ortalacha_ball',
        'talim_davlat', 'talim_viloyat', 'talim_tuman',
        'talim_viloyat_text', 'talim_tuman_text',
        'oqigan_yili_boshi', 'oqigan_yili_tugashi',
        'sertifikat_turi', 'sertifikat_ball', 'milliy_sertifikat',
        'chet_til_sertifikat', 'chet_til_ball', 'chet_tillari', 'chet_til_boshqa',
        'ota_familiya', 'ota_ismi', 'ota_sharifi', 'ota_tel', 'ota_ish_joyi', 'ota_lavozimi',
        'ona_familiya', 'ona_ismi', 'ona_sharifi', 'ona_tel', 'ona_ish_joyi', 'ona_lavozimi',
        // Imtiyozlar va mukofotlar
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
        // Qobiliyat va qiziqish
        'iqtidori', 'iqtidori_boshqa', 'sport_qobiliyat', 'sport_qobiliyat_boshqa', 'sport_boshqa',
        // Metadata
        'application_number', 'admission_submitted_at',
        'updated_by',
    ];

    protected $casts = [
        'tugilgan_sana' => 'date',
        'passport_sana' => 'date',
        'admission_submitted_at' => 'datetime',
        'chet_tillari' => 'array',
        'iqtidori' => 'array',
        'sport_qobiliyat' => 'array',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}
