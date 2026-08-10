<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\StudentTransferApplication;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class StudentTransferApplicationController extends Controller
{
    public function create(): View
    {
        $student = auth('student')->user();

        abort_unless($student, 401);

        return view('student.transfer-application.create', [
            'student' => $student,
            'applications' => StudentTransferApplication::where('student_id', $student->id)
                ->latest()
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $student = auth('student')->user();

        abort_unless($student, 401);

        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:50'],
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
            'order_document' => ['required', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png', 'max:10240'],
        ], [
            'phone.required' => 'Telefon raqamini kiriting.',
            'phone.max' => 'Telefon raqami juda uzun.',
            'reason.required' => "O'qishni ko'chirish sababini kiriting.",
            'reason.min' => 'Sabab kamida 10 ta belgidan iborat bo\'lsin.',
            'reason.max' => 'Sabab 2000 ta belgidan oshmasligi kerak.',
            'order_document.required' => "O'qishni ko'chirish buyrug'ini yuklang.",
            'order_document.mimes' => 'Buyruq PDF, Word, JPG yoki PNG formatida bo\'lishi kerak.',
            'order_document.max' => 'Fayl hajmi 10 MB dan oshmasligi kerak.',
        ]);

        $file = $validated['order_document'];
        $extension = strtolower($file->getClientOriginalExtension());
        $application = StudentTransferApplication::create([
            'student_id' => $student->id,
            'phone' => trim($validated['phone']),
            'reason' => trim($validated['reason']),
            'order_path' => 'pending',
            'order_name' => $file->getClientOriginalName(),
            'order_mime' => $file->getMimeType(),
            'order_size' => $file->getSize(),
            'status' => 'pending',
        ]);

        $path = $file->storeAs(
            "student-transfer-applications/{$application->id}",
            "buyruq.{$extension}"
        );

        if (!$path) {
            $application->delete();

            return back()
                ->withInput()
                ->withErrors(['order_document' => 'Faylni saqlashda xatolik yuz berdi. Qayta urinib ko\'ring.']);
        }

        $application->update(['order_path' => $path]);

        return redirect()
            ->route('student.transfer-application.create')
            ->with('success', "O'qishni ko'chirish arizangiz muvaffaqiyatli yuborildi.");
    }

    public function document(int $id)
    {
        $student = auth('student')->user();

        $application = StudentTransferApplication::where('id', $id)
            ->where('student_id', $student->id)
            ->firstOrFail();

        abort_if(!$application->order_path || $application->order_path === 'pending'
            || !Storage::exists($application->order_path), 404);

        return response()->file(Storage::path($application->order_path), [
            'Content-Type' => $application->order_mime ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="' . basename($application->order_name) . '"',
        ]);
    }
}
