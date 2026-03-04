<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamAppealComment extends Model
{
    protected $fillable = [
        'exam_appeal_id',
        'user_type',
        'user_id',
        'user_name',
        'comment',
    ];

    public function appeal()
    {
        return $this->belongsTo(ExamAppeal::class, 'exam_appeal_id');
    }
}
