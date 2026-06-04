<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentAdmissionData extends Model
{
    protected $table = 'student_admission_data';

    protected $fillable = [
        'student_id',
        'application_number', 'submitted_at', 'files',
        'full_name',
        'familya', 'ism', 'otasining_ismi', 'tugilgan_sana', 'jshshir', 'jinsi',
        'tel1', 'tel2', 'email', 'millat', 'millat_other',
        'tugilgan_davlat', 'tugilgan_viloyat', 'tugulgan_tuman',
        'tugilgan_viloyat_text', 'tugilgan_tuman_text',
        'doimiy_manzil',
        'yashash_davlat', 'yashash_viloyat', 'yashash_tuman', 'yashash_manzil',
        'yashash_viloyat_text', 'yashash_tuman_text',
        'vaqtinchalik_manzil',
        'kenglik', 'uzunlik',
        'passport_seriya', 'passport_raqam', 'passport_sana', 'passport_joy',
        'abituriyent_id', 'javoblar_varaqasi', 'talim_tili', 'imtihon_alifbosi',
        'oliy_malumot', 'otm_nomi', 'prev_otm_nomi',
        'talim_turi', 'talim_shakli', 'mutaxassislik', 'hozirgi_talim_turi',
        'toplagan_ball', 'tavsiya_turi', 'tolov_shakli',
        'muassasa_nomi', 'muassasa_turi', 'hujjat_seriya', 'ortalacha_ball',
        'talim_davlat', 'talim_viloyat', 'talim_tuman', 'talim_viloyat_text', 'talim_tuman_text',
        'oqigan_yili_boshi', 'oqigan_yili_tugashi',
        'sertifikat_turi', 'sertifikat_ball', 'milliy_sertifikat',
        'chet_til_sertifikat', 'chet_til_ball', 'chet_tillari', 'chet_til_boshqa',
        'ota_familiya', 'ota_ismi', 'ota_sharifi', 'ota_tel', 'ota_ish_joyi', 'ota_lavozimi',
        'ona_familiya', 'ona_ismi', 'ona_sharifi', 'ona_tel', 'ona_ish_joyi', 'ona_lavozimi',
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
        'updated_by',
    ];

    protected $casts = [
        'tugilgan_sana' => 'date',
        'passport_sana' => 'date',
        'submitted_at' => 'datetime',
        'files' => 'array',
        'kenglik' => 'decimal:6',
        'uzunlik' => 'decimal:6',
        'chet_tillari' => 'array',
        'iqtidori' => 'array',
        'sport_qobiliyat' => 'array',
        'd_kiritilgan' => 'boolean',
        'd_oila_azosi' => 'boolean',
        'kam_taminlangan' => 'boolean',
        'harbiy_qaytgan' => 'boolean',
        'nafaqa_oluvchi' => 'boolean',
        'nogironligi' => 'boolean',
        'yetim_talaba' => 'boolean',
        'davlat_mukofoti' => 'boolean',
        'kokrak_nishoni' => 'boolean',
        'prezident_stip' => 'boolean',
        'davlat_stip' => 'boolean',
        'xalqaro_stip' => 'boolean',
        'resp_sport' => 'boolean',
        'xal_sport' => 'boolean',
        'resp_fan_olimp' => 'boolean',
        'xal_fan_olimp' => 'boolean',
        'boshqa_yutuq' => 'boolean',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}
