<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Qabul ko'rsatkichlari — oldingi yillardagi qabul statistikasi.
 * Hisobotlarda tez-tez ishlatiladigan yig'ma ma'lumotlar.
 */
class AdmissionIndicator extends Model
{
    protected $table = 'admission_indicators';

    protected $fillable = [
        'qabul_yili',
        'talim_turi',
        'talim_shakli',
        'mutaxassislik',
        'mutaxassislik_kodi',
        'tolov_shakli',
        'reja',
        'qabul_soni',
        'min_ball',
        'izoh',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'qabul_yili' => 'integer',
        'reja' => 'integer',
        'qabul_soni' => 'integer',
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
}
