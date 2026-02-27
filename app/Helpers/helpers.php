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
     * Dekan foydalanuvchining birinchi fakultetini olish (Department model)
     * Ko'p fakultetli dekanlar uchun get_dekan_faculties() dan foydalaning
     *
     * @return \App\Models\Department|null
     */
    function get_dekan_faculty(): ?Department
    {
        if (!is_active_dekan()) return null;

        $user = auth()->user();

        return $user->deanFaculties()->first();
    }
}

if (!function_exists('get_dekan_faculties')) {
    /**
     * Dekan foydalanuvchining barcha fakultetlarini olish
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    function get_dekan_faculties(): \Illuminate\Database\Eloquent\Collection
    {
        if (!is_active_dekan()) return new \Illuminate\Database\Eloquent\Collection();

        $user = auth()->user();

        return $user->deanFaculties;
    }
}

if (!function_exists('get_dekan_faculty_ids')) {
    /**
     * Dekan foydalanuvchining barcha fakultet id (departments.id) lari
     *
     * @return array
     */
    function get_dekan_faculty_ids(): array
    {
        if (!is_active_dekan()) return [];

        $user = auth()->user();

        return $user->deanFaculties()->pluck('departments.id')->toArray();
    }
}

if (!function_exists('get_dekan_faculty_id')) {
    /**
     * Dekan foydalanuvchining birinchi fakultet ID sini olish
     *
     * @return int|null
     */
    function get_dekan_faculty_id(): ?int
    {
        $faculty = get_dekan_faculty();
        return $faculty?->id;
    }
}

if (!function_exists('is_active_registrator')) {
    /**
     * Joriy foydalanuvchining faol roli registrator_ofisi ekanligini tekshirish
     */
    function is_active_registrator(): bool
    {
        $user = auth()->user();
        if (!$user) return false;

        $roles = $user->getRoleNames()->toArray();
        $activeRole = session('active_role', $roles[0] ?? '');
        if (!in_array($activeRole, $roles) && count($roles) > 0) {
            $activeRole = $roles[0];
        }

        return $activeRole === 'registrator_ofisi';
    }
}

if (!function_exists('is_active_fan_masuli')) {
    /**
     * Joriy foydalanuvchining faol roli fan_masuli ekanligini tekshirish
     */
    function is_active_fan_masuli(): bool
    {
        $user = auth()->user();
        if (!$user) return false;

        $roles = $user->getRoleNames()->toArray();
        $activeRole = session('active_role', $roles[0] ?? '');
        if (!in_array($activeRole, $roles) && count($roles) > 0) {
            $activeRole = $roles[0];
        }

        return $activeRole === 'fan_masuli';
    }
}

if (!function_exists('get_fan_masuli_subject_ids')) {
    /**
     * Fan mas'uli o'qituvchining mas'ul fanlari ID lari
     */
    function get_fan_masuli_subject_ids(): array
    {
        if (!is_active_fan_masuli()) return [];

        $user = auth()->user();

        return $user->responsibleSubjects()->pluck('curriculum_subjects.id')->toArray();
    }
}

if (!function_exists('is_active_oqituvchi')) {
    /**
     * Joriy foydalanuvchi o'qituvchi sifatida ishlayotganini tekshirish.
     * Web guard va teacher guard uchun session('active_role') tekshiriladi.
     *
     * Muhim: Web guard birinchi tekshiriladi, chunki admin foydalanuvchisi
     * bir vaqtda teacher guard sessiyasiga ham ega bo'lishi mumkin
     * (impersonatsiya yoki parallel kirish natijasida).
     */
    function is_active_oqituvchi(): bool
    {
        // Web guard orqali kirgan bo'lsa, faqat active_role ga qarab qaror qilish
        $webUser = auth()->guard('web')->user();
        if ($webUser) {
            $roles = $webUser->getRoleNames()->toArray();
            $activeRole = session('active_role', $roles[0] ?? '');
            if (!in_array($activeRole, $roles) && count($roles) > 0) {
                $activeRole = $roles[0];
            }

            return $activeRole === 'oqituvchi';
        }

        // Teacher guard orqali kirilganmi tekshirish (faqat web guard yo'q bo'lganda)
        if (auth()->guard('teacher')->check()) {
            $teacher = auth()->guard('teacher')->user();
            $roles = $teacher->getRoleNames()->toArray();
            $activeRole = session('active_role', $roles[0] ?? 'oqituvchi');
            if (!in_array($activeRole, $roles) && count($roles) > 0) {
                $activeRole = $roles[0];
            }

            return $activeRole === 'oqituvchi';
        }

        return false;
    }
}

if (!function_exists('get_teacher_hemis_id')) {
    /**
     * Joriy foydalanuvchining HEMIS ID sini olish (Teacher model uchun).
     * Teacher guard va web guard uchun alohida tekshiradi (impersonatsiya uchun ham ishlaydi).
     */
    function get_teacher_hemis_id(): ?int
    {
        // Teacher guard orqali kirgan bo'lsa
        $teacher = auth()->guard('teacher')->user();
        if ($teacher) {
            return $teacher->hemis_id;
        }

        // Web guard orqali kirgan bo'lsa, teachers jadvalidan qidirish
        $user = auth()->guard('web')->user();
        if (!$user) return null;

        $teacher = \App\Models\Teacher::where('login', $user->email)->first();
        return $teacher?->hemis_id;
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
