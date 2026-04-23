<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentPhoto extends Model
{
    protected $fillable = [
        'student_id_number',
        'full_name',
        'group_name',
        'semester_name',
        'uploaded_by',
        'photo_path',
    ];

    public function getPhotoUrlAttribute(): string
    {
        return asset('storage/' . $this->photo_path);
    }
}
