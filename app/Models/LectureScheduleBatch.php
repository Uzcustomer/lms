<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LectureScheduleBatch extends Model
{
    protected $fillable = [
        'uploaded_by',
        'uploaded_by_guard',
        'file_name',
        'total_rows',
        'conflicts_count',
        'hemis_mismatches_count',
        'semester_code',
        'education_year',
        'status',
    ];

    public function items()
    {
        return $this->hasMany(LectureSchedule::class, 'batch_id');
    }

    public function getUploaderNameAttribute(): string
    {
        if ($this->uploaded_by_guard === 'teacher') {
            $teacher = Teacher::where('id', $this->uploaded_by)->first();
            return $teacher?->short_name ?? $teacher?->full_name ?? 'Noma\'lum';
        }
        $user = \App\Models\User::find($this->uploaded_by);
        return $user?->name ?? 'Noma\'lum';
    }
}
