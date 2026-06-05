<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VedomostSubmissionLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'vedomost_submission_id',
        'action',
        'from_status',
        'to_status',
        'note',
        'user_id',
        'user_name',
    ];

    public function submission()
    {
        return $this->belongsTo(VedomostSubmission::class, 'vedomost_submission_id');
    }
}
