<?php

namespace App\Http\Controllers\Public;

use App\Enums\RetakeAcademicDeptStatus;
use App\Http\Controllers\Controller;
use App\Models\RetakeApplication;
use Illuminate\View\View;

/**
 * Public verifikatsiya sahifasi.
 *
 * GET /verify/{verification_code}
 *  - Auth talab qilmaydi
 *  - Rate limited (daqiqada 30 IP bo'yicha)
 *  - Sezgir ma'lumotlar yashirin (kvitansiya, izoh, dekan F.I.Sh., audit log)
 *
 * Faqat approved holatdagi arizalar verifikatsiya qaytaradi.
 */
class RetakeVerifyController extends Controller
{
    public function show(string $code): View
    {
        $application = RetakeApplication::query()
            ->where('verification_code', $code)
            ->with(['student:id,hemis_id,full_name,group_name,department_name,specialty_name', 'retakeGroup.teacher'])
            ->first();

        // Topilmagan yoki approved emas — qizil sahifa
        if ($application === null
            || $application->academic_dept_status !== RetakeAcademicDeptStatus::APPROVED) {
            return view('public.retake-verify', [
                'found' => false,
                'application' => null,
            ]);
        }

        return view('public.retake-verify', [
            'found' => true,
            'application' => $application,
            'student' => $application->student,
            'group' => $application->retakeGroup,
            'teacher' => $application->retakeGroup?->teacher,
        ]);
    }
}
