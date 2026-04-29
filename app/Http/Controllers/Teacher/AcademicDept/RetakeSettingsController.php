<?php

namespace App\Http\Controllers\Teacher\AcademicDept;

use App\Http\Controllers\Controller;
use App\Models\RetakeSetting;
use App\Models\Teacher;
use App\Services\Retake\RetakeAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RetakeSettingsController extends Controller
{
    public function index()
    {
        $this->authorize();

        $settings = RetakeSetting::orderBy('key')->get()->keyBy('key');

        return view('teacher.academic-dept.retake-settings.index', [
            'settings' => $settings,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorize();

        $data = $request->validate([
            'credit_price' => 'required|numeric|min:0',
            'min_group_size' => 'required|integer|min:1',
            'receipt_max_mb' => 'required|integer|min:1|max:50',
            'reject_reason_min_length' => 'required|integer|min:3|max:200',
        ]);

        /** @var Teacher $actor */
        $actor = Auth::guard('teacher')->user();

        foreach ($data as $key => $value) {
            $setting = RetakeSetting::where('key', $key)->first();
            if ($setting) {
                $setting->update([
                    'value' => (string) $value,
                    'updated_by_user_id' => $actor->id,
                    'updated_by_name' => $actor->full_name,
                ]);
            }
        }

        return redirect()->route('teacher.retake-settings.index')
            ->with('success', __('Sozlamalar yangilandi'));
    }

    private function authorize(): void
    {
        if (!RetakeAccess::canManageSettings(Auth::guard('teacher')->user())) {
            abort(403, 'Sizda qayta o\'qish sozlamalarini boshqarish ruxsati yo\'q');
        }
    }
}
