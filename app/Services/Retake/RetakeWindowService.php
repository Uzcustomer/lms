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

        // Uzaytirishmi? (yangi tugash sanasi eskisidan keyinmi)
        $oldEnd = $window->end_date ? Carbon::parse($window->end_date)->startOfDay() : null;
        $newEnd = Carbon::parse($endDate)->startOfDay();
        $isExtension = $oldEnd === null || $newEnd->gt($oldEnd);

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

        // Window UZAYTIRILSA — ostidagi o'qish guruhlarining tugash sanasi ham
        // uzayadi va qulfi ochiladi. Shunda guruhda mustaqil ta'lim yuklash va
        // baho qo'yish yangi tugash sanasigacha ochiq turadi (yakuniy qilingan
        // bo'lsa ham). Qisqartirishda guruhlar tegmaydi.
        if ($isExtension) {
            $this->extendLinkedGroupEndDates([$window->id], $endDate);
        }
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
    public function extendLinkedGroupEndDates(array $windowIds, string $newEndDate): int
    {
        $windowIds = array_values(array_filter($windowIds));
        if (empty($windowIds)) {
            return 0;
        }

        $newEnd = Carbon::parse($newEndDate)->startOfDay();
        $today = Carbon::today();

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

        // 1) Tugash sanasini uzaytirish — faqat yangidan oldin bo'lganlarni
        //    (completed bo'lsa ham — auto-cron tugagan deb belgilab qo'ygan
        //    bo'lishi mumkin, baribir uzaytiramiz).
        $extended = RetakeGroup::query()
            ->whereIn('id', $groupIds)
            ->whereDate('end_date', '<', $newEnd)
            ->update(['end_date' => $newEnd->toDateString()]);

        // 2) Qulfni ochish — bog'liq barcha guruhlar uchun (status'dan qat'i
        //    nazar). Muddat uzaytirilgani uchun yakuniy holatni bekor qilamiz.
        RetakeGroup::query()
            ->whereIn('id', $groupIds)
            ->where('is_locked', true)
            ->update([
                'is_locked' => false,
                'locked_at' => null,
                'locked_by_user_id' => null,
                'locked_by_name' => null,
            ]);

        // 3) Completed guruhlarni qayta faollashtirish — agar yangi end_date
        //    bugun yoki keyin bo'lsa (cron oldin "tugagan" deb belgilab qo'ygan
        //    bo'lishi mumkin). start_date kelajakdami yoki o'tganmiga qarab
        //    scheduled/in_progress'ga qaytaramiz.
        if ($newEnd->gte($today)) {
            RetakeGroup::query()
                ->whereIn('id', $groupIds)
                ->where('status', RetakeGroup::STATUS_COMPLETED)
                ->whereDate('start_date', '<=', $today)
                ->update(['status' => RetakeGroup::STATUS_IN_PROGRESS]);

            RetakeGroup::query()
                ->whereIn('id', $groupIds)
                ->where('status', RetakeGroup::STATUS_COMPLETED)
                ->whereDate('start_date', '>', $today)
                ->update(['status' => RetakeGroup::STATUS_SCHEDULED]);
        }

        return $extended;
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
