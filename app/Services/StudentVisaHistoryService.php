<?php

namespace App\Services;

use App\Models\Student;
use App\Models\StudentVisaInfo;
use App\Models\StudentVisaInfoHistory;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class StudentVisaHistoryService
{
    private const SNAPSHOT_FIELDS = [
        'birth_country', 'birth_region', 'birth_city', 'birth_date',
        'passport_number', 'passport_issued_place', 'passport_issued_date', 'passport_expiry_date', 'passport_scan_path',
        'registration_start_date', 'registration_end_date', 'registration_doc_path', 'registration_process_status',
        'address_type', 'current_address',
        'visa_number', 'visa_type', 'visa_start_date', 'visa_end_date',
        'visa_issued_place', 'visa_issued_date', 'visa_entries_count', 'visa_stay_days',
        'visa_scan_path', 'visa_process_status',
        'entry_date', 'firm', 'firm_custom',
        'status', 'rejection_reason',
    ];

    /**
     * Yangi snapshot yaratish.
     *
     * @param  array<string,mixed>|null  $oldValues  Tahrirdan oldingi qiymatlar (changed_fields ni hisoblash uchun)
     */
    public function snapshot(
        Student $student,
        ?StudentVisaInfo $visaInfo,
        string $changeType,
        ?array $oldValues = null,
        ?string $note = null
    ): StudentVisaInfoHistory {
        if (!Schema::hasTable('student_visa_info_histories')) {
            return new StudentVisaInfoHistory();
        }

        $snapshot = $this->fieldsFrom($visaInfo);

        $changedFields = null;
        if ($oldValues !== null && $visaInfo) {
            $diff = [];
            foreach (self::SNAPSHOT_FIELDS as $field) {
                $old = $oldValues[$field] ?? null;
                $new = $visaInfo->getAttribute($field);
                if ($old instanceof \DateTimeInterface) $old = $old->format('Y-m-d');
                if ($new instanceof \DateTimeInterface) $new = $new->format('Y-m-d');
                if ((string) $old !== (string) $new) {
                    $diff[] = $field;
                }
            }
            $changedFields = $diff ?: null;
        }

        $actor = $this->resolveActor($student);

        return StudentVisaInfoHistory::create(array_merge($snapshot, [
            'student_id' => $student->id,
            'visa_info_id' => $visaInfo?->id,
            'change_type' => $changeType,
            'changed_fields' => $changedFields,
            'actor_type' => $actor['type'],
            'actor_id' => $actor['id'],
            'actor_name' => $actor['name'],
            'actor_role' => $actor['role'],
            'note' => $note,
            'created_at' => now(),
        ]));
    }

    /**
     * StudentVisaInfo dan snapshot maydonlarini massivga aylantirish.
     */
    public function fieldsFrom(?StudentVisaInfo $visaInfo): array
    {
        $out = [];
        foreach (self::SNAPSHOT_FIELDS as $field) {
            $out[$field] = $visaInfo?->getAttribute($field);
        }
        return $out;
    }

    /**
     * Joriy foydalanuvchi (admin / talaba / firma).
     */
    private function resolveActor(Student $student): array
    {
        if (Auth::guard('web')->check()) {
            $u = Auth::guard('web')->user();
            return [
                'type' => 'admin',
                'id' => $u->id,
                'name' => $u->name ?? $u->full_name ?? 'admin',
                'role' => $this->primaryRole($u),
            ];
        }

        if (Auth::guard('teacher')->check()) {
            $t = Auth::guard('teacher')->user();
            return [
                'type' => 'teacher',
                'id' => $t->id,
                'name' => $t->full_name ?? $t->name ?? 'teacher',
                'role' => $this->primaryRole($t),
            ];
        }

        if (Auth::guard('student')->check()) {
            $s = Auth::guard('student')->user();
            return [
                'type' => 'student',
                'id' => $s->id,
                'name' => $s->full_name ?? 'talaba',
                'role' => 'talaba',
            ];
        }

        return ['type' => 'system', 'id' => null, 'name' => 'tizim', 'role' => null];
    }

    private function primaryRole($user): ?string
    {
        try {
            if (method_exists($user, 'roles')) {
                $r = $user->roles()->pluck('name')->first();
                if ($r) return $r;
            }
        } catch (\Throwable $e) {
            // ignore
        }
        return null;
    }
}
