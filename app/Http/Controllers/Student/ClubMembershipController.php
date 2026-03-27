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
            $department = Department::where('active', true)
                ->where('name', 'LIKE', '%kafedra%')
                ->get()
                ->first(function ($dept) use ($request) {
                    // Kafedra section title ichidan department nomini izlaymiz
                    return mb_stripos($request->kafedra_name, mb_substr($dept->name, 0, 20)) !== false
                        || mb_stripos($dept->name, 'kafedra') !== false
                            && $this->kafedraMatch($request->kafedra_name, $dept->name);
                });

            $departmentHemisId = $department?->department_hemis_id;
        }

        ClubMembership::create([
            'student_id' => $student->id,
            'student_hemis_id' => $student->hemis_id,
            'student_name' => $student->full_name,
            'group_name' => $student->group_name,
            'club_name' => $request->club_name,
            'club_place' => $request->club_place,
            'club_day' => $request->club_day,
            'club_time' => $request->club_time,
            'kafedra_name' => $request->kafedra_name,
            'department_hemis_id' => $departmentHemisId,
            'status' => 'pending',
        ]);

        return back()->with('success', 'To\'garakka a\'zo bo\'lish uchun ariza yuborildi!');
    }

    private function kafedraMatch(string $sectionTitle, string $deptName): bool
    {
        // Department nomidan asosiy so'zlarni olamiz (kafedra so'zini olib tashlab)
        $deptClean = mb_strtolower(preg_replace('/\s*kafedra\s*/ui', ' ', $deptName));
        $keywords = array_filter(explode(' ', trim($deptClean)), fn($w) => mb_strlen($w) > 3);

        if (empty($keywords)) return false;

        $titleLower = mb_strtolower($sectionTitle);
        $matched = 0;
        foreach ($keywords as $kw) {
            if (mb_stripos($titleLower, $kw) !== false) {
                $matched++;
            }
        }

        // Kamida 60% so'zlar mos kelsa
        return $matched >= count($keywords) * 0.6;
    }
}
