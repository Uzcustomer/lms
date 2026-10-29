<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Talabaga yuborilgan havolani (masalan, qayta topshirish broni) mehmon ochsa —
 * umumiy /login emas, bosh sahifadagi login oynasiga (Talaba / Xodim) yuboriladi.
 * Login'dan keyin (parol, HEMIS, Face ID) url.intended orqali aynan shu sahifaga qaytadi.
 *
 * auth:student o'rniga ishlatiladi: u bilan birga qo'yilsa, Laravel auth ni
 * oldinroq ishga tushiradi va mehmon baribir umumiy /login ga tushadi.
 */
class RedirectGuestStudentToLogin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::guard('student')->check()) {
            return redirect()->guest(route('welcome'));
        }

        // auth:student kabi — Auth::user() / auth()->user() talabani qaytarsin
        Auth::shouldUse('student');

        return $next($request);
    }
}
