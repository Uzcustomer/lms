<?php

use App\Enums\ProjectRole;
use App\Models\Teacher;
use App\Models\User;
use Spatie\Permission\Models\Role;

/*
 * Xodim hisoblarini (rol, parol, login) boshqarish faqat rol boshqaruvchilariga
 * (superadmin, admin, kichik_admin) ochiq, superadmin hisobini esa faqat superadmin
 * o'zgartira oladi. Ma'lumotlar — faqat soxta.
 */

beforeEach(function () {
    foreach (ProjectRole::cases() as $role) {
        Role::firstOrCreate(['name' => $role->value, 'guard_name' => 'web']);
    }
});

function fakeTeacher(array $roles = [], array $attrs = []): Teacher
{
    static $n = 0;
    $n++;

    $teacher = Teacher::forceCreate(array_merge([
        'hemis_id' => 900000 + $n,
        'full_name' => "Sinov Xodim {$n}",
        'short_name' => "Sinov X. {$n}",
        'first_name' => 'Sinov',
        'second_name' => "Xodim{$n}",
        'employee_id_number' => 'T-' . (700000 + $n),
        'birth_date' => '1990-01-15',
        'year_of_enter' => 2020,
        'gender' => 'Erkak',
        'department' => 'Sinov kafedrasi',
        'employment_form' => 'Asosiy',
        'employment_staff' => '1',
        'staff_position' => 'Assistent',
        'employee_status' => 'Ishlamoqda',
        'employee_type' => 'O\'qituvchi',
        'contract_number' => 'C-' . $n,
        'decree_number' => 'D-' . $n,
        'contract_date' => '2020-09-01',
        'decree_date' => '2020-09-01',
        'login' => "sinov{$n}",
        'password' => 'eski-parol-123',
    ], $attrs));

    if ($roles) {
        $teacher->syncRoles($roles);
    }

    return $teacher;
}

/**
 * Route darajasida rad etish: ilova Spatie UnauthorizedException ni 403 emas,
 * "back + error" redirect sifatida qaytaradi (bootstrap/app.php).
 */
function assertRoleDenied($response): void
{
    $response->assertRedirect()->assertSessionHas('error');
}

function fakeUser(array $roles): User
{
    static $n = 0;
    $n++;

    $user = User::forceCreate([
        'name' => "Sinov Admin {$n}",
        'email' => "admin{$n}@example.test",
        'password' => bcrypt('parol-12345'),
    ]);
    $user->syncRoles($roles);

    return $user;
}

test("oddiy o'qituvchi boshqa xodimga rol bera olmaydi", function () {
    $actor = fakeTeacher(['oqituvchi']);
    $target = fakeTeacher(['oqituvchi']);

    assertRoleDenied($this->actingAs($actor, 'teacher')
        ->put(route('admin.teachers.update-roles', $target), ['roles' => ['superadmin']]));

    expect($target->fresh()->getRoleNames()->all())->toBe(['oqituvchi']);
});

test("oddiy o'qituvchi o'ziga superadmin rolini bera olmaydi", function () {
    $actor = fakeTeacher(['oqituvchi']);

    assertRoleDenied($this->actingAs($actor, 'teacher')
        ->put(route('admin.teachers.update-roles', $actor), ['roles' => ['oqituvchi', 'superadmin']]));

    expect($actor->fresh()->hasRole('superadmin'))->toBeFalse();
});

test("dekan xodim parolini tiklay olmaydi va login/parolini o'zgartira olmaydi", function () {
    $actor = fakeTeacher(['dekan']);
    $target = fakeTeacher(['oqituvchi']);
    $hash = $target->password;

    assertRoleDenied($this->actingAs($actor, 'teacher')
        ->post(route('admin.teachers.reset-password', $target)));

    assertRoleDenied($this->actingAs($actor, 'teacher')
        ->put(route('admin.teachers.update', $target), ['login' => 'boshqa', 'password' => 'yangi-parol', 'status' => 1]));

    $fresh = $target->fresh();
    expect($fresh->password)->toBe($hash)
        ->and($fresh->login)->toBe($target->login);
});

test("oddiy xodim foydalanuvchilar va audit jurnaliga kira olmaydi", function () {
    $actor = fakeTeacher(['registrator_ofisi']);

    assertRoleDenied($this->actingAs($actor, 'teacher')->get(route('admin.users.index')));
    assertRoleDenied($this->actingAs($actor, 'teacher')->get(route('admin.activity-log.index')));
    assertRoleDenied($this->actingAs($actor, 'teacher')
        ->post(route('admin.users.store'), [
            'name' => 'Yangi', 'email' => 'yangi@example.test', 'password' => 'parol-12345', 'roles' => ['superadmin'],
        ]));

    expect(User::where('email', 'yangi@example.test')->exists())->toBeFalse();
});

test('admin xodimga oddiy rol bera oladi', function () {
    $admin = fakeUser(['admin']);
    $target = fakeTeacher(['oqituvchi']);

    $this->actingAs($admin, 'web')
        ->put(route('admin.teachers.update-roles', $target), ['roles' => ['oqituvchi', 'tyutor']])
        ->assertRedirect();

    expect($target->fresh()->hasRole('tyutor'))->toBeTrue();
});

test('admin superadmin rolini bera olmaydi, superadmin esa bera oladi', function () {
    $admin = fakeUser(['admin']);
    $superadmin = fakeUser(['superadmin']);
    $target = fakeTeacher(['oqituvchi']);

    $this->actingAs($admin, 'web')
        ->put(route('admin.teachers.update-roles', $target), ['roles' => ['oqituvchi', 'superadmin']])
        ->assertForbidden();
    expect($target->fresh()->hasRole('superadmin'))->toBeFalse();

    $this->actingAs($superadmin, 'web')
        ->put(route('admin.teachers.update-roles', $target), ['roles' => ['oqituvchi', 'superadmin']])
        ->assertRedirect();
    expect($target->fresh()->hasRole('superadmin'))->toBeTrue();
});

test('kichik admin superadmin hisobining parolini tiklay olmaydi va uni o\'chira olmaydi', function () {
    $junior = fakeUser(['kichik_admin']);
    $superTeacher = fakeTeacher(['superadmin']);
    $superUser = fakeUser(['superadmin']);

    $this->actingAs($junior, 'web')
        ->post(route('admin.teachers.reset-password', $superTeacher))
        ->assertForbidden();

    $this->actingAs($junior, 'web')
        ->delete(route('admin.users.destroy', $superUser))
        ->assertForbidden();

    expect(User::whereKey($superUser->id)->exists())->toBeTrue();
});

test('admin oddiy xodim parolini tiklay oladi', function () {
    $admin = fakeUser(['admin']);
    $target = fakeTeacher(['oqituvchi']);

    $this->actingAs($admin, 'web')
        ->post(route('admin.teachers.reset-password', $target))
        ->assertRedirect();

    expect($target->fresh()->must_change_password)->toBeTruthy();
});
