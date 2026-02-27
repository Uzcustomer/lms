<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KtrChangeApproval extends Model
{
    protected $fillable = [
        'change_request_id',
        'role',
        'approver_name',
        'approver_id',
        'status',
        'responded_at',
    ];

    protected $casts = [
        'responded_at' => 'datetime',
    ];

    public function changeRequest()
    {
        return $this->belongsTo(KtrChangeRequest::class, 'change_request_id');
    }
}
