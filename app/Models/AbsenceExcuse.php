<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AbsenceExcuse extends Model
{
    use HasFactory, LogsActivity;

    protected static string $activityModule = 'absence_excuse';

    /**
     * Vazirlik tomonidan belgilangan 8 ta sababli dars qoldirish asoslari
     */
    const REASONS = [
        'kasallik' => [
            'label' => 'Kasallik (vaqtincha mehnatga layoqatsizlik)',
            'document' => 'Mehnatga layoqatsizlik ma\'lumotnomasi (095/x shakl)',
            'max_days' => 30,
            'note' => '30 kundan ortiq bo\'lsa, tibbiy-maslahat komissiyasi xulosasi talab qilinadi',
        ],
        'tibbiy_korik' => [
            'label' => 'Nogironligi mavjud talabaning tibbiy ko\'rikdan o\'tishi',
            'document' => 'Tibbiy mehnat ekspertiza komissiyasi (TMEK) ma\'lumotnomasi',
            'max_days' => null,
            'note' => 'Ko\'rikdan o\'tish muddatiga',
        ],
        'nikoh_toyi' => [
            'label' => 'Talabaning nikoh to\'yi',
            'document' => 'Talabaning arizasi va nikoh to\'yi haqidagi guvohnoma',
            'max_days' => 10,
            'note' => null,
        ],
        'yaqin_qarindosh' => [
            'label' => 'Yaqin qarindoshning to\'yi yoki vafoti',
            'document' => 'Talaba arizasi va asoslovchi hujjat (guvohnoma)',
            'max_days' => 5,
            'note' => 'Birinchi darajali qarindoshlar uchun',
        ],
        'homiladorlik' => [
            'label' => 'Homiladorlik va tug\'ruq',
            'document' => 'Tibbiy muassasa ma\'lumotnomasi va tug\'ilganlik guvohnomasi',
            'max_days' => 30,
            'note' => '30 kundan ortiq bo\'lsa, akademik ta\'til rasmiylashtirish talab qilinadi',
        ],
        'musobaqa_tadbir' => [
            'label' => 'Musobaqalar va tadbirlarda qatnashish',
            'document' => 'Tegishli vazirlik/idora so\'rov xati yoki rektor buyrug\'i',
            'max_days' => 30,
            'note' => 'Tadbir muddatiga, 30 kundan ko\'p bo\'lmagan muddatga',
        ],
        'tabiiy_ofat' => [
            'label' => 'Tabiiy ofat yoki baxtsiz hodisa',
            'document' => 'Tegishli idoralar ma\'lumotnomasi',
            'max_days' => 30,
            'note' => 'Ma\'lumotnomada belgilangan muddatlarga',
        ],
        'xorijlik_viza' => [
            'label' => 'Xorijlik talabaning viza muddati tugashi',
            'document' => 'Pasport nusxasi va xalqaro bo\'lim bildirgisi',
            'max_days' => 30,
            'note' => 'Viza olish muddatiga, 30 kundan ko\'p bo\'lmagan muddatga',
        ],
    ];

    protected $fillable = [
        'student_id',
        'student_hemis_id',
        'student_full_name',
        'group_name',
        'department_name',
        'doc_number',
        'reason',
        'start_date',
        'end_date',
        'description',
        'file_path',
        'file_original_name',
        'status',
        'reviewed_by',
        'reviewed_by_name',
        'rejection_reason',
        'reviewed_at',
        'approved_pdf_path',
        'verification_token',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'reviewed_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->verification_token)) {
                $model->verification_token = Str::uuid()->toString();
            }
        });
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function makeups()
    {
        return $this->hasMany(AbsenceExcuseMakeup::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeByStudent($query, $studentId)
    {
        return $query->where('student_id', $studentId);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function getReasonLabelAttribute(): string
    {
        return self::REASONS[$this->reason]['label'] ?? $this->reason;
    }

    public function getReasonDocumentAttribute(): string
    {
        return self::REASONS[$this->reason]['document'] ?? '';
    }

    public function getReasonMaxDaysAttribute(): ?int
    {
        return self::REASONS[$this->reason]['max_days'] ?? null;
    }

    public function getReasonNoteAttribute(): ?string
    {
        return self::REASONS[$this->reason]['note'] ?? null;
    }

    public static function reasonLabels(): array
    {
        return collect(self::REASONS)->mapWithKeys(fn ($data, $key) => [$key => $data['label']])->toArray();
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'Kutilmoqda',
            'approved' => 'Tasdiqlangan',
            'rejected' => 'Rad etilgan',
            default => $this->status,
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'yellow',
            'approved' => 'green',
            'rejected' => 'red',
            default => 'gray',
        };
    }
}
