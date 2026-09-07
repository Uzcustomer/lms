<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

/**
 * Fanlar farqi — boshqa muassasadan/yo'nalishdan kelgan talabaning o'quv
 * rejasi bilan joriy reja orasidagi farq fanlari. Hozircha bo'sh sahifa.
 */
class SubjectDifferenceController extends Controller
{
    public function index(): View
    {
        return view('admin.subject-differences.index');
    }
}
