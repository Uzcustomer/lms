<?php

namespace App\Services\Retake;

use App\Models\RetakeApplication;
use App\Models\RetakeApplicationWindow;
use App\Models\RetakeGroup;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class RetakeWindowService
{
    /**
     * Talabaning yo'nalish va kursi uchun joriy faol oyna.
     * Faqat ochiq sessiyadagi oynalar e'tiborga olinadi — yopilgan
     * sessiyadagi oyna ko'rinmaydi.
     */
    public function activeWindowForStudent(Student $student): ?RetakeApplicationWindow
    {
        if (!$student->specialty_id || !$student->level_code) {
            return null;
        }

        return RetakeApplicationWindow::query()
            ->forStudent((int) $student->specialty_id, $student->level_code, (string) ($student->department_id ?? ''), (string) ($student->specialty_name ?? ''))
            ->active()
            ->whereHas('session', fn ($q) => $q->where('is_closed', false))
            ->orderByDesc('end_date')
            ->first();
    }

    /**
     * Talabaning oynalari tarixi (eski + joriy).
     *
     * @return Collection<RetakeApplicationWindow>
     */
    public function windowsForStudent(Student $student): Collection
    {
        if (!$student->specialty_id || !$student->level_code) {
            return new Collection();
        }

        return RetakeApplicationWindow::query()
            ->forStudent((int) $student->specialty_id, $student->level_code, (string) ($student->department_id ?? ''), (string) ($student->specialty_name ?? ''))
            ->orderByDesc('start_date')
            ->get();
    }

    /**
     * Yangi qabul oynasini ochish (O'quv bo'limi).
     * `session_id` majburiy — har oyna albatta sessiyaga bog'langan bo'lishi kerak.
     * Bir xil yo'nalish/kurs/semestr kombinatsiyasi har sessiyada bittadan bo'lishi mumkin
     * (boshqa sessiyada — alohida oyna).
     */
    public function createWindow(array $data, Teacher $createdBy): RetakeApplicationWindow
    {
        $this->validateDateRange($data['start_date'], $data['end_date']);

        if (empty($data['session_id'])) {
            throw ValidationException::withMessages([
                'session_id' => 'Sessiyani tanlash majburiy',
            ]);
        }

        // Bir xil (sessiya, fakultet, yo'nalish, kurs, semestr) kombinatsiyasi
        // takroriy oyna sifatida yaratilishi ruxsat etiladi (foydalanuvchi
        // talabiga ko'ra). Avvalgi unique check va trashed-cleanup logikalari
        // olib tashlandi.

        $payload = [
            ...$data,
            'created_by_user_id' => $createdBy->id,
            'created_by_name' => $createdBy->full_name,
        ];

        // Migration hali qo'llanmagan bo'lsa yangi ustunlar yo'q — 500 oldini olish
        foreach (['creation_batch_id', 'department_hemis_id'] as $col) {
            if (isset($payload[$col]) &&
                !\Illuminate\Support\Facades\Schema::hasColumn('retake_application_windows', $col)) {
                unset($payload[$col]);
            }
        }

        return RetakeApplicationWindow::create($payload);
    }

    /**
     * Sanalarni o'zgartirish — faqat super-admin override.
     */
    public function overrideDates(RetakeApplicationWindow $window, string $startDate, string $endDate): void
    {
        $this->validateDateRange($startDate, $endDate);

        $update = [
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];

        // Tugash sanasi uzaytirilsa — necha kunga uzaytirilgan bo'lsa,
        // bugundan boshlab shuncha kun ariza qabuli qayta ochiladi.
        // (Migration ishga tushgan bo'lsagina — aks holda jim qoladi.)
        if (RetakeApplicationWindow::supportsReopen()) {
            $update['application_reopen_until'] = $this->reopenUntil($window->end_date, $endDate);
        }

        $window->update($update);

        // Override har doim ostidagi o'qish guruhlariga sinxronlanadi: guruh
        // sanasi yangi oyna sanasidan orqada bo'lsa uzaytiriladi, qulfi ochiladi
        // va completed bo'lsa qayta faollashtiriladi. Bu avval uzaytirilgan,
        // ammo guruhga tushmay qolgan holatlarni ham to'g'rilaydi.
        $this->extendLinkedGroupEndDates([$window->id], $endDate);
    }

    /**
     * Berilgan oyna(lar) ostidagi o'qish guruhlarining (RetakeGroup):
     *  1) tugash sanasini yangi sanagacha uzaytiradi (faqat sanasi yangidan
     *     oldin bo'lganlarni — qisqartirmaydi);
     *  2) QULFINI ochadi (yakuniy qilingan bo'lsa ham);
     *  3) status `completed` bo'lib qolgan guruhlarni qayta faollashtiradi
     *     (auto-cron muddat o'tganda completed qilib qo'ygan bo'lishi mumkin) —
     *     yangi end_date >= bugun bo'lsa `in_progress`/`scheduled`'ga qaytaradi.
     *
     * Shu orqali muddat uzaytirilganda mustaqil ta'lim yuklash va baho qo'yish
     * yangi tugash sanasigacha to'liq qayta ochiladi.
     *
     * Zanjir: window → retake_application_groups (window_id)
     *         → retake_applications (group_id) → retake_group_id.
     *
     * @return int Sana uzaytirilgan guruhlar soni
     */
    /**
     * @param  array<int>  $windowIds
     * @param  string|null $newEndDate Endi ishlatilmaydi (orqaga moslik uchun
     *                                 saqlangan) — helper har guruh uchun
     *                                 hissa qo'shgan barcha oynalarning eng
     *                                 kech end_date'idan boshlab o'zi hisoblaydi.
     */
    public function extendLinkedGroupEndDates(array $windowIds, ?string $newEndDate = null): int
    {
        $windowIds = array_values(array_filter($windowIds));
        if (empty($windowIds)) {
            return 0;
        }

        $today = Carbon::today();

        // Shu oyna(lar) ostidagi o'qish guruhlari
        $groupIds = RetakeApplication::query()
            ->whereNotNull('retake_group_id')
            ->whereIn('group_id', function ($q) use ($windowIds) {
                $q->select('id')
                    ->from('retake_application_groups')
                    ->whereIn('window_id', $windowIds);
            })
            ->distinct()
            ->pluck('retake_group_id')
            ->all();

        if (empty($groupIds)) {
            return 0;
        }

        // Har bir guruh uchun hissa qo'shgan BARCHA oynalarning eng kech tugash
        // sanasini hisoblaymiz — guruh sanasi shu eng kech oyna sanasiga teng
        // bo'ladi (aralash guruhlar uchun ham to'g'ri).
        $maxEnds = RetakeApplication::query()
            ->join('retake_application_groups as rag', 'rag.id', '=', 'retake_applications.group_id')
            ->join('retake_application_windows as w', 'w.id', '=', 'rag.window_id')
            ->whereNull('w.deleted_at')
            ->whereIn('retake_applications.retake_group_id', $groupIds)
            ->groupBy('retake_applications.retake_group_id')
            ->selectRaw('retake_applications.retake_group_id as gid, MAX(w.end_date) as max_end')
            ->pluck('max_end', 'gid');

        $changed = 0;
        $groups = RetakeGroup::query()->whereIn('id', $groupIds)->get();

        foreach ($groups as $group) {
            $target = $maxEnds->get($group->id);
            if (!$target) {
                continue;
            }
            $targetEnd = Carbon::parse($target)->startOfDay();

            $update = [];

            // Guruh sanasi oynaning eng kech sanasiga teng bo'lsin
            // (faqat oldinga — qisqartirmaymiz, boshqa ishlar buzilmasin uchun).
            $curEnd = $group->end_date ? Carbon::parse($group->end_date)->startOfDay() : null;
            if ($curEnd === null || !$curEnd->equalTo($targetEnd)) {
                $update['end_date'] = $targetEnd->toDateString();
            }

            $effectiveEnd = isset($update['end_date']) ? $targetEnd : $curEnd;

            // Muddat hali amal qilsa — qulfni ochamiz va completed'ni qaytaramiz
            if ($effectiveEnd && $effectiveEnd->gte($today)) {
                if ($group->is_locked) {
                    $update['is_locked'] = false;
                    $update['locked_at'] = null;
                    $update['locked_by_user_id'] = null;
                    $update['locked_by_name'] = null;
                }
                if ($group->status === RetakeGroup::STATUS_COMPLETED) {
                    $startInFuture = $group->start_date && Carbon::parse($group->start_date)->startOfDay()->gt($today);
                    $update['status'] = $startInFuture
                        ? RetakeGroup::STATUS_SCHEDULED
                        : RetakeGroup::STATUS_IN_PROGRESS;
                }
            } elseif ($effectiveEnd && $effectiveEnd->lt($today) && $group->status !== RetakeGroup::STATUS_COMPLETED) {
                $update['status'] = RetakeGroup::STATUS_COMPLETED;
            }

            if (!empty($update)) {
                $group->update($update);
                $changed++;
            }
        }

        return $changed;
    }

    /**
     * Eski va yangi tugash sanalariga qarab ariza qayta ochilish sanasini
     * hisoblaydi. Tugash sanasi uzaytirilgan bo'lsagina (N > 0) — bugundan
     * +N kun qaytaradi, aks holda null.
     */
    public function reopenUntil($oldEndDate, string $newEndDate): ?\Illuminate\Support\Carbon
    {
        if ($oldEndDate === null) {
            return null;
        }
        $old = \Illuminate\Support\Carbon::parse($oldEndDate)->startOfDay();
        $new = \Illuminate\Support\Carbon::parse($newEndDate)->startOfDay();

        $extraDays = $old->diffInDays($new, false);
        if ($extraDays <= 0) {
            return null;
        }

        return \Illuminate\Support\Carbon::today()->addDays($extraDays);
    }

    private function validateDateRange(string $start, string $end): void
    {
        if (strtotime($start) > strtotime($end)) {
            throw ValidationException::withMessages([
                'end_date' => 'Tugash sanasi boshlanish sanasidan keyin bo\'lishi kerak',
            ]);
        }
    }
}
