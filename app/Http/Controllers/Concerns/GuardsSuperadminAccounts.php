<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\ProjectRole;
use Illuminate\Support\Facades\Auth;

/**
 * Superadmin hisoblarini superadmin bo'lmaganlardan himoya qilish.
 *
 * Route darajasida rol boshqaruvchilari (superadmin, admin, kichik_admin)
 * cheklangan. Bu trait esa ular ichida ham faqat superadmin:
 *  - kimgadir superadmin rolini bera oladi yoki undan olib qo'ya oladi;
 *  - superadmin hisobining login/parolini o'zgartira oladi yoki o'chira oladi.
 * Aks holda admin yoki kichik_admin o'zini superadmin qilib olishi mumkin edi.
 */
trait GuardsSuperadminAccounts
{
    protected function actorIsSuperadmin(): bool
    {
        $actor = Auth::user();

        return $actor !== null
            && method_exists($actor, 'hasRole')
            && $actor->hasRole(ProjectRole::SUPERADMIN->value);
    }

    /**
     * Superadmin roli qo'shilayotgan yoki olib tashlanayotgan bo'lsa, faqat superadmin ruxsat oladi.
     */
    protected function ensureCanChangeSuperadminRole(array $newRoles, array $oldRoles): void
    {
        $role = ProjectRole::SUPERADMIN->value;
        $changes = in_array($role, $newRoles, true) !== in_array($role, $oldRoles, true);

        if ($changes && !$this->actorIsSuperadmin()) {
            abort(403, 'Superadmin rolini faqat superadmin bera oladi yoki olib qo\'ya oladi.');
        }
    }

    /**
     * Superadmin hisobini (login, parol, o'chirish) faqat superadmin boshqaradi.
     */
    protected function ensureCanManageAccount($account): void
    {
        $isSuperadminAccount = method_exists($account, 'hasRole')
            && $account->hasRole(ProjectRole::SUPERADMIN->value);

        if ($isSuperadminAccount && !$this->actorIsSuperadmin()) {
            abort(403, 'Superadmin hisobini faqat superadmin o\'zgartira oladi.');
        }
    }
}
