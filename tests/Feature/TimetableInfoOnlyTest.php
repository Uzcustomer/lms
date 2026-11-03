<?php

use App\Http\Controllers\Admin\TimetableController;
use App\Models\Auditorium;
use App\Models\Teacher;
use App\Models\TimetableBoard;
use App\Models\TimetableCard;
use App\Models\TimetableCyclePlacement;
use App\Models\TimetableSubjectSetting;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;

beforeEach(fn () => $this->travelTo(Carbon::parse('2026-09-01 09:00', 'Asia/Tashkent')));

function ioTeacher(): Teacher
{
    Role::findOrCreate('admin', 'web');
    $t = new Teacher();
    $t->forceFill(['hemis_id' => random_int(100000, 999999), 'full_name' => 'MAHMARAIMOV SH. T.', 'short_name' => 'MAHMARAIMOV SH. T.',
        'first_name' => 'Sinov', 'second_name' => 'Sinov', 'employee_id_number' => (string) random_int(100000, 999999),
        'birth_date' => '1980-01-01', 'year_of_enter' => 2010, 'gender' => 'm', 'department' => 'Farmakologiya kafedrasi',
        'employment_form' => 'x', 'employment_staff' => 'x', 'staff_position' => 'x', 'employee_status' => 'x', 'employee_type' => 'x',
        'contract_number' => '1', 'decree_number' => '1', 'contract_date' => '2020-01-01', 'decree_date' => '2020-01-01',
        'status' => true, 'login' => 'io' . random_int(100000, 999999), 'password' => bcrypt('x')])->save();
    $t->assignRole('admin');
    return $t;
}

function ioSetup(): array
{
    $teacher = ioTeacher();
    $board = TimetableBoard::create(['name' => 'Info-only', 'academic_year' => '2026-2027', 'semester_parity' => 'kuzgi',
        'days' => 6, 'pairs_per_day' => 12, 'weeks' => 4, 'settings' => ['semester_start' => '2026-09-14']]);
    $room = Auditorium::create(['code' => 'IO101', 'name' => '101', 'volume' => 10, 'active' => true]);
    $cards = [];
    foreach ([5 => ['d1/d22-04'], 6 => ['p/p21-01a', 'p/p21-01b']] as $course => $groups) {
        TimetableSubjectSetting::create(['board_id' => $board->id, 'specialty_name' => 'davolash ishi', 'course' => $course,
            'subject_name' => 'Klinik farmakologiya', 'mode' => 'cycle', 'cycle_days' => 2]);
        foreach ($groups as $group) {
            $cards[] = TimetableCard::create(['board_id' => $board->id, 'faculty_name' => 'F1', 'specialty_name' => 'davolash ishi',
                'course' => $course, 'oqim_label' => $course . '-oqim', 'lang' => 'uz', 'students' => $course === 6 ? 100 : 5,
                'training_type' => 'practice', 'len_half' => 2, 'weeks' => 4, 'subject_name' => 'Klinik farmakologiya',
                'group_name' => $group, 'kafedra_name' => 'Farmakologiya kafedrasi', 'teacher_id' => $teacher->id,
                'teacher_name' => $teacher->full_name, 'auditorium_code' => $room->code, 'auditorium_name' => $room->name]);
            TimetableCyclePlacement::create(['board_id' => $board->id, 'specialty_name' => 'davolash ishi', 'course' => $course,
                'group_name' => $group, 'subject_name' => 'Klinik farmakologiya', 'start_index' => 0, 'training_type' => 'practice']);
        }
    }
    return compact('teacher', 'board', 'room', 'cards');
}

function ioCall(string $method, ...$args)
{
    $ctl = new TimetableController();
    return (new ReflectionMethod($ctl, $method))->invoke($ctl, ...$args);
}

function ioAs(Teacher $teacher)
{
    return test()->withoutVite()->actingAs($teacher, 'teacher')->withSession(['active_role' => 'admin']);
}

it('does not count sixth-course teachers, rooms or capacity, but restores conflicts when disabled', function () {
    $s = ioSetup();
    expect(ioCall('cycleConflicts', $s['board']))->toBe([]);
    $busy = ioCall('unitBusy', $s['board'], collect([$s['cards'][0]]));
    expect($busy['teachers'])->toBe([])->and($busy['rooms'])->toBe([]);
    $s['board']->update(['settings' => ['info_only_courses' => [], 'semester_start' => '2026-09-14']]);
    expect(ioCall('cycleConflicts', $s['board']))->not->toBe([]);
});

it('keeps ordinary fifth-course collisions', function () {
    $s = ioSetup();
    $s['cards'][1]->update(['course' => 5, 'group_name' => 'd1/d22-05']);
    TimetableCyclePlacement::where('board_id', $s['board']->id)->where('group_name', 'p/p21-01a')
        ->update(['course' => 5, 'group_name' => 'd1/d22-05']);
    expect(ioCall('cycleConflicts', $s['board']))->not->toBe([]);
});

it('shows informational lessons in the teacher agenda while keeping the slot free', function () {
    $s = ioSetup();
    $s['cards'][0]->update(['teacher_id' => null, 'auditorium_code' => null]);
    $response = ioAs($s['teacher'])->getJson(route('admin.timetable.teacher-agenda', $s['board']) . '?' . http_build_query([
        'teacher_id' => $s['teacher']->id, 'date' => '2026-09-14', 'range' => 'day', 'layer' => 'internal',
    ]))->assertOk();
    expect($response->json('entries'))->not->toBeEmpty();
    foreach ($response->json('entries') as $entry) {
        expect($entry['info_only'])->toBeTrue()->and($entry['conflict'])->toBeFalse();
    }
    expect($response->json('free.2026-09-14'))->toContain(1);
});

it('accepts and validates configurable informational courses without a migration', function () {
    $s = ioSetup();
    $url = route('admin.timetable.settings.save', $s['board']);
    $body = ['days' => 6, 'bell_schedule' => [['type' => 'pair', 'start' => '08:30', 'end' => '09:50']],
        'settings' => ['info_only_courses' => [3, 6]]];
    ioAs($s['teacher'])->postJson($url, $body)->assertOk();
    expect($s['board']->fresh()->settings['info_only_courses'])->toBe([3, 6]);
    ioAs($s['teacher'])->postJson($url, array_replace_recursive($body, ['settings' => ['info_only_courses' => [8]]]))->assertUnprocessable();
    $body['settings']['info_only_courses'] = [];
    ioAs($s['teacher'])->postJson($url, $body)->assertOk();
    expect($s['board']->fresh()->settings['info_only_courses'])->toBe([]);
    $body['settings']['info_only_courses'] = [6];
    ioAs($s['teacher'])->postJson($url, $body)->assertOk();
    unset($body['settings']['info_only_courses']);
    $body['info_only_courses_present'] = 1;
    ioAs($s['teacher'])->post($url, $body)->assertOk();
    expect($s['board']->fresh()->settings['info_only_courses'])->toBe([]);
});

it('assigns a fifth-course unit without confirmation when only online sixth-course units overlap', function () {
    $s = ioSetup();
    $s['cards'][0]->update(['teacher_id' => null, 'teacher_name' => null, 'auditorium_code' => null, 'auditorium_name' => null]);
    ioAs($s['teacher'])->postJson(route('admin.timetable.cycle-assign', $s['board']), [
        'specialty_name' => 'davolash ishi', 'course' => 5, 'group_name' => 'd1/d22-04',
        'subject_name' => 'Klinik farmakologiya', 'training_type' => 'practice', 'view' => 'group', 'layer' => 'internal',
        'teacher_id' => $s['teacher']->id, 'auditorium_code' => 'IO101',
    ])->assertOk()->assertJsonMissing(['needs_confirm' => true]);
    expect($s['cards'][0]->fresh()->teacher_id)->toBe($s['teacher']->id);
});

it('allows a weekly suggestion into a slot occupied only by an informational lesson', function () {
    $s = ioSetup();
    TimetableSubjectSetting::where('board_id', $s['board']->id)->delete();
    TimetableCyclePlacement::where('board_id', $s['board']->id)->delete();
    $s['cards'][0]->update(['course' => 2, 'day' => 2, 'pair' => 1]);
    $s['cards'][1]->update(['day' => 1, 'pair' => 1]);
    $body = ['week' => 1, 'card_id' => $s['cards'][0]->id, 'reason' => 'Onlayn dars vaqtiga',
        'actions' => [['op' => 'move', 'card_id' => $s['cards'][0]->id, 'week' => 1, 'day' => 1, 'pair' => 1]]];
    ioAs($s['teacher'])->postJson(route('admin.timetable.week-suggestions.apply', $s['board']), $body)->assertOk();
    expect(\App\Models\TimetableCardOverride::where('card_id', $s['cards'][0]->id)->value('day'))->toBe(1);
    $s['board']->update(['settings' => ['semester_start' => '2026-09-14', 'info_only_courses' => []]]);
    expect(ioCall('findWeekConflicts', $s['cards'][0]->fresh(), 1, 1, 1))->not->toBeEmpty();
});

it('keeps online unit assignment options free even when the room capacity is too small', function () {
    $s = ioSetup();
    $body = ['specialty_name' => 'davolash ishi', 'course' => 6, 'group_name' => 'p/p21-01a',
        'subject_name' => 'Klinik farmakologiya', 'training_type' => 'practice', 'view' => 'group', 'layer' => 'internal'];
    $options = ioAs($s['teacher'])->postJson(route('admin.timetable.cycle-assign-options', $s['board']), $body)->assertOk();
    expect($options->json('busy_teachers'))->toBe([])
        ->and(array_column($options->json('rooms'), 'code'))->toContain('IO101');
    ioAs($s['teacher'])->postJson(route('admin.timetable.cycle-assign', $s['board']), $body + [
        'teacher_id' => $s['teacher']->id, 'auditorium_code' => 'IO101',
    ])->assertOk()->assertJsonMissing(['needs_confirm' => true]);
});

it('allows overlapping online group blocks while retaining ordinary group placement validation', function () {
    $s = ioSetup();
    $other = $s['cards'][1]->replicate();
    $other->subject_name = 'Ikkinchi onlayn fan';
    $other->save();
    TimetableSubjectSetting::create(['board_id' => $s['board']->id, 'specialty_name' => 'davolash ishi', 'course' => 6,
        'subject_name' => $other->subject_name, 'mode' => 'cycle', 'cycle_days' => 2]);
    $body = ['specialty_name' => 'davolash ishi', 'course' => 6, 'group_name' => 'p/p21-01a',
        'subject_name' => $other->subject_name, 'training_type' => 'practice', 'view' => 'group', 'layer' => 'internal',
        'action' => 'place', 'start_index' => 0, 'pair' => 1];
    ioAs($s['teacher'])->postJson(route('admin.timetable.cycle-place', $s['board']), $body)->assertOk();
    expect(TimetableCyclePlacement::where('board_id', $s['board']->id)->where('group_name', 'p/p21-01a')->count())->toBe(2);
    TimetableCyclePlacement::where('board_id', $s['board']->id)->where('subject_name', $other->subject_name)->delete();
    $s['board']->update(['settings' => ['semester_start' => '2026-09-14', 'info_only_courses' => []]]);
    ioAs($s['teacher'])->postJson(route('admin.timetable.cycle-place', $s['board']), $body)->assertUnprocessable();
});

it('preserves competing-request protection for one online lesson without reserving its resources', function () {
    $s = ioSetup();
    $online = $s['cards'][1];
    \App\Models\TimetableChangeRequest::create(['board_id' => $s['board']->id, 'requester_id' => $s['teacher']->id,
        'requester_type' => $s['teacher']->getMorphClass(), 'requester_name' => 'Sinov', 'requester_role' => 'oqituvchi',
        'route_name' => 'admin.timetable.cards.week-override', 'target_key' => 'online-week-1', 'params' => ['card' => $online->id],
        'payload' => ['action' => 'move', 'week' => 1, 'day' => 1, 'pair' => 1], 'kind' => 'move', 'summary' => 'Onlayn dars',
        'details' => [], 'status' => 'pending', 'approval_stage' => 'department']);
    $move = ['week' => 1, 'day' => 1, 'pair' => 1];
    expect(ioCall('pendingTimeConflicts', $s['cards'][0], $move, []))->toBe([])
        ->and(ioCall('pendingTimeConflicts', $s['cards'][2], $move, []))->toBe([]);
    $same = ioCall('pendingTimeConflicts', $online, $move, []);
    expect($same)->toHaveCount(1)->and($same[0])->toContain('shu dars')->not->toContain('guruh:', 'auditoriya:', 'o‘qituvchi');
});
