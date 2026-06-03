<?php
// =====================================================================
// DIAGNOSTIKA (Laravel tinker uchun)
// Ishga tushirish (production serverda, loyiha papkasida):
//   php artisan tinker --execute="require 'diagnostic_test_grade_holiqova_tinker.php';"
// yoki:  php artisan tinker  ->  keyin pastdagi kodni nusxalab qo'ying.
// =====================================================================

use Illuminate\Support\Facades\DB;

$hemisId = '7416'; // HOLIQOVA POKIZA SAMANDAR QIZI

echo "\n==== 1) Barcha TEST (102) qatorlari — soft-delete bilan birga ====\n";
$rows = DB::table('student_grades as sg')
    ->leftJoin('exam_tests as et', 'et.id', '=', 'sg.test_id')
    ->where('sg.student_hemis_id', $hemisId)
    ->where('sg.training_type_code', 102)
    ->orderByRaw('sg.deleted_at IS NULL DESC')
    ->orderBy('sg.attempt')
    ->orderBy('sg.created_at')
    ->get([
        'sg.id', 'sg.subject_id', 'sg.semester_code', 'sg.education_year_code',
        'sg.attempt', 'sg.test_id', 'et.shakl as exam_test_shakl',
        'sg.grade', 'sg.retake_grade', 'sg.status', 'sg.is_qoshimcha',
        'sg.lesson_date', 'sg.created_at', 'sg.deleted_at',
    ]);
foreach ($rows as $r) {
    printf(
        "id=%-7s attempt=%s shakl=%-2s grade=%-4s retake=%-4s qosh=%s status=%-10s sem=%s created=%s deleted=%s\n",
        $r->id, $r->attempt, $r->exam_test_shakl ?? '-', $r->grade,
        $r->retake_grade ?? '-', $r->is_qoshimcha, $r->status,
        $r->semester_code, $r->created_at, $r->deleted_at ?? 'NULL'
    );
}

echo "\n==== 2) Vedomost-tekshirish AYNAN shuni oladi: MAX(grade), deleted_at IS NULL ====\n";
$max = DB::table('student_grades')
    ->whereNull('deleted_at')
    ->where('student_hemis_id', $hemisId)
    ->where('training_type_code', 102)
    ->groupBy('student_hemis_id', 'subject_id', 'semester_code')
    ->get([
        'subject_id', 'semester_code',
        DB::raw('MAX(grade) as test_max'),
        DB::raw('COUNT(*) as cnt'),
        DB::raw('GROUP_CONCAT(grade ORDER BY grade) as baholar'),
    ]);
foreach ($max as $m) {
    printf("subject=%s sem=%s  MAX=%s  (qatorlar=%s, baholar=%s)\n",
        $m->subject_id, $m->semester_code, $m->test_max, $m->cnt, $m->baholar);
}

echo "\n==== 3) Jurnal asosiy ustuni: attempt=1 ====\n";
$a1 = DB::table('student_grades')
    ->whereNull('deleted_at')
    ->where('student_hemis_id', $hemisId)
    ->where('training_type_code', 102)
    ->where('attempt', 1)
    ->get(['attempt', 'grade', 'test_id', 'created_at']);
foreach ($a1 as $r) {
    printf("attempt=1 grade=%s test_id=%s created=%s\n", $r->grade, $r->test_id, $r->created_at);
}
echo "\nXulosa: 2) dagi MAX 89 bo'lib, 3) dagi attempt=1 grade 82 bo'lsa — sabab tasdiqlanadi.\n";
