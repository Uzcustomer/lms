<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentAdmissionData extends Model
{
    protected $table = 'student_admission_data';

    protected $fillable = [
        'student_id',
        'familya', 'ism', 'otasining_ismi', 'tugilgan_sana', 'jshshir', 'jinsi',
        'tel1', 'tel2', 'email', 'millat',
        'tugilgan_davlat', 'tugilgan_viloyat', 'tugulgan_tuman',
        'doimiy_manzil',
        'yashash_davlat', 'yashash_viloyat', 'yashash_tuman', 'yashash_manzil',
        'vaqtinchalik_manzil',
        'passport_seriya', 'passport_raqam', 'passport_sana', 'passport_joy',
        'abituriyent_id', 'javoblar_varaqasi', 'talim_tili', 'imtihon_alifbosi',
        'oliy_malumot', 'otm_nomi', 'talim_turi', 'talim_shakli', 'mutaxassislik', 'hozirgi_talim_turi',
        'toplagan_ball', 'tavsiya_turi', 'tolov_shakli', 'muassasa_nomi', 'hujjat_seriya', 'ortalacha_ball',
        'talim_davlat', 'talim_viloyat', 'talim_tuman', 'oqigan_yili_boshi', 'oqigan_yili_tugashi',
        'sertifikat_turi', 'sertifikat_ball', 'milliy_sertifikat', 'chet_til_sertifikat', 'chet_til_ball',
        'ota_familiya', 'ota_ismi', 'ota_sharifi', 'ota_tel', 'ota_ish_joyi', 'ota_lavozimi',
        'ona_familiya', 'ona_ismi', 'ona_sharifi', 'ona_tel', 'ona_ish_joyi', 'ona_lavozimi',
        'updated_by',
    ];

    protected $casts = [
        'tugilgan_sana' => 'date',
        'passport_sana' => 'date',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}
