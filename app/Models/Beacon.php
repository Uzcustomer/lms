<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Beacon extends Model
{
    protected $fillable = [
        'uuid', 'major', 'minor', 'auditorium_code', 'auditorium_name', 'label', 'active',
    ];

    protected $casts = [
        'major' => 'integer',
        'minor' => 'integer',
        'active' => 'boolean',
    ];

    public function auditorium()
    {
        return $this->belongsTo(Auditorium::class, 'auditorium_code', 'code');
    }

    /** Case-insensitive match on the three iBeacon identifiers. */
    public function scopeMatching($query, string $uuid, int $major, int $minor)
    {
        return $query->whereRaw('LOWER(uuid) = ?', [strtolower($uuid)])
            ->where('major', $major)
            ->where('minor', $minor);
    }

    public function matches(string $uuid, int $major, int $minor): bool
    {
        return strtolower($this->uuid) === strtolower($uuid)
            && $this->major === $major
            && $this->minor === $minor;
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'major' => $this->major,
            'minor' => $this->minor,
            'auditorium_code' => $this->auditorium_code,
            'auditorium_name' => $this->auditorium_name,
        ];
    }
}
