<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RetakeWindowSession extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'is_closed',
        'created_by_user_id',
        'created_by_name',
        'closed_at',
    ];

    protected $casts = [
        'is_closed' => 'boolean',
        'closed_at' => 'datetime',
    ];

    public function windows(): HasMany
    {
        return $this->hasMany(RetakeApplicationWindow::class, 'session_id');
    }

    public function scopeOpen($query)
    {
        return $query->where('is_closed', false);
    }
}
