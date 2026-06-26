<?php

namespace App\Http\Controllers;

use App\Models\TestSubjectLessonTestQuestion;
use Illuminate\Support\Facades\Storage;

class TestSubjectQuestionImageController extends Controller
{
    public function show(TestSubjectLessonTestQuestion $question)
    {
        $path = trim((string) ($question->image_path ?? ''));

        if ($path === '' || !Storage::disk('public')->exists($path)) {
            abort(404, 'Rasm topilmadi.');
        }

        return Storage::disk('public')->response($path);
    }
}
