<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Admin\StudentSurveyController;
use App\Models\StudentSurveyCompletion;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Talaba majburiy so'rovnomani bajarmagan va deadline o'tgan bo'lsa,
 * boshqa talaba sahifalariga kira olmaydi — survey sahifasiga yo'naltiriladi.
 *
 * Deadline o'tmaguncha to'siq qo'yilmaydi (faqat layout'da banner/popup ko'rsatiladi).
 */
class EnsureSurveyCompleted
{
    public function handle(Request $request, Closure $next): Response
    {
        $student = Auth::guard('student')->user();
        if (!$student) {
            return $next($request);
        }

        // Survey va logout sahifalari har doim ochiq
        if ($request->routeIs('student.survey.*') || $request->routeIs('student.logout')) {
            return $next($request);
        }

        // Survey o'chirilgan bo'lsa hech narsa qilmaymiz
        if (!StudentSurveyController::isActive()) {
            return $next($request);
        }

        $config = config('student_survey');
        if (!$config || empty($config['key'])) {
            return $next($request);
        }
        $deadline = StudentSurveyController::currentDeadline();
        if (!$deadline) return $next($request);

        $deadlinePassed = strtotime($deadline) < time();
        if (!$deadlinePassed) {
            return $next($request);
        }

        $completed = StudentSurveyCompletion::where('survey_key', $config['key'])
            ->where('student_hemis_id', $student->hemis_id)
            ->exists();

        if ($completed) {
            return $next($request);
        }

        return redirect()->route('student.survey.show');
    }
}
