<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Tyutor uchun guruh starostalarini (yetakchilarini) belgilash.
 *
 * Tyutorga biriktirilgan faol guruhlar va ulardagi talabalar ro'yxatini beradi;
 * tanlangan talabani o'z guruhining starostasi qilib belgilaydi (bir guruhda
 * faqat bitta starosta). Natija talabaning o'z yozuviga (students.is_starosta)
 * saqlanadi, shu bois boshqa joylarda ham ishlatish mumkin.
 */
class TutorStarostaController extends Controller
{
    /**
     * Tyutorning guruhlari + har guruhdagi talabalar (accordion uchun).
     */
    public function groups(): JsonResponse
    {
        if (! is_active_tyutor()) {
            abort(403);
        }

        $teacher = get_tyutor_teacher();
        if (! $teacher) {
            return response()->json(['groups' => []]);
        }

        $groups = $teacher->groups()
            ->where('groups.active', true)
            ->orderBy('groups.name')
            ->get(['groups.id', 'groups.group_hemis_id', 'groups.name']);

        $groupHemisIds = $groups->pluck('group_hemis_id')->filter()->values()->all();

        $studentsByGroup = Student::query()
            ->whereIn('group_id', $groupHemisIds)
            ->where('student_status_code', 11) // faqat faol (o'qiyotgan) talabalar
            ->orderByDesc('is_starosta')
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'group_id', 'is_starosta', 'student_id_number'])
            ->groupBy('group_id');

        $data = $groups->map(function ($group) use ($studentsByGroup) {
            $students = $studentsByGroup->get($group->group_hemis_id, collect());
            $starosta = $students->firstWhere('is_starosta', true);

            return [
                'id' => $group->id,
                'name' => $group->name,
                'student_count' => $students->count(),
                'starosta' => $starosta ? [
                    'id' => $starosta->id,
                    'name' => $starosta->full_name,
                ] : null,
                'students' => $students->map(fn ($s) => [
                    'id' => $s->id,
                    'name' => $s->full_name,
                    'student_id_number' => $s->student_id_number,
                    'is_starosta' => (bool) $s->is_starosta,
                ])->values(),
            ];
        })->values();

        return response()->json([
            'groups' => $data,
            'has_missing' => $data->contains(fn ($g) => $g['starosta'] === null && $g['student_count'] > 0),
        ]);
    }

    /**
     * Tanlangan talabani o'z guruhining starostasi qilib belgilash.
     */
    public function set(Request $request): JsonResponse
    {
        if (! is_active_tyutor()) {
            abort(403);
        }

        $teacher = get_tyutor_teacher();
        if (! $teacher) {
            abort(403);
        }

        $validated = $request->validate([
            'student_id' => ['required', 'integer', 'exists:students,id'],
        ]);

        $student = Student::findOrFail($validated['student_id']);

        // Xavfsizlik: talaba tyutorning faol guruhlaridan biriga tegishli bo'lishi shart.
        $groupHemisIds = $teacher->groups()
            ->where('groups.active', true)
            ->pluck('groups.group_hemis_id')
            ->all();

        if (! in_array($student->group_id, $groupHemisIds, true)) {
            abort(403, 'Bu talaba sizning guruhlaringizga tegishli emas.');
        }

        DB::transaction(function () use ($student) {
            // Bir guruhda faqat bitta starosta — avvalgilarini bekor qilish.
            Student::where('group_id', $student->group_id)
                ->where('is_starosta', true)
                ->where('id', '!=', $student->id)
                ->update(['is_starosta' => false]);

            if (! $student->is_starosta) {
                $student->is_starosta = true;
                $student->save();
            }
        });

        return response()->json([
            'success' => true,
            'group_hemis_id' => $student->group_id,
            'student' => [
                'id' => $student->id,
                'name' => $student->full_name,
            ],
        ]);
    }
}
