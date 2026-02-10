<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class PasswordSettingsController extends Controller
{
    public function index()
    {
        $tempPasswordDays = Setting::get('temp_password_days', 3);
        $changedPasswordDays = Setting::get('changed_password_days', 30);
        $telegramDeadlineDays = Setting::get('telegram_deadline_days', 7);

        return view('admin.settings.password', compact('tempPasswordDays', 'changedPasswordDays', 'telegramDeadlineDays'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'temp_password_days' => 'required|integer|min:1|max:365',
            'changed_password_days' => 'required|integer|min:1|max:365',
            'telegram_deadline_days' => 'required|integer|min:1|max:365',
        ]);

        Setting::set('temp_password_days', $request->temp_password_days);
        Setting::set('changed_password_days', $request->changed_password_days);
        Setting::set('telegram_deadline_days', $request->telegram_deadline_days);

        return back()->with('success', 'Sozlamalar muvaffaqiyatli yangilandi.');
    }
}
