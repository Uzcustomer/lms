<?php

namespace App\Policies;

use App\Models\RetakeGroup;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Contracts\Auth\Authenticatable;

class RetakeGroupPolicy
{
    use HandlesAuthorization;

    public function viewAny(Authenticatable $user): bool
    {
        if (! method_exists($user, 'hasRole')) {
            return false;
        }
        return $user->hasRole([
            'dekan', 'registrator_ofisi', 'oquv_bolimi', 'oquv_bolimi_boshligi',
            'oqituvchi', 'admin', 'superadmin',
        ]);
    }

    public function view(Authenticatable $user, RetakeGroup $group): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Guruh yaratish — faqat o'quv bo'limi.
     */
    public function create(Authenticatable $user): bool
    {
        if (! method_exists($user, 'hasRole')) {
            return false;
        }
        return $user->hasRole(['oquv_bolimi', 'oquv_bolimi_boshligi', 'admin', 'superadmin']);
    }

    /**
     * Guruh yangilash — o'quv bo'limi (lekin Service ichida boshlanish sanasi
     * tekshiruvi mavjud).
     */
    public function update(Authenticatable $user, RetakeGroup $group): bool
    {
        return $this->create($user);
    }

    public function delete(Authenticatable $user, RetakeGroup $group): bool
    {
        if (! method_exists($user, 'hasRole')) {
            return false;
        }
        return $user->hasRole('superadmin');
    }
}
