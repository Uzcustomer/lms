<?php

namespace App\Services;

use App\Models\Teacher;
use App\Models\YnQaydnomaConfirmation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kichik guruhlar (a/b/c) qaydnomasi: boshqa kichik guruh o'qituvchisi tasdiqlagan, shu o'qituvchining
 * guruhi esa hali tasdiqlanmagan — o'qituvchiga modal ("sizning tasdig'ingiz kutilmoqda").
 */
class YnqPending
{
    private const TTL = 120;

    /** @return array<int, array{group: string, subject: string, by: array<int, string>, url: string, ready: bool}> */
    public static function forTeacher(Teacher $teacher): array
    {
        return Cache::remember(self::cacheKey($teacher->id), self::TTL, fn () => self::compute($teacher));
    }

    /** O'qituvchi tasdiqlagach ro'yxat darhol yangilansin */
    public static function forget(int $teacherId): void
    {
        Cache::forget(self::cacheKey($teacherId));
    }

    private static function cacheKey(int $teacherId): string
    {
        return 'ynq-pending:' . $teacherId;
    }

    private static function compute(Teacher $teacher): array
    {
        if (!Schema::hasTable('yn_qaydnoma_confirmations') || !$teacher->hemis_id) {
            return [];
        }
        // O'qituvchining joriy amaliy (ma'ruza va nazoratlardan tashqari) kichik guruh × fanlari
        $own = DB::table('schedules as sc')
            ->join('groups as g', 'g.group_hemis_id', '=', 'sc.group_id')
            ->whereNull('sc.deleted_at')
            ->where('sc.employee_id', $teacher->hemis_id)
            ->whereNotIn('sc.training_type_code', [11, 99, 100, 101, 102, 103])
            ->where('sc.education_year_current', true)
            ->select('sc.group_id', 'sc.subject_id', 'sc.semester_code', 'sc.subject_name', 'g.id as group_pk', 'g.name as group_name', 'g.curriculum_hemis_id')
            ->distinct()
            ->get()
            ->filter(fn ($r) => YnqFamily::stem($r->group_name) !== null);

        $items = [];
        foreach ($own as $r) {
            [$subjectId, $semesterCode, $groupId] = [(string) $r->subject_id, (string) $r->semester_code, (string) $r->group_id];
            $members = YnqFamily::membersFor($subjectId, $semesterCode, $groupId, $r->group_name, $r->curriculum_hemis_id);
            if (count($members) < 2) {
                continue;
            }
            $confirmations = YnQaydnomaConfirmation::forVedomost($subjectId, $semesterCode, $members)
                ->where('role', YnQaydnomaConfirmation::ROLE_TEACHER)->orderBy('confirmed_at')->get();
            // Hali hech kim tasdiqlamagan yoki shu guruh / shu o'qituvchi tasdiqlagan — kutilmaydi
            if ($confirmations->isEmpty() || $confirmations->contains(fn ($c) => (string) $c->group_hemis_id === $groupId || (int) $c->teacher_id === (int) $teacher->id)) {
                continue;
            }
            $names = DB::table('groups')->whereIn('group_hemis_id', $confirmations->pluck('group_hemis_id')->unique()->values())->pluck('name', 'group_hemis_id');
            $ready = Schema::hasTable('yn_submissions') && DB::table('yn_submissions')
                ->where('subject_id', $subjectId)->where('semester_code', $semesterCode)->where('group_hemis_id', $groupId)->exists();
            $w = $confirmations->first()->weights ?? [];
            $items[$groupId . '|' . $subjectId . '|' . $semesterCode] = [
                'group' => (string) $r->group_name,
                'subject' => (string) $r->subject_name,
                'by' => $confirmations->unique('teacher_id')
                    ->map(fn ($c) => ($names[$c->group_hemis_id] ?? '') . ' — ' . $c->teacher_name)->values()->all(),
                // YN yuborilgan — qaydnomani ko'rib tasdiqlaydi; yo'q bo'lsa — avval jurnalda YN yuboradi
                'ready' => $ready,
                'url' => $ready
                    ? route('admin.journal.view-yn-qaydnoma', [
                        'subject_id' => $subjectId, 'semester_code' => $semesterCode, 'group_hemis_id' => $groupId,
                        'weight_jn' => $w['jn'] ?? 30, 'weight_mt' => $w['mt'] ?? 10, 'weight_on' => $w['on'] ?? 0,
                        'weight_oski' => $w['oski'] ?? 0, 'weight_test' => $w['test'] ?? 60,
                    ])
                    : route('admin.journal.show', [$r->group_pk, $subjectId, $semesterCode]),
            ];
        }

        return array_values($items);
    }
}
