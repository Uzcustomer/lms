<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClubMembership;
use Illuminate\Http\Request;

class ClubApplicationController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $activeRole = session('active_role', '');

        if (in_array($activeRole, ['superadmin', 'admin', 'kichik_admin'])) {
            $applications = ClubMembership::orderByDesc('created_at')->get();
        } elseif ($activeRole === 'kafedra_mudiri') {
            $applications = ClubMembership::where('department_hemis_id', $user->department_hemis_id)
                ->orderByDesc('created_at')
                ->get();
        } else {
            abort(403);
        }

        return view('admin.club-applications', compact('applications'));
    }

    public function approve(ClubMembership $application)
    {
        $this->authorize($application);
        $application->update(['status' => 'approved', 'reject_reason' => null]);
        return back()->with('success', '\'' . $application->club_name . '\' arizasi tasdiqlandi.');
    }

    public function reject(Request $request, ClubMembership $application)
    {
        $this->authorize($application);
        $request->validate(['reject_reason' => 'nullable|string|max:500']);
        $application->update([
            'status' => 'rejected',
            'reject_reason' => $request->reject_reason,
        ]);
        return back()->with('success', '\'' . $application->club_name . '\' arizasi rad etildi.');
    }

    private function authorize(ClubMembership $application): void
    {
        $user = auth()->user();
        $activeRole = session('active_role', '');

        if (in_array($activeRole, ['superadmin', 'admin', 'kichik_admin'])) {
            return;
        }

        if ($activeRole === 'kafedra_mudiri' && $application->department_hemis_id == $user->department_hemis_id) {
            return;
        }

        abort(403);
    }
}
