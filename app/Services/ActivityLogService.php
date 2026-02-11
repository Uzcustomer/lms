<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ActivityLogService
{
    /**
     * Faoliyat jurnalini yozish (C-variant — qo'lda chaqirish uchun)
     */
    public static function log(
        string $action,
        string $module,
        ?string $description = null,
        ?Model $subject = null,
        ?array $oldValues = null,
        ?array $newValues = null
    ): ActivityLog {
        $guard = self::detectGuard();
        $user = Auth::guard($guard)->user();

        return ActivityLog::create([
            'guard' => $guard,
            'user_id' => $user?->id,
            'user_name' => self::getUserName($user),
            'role' => self::getActiveRole($user),
            'action' => $action,
            'module' => $module,
            'description' => $description,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id' => $subject?->id ?? $subject?->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);
    }

    /**
     * Model yaratilganda log
     */
    public static function logCreated(Model $model, string $module, ?string $description = null): ActivityLog
    {
        return self::log('create', $module, $description, $model, null, self::getLoggableValues($model));
    }

    /**
     * Model yangilanganda log (faqat o'zgargan maydonlar)
     */
    public static function logUpdated(Model $model, string $module, ?string $description = null): ActivityLog
    {
        $dirty = $model->getDirty();
        $original = collect($model->getOriginal())->only(array_keys($dirty))->toArray();
        $changed = collect($dirty)->toArray();

        return self::log('update', $module, $description, $model, $original, $changed);
    }

    /**
     * Model o'chirilganda log
     */
    public static function logDeleted(Model $model, string $module, ?string $description = null): ActivityLog
    {
        return self::log('delete', $module, $description, $model, self::getLoggableValues($model));
    }

    /**
     * Login log
     */
    public static function logLogin(?string $guard = null): ActivityLog
    {
        $guard = $guard ?? self::detectGuard();
        $user = Auth::guard($guard)->user();

        return ActivityLog::create([
            'guard' => $guard,
            'user_id' => $user?->id,
            'user_name' => self::getUserName($user),
            'role' => self::getActiveRole($user),
            'action' => 'login',
            'module' => 'auth',
            'description' => 'Tizimga kirdi',
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);
    }

    /**
     * Logout log
     */
    public static function logLogout(?string $guard = null): ActivityLog
    {
        $guard = $guard ?? self::detectGuard();
        $user = Auth::guard($guard)->user();

        return ActivityLog::create([
            'guard' => $guard,
            'user_id' => $user?->id,
            'user_name' => self::getUserName($user),
            'role' => self::getActiveRole($user),
            'action' => 'logout',
            'module' => 'auth',
            'description' => 'Tizimdan chiqdi',
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);
    }

    /**
     * Joriy guard'ni aniqlash
     */
    protected static function detectGuard(): string
    {
        foreach (['teacher', 'student', 'web'] as $guard) {
            if (Auth::guard($guard)->check()) {
                return $guard;
            }
        }
        return 'web';
    }

    /**
     * Foydalanuvchi ismini olish
     */
    protected static function getUserName($user): ?string
    {
        if (!$user) return null;
        return $user->name ?? $user->full_name ?? $user->short_name ?? null;
    }

    /**
     * Faol rolni olish
     */
    protected static function getActiveRole($user): ?string
    {
        if (!$user) return null;

        $roles = $user->getRoleNames()->toArray();
        $activeRole = session('active_role', $roles[0] ?? null);

        if ($activeRole && !in_array($activeRole, $roles) && count($roles) > 0) {
            $activeRole = $roles[0];
        }

        return $activeRole;
    }

    /**
     * Modeldan log uchun qiymatlarni olish (password kabi maxfiy maydonlarni chiqarish)
     */
    protected static function getLoggableValues(Model $model): array
    {
        $hidden = ['password', 'local_password', 'remember_token', 'token',
                    'telegram_verification_code', 'two_factor_secret'];

        return collect($model->getAttributes())
            ->except($hidden)
            ->toArray();
    }
}
