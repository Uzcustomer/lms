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

        if ($teacher && $teacher->must_change_password) {
            if (!$request->routeIs('teacher.force-change-password*') && !$request->routeIs('teacher.logout')) {
                return redirect()->route('teacher.force-change-password');
            }
        }

        return $next($request);
    }
}
