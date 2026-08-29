<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\StudentDistributionGroup;
use App\Models\StudentGroupChangeApplication;
use App\Models\StudentGroupChangePermission;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class GroupChangeApplicationController extends Controller
{
    public function create()
    {
        $student = Auth::guard('student')->user();
        $permissionEnabled = StudentGroupChangePermission::query()
            ->where('student_id', $student->id)
            ->where('enabled', true)
            ->exists();
        $applications = StudentGroupChangeApplication::query()
            ->where('student_id', $student->id)
            ->latest()
            ->get();

        abort_unless(
            $permissionEnabled || $applications->isNotEmpty(),
            403,
            'Bu xizmat uchun registrator ofisi ruxsati kerak.'
        );

        $sourceGroup = $this->sourceGroupFor($student);
        $availableGroups = collect();
        if ($permissionEnabled && $sourceGroup && !$applications->contains('status', 'pending')) {
            $availableGroups = StudentDistributionGroup::query()
                ->where('is_active', true)
                ->where('is_source', false)
                ->where('specialty_name', $sourceGroup->specialty_name)
                ->where('course', $sourceGroup->course)
                ->where('free_places', '>', 0)
                ->orderBy('group_name')
                ->get()
                ->filter(fn (StudentDistributionGroup $target) => $this->distributionFacultiesCompatible($sourceGroup, $target))
                ->values();
        }

        return view('student.group-change-application.create', [
            'student' => $student,
            'sourceGroup' => $sourceGroup,
            'availableGroups' => $availableGroups,
            'applications' => $applications,
            'canSubmit' => $permissionEnabled
                && $sourceGroup
                && $availableGroups->isNotEmpty()
                && !$applications->contains('status', 'pending'),
        ]);
    }

    public function store(Request $request)
    {
        $student = Auth::guard('student')->user();
        abort_unless(
            StudentGroupChangePermission::query()
                ->where('student_id', $student->id)
                ->where('enabled', true)
                ->exists(),
            403,
            'Bu xizmat uchun registrator ofisi ruxsati kerak.'
        );

        $data = $request->validate([
            'target_group_id' => 'required|integer|exists:student_distribution_groups,id',
            'reason' => 'required|string|min:10|max:2000',
        ], [
            'target_group_id.required' => "O'tmoqchi bo'lgan guruhni tanlang.",
            'reason.required' => 'Ariza sababini yozing.',
            'reason.min' => "Ariza sababi kamida 10 ta belgidan iborat bo'lsin.",
        ]);
        $sourceGroup = $this->sourceGroupFor($student);
        if (!$sourceGroup) {
            return back()->withErrors(['target_group_id' => "Sizning guruhingiz taqsimlanadigan guruhlar ro'yxatida topilmadi."])->withInput();
        }

        try {
            DB::transaction(function () use ($student, $sourceGroup, $data) {
                $target = StudentDistributionGroup::query()
                    ->where('is_active', true)
                    ->where('is_source', false)
                    ->lockForUpdate()
                    ->findOrFail($data['target_group_id']);

                if ($target->free_places < 1
                    || !$this->distributionFacultiesCompatible($sourceGroup, $target)
                    || $target->specialty_name !== $sourceGroup->specialty_name
                    || (int) $target->course !== (int) $sourceGroup->course) {
                    abort(422, "Tanlangan guruh sizga mos emas yoki uning bo'sh joyi qolmagan.");
                }

                $pendingExists = StudentGroupChangeApplication::query()
                    ->where('student_id', $student->id)
                    ->where('status', 'pending')
                    ->lockForUpdate()
                    ->exists();
                if ($pendingExists) {
                    abort(422, "Sizda ko'rib chiqilayotgan ariza mavjud.");
                }

                StudentGroupChangeApplication::query()->create([
                    'student_id' => $student->id,
                    'source_group_id' => $sourceGroup->id,
                    'target_group_id' => $target->id,
                    'student_name' => $student->full_name,
                    'student_id_number' => $student->student_id_number,
                    'faculty_name' => $sourceGroup->faculty_name,
                    'specialty_name' => $sourceGroup->specialty_name,
                    'course' => $sourceGroup->course,
                    'source_group_name' => $sourceGroup->group_name,
                    'target_group_name' => $target->group_name,
                    'reason' => trim($data['reason']),
                    'status' => 'pending',
                ]);
            });
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            return back()->withErrors(['target_group_id' => $exception->getMessage()])->withInput();
        }

        return redirect()
            ->route('student.group-change-application.create')
            ->with('success', 'Arizangiz qabul qilindi va registrator ofisiga yuborildi.');
    }

    private function sourceGroupFor(Student $student): ?StudentDistributionGroup
    {
        $course = $this->courseNumber($student->level_code, $student->level_name);
        if (!$course) {
            return null;
        }

        return StudentDistributionGroup::query()
            ->where('is_active', true)
            ->where('is_source', true)
            ->where('faculty_name', trim((string) $student->department_name))
            ->where('specialty_name', trim((string) $student->specialty_name))
            ->where('course', $course)
            ->where(function (Builder $query) use ($student) {
                $query->where('group_name', $student->group_name);
                if ($student->group_id) {
                    $query->orWhere('group_hemis_id', (string) $student->group_id);
                }
            })
            ->first();
    }

    private function distributionFacultiesCompatible(
        StudentDistributionGroup $source,
        StudentDistributionGroup $target
    ): bool {
        if (mb_strtolower(trim((string) $source->faculty_name)) === mb_strtolower(trim((string) $target->faculty_name))) {
            return true;
        }

        return preg_match('/^d[12](?:\\/|$)/i', trim((string) $source->group_name)) === 1
            && preg_match('/^d[12](?:\\/|$)/i', trim((string) $target->group_name)) === 1;
    }

    private function courseNumber($levelCode, $levelName): ?int
    {
        $code = (int) $levelCode;
        if ($code >= 11 && $code <= 16) {
            return $code - 10;
        }
        if ($code >= 1 && $code <= 6) {
            return $code;
        }
        if (preg_match('/([1-6])\\s*[- ]?\\s*kurs/i', (string) $levelName, $match)) {
            return (int) $match[1];
        }

        return null;
    }
}
