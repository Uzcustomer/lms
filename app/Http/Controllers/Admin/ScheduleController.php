<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ImportSchedulesJob;
use App\Jobs\ImportSchedulesPartiallyJob;
use App\Models\Schedule;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    public function importSchedules(Request $request)
    {
        try {
            // Schedule::truncate();

            ImportSchedulesJob::dispatch();

            return response()->json([
                'success' => true,
                'message' => 'Dars jadvallari muvaffaqiyatli yangilandi'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Xatolik yuz berdi: ' . $e->getMessage()
            ], 500);
        }
    }
}
