<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AdminMultiGuardAuth
{
    /**
     * Admin sahifalariga teacher va web guardlardan kirish.
     * Teacher guard bilan registrator_ofisi roli bo'lsa, teacher guard ishlatiladi.
     * Aks holda web guard (oddiy admin) ishlatiladi.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Registrator_ofisi va dekan teacher uchun teacher guard afzal
        $teacher = Auth::guard('teacher')->user();
        if ($teacher && ($teacher->hasRole('registrator_ofisi') || $teacher->hasRole('dekan'))) {
            Auth::shouldUse('teacher');
            return $next($request);
        }

        // Oddiy admin uchun web guard
        if (Auth::guard('web')->check()) {
            Auth::shouldUse('web');
            return $next($request);
        }

        // Autentifikatsiya yo'q - login sahifasiga
        return redirect()->route('admin.login');
    }
}
