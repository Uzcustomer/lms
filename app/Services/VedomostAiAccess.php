<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Vedomost AI imkoniyatlariga ruxsat: AI sozlamalari, AI tekshiruvi (natijalar, yig'ma oyna)
 * va tasdiqlanganlar jamlanmasi. Superadmin — har doim; boshqalar — superadmin AI sozlamalarida
 * ism-familiya bo'yicha qidirib tanlagan foydalanuvchilar (har biriga alohida ruxsatlar).
 *
 * Ro'yxat settings jadvalida JSON: [{type, id, name, perms: {settings, check, jamlanma}}].
 * Foydalanuvchi — User (web) yoki Teacher (teacher guard) bo'lishi mumkin: type — morph class.
 */
class VedomostAiAccess
{
    private const SETTING_KEY = 'vedomost_ai_access';

    /** Ruxsat turlari => yorliq. */
    public const PERMS = [
        'settings' => 'AI sozlamalari',
        'check' => 'AI tekshiruvi va natijalari',
        'jamlanma' => 'Jamlanma yuklab olish',
    ];

    private ?array $cache = null;

    public static function isSuperadmin(): bool
    {
        return session('active_role', '') === 'superadmin';
    }

    /** Joriy foydalanuvchi shu imkoniyatdan foydalana oladimi. */
    public function can(string $perm): bool
    {
        if (self::isSuperadmin()) {
            return true;
        }
        $actor = auth()->user();
        if (!$actor instanceof Model) {
            return false;
        }
        foreach ($this->all() as $e) {
            if ($e['type'] === $actor->getMorphClass() && (int) $e['id'] === (int) $actor->getKey()) {
                return !empty($e['perms'][$perm]);
            }
        }

        return false;
    }

    /** @return array<int, array{type: string, id: int, name: string, perms: array<string, bool>}> */
    public function all(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }
        $list = json_decode((string) Setting::get(self::SETTING_KEY, '[]'), true);

        return $this->cache = is_array($list) ? array_values(array_filter($list, fn ($e) => isset($e['type'], $e['id']))) : [];
    }

    /**
     * Ro'yxatni saqlaydi (faqat ma'lum turdagi foydalanuvchilar; nomi bazadan olinadi).
     *
     * @param  array<int, array{type: string, id: int|string, perms?: array}>  $entries
     */
    public function save(array $entries): void
    {
        $out = [];
        foreach ($entries as $e) {
            $model = $this->resolve((string) ($e['type'] ?? ''), (int) ($e['id'] ?? 0));
            if (!$model) {
                continue;
            }
            $perms = [];
            foreach (array_keys(self::PERMS) as $p) {
                $perms[$p] = filter_var($e['perms'][$p] ?? false, FILTER_VALIDATE_BOOLEAN);
            }
            $out[$model->getMorphClass() . ':' . $model->getKey()] = [
                'type' => $model->getMorphClass(),
                'id' => (int) $model->getKey(),
                'name' => self::nameOf($model),
                'perms' => $perms,
            ];
        }
        Setting::set(self::SETTING_KEY, json_encode(array_values($out), JSON_UNESCAPED_UNICODE));
        $this->cache = array_values($out);
    }

    /**
     * Ism-familiya bo'yicha qidirish: tizim foydalanuvchilari (users) va xodimlar (teachers).
     *
     * @return array<int, array{type: string, id: int, name: string, hint: string}>
     */
    public function search(string $q, int $limit = 20): array
    {
        $q = trim($q);
        if (mb_strlen($q) < 2) {
            return [];
        }
        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
        $out = [];
        foreach (User::where('name', 'like', $like)->orderBy('name')->limit($limit)->get() as $u) {
            $out[] = ['type' => $u->getMorphClass(), 'id' => (int) $u->id, 'name' => self::nameOf($u),
                'hint' => trim('Foydalanuvchi' . (method_exists($u, 'getRoleNames') ? ' · ' . $u->getRoleNames()->implode(', ') : ''), ' ·')];
        }
        foreach (Teacher::where('full_name', 'like', $like)->orderBy('full_name')->limit($limit)->get() as $t) {
            $out[] = ['type' => $t->getMorphClass(), 'id' => (int) $t->id, 'name' => self::nameOf($t),
                'hint' => trim('Xodim' . (method_exists($t, 'getRoleNames') ? ' · ' . $t->getRoleNames()->implode(', ') : ''), ' ·')];
        }

        return array_slice($out, 0, $limit * 2);
    }

    private function resolve(string $type, int $id): ?Model
    {
        if ($id <= 0) {
            return null;
        }
        foreach ([User::class, Teacher::class] as $cls) {
            $m = new $cls();
            if ($m->getMorphClass() === $type) {
                return $cls::find($id);
            }
        }

        return null;
    }

    private static function nameOf(Model $m): string
    {
        return (string) ($m->full_name ?? $m->name ?? $m->short_name ?? ('#' . $m->getKey()));
    }
}
