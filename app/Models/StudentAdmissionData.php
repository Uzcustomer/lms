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
        'oliy_malumot', 'otm_nomi', 'talim_turi', 'talim_shakli', 'mutaxassislik',
        'toplagan_ball', 'tolov_shakli', 'muassasa_nomi', 'hujjat_seriya', 'ortalacha_ball',
        'sertifikat_turi', 'sertifikat_ball', 'milliy_sertifikat',
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
