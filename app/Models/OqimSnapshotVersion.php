<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OqimSnapshotVersion extends Model
{
    protected $fillable = [
        'context_key', 'context', 'kind', 'academic_year',
        'faculty_id', 'faculty_name', 'education_type',
        'data', 'summary', 'note', 'approved_by', 'approved_at',
    ];

    protected $casts = [
        'context'     => 'array',
        'summary'     => 'array',
        'data'        => 'array',
        'approved_at' => 'datetime',
    ];
}
