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

        if (!in_array($activeRole, ['superadmin', 'admin', 'kichik_admin', 'kafedra_mudiri'])) {
            abort(403);
        }

        try {
            if ($activeRole === 'kafedra_mudiri') {
                $applications = ClubMembership::where('department_hemis_id', $user->department_hemis_id)
                    ->orderByDesc('created_at')
                    ->get();
            } else {
                $applications = ClubMembership::orderByDesc('created_at')->get();
            }
        } catch (\Exception $e) {
            $applications = collect();
        }

        return view('admin.club-applications', compact('applications'));
    }

    public function show(ClubMembership $application)
    {
        $this->checkAccess($application);
        return view('admin.club-applications-show', compact('application'));
    }

    public function approve(ClubMembership $application)
    {
        $this->checkAccess($application);
        $application->update(['status' => 'approved', 'reject_reason' => null]);
        return back()->with('success', '\'' . $application->club_name . '\' arizasi tasdiqlandi.');
    }

    public function reject(Request $request, ClubMembership $application)
    {
        $this->checkAccess($application);
        $request->validate(['reject_reason' => 'nullable|string|max:500']);
        $application->update([
            'status' => 'rejected',
            'reject_reason' => $request->reject_reason,
        ]);
        return back()->with('success', '\'' . $application->club_name . '\' arizasi rad etildi.');
    }

    private function checkAccess(ClubMembership $application): void
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
