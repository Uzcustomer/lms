<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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

    /**
     * Talaba sana oralig'ida darsda bo'lib baho olgan kunlar — sababli ma'lumotnoma bilan zid.
     * NB (reason=absent) va mustaqil ta'lim (99 — darsga kelish emas) hisobga olinmaydi.
     */
    public static function gradeConflicts(string $studentHemisId, $from, $to): Collection
    {
        return DB::table('student_grades')
            ->where('student_hemis_id', $studentHemisId)
            ->whereDate('lesson_date', '>=', $from)
            ->whereDate('lesson_date', '<=', $to)
            ->whereNotNull('grade')
            ->where(fn ($q) => $q->whereNull('reason')->orWhere('reason', '!=', 'absent'))
            ->where(fn ($q) => $q->whereNull('training_type_code')->orWhere('training_type_code', '!=', 99))
            ->whereNull('deleted_at')
            ->orderBy('lesson_date')
            ->get(['lesson_date', 'subject_name', 'grade', 'lesson_pair_name']);
    }

    /** Talabaga ko'rsatish uchun: [{date, subject_name, pair, grade}] */
    public static function gradeConflictRows(Collection $conflicts): array
    {
        return $conflicts->map(fn ($c) => [
            'date' => Carbon::parse($c->lesson_date)->format('d.m.Y'),
            'subject_name' => $c->subject_name,
            'pair' => $c->lesson_pair_name,
            'grade' => rtrim(rtrim(number_format((float) $c->grade, 2, '.', ''), '0'), '.'),
        ])->values()->all();
    }

    public static function gradeConflictMessage(Collection $conflicts): string
    {
        $days = collect(self::gradeConflictRows($conflicts))
            ->map(fn ($r) => "{$r['date']} — {$r['subject_name']} ({$r['grade']})")
            ->implode('; ');

        return "Ariza sanalarida darsda bo'lib baho olgansiz: {$days}. "
            . "Bu kunlar sababli bo'lishi mumkin emas — sana oralig'ini to'g'rilang.";
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
