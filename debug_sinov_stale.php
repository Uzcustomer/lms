<?php
// =====================================================================
// DEBUG: sinov "Sinov (test)" qiymati nega joriy JN o'rtachaga mos emas.
//  - SinovTestGrade snapshot vaqti (overridden_at) vs JB baholar o'zgargan vaqti.
// Ishga tushirish:
//   php artisan tinker --execute="require 'debug_sinov_stale.php';"
// =====================================================================

use App\Models\SinovTestGrade;
use App\Services\JournalGradeService;
use Illuminate\Support\Facades\DB;

$sid = '497';
$sem = '16';
$groupName = 'd1/23-11a';
$names = ['AMIRQULOVA', 'XOLIYAROVA'];

$gr = DB::table('groups')->where('name', $groupName)->first(['group_hemis_id', 'name']);
if (!$gr) { echo "Guruh topilmadi: $groupName\n"; return; }
$gh = (string) $gr->group_hemis_id;
echo "Guruh: {$gr->name} (group_hemis_id=$gh) | fan=$sid | sem=$sem\n\n";

foreach ($names as $namePart) {
    $stu = DB::table('students')->where('group_id', $gh)
        ->where('full_name', 'like', "%$namePart%")->first(['hemis_id', 'full_name']);
    if (!$stu) { echo "=== $namePart: talaba topilmadi ===\n\n"; continue; }
    echo "================= {$stu->full_name} (hemis={$stu->hemis_id}) =================\n";

    // 1) SinovTestGrade snapshot
    $stg = SinovTestGrade::where('subject_id', $sid)->where('semester_code', $sem)
        ->where('group_hemis_id', $gh)->where('student_hemis_id', $stu->hemis_id)->first();
    if ($stg) {
        printf("  SinovTestGrade: default=%s override=%s locked=%s\n",
            $stg->default_grade ?? 'NULL', $stg->override_grade ?? 'NULL', $stg->is_locked);
        printf("    overridden_at=%s created=%s updated=%s\n",
            $stg->overridden_at ?? 'NULL', $stg->created_at ?? 'NULL', $stg->updated_at ?? 'NULL');
    } else {
        echo "  SinovTestGrade: YO'Q\n";
    }

    // 2) Joriy (live) JN/MT o'rtacha
    $jnmt = JournalGradeService::computeJnMtBulk([[$gh, $sid, $sem]], [(string) $stu->hemis_id => $gh]);
    $live = $jnmt["$gh|$sid|$sem"][(string) $stu->hemis_id] ?? null;
    printf("  JORIY (live) JN o'rtacha = %s  (Sinov ustunda ko'rinayotgan = %s)\n",
        $live['jn'] ?? '?', $stg->override_grade ?? ($stg->default_grade ?? '?'));

    // 3) JB baholar — snapshotdan keyin o'zgargani belgilanadi
    $cut = $stg->overridden_at ?? $stg->created_at ?? null;
    $grades = DB::table('student_grades')
        ->where('student_hemis_id', $stu->hemis_id)
        ->where('subject_id', $sid)->where('semester_code', $sem)
        ->whereNotIn('training_type_code', [11, 99, 100, 101, 102, 103])
        ->whereNotNull('lesson_date')->whereNull('deleted_at')
        ->orderBy('updated_at')
        ->get(['grade', 'retake_grade', 'lesson_date', 'created_at', 'updated_at']);
    echo "  JB baholar (snapshotdan='$cut' KEYIN o'zgargani '<<<'):\n";
    $afterCount = 0;
    foreach ($grades as $g) {
        $after = ($cut && $g->updated_at > $cut) ? '   <<< snapshotdan KEYIN' : '';
        if ($after) $afterCount++;
        printf("    lesson=%s grade=%s retake=%s created=%s updated=%s%s\n",
            substr((string) $g->lesson_date, 0, 10), $g->grade, $g->retake_grade ?? '-',
            $g->created_at, $g->updated_at, $after);
    }
    printf("  => Snapshotdan keyin o'zgargan/qo'shilgan JB baholar: %d ta\n", $afterCount);
    echo "  XULOSA: agar >0 bo'lsa va JORIY JN != ko'rinayotgan qiymat bo'lsa — sabab tasdiqlandi (eski snapshot).\n\n";
}
