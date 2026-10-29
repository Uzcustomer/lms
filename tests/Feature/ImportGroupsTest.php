<?php

use App\Models\Group;
use Illuminate\Support\Facades\Http;

// HEMIS group-list elementi (import:groups o'qiydigan maydonlar).
function hemisGroupItem(int $id, string $name, int $curriculum, ?bool $active = null): array
{
    return array_filter([
        'id' => $id, 'name' => $name, '_curriculum' => $curriculum, 'active' => $active,
        'department' => ['id' => 5, 'name' => 'Xalqaro talim fakulteti', 'code' => 'F5', 'active' => true,
            'structureType' => ['code' => '11', 'name' => 'Fakultet'], 'localityType' => ['code' => '11', 'name' => 'Mahalliy']],
        'specialty' => ['id' => 7, 'code' => '60910200', 'name' => 'Pediatriya ishi'],
        'educationLang' => ['code' => '12', 'name' => 'Rus'],
    ], fn ($v) => $v !== null);
}

function localGroup(int $hemisId, string $name, int $curriculum, bool $active): Group
{
    return Group::create(['group_hemis_id' => $hemisId, 'name' => $name, 'department_hemis_id' => 5,
        'department_name' => 'Xalqaro talim fakulteti', 'department_code' => 'F5', 'department_structure_type_code' => '11',
        'department_structure_type_name' => 'Fakultet', 'department_locality_type_code' => '11', 'department_locality_type_name' => 'Mahalliy',
        'department_active' => true, 'specialty_hemis_id' => 7, 'specialty_code' => '60910200', 'specialty_name' => 'Pediatriya ishi',
        'education_lang_code' => '12', 'education_lang_name' => 'Rus', 'curriculum_hemis_id' => $curriculum, 'active' => $active]);
}

// Sahifalar: [sahifa => elementlar]; null — shu sahifada HEMIS xatosi.
function fakeGroupList(array $pages): void
{
    config(['services.telegram.bot_token' => null]);
    Http::fake(['*group-list*' => function ($request) use ($pages) {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $q);
        $page = (int) ($q['page'] ?? 1);
        $items = array_key_exists($page, $pages) ? $pages[$page] : [];

        return $items === null ? Http::response('xato', 500)
            : Http::response(['data' => ['items' => $items, 'pagination' => ['pageCount' => count($pages)]]]);
    }]);
}

it('HEMIS ro\'yxatida yo\'q bo\'lgan faol guruh to\'liq importdan keyin nofaol qilinadi', function () {
    // pp/21-01 — HEMIS'da eski guruh (157) endi ro'yxatda yo'q, yangisi (235) bor
    $old = localGroup(14, 'pp/21-01', 157, true);
    $new = localGroup(1169, 'pp/21-01', 235, true);
    $archived = localGroup(20, 'pp/19-01', 100, false);
    $off = localGroup(30, 'pp/22-01', 240, true);
    fakeGroupList([
        1 => [hemisGroupItem(1169, 'pp/21-01', 235)],
        // HEMIS'ning o'zi nofaol degan guruh — ro'yxatda bor, faolligi HEMIS maydonidan
        2 => [hemisGroupItem(30, 'pp/22-01', 240, false)],
    ]);

    $this->artisan('import:groups')->assertSuccessful();

    expect($old->fresh()->active)->toBeFalse()
        ->and($new->fresh()->active)->toBeTrue()
        ->and($archived->fresh()->active)->toBeFalse()
        ->and($off->fresh()->active)->toBeFalse();
});

it('import chala qolsa (HEMIS xatosi) hech bir guruh nofaol qilinmaydi', function () {
    $old = localGroup(14, 'pp/21-01', 157, true);
    $new = localGroup(1169, 'pp/21-01', 235, true);
    fakeGroupList([1 => [hemisGroupItem(1169, 'pp/21-01', 235)], 2 => null]);

    $this->artisan('import:groups')->assertSuccessful();

    expect($old->fresh()->active)->toBeTrue()
        ->and($new->fresh()->active)->toBeTrue();
});

it('HEMIS bo\'sh ro\'yxat qaytarsa hech bir guruh nofaol qilinmaydi', function () {
    $old = localGroup(14, 'pp/21-01', 157, true);
    fakeGroupList([1 => []]);

    $this->artisan('import:groups')->assertSuccessful();

    expect($old->fresh()->active)->toBeTrue();
});
