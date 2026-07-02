<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\StudentFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class StudentDocumentController extends Controller
{
    public function index(Request $request)
    {
        $student = Auth::guard('student')->user();

        $files = StudentFile::query()
            ->where('student_id', $student->id)
            ->latest()
            ->get();

        return view('student.documents.index', compact('student', 'files'));
    }

    public function download(StudentFile $file)
    {
        $student = Auth::guard('student')->user();

        if ((int) $file->student_id !== (int) $student->id) {
            abort(404);
        }

        $disk = Storage::disk('public');
        if (!$disk->exists($file->path)) {
            return back()->with('error', "Fayl topilmadi.");
        }

        return $disk->download($file->path, $file->original_name);
    }
}
