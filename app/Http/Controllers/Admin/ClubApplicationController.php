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

        if (!in_array($activeRole, ['superadmin', 'admin', 'kichik_admin', 'kafedra_mudiri', 'registrator_ofisi'])) {
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

    public function bulkApprove(Request $request)
    {
        $ids = $this->validateBulkIds($request);
        $applications = ClubMembership::whereIn('id', $ids)->where('status', 'pending')->get();
        $count = 0;
        foreach ($applications as $application) {
            $this->checkAccess($application);
            $application->update(['status' => 'approved', 'reject_reason' => null]);
            $count++;
        }
        return back()->with('success', $count . ' ta ariza tasdiqlandi.');
    }

    public function bulkReject(Request $request)
    {
        $request->validate([
            'reject_reason' => 'nullable|string|max:500',
        ]);
        $ids = $this->validateBulkIds($request);
        $reason = $request->input('reject_reason');
        $applications = ClubMembership::whereIn('id', $ids)->where('status', 'pending')->get();
        $count = 0;
        foreach ($applications as $application) {
            $this->checkAccess($application);
            $application->update(['status' => 'rejected', 'reject_reason' => $reason]);
            $count++;
        }
        return back()->with('success', $count . ' ta ariza rad etildi.');
    }

    private function validateBulkIds(Request $request): array
    {
        $request->validate([
            'ids'   => 'required|array|min:1',
            'ids.*' => 'integer',
        ]);
        return $request->input('ids');
    }

    public function destroy(ClubMembership $application)
    {
        $activeRole = session('active_role', '');
        if (!in_array($activeRole, ['superadmin', 'admin'])) {
            abort(403);
        }

        $name = $application->club_name;
        $application->delete();

        return back()->with('success', '\'' . $name . '\' arizasi o\'chirildi.');
    }

    private function checkAccess(ClubMembership $application): void
    {
        $user = auth()->user();
        $activeRole = session('active_role', '');

        if (in_array($activeRole, ['superadmin', 'admin', 'kichik_admin', 'registrator_ofisi'])) {
            return;
        }

        if ($activeRole === 'kafedra_mudiri' && $application->department_hemis_id == $user->department_hemis_id) {
            return;
        }

        abort(403);
    }
}
