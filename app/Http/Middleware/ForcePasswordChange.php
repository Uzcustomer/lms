<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ForcePasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        $teacher = Auth::guard('teacher')->user();

        if (!$teacher) {
            return $next($request);
        }

        $allowedRoutes = [
            'teacher.force-change-password*',
            'teacher.complete-profile*',
            'teacher.verify-telegram*',
            'teacher.logout',
        ];

        $isAllowed = false;
        foreach ($allowedRoutes as $route) {
            if ($request->routeIs($route)) {
                $isAllowed = true;
                break;
            }
        }

        if (!$isAllowed) {
            if ($teacher->must_change_password) {
                return redirect()->route('teacher.force-change-password');
            }

            if (!$teacher->isProfileComplete()) {
                return redirect()->route('teacher.complete-profile');
            }
        }

        return $next($request);
    }
}
