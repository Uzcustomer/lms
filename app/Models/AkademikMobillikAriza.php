<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AkademikMobillikAriza extends Model
{
    protected $table = 'akademik_mobillik_arizalar';

    protected $fillable = [
        'student_id',
        'phone',
        'reason',
        'transfer_destination',
        'document_path',
        'document_name',
        'document_mime',
        'document_size',
        'curriculum_document_path',
        'curriculum_document_name',
        'curriculum_document_mime',
        'curriculum_document_size',
        'status',
        'created_by_id',
        'created_by_name',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(AkademikMobillikTasdiq::class, 'application_id');
    }
}
