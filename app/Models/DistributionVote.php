<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * Talabaning guruh tanlash ovozi. Talabada bitta faol ovoz bo'ladi;
 * rejasi bekor qilingan ovoz o'chirilmaydi — "eski" (archived_at) deb
 * belgilanadi va talaba qaytadan ovoz bera oladi.
 */
class DistributionVote extends Model
{
    protected $table = 'distribution_votes';

    protected $fillable = [
        'student_id', 'from_group_hemis_id', 'to_group_hemis_id',
        'student_name', 'student_id_number', 'from_group_name', 'to_group_name',
        'status', 'approved_by', 'approved_at', 'archived_at', 'archived_by',
    ];

    protected $casts = [
        'student_id' => 'integer',
        'from_group_hemis_id' => 'integer',
        'to_group_hemis_id' => 'integer',
        'approved_by' => 'integer',
        'approved_at' => 'datetime',
        'archived_by' => 'integer',
        'archived_at' => 'datetime',
    ];

    private static ?bool $supportsArchive = null;

    /** archived_at ustuni bormi (migratsiya hali ishlatilmagan bo'lishi mumkin). */
    public static function supportsArchive(): bool
    {
        return self::$supportsArchive ??= Schema::hasColumn('distribution_votes', 'archived_at');
    }

    /** Faol (eski deb belgilanmagan) ovozlar. */
    public function scopeActive(Builder $query): Builder
    {
        return self::supportsArchive() ? $query->whereNull('archived_at') : $query;
    }

    public function isArchived(): bool
    {
        return self::supportsArchive() && $this->archived_at !== null;
    }

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }
}
