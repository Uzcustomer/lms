<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentDistributionGroup extends Model
{
    protected $fillable = [
        'faculty_name', 'specialty_name', 'course', 'group_name', 'group_hemis_id',
        'capacity', 'occupied_count', 'free_places', 'source_file', 'uploaded_by',
        'import_key', 'scope_hash', 'is_active',
    ];

    protected $casts = [
        'course' => 'integer', 'capacity' => 'integer', 'occupied_count' => 'integer',
        'free_places' => 'integer', 'uploaded_by' => 'integer', 'is_active' => 'boolean',
    ];
}
