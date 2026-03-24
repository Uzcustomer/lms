<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\StudentPassport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class StudentPassportController extends Controller
{
    public function index()
    {
        $student = Auth::guard('student')->user();

        if (!$student->is_graduate) {
            abort(403);
        }

        $studentPassport = StudentPassport::where('student_id', $student->id)->first();

        return view('student.passport', compact('studentPassport', 'student'));
    }

    public function store(Request $request)
    {
        $student = Auth::guard('student')->user();

        if (!$student->is_graduate) {
            abort(403);
        }

        $existing = StudentPassport::where('student_id', $student->id)->first();

        $fileRules = $existing
            ? ['nullable|file|mimes:jpg,jpeg,pdf|max:1024', 'nullable|file|mimes:jpg,jpeg,pdf|max:1024', 'nullable|file|mimes:jpg,jpeg,pdf|max:1024']
            : ['required|file|mimes:jpg,jpeg,pdf|max:1024', 'required|file|mimes:jpg,jpeg,pdf|max:1024', 'required|file|mimes:jpg,jpeg,pdf|max:1024'];

        $request->merge([
            'first_name' => mb_strtoupper($request->first_name),
            'last_name' => mb_strtoupper($request->last_name),
            'father_name' => mb_strtoupper($request->father_name),
            'first_name_en' => mb_strtoupper($request->first_name_en),
            'last_name_en' => mb_strtoupper($request->last_name_en),
            'passport_series' => mb_strtoupper($request->passport_series),
        ]);

        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'father_name' => 'required|string|max:255',
            'first_name_en' => 'required|string|max:255',
            'last_name_en' => 'required|string|max:255',
            'passport_series' => 'required|string|max:2|regex:/^[A-Z]{2}$/',
            'passport_number' => 'required|string|max:7|regex:/^\d{7}$/',
            'jshshir' => 'required|string|max:14|regex:/^\d{14}$/',
            'passport_front' => $fileRules[0],
            'passport_back' => $fileRules[1],
            'foreign_passport' => $fileRules[2],
        ], [
            'first_name.required' => 'Ismingizni kiriting.',
            'last_name.required' => 'Familiyangizni kiriting.',
            'father_name.required' => 'Otangizning ismini kiriting.',
            'first_name_en.required' => 'Inglizcha ismingizni kiriting.',
            'last_name_en.required' => 'Inglizcha familiyangizni kiriting.',
            'passport_series.required' => 'Passport seriyasini kiriting.',
            'passport_series.regex' => 'Passport seriyasi 2 ta harfdan iborat bo\'lishi kerak.',
            'passport_number.required' => 'Passport raqamini kiriting.',
            'passport_number.regex' => 'Passport raqami 7 ta raqamdan iborat bo\'lishi kerak.',
            'jshshir.required' => 'JSHSHIR ni kiriting.',
            'jshshir.regex' => 'JSHSHIR 14 ta raqamdan iborat bo\'lishi kerak.',
            'passport_front.required' => 'Pasport oldi tarafini yuklang.',
            'passport_front.mimes' => 'Pasport oldi tarafi faqat JPG yoki PDF formatida bo\'lishi kerak.',
            'passport_front.max' => 'Fayl hajmi 1MB dan oshmasligi kerak.',
            'passport_back.required' => 'Pasport orqa tarafini yuklang.',
            'passport_back.mimes' => 'Pasport orqa tarafi faqat JPG yoki PDF formatida bo\'lishi kerak.',
            'passport_back.max' => 'Fayl hajmi 1MB dan oshmasligi kerak.',
            'foreign_passport.required' => 'Xorijga chiqish pasportini yuklang.',
            'foreign_passport.mimes' => 'Xorijga chiqish pasporti faqat JPG yoki PDF formatida bo\'lishi kerak.',
            'foreign_passport.max' => 'Fayl hajmi 1MB dan oshmasligi kerak.',
        ]);

        $data = [
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'father_name' => $request->father_name,
            'first_name_en' => $request->first_name_en,
            'last_name_en' => $request->last_name_en,
            'full_name_uz' => $request->last_name . ' ' . $request->first_name . ' ' . $request->father_name,
            'full_name_en' => $request->last_name_en . ' ' . $request->first_name_en,
            'passport_series' => $request->passport_series,
            'passport_number' => $request->passport_number,
            'jshshir' => $request->jshshir,
        ];

        $storagePath = 'student-passports/' . $student->id;

        if ($request->hasFile('passport_front')) {
            if ($existing?->passport_front_path) {
                Storage::disk('public')->delete($existing->passport_front_path);
            }
            $data['passport_front_path'] = $request->file('passport_front')->store($storagePath, 'public');
        }

        if ($request->hasFile('passport_back')) {
            if ($existing?->passport_back_path) {
                Storage::disk('public')->delete($existing->passport_back_path);
            }
            $data['passport_back_path'] = $request->file('passport_back')->store($storagePath, 'public');
        }

        if ($request->hasFile('foreign_passport')) {
            if ($existing?->foreign_passport_path) {
                Storage::disk('public')->delete($existing->foreign_passport_path);
            }
            $data['foreign_passport_path'] = $request->file('foreign_passport')->store($storagePath, 'public');
        }

        StudentPassport::updateOrCreate(
            ['student_id' => $student->id],
            $data
        );

        return redirect()->route('student.passport.index')
            ->with('success', 'Pasport ma\'lumotlari saqlandi.');
    }

    public function showFile(string $field)
    {
        $student = Auth::guard('student')->user();
        $passport = StudentPassport::where('student_id', $student->id)->firstOrFail();

        $allowed = ['passport_front_path', 'passport_back_path', 'foreign_passport_path'];
        if (!in_array($field, $allowed) || !$passport->$field) {
            abort(404);
        }

        return Storage::disk('public')->response($passport->$field);
    }

    public function deleteFile(string $field)
    {
        $student = Auth::guard('student')->user();
        $passport = StudentPassport::where('student_id', $student->id)->firstOrFail();

        $allowed = ['passport_front_path', 'passport_back_path', 'foreign_passport_path'];
        if (!in_array($field, $allowed) || !$passport->$field) {
            abort(404);
        }

        Storage::disk('public')->delete($passport->$field);
        $passport->update([$field => null]);

        if (request()->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->back()
            ->with('success', 'Fayl o\'chirildi.');
    }
}
