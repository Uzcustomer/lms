<?php
// tailwind natijasini _styles.blade.php ga yozadi (qarang: tailwind.config.cjs).
$css = trim((string) file_get_contents($argv[1] ?? '/tmp/sd.css'));
$out = __DIR__ . '/../../resources/views/admin/subject-differences/_styles.blade.php';
file_put_contents($out, "{{-- Avtomatik yaratilgan: tools/subject-differences/tailwind.config.cjs. Qo'lda tahrirlamang. --}}\n<style>" . $css . "</style>\n");
echo 'yozildi: ' . strlen($css) . " bayt\n";
