<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * O'qishdan chetlashgan talabaga (HEMIS holati 60) hech qanday holatda baho
 * qo'yilmaydi — kunlik baho, otrabotka, MT, OSKI/test, sababli ariza bahosi
 * va superadmin tahriri ham. Jurnaldagi baho saqlash yo'llariga qo'yiladi.
 *
 * Talaba so'rovdagi student_hemis_id dan, u bo'lmasa grade_id (student_grades)
 * orqali topiladi.
 */
class BlockExpelledStudentGrade
{
    public const EXPELLED_STATUS = '60';

    public function handle(Request $request, Closure $next)
    {
        $hemisId = $request->input('student_hemis_id');
        if (!$hemisId && $request->filled('grade_id')) {
            $hemisId = DB::table('student_grades')->where('id', (int) $request->input('grade_id'))->value('student_hemis_id');
        }

        if ($hemisId && (string) DB::table('students')->where('hemis_id', $hemisId)->value('student_status_code') === self::EXPELLED_STATUS) {
            $message = "Talaba o'qishdan chetlashgan — unga baho qo'yib bo'lmaydi.";

            return $request->expectsJson() || $request->ajax()
                ? response()->json(['success' => false, 'message' => $message], 403)
                : back()->with('error', $message);
        }

        return $next($request);
    }
}
