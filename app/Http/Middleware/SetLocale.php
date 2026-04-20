<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        // 1. Avval session yoki cookie'dan olishga harakat
        $locale = Session::get('locale');
        if (!$locale) {
            $cookieLocale = $request->cookie('locale');
            if ($cookieLocale && in_array($cookieLocale, ['uz', 'ru', 'en'])) {
                $locale = $cookieLocale;
            }
        }

        // 2. Agar hali til aniqlanmagan bo'lsa
        if (!$locale) {
            // Xalqaro talabalar uchun default inglizcha
            try {
                $student = Auth::guard('student')->user();
                if ($student && (
                    str_starts_with(strtolower($student->group_name ?? ''), 'xd') ||
                    str_contains(strtolower($student->citizenship_name ?? ''), 'orijiy')
                )) {
                    $locale = 'en';
                    // Session ga saqlash — keyingi so'rovlarda qayta tekshirmasligi uchun
                    Session::put('locale', 'en');
                }
            } catch (\Throwable $e) {
                // Auth guard xatosi bo'lsa e'tiborsiz qoldirish
            }

            if (!$locale) {
                $locale = 'uz';
            }
        }

        if (in_array($locale, ['uz', 'ru', 'en'])) {
            App::setLocale($locale);
        }

        return $next($request);
    }
}
