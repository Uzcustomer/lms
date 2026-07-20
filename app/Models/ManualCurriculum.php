<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ManualCurriculum extends Model
{
    protected $table = 'manual_curricula';

    protected $fillable = [
        'type',
        'status',
        'name',
        'specialty_code',
        'specialty_name',
        'plan_year',
        'curricula_hemis_id',
        'level_code',
        'semester_code',
        'education_type_name',
        'education_period',
        'file_original_name',
        'file_path',
        'notes',
        'created_by',
    ];

    public function subjects()
    {
        return $this->hasMany(ManualCurriculumSubject::class);
    }

    public function hemisCurriculum()
    {
        return $this->belongsTo(Curriculum::class, 'curricula_hemis_id', 'curricula_hemis_id');
    }

    public function typeLabel(): string
    {
        return $this->type === 'namunaviy' ? "Namunaviy o'quv reja" : "Ishchi o'quv reja";
    }

    /**
     * Bir HEMIS o'quv reja uchun namunaviy BITTA bo'lishi kerak. Agar tarixda
     * bir nechta namunaviy yuklangan bo'lsa (masalan, xato bilan qayta yuklash),
     * "kanonik" (asosiy) reja sifatida eng to'liq — eng ko'p fan qatorli — reja
     * olinadi; fan soni teng bo'lsa, eng oxirgi yuklangani (katta id) ustun.
     *
     * Ro'yxat, solishtirish va tozalash buyrug'i — hammasi shu yagona mezonga
     * tayanadi, shunda qaysi namunaviy tanlanishi barcha joyda bir xil bo'ladi.
     *
     * subjects_count oldindan yuklangan bo'lsa (withCount('subjects')) qo'shimcha
     * so'rovsiz ishlaydi; bo'lmasa fanlar sanab olinadi.
     */
    public static function canonicalRank(self $curriculum): array
    {
        $count = $curriculum->subjects_count ?? $curriculum->subjects()->count();

        return [(int) $count, (int) $curriculum->id];
    }

    /** Rejalashtirilgan (HEMIS'ga bog'lanmagan) reja. */
    public function isPlanned(): bool
    {
        return $this->status === 'planned' || ($this->curricula_hemis_id === null && $this->status !== 'active');
    }
}
