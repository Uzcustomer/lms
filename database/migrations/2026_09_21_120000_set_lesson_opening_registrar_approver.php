<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Dars ochish: 3-so'rovdan boshlab registrator ofisi tomonidan faqat bitta
 * xodim — o'quv bo'limi ishlarini olib boradigan Sharipov Abror — imzolaydi.
 * Ofisda o'nlab xodim bor, shuning uchun aynan o'sha kishi bir marta yozib
 * qo'yiladi. Keyin uni sahifadagi "Registratordan:" ro'yxatidan almashtirish
 * mumkin — sozlama bo'sh bo'lmasa bu migratsiya hech narsaga tegmaydi.
 */
return new class extends Migration
{
    private const SETTING_KEY = 'lesson_opening_registrar_approver';
    private const ROLE = 'registrator_ofisi';

    public function up(): void
    {
        if (!Schema::hasTable('settings') || !Schema::hasTable('roles')) {
            return;
        }

        $current = trim((string) (DB::table('settings')
            ->where('key', self::SETTING_KEY)
            ->value('value') ?? ''));

        if ($current !== '') {
            return; // allaqachon tanlangan — tegmaymiz
        }

        $key = $this->findApprover();
        if ($key === null) {
            return;
        }

        DB::table('settings')->updateOrInsert(
            ['key' => self::SETTING_KEY],
            ['value' => $key, 'updated_at' => now(), 'created_at' => now()]
        );
    }

    public function down(): void
    {
        DB::table('settings')->where('key', self::SETTING_KEY)->delete();
    }

    /** Registrator ofisi rolidagi Sharipov Abror: "teacher:12" yoki "web:3" */
    private function findApprover(): ?string
    {
        $roleId = DB::table('roles')->where('name', self::ROLE)->value('id');
        if (!$roleId) {
            return null;
        }

        $matches = function (?string $name) {
            $name = mb_strtolower((string) $name);

            return str_contains($name, 'sharipov') && str_contains($name, 'abror');
        };

        if (Schema::hasTable('teachers')) {
            $teachers = DB::table('teachers as t')
                ->join('model_has_roles as mr', function ($join) use ($roleId) {
                    $join->on('mr.model_id', '=', 't.id')
                        ->where('mr.role_id', $roleId)
                        ->where('mr.model_type', 'App\Models\Teacher');
                })
                ->get(['t.id', 't.full_name as name']);

            foreach ($teachers as $teacher) {
                if ($matches($teacher->name)) {
                    return 'teacher:' . $teacher->id;
                }
            }
        }

        if (Schema::hasTable('users')) {
            $users = DB::table('users as u')
                ->join('model_has_roles as mr', function ($join) use ($roleId) {
                    $join->on('mr.model_id', '=', 'u.id')
                        ->where('mr.role_id', $roleId)
                        ->where('mr.model_type', 'App\Models\User');
                })
                ->get(['u.id', 'u.name']);

            foreach ($users as $user) {
                if ($matches($user->name)) {
                    return 'web:' . $user->id;
                }
            }
        }

        return null;
    }
};
