<?php

namespace App\Services\Monitoring;

use App\Models\MonitoringIstisno;
use Illuminate\Database\Eloquent\Model;

/**
 * Monitoring kirish qoidalari (TZ 2-bo'lim). Rollar config('monitoring.roles') da:
 *  - manage: hammasini ko'radi, detektor sozlaydi, istisnoni yopadi/qayta ochadi;
 *  - work:   hammasini ko'radi, import qiladi, mas'ul biriktiradi, izoh yozadi;
 *  - view:   panel va ro'yxatni faqat ko'radi;
 *  - mas'ul: rolidan qat'i nazar faqat o'ziga biriktirilgan istisnolarni ko'radi va javob beradi.
 *
 * Xavfsizlik uchun faol rol emas, foydalanuvchining istalgan roli hisobga olinadi
 * (menyu esa faol rol bo'yicha ko'rsatiladi).
 */
final class MonitoringAccess
{
    public static function actor(): ?Model
    {
        $user = auth()->user();

        return $user instanceof Model ? $user : null;
    }

    private static function hasAny(?Model $actor, string $group): bool
    {
        $roles = config("monitoring.roles.{$group}", []);

        return $actor !== null
            && $roles !== []
            && method_exists($actor, 'hasAnyRole')
            && $actor->hasAnyRole($roles);
    }

    public static function canManage(?Model $actor): bool
    {
        return self::hasAny($actor, 'manage');
    }

    public static function canWork(?Model $actor): bool
    {
        return self::canManage($actor) || self::hasAny($actor, 'work');
    }

    public static function canViewAll(?Model $actor): bool
    {
        return self::canWork($actor) || self::hasAny($actor, 'view');
    }

    public static function canView(?Model $actor, MonitoringIstisno $istisno): bool
    {
        return self::canViewAll($actor) || $istisno->isOwnedBy($actor);
    }

    /** Javob/izoh yozish va dalil yuklash. */
    public static function canRespond(?Model $actor, MonitoringIstisno $istisno): bool
    {
        if (!$istisno->isOpen()) {
            return false;
        }

        return $istisno->isOwnedBy($actor) || self::canWork($actor);
    }

    public static function canAssign(?Model $actor): bool
    {
        return self::canWork($actor);
    }

    public static function canClose(?Model $actor): bool
    {
        return self::canManage($actor);
    }

    public static function openAssignedCount(?Model $actor): int
    {
        if ($actor === null) {
            return 0;
        }

        return MonitoringIstisno::query()->ownedBy($actor)->open()->count();
    }

    /** Mas'ul sifatida tanlanishi mumkin bo'lgan model klasslari. */
    public static function ownerTypes(): array
    {
        return [
            'teacher' => \App\Models\Teacher::class,
            'user' => \App\Models\User::class,
        ];
    }

    /** Mas'ul kaliti: «teacher:5» yoki «user:2» (formalarda va params'da saqlanadi). */
    public static function ownerKey(?Model $model): ?string
    {
        if ($model === null) {
            return null;
        }
        $alias = array_search(get_class($model), self::ownerTypes(), true);

        return $alias === false ? null : $alias . ':' . $model->getKey();
    }

    public static function resolveOwnerKey(?string $key): ?Model
    {
        if (!$key || !str_contains($key, ':')) {
            return null;
        }
        [$type, $id] = explode(':', $key, 2);
        $class = self::ownerTypes()[$type] ?? null;

        return ($class && ctype_digit($id)) ? $class::find((int) $id) : null;
    }

    public static function displayName(?Model $model): string
    {
        if ($model === null) {
            return '—';
        }

        return $model->full_name ?? $model->name ?? ('#' . $model->getKey());
    }
}
