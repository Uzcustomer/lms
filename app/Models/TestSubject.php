<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TestSubject extends Model
{
    protected $fillable = [
        'name',
        'faculty_hemis_id',
        'faculty_name',
        'specialty_hemis_id',
        'specialty_name',
        'level_code',
        'level_name',
        'teacher_id',
        'teacher_hemis_id',
        'teacher_name',
        'starts_on',
        'ends_on',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'starts_on' => 'date',
        'ends_on' => 'date',
        'is_active' => 'boolean',
    ];

    public function teacher()
    {
        return $this->belongsTo(Teacher::class, 'teacher_id');
    }

    public function groupLinks()
    {
        return $this->hasMany(TestSubjectGroup::class);
    }

    public function groups()
    {
        return $this->hasMany(TestSubjectGroup::class);
    }

    public function lessons()
    {
        return $this->hasMany(TestSubjectLesson::class)->orderBy('topic_order')->orderBy('id');
    }
}
