<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Oqim taqsimotining saqlangan/tasdiqlangan holati (registrator ofisi).
 */
class OqimSnapshot extends Model
{
    protected $fillable = [
        'context_key',
        'context',
        'data',
        'status',
        'note',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'context'     => 'array',
        'data'        => 'array',
        'approved_at' => 'datetime',
    ];
}
