<?php

require_once __DIR__ . '/helpers.php';

use App\Models\MonitoringDetector;
use App\Models\MonitoringIstisno;
use App\Models\MonitoringRun;
use App\Models\TeacherNotification;
use App\Services\Monitoring\Contracts\Detector;
use App\Services\Monitoring\DetectorResult;
use App\Services\Monitoring\DetectorRunner;
use App\Services\Monitoring\Finding;

/**
 * Sinov detektori: qaytaradigan holatlari test ichida beriladi.
 */
class MonitoringFakeDetector implements Detector
{
    /** @var array<int, array> */
    public static array $findings = [];
    public static ?array $scopes = null;
    public static bool $fail = false;

    public function run(array $params, MonitoringDetector $detector): DetectorResult
    {
        if (self::$fail) {
            throw new RuntimeException('Sinov xatosi');
        }
        $result = new DetectorResult(self::$scopes);
        foreach (self::$findings as $f) {
            $result->add(new Finding(...$f));
        }

        return $result;
    }
}

beforeEach(function () {
    MonitoringFakeDetector::$findings = [];
    MonitoringFakeDetector::$scopes = null;
    MonitoringFakeDetector::$fail = false;
    config(['monitoring.detectors.T-1' => MonitoringFakeDetector::class]);

    $this->detector = MonitoringDetector::create([
        'code' => 'T-1', 'group_name' => 'SINOV', 'title' => 'Sinov qoidasi', 'rule_text' => 'Sinov',
        'enabled' => true, 'due_workdays' => 3, 'params' => [],
    ]);
    $this->runner = app(DetectorRunner::class);
});

function monFinding(string $fp, array $extra = []): array
{
    return array_merge(['fingerprint' => $fp, 'subjectType' => 'talaba', 'subjectLabel' => "Holat {$fp}"], $extra);
}

test('yangi holat istisno ochadi, mas\'ulga biriktiradi va xabar beradi', function () {
    $owner = monTeacher(['dekan']);
    MonitoringFakeDetector::$findings = [monFinding('a', ['owner' => $owner])];

    $run = $this->runner->run($this->detector);

    expect($run->status)->toBe(MonitoringRun::STATUS_OK)
        ->and($run->opened_count)->toBe(1);
    $istisno = MonitoringIstisno::sole();
    expect($istisno->status)->toBe('ochiq')
        ->and($istisno->isOwnedBy($owner))->toBeTrue()
        ->and($istisno->due_at->isFuture())->toBeTrue()
        ->and(TeacherNotification::where('teacher_id', $owner->id)->where('type', 'monitoring')->count())->toBe(1);
});

test('qayta ishga tushirishda dublikat istisno yaratilmaydi', function () {
    MonitoringFakeDetector::$findings = [monFinding('a', ['details' => ['soat' => 80]])];
    $this->runner->run($this->detector);

    MonitoringFakeDetector::$findings = [monFinding('a', ['details' => ['soat' => 85]])];
    $run = $this->runner->run($this->detector);

    expect(MonitoringIstisno::count())->toBe(1)
        ->and($run->opened_count)->toBe(0)
        ->and($run->updated_count)->toBe(1)
        ->and(MonitoringIstisno::sole()->details['soat'])->toBe(85);
});

test('holat bartaraf bo\'lsa istisno tizim tomonidan yopiladi va tarixda qoladi', function () {
    MonitoringFakeDetector::$findings = [monFinding('a'), monFinding('b')];
    $this->runner->run($this->detector);

    MonitoringFakeDetector::$findings = [monFinding('b')];
    $run = $this->runner->run($this->detector);

    $a = MonitoringIstisno::where('fingerprint', 'a')->sole();
    expect($run->closed_count)->toBe(1)
        ->and($a->status)->toBe('yopildi')
        ->and($a->closed_by_system)->toBeTrue()
        ->and($a->statusLabel())->toBe('Yopildi (tizim)')
        ->and($a->comments()->where('new_status', 'yopildi')->exists())->toBeTrue()
        ->and(MonitoringIstisno::where('fingerprint', 'b')->sole()->status)->toBe('ochiq');
});

test('tizim yopgan holat yana paydo bo\'lsa qayta ochiladi', function () {
    MonitoringFakeDetector::$findings = [monFinding('a')];
    $this->runner->run($this->detector);
    MonitoringFakeDetector::$findings = [];
    $this->runner->run($this->detector);

    MonitoringFakeDetector::$findings = [monFinding('a')];
    $run = $this->runner->run($this->detector);

    expect($run->reopened_count)->toBe(1)
        ->and(MonitoringIstisno::sole()->status)->toBe('qayta_ochildi');
});

test('administrator «asosli istisno» deb yopgani avtomatik qayta ochilmaydi', function () {
    MonitoringFakeDetector::$findings = [monFinding('a')];
    $this->runner->run($this->detector);
    MonitoringIstisno::sole()->forceFill(['status' => 'asosli_istisno', 'closed_at' => now()])->save();

    $this->runner->run($this->detector);

    expect(MonitoringIstisno::sole()->status)->toBe('asosli_istisno');
});

test('detektor xato bersa run «xato» bo\'ladi va ochiq istisnolar yopilmaydi', function () {
    MonitoringFakeDetector::$findings = [monFinding('a')];
    $this->runner->run($this->detector);

    MonitoringFakeDetector::$fail = true;
    $run = $this->runner->run($this->detector);

    expect($run->status)->toBe(MonitoringRun::STATUS_ERROR)
        ->and($run->error_message)->toContain('Sinov xatosi')
        ->and(MonitoringIstisno::sole()->status)->toBe('ochiq');
});

test('ogohlantirish rejimida mas\'ulga biriktirilmaydi va xabar yuborilmaydi', function () {
    $owner = monTeacher(['dekan']);
    $this->detector->update(['warning_mode' => true]);
    MonitoringFakeDetector::$findings = [monFinding('a', ['owner' => $owner])];

    $this->runner->run($this->detector->fresh());

    $istisno = MonitoringIstisno::sole();
    expect($istisno->warning_only)->toBeTrue()
        ->and($istisno->owner_id)->toBeNull()
        ->and(TeacherNotification::count())->toBe(0);
});

test('qamrovdan tashqaridagi istisno (masalan o\'tgan semestr) yopilmaydi', function () {
    MonitoringFakeDetector::$findings = [monFinding('eski', ['scopeKey' => '2025:12'])];
    MonitoringFakeDetector::$scopes = ['2025:12'];
    $this->runner->run($this->detector);

    // Semestr almashdi: endi faqat yangi semestr tekshiriladi, eski holat ro'yxatda yo'q.
    MonitoringFakeDetector::$findings = [];
    MonitoringFakeDetector::$scopes = ['2026:13'];
    $run = $this->runner->run($this->detector);

    expect($run->closed_count)->toBe(0)
        ->and(MonitoringIstisno::sole()->status)->toBe('ochiq');
});

test('mas\'ul topilmasa detektorning standart mas\'uli biriktiriladi', function () {
    $registrar = monTeacher(['registrator_ofisi']);
    $this->detector->update(['default_owner_type' => $registrar->getMorphClass(), 'default_owner_id' => $registrar->id]);
    MonitoringFakeDetector::$findings = [monFinding('a')];

    $this->runner->run($this->detector->fresh());

    expect(MonitoringIstisno::sole()->isOwnedBy($registrar))->toBeTrue();
});

test('monitoring:run buyrug\'i yoqilgan detektorlarni ishga tushiradi', function () {
    MonitoringFakeDetector::$findings = [monFinding('a')];

    $this->artisan('monitoring:run', ['code' => ['T-1']])->assertSuccessful();

    expect(MonitoringIstisno::count())->toBe(1);
});

test('ogohlantirish rejimi o\'chirilgach istisno mas\'ulga biriktiriladi va xabar boradi', function () {
    $owner = monTeacher(['dekan']);
    $this->detector->update(['warning_mode' => true]);
    MonitoringFakeDetector::$findings = [monFinding('a', ['owner' => $owner])];
    $this->runner->run($this->detector->fresh());
    expect(MonitoringIstisno::sole()->owner_id)->toBeNull();

    $this->detector->update(['warning_mode' => false]);
    $this->runner->run($this->detector->fresh());

    $istisno = MonitoringIstisno::sole();
    expect($istisno->warning_only)->toBeFalse()
        ->and($istisno->isOwnedBy($owner))->toBeTrue()
        ->and(TeacherNotification::where('teacher_id', $owner->id)->count())->toBe(1);
});
