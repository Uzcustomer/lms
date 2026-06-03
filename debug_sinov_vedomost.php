<?php
// =====================================================================
// DEBUG: vedomost-tekshirish sinov test to'ldirishi.
//  - A qism: BARCHA sinov (subject,sem,group) larda test qayerdan keladi
//    (student_grades 102 BORmi = "ishlaydi", yoki faqat SinovTestGrade =
//    mening fallback kerak).
//  - B qism: subject 312 / sem 16 guruhini bosqichma-bosqich trace.
//
// Ishga tushirish:
//   php artisan tinker --execute="require 'debug_sinov_vedomost.php';"
// =====================================================================

use App\Services\JournalGradeService;
use App\Models\SinovTestGrade;
use Illuminate\Support\Facades\DB;

// ---------------------------------------------------------------------
echo "==== A) Sinov (subject,sem,group) lar: test manbai ====\n";
$combos = SinovTestGrade::select('subject_id', 'semester_code', 'group_hemis_id')
    ->distinct()->get();
echo "Jami sinov (subject,sem,group) kombinatsiyalari: " . $combos->count() . "\n";

$withSg = []; $onlyStg = [];
foreach ($combos as $c) {
    $hemisIds = DB::table('students')->where('group_id', $c->group_hemis_id)->pluck('hemis_id');
    $has = false;
    if ($hemisIds->isNotEmpty()) {
        $has = DB::table('student_grades')
            ->where('subject_id', $c->subject_id)
            ->where('semester_code', $c->semester_code)
            ->whereIn('student_hemis_id', $hemisIds)
            ->where('training_type_code', 102)
            ->whereNull('deleted_at')
            ->exists();
    }
    $label = "{$c->subject_id}/{$c->semester_code}/{$c->group_hemis_id}";
    if ($has) $withSg[] = $label; else $onlyStg[] = $label;
}
printf("  [ISHLAYDI] student_grades 102 BOR (computeOnOskiTest ko'rsatadi): %d\n", count($withSg));
foreach (array_slice($withSg, 0, 6) as $x) echo "      $x\n";
printf("  [FALLBACK KERAK] faqat SinovTestGrade: %d\n", count($onlyStg));
foreach (array_slice($onlyStg, 0, 6) as $x) echo "      $x\n";

// ---------------------------------------------------------------------
echo "\n==== B) Trace: subject 312 / sem 16 ====\n";
$sid = '312';
$sem = '16';

$groups = SinovTestGrade::where('subject_id', $sid)->where('semester_code', $sem)
    ->select('group_hemis_id', DB::raw('count(*) as cnt'))->groupBy('group_hemis_id')->get();
echo "Guruhlar (SinovTestGrade):\n";
foreach ($groups as $g) printf("  group_hemis_id=[%s] turi=%s — %s yozuv\n", $g->group_hemis_id, gettype($g->group_hemis_id), $g->cnt);
if ($groups->isEmpty()) { echo "  YO'Q!\n"; return; }

$gh = (string) $groups->first()->group_hemis_id;
echo "Tekshirilayotgan group_hemis_id=[$gh]\n";

$studentHemisIds = DB::table('students')->where('group_id', $gh)->pluck('hemis_id')->toArray();
printf("students.group_id=%s talabalar: %d | namuna: %s (turi=%s)\n",
    $gh, count($studentHemisIds), json_encode(array_slice($studentHemisIds, 0, 3)),
    isset($studentHemisIds[0]) ? gettype($studentHemisIds[0]) : 'NULL');

$oot = JournalGradeService::computeOnOskiTest($gh, $sid, $sem, $studentHemisIds);
printf("computeOnOskiTest: test mavjud talabalar=%d namuna=%s\n",
    count($oot['test']), json_encode(array_slice($oot['test'], 0, 3, true)));

$sinov = SinovTestGrade::where('subject_id', $sid)->where('semester_code', $sem)
    ->where('group_hemis_id', $gh)->get()->keyBy('student_hemis_id');
printf("SinovTestGrade (group=%s) soni=%d | kalit namuna=%s (turi=%s)\n",
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
printf("get(\$hid) mos: %d/%d | to'ldiriladigan: %d\n", $matched, count($studentHemisIds), $filled);
foreach ($samples as $s) echo "  $s\n";

echo "\n==== XULOSA ====\n";
if (empty($studentHemisIds)) echo "  - students.group_id=$gh bo'yicha talaba YO'Q (group_id != group_hemis_id?)\n";
elseif ($sinov->isEmpty()) echo "  - where('group_hemis_id',$gh) SinovTestGrade BO'SH — group_hemis_id mos emas!\n";
elseif ($matched === 0) echo "  - get(\$hid) hech kimga mos kelmadi — kalit turi/qiymati mos emas!\n";
elseif ($filled > 0) echo "  - to'ldirish ISHLASHI kerak ($filled ta). Agar Excel'da chiqmasa, boshqa sabab (weight yoki calcYn).\n";
