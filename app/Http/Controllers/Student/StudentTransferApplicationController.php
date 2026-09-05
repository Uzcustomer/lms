<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\AkademikMobillikAriza;
use App\Models\StudentTransferApplication;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StudentTransferApplicationController extends Controller
{
    public function create(): View
    {
        $student = auth('student')->user();

        abort_unless($student, 401);

        // Yangi arizalar student_transfer_applications jadvalida; eski
        // (hali ko'chirilmagan) arizalar akademik mobillikda turgan bo'lishi
        // mumkin - ikkalasi ham hisobga olinadi, eng yangisi ko'rsatiladi.
        $applications = AkademikMobillikAriza::where('student_id', $student->id)
            ->with('approvals')
            ->latest()
            ->get();

        $transferApplication = StudentTransferApplication::where('student_id', $student->id)
            ->latest()
            ->first();
        $latest = collect([$applications->first(), $transferApplication])
            ->filter()
            ->sortByDesc('created_at')
            ->first();
        $canSubmit = !$latest;

        return view('student.transfer-application.create', [
            'student' => $student,
            'applications' => $applications,
            'latest' => $latest,
            'canSubmit' => $canSubmit,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $student = auth('student')->user();

        abort_unless($student, 401);

        if (
            AkademikMobillikAriza::where('student_id', $student->id)->exists()
            || StudentTransferApplication::where('student_id', $student->id)->exists()
        ) {
            return redirect()
                ->route('student.transfer-application.create')
                ->with('error', 'Hurmatli talaba, siz arizani avval yuborgansiz. Bir talabaga faqat bitta ariza yuborish mumkin.');
        }

        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:50'],
            'target_institution' => ['required', 'string', 'max:255'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'order_document' => ['required', 'file', 'max:10240'],
            'basis_document' => ['required', 'file', 'max:10240'],
        ], [
            'phone.required' => 'Telefon raqamini kiriting.',
            'phone.max' => 'Telefon raqami juda uzun.',
            'target_institution.required' => "O'qishni ko'chirmoqchi bo'lgan ta'lim tashkiloti nomini kiriting.",
            'target_institution.max' => 'Ta\'lim tashkiloti nomi 255 ta belgidan oshmasligi kerak.',
            'reason.min' => 'Sabab kamida 10 ta belgidan iborat bo\'lsin.',
            'reason.max' => 'Sabab 2000 ta belgidan oshmasligi kerak.',
            'order_document.required' => "O'qishni ko'chirish uchun asos hujjatini yuklang.",
            'order_document.mimes' => 'Buyruq PDF, Word, JPG yoki PNG formatida bo\'lishi kerak.',
            'order_document.max' => 'Transfer.edu.uz tasdiqlovchi hujjati 10 MB dan oshmasligi kerak.',
            'basis_document.required' => "O'qishni ko'chirish uchun asos hujjatini yuklang.",
            'basis_document.max' => 'Asos hujjati 10 MB dan oshmasligi kerak.',
        ]);

        $file = $validated['order_document'];
        $basisFile = $validated['basis_document'];
        $extension = strtolower($file->getClientOriginalExtension()) ?: 'bin';
        $basisExtension = strtolower($basisFile->getClientOriginalExtension()) ?: 'bin';
        try {
            $application = DB::transaction(function () use ($student, $validated, $file, $basisFile) {
                DB::table('students')
                    ->where('id', $student->id)
                    ->lockForUpdate()
                    ->first();

                if (
                    AkademikMobillikAriza::where('student_id', $student->id)->exists()
                    || StudentTransferApplication::where('student_id', $student->id)->exists()
                ) {
                    throw ValidationException::withMessages([
                        'application' => 'Hurmatli talaba, siz arizani avval yuborgansiz. Bir talabaga faqat bitta ariza yuborish mumkin.',
                    ]);
                }

                // Talaba arizasi O'QISHNI KO'CHIRISH jadvaliga yoziladi -
                // akademik mobillik registrator boshqaradigan alohida jarayon.
                return StudentTransferApplication::create([
                    'student_id' => $student->id,
                    'phone' => trim($validated['phone']),
                    'target_institution' => trim($validated['target_institution']),
                    'reason' => trim((string) ($validated['reason'] ?? '')),
                    'order_path' => 'pending',
                    'order_name' => $file->getClientOriginalName(),
                    'order_mime' => $file->getMimeType(),
                    'order_size' => $file->getSize(),
                    'basis_document_path' => 'pending',
                    'basis_document_name' => $basisFile->getClientOriginalName(),
                    'basis_document_mime' => $basisFile->getMimeType(),
                    'basis_document_size' => $basisFile->getSize(),
                    'status' => 'pending',
                ]);
            });
        } catch (ValidationException $exception) {
            return back()->withInput()->withErrors($exception->errors());
        }

        $directory = "student-transfer-applications/{$application->id}";
        $path = $file->storeAs($directory, "transfer-tasdiq.{$extension}");
        $basisPath = $basisFile->storeAs($directory, "asos-hujjati.{$basisExtension}");

        if (!$path || !$basisPath) {
            if ($path) {
                Storage::delete($path);
            }
            if ($basisPath) {
                Storage::delete($basisPath);
            }
            $application->delete();

            return back()
                ->withInput()
                ->withErrors(['basis_document' => 'Hujjatlarni saqlashda xatolik yuz berdi. Qayta urinib ko\'ring.']);
        }

        $application->update([
            'order_path' => $path,
            'basis_document_path' => $basisPath,
        ]);

        return redirect()
            ->route('student.transfer-application.create')
            ->with('success', 'Hurmatli talaba, sizning arizangiz qabul qilindi. Arizangiz ko\'rib chiqilgach, natija haqida sizga ma\'lumot beriladi.');
    }

    public function document(int $id)
    {
        $student = auth('student')->user();

        // Yangi arizalar o'z jadvalidan, eski (ko'chirilmagan)lari mobillikdan.
        $transfer = StudentTransferApplication::where('id', $id)
            ->where('student_id', $student->id)
            ->first();
        if ($transfer) {
            abort_if(!$transfer->order_path || $transfer->order_path === 'pending'
                || !Storage::exists($transfer->order_path), 404);

            return response()->file(Storage::path($transfer->order_path), [
                'Content-Type' => $transfer->order_mime ?: 'application/octet-stream',
                'Content-Disposition' => 'inline; filename="' . basename($transfer->order_name) . '"',
            ]);
        }

        $application = AkademikMobillikAriza::where('id', $id)
            ->where('student_id', $student->id)
            ->firstOrFail();

        abort_if(!$application->document_path || $application->document_path === 'pending'
            || !Storage::exists($application->document_path), 404);

        return response()->file(Storage::path($application->document_path), [
            'Content-Type' => $application->document_mime ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="' . basename($application->document_name) . '"',
        ]);
    }
}
