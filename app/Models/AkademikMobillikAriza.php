<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AkademikMobillikAriza extends Model
{
    protected $table = 'akademik_mobillik_arizalar';

    protected $fillable = [
        'student_id',
        'phone',
        'reason',
        'status',
        'created_by_id',
        'created_by_name',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
