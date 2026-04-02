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
        $locale = Session::get('locale', $request->cookie('locale'));

        // Agar til tanlanmagan bo'lsa, xalqaro talabalar uchun default inglizcha
        if (!$locale) {
            $student = Auth::guard('student')->user();
            if ($student && (
                str_starts_with(strtolower($student->group_name ?? ''), 'xd') ||
                str_contains(strtolower($student->citizenship_name ?? ''), 'orijiy')
            )) {
                $locale = 'en';
            } else {
                $locale = 'uz';
            }
        }

        if (in_array($locale, ['uz', 'ru', 'en'])) {
            App::setLocale($locale);
        }

        return $next($request);
    }
}
