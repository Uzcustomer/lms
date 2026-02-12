<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Deadline;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function index(Request $request)
    {
        $data = [];

        // Deadline data
        $data['deadlines'] = Deadline::with('level')->get();
        $data['spravkaDays'] = Setting::get('spravka_deadline_days', 10);
        $data['mtDeadlineType'] = Setting::get('mt_deadline_type', 'before_last');
        $data['mtDeadlineTime'] = Setting::get('mt_deadline_time', '17:00');
        $data['mtMaxResubmissions'] = Setting::get('mt_max_resubmissions', 3);
        $data['lessonOpeningDays'] = Setting::get('lesson_opening_days', 3);

        // Password data
        $data['tempPasswordDays'] = Setting::get('temp_password_days', 3);
        $data['changedPasswordDays'] = Setting::get('changed_password_days', 30);

        // Telegram data
        $data['telegramDeadlineDays'] = Setting::get('telegram_deadline_days', 7);

        return view('admin.settings.index', $data);
    }

    public function updateDeadlines(Request $request)
    {
        $validated = $request->validate([
            'deadlines' => 'required|array',
            'deadlines.*.days' => 'required|integer|min:1',
            'deadlines.*.joriy' => 'required|integer|min:1',
            'deadlines.*.mustaqil_talim' => 'required|integer|min:1',
        ]);

        if ($request->filled('spravka_deadline_days')) {
            Setting::set('spravka_deadline_days', (int) $request->spravka_deadline_days);
        }

        if ($request->filled('mt_deadline_type')) {
            Setting::set('mt_deadline_type', $request->mt_deadline_type);
        }

        if ($request->filled('mt_deadline_time')) {
            Setting::set('mt_deadline_time', $request->mt_deadline_time);
        }

        if ($request->filled('mt_max_resubmissions')) {
            Setting::set('mt_max_resubmissions', (int) $request->mt_max_resubmissions);
        }

        if ($request->filled('lesson_opening_days')) {
            Setting::set('lesson_opening_days', (int) $request->lesson_opening_days);
        }

        foreach ($validated['deadlines'] as $levelCode => $deadlineData) {
            Deadline::updateOrCreate(
                ['level_code' => $levelCode],
                [
                    'deadline_days' => $deadlineData['days'],
                    'joriy' => $deadlineData['joriy'],
                    'mustaqil_talim' => $deadlineData['mustaqil_talim'],
                ]
            );
        }

        return redirect()->route('admin.settings')->with('success', 'Muddatlar muvaffaqiyatli yangilandi!');
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'temp_password_days' => 'required|integer|min:1|max:365',
            'changed_password_days' => 'required|integer|min:1|max:365',
        ]);

        Setting::set('temp_password_days', $request->temp_password_days);
        Setting::set('changed_password_days', $request->changed_password_days);

        return redirect()->route('admin.settings')->with('success', 'Parol sozlamalari muvaffaqiyatli yangilandi.');
    }

    public function updateTelegram(Request $request)
    {
        $request->validate([
            'telegram_deadline_days' => 'required|integer|min:1|max:365',
        ]);

        Setting::set('telegram_deadline_days', $request->telegram_deadline_days);

        return redirect()->route('admin.settings')->with('success', 'Telegram sozlamalari muvaffaqiyatli yangilandi.');
    }
}
