<?php

namespace App\Http\Controllers\Admin\Concerns;

use Illuminate\Http\Request;

/**
 * Bugungi darsni ko'chirish huquqi. Admin, superadmin va registrator ofisi (faol rol) — bugungi darsni har qanday holatda (boshlangan,
 * hatto tugagan bo'lsa ham) bugunning istalgan parasiga ko'chira oladi; o'tgan kunlar (bugundan oldin) — hamma uchun yopiq. Boshqa rollar
 * (o'qituvchi, fan mas'uli, kafedra mudiri) — avvalgidek: bugungi va o'tgan darslar ko'chirilmaydi.
 *
 * Tasdiqlangan so'rovni xodim qayta bajarganda (TimetableChangeApproval) so'rov kafedra mudiri nomidan bajariladi — bugungi kun ochiqligi
 * "timetable.staff_today" belgisi bilan uzatiladi (foydalanuvchi kiritmasidan emas, faqat server qo'yadi).
 */
trait TimetableStaffToday
{
    /** Bugungi darsni ko'chira oladigan rollar (faol rol). */
    public const TODAY_STAFF_ROLES = \App\Http\Controllers\Admin\TimetableController::TIMETABLE_FULL_EDIT_ROLES;

    /** Bugun ochiqmi: faol rol — admin / superadmin / registrator ofisi yoki xodim tasdiqlagan so'rovni qayta bajarish. */
    private function staffTodayOk(Request $request): bool
    {
        return $request->attributes->get('timetable.staff_today') === true
            || in_array($this->timetableActiveRole($request), self::TODAY_STAFF_ROLES, true);
    }

    /** Sana ko'chirish uchun yopiqmi (o'tgan): xodimga — bugundan oldingi kun, boshqalarga — bugun va undan oldin. */
    private function isPastFor(Request $request, string $date): bool
    {
        return self::pastFor($this->staffTodayOk($request), $date);
    }

    /** $staff — bugun ochiq (faqat o'tgan kun yopiq); aks holda bugun ham yopiq. Bugun — Asia/Tashkent. */
    public static function pastFor(bool $staff, string $date): bool
    {
        $today = self::proposalToday();

        return $staff ? $date < $today : $date <= $today;
    }

    /** Xodim bo'lmaganga bugungi darsni ko'chirish rad etilganda qo'shiladigan izoh. */
    public static function todayStaffOnlyText(): string
    {
        return "Bugungi darsni faqat admin va registrator ofisi ko'chiradi.";
    }

    /**
     * Bugungi dars ko'chirildi — o'zgarishlar tarixi yozuviga belgi (TimetableAssignmentAccess::runRecorded va so'rovni qayta bajarish
     * summary ga qo'shadi) va javobda HEMIS eslatmasi uchun.
     */
    private function markTodayMoved(Request $request): void
    {
        self::addHistoryNote($request, 'bugungi dars ko‘chirildi');
        $request->attributes->set('timetable.today_moved', true);
    }

    /** O'zgarishlar tarixiga izoh (bir nechta bo'lsa — vergul bilan). */
    public static function addHistoryNote(Request $request, string $note): void
    {
        $notes = (array) $request->attributes->get('timetable.history_notes', []);
        if (!in_array($note, $notes, true)) {
            $notes[] = $note;
        }
        $request->attributes->set('timetable.history_notes', $notes);
    }

    /** HEMIS eslatmasi (bugungi dars ko'chirilganda) — front tasdiq oynasi / panelida ham shu matn. */
    public static function todayHemisHint(): string
    {
        return 'Bugungi dars — ko‘chirgandan keyin HEMIS dagi jadval va davomatni «Bugungi darslar (HEMIS)» orqali solishtiring.';
    }
}
