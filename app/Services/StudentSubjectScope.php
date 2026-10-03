<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * student_subjects jadvalini joriy o'quv yili bilan cheklash.
 *
 * MUAMMO. student_subjects HEMIS'dan yillar davomida yig'iladi va
 * (student_hemis_id, curriculum_subject_hemis_id) bo'yicha unique. semester_id
 * esa o'quv yiliga bog'lanmagan kod (masalan 13 — "3-semestr"), shuning uchun
 * o'tgan yilgi va bu yilgi biriktirishlar bitta semestr kodi ostida aralashadi.
 *
 * Oqibati: o'qishdan chetlashib qayta tiklangan talaba eski fanlari bilan
 * birga ro'yxatga tushadi. O'qituvchilar unga baho qo'ymaydi (to'g'ri qiladi —
 * u fanni o'tgan yili o'qib bo'lgan), lekin "baho qo'yilmaganlar" hisoboti
 * uni har bir fanda qarzdor deb ko'rsatadi.
 *
 * YECHIM. 2026-04-02 migratsiyasi student_subjects ga education_year ustunini
 * qo'shgan va HemisService uni to'ldiradi. Shu ustunni filtr sifatida
 * ishlatamiz: faqat joriy o'quv yilidagi biriktirishlar hisobga olinadi.
 *
 * QOIDA QAT'IY: biriktirma joriy o'quv yiliga tegishli bo'lishi shart. Boshqa
 * yil yoki boshqa semestr biriktirmasi hisobga olinmaydi, talabaning shu fandan
 * bahosi bo'lsa ham. Baho — o'tmishning izi, biriktirma esa hozir kim o'qiyotganini
 * belgilaydi; ikkisi ziddiyatga tushsa HEMIS biriktirmasi ustun turadi.
 *
 * EHTIYOT CHORASI. Ustun bo'sh bo'lgan o'rnatmalarda (eski ma'lumot, hali
 * sinxronlanmagan) filtr hamma narsani kesib tashlamasligi kerak — u holda
 * hisobotlar bo'shab qoladi va muammo kattalashadi. Shuning uchun:
 *   - ustun yo'q bo'lsa filtr qo'llanmaydi;
 *   - jadvalda to'ldirilgan education_year umuman bo'lmasa ham qo'llanmaydi;
 *   - qo'llanganda NULL qiymatlar ham o'tkaziladi (sinxronlanmagan qatorlar
 *     yo'qolib qolmasin).
 * Ya'ni filtr faqat ANIQ boshqa yilga tegishli qatorlarni chiqaradi.
 */
class StudentSubjectScope
{
    /** Kesh muddati (soniya): navbat ishchisi uzoq yashaydi, yil almashganda eskirmasin */
    private const CACHE_TTL = 600;

    /** Bir so'rov davomida takroriy tekshiruv bo'lmasin */
    private static ?bool $usable = null;

    private static ?string $year = null;

    private static bool $yearResolved = false;

    private static int $resolvedAt = 0;

    /**
     * Joriy o'quv yili (semesters.current = true dagi eng katta education_year).
     * Masalan "2026" yoki "2026-2027" — HEMIS qanday saqlagan bo'lsa shunday.
     */
    public static function currentYear(): ?string
    {
        if (self::$yearResolved && time() - self::$resolvedAt < self::CACHE_TTL) {
            return self::$year;
        }

        self::$yearResolved = true;
        self::$resolvedAt = time();
        self::$usable = null; // yil qayta aniqlandi — qo'llash tekshiruvi ham yangilansin

        try {
            $value = DB::table('semesters')
                ->where('current', true)
                ->whereNotNull('education_year')
                ->where('education_year', '!=', '')
                ->max('education_year');

            self::$year = $value !== null ? (string) $value : null;
        } catch (\Throwable $e) {
            Log::warning('StudentSubjectScope: joriy o\'quv yilini aniqlab bo\'lmadi: '.$e->getMessage());
            self::$year = null;
        }

        return self::$year;
    }

    /**
     * Filtrni qo'llash mumkinmi: ustun bor, joriy yil ma'lum va jadvalda
     * shu yilga tegishli kamida bitta qator bor.
     */
    public static function usable(): bool
    {
        // Avval yil (kesh muddati tugagan bo'lsa $usable ni ham tozalaydi)
        $year = self::currentYear();

        if (self::$usable !== null) {
            return self::$usable;
        }

        self::$usable = false;

        try {
            if (! Schema::hasColumn('student_subjects', 'education_year')) {
                return self::$usable;
            }
            if ($year === null) {
                return self::$usable;
            }

            // Joriy yil uchun ma'lumot umuman bo'lmasa filtr zarar qiladi
            self::$usable = DB::table('student_subjects')
                ->where('education_year', $year)
                ->exists();
        } catch (\Throwable $e) {
            Log::warning('StudentSubjectScope: tekshiruv xatosi: '.$e->getMessage());
            self::$usable = false;
        }

        return self::$usable;
    }

    /**
     * student_subjects so'roviga joriy o'quv yili filtrini qo'shish.
     *
     * $from/$to endi ishlatilmaydi (ilgari "shu davrda bahosi bor" istisnosi
     * uchun kerak edi). Imzo chaqiruvchilarni buzmaslik uchun saqlangan.
     *
     * @param  Builder  $query  student_subjects (yoki uning aliasi) bo'yicha so'rov
     * @param  string  $table  jadval nomi yoki alias — "ss" kabi
     * @param  string|null  $from  ishlatilmaydi
     * @param  string|null  $to  ishlatilmaydi
     */
    public static function apply(Builder $query, string $table = 'student_subjects', ?string $from = null, ?string $to = null): Builder
    {
        if (! self::usable()) {
            return $query;
        }

        $year = self::currentYear();
        $column = $table.'.education_year';

        // Joriy yil biriktirmasi. NULL ham o'tadi: sinxronlanmagan qator
        // yo'qolib ketmasin (yili noma'lum, aniq "boshqa yil" emas).
        return $query->where(function ($w) use ($column, $year) {
            $w->where($column, $year)->orWhereNull($column);
        });
    }

    /** Testlar va uzoq ishlaydigan jarayonlar uchun keshni tozalash */
    public static function flush(): void
    {
        self::$usable = null;
        self::$year = null;
        self::$yearResolved = false;
        self::$resolvedAt = 0;
    }
}
