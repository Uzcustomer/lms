<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class DocumentVerification extends Model
{
    protected $fillable = [
        'token',
        'document_type',
        'subject_name',
        'group_names',
        'semester_name',
        'department_name',
        'generated_by',
        'generated_at',
        'document_path',
        'meta',
    ];

    protected $casts = [
        'generated_at' => 'datetime',
        'meta' => 'array',
    ];

    public static function createForDocument(array $data): self
    {
        return self::create(array_merge($data, [
            'token' => Str::random(48),
            'generated_at' => now(),
        ]));
    }

    public function getVerificationUrl(): string
    {
        return route('document.verify', $this->token);
    }
}
