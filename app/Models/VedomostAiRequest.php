<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Bitta Gemini so'rovining jurnali (AI sozlamalari statistikasi uchun).
 */
class VedomostAiRequest extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'vedomost_submission_id',
        'vedomost_ai_key_id',
        'model',
        'purpose',
        'status',
        'http_status',
        'error_type',
        'error_message',
        'latency_ms',
        'input_tokens',
        'output_tokens',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function key()
    {
        return $this->belongsTo(VedomostAiKey::class, 'vedomost_ai_key_id');
    }
}
