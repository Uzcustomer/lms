<?php
// =====================================================================
// DEBUG: vedomost-tekshirish sinov test to'ldirishi nega ishlamayapti.
// Ishga tushirish:
//   php artisan tinker --execute="require 'debug_sinov_vedomost.php';"
// =====================================================================

use App\Services\JournalGradeService;
use App\Models\SinovTestGrade;
use Illuminate\Support\Facades\DB;

$sid = '312';
$sem = '16';

echo "==== 1) Shu fan/sem uchun SinovTestGrade bo'lgan guruhlar ====\n";
$groups = SinovTestGrade::where('subject_id', $sid)
    ->where('semester_code', $sem)
    ->select('group_hemis_id', DB::raw('count(*) as cnt'))
    ->groupBy('group_hemis_id')
    ->get();
foreach ($groups as $g) {
    printf("  group_hemis_id=[%s] (turi=%s) — %s yozuv\n",
        $g->group_hemis_id, gettype($g->group_hemis_id), $g->cnt);
}
if ($groups->isEmpty()) { echo "  YO'Q — bu fan/sem uchun umuman SinovTestGrade yo'q!\n"; return; }

$gh = (string) $groups->first()->group_hemis_id;
echo "\nTekshirilayotgan group_hemis_id = [$gh]\n";

echo "\n==== 2) students jadvali (group_id = group_hemis_id?) ====\n";
$studentHemisIds = DB::table('students')->where('group_id', $gh)->pluck('hemis_id')->toArray();
printf("  students.group_id=%s bo'yicha talabalar: %d\n", $gh, count($studentHemisIds));
printf("  namuna hemis_id: %s (turi=%s)\n",
    json_encode(array_slice($studentHemisIds, 0, 3)),
    isset($studentHemisIds[0]) ? gettype($studentHemisIds[0]) : 'NULL');

echo "\n==== 3) computeOnOskiTest natijasi (test, 102) ====\n";
$oot = JournalGradeService::computeOnOskiTest($gh, $sid, $sem, $studentHemisIds);
printf("  test mavjud talabalar: %d (bo'sh bo'lsa = student_grades da yo'q)\n", count($oot['test']));
printf("  namuna: %s\n", json_encode(array_slice($oot['test'], 0, 3, true)));

echo "\n==== 4) SinovTestGrade (group bilan) keyBy(student_hemis_id) ====\n";
$sinov = SinovTestGrade::where('subject_id', $sid)
    ->where('semester_code', $sem)
    ->where('group_hemis_id', $gh)
    ->get()
    ->keyBy('student_hemis_id');
printf("  group bilan SinovTestGrade soni: %d\n", $sinov->count());
printf("  kalit namunasi: %s (kalit turi=%s)\n",
    json_encode($sinov->keys()->slice(0, 3)->values()->all()),
    $sinov->isNotEmpty() ? gettype($sinov->keys()->first()) : 'NULL');
$first = $sinov->first();
if ($first) {
    printf("  1-yozuv: student=%s override=%s default=%s\n",
        $first->student_hemis_id, $first->override_grade ?? 'NULL', $first->default_grade ?? 'NULL');
}

echo "\n==== 5) MOSLIK testi: har talaba uchun get(\$hid) ishlayaptimi? ====\n";
$matched = 0; $filled = 0; $samples = [];
foreach ($studentHemisIds as $hid) {
    $sg = $sinov->get($hid);
    if ($sg) $matched++;
    $val = $sg ? ($sg->override_grade ?? $sg->default_grade) : null;
    if ($val === null) $val = $oot['test'][$hid] ?? null; // (debugda jn fallback o'rniga shuni ko'ramiz)
    if ($val !== null) $filled++;
    if (count($samples) < 5) {
        $samples[] = sprintf("hid=%s(%s) -> sg=%s val=%s", $hid, gettype($hid), $sg ? 'BOR' : 'YO\'Q', $val ?? 'NULL');
    }
}
printf("  get() mos kelgan: %d / %d\n", $matched, count($studentHemisIds));
printf("  to'ldiriladigan test soni: %d\n", $filled);
echo "  namunalar:\n";
foreach ($samples as $s) echo "    $s\n";

echo "\n==== XULOSA ====\n";
if ($groups->first()->group_hemis_id != $gh) echo "  - group_hemis_id turi mos emas\n";
if (count($studentHemisIds) === 0) echo "  - DIQQAT: students.group_id=$gh bo'yicha talaba topilmadi (group_id != group_hemis_id?)\n";
if ($sinov->isEmpty()) echo "  - DIQQAT: where('group_hemis_id',$gh) bo'yicha SinovTestGrade bo'sh — group_hemis_id mosligi buzilgan!\n";
if (!empty($studentHemisIds) && $matched === 0) echo "  - DIQQAT: get(\$hid) hech kimga mos kelmadi — kalit turi/qiymati mos emas!\n";
echo "  (yuqoridagi 'to'ldiriladigan test soni' > 0 bo'lsa, kod ishlashi kerak)\n";
