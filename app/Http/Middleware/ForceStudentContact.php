<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ForceStudentContact
{
    public function handle(Request $request, Closure $next): Response
    {
        $student = Auth::guard('student')->user();

        if (!$student) {
            return $next($request);
        }

        $allowedRoutes = [
            'student.complete-profile*',
            'student.verify-telegram*',
            'student.password.*',
            'student.logout',
        ];

        $isAllowed = false;
        foreach ($allowedRoutes as $route) {
            if ($request->routeIs($route)) {
                $isAllowed = true;
                break;
            }
        }

        if (!$isAllowed) {
            // 1. Telefon raqami majburiy
            if (!$student->isProfileComplete()) {
                return redirect()->route('student.complete-profile');
            }

            // 2. Telegram tasdiqlash — muhlat o'tgandan keyin block
            if ($student->isTelegramDeadlinePassed()) {
                return redirect()->route('student.complete-profile');
            }
        }

        return $next($request);
    }
}
