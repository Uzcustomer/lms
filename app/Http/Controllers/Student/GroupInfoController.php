<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\DistributionDraftAssignment;
use App\Models\Group;
use App\Services\DistributionCatalog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/**
 * "Guruh ma'lumotlarim" — talabaning guruhi va tyutori.
 *
 * Talaba taqsimotda boshqa guruhga o'tkazilgan bo'lsa YANGI guruh ko'rsatiladi:
 * profildagi guruh HEMISdan keladi va reja unga hali qo'llanmagan. Tyutor esa
 * ko'chirishda o'zgarmaydi — u doim hozirgi HEMIS guruhi bo'yicha olinadi.
 */
class GroupInfoController extends Controller
{
    public function __construct(private DistributionCatalog $catalog)
    {
    }

    public function show(): View
    {
        $student = Auth::guard('student')->user();

        $draft = Schema::hasTable('distribution_draft_assignments')
            ? DistributionDraftAssignment::query()->where('student_id', $student->id)->first()
            : null;

        $groupName = $draft ? $draft->to_group_name : $student->group_name;

        // Talaba sahifani ochdi — registrator ro'yxatidagi "ko'rdi" ustuni
        // uchun birinchi ko'rish vaqti yoziladi. Query builder orqali, aks holda
        // updated_at ham o'zgarib ketardi.
        if ($draft && $draft->seen_at === null
            && Schema::hasColumn('distribution_draft_assignments', 'seen_at')) {
            DistributionDraftAssignment::query()
                ->where('id', $draft->id)
                ->update(['seen_at' => now()]);
        }

        return view('student.group-info', [
            'student' => $student,
            'draft' => $draft,
            'groupName' => $groupName,
            // Tyutor ko'chirishda o'zgarmaydi — hozirgi (HEMIS) guruh bo'yicha
            // olinadi, admin talaba sahifasi bilan bir xil.
            'tutors' => $this->tutorsFor((int) $student->group_id, $student->group_name),
        ]);
    }

    /**
     * Guruh tyutorlari.
     *
     * Tyutor guruhga `group_teacher` jadvali orqali biriktiriladi (HEMISdagi
     * tutorGroups, import:teachers yozadi). HEMIS bitta guruhni ba'zan ikki id
     * bilan saqlaydi ("d1/25-01(b)" va "d1/d25-01(b)") — tyutor ikkinchisiga
     * biriktirilgan bo'lishi mumkin, shu sabab id bo'yicha topilmasa nomi bir
     * xil faol guruhlardan qidiriladi.
     */
    private function tutorsFor(int $groupHemisId, ?string $groupName): Collection
    {
        $groups = Group::query()->where('group_hemis_id', $groupHemisId)->get();

        $tutors = $this->activeTutors($groups);
        if ($tutors->isNotEmpty() || !$groupName) {
            return $tutors;
        }

        $key = $this->catalog->groupNameKey($groupName);
        $sameName = Group::query()
            ->where('active', true)
            ->get(['id', 'name'])
            ->filter(fn (Group $group) => $this->catalog->groupNameKey($group->name) === $key);

        return $this->activeTutors($sameName);
    }

    /**
     * Guruhlarga biriktirilgan tyutorlar: faollari bo'lsa faqat ular, aks
     * holda hammasi. Admin sahifasi faollikni tekshirmaydi — u ko'rsatgan
     * tyutor bu yerda yashirinib qolmasligi kerak, lekin faol va nofaol
     * birga bo'lsa ketgan xodim chiqmaydi.
     */
    private function activeTutors(Collection $groups): Collection
    {
        if ($groups->isEmpty()) {
            return collect();
        }

        $tutors = Group::query()
            ->whereIn('id', $groups->pluck('id'))
            ->with(['teachers' => fn ($query) => $query
                ->select('teachers.id', 'teachers.full_name', 'teachers.phone', 'teachers.telegram_username', 'teachers.is_active')])
            ->get()
            ->flatMap(fn (Group $group) => $group->teachers)
            ->unique('id')
            ->values();

        $active = $tutors->filter(fn ($tutor) => (bool) $tutor->is_active)->values();

        return $active->isNotEmpty() ? $active : $tutors;
    }
}
