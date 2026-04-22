<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class FeatureToggleController extends Controller
{
    private array $features = [
        'superadmin_grade_edit' => [
            'label' => 'Superadmin baho tahrirlash',
            'description' => 'Jurnalda batafsilga o\'tib istalgan bahoni edit qilish imkoniyati',
        ],
        'absence_excuse_delete' => [
            'label' => 'Sababli ariza o\'chirish',
            'description' => 'Sababli arizalar ro\'yxatida O\'chirish tugmasini ko\'rsatish',
        ],
    ];

    public function index()
    {
        if (!auth()->user()?->hasRole('superadmin')) {
            abort(403);
        }

        $toggles = [];
        foreach ($this->features as $key => $meta) {
            $toggles[] = [
                'key' => $key,
                'label' => $meta['label'],
                'description' => $meta['description'],
                'enabled' => Setting::get('feature_' . $key, '0') === '1',
            ];
        }

        return view('admin.feature-toggles', compact('toggles'));
    }

    public function update(Request $request)
    {
        if (!auth()->user()?->hasRole('superadmin')) {
            abort(403);
        }

        $request->validate([
            'key' => 'required|string',
            'enabled' => 'required|boolean',
        ]);

        if (!array_key_exists($request->key, $this->features)) {
            return response()->json(['success' => false, 'message' => 'Noma\'lum funksiya'], 400);
        }

        Setting::set('feature_' . $request->key, $request->enabled ? '1' : '0');

        return response()->json([
            'success' => true,
            'key' => $request->key,
            'enabled' => $request->enabled,
        ]);
    }
}
