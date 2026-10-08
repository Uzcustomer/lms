<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StudentComplaint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Registrator ofisi: xalqaro talabalar shikoyatlari.
 * Xodim muammo bilan shug'ullanadi va "Hal etildi" deb belgilaydi yoki
 * shikoyatni rasmlari bilan butunlay o'chiradi.
 */
class StudentComplaintController extends Controller
{
    public function index(Request $request)
    {
        $emptyStats = ['total' => 0, 'new' => 0, 'resolved' => 0];

        if (!Schema::hasTable('student_complaints')) {
            return view('admin.student-complaints.index', [
                'complaints' => new \Illuminate\Pagination\LengthAwarePaginator([], 0, 20),
                'stats' => $emptyStats,
                'migrationPending' => true,
            ]);
        }

        $status = in_array($request->get('status'), ['new', 'resolved'], true) ? $request->get('status') : null;
        $search = trim((string) $request->get('search'));

        $complaints = StudentComplaint::query()
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($search !== '', fn ($q) => $q->where(function ($inner) use ($search) {
                $inner->where('student_name', 'like', "%{$search}%")
                    ->orWhere('student_id_number', 'like', "%{$search}%")
                    ->orWhere('group_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            }))
            // Yangilari tepada
            ->orderByRaw("CASE WHEN status = 'new' THEN 0 ELSE 1 END")
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $new = StudentComplaint::where('status', StudentComplaint::STATUS_NEW)->count();
        $resolved = StudentComplaint::where('status', StudentComplaint::STATUS_RESOLVED)->count();

        return view('admin.student-complaints.index', [
            'complaints' => $complaints,
            'stats' => ['total' => $new + $resolved, 'new' => $new, 'resolved' => $resolved],
            'migrationPending' => false,
        ]);
    }

    public function resolve(StudentComplaint $complaint)
    {
        $user = Auth::guard('web')->user() ?? Auth::guard('teacher')->user();

        $complaint->update([
            'status' => StudentComplaint::STATUS_RESOLVED,
            'resolved_at' => now(),
            'resolved_by_name' => $user?->name ?? $user?->full_name ?? $user?->short_name,
        ]);

        return back()->with('success', "Shikoyat #{$complaint->id} hal etildi deb belgilandi.");
    }

    /** Shikoyat rasmlari bilan butunlay o'chiriladi. */
    public function destroy(StudentComplaint $complaint)
    {
        Storage::disk(StudentComplaint::DISK)->deleteDirectory('student-complaints/' . $complaint->id);
        foreach ($complaint->images ?? [] as $path) {
            Storage::disk(StudentComplaint::DISK)->delete($path);
        }

        $id = $complaint->id;
        $complaint->delete();

        return back()->with('success', "Shikoyat #{$id} rasmlari bilan o'chirildi.");
    }

    public function image(StudentComplaint $complaint, int $index)
    {
        $path = ($complaint->images ?? [])[$index] ?? null;
        abort_unless($path && Storage::disk(StudentComplaint::DISK)->exists($path), 404);

        return Storage::disk(StudentComplaint::DISK)->response($path);
    }
}
