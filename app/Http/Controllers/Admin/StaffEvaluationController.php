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

        // SVG markaziga "RG" yozuvini qo'shish
        $rgOverlay = '<rect x="155" y="165" width="90" height="70" rx="10" fill="white"/>'
            . '<rect x="162" y="172" width="76" height="56" rx="8" fill="#2563EB"/>'
            . '<text x="200" y="210" text-anchor="middle" font-family="Arial,sans-serif" font-weight="bold" font-size="34" fill="white">RG</text>';
        $qrSvg = str_replace('</svg>', $rgOverlay . '</svg>', $qrSvg);

        return response($qrSvg)
            ->header('Content-Type', 'image/svg+xml')
            ->header('Content-Disposition', 'attachment; filename="qr-' . Str::slug($teacher->full_name) . '.svg"');
    }
}
