<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\ClubMembership;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ClubMembershipController extends Controller
{
    public function index()
    {
        $student = Auth::guard('student')->user();
        $myMemberships = ClubMembership::where('student_id', $student->id)
            ->orderByDesc('created_at')
            ->get();

        return view('student.clubs', compact('myMemberships'));
    }

    public function myClubs()
    {
        $student = Auth::guard('student')->user();
        $myMemberships = ClubMembership::where('student_id', $student->id)
            ->orderByDesc('created_at')
            ->get();

        return view('student.my-clubs', compact('myMemberships'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'club_name' => 'required|string|max:255',
            'club_place' => 'nullable|string|max:255',
            'club_day' => 'nullable|string|max:255',
            'club_time' => 'nullable|string|max:255',
            'kafedra_name' => 'nullable|string|max:500',
        ]);

        $student = Auth::guard('student')->user();

        $existing = ClubMembership::where('student_id', $student->id)
            ->where('club_name', $request->club_name)
            ->first();

        if ($existing) {
            return back()->with('error', 'Siz bu to\'garakka allaqachon ariza yuborgansiz.');
        }

        // Kafedra nomidan department_hemis_id ni topish
        $departmentHemisId = null;
        if ($request->kafedra_name) {
            $kafedraTitle = mb_strtolower($request->kafedra_name);
            $department = Department::where('active', true)
                ->where('name', 'LIKE', '%kafedra%')
                ->get()
                ->first(function ($dept) use ($kafedraTitle) {
                    $deptName = mb_strtolower($dept->name);
                    // Kafedra nomidan "kafedra" so'zini olib tashlab asosiy qismini tekshiramiz
                    $deptCore = trim(preg_replace('/\s*kafedras?i?\s*/ui', ' ', $deptName));
                    $words = array_filter(explode(' ', $deptCore), fn($w) => mb_strlen($w) > 3);
                    if (empty($words)) return false;
                    $matched = 0;
                    foreach ($words as $w) {
                        if (mb_stripos($kafedraTitle, $w) !== false) $matched++;
                    }
                    return $matched >= count($words) * 0.5;
                });

            $departmentHemisId = $department?->department_hemis_id;
        }

        $data = [
            'student_id' => $student->id,
            'student_hemis_id' => $student->hemis_id,
            'student_name' => $student->full_name,
            'group_name' => $student->group_name,
            'club_name' => $request->club_name,
            'club_place' => $request->club_place,
            'club_day' => $request->club_day,
            'club_time' => $request->club_time,
            'kafedra_name' => $request->kafedra_name,
            'status' => 'pending',
        ];

        if (\Schema::hasColumn('club_memberships', 'department_hemis_id')) {
            $data['department_hemis_id'] = $departmentHemisId;
        }

        ClubMembership::create($data);

        return back()->with('success', 'To\'garakka a\'zo bo\'lish uchun ariza yuborildi!');
    }

}
