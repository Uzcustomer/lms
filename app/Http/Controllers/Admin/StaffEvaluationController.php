<?php

namespace App\Http\Controllers\Admin;

use App\Exports\StaffEvaluationExport;
use App\Http\Controllers\Controller;
use App\Models\Setting;
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

        if (in_array($request->input('tab'), ['qr', 'shablon'])) {
            $query->whereNotNull('eval_qr_token');
        }

        $teachers = $query->orderBy('full_name')->paginate(20)->withQueryString();

        $template = $this->getTemplateData();

        return view('admin.staff-evaluation.index', compact('teachers', 'template'));
    }

    public function saveTemplate(Request $request)
    {
        $data = $request->validate([
            'institution'    => 'nullable|string|max:255',
            'branch'         => 'nullable|string|max:255',
            'position_label' => 'nullable|string|max:255',
            'title'          => 'nullable|string|max:255',
            'description'    => 'nullable|string|max:1000',
            'show_logo'      => 'nullable|boolean',
            'width_mm'       => 'nullable|numeric|min:20|max:300',
            'height_mm'      => 'nullable|numeric|min:20|max:300',
            // Interaktiv tahrirlovchidan keladigan joylashuv/o'lcham (mm)
            'qr_size_mm'     => 'nullable|numeric|min:5|max:300',
            'qr_x_mm'        => 'nullable|numeric|min:0|max:300',
            'qr_y_mm'        => 'nullable|numeric|min:0|max:300',
            'text_x_mm'      => 'nullable|numeric|min:0|max:300',
            'text_y_mm'      => 'nullable|numeric|min:0|max:300',
            'text_w_mm'      => 'nullable|numeric|min:5|max:300',
            'text_h_mm'      => 'nullable|numeric|min:3|max:300',
            'text_size_mm'   => 'nullable|numeric|min:0.5|max:20',
        ]);

        Setting::set('staff_eval_tpl_institution', $data['institution'] ?? '');
        Setting::set('staff_eval_tpl_branch', $data['branch'] ?? '');
        Setting::set('staff_eval_tpl_position_label', $data['position_label'] ?? '');
        Setting::set('staff_eval_tpl_title', $data['title'] ?? '');
        Setting::set('staff_eval_tpl_description', $data['description'] ?? '');
        Setting::set('staff_eval_tpl_show_logo', $request->boolean('show_logo') ? '1' : '0');
        Setting::set('staff_eval_tpl_width_mm', (string)($data['width_mm'] ?? 60));
        Setting::set('staff_eval_tpl_height_mm', (string)($data['height_mm'] ?? 40));
        // Layout sozlamalari — faqat formada kelganlar yangilanadi (bo'sh = avtomatik)
        foreach (['qr_size_mm','qr_x_mm','qr_y_mm','text_x_mm','text_y_mm','text_w_mm','text_h_mm','text_size_mm'] as $k) {
            if (array_key_exists($k, $data) && $data[$k] !== null && $data[$k] !== '') {
                Setting::set('staff_eval_tpl_' . $k, (string) $data[$k]);
            }
        }

        return redirect()->route('admin.staff-evaluation.index', ['tab' => 'shablon'])
            ->with('success', "Shablon muvaffaqiyatli saqlandi.");
    }

    private function getTemplateData(): array
    {
        $w = (float) Setting::get('staff_eval_tpl_width_mm', '60');
        $h = (float) Setting::get('staff_eval_tpl_height_mm', '40');
        // Avtomatik (eski) joylashuv standartlari — saqlanmagan paytda ishlatiladi
        $autoQr = max(15, min($h - 4, $w * 0.45));
        $autoQrX = $w - $autoQr - 2;
        $autoQrY = max(2, ($h - $autoQr) / 2);
        $autoTextX = 2;
        $autoTextY = max(2, ($h - 14) / 2);
        $autoTextW = max(12, $w - $autoQr - 5);
        $autoTextH = min(16, $h - 6);
        return [
            'institution'    => Setting::get('staff_eval_tpl_institution', 'Toshkent davlat tibbiyot universiteti'),
            'branch'         => Setting::get('staff_eval_tpl_branch', 'Termiz filiali'),
            'position_label' => Setting::get('staff_eval_tpl_position_label', 'Registrator ofisi xodimi:'),
            'title'          => Setting::get('staff_eval_tpl_title', ''),
            'description'    => Setting::get('staff_eval_tpl_description', ''),
            'show_logo'      => Setting::get('staff_eval_tpl_show_logo', '1') === '1',
            'width_mm'       => $w,
            'height_mm'      => $h,
            'qr_size_mm'     => (float) Setting::get('staff_eval_tpl_qr_size_mm', (string) $autoQr),
            'qr_x_mm'        => (float) Setting::get('staff_eval_tpl_qr_x_mm', (string) $autoQrX),
            'qr_y_mm'        => (float) Setting::get('staff_eval_tpl_qr_y_mm', (string) $autoQrY),
            'text_x_mm'      => (float) Setting::get('staff_eval_tpl_text_x_mm', (string) $autoTextX),
            'text_y_mm'      => (float) Setting::get('staff_eval_tpl_text_y_mm', (string) $autoTextY),
            'text_w_mm'      => (float) Setting::get('staff_eval_tpl_text_w_mm', (string) $autoTextW),
            'text_h_mm'      => (float) Setting::get('staff_eval_tpl_text_h_mm', (string) $autoTextH),
            'text_size_mm'   => (float) Setting::get('staff_eval_tpl_text_size_mm', '1.9'),
        ];
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

    public function generateSelectedQr(Request $request)
    {
        $request->validate([
            'teacher_ids' => 'required|array|min:1',
            'teacher_ids.*' => 'integer|exists:teachers,id',
        ]);

        $count = 0;
        $skipped = 0;
        Teacher::whereIn('id', $request->teacher_ids)
            ->where('is_active', true)
            ->chunkById(100, function ($teachers) use (&$count, &$skipped) {
                foreach ($teachers as $teacher) {
                    if ($teacher->eval_qr_token) {
                        $skipped++;
                        continue;
                    }
                    $teacher->update(['eval_qr_token' => Str::random(32)]);
                    $count++;
                }
            });

        $message = "{$count} ta xodim uchun QR kod yaratildi.";
        if ($skipped > 0) {
            $message .= " ({$skipped} ta xodim avval QR kodi bo'lgani uchun o'tkazib yuborildi.)";
        }

        return redirect()->route('admin.staff-evaluation.index', array_merge(
            $request->only('search'),
            ['tab' => 'qr']
        ))->with('success', $message);
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
