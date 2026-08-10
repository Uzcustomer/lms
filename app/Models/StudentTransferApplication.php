<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentTransferApplication extends Model
{
    protected $fillable = [
        'student_id',
        'phone',
        'target_institution',
        'reason',
        'order_path',
        'order_name',
        'order_mime',
        'order_size',
        'status',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
