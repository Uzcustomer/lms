<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Qabul ko'rsatkichlari jadvali endi ham yig'ma statistika, ham
 * student-level import qatorlarini saqlashi mumkin.
 */
class AdmissionIndicator extends Model
{
    protected $table = 'admission_indicators';

    protected $fillable = [
        't_r',
        'jshshir_kod',
        'student_id',
        'full_name',
        'farmoyish',
        'buyruq',
        'fuqarolik',
        'davlat',
        'millat',
        'viloyat',
        'tuman',
        'jinsi',
        'tugilgan_sana',
        'passport_raqami',
        'jshshir_kod_2',
        'kurs',
        'fakultet',
        'talim_tili',
        'oquv_yili',
        'qabul_yili',
        'mutaxassislik',
        'mutaxassislik_kodi',
        'talim_turi',
        'talim_shakli',
        'tolov_shakli',
        'talaba_toifasi',
        'imtiyoz_toifasi',
        'toplagan_bali',
        'otish_bali',
        'tolov_kontrakt_barobari_bazaviy',
        'tolov_kontrakt_shartnoma_summasi',
        'kvota',
        'reja',
        'qabul_soni',
        'min_ball',
        'izoh',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        't_r' => 'integer',
        'student_id' => 'integer',
        'kurs' => 'integer',
        'qabul_yili' => 'integer',
        'kvota' => 'integer',
        'reja' => 'integer',
        'qabul_soni' => 'integer',
        'tugilgan_sana' => 'date',
        'toplagan_bali' => 'decimal:2',
        'otish_bali' => 'decimal:2',
        'tolov_kontrakt_barobari_bazaviy' => 'decimal:2',
        'tolov_kontrakt_shartnoma_summasi' => 'decimal:2',
        'min_ball' => 'decimal:1',
    ];

    /** Ta'lim turi variantlari (forma select uchun). */
    public const TALIM_TURLARI = [
        'Bakalavr',
        'Magistr',
        'Ordinatura',
        'Rezidentura',
        'Doktorantura',
    ];

    /** Ta'lim shakli variantlari. */
    public const TALIM_SHAKLLARI = [
        'Kunduzgi',
        'Sirtqi',
        'Kechki',
        'Masofaviy',
    ];

    /** To'lov shakli variantlari. */
    public const TOLOV_SHAKLLARI = [
        'Davlat granti',
        "To'lov-shartnoma",
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }
}
