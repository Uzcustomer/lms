<?php

namespace App\Http\Controllers\Admin;

use App\Exports\VisaApplicationsExport;
use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\VisaApplication;
use App\Services\TelegramService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpWord\TemplateProcessor;
use ZipArchive;

class VisaApplicationController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->input('status'); // filter
        // Default: faqat har bir talabaning eng oxirgi arizasi. Admin "Hammasi"
        // tugmasini bossa, eski (qayta topshirilgan) arizalarni ham ko'rsatamiz.
        $showAll = $request->boolean('all');

        // Har bir hemis_id bo'yicha eng oxirgi (eng katta id) arizaning idsi
        $latestIds = VisaApplication::query()
            ->selectRaw('MAX(id) as id')
            ->groupBy('student_hemis_id')
            ->pluck('id');

        $base = VisaApplication::query();
        if (!$showAll) {
            $base->whereIn('id', $latestIds);
        }

        $query = (clone $base)->latest();
        if ($status && in_array($status, ['pending', 'reviewing', 'approved', 'rejected'])) {
            $query->where('status', $status);
        }
        $applications = $query->paginate(50)->withQueryString();

        $counts = (clone $base)
            ->selectRaw('status, COUNT(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status')
            ->all();

        $internationalStudents = (clone $this->internationalStudentsQuery())
            ->whereNotNull('hemis_id')
            ->select(
                'hemis_id',
                'full_name',
                'group_name',
                'student_id_number',
                'department_name',
                'specialty_name',
                'level_code',
                'level_name'
            )
            ->orderBy('full_name')
            ->get();

        $totalForeignCitizens = $internationalStudents->count();

        $latestApplications = VisaApplication::query()
            ->whereIn('id', $latestIds)
            ->whereNotNull('student_hemis_id')
            ->get()
            ->keyBy(fn (VisaApplication $app) => (string) $app->student_hemis_id);

        $submittedHemisIds = VisaApplication::query()
            ->whereNotNull('student_hemis_id')
            ->whereIn('student_hemis_id', $internationalStudents->pluck('hemis_id'))
            ->distinct()
            ->pluck('student_hemis_id')
            ->map(fn ($id) => (string) $id)
            ->all();

        $submittedLookup = array_fill_keys($submittedHemisIds, true);

        $studentList = $internationalStudents->map(function (Student $student) use ($latestApplications) {
            $latestApplication = $latestApplications->get((string) $student->hemis_id);

            return [
                'hemis_id'           => $student->hemis_id,
                'full_name'          => $student->full_name,
                'group_name'         => $student->group_name,
                'student_id_number'  => $student->student_id_number,
                'department_name'    => $student->department_name,
                'specialty_name'     => $student->specialty_name,
                'course_name'        => $student->level_name ?: ($student->level_code ? $student->level_code . '-kurs' : '—'),
                'application_number' => $latestApplication?->application_number,
                'application_status' => $latestApplication?->status,
                'submitted_at'       => $latestApplication?->created_at?->format('d.m.Y H:i'),
            ];
        });

        $submittedStudents = $studentList
            ->filter(fn (array $student) => isset($submittedLookup[(string) $student['hemis_id']]))
            ->values();

        $notSubmittedStudents = $studentList
            ->filter(fn (array $student) => !isset($submittedLookup[(string) $student['hemis_id']]))
            ->values();

        $submittedApplications = $submittedStudents->count();
        $notSubmittedApplications = $notSubmittedStudents->count();

        return view('admin.visa-applications.index', [
            'applications' => $applications,
            'counts'       => $counts,
            'status'       => $status,
            'showAll'      => $showAll,
            'visaStats'    => [
                'total_foreign_citizens' => $totalForeignCitizens,
                'submitted_applications' => $submittedApplications,
                'not_submitted'          => $notSubmittedApplications,
                'lists'                  => [
                    'total'         => $studentList->values(),
                    'submitted'     => $submittedStudents,
                    'not_submitted' => $notSubmittedStudents,
                ],
            ],
        ]);
    }

    public function approve(VisaApplication $application, Request $request)
    {
        $application->update([
            'status'      => 'approved',
            'admin_note'  => $request->input('admin_note'),
            'reviewed_at' => now(),
            'reviewed_by' => auth()->id(),
        ]);
        $this->notifyStudent($application);
        return redirect()->route('admin.visa-applications.index', ['status' => 'approved'])
            ->with('success', "Ariza #{$application->application_number} qabul qilindi.");
    }

    public function reject(VisaApplication $application, Request $request)
    {
        $application->update([
            'status'      => 'rejected',
            'admin_note'  => $request->input('admin_note'),
            'reviewed_at' => now(),
            'reviewed_by' => auth()->id(),
        ]);
        $this->notifyStudent($application);
        return redirect()->route('admin.visa-applications.index', ['status' => 'rejected'])
            ->with('success', "Ariza #{$application->application_number} rad etildi.");
    }

    public function statsList(string $type)
    {
        abort_unless(in_array($type, ['total', 'submitted', 'not_submitted'], true), 404);

        $latestIds = VisaApplication::query()
            ->selectRaw('MAX(id) as id')
            ->groupBy('student_hemis_id')
            ->pluck('id');

        $internationalStudents = (clone $this->internationalStudentsQuery())
            ->whereNotNull('hemis_id')
            ->select(
                'hemis_id',
                'full_name',
                'group_name',
                'student_id_number',
                'department_name',
                'specialty_name',
                'level_code',
                'level_name'
            )
            ->orderBy('full_name')
            ->get();

        $latestApplications = VisaApplication::query()
            ->whereIn('id', $latestIds)
            ->whereNotNull('student_hemis_id')
            ->get()
            ->keyBy(fn (VisaApplication $app) => (string) $app->student_hemis_id);

        $submittedHemisIds = VisaApplication::query()
            ->whereNotNull('student_hemis_id')
            ->whereIn('student_hemis_id', $internationalStudents->pluck('hemis_id'))
            ->distinct()
            ->pluck('student_hemis_id')
            ->map(fn ($id) => (string) $id)
            ->all();

        $submittedLookup = array_fill_keys($submittedHemisIds, true);

        $studentList = $internationalStudents->map(function (Student $student) use ($latestApplications) {
            $latestApplication = $latestApplications->get((string) $student->hemis_id);

            return [
                'hemis_id'           => $student->hemis_id,
                'full_name'          => $student->full_name,
                'group_name'         => $student->group_name,
                'student_id_number'  => $student->student_id_number,
                'department_name'    => $student->department_name,
                'specialty_name'     => $student->specialty_name,
                'course_name'        => $student->level_name ?: ($student->level_code ? $student->level_code . '-kurs' : '—'),
                'application_number' => $latestApplication?->application_number,
                'application_status' => $latestApplication?->status,
                'submitted_at'       => $latestApplication?->created_at?->format('d.m.Y H:i'),
            ];
        })->values();

        $submittedStudents = $studentList
            ->filter(fn (array $student) => isset($submittedLookup[(string) $student['hemis_id']]))
            ->values();

        $notSubmittedStudents = $studentList
            ->filter(fn (array $student) => !isset($submittedLookup[(string) $student['hemis_id']]))
            ->values();

        $meta = [
            'total' => [
                'title' => 'Xorijiy fuqarolar umumiy ro‘yxati',
                'count' => $studentList->count(),
                'rows' => $studentList,
                'theme' => ['bg' => '#eff6ff', 'fg' => '#0c4a6e', 'border' => '#bae6fd', 'table' => '#0f172a'],
            ],
            'submitted' => [
                'title' => 'Visa application topshirganlar',
                'count' => $submittedStudents->count(),
                'rows' => $submittedStudents,
                'theme' => ['bg' => '#ecfdf5', 'fg' => '#065f46', 'border' => '#a7f3d0', 'table' => '#047857'],
            ],
            'not_submitted' => [
                'title' => 'Visa application topshirmaganlar',
                'count' => $notSubmittedStudents->count(),
                'rows' => $notSubmittedStudents,
                'theme' => ['bg' => '#fff7ed', 'fg' => '#92400e', 'border' => '#fcd34d', 'table' => '#d97706'],
            ],
        ];

        $statusMeta = [
            'pending'   => ['label' => 'Kutilmoqda', 'bg' => '#fef3c7', 'fg' => '#92400e', 'border' => '#fde68a'],
            'reviewing' => ['label' => 'Ko‘rilmoqda', 'bg' => '#dbeafe', 'fg' => '#1e40af', 'border' => '#bfdbfe'],
            'approved'  => ['label' => 'Qabul qilindi', 'bg' => '#d1fae5', 'fg' => '#065f46', 'border' => '#a7f3d0'],
            'rejected'  => ['label' => 'Rad etilgan', 'bg' => '#fee2e2', 'fg' => '#991b1b', 'border' => '#fecaca'],
        ];

        return view('admin.visa-applications.stats-list', [
            'type' => $type,
            'title' => $meta[$type]['title'],
            'count' => $meta[$type]['count'],
            'rows' => $meta[$type]['rows'],
            'theme' => $meta[$type]['theme'],
            'statusMeta' => $statusMeta,
        ]);
    }

    /**
     * Bir nechta arizani bir vaqtning o'zida boshqa bosqichga ko'chirish.
     */
    public function bulkUpdate(Request $request)
    {
        $data = $request->validate([
            'ids'    => 'required|array|min:1',
            'ids.*'  => 'integer|exists:visa_applications,id',
            'action' => 'required|in:pending,reviewing,approved,rejected',
            'admin_note' => 'nullable|string|max:500',
        ]);

        $apps = VisaApplication::whereIn('id', $data['ids'])->get();
        $updated = 0;
        foreach ($apps as $app) {
            $app->update([
                'status'      => $data['action'],
                'admin_note'  => $data['admin_note'] ?? $app->admin_note,
                'reviewed_at' => now(),
                'reviewed_by' => auth()->id(),
            ]);
            $this->notifyStudent($app);
            $updated++;
        }

        return redirect()->route('admin.visa-applications.index', ['status' => $data['action']])
            ->with('success', "{$updated} ta ariza '{$data['action']}' bosqichiga ko'chirildi.");
    }

    public function destroy(VisaApplication $application)
    {
        foreach ([$application->passport_pdf_path, $application->application_pdf_path, $application->receipt_pdf_path] as $path) {
            if ($path && Storage::disk('local')->exists($path)) {
                Storage::disk('local')->delete($path);
            }
        }
        $num = $application->application_number;
        $application->delete();

        return back()->with('success', "Ariza #{$num} o'chirildi.");
    }

    public function file(VisaApplication $application, string $kind)
    {
        $map = [
            'passport'    => $application->passport_pdf_path,
            'application' => $application->application_pdf_path,
            'receipt'     => $application->receipt_pdf_path,
        ];
        $path = $map[$kind] ?? null;
        if (!$path || !Storage::disk('local')->exists($path)) {
            abort(404, 'File not found.');
        }
        return response()->file(Storage::disk('local')->path($path), [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . basename($path) . '"',
        ]);
    }

    /**
     * Excel export — agar id'lar berilsa, faqat shu arizalar; aks holda
     * joriy holat (status) bo'yicha yuklab beriladi.
     */
    public function export(Request $request)
    {
        $status = $request->input('status');
        $ids    = array_filter(array_map('intval', (array) $request->input('ids', [])));

        $name = 'visa-arizalar';
        if (!empty($ids)) {
            $name .= '-' . count($ids) . 'ta';
        } elseif ($status) {
            $name .= '-' . $status;
        }
        $name .= '-' . now()->format('Ymd_His') . '.xlsx';

        return Excel::download(new VisaApplicationsExport($status, $ids), $name);
    }

    public function downloadDocuments(Request $request)
    {
        $data = $request->validate([
            'ids'   => 'required|array|min:1',
            'ids.*' => 'integer|exists:visa_applications,id',
        ]);

        if (!class_exists(ZipArchive::class)) {
            abort(500, 'ZipArchive extension is not available on the server.');
        }

        $apps = VisaApplication::whereIn('id', $data['ids'])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        if ($apps->isEmpty()) {
            return back()->with('error', 'Tanlangan arizalar topilmadi.');
        }

        $tmp = tempnam(sys_get_temp_dir(), 'visa_docs_');
        if ($tmp === false) {
            abort(500, 'Temporary file could not be created.');
        }

        $zipPath = $tmp . '.zip';
        @unlink($tmp);

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            abort(500, 'ZIP file could not be created.');
        }

        $addedFiles = 0;
        $fileCounts = [];

        foreach ($apps as $app) {
            $fullName = trim(implode(' ', array_filter([
                $app->last_name,
                $app->first_name,
                $app->middle_name,
            ])));
            $baseName = $this->zipSafeName($fullName, 'student-' . $app->application_number);

            $documents = [
                'passport'    => $app->passport_pdf_path,
                'application' => $app->application_pdf_path,
                'receipt'     => $app->receipt_pdf_path,
            ];

            foreach ($documents as $label => $path) {
                if (!$path || !Storage::disk('local')->exists($path)) {
                    continue;
                }

                $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
                $extension = $extension !== '' ? $extension : 'pdf';
                $rawName = $baseName . '_' . $label . '.' . $extension;
                $targetName = $this->uniqueZipFileName($rawName, $fileCounts);

                $zip->addFile(Storage::disk('local')->path($path), $targetName);
                $addedFiles++;
            }
        }

        $zip->close();

        if ($addedFiles === 0) {
            @unlink($zipPath);
            return back()->with('error', 'Tanlangan arizalarda yuklab olinadigan hujjatlar topilmadi.');
        }

        $filename = 'visa-documents-' . now()->format('Ymd_His') . '.zip';

        return response()->download($zipPath, $filename, [
            'Content-Type' => 'application/zip',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Tanlangan arizalardan telex (so'rovnoma) Word hujjatini yaratadi.
     * Faqat 'approved' arizalar uchun ishlatish tavsiya etiladi.
     */
    public function telex(Request $request)
    {
        $data = $request->validate([
            'ids'   => 'required|array|min:1',
            'ids.*' => 'integer|exists:visa_applications,id',
        ]);

        $apps = VisaApplication::whereIn('id', $data['ids'])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        if ($apps->isEmpty()) {
            return back()->with('error', 'Tanlangan arizalar topilmadi.');
        }

        $templatePath = resource_path('templates/visa/telex_template.docx');
        if (!file_exists($templatePath)) {
            abort(500, 'Telex shabloni topilmadi: ' . $templatePath);
        }

        $processor = new TemplateProcessor($templatePath);

        $paragraphs = $apps
            ->values()
            ->map(function (VisaApplication $app, int $index) {
                $fullName = trim(implode(' ', array_filter([
                    $app->last_name,
                    $app->first_name,
                    $app->middle_name,
                ])));

                $details = array_filter([
                    $app->passport_number ? 'passport raqami ' . $app->passport_number : null,
                    optional($app->birth_date)->format('d.m.Y'),
                ]);

                $line = ($index + 1) . '. ' . $fullName
                    . ($details ? ' (' . implode(', ', $details) . ')' : '');

                $text = htmlspecialchars($line, ENT_XML1 | ENT_COMPAT, 'UTF-8');

                return '<w:p>'
                    . '<w:pPr>'
                    . '<w:spacing w:before="0" w:after="0" w:line="240" w:lineRule="auto"/>'
                    . '<w:ind w:left="360" w:hanging="360"/>'
                    . '</w:pPr>'
                    . '<w:r>'
                    . '<w:rPr>'
                    . '<w:rFonts w:ascii="Times New Roman" w:hAnsi="Times New Roman" w:cs="Times New Roman"/>'
                    . '<w:sz w:val="24"/>'
                    . '<w:szCs w:val="24"/>'
                    . '</w:rPr>'
                    . '<w:t xml:space="preserve">' . $text . '</w:t>'
                    . '</w:r>'
                    . '</w:p>';
            })
            ->implode('');

        $processor->replaceXmlBlock('applicants_list', $paragraphs, 'w:p');

        $tmp = tempnam(sys_get_temp_dir(), 'telex_') . '.docx';
        $processor->saveAs($tmp);

        $filename = 'telex_' . now()->format('Ymd_His') . '.docx';

        return response()->download($tmp, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Talabaga viza ariza holatining yangilanishi haqida Telegram orqali xabar
     * yuboradi. Talaba telegramni bog'lamagan bo'lsa, jimgina o'tib ketadi.
     */
    private function notifyStudent(VisaApplication $app): void
    {
        $student = null;
        if ($app->student_id) {
            $student = Student::find($app->student_id);
        }
        if (!$student && $app->student_hemis_id) {
            $student = Student::where('hemis_id', $app->student_hemis_id)->first();
        }
        if (!$student || !$student->telegram_chat_id) {
            return;
        }

        $statusLabel = match ($app->status) {
            'pending'   => "⏳ Kutilmoqda",
            'reviewing' => "👀 Ko'rilmoqda",
            'approved'  => "✅ Qabul qilindi",
            'rejected'  => "❌ Rad etildi",
            default     => $app->status,
        };

        $msg = "<b>Viza arizangiz holati yangilandi</b>\n\n"
            . "Ariza №: <b>{$app->application_number}</b>\n"
            . "Yangi holat: <b>{$statusLabel}</b>";

        if ($app->admin_note) {
            $note = htmlspecialchars($app->admin_note, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $msg .= "\n\n<b>Izoh:</b>\n{$note}";
        }

        try {
            app(TelegramService::class)->sendToUser((string) $student->telegram_chat_id, $msg);
        } catch (\Throwable $e) {
            Log::warning('Visa notify failed: ' . $e->getMessage(), [
                'application_id' => $app->id,
                'student_id'     => $student->id,
            ]);
        }
    }

    private function internationalStudentsQuery()
    {
        return Student::query()->where(function ($q) {
            $q->where('group_name', 'like', 'xd%')
                ->orWhere('citizenship_name', 'like', '%orijiy%');
        });
    }

    private function zipSafeName(string $value, string $fallback): string
    {
        $value = preg_replace('/[\\\\\/:*?"<>|]+/u', ' ', $value);
        $value = preg_replace('/\s+/u', ' ', trim((string) $value));

        return $value !== '' ? $value : $fallback;
    }

    private function uniqueZipFileName(string $filename, array &$counts): string
    {
        $counts[$filename] = ($counts[$filename] ?? 0) + 1;

        if ($counts[$filename] === 1) {
            return $filename;
        }

        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        $name = pathinfo($filename, PATHINFO_FILENAME);

        return $name . ' (' . $counts[$filename] . ')' . ($ext !== '' ? '.' . $ext : '');
    }
}
