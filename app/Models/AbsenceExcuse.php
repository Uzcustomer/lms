<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AbsenceExcuse extends Model
{
    use HasFactory, LogsActivity;

    protected static string $activityModule = 'absence_excuse';

    const REASONS = [
        'kasallik' => 'Kasallik',
        'oilaviy_sabab' => 'Oilaviy sabab',
        'harbiy_chaqiruv' => 'Harbiy chaqiruv',
        'sport_musobaqa' => 'Sport musobaqasi',
        'ilmiy_tadbir' => 'Ilmiy tadbir',
        'manaviy_tadbir' => 'Ma\'naviy-ma\'rifiy tadbir',
        'sudlov_chaqiruv' => 'Sudlov/tergov organlari chaqiruvi',
        'boshqa_uzrli' => 'Boshqa uzrli sabab',
    ];

    protected $fillable = [
        'student_id',
        'student_hemis_id',
        'student_full_name',
        'group_name',
        'department_name',
        'reason',
        'start_date',
        'end_date',
        'description',
        'file_path',
        'file_original_name',
        'status',
        'reviewed_by',
        'reviewed_by_name',
        'rejection_reason',
        'reviewed_at',
        'approved_pdf_path',
        'verification_token',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'reviewed_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->verification_token)) {
                $model->verification_token = Str::uuid()->toString();
            }
        });
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeByStudent($query, $studentId)
    {
        return $query->where('student_id', $studentId);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function getReasonLabelAttribute(): string
    {
        return self::REASONS[$this->reason] ?? $this->reason;
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'Kutilmoqda',
            'approved' => 'Tasdiqlangan',
            'rejected' => 'Rad etilgan',
            default => $this->status,
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'yellow',
            'approved' => 'green',
            'rejected' => 'red',
            default => 'gray',
        };
    }
}
