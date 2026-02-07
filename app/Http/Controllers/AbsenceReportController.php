<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;

class AbsenceReportController extends Controller
{
    public function index()
    {
        return view('admin.absence_report.index');
    }
}
