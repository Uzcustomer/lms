<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\ClubMembership;
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
            'status' => 'pending',
        ]);

        return back()->with('success', 'To\'garakka a\'zo bo\'lish uchun ariza yuborildi!');
    }
}
