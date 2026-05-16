<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class RetakeApplicationWindow extends Model
{
    use SoftDeletes;

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
        'created_by_user_id',
        'created_by_name',
        'creation_batch_id',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

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
     * Window holatlari:
     *  - active : today <= start_date — ariza qabul ochiq (start_date kuni ham
     *             ariza yuborilishi mumkin)
     *  - study  : start_date < today <= end_date — o'qish davri (jurnal ishlaydi)
     *  - closed : today > end_date — tugagan
     */
    public function getStatusAttribute(): string
    {
        $today = Carbon::today();

        if ($this->start_date->gte($today)) {
            return 'active';
        }
        if ($this->end_date->lt($today)) {
            return 'closed';
        }
        return 'study';
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
     */
    public function scopeActive($query)
    {
        $today = Carbon::today();
        return $query->whereDate('start_date', '>=', $today);
    }
}
