<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentGroupHistory extends Model
{
    protected $table = 'student_group_history';

    protected $fillable = [
        'student_id',
        'group_hemis_id',
        'group_name',
        'specialty_name',
        'payment_form_code',
        'payment_form_name',
        'education_year_name',
        'started_at',
        'ended_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * To'lov shaklini grant / kontrakt sifatida tasniflaydi.
     *
     * HEMIS nomlari turlicha kelishi mumkin ("Davlat granti", "Byudjet",
     * "To'lov-kontrakt", "Shartnoma"), shuning uchun nom bo'yicha aniqlanadi —
     * StudentController dagi statistika bilan bir xil mantiq.
     */
    public static function classifyPaymentForm(?string $name): ?string
    {
        $name = mb_strtolower(trim((string) $name));
        if ($name === '') {
            return null;
        }

        return str_contains($name, 'grant') || str_contains($name, 'byudjet') || str_contains($name, 'budjet')
            ? 'grant'
            : 'contract';
    }

    public function paymentKind(): ?string
    {
        return static::classifyPaymentForm($this->payment_form_name);
    }

    public function isGrant(): bool
    {
        return $this->paymentKind() === 'grant';
    }
}
