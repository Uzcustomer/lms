<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\StudentComplaint;
use App\Services\StudentComplaintNotifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * Xalqaro ta'lim fakulteti talabalarining shikoyatlari (Complaints).
 * Talaba muammoni yozadi, telefon raqamini (majburiy) va bir nechta rasm
 * biriktiradi; shikoyat registrator ofisiga tushadi.
 */
class StudentComplaintController extends Controller
{
    public const MAX_IMAGES = 5;

    public function index()
    {
        $student = $this->student();

        return view('student.complaints', [
            'student' => $student,
            'complaints' => StudentComplaint::where('student_id', $student->id)->latest()->get(),
            'maxImages' => self::MAX_IMAGES,
        ]);
    }

    public function store(Request $request)
    {
        $student = $this->student();

        $data = $request->validate([
            'phone' => ['required', 'string', 'max:32', 'regex:/^\+?[0-9\s\-()]{7,32}$/'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            'images' => ['nullable', 'array', 'max:' . self::MAX_IMAGES],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [
            'phone.required' => __('Telefon raqamingizni kiriting.'),
            'phone.regex' => __("Telefon raqami noto'g'ri."),
            'message.required' => __('Muammoni yozing.'),
            'message.min' => __("Muammoni batafsilroq yozing (kamida 10 ta belgi)."),
            'images.max' => __('Ko\'pi bilan :max ta rasm yuklash mumkin.', ['max' => self::MAX_IMAGES]),
            'images.*.image' => __('Faqat rasm (jpg, png, webp) yuklash mumkin.'),
            'images.*.mimes' => __('Faqat rasm (jpg, png, webp) yuklash mumkin.'),
            'images.*.max' => __('Har bir rasm 5 MB dan oshmasligi kerak.'),
        ]);

        $complaint = StudentComplaint::create([
            'student_id' => $student->id,
            'student_hemis_id' => $student->hemis_id,
            'student_id_number' => $student->student_id_number,
            'student_name' => $student->full_name,
            'group_name' => $student->group_name,
            'faculty_name' => $student->department_name,
            'phone' => trim($data['phone']),
            'message' => trim($data['message']),
            'images' => [],
            'status' => StudentComplaint::STATUS_NEW,
        ]);

        $paths = [];
        foreach ($request->file('images', []) as $file) {
            $paths[] = $file->store('student-complaints/' . $complaint->id, StudentComplaint::DISK);
        }
        $complaint->update(['images' => $paths]);

        // Telegram javobni kechiktirmasin — javob yuborilgandan keyin.
        dispatch(fn () => StudentComplaintNotifier::notify($complaint->fresh()))->afterResponse();

        return redirect()
            ->route('student.complaints.index')
            ->with('success', __("Shikoyatingiz yuborildi. Registrator ofisi ko'rib chiqadi."));
    }

    /** Talaba o'z shikoyatidagi rasmni ko'radi. */
    public function image(StudentComplaint $complaint, int $index)
    {
        abort_unless((int) $complaint->student_id === (int) $this->student()->id, 404);

        $path = ($complaint->images ?? [])[$index] ?? null;
        abort_unless($path && Storage::disk(StudentComplaint::DISK)->exists($path), 404);

        return Storage::disk(StudentComplaint::DISK)->response($path);
    }

    private function student()
    {
        $student = Auth::guard('student')->user();
        abort_unless($student && $student->isInternationalFaculty(), 404);

        return $student;
    }
}
