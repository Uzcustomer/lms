<?php

require_once __DIR__ . '/helpers.php';

use App\Models\MonitoringDetector;
use App\Models\MonitoringIstisno;

/*
 * Kirish qoidalari (TZ 2-bo'lim, pilot qabul mezoni: «mas'ul faqat o'z istisnolarini ko'radi»).
 */

beforeEach(function () {
    $this->withoutVite(); // testlarda frontend build (public/build) yo'q
    $this->detector = MonitoringDetector::where('code', 'D-2')->firstOrFail();
});

function monIstisno(MonitoringDetector $detector, $owner = null, array $attrs = []): MonitoringIstisno
{
    $n = monSeq();

    return MonitoringIstisno::create(array_merge([
        'detector_id' => $detector->id,
        'fingerprint' => "sinov:{$n}",
        'subject_type' => 'talaba',
        'subject_label' => "Sinov holati {$n}",
        'found_at' => now(),
        'last_seen_at' => now(),
        'due_at' => now()->addDays(3),
        'status' => 'ochiq',
        'owner_type' => $owner?->getMorphClass(),
        'owner_id' => $owner?->getKey(),
    ], $attrs));
}

test('mas\'ul faqat o\'ziga biriktirilgan istisnoni ko\'radi', function () {
    $dean = monTeacher(['dekan']);
    $otherDean = monTeacher(['dekan']);
    $mine = monIstisno($this->detector, $dean);
    $foreign = monIstisno($this->detector, $otherDean);

    $this->actingAs($dean, 'teacher')->get(route('admin.monitoring.istisnolar.show', $mine))->assertOk();
    $this->actingAs($dean, 'teacher')->get(route('admin.monitoring.istisnolar.show', $foreign))->assertForbidden();

    $this->actingAs($dean, 'teacher')->get(route('admin.monitoring.mine'))
        ->assertOk()
        ->assertSee($mine->subject_label)
        ->assertDontSee($foreign->subject_label);
});

test('mas\'ul umumiy ro\'yxat va panelga kira olmaydi', function () {
    $dean = monTeacher(['dekan']);

    $this->actingAs($dean, 'teacher')->get(route('admin.monitoring.istisnolar.index'))->assertForbidden();
    $this->actingAs($dean, 'teacher')->get(route('admin.monitoring.index'))->assertRedirect(route('admin.monitoring.mine'));
    $this->actingAs($dean, 'teacher')->get(route('admin.monitoring.detektorlar.index'))->assertForbidden();
});

test('mas\'ul javob yozadi va «bajarildi» deb belgilaydi, lekin yopa olmaydi', function () {
    $dean = monTeacher(['dekan']);
    $istisno = monIstisno($this->detector, $dean);

    $this->actingAs($dean, 'teacher')
        ->post(route('admin.monitoring.istisnolar.respond', $istisno), ['body' => 'Buyruq loyihasi tayyorlandi', 'mark_done' => 1])
        ->assertRedirect();
    expect($istisno->fresh()->status)->toBe('javob_berildi');

    $this->actingAs($dean, 'teacher')
        ->post(route('admin.monitoring.istisnolar.transition', $istisno), ['action' => 'close'])
        ->assertForbidden();
    expect($istisno->fresh()->status)->toBe('javob_berildi');
});

test('rahbariyat (o\'quv prorektori) ko\'radi, lekin yoza olmaydi', function () {
    $viewer = monTeacher(['oquv_prorektori']);
    $istisno = monIstisno($this->detector, monTeacher(['dekan']));

    $this->actingAs($viewer, 'teacher')->get(route('admin.monitoring.istisnolar.index'))->assertOk()->assertSee($istisno->subject_label);
    $this->actingAs($viewer, 'teacher')->post(route('admin.monitoring.istisnolar.respond', $istisno), ['body' => 'x'])->assertForbidden();
    $this->actingAs($viewer, 'teacher')->post(route('admin.monitoring.istisnolar.transition', $istisno), ['action' => 'close'])->assertForbidden();
});

test('monitoring administratori yopadi, asosli istisno va qayta ochish izoh talab qiladi', function () {
    $admin = monUser(['monitoring_admin']);
    $istisno = monIstisno($this->detector, monTeacher(['dekan']));

    $this->actingAs($admin, 'web')
        ->post(route('admin.monitoring.istisnolar.transition', $istisno), ['action' => 'justify'])
        ->assertSessionHasErrors('body');

    $this->actingAs($admin, 'web')
        ->post(route('admin.monitoring.istisnolar.transition', $istisno), ['action' => 'justify', 'body' => 'Tibbiy ma\'lumotnoma bor'])
        ->assertRedirect();
    expect($istisno->fresh()->status)->toBe('asosli_istisno');

    $this->actingAs($admin, 'web')
        ->post(route('admin.monitoring.istisnolar.transition', $istisno), ['action' => 'reopen', 'body' => 'Ma\'lumotnoma shakli noto\'g\'ri'])
        ->assertRedirect();
    expect($istisno->fresh()->status)->toBe('qayta_ochildi');
});

test('registrator mas\'ul biriktiradi, mas\'ulga xabar boradi', function () {
    $registrar = monTeacher(['registrator_ofisi']);
    $dean = monTeacher(['dekan']);
    $istisno = monIstisno($this->detector);

    $this->actingAs($registrar, 'teacher')
        ->post(route('admin.monitoring.istisnolar.assign', $istisno), ['owner' => 'teacher:' . $dean->id])
        ->assertRedirect();

    expect($istisno->fresh()->isOwnedBy($dean))->toBeTrue()
        ->and(\App\Models\TeacherNotification::where('teacher_id', $dean->id)->exists())->toBeTrue();
});

test('ko\'rish va eksport audit jurnaliga yoziladi', function () {
    $admin = monUser(['monitoring_admin']);
    $istisno = monIstisno($this->detector);

    $this->actingAs($admin, 'web')->get(route('admin.monitoring.istisnolar.show', $istisno))->assertOk();
    $this->actingAs($admin, 'web')->get(route('admin.monitoring.istisnolar.index', ['export' => 'excel']))->assertOk();

    expect(\App\Models\ActivityLog::where('module', 'monitoring_istisno')->where('action', 'view')->exists())->toBeTrue()
        ->and(\App\Models\ActivityLog::where('module', 'monitoring_istisno')->where('action', 'export')->exists())->toBeTrue();
});
