<?php

use App\Services\Retake\RetakeSessionCode;

test('normalizes a session name into the canonical code', function () {
    expect(RetakeSessionCode::normalize('2025-2026 yozgi qayta o\'qish'))->toBe('2025-2026-yozgi');
    expect(RetakeSessionCode::normalize('2026-2027 kuzgi'))->toBe('2026-2027-kuzgi');
    expect(RetakeSessionCode::normalize('Qishki 2030-2031'))->toBe('2030-2031-qishki');
});

test('accepts slash year separator but emits canonical hyphen code', function () {
    expect(RetakeSessionCode::normalize('2025/2026-yozgi'))->toBe('2025-2026-yozgi');
});

test('returns null when fasl or year range is missing', function () {
    expect(RetakeSessionCode::normalize('2025-2026 qayta o\'qish'))->toBeNull();
    expect(RetakeSessionCode::normalize('yozgi qayta o\'qish'))->toBeNull();
    expect(RetakeSessionCode::normalize('bahorgi 2025-2026'))->toBeNull();
    expect(RetakeSessionCode::normalize(null))->toBeNull();
    expect(RetakeSessionCode::normalize(''))->toBeNull();
});

test('extracts the session code from a full Moodle quiz name', function () {
    $name = "YN test (rus)_Umumiy xirurgiya_6-sem_DAV-2_D_Qayta-o'qish-2025-2026-yozgi";
    expect(RetakeSessionCode::fromQuizName($name))->toBe('2025-2026-yozgi');
    expect(RetakeSessionCode::isRetakeQuiz($name))->toBeTrue();
});

test('prefers the shakl segment when both are passed', function () {
    expect(RetakeSessionCode::fromQuizName('irrelevant', "Qayta-o'qish-2026-2027-qishki"))
        ->toBe('2026-2027-qishki');
});

test('regular (non-retake) quiz names are not treated as retake', function () {
    $name = "YN test (uzb)_Tibbiy kimyo_2-sem_DAV-2_D_1-urinish";
    expect(RetakeSessionCode::fromQuizName($name))->toBeNull();
    expect(RetakeSessionCode::isRetakeQuiz($name))->toBeFalse();
});

test('builds the canonical quiz suffix', function () {
    expect(RetakeSessionCode::quizSuffix('2025-2026-yozgi'))->toBe("Qayta-o'qish-2025-2026-yozgi");
});

test('detects retake marker even without a session token (legacy quizzes)', function () {
    // Hozirgi prod quizlari: shakl faqat "Qayta-o'qish" (yil/fasl tokensiz)
    expect(RetakeSessionCode::isRetakeQuiz(null, "Qayta-o'qish"))->toBeTrue();
    expect(RetakeSessionCode::hasRetakeMarker("Qayta-o'qish"))->toBeTrue();
    // Lekin sessiya kodi chiqmaydi (token yo'q)
    expect(RetakeSessionCode::fromQuizName(null, "Qayta-o'qish"))->toBeNull();
});

test('marker detection is robust to apostrophe variants', function () {
    expect(RetakeSessionCode::hasRetakeMarker("Qayta-o'qish"))->toBeTrue();   // ASCII '
    expect(RetakeSessionCode::hasRetakeMarker("Qayta-o‘qish"))->toBeTrue();   // U+2018
    expect(RetakeSessionCode::hasRetakeMarker('Qayta oqish'))->toBeTrue();    // apostrofsiz
    expect(RetakeSessionCode::hasRetakeMarker("1-urinish"))->toBeFalse();
});

test('different seasons of the same year produce distinct codes', function () {
    $winter = RetakeSessionCode::fromQuizName("X_Qayta-o'qish-2025-2026-qishki");
    $summer = RetakeSessionCode::fromQuizName("X_Qayta-o'qish-2025-2026-yozgi");
    expect($winter)->not->toBe($summer);
});
