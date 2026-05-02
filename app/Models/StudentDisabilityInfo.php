<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentDisabilityInfo extends Model
{
    protected $fillable = [
        'student_id',
        'examined_at',
        'disability_group',
        'disability_reason',
        'disability_duration',
        'reexamination_at',
        'certificate_path',
    ];

    protected $casts = [
        'examined_at' => 'date',
        'reexamination_at' => 'date',
        'disability_duration' => 'date',
    ];

    public const GROUPS = [
        'I' => "I guruh",
        'II' => "II guruh",
        'III' => "III guruh",
        'bolalikdan' => "Bolalikdan nogiron",
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}
