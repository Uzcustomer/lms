<?php

namespace App\Http\Middleware;

use App\Models\Group;
use App\Models\MarkingSystemScore;
use App\Models\Student;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bitta guruh × fan × semestr bilan ishlaydigan so'rovlarda (jurnal, uning AJAX amallari, YN yuborish,
 * qaydnoma) o'tish chegarasi shu fan bo'yicha: YN imtihonlari chegara oshirilgan sanadan
 * (26.10.2026) oldin tugagan fanda — eski 60, qolganlarida sozlamadagi (MarkingSystemScore::useSubject).
 */
class ApplySubjectGradeLimit
{
    public function handle(Request $request, Closure $next): Response
    {
        MarkingSystemScore::forgetSubject();
        try {
            [$group, $subject, $semester] = $this->vedomost($request);
            if ($group !== null && $subject !== null && $semester !== null) {
                MarkingSystemScore::useSubject($group, $subject, $semester);
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return $next($request);
    }

    /** @return array{0: ?string, 1: ?string, 2: ?string} guruh (HEMIS id), fan, semestr */
    private function vedomost(Request $request): array
    {
        $route = $request->route();
        $scalar = fn ($v) => is_scalar($v) && (string) $v !== '' ? (string) $v : null;
        $subject = $scalar($route?->parameter('subjectId') ?? $request->input('subject_id'));
        $semester = $scalar($route?->parameter('semesterCode') ?? $request->input('semester_code'));
        if ($subject === null || $semester === null) {
            return [null, null, null];
        }

        $group = $scalar($request->input('group_hemis_id'));
        // Jurnal sahifasi: /journal/show/{groupId}/{subjectId}/{semesterCode} — groupId guruhning id si
        if ($group === null && ($pk = $route?->parameter('groupId')) && str_ends_with((string) $route->getActionName(), 'JournalController@show')) {
            $group = $scalar(Group::whereKey($pk)->value('group_hemis_id'));
        }
        // Bitta talaba bahosi (saqlash / tahrirlash) — talabaning guruhi
        if ($group === null && ($student = $scalar($request->input('student_hemis_id')))) {
            $group = $scalar(Student::where('hemis_id', $student)->value('group_id'));
        }

        return [$group, $subject, $semester];
    }
}
