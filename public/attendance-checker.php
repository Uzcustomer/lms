<?php
/**
 * HEMIS Attendance Checker
 * Talaba davomatini API dan olish va Excel ga eksport qilish
 */

// API Token - student.ttatf.uz dan olingan token
$API_BASE = 'https://student.ttatf.uz/rest/v1/data/attendance-list';

// Cookie dan tokenni olish yoki hardcode qilish
$TOKEN = $_COOKIE['hemis_token'] ?? '';

// ============ FORM YUBORILGAN BO'LSA ============
$results = [];
$error = '';
$studentId = $_GET['student_id'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$export = $_GET['export'] ?? '';
$token = $_GET['token'] ?? $TOKEN;

if ($studentId && $token) {
    $url = $API_BASE . '?_student=' . urlencode($studentId);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Accept: application/json',
        ],
        CURLOPT_TIMEOUT => 30,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        $error = "API xatolik: HTTP $httpCode";
    } else {
        $json = json_decode($response, true);
        $items = $json['data']['items'] ?? $json['items'] ?? $json['data'] ?? [];

        if (!is_array($items)) {
            $error = "Ma'lumot topilmadi yoki format noto'g'ri";
        } else {
            // Sana bo'yicha filtrlash
            $fromTs = $dateFrom ? strtotime($dateFrom) : 0;
            $toTs = $dateTo ? strtotime($dateTo . ' 23:59:59') : PHP_INT_MAX;

            foreach ($items as $item) {
                $lessonTs = $item['lesson_date'] ?? 0;
                if ($lessonTs >= $fromTs && $lessonTs <= $toTs) {
                    // Faqat absent bo'lganlarni olish
                    $absentOn = $item['absent_on'] ?? 0;
                    $absentOff = $item['absent_off'] ?? 0;

                    $results[] = [
                        'id' => $item['id'] ?? '',
                        'student_id' => $item['student']['id'] ?? '',
                        'student_name' => $item['student']['name'] ?? '',
                        'subject_id' => $item['subject']['id'] ?? '',
                        'subject_name' => $item['subject']['name'] ?? '',
                        'subject_code' => $item['subject']['code'] ?? '',
                        'training_type' => $item['trainingType']['name'] ?? '',
                        'lesson_pair' => ($item['lessonPair']['start_time'] ?? '') . '-' . ($item['lessonPair']['end_time'] ?? ''),
                        'absent_on' => $absentOn,
                        'absent_off' => $absentOff,
                        'lesson_date' => $lessonTs ? date('d.m.Y', $lessonTs) : '',
                        'lesson_date_raw' => $lessonTs,
                        'semester' => $item['semester']['name'] ?? '',
                        'semester_code' => $item['semester']['code'] ?? '',
                        'group_name' => $item['group']['name'] ?? '',
                        'education_year' => $item['educationYear']['name'] ?? '',
                        'education_year_current' => $item['educationYear']['current'] ?? false,
                        'employee_name' => $item['employee']['name'] ?? '',
                    ];
                }
            }

            // Sanaga bo'yicha tartiblash
            usort($results, fn($a, $b) => $a['lesson_date_raw'] <=> $b['lesson_date_raw']);
        }
    }

    // ============ EXCEL EXPORT ============
    if ($export === 'excel' && !empty($results)) {
        header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
        header('Content-Disposition: attachment; filename="attendance_' . $studentId . '_' . date('Y-m-d') . '.xls"');
        header('Pragma: no-cache');

        echo "\xEF\xBB\xBF"; // UTF-8 BOM
        echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel"><head><meta charset="UTF-8"></head><body>';
        echo '<table border="1" cellpadding="4" cellspacing="0" style="border-collapse:collapse;font-family:Arial;font-size:12px;">';
        echo '<tr style="background:#1e40af;color:#fff;font-weight:bold;">';
        echo '<th>#</th><th>Sana</th><th>Fan nomi</th><th>Subject ID</th><th>Dars turi</th><th>Juftlik</th>';
        echo '<th>Sababli</th><th>Sababsiz</th><th>Semestr</th><th>O\'quv yili</th><th>O\'qituvchi</th><th>Guruh</th>';
        echo '</tr>';

        foreach ($results as $i => $r) {
            $bg = $r['absent_off'] > 0 ? '#fef2f2' : ($r['absent_on'] > 0 ? '#f0fdf4' : '#fff');
            echo '<tr style="background:' . $bg . ';">';
            echo '<td>' . ($i + 1) . '</td>';
            echo '<td>' . $r['lesson_date'] . '</td>';
            echo '<td>' . htmlspecialchars($r['subject_name']) . '</td>';
            echo '<td>' . $r['subject_id'] . '</td>';
            echo '<td>' . htmlspecialchars($r['training_type']) . '</td>';
            echo '<td>' . $r['lesson_pair'] . '</td>';
            echo '<td style="color:green;font-weight:bold;">' . $r['absent_on'] . '</td>';
            echo '<td style="color:red;font-weight:bold;">' . $r['absent_off'] . '</td>';
            echo '<td>' . htmlspecialchars($r['semester']) . '</td>';
            echo '<td>' . htmlspecialchars($r['education_year']) . ($r['education_year_current'] ? ' ●' : '') . '</td>';
            echo '<td>' . htmlspecialchars($r['employee_name']) . '</td>';
            echo '<td>' . htmlspecialchars($r['group_name']) . '</td>';
            echo '</tr>';
        }
        echo '</table></body></html>';
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HEMIS Attendance Checker</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f1f5f9; color: #1e293b; }
        .container { max-width: 1400px; margin: 0 auto; padding: 20px; }
        h1 { font-size: 22px; color: #1e40af; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .filter-card { background: #fff; border-radius: 12px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .filter-row { display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end; }
        .filter-group { display: flex; flex-direction: column; gap: 4px; }
        .filter-group label { font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase; }
        .filter-group input { padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; outline: none; transition: border-color 0.2s; }
        .filter-group input:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }
        .btn { padding: 8px 20px; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.2s; }
        .btn-primary { background: #2563eb; color: #fff; }
        .btn-primary:hover { background: #1d4ed8; }
        .btn-green { background: #16a34a; color: #fff; }
        .btn-green:hover { background: #15803d; }
        .stats { display: flex; gap: 10px; margin-bottom: 16px; flex-wrap: wrap; }
        .stat-badge { padding: 6px 14px; border-radius: 8px; font-size: 13px; font-weight: 700; }
        .stat-total { background: #dbeafe; color: #1e40af; }
        .stat-on { background: #dcfce7; color: #166534; }
        .stat-off { background: #fee2e2; color: #991b1b; }
        table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        thead th { background: #1e293b; color: #fff; padding: 10px 8px; font-size: 11px; text-transform: uppercase; text-align: left; position: sticky; top: 0; }
        tbody td { padding: 8px; font-size: 13px; border-bottom: 1px solid #f1f5f9; }
        tbody tr:hover { background: #f8fafc; }
        .row-off { background: #fef2f2; }
        .row-on { background: #f0fdf4; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 700; }
        .badge-on { background: #dcfce7; color: #166534; }
        .badge-off { background: #fee2e2; color: #991b1b; }
        .badge-current { background: #dbeafe; color: #1e40af; font-size: 10px; }
        .error { padding: 12px 16px; background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; border-radius: 8px; margin-bottom: 16px; font-weight: 600; }
        .empty { text-align: center; padding: 40px; color: #94a3b8; font-size: 15px; }
    </style>
</head>
<body>
<div class="container">
    <h1>
        <svg width="28" height="28" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
        HEMIS Attendance Checker
    </h1>

    <div class="filter-card">
        <form method="GET" action="">
            <div class="filter-row">
                <div class="filter-group">
                    <label>API Token</label>
                    <input type="text" name="token" value="<?= htmlspecialchars($token) ?>" placeholder="Bearer token..." style="width:300px;" required>
                </div>
                <div class="filter-group">
                    <label>Student ID</label>
                    <input type="text" name="student_id" value="<?= htmlspecialchars($studentId) ?>" placeholder="6372" style="width:120px;" required>
                </div>
                <div class="filter-group">
                    <label>Sanadan</label>
                    <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>">
                </div>
                <div class="filter-group">
                    <label>Sanagacha</label>
                    <input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>">
                </div>
                <div class="filter-group">
                    <label>&nbsp;</label>
                    <button type="submit" class="btn btn-primary">Qidirish</button>
                </div>
                <?php if (!empty($results)): ?>
                <div class="filter-group">
                    <label>&nbsp;</label>
                    <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'excel'])) ?>" class="btn btn-green" style="text-decoration:none;">Excel yuklab olish</a>
                </div>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (!empty($results)): ?>
        <?php
        $totalOn = array_sum(array_column($results, 'absent_on'));
        $totalOff = array_sum(array_column($results, 'absent_off'));
        $studentName = $results[0]['student_name'] ?? '';
        $groupName = $results[0]['group_name'] ?? '';
        ?>
        <div class="stats">
            <span class="stat-badge stat-total">Jami: <?= count($results) ?> ta yozuv | <?= htmlspecialchars($studentName) ?> | <?= htmlspecialchars($groupName) ?></span>
            <span class="stat-badge stat-on">Sababli: <?= $totalOn ?> soat</span>
            <span class="stat-badge stat-off">Sababsiz: <?= $totalOff ?> soat</span>
        </div>

        <div style="overflow-x:auto;">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Sana</th>
                        <th>Fan nomi</th>
                        <th>Subject ID</th>
                        <th>Dars turi</th>
                        <th>Juftlik</th>
                        <th>Sababli</th>
                        <th>Sababsiz</th>
                        <th>Semestr</th>
                        <th>O'quv yili</th>
                        <th>O'qituvchi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($results as $i => $r): ?>
                    <tr class="<?= $r['absent_off'] > 0 ? 'row-off' : ($r['absent_on'] > 0 ? 'row-on' : '') ?>">
                        <td><?= $i + 1 ?></td>
                        <td style="white-space:nowrap;font-weight:600;"><?= $r['lesson_date'] ?></td>
                        <td><?= htmlspecialchars($r['subject_name']) ?></td>
                        <td style="text-align:center;"><?= $r['subject_id'] ?></td>
                        <td><?= htmlspecialchars($r['training_type']) ?></td>
                        <td style="white-space:nowrap;"><?= $r['lesson_pair'] ?></td>
                        <td style="text-align:center;"><?php if($r['absent_on'] > 0): ?><span class="badge badge-on"><?= $r['absent_on'] ?></span><?php else: ?>0<?php endif; ?></td>
                        <td style="text-align:center;"><?php if($r['absent_off'] > 0): ?><span class="badge badge-off"><?= $r['absent_off'] ?></span><?php else: ?>0<?php endif; ?></td>
                        <td style="white-space:nowrap;"><?= htmlspecialchars($r['semester']) ?></td>
                        <td style="white-space:nowrap;"><?= htmlspecialchars($r['education_year']) ?><?php if($r['education_year_current']): ?> <span class="badge badge-current">joriy</span><?php endif; ?></td>
                        <td><?= htmlspecialchars($r['employee_name']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php elseif ($studentId && !$error): ?>
        <div class="empty">Ma'lumot topilmadi yoki barcha yozuvlar sana filtriga mos kelmadi.</div>
    <?php else: ?>
        <div class="empty">Talaba ID kiriting va qidirish tugmasini bosing.</div>
    <?php endif; ?>
</div>
</body>
</html>
