<?php
/**
 * Moodle serverda ishga tushadigan skript.
 * LMS dan "sync kerakmi?" so'raydi. Agar kerak bo'lsa — push skriptni ishga tushiradi.
 *
 * Moodle serverda crontab ga qo'shing:
 * (har 3 daqiqada tekshirish)
 *
 *    */3 * * * * /usr/bin/php /opt/scripts/moodle_check_trigger.php >> /var/log/moodle_check_trigger.log 2>&1
 *
 * Sozlash:
 *   LMS_URL     — LMS server URL (Moodle dan kirsa bo'ladigan)
 *   SYNC_SECRET — LMS .env dagi MOODLE_SYNC_SECRET bilan bir xil
 *   PUSH_SCRIPT — mavjud push skript yo'li
 */

// ===== SOZLAMALAR =====
$LMS_URL     = 'https://mark.tashmedunitf.uz';
$SYNC_SECRET = '159357@Dax';
$PUSH_SCRIPT = '/opt/scripts/moodle_to_lmsttatf_push.php';
// =======================

$checkUrl = rtrim($LMS_URL, '/') . '/moodle/should-sync';

$ch = curl_init($checkUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_HTTPHEADER     => [
        'X-SYNC-SECRET: ' . $SYNC_SECRET,
    ],
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error    = curl_error($ch);
curl_close($ch);

$ts = date('Y-m-d H:i:s');

if ($error) {
    echo "[{$ts}] ERROR: curl xato — {$error}\n";
    exit(1);
}

if ($httpCode !== 200) {
    echo "[{$ts}] ERROR: HTTP {$httpCode} — {$response}\n";
    exit(1);
}

$data = json_decode($response, true);

if (!$data || !isset($data['trigger'])) {
    echo "[{$ts}] ERROR: noto'g'ri javob — {$response}\n";
    exit(1);
}

if ($data['trigger'] === true) {
    echo "[{$ts}] TRIGGER: sync so'ralgan ({$data['requested_at']}). Push skript ishga tushirilmoqda...\n";

    // Push skriptni ishga tushirish
    $output = [];
    $exitCode = 0;
    exec("/usr/bin/php {$PUSH_SCRIPT} 2>&1", $output, $exitCode);

    $outputStr = implode("\n", $output);
    echo "[{$ts}] Push skript yakunlandi (exit: {$exitCode})\n";
    if ($outputStr) {
        echo "[{$ts}] Output: {$outputStr}\n";
    }
} else {
    echo "[{$ts}] OK: sync kerak emas\n";
}
