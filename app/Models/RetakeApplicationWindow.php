<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class RetakeApplicationWindow extends Model
{
    use SoftDeletes;

    /**
     * `application_reopen_until` ustuni mavjudligi (migration ishga tushganmi).
     * Bir martagina tekshiriladi — agar migration hali ishlamagan bo'lsa,
     * qayta ochish funksiyasi jim qoladi, 500 xato bermaydi.
     */
    protected static ?bool $reopenColumnExists = null;
    protected static ?bool $overrideTrackingColumnExists = null;

    public static function supportsReopen(): bool
    {
        if (self::$reopenColumnExists === null) {
            try {
                self::$reopenColumnExists = Schema::hasColumn('retake_application_windows', 'application_reopen_until');
            } catch (\Throwable $e) {
                self::$reopenColumnExists = false;
            }
        }
        return self::$reopenColumnExists;
    }

    public static function supportsOverrideTracking(): bool
    {
        if (self::$overrideTrackingColumnExists === null) {
            try {
                self::$overrideTrackingColumnExists = Schema::hasColumn('retake_application_windows', 'override_count');
            } catch (\Throwable $e) {
                self::$overrideTrackingColumnExists = false;
            }
        }
        return self::$overrideTrackingColumnExists;
    }

    /**
     * Model eventlari: oyna tugash sanasi (end_date) o'zgarsa — shu oyna
     * ostidagi o'qish guruhlari sanasi AVTOMATIK moslashadi (eng kech oyna
     * sanasiga). Bu $window->save()/update() orqali bo'lgan har qanday
     * o'zgarishda ishlaydi. (Bulk query-builder update'da event ishlamaydi,
     * shuning uchun u yerda servis qo'lda chaqiriladi.)
     */
    protected static function booted(): void
    {
        static::updated(function (self $window) {
            if (!$window->wasChanged('end_date')) {
                return;
            }
            try {
                app(\App\Services\Retake\RetakeWindowService::class)
                    ->extendLinkedGroupEndDates([$window->id], (string) $window->end_date->toDateString());
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('[Retake] window->group date sync: ' . $e->getMessage());
            }
        });
    }

    protected $fillable = [
        'session_id',
        'specialty_id',
        'specialty_name',
        'department_hemis_id',
        'level_code',
        'level_name',
        'semester_code',
        'semester_name',
        'start_date',
        'end_date',
        'application_reopen_until',
        'override_count',
        'override_last_at',
        'override_last_by_name',
        'created_by_user_id',
        'created_by_name',
        'creation_batch_id',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'application_reopen_until' => 'date',
        'override_count' => 'integer',
        'override_last_at' => 'datetime',
    ];

    public function hasConsumedSingleOverride(): bool
    {
        if (!self::supportsOverrideTracking()) {
            return false;
        }

        return (int) ($this->override_count ?? 0) > 0;
    }

    public function session()
    {
        return $this->belongsTo(RetakeWindowSession::class, 'session_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(Teacher::class, 'created_by_user_id');
    }

    public function applicationGroups()
    {
        return $this->hasMany(RetakeApplicationGroup::class, 'window_id');
    }

    /**
     * Ariza qabuli qayta ochilganmi? Tugash sanasi Override orqali
     * uzaytirilganda `application_reopen_until` to'ldiriladi — shu sanagacha
     * (shu kun ham) o'qish davrida ham ariza qabuli ochiq turadi.
     */
    public function isApplicationReopened(): bool
    {
        if (!self::supportsReopen()) {
            return false;
        }
        return $this->application_reopen_until !== null
            && $this->application_reopen_until->gte(Carbon::today());
    }

    /**
     * Window holatlari:
     *  - active : today <= start_date — ariza qabul ochiq (start_date kuni ham
     *             ariza yuborilishi mumkin). Override bilan qayta ochilgan
     *             bo'lsa, o'qish davrida ham 'active' bo'ladi.
     *  - study  : start_date < today <= end_date — o'qish davri (jurnal ishlaydi)
     *  - closed : today > end_date — tugagan
     */
    public function getStatusAttribute(): string
    {
        $today = Carbon::today();

        if ($this->end_date->gte($today) || $this->isApplicationReopened()) {
            return 'active';
        }

        return 'closed';
    }

    public function getRemainingDaysAttribute(): int
    {
        $today = Carbon::today();
        if ($this->end_date->lt($today)) {
            return 0;
        }
        return $today->diffInDays($this->end_date);
    }

    public function isOpen(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Talabaning oynalarini tanlash.
     *
     * Muhim: HEMIS data tuzilishi tufayli `students.specialty_id` va
     * `specialties.specialty_hemis_id` qiymatlari har doim ham mos kelmaydi.
     * Misol: "Davolash ishi" yo'nalishi talabalarda specialty_id=18, ammo
     * Specialty jadvalida har bir fakultet uchun alohida (44, 98, ...).
     * Shu sababdan window'da saqlangan specialty_id ham 44/98 bo'lishi mumkin.
     *
     * Mos kelish uchun: specialty_id YOKI specialty_name (case-insensitive)
     * mos kelsa kifoya. Bu HEMIS variant farqlari uchun ham mos tushadi.
     *
     * `$studentDepartmentHemisId` parametri orqaga moslik uchun saqlanadi,
     * ammo filtr sifatida ishlatilmaydi (talabaning haqiqiy fakulteti
     * Student.department_id da saqlangan).
     */
    public function scopeForStudent($query, int $specialtyId, string $levelCode, ?string $studentDepartmentHemisId = null, ?string $specialtyName = null)
    {
        $query->where('level_code', $levelCode);

        $name = $specialtyName !== null ? trim($specialtyName) : '';

        $query->where(function ($q) use ($specialtyId, $name) {
            $q->where('specialty_id', $specialtyId);
            if ($name !== '') {
                $q->orWhereRaw('LOWER(TRIM(specialty_name)) = ?', [mb_strtolower($name)]);
            }
        });

        return $query;
    }

    /**
     * "Ariza qabul davri" faol oyna — bugungi sana boshlanish sanasidan oldin
     * yoki shu kun. Ya'ni start_date kuni ham talaba ariza yubora oladi.
     * (start_date — qayta o'qish "o'qish davrining" boshlanish kuni, ammo
     * shu kun ham ariza qabuli yopilmaydi).
     *
     * Bundan tashqari: Override bilan tugash sanasi uzaytirilgan bo'lsa,
     * `application_reopen_until` sanasigacha (shu kun ham) ariza qabuli
     * qayta ochiq turadi.
     */
    public function scopeActive($query)
    {
        $today = Carbon::today();

        if (!self::supportsReopen()) {
            return $query->whereDate('end_date', '>=', $today);
        }

        return $query->where(function ($q) use ($today) {
            $q->whereDate('end_date', '>=', $today)
              ->orWhereDate('application_reopen_until', '>=', $today);
        });
    }
}
