<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Umumiy ochilgan testga kirish so'rovi: talaba ID sini kiritadi, o'qituvchi
 * "Kirish so'rovlari" oynasida ruxsat beradi yoki rad etadi.
 */
class FanTestiEntryRequest extends Model
{
    protected $table = 'fan_testi_entry_requests';

    /** Ruxsat shuncha daqiqa ichida ishlatilmasa eskiradi. */
    public const APPROVAL_MINUTES = 30;

    /** Shuncha soatdan eski so'rovlar o'qituvchi ro'yxatida ko'rinmaydi. */
    public const LIST_HOURS = 3;

    protected $fillable = [
        'fan_testi_id', 'student_id', 'student_name', 'student_id_number', 'group_name', 'faculty_name',
        'token', 'status', 'decided_by', 'decided_at', 'used_at', 'attempt_id', 'ip_address',
    ];

    protected $casts = [
        'decided_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    protected $hidden = ['token'];

    public function test()
    {
        return $this->belongsTo(FanTesti::class, 'fan_testi_id');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /** Ruxsat berilgan, hali ishlatilmagan va eskirmagan. */
    public function isUsable(): bool
    {
        return $this->status === 'approved'
            && $this->used_at === null
            && $this->decided_at !== null
            && $this->decided_at->gt(now()->subMinutes(self::APPROVAL_MINUTES));
    }

    /**
     * Talabaning shu testdagi ochiq so'rovi (kutilayotgan yoki ishlatilmagan
     * ruxsat) qaytariladi, bo'lmasa yangisi yaratiladi. Belgi har safar
     * yangilanadi — ruxsat faqat oxirgi kiritilgan brauzerda ishlaydi.
     */
    public static function openFor(FanTesti $test, Student $student, ?string $ip): self
    {
        $existing = static::query()
            ->where('fan_testi_id', $test->id)
            ->where('student_id', $student->id)
            ->whereNull('used_at')
            ->whereIn('status', ['pending', 'approved'])
            ->where('created_at', '>', now()->subHours(self::LIST_HOURS))
            ->latest('id')
            ->first();

        if ($existing && ($existing->isPending() || $existing->isUsable())) {
            $existing->forceFill(['token' => Str::random(64), 'ip_address' => $ip])->save();

            return $existing;
        }

        return static::create([
            'fan_testi_id' => $test->id,
            'student_id' => $student->id,
            'student_name' => $student->full_name,
            'student_id_number' => $student->student_id_number,
            'group_name' => $student->group_name,
            'faculty_name' => $student->department_name,
            'token' => Str::random(64),
            'status' => 'pending',
            'ip_address' => $ip,
        ]);
    }

    public function statusLabel(): string
    {
        if ($this->used_at) {
            return 'Testga kirdi';
        }

        return match ($this->status) {
            'approved' => $this->isUsable() ? 'Ruxsat berildi' : 'Ruxsat eskirdi',
            'rejected' => 'Rad etildi',
            default => 'Kutilmoqda',
        };
    }
}
