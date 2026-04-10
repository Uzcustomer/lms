<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class StaffEvaluationController extends Controller
{
    public function index()
    {
        $hasTable = Schema::hasTable('staff_evaluations');

        $query = User::orderBy('name');

        if ($hasTable) {
            $query->withCount('staffEvaluations')
                  ->withAvg('staffEvaluations', 'rating');
        }

        $users = $query->get();

        return view('admin.staff-evaluation.index', compact('users', 'hasTable'));
    }

    public function show(User $user)
    {
        if (!Schema::hasTable('staff_evaluations')) {
            return back()->with('error', 'staff_evaluations jadvali topilmadi. Migration ishlatilmagan.');
        }

        $evaluations = $user->staffEvaluations()
            ->with('student:id,full_name,short_name')
            ->latest()
            ->paginate(20);

        $avgRating = $user->staffEvaluations()->avg('rating');
        $totalCount = $user->staffEvaluations()->count();
        $ratingDistribution = [];
        for ($i = 5; $i >= 1; $i--) {
            $ratingDistribution[$i] = $user->staffEvaluations()->where('rating', $i)->count();
        }

        return view('admin.staff-evaluation.show', compact(
            'user', 'evaluations', 'avgRating', 'totalCount', 'ratingDistribution'
        ));
    }

    public function generateQr(User $user)
    {
        if (!$user->eval_qr_token) {
            $user->update(['eval_qr_token' => Str::random(32)]);
        }

        return back()->with('success', "{$user->name} uchun QR kod yaratildi.");
    }

    public function generateAllQr()
    {
        $users = User::whereNull('eval_qr_token')->get();
        foreach ($users as $user) {
            $user->update(['eval_qr_token' => Str::random(32)]);
        }

        return back()->with('success', $users->count() . " ta xodim uchun QR kod yaratildi.");
    }

    public function downloadQr(User $user)
    {
        if (!$user->eval_qr_token) {
            $user->update(['eval_qr_token' => Str::random(32)]);
        }

        $url = route('staff-evaluate.form', $user->eval_qr_token);

        $qrSvg = QrCode::size(400)->margin(2)->generate($url);

        return response($qrSvg)
            ->header('Content-Type', 'image/svg+xml')
            ->header('Content-Disposition', 'attachment; filename="qr-' . Str::slug($user->name) . '.svg"');
    }
}
