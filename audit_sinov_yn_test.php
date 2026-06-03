<?php
// =====================================================================
// AUDIT: kechagi sinov->YN test (submitToYn) natijasida yozilgan
// soxta 102-qatorlarni butun tizim bo'yicha aniqlash.
//
// Ishga tushirish:
//   php artisan tinker --execute="require 'audit_sinov_yn_test.php';"
// =====================================================================

use Illuminate\Support\Facades\DB;

// ---------------------------------------------------------------------
// 0) Holiqova'ning ikki qatorini TO'LIQ taqqoslash —
//    jurnal 89 ni nega chetlatayotganini ko'rish uchun
// ---------------------------------------------------------------------
echo "==== 0) Holiqova subject=14 sem=14 — ikki qator farqi ====\n";
$rows = DB::table('student_grades')
    ->where('student_hemis_id', 7416)
    ->where('subject_id', 14)
    ->where('semester_code', 14)
    ->where('training_type_code', 102)
    ->get(['id','grade','status','reason','attempt','is_qoshimcha',
           'education_year_code','education_year_name','lesson_date',
           'quiz_result_id','test_id','deleted_at']);
foreach ($rows as $r) {
    printf("id=%s grade=%s status=%-9s reason=%-14s attempt=%s qosh=%s eduYear=%s lesson=%s quiz_id=%s deleted=%s\n",
        $r->id, $r->grade, $r->status, $r->reason, $r->attempt,
        $r->is_qoshimcha, $r->education_year_code ?? 'NULL', $r->lesson_date ?? 'NULL',
        $r->quiz_result_id ?? 'NULL', $r->deleted_at ?? 'NULL');
}

// ---------------------------------------------------------------------
// 1) Jami soxta sinov_yn_test qatorlari
// ---------------------------------------------------------------------
echo "\n==== 1) Jami sinov_yn_test (102) qatorlar soni ====\n";
$total = DB::table('student_grades')
    ->whereNull('deleted_at')
    ->where('training_type_code', 102)
    ->where('reason', 'sinov_yn_test')
    ->count();
echo "Jami: $total\n";

// ---------------------------------------------------------------------
// 2) ENG XAVFLISI: real test natijasi BOR bo'lsa-yu, ustiga sinov soxta
//    qator ham bor (MAX uni noto'g'ri tanlashi mumkin). Talaba/fan/sem.
// ---------------------------------------------------------------------
echo "\n==== 2) ZIDDIYAT: real test + soxta sinov birga (fan/sem bo'yicha) ====\n";
$conflicts = DB::select("
    SELECT s.subject_id, s.semester_code,
           COUNT(DISTINCT s.student_hemis_id) AS talabalar,
           SUM(CASE WHEN s.grade <> r.grade THEN 1 ELSE 0 END) AS baho_farqli
    FROM student_grades s
    JOIN student_grades r
      ON  r.student_hemis_id = s.student_hemis_id
      AND r.subject_id       = s.subject_id
      AND r.semester_code    = s.semester_code
      AND r.training_type_code = 102
      AND (r.reason <> 'sinov_yn_test' OR r.reason IS NULL)
      AND r.deleted_at IS NULL
    WHERE s.training_type_code = 102
      AND s.reason = 'sinov_yn_test'
      AND s.deleted_at IS NULL
    GROUP BY s.subject_id, s.semester_code
    ORDER BY talabalar DESC
");
printf("%-10s %-8s %-10s %s\n", 'subject', 'sem', 'talabalar', 'baho_farqli');
$confTalaba = 0;
foreach ($conflicts as $c) {
    printf("%-10s %-8s %-10s %s\n", $c->subject_id, $c->semester_code, $c->talabalar, $c->baho_farqli);
    $confTalaba += $c->talabalar;
}
echo "JAMI ziddiyatli (talaba-fan): $confTalaba\n";

// ---------------------------------------------------------------------
// 3) YETIM soxta qator: curriculum yopilish shakli endi 'sinov' EMAS,
//    lekin sinov_yn_test qator turibdi (sozlama keyin to'g'rilangan).
// ---------------------------------------------------------------------
echo "\n==== 3) YETIM: closing_form endi 'sinov' emas, lekin soxta qator bor ====\n";
$orphans = DB::select("
    SELECT sg.subject_id, sg.semester_code, g.curriculum_hemis_id,
           cs.closing_form,
           COUNT(DISTINCT sg.student_hemis_id) AS talabalar
    FROM student_grades sg
    JOIN students st ON st.hemis_id = sg.student_hemis_id
    JOIN `groups` g  ON g.group_hemis_id = st.group_id
    LEFT JOIN curriculum_subjects cs
      ON cs.subject_id = sg.subject_id
     AND cs.semester_code = sg.semester_code
     AND cs.curricula_hemis_id = g.curriculum_hemis_id
    WHERE sg.training_type_code = 102
      AND sg.reason = 'sinov_yn_test'
      AND sg.deleted_at IS NULL
      AND (cs.closing_form IS NULL OR cs.closing_form <> 'sinov')
    GROUP BY sg.subject_id, sg.semester_code, g.curriculum_hemis_id, cs.closing_form
    ORDER BY talabalar DESC
");
printf("%-10s %-6s %-12s %-12s %s\n", 'subject','sem','curriculum','closing_form','talabalar');
$orphTalaba = 0;
foreach ($orphans as $o) {
    printf("%-10s %-6s %-12s %-12s %s\n",
        $o->subject_id, $o->semester_code, $o->curriculum_hemis_id,
        $o->closing_form ?? 'NULL', $o->talabalar);
    $orphTalaba += $o->talabalar;
}
echo "JAMI yetim (talaba-fan-reja): $orphTalaba\n";

echo "\n==== TUGADI ====\n";
echo "2-bo'lim = MAX tufayli noto'g'ri ko'rinayotgan baholar (eng muhim).\n";
echo "3-bo'lim = sozlama to'g'rilangan, lekin eski soxta baho qolgan holatlar.\n";
