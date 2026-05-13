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
     *  - active : today < start_date — ariza qabul ochiq (talabalar ariza yuborishi mumkin)
     *  - study  : start_date <= today <= end_date — o'qish davri (ariza yopildi, jurnal ishlaydi)
     *  - closed : today > end_date — tugagan
     */
    public function getStatusAttribute(): string
    {
        $today = Carbon::today();

        if ($this->start_date->gt($today)) {
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
     * Tarixda fakultet filtri qo'shilgandi, lekin Student.department_id va
     * window.department_hemis_id qiymatlari HEMIS ma'lumotlar farqi yoki
     * admin tanlov nuanslari tufayli aniq mos kelmasligi mumkin. Bu sabab
     * talaba ariza bera olmaydigan holatga tushardi.
     *
     * Endi faqat (yo'nalish + kurs) bo'yicha mos keluvchi oyna qaytariladi.
     * Talabaning haqiqiy fakulteti `Student.department_id` orqali alohida
     * saqlanadi va guruhlashda ishlatiladi — bu yerda zarurati yo'q.
     *
     * Eslatma: `$studentDepartmentHemisId` parametri orqaga moslik uchun
     * saqlanadi, ammo filtr sifatida ishlatilmaydi.
     */
    public function scopeForStudent($query, int $specialtyId, string $levelCode, ?string $studentDepartmentHemisId = null)
    {
        return $query->where('specialty_id', $specialtyId)
            ->where('level_code', $levelCode);
    }

    /**
     * "Ariza qabul davri" faol oyna — bugungi sana boshlanish sanasidan oldin (qattiq kichik).
     * Ya'ni start_date kunining boshlanishidan boshlab oyna ariza qabul qilmaydi
     * (start_date — qayta o'qish "o'qish davrining" boshlanish kuni).
     */
    public function scopeActive($query)
    {
        $today = Carbon::today();
        return $query->whereDate('start_date', '>', $today);
    }
}
