<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FaceIdLog extends Model
{
    protected $fillable = [
        'student_id',
        'student_id_number',
        'result',
        'confidence',
        'distance',
        'failure_reason',
        'snapshot',
        'ip_address',
        'user_agent',
    ];

    protected $hidden = [
        'snapshot',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function getResultLabelAttribute(): string
    {
        return match($this->result) {
            'success'         => 'Muvaffaqiyatli',
            'failed'          => 'Muvaffaqiyatsiz',
            'liveness_failed' => 'Jonlilik tekshiruvi o\'tmadi',
            'not_found'       => 'Talaba topilmadi',
            'disabled'        => 'Face ID o\'chirilgan',
            default           => $this->result,
        };
    }

    public function getResultColorAttribute(): string
    {
        return match($this->result) {
            'success'  => 'green',
            'failed'   => 'red',
            default    => 'yellow',
        };
    }
}
