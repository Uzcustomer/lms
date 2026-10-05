<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClubMembership;
use Illuminate\Http\Request;

class ClubApplicationController extends Controller
{
    public function index()
    {
        return view('admin.club-applications', ['applications' => $this->visibleApplications()]);
    }

    /**
     * Sahifadagi arizalarni Excelga: to'garaklar kesimida yig'ma va barcha
     * arizalar. Rol cheklovi index bilan bir xil — kafedra mudiri faqat o'z
     * kafedrasini oladi.
     */
    public function export()
    {
        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\ClubApplicationsExport($this->visibleApplications()),
            'togarak_arizalari_'.now('Asia/Tashkent')->format('Y-m-d').'.xlsx'
        );
    }

    /** Joriy rol ko'ra oladigan arizalar (index va export uchun bir xil) */
    private function visibleApplications()
    {
        $user = auth()->user();
        $activeRole = session('active_role', '');

        if (!in_array($activeRole, ['superadmin', 'admin', 'kichik_admin', 'kafedra_mudiri', 'registrator_ofisi'])) {
            abort(403);
        }

        try {
            if ($activeRole === 'kafedra_mudiri') {
                return ClubMembership::where('department_hemis_id', $user->department_hemis_id)
                    ->orderByDesc('created_at')
                    ->get();
            }

            return ClubMembership::orderByDesc('created_at')->get();
        } catch (\Exception $e) {
            return collect();
        }
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
