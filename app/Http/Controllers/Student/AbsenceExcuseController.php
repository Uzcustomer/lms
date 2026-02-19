<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\AbsenceExcuse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AbsenceExcuseController extends Controller
{
    public function index()
    {
        $student = Auth::guard('student')->user();

        $excuses = AbsenceExcuse::byStudent($student->id)
            ->orderByDesc('created_at')
            ->paginate(15);

        return view('student.absence-excuses.index', compact('excuses'));
    }

    public function create()
    {
        $reasons = AbsenceExcuse::REASONS;
        return view('student.absence-excuses.create', compact('reasons'));
    }

    public function store(Request $request)
    {
        $student = Auth::guard('student')->user();

        $reasonKeys = implode(',', array_keys(AbsenceExcuse::REASONS));

        $request->validate([
            'reason' => "required|in:{$reasonKeys}",
            'start_date' => 'required|date|before_or_equal:end_date',
            'end_date' => [
                'required',
                'date',
                'after_or_equal:start_date',
                function ($attribute, $value, $fail) use ($request) {
                    if ($request->reason && $request->start_date && $value) {
                        $reasonData = AbsenceExcuse::REASONS[$request->reason] ?? null;
                        if ($reasonData && $reasonData['max_days']) {
                            $start = \Carbon\Carbon::parse($request->start_date);
                            $end = \Carbon\Carbon::parse($value);
                            $days = $start->diffInDays($end) + 1;
                            if ($days > $reasonData['max_days']) {
                                $fail("Tanlangan sabab uchun maksimum {$reasonData['max_days']} kun ruxsat etiladi. Siz {$days} kun belgiladingiz.");
                            }
                        }
                    }
                },
            ],
            'description' => 'nullable|string|max:1000',
            'file' => [
                'required',
                'file',
                'max:10240',
                function ($attribute, $value, $fail) {
                    $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
                    $clientExt = strtolower($value->getClientOriginalExtension());
                    $guessedExt = $value->guessExtension();
                    if (!in_array($clientExt, $allowedExtensions) && !in_array($guessedExt, $allowedExtensions)) {
                        $fail('Faqat PDF, JPG, PNG, DOC, DOCX formatdagi fayllar qabul qilinadi.');
                    }
                },
            ],
        ], [
            'reason.required' => 'Sababni tanlang',
            'reason.in' => 'Noto\'g\'ri sabab tanlangan',
            'start_date.required' => 'Boshlanish sanasini kiriting',
            'end_date.required' => 'Tugash sanasini kiriting',
            'start_date.before_or_equal' => 'Boshlanish sanasi tugash sanasidan keyin bo\'lmasligi kerak',
            'end_date.after_or_equal' => 'Tugash sanasi boshlanish sanasidan oldin bo\'lmasligi kerak',
            'file.required' => 'Hujjat yuklash majburiy',
            'file.max' => 'Fayl hajmi 10MB dan oshmasligi kerak',
        ]);

        $file = $request->file('file');
        $filePath = $file->store('absence-excuses/' . $student->hemis_id, 'public');

        AbsenceExcuse::create([
            'student_id' => $student->id,
            'student_hemis_id' => $student->hemis_id,
            'student_full_name' => $student->full_name,
            'group_name' => $student->group_name,
            'department_name' => $student->department_name,
            'reason' => $request->reason,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'description' => $request->description,
            'file_path' => $filePath,
            'file_original_name' => $file->getClientOriginalName(),
            'status' => 'pending',
        ]);

        return redirect()
            ->route('student.absence-excuses.index')
            ->with('success', 'Arizangiz muvaffaqiyatli yuborildi.');
    }

    public function show($id)
    {
        $student = Auth::guard('student')->user();
        $excuse = AbsenceExcuse::byStudent($student->id)->findOrFail($id);

        return view('student.absence-excuses.show', compact('excuse'));
    }

    public function download($id)
    {
        $student = Auth::guard('student')->user();
        $excuse = AbsenceExcuse::byStudent($student->id)->findOrFail($id);

        $filePath = storage_path('app/public/' . $excuse->file_path);
        if (!file_exists($filePath)) {
            abort(404, 'Fayl serverda topilmadi');
        }

        return response()->download($filePath, $excuse->file_original_name);
    }

    public function downloadPdf($id)
    {
        $student = Auth::guard('student')->user();
        $excuse = AbsenceExcuse::byStudent($student->id)->findOrFail($id);

        if (!$excuse->isApproved() || !$excuse->approved_pdf_path) {
            abort(404, 'PDF hujjat topilmadi');
        }

        $filePath = storage_path('app/public/' . $excuse->approved_pdf_path);
        if (!file_exists($filePath)) {
            abort(404, 'PDF fayl serverda topilmadi');
        }

        return response()->download($filePath, 'sababli_ariza_' . $excuse->id . '.pdf');
    }
}
