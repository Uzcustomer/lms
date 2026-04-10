<?php

namespace App\Http\Controllers\Admin;

use App\Exports\StaffEvaluationExport;
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

        if ($request->input('tab') === 'qr') {
            $query->whereNotNull('eval_qr_token');
        }

        $teachers = $query->orderBy('full_name')->paginate(20)->withQueryString();

        return view('admin.staff-evaluation.index', compact('teachers'));
    }

    public function show(Request $request, Teacher $teacher)
    {
        $query = $teacher->staffEvaluations()
            ->with('student:id,full_name,short_name')
            ->latest();

        if ($request->filled('rating')) {
            $query->where('rating', $request->rating);
        }

        $evaluations = $query->paginate(20)->withQueryString();

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

        return redirect()->route('admin.staff-evaluation.index', array_merge(
            request()->only('search'),
            ['tab' => 'qr']
        ))->with('success', "{$teacher->full_name} uchun QR kod yaratildi.");
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

        return redirect()->route('admin.staff-evaluation.index', array_merge(
            request()->only('search'),
            ['tab' => 'qr']
        ))->with('success', "{$count} ta xodim uchun QR kod yaratildi.");
    }

    public function deleteAllQr()
    {
        $teachers = Teacher::whereNotNull('eval_qr_token')->get();
        $count = $teachers->count();

        foreach ($teachers as $teacher) {
            $teacher->staffEvaluations()->delete();
            $teacher->update(['eval_qr_token' => null]);
        }

        return redirect()->route('admin.staff-evaluation.index', ['tab' => 'list'])
            ->with('success', "{$count} ta xodimning QR kodi va baholari o'chirildi.");
    }

    public function deleteQr(Teacher $teacher)
    {
        $teacher->staffEvaluations()->delete();
        $teacher->update(['eval_qr_token' => null]);

        return redirect()->route('admin.staff-evaluation.show', $teacher)
            ->with('success', "QR kod va barcha baholar o'chirildi.");
    }

    public function regenerateQr(Teacher $teacher)
    {
        $teacher->staffEvaluations()->delete();
        $teacher->update(['eval_qr_token' => Str::random(32)]);

        return redirect()->route('admin.staff-evaluation.show', $teacher)
            ->with('success', "QR kod qayta yaratildi, eski baholar o'chirildi.");
    }

    public function exportExcel(Request $request, Teacher $teacher)
    {
        $rating = $request->input('rating');
        $filename = 'baholar-' . Str::slug($teacher->full_name) . '.xlsx';

        return (new StaffEvaluationExport($teacher->id, $rating))->download($filename);
    }

    public function downloadQr(Teacher $teacher)
    {
        if (!$teacher->eval_qr_token) {
            $teacher->update(['eval_qr_token' => Str::random(32)]);
        }

        $url = route('staff-evaluate.form', $teacher->eval_qr_token);

        $qrSvg = QrCode::size(400)->errorCorrection('H')->margin(2)->generate($url);

        // SVG markaziga logo qo'shish
        $logoPath = public_path('logo.png');
        if (file_exists($logoPath)) {
            $logoBase64 = base64_encode(file_get_contents($logoPath));
            $logoOverlay = '<circle cx="200" cy="200" r="56" fill="white"/>'
                . '<image x="150" y="150" width="100" height="100" href="data:image/png;base64,' . $logoBase64 . '" clip-path="circle(50px at 50px 50px)"/>';
            $qrSvg = str_replace('</svg>', $logoOverlay . '</svg>', $qrSvg);
        }

        return response($qrSvg)
            ->header('Content-Type', 'image/svg+xml')
            ->header('Content-Disposition', 'attachment; filename="qr-' . Str::slug($teacher->full_name) . '.svg"');
    }
}
