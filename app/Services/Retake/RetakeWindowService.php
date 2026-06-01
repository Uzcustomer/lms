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
            $reopen = $this->reopenUntil($window->end_date, $endDate);
            if ($reopen !== null) {
                $update['application_reopen_until'] = $reopen;
            }
        }

        $window->update($update);

        // Window ostidagi o'qish guruhlarining tugash sanasini ham uzaytiramiz
        // (faqat uzaytirish — qisqartirmaymiz). Shunda guruhda mustaqil ta'lim
        // yuklash va baho qo'yish ham yangi tugash sanasigacha ochiq turadi.
        $this->extendLinkedGroupEndDates([$window->id], $endDate);
    }

    /**
     * Berilgan oyna(lar) ostidagi o'qish guruhlarining (RetakeGroup) tugash
     * sanasini yangi sanagacha uzaytiradi — faqat hozirgi tugash sanasi
     * yangidan oldin bo'lsa (uzaytirish), va guruh tugamagan bo'lsa.
     *
     * Zanjir: window → retake_application_groups (window_id)
     *         → retake_applications (group_id) → retake_group_id.
     *
     * @return int Yangilangan guruhlar soni
     */
    public function extendLinkedGroupEndDates(array $windowIds, string $newEndDate): int
    {
        $windowIds = array_values(array_filter($windowIds));
        if (empty($windowIds)) {
            return 0;
        }

        $newEnd = Carbon::parse($newEndDate)->startOfDay();

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

        return RetakeGroup::query()
            ->whereIn('id', $groupIds)
            ->where('status', '!=', RetakeGroup::STATUS_COMPLETED)
            ->whereDate('end_date', '<', $newEnd)
            ->update(['end_date' => $newEnd->toDateString()]);
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
