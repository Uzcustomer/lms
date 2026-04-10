<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Teacher;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class StaffEvaluationController extends Controller
{
    public function index(Request $request)
    {
        $query = Teacher::withCount('staffEvaluations')
            ->withAvg('staffEvaluations', 'rating')
            ->where('is_active', true);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('short_name', 'like', "%{$search}%")
                  ->orWhere('department', 'like', "%{$search}%");
            });
        }

        $teachers = $query->orderBy('full_name')->paginate(20)->withQueryString();

        return view('admin.staff-evaluation.index', compact('teachers'));
    }

    public function show(Teacher $teacher)
    {
        $evaluations = $teacher->staffEvaluations()
            ->with('student:id,full_name,short_name')
            ->latest()
            ->paginate(20);

        $avgRating = $teacher->staffEvaluations()->avg('rating');
        $totalCount = $teacher->staffEvaluations()->count();
        $ratingDistribution = [];
        for ($i = 5; $i >= 1; $i--) {
            $ratingDistribution[$i] = $teacher->staffEvaluations()->where('rating', $i)->count();
        }

        return view('admin.staff-evaluation.show', compact(
            'teacher', 'evaluations', 'avgRating', 'totalCount', 'ratingDistribution'
        ));
    }

    public function generateQr(Teacher $teacher)
    {
        if (!$teacher->eval_qr_token) {
            $teacher->update(['eval_qr_token' => Str::random(32)]);
        }

        return back()->with('success', "{$teacher->full_name} uchun QR kod yaratildi.");
    }

    public function generateAllQr()
    {
        $count = 0;
        Teacher::where('is_active', true)->whereNull('eval_qr_token')->chunkById(100, function ($teachers) use (&$count) {
            foreach ($teachers as $teacher) {
                $teacher->update(['eval_qr_token' => Str::random(32)]);
                $count++;
            }
        });

        return back()->with('success', "{$count} ta xodim uchun QR kod yaratildi.");
    }

    public function downloadQr(Teacher $teacher)
    {
        if (!$teacher->eval_qr_token) {
            $teacher->update(['eval_qr_token' => Str::random(32)]);
        }

        $url = route('staff-evaluate.form', $teacher->eval_qr_token);

        $qrSvg = QrCode::size(400)->margin(2)->generate($url);

        return response($qrSvg)
            ->header('Content-Type', 'image/svg+xml')
            ->header('Content-Disposition', 'attachment; filename="qr-' . Str::slug($teacher->full_name) . '.svg"');
    }
}
