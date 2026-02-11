<?php

namespace App\Traits;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;

/**
 * A-variant: Modelga qo'shilganda create/update/delete avtomatik loglanadi.
 *
 * Ishlatish:
 *   use LogsActivity;
 *   protected static string $activityModule = 'student_grade';
 *   protected static array $logOnly = ['grade', 'status', 'reason']; // ixtiyoriy
 */
trait LogsActivity
{
    public static function bootLogsActivity(): void
    {
        static::created(function ($model) {
            static::writeActivityLog('create', $model, null, static::getLoggableAttributes($model));
        });

        static::updated(function ($model) {
            $dirty = $model->getDirty();
            if (empty($dirty)) return;

            $logOnly = static::$logOnly ?? null;
            if ($logOnly) {
                $dirty = collect($dirty)->only($logOnly)->toArray();
                if (empty($dirty)) return;
            }

            $original = collect($model->getOriginal())->only(array_keys($dirty))->toArray();

            static::writeActivityLog('update', $model, $original, $dirty);
        });

        static::deleted(function ($model) {
            static::writeActivityLog('delete', $model, static::getLoggableAttributes($model));
        });
    }

    protected static function writeActivityLog(
        string $action,
        $model,
        ?array $oldValues = null,
        ?array $newValues = null
    ): void {
        $guard = static::detectCurrentGuard();
        $user = Auth::guard($guard)->user();

        try {
            ActivityLog::create([
                'guard' => $guard,
                'user_id' => $user?->id,
                'user_name' => $user?->name ?? $user?->full_name ?? $user?->short_name ?? null,
                'role' => static::resolveActiveRole($user),
                'action' => $action,
                'module' => static::$activityModule ?? 'unknown',
                'description' => static::getActivityDescription($action, $model),
                'subject_type' => get_class($model),
                'subject_id' => $model->getKey(),
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('ActivityLog write failed: ' . $e->getMessage());
        }
    }

    protected static function getActivityDescription(string $action, $model): string
    {
        $module = static::$activityModule ?? class_basename($model);
        $labels = [
            'create' => 'yaratildi',
            'update' => 'tahrirlandi',
            'delete' => "o'chirildi",
        ];

        return ucfirst($module) . ' ' . ($labels[$action] ?? $action);
    }

    protected static function getLoggableAttributes($model): array
    {
        $hidden = ['password', 'local_password', 'remember_token', 'token',
                    'telegram_verification_code', 'two_factor_secret'];

        $attributes = $model->getAttributes();

        $logOnly = static::$logOnly ?? null;
        if ($logOnly) {
            $attributes = collect($attributes)->only($logOnly)->toArray();
        }

        return collect($attributes)->except($hidden)->toArray();
    }

    protected static function detectCurrentGuard(): string
    {
        foreach (['teacher', 'student', 'web'] as $guard) {
            if (Auth::guard($guard)->check()) {
                return $guard;
            }
        }
        return 'web';
    }

    protected static function resolveActiveRole($user): ?string
    {
        if (!$user) return null;

        $roles = $user->getRoleNames()->toArray();
        $activeRole = session('active_role', $roles[0] ?? null);

        if ($activeRole && !in_array($activeRole, $roles) && count($roles) > 0) {
            $activeRole = $roles[0];
        }

        return $activeRole;
    }
}
