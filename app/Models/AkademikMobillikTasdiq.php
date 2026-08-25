<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AkademikMobillikTasdiq extends Model
{
    protected $table = 'akademik_mobillik_tasdiqlari';

    protected $fillable = [
        'application_id',
        'role',
        'status',
        'rejection_comment',
        'reviewed_by_id',
        'reviewed_by_name',
        'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(AkademikMobillikAriza::class, 'application_id');
    }
}
