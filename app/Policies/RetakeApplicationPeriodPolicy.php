<?php

namespace App\Policies;

use App\Models\RetakeApplicationPeriod;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Contracts\Auth\Authenticatable;

class RetakeApplicationPeriodPolicy
{
    use HandlesAuthorization;

    /**
     * Qabul oynalari ro'yxatini ko'rish — har rol uchun ochiq.
     */
    public function viewAny(Authenticatable $user): bool
    {
        return true;
    }

    public function view(Authenticatable $user, RetakeApplicationPeriod $period): bool
    {
        return true;
    }

    /**
     * Yangi qabul oynasi yaratish — faqat o'quv bo'limi.
     */
    public function create(Authenticatable $user): bool
    {
        if (! method_exists($user, 'hasRole')) {
            return false;
        }
        return $user->hasRole(['oquv_bolimi', 'oquv_bolimi_boshligi', 'admin', 'superadmin']);
    }

    /**
     * Sanalarni o'zgartirish — adolatlilik qoidasi: faqat super-admin override.
     * O'quv bo'limi ham yaratilgach o'zgartira olmaydi.
     */
    public function update(Authenticatable $user, RetakeApplicationPeriod $period): bool
    {
        if (! method_exists($user, 'hasRole')) {
            return false;
        }
        return $user->hasRole('superadmin');
    }

    /**
     * O'chirish — yo'q. Mavjud arizalar tegishli bo'lishi mumkin.
     */
    public function delete(Authenticatable $user, RetakeApplicationPeriod $period): bool
    {
        return false;
    }
}
