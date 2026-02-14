<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarkingSystemScore extends Model
{
    protected $fillable = [
        'marking_system_code',
        'marking_system_name',
        'minimum_limit',
        'gpa_limit',
        'jn_limit',
        'jn_active',
        'mt_limit',
        'mt_active',
        'on_limit',
        'on_active',
        'oski_limit',
        'oski_active',
        'test_limit',
        'test_active',
        'total_limit',
        'total_active',
    ];

    protected $casts = [
        'minimum_limit' => 'integer',
        'gpa_limit' => 'float',
        'jn_limit' => 'integer',
        'jn_active' => 'boolean',
        'mt_limit' => 'integer',
        'mt_active' => 'boolean',
        'on_limit' => 'integer',
        'on_active' => 'boolean',
        'oski_limit' => 'integer',
        'oski_active' => 'boolean',
        'test_limit' => 'integer',
        'test_active' => 'boolean',
        'total_limit' => 'integer',
        'total_active' => 'boolean',
    ];

    public function curricula()
    {
        return $this->hasMany(Curriculum::class, 'marking_system_code', 'marking_system_code');
    }
}
