<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

class LectureScheduleController extends Controller
{
    public function index()
    {
        return view('admin.lecture-schedule.index');
    }
}
