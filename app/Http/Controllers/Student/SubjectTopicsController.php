<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\CurriculumSubject;
use App\Services\KtrTopics;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * "Fan mavzulari": talabaning joriy semestr fanlari va har fanning KTR
 * (kalendar-tematik reja) bo'yicha ma'ruza / amaliy mavzulari — sahifada
 * ko'rish va PDF qilib yuklab olish.
 */
class SubjectTopicsController extends Controller
{
    public function index()
    {
        $subjects = $this->studentSubjects()->map(function (CurriculumSubject $cs) {
            $topics = KtrTopics::forSubject($cs);

            return [
                'id' => $cs->id,
                'name' => $cs->subject_name,
                'has_plan' => $topics['has_plan'],
                'topics_count' => $topics['topics_count'],
                'types' => array_values($topics['types']),
            ];
        })->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values();

        return view('student.topics.index', [
            'subjects' => $subjects,
            'semesterName' => Auth::user()->semester_name,
        ]);
    }

    public function show(int $curriculumSubject)
    {
        $cs = $this->ownSubject($curriculumSubject);

        return view('student.topics.show', [
            'subject' => $cs,
            'topics' => KtrTopics::forSubject($cs),
        ]);
    }

    public function pdf(int $curriculumSubject)
    {
        $cs = $this->ownSubject($curriculumSubject);
        $student = Auth::user();
        $topics = KtrTopics::forSubject($cs);
        abort_unless($topics['has_plan'], 404);

        $pdf = Pdf::loadView('pdf.subject-topics', [
            'subject' => $cs,
            'topics' => $topics,
            'student' => $student,
        ])->setPaper('a4')
            // Faqat ishlatilgan harflar joylanadi — fayl hajmi 1 MB dan bir necha o'n KB ga tushadi
            ->setOption('isFontSubsettingEnabled', true);

        return $pdf->download(Str::slug($cs->subject_name ?: 'fan') . '-mavzular.pdf');
    }

    /** Talabaning joriy semestr fanlari (fanlar sahifasi bilan bir xil qoida) */
    private function studentSubjects(): Collection
    {
        $student = Auth::user();
        $subjects = CurriculumSubject::where('curricula_hemis_id', $student->curriculum_id)
            ->where('semester_code', $student->semester_code)
            ->where('is_active', true)
            ->get();

        return app(StudentController::class)->keepStudentCycleVariants($subjects, $student, $student->semester_code);
    }

    /** Faqat talabaning o'z fani ochiladi */
    private function ownSubject(int $id): CurriculumSubject
    {
        $cs = $this->studentSubjects()->firstWhere('id', $id);
        abort_unless($cs, 404);

        return $cs;
    }
}
