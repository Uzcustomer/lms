<?php

use App\Models\Department;
use Carbon\Carbon;

if (!function_exists('is_active_dekan')) {
    /**
     * Joriy foydalanuvchining faol roli dekan ekanligini tekshirish
     */
    function is_active_dekan(): bool
    {
        $user = auth()->user();
        if (!$user) return false;

        $roles = $user->getRoleNames()->toArray();
        $activeRole = session('active_role', $roles[0] ?? '');
        if (!in_array($activeRole, $roles) && count($roles) > 0) {
            $activeRole = $roles[0];
        }

        return $activeRole === 'dekan';
    }
}

if (!function_exists('get_dekan_faculty')) {
    /**
     * Dekan foydalanuvchining fakultetini olish (Department model)
     * Faqat teacher guard orqali kirgan dekanlar uchun ishlaydi
     *
     * @return \App\Models\Department|null
     */
    function get_dekan_faculty(): ?Department
    {
        if (!is_active_dekan()) return null;

        $user = auth()->user();
        $departmentHemisId = $user->department_hemis_id ?? null;

        if (!$departmentHemisId) return null;

        return Department::where('department_hemis_id', $departmentHemisId)
            ->where('structure_type_code', 11)
            ->first();
    }
}

if (!function_exists('get_dekan_faculty_id')) {
    /**
     * Dekan foydalanuvchining fakultet ID sini olish
     *
     * @return int|null
     */
    function get_dekan_faculty_id(): ?int
    {
        $faculty = get_dekan_faculty();
        return $faculty?->id;
    }
}

if (!function_exists('format_date')) {
    /**
     * Sanani dd.mm.yyyy formatda chiqarish
     *
     * @param  mixed  $date  Carbon, DateTime, string yoki null
     * @param  string|null  $default  bo'sh bo'lsa qaytadigan qiymat
     * @return string
     *
     * Ishlatish:
     *   format_date($model->created_at)          → '08.02.2026'
     *   format_date('2026-02-08')                 → '08.02.2026'
     *   format_date(null, '-')                    → '-'
     */
    function format_date($date, string $default = '-'): string
    {
        if (empty($date)) {
            return $default;
        }

        if (is_string($date)) {
            try {
                $date = Carbon::parse($date);
            } catch (\Exception $e) {
                return $default;
            }
        }

        return $date->format(config('app.date_format', 'd.m.Y'));
    }
}

if (!function_exists('format_datetime')) {
    /**
     * Sana va vaqtni dd.mm.yyyy HH:ii formatda chiqarish
     *
     * @param  mixed  $date  Carbon, DateTime, string yoki null
     * @param  bool  $withSeconds  sekundlarni ko'rsatish (dd.mm.yyyy HH:ii:ss)
     * @param  string|null  $default  bo'sh bo'lsa qaytadigan qiymat
     * @return string
     *
     * Ishlatish:
     *   format_datetime($model->created_at)              → '08.02.2026 14:30'
     *   format_datetime($model->created_at, true)        → '08.02.2026 14:30:45'
     *   format_datetime(null)                             → '-'
     */
    function format_datetime($date, bool $withSeconds = false, string $default = '-'): string
    {
        if (empty($date)) {
            return $default;
        }

        if (is_string($date)) {
            try {
                $date = Carbon::parse($date);
            } catch (\Exception $e) {
                return $default;
            }
        }

        $format = $withSeconds
            ? config('app.datetime_full_format', 'd.m.Y H:i:s')
            : config('app.datetime_format', 'd.m.Y H:i');

        return $date->format($format);
    }
}
