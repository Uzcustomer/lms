<?php
// =====================================================================
// DEBUG: sinov fanlari test bahosi holati (ikki tarixiy holat + bugungi
// o'chirishni hisobga olib).
//
//  Holat 1: "YN ga jo'natish" bosilgan -> student_grades sinov_yn_test + YnSubmission
//  Holat 2: bosilmagan -> faqat SinovTestGrade (JN asosida)
//  Bugun:   ziddiyatli soxta sinov_yn_test qatorlar soft-delete qilindi
//
// Ishga tushirish:
//   php artisan tinker --execute="require 'debug_sinov_vedomost.php';"
// =====================================================================

use App\Services\JournalGradeService;
use App\Models\SinovTestGrade;
use Illuminate\Support\Facades\DB;

$combos = SinovTestGrade::select('subject_id', 'semester_code', 'group_hemis_id')
    ->distinct()->get();
echo "Jami sinov (subject,sem,group): " . $combos->count() . "\n\n";

$c1_ok = 0;       // YN bosilgan + student_grades 102 BOR (ishlaydi)
$c1_gap = [];     // YN bosilgan, lekin student_grades 102 YO'Q (DIQQAT: bugun o'chirilgan?)
$c2_fallback = 0; // YN bosilmagan, faqat SinovTestGrade (fallback kerak)
$c2_nostg = [];   // na YN, na 102, na SinovTestGrade default (bahosiz)

foreach ($combos as $c) {
    $hemisIds = DB::table('students')->where('group_id', $c->group_hemis_id)->pluck('hemis_id');
    $hasSg = $hemisIds->isNotEmpty() && DB::table('student_grades')
        ->where('subject_id', $c->subject_id)->where('semester_code', $c->semester_code)
        ->whereIn('student_hemis_id', $hemisIds)
        ->where('training_type_code', 102)->whereNull('deleted_at')->exists();
    $hasSub = DB::table('yn_submissions')
        ->where('subject_id', $c->subject_id)->where('semester_code', $c->semester_code)
        ->where('group_hemis_id', $c->group_hemis_id)->exists();
    $label = "{$c->subject_id}/{$c->semester_code}/{$c->group_hemis_id}";

    if ($hasSub) {
        if ($hasSg) $c1_ok++;
        else $c1_gap[] = $label; // YN yuborilgan, lekin 102 yo'q -> ehtimol bugun o'chirilgan
    } else {
        if ($hasSg) $c1_ok++;            // YN yo'q, lekin 102 bor (eski backfill) -> baribir ishlaydi
        else $c2_fallback++;             // faqat SinovTestGrade -> mening fallback kerak
    }
}
echo "==== A) Holat taqsimoti ====\n";
printf("  [ISHLAYDI] student_grades 102 BOR (Holat1/backfill): %d\n", $c1_ok);
printf("  [FALLBACK] faqat SinovTestGrade, 102 yo'q (Holat2): %d\n", $c2_fallback);
printf("  [DIQQAT] YN yuborilgan, lekin 102 YO'Q (bugun o'chgan?): %d\n", count($c1_gap));
foreach (array_slice($c1_gap, 0, 10) as $x) echo "      $x\n";

// ---------------------------------------------------------------------
echo "\n==== B) Bugungi soft-delete: o'chirilgan sinov_yn_test qatorlar ====\n";
$del = DB::table('student_grades')
    ->where('training_type_code', 102)->where('reason', 'sinov_yn_test')
    ->whereNotNull('deleted_at')
    ->select('student_hemis_id', 'subject_id', 'semester_code', 'grade', 'deleted_at')
    ->get();
printf("  Jami o'chirilgan sinov_yn_test: %d\n", $del->count());
$gapAfterDelete = 0;
foreach ($del as $d) {
    // O'chirilgandan keyin shu talaba/fan/sem da boshqa (real) 102 qoldimi?
    $remain = DB::table('student_grades')
        ->where('student_hemis_id', $d->student_hemis_id)
        ->where('subject_id', $d->subject_id)->where('semester_code', $d->semester_code)
        ->where('training_type_code', 102)->whereNull('deleted_at')->exists();
    if (!$remain) $gapAfterDelete++;
}
printf("  Ulardan o'rnida BOSHQA 102 qolmaganlari (bahosiz qolish xavfi): %d\n", $gapAfterDelete);
echo "  (0 bo'lsa = o'chirish hech kimni bahosiz qoldirmagan)\n";

// ---------------------------------------------------------------------
echo "\n==== C) Trace: subject 312 / sem 16 ====\n";
$sid = '312'; $sem = '16';
$groups = SinovTestGrade::where('subject_id', $sid)->where('semester_code', $sem)
    ->select('group_hemis_id', DB::raw('count(*) as cnt'))->groupBy('group_hemis_id')->get();
foreach ($groups as $g) printf("  group=[%s] turi=%s — %s yozuv\n", $g->group_hemis_id, gettype($g->group_hemis_id), $g->cnt);
if ($groups->isEmpty()) { echo "  YO'Q!\n"; return; }
$gh = (string) $groups->first()->group_hemis_id;

$studentHemisIds = DB::table('students')->where('group_id', $gh)->pluck('hemis_id')->toArray();
printf("  students.group_id=%s talabalar=%d namuna=%s (turi=%s)\n",
    $gh, count($studentHemisIds), json_encode(array_slice($studentHemisIds, 0, 3)),
    isset($studentHemisIds[0]) ? gettype($studentHemisIds[0]) : 'NULL');

$oot = JournalGradeService::computeOnOskiTest($gh, $sid, $sem, $studentHemisIds);
printf("  computeOnOskiTest test=%d\n", count($oot['test']));

$sinov = SinovTestGrade::where('subject_id', $sid)->where('semester_code', $sem)
    ->where('group_hemis_id', $gh)->get()->keyBy('student_hemis_id');
printf("  SinovTestGrade(group=%s)=%d kalit_namuna=%s (turi=%s)\n",
    $gh, $sinov->count(), json_encode($sinov->keys()->slice(0, 3)->values()->all()),
    $sinov->isNotEmpty() ? gettype($sinov->keys()->first()) : 'NULL');

$matched = 0; $filled = 0; $samples = [];
foreach ($studentHemisIds as $hid) {
    $sg = $sinov->get($hid);
    if ($sg) $matched++;
    $val = $sg ? ($sg->override_grade ?? $sg->default_grade) : null;
    if ($val !== null) $filled++;
    if (count($samples) < 5) $samples[] = sprintf("hid=%s(%s) sg=%s val=%s", $hid, gettype($hid), $sg ? 'BOR' : 'YOQ', $val ?? 'NULL');
}
printf("  get(\$hid) mos=%d/%d to'ldiriladigan=%d\n", $matched, count($studentHemisIds), $filled);
foreach ($samples as $s) echo "    $s\n";

echo "\n==== XULOSA (312) ====\n";
if (empty($studentHemisIds)) echo "  - students.group_id=$gh talaba YO'Q (group_id != group_hemis_id?)\n";
elseif ($sinov->isEmpty()) echo "  - where('group_hemis_id',$gh) SinovTestGrade BO'SH — group_hemis_id mos emas!\n";
elseif ($matched === 0) echo "  - get(\$hid) mos kelmadi — kalit turi/qiymati mos emas!\n";
elseif ($filled > 0) echo "  - to'ldirish ISHLASHI kerak ($filled ta).\n";
