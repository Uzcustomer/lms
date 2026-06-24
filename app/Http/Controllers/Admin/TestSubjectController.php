<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

class TestSubjectController extends Controller
{
    public function index()
    {
        return view('admin.test-subjects.index');
    }
}
