<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Vedomost rad etilish inboxida o'qilgan holat (foydalanuvchi × vedomost).
 */
class VedomostRejectionRead extends Model
{
    protected $fillable = [
        'vedomost_submission_id',
        'viewer_type',
        'viewer_id',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];
}
