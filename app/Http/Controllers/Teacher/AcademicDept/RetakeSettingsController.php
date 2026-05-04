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
    /**
     * Sozlamalar bir nechta rol o'rtasida bo'lingan:
     *  - O'quv bo'limi: faqat `min_group_size`
     *  - Registrator ofisi: `credit_price`, `receipt_max_mb`, `reject_reason_min_length`
     *  - Super-admin / admin: hammasi
     */
    private const ACADEMIC_KEYS = ['min_group_size'];
    private const REGISTRAR_KEYS = ['credit_price', 'receipt_max_mb', 'reject_reason_min_length'];

    public function index()
    {
        $this->authorize();

        $settings = RetakeSetting::orderBy('key')->get()->keyBy('key');
        $editableKeys = $this->editableKeys();

        return view('teacher.academic-dept.retake-settings.index', [
            'settings' => $settings,
            'editableKeys' => $editableKeys,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorize();

        $editableKeys = $this->editableKeys();

        $rules = [];
        if (in_array('credit_price', $editableKeys, true))            $rules['credit_price'] = 'required|numeric|min:0';
        if (in_array('min_group_size', $editableKeys, true))          $rules['min_group_size'] = 'required|integer|min:1';
        if (in_array('receipt_max_mb', $editableKeys, true))          $rules['receipt_max_mb'] = 'required|integer|min:1|max:50';
        if (in_array('reject_reason_min_length', $editableKeys, true))$rules['reject_reason_min_length'] = 'required|integer|min:3|max:200';

        $data = $request->validate($rules);

        /** @var Teacher $actor */
        $actor = RetakeAccess::currentStaff();

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

        return redirect()->route('admin.retake-settings.index')
            ->with('success', __('Sozlamalar yangilandi'));
    }

    /**
     * Joriy aktiv rol qaysi sozlamalarni tahrirlay olishini qaytaradi.
     */
    private function editableKeys(): array
    {
        $actor = RetakeAccess::currentStaff();
        $activeRole = (string) session('active_role', '');

        $isSuperAdmin = $actor && $actor->hasAnyRole([
            \App\Enums\ProjectRole::SUPERADMIN->value,
            \App\Enums\ProjectRole::ADMIN->value,
        ]);
        if ($isSuperAdmin) {
            return array_merge(self::ACADEMIC_KEYS, self::REGISTRAR_KEYS);
        }

        if ($activeRole === \App\Enums\ProjectRole::REGISTRAR_OFFICE->value) {
            return self::REGISTRAR_KEYS;
        }

        if (in_array($activeRole, [
            \App\Enums\ProjectRole::ACADEMIC_DEPARTMENT->value,
            \App\Enums\ProjectRole::ACADEMIC_DEPARTMENT_HEAD->value,
        ], true)) {
            return self::ACADEMIC_KEYS;
        }

        return [];
    }

    private function authorize(): void
    {
        $actor = RetakeAccess::currentStaff();

        // Sozlamalarga kirish: registrator + o'quv bo'limi (oddiy ham, boshlig'i ham) + admin
        $allowed = $actor && (
            $actor->hasAnyRole([
                \App\Enums\ProjectRole::SUPERADMIN->value,
                \App\Enums\ProjectRole::ADMIN->value,
                \App\Enums\ProjectRole::REGISTRAR_OFFICE->value,
                \App\Enums\ProjectRole::ACADEMIC_DEPARTMENT->value,
                \App\Enums\ProjectRole::ACADEMIC_DEPARTMENT_HEAD->value,
            ])
        );

        if (!$allowed) {
            abort(403, 'Sizda qayta o\'qish sozlamalarini ko\'rish ruxsati yo\'q');
        }
    }
}
