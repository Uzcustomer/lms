<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class JurnalController extends Controller
{
    /**
     * Jurnal sahifasi
     */
    public function index(Request $request)
    {
        return view('admin.jurnal.index');
    }
}
