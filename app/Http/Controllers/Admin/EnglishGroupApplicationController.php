<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InglizGuruhAriza;
use App\Models\Student;
use App\Models\StudentNotification;
use App\Services\TelegramService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class EnglishGroupApplicationController extends Controller
{
    public function index(Request $request)
    {
        $query = InglizGuruhAriza::query()->latest();
        $statusFilter = $request->query('status');

        if ($statusFilter === null || $statusFilter === '') {
            $query->where('status', 'pending');
        } elseif ($statusFilter !== 'all') {
            $query->where('status', $statusFilter);
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('student_hemis_id', 'like', "%{$search}%")
                    ->orWhere('group_name', 'like', "%{$search}%")
                    ->orWhere('faculty_name', 'like', "%{$search}%")
                    ->orWhere('specialty_name', 'like', "%{$search}%")
                    ->orWhere('phone_number', 'like', "%{$search}%");
            });
        }

        if ($request->filled('english_level')) {
            $query->where('english_level', $request->english_level);
        }

        $applications = $query->paginate(20)->withQueryString();

        $stats = [
            'pending' => InglizGuruhAriza::where('status', 'pending')->count(),
            'approved' => InglizGuruhAriza::where('status', 'approved')->count(),
            'rejected' => InglizGuruhAriza::where('status', 'rejected')->count(),
            'total' => InglizGuruhAriza::count(),
        ];

        $englishLevels = [
            'boshlangich' => "Boshlang'ich",
            'orta' => "O'rta",
            'mukammal' => 'Mukammal',
        ];

        return view('admin.english-group-applications.index', compact('applications', 'stats', 'englishLevels'));
    }

    public function approve(int $id)
    {
        $application = InglizGuruhAriza::findOrFail($id);
        $application->update([
            'status' => 'approved',
            'rejection_reason_code' => null,
            'admin_note' => null,
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);

        $this->notifyStudent($application, 'approved');

        return redirect()->route('admin.english-group-applications.index', request()->query())
            ->with('success', 'Ariza qabul qilindi.');
    }

    public function reject(Request $request, int $id)
    {
        $data = $request->validate([
            'rejection_reason_code' => 'nullable|in:interview_failed',
            'admin_note' => 'nullable|string|max:1000|required_without:rejection_reason_code',
        ], [
            'rejection_reason_code.in' => "Noto'g'ri rad etish sababi tanlandi.",
            'admin_note.required_without' => 'Rad etish uchun sabab yoki izoh kiritilishi shart.',
            'admin_note.max' => 'Izoh juda uzun.',
        ]);

        $application = InglizGuruhAriza::findOrFail($id);
        $application->update([
            'status' => 'rejected',
            'rejection_reason_code' => $data['rejection_reason_code'] ?? null,
            'admin_note' => $data['admin_note'] ?? null,
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);

        $this->notifyStudent($application, 'rejected');

        return redirect()->route('admin.english-group-applications.index', request()->query())
            ->with('success', 'Ariza rad etildi.');
    }

    public function export(Request $request)
    {
        $applications = InglizGuruhAriza::query()->latest('created_at')->get();

        $levels = [
            'boshlangich' => "Boshlang'ich",
            'orta' => "O'rta",
            'mukammal' => 'Mukammal',
        ];
        $statuses = [
            'pending' => 'Kutilmoqda',
            'approved' => 'Qabul qilingan',
            'rejected' => 'Rad etilgan',
        ];

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Arizalar');

        $headers = [
            'ID', 'F.I.Sh.', 'HEMIS ID', 'Telefon', 'Fakultet', 'Yo\'nalish',
            'Kurs', 'Semestr', 'Guruh', 'Ingliz tili darajasi', 'Holat',
            'Rad etish sababi', 'Admin izohi', 'Sertifikat fayli', 'Ariza sanasi', 'Ko\'rib chiqilgan sana',
        ];
        $sheet->fromArray($headers, null, 'A1');

        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '2563EB']],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
        ];
        $sheet->getStyle('A1:P1')->applyFromArray($headerStyle);
        $sheet->getRowDimension(1)->setRowHeight(30);

        $row = 2;
        foreach ($applications as $application) {
            $sheet->fromArray([
                $application->id,
                $application->full_name,
                $application->student_hemis_id ?: '',
                $application->phone_number ?: '',
                $application->faculty_name ?: '',
                $application->specialty_name ?: '',
                $application->course_name ?: '',
                $application->semester_name ?: '',
                $application->group_name ?: '',
                $levels[$application->english_level] ?? ($application->english_level ?: ''),
                $statuses[$application->status] ?? $application->status,
                $application->rejection_reason_label ?: '',
                $application->admin_note ?: '',
                $application->certificate_pdf_path ? basename($application->certificate_pdf_path) : '',
                optional($application->created_at)->format('d.m.Y H:i'),
                optional($application->reviewed_at)->format('d.m.Y H:i'),
            ], null, "A{$row}");

            $fill = match ($application->status) {
                'approved' => 'DCFCE7',
                'rejected' => 'FEE2E2',
                default => 'FEF3C7',
            };
            $sheet->getStyle("A{$row}:O{$row}")->applyFromArray([
                'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => $fill]],
            ]);
            $row++;
        }

        $lastRow = max(1, $row - 1);
        $sheet->getStyle("A1:P{$lastRow}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['rgb' => 'CBD5E1'],
                ],
            ],
            'alignment' => ['vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER],
        ]);
        foreach ([
            'A' => 8, 'B' => 28, 'C' => 16, 'D' => 16, 'E' => 24, 'F' => 28,
            'G' => 16, 'H' => 16, 'I' => 18, 'J' => 20, 'K' => 18, 'L' => 22,
            'M' => 32, 'N' => 24, 'O' => 20, 'P' => 20,
        ] as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }
        $sheet->freezePane('A2');
        $sheet->setAutoFilter("A1:P{$lastRow}");

        $fileName = 'ingliz-guruh-arizalari-' . now()->format('Y-m-d_H-i') . '.xlsx';
        $temp = tempnam(sys_get_temp_dir(), 'english_applications_');
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($temp);
        $spreadsheet->disconnectWorksheets();

        return response()->download($temp, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    public function documentsZip(Request $request)
    {
        $applications = InglizGuruhAriza::query()
            ->whereNotNull('certificate_pdf_path')
            ->where('certificate_pdf_path', '!=', '')
            ->latest('created_at')
            ->get();

        $zip = new \ZipArchive();
        $temp = tempnam(sys_get_temp_dir(), 'english_certificates_');
        if ($zip->open($temp, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            abort(500, 'ZIP fayl yaratib bo\'lmadi.');
        }

        $usedNames = [];
        foreach ($applications as $application) {
            if (!Storage::exists($application->certificate_pdf_path)) {
                continue;
            }

            $baseName = trim((string) $application->full_name) ?: ('ariza-' . $application->id);
            $baseName = preg_replace('/[\\\\\\/:*?"<>|]+/u', ' ', $baseName);
            $baseName = preg_replace('/\\s+/u', ' ', trim($baseName));
            $baseName = $baseName ?: ('ariza-' . $application->id);
            $fileName = $baseName . '.pdf';

            if (isset($usedNames[$fileName])) {
                $usedNames[$fileName]++;
                $fileName = $baseName . ' (' . $usedNames[$baseName . '.pdf'] . ').pdf';
            } else {
                $usedNames[$fileName] = 1;
            }

            $zip->addFile(Storage::path($application->certificate_pdf_path), $fileName);
        }

        $zip->close();
        $fileName = 'ingliz-guruh-sertifikatlari-' . now()->format('Y-m-d_H-i') . '.zip';

        return response()->download($temp, $fileName, [
            'Content-Type' => 'application/zip',
        ])->deleteFileAfterSend(true);
    }

    public function certificate(int $id)
    {
        $application = InglizGuruhAriza::findOrFail($id);
        abort_if(!$application->certificate_pdf_path || !Storage::exists($application->certificate_pdf_path), 404);

        return response()->file(Storage::path($application->certificate_pdf_path), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="til_sertifikati.pdf"',
        ]);
    }

    public function destroy(int $id)
    {
        $application = InglizGuruhAriza::findOrFail($id);

        if ($application->certificate_pdf_path) {
            Storage::delete($application->certificate_pdf_path);

            $dir = dirname($application->certificate_pdf_path);
            if ($dir && $dir !== '.') {
                Storage::deleteDirectory($dir);
            }
        }

        $application->delete();

        return redirect()->route('admin.english-group-applications.index', request()->query())
            ->with('success', "Ariza va unga biriktirilgan fayl o'chirildi.");
    }

    private function notifyStudent(InglizGuruhAriza $application, string $event): void
    {
        $student = null;
        if ($application->student_id) {
            $student = Student::find($application->student_id);
        }
        if (!$student && $application->student_hemis_id) {
            $student = Student::where('hemis_id', $application->student_hemis_id)->first();
        }
        if (!$student) {
            return;
        }

        $title = match ($event) {
            'approved' => "Ingliz tili guruhiga o'tish arizasi qabul qilindi",
            'rejected' => "Ingliz tili guruhiga o'tish arizasi rad etildi",
            default => "Ingliz tili guruhiga o'tish arizasi",
        };

        $message = match ($event) {
            'approved' => "Arizangiz admin tomonidan qabul qilindi.",
            'rejected' => "Arizangiz admin tomonidan rad etildi.",
            default => "Arizangiz bo'yicha holat yangilandi.",
        };

        if ($application->rejection_reason_label) {
            $message .= " Sabab: {$application->rejection_reason_label}.";
        }
        if ($application->admin_note) {
            $message .= " Izoh: {$application->admin_note}";
        }

        StudentNotification::create([
            'student_id' => $student->id,
            'type' => 'english_group_application',
            'title' => $title,
            'message' => $message,
            'link' => '/student/english-group-application',
            'data' => [
                'application_id' => $application->id,
                'status' => $application->status,
            ],
        ]);

        if (!empty($student->telegram_chat_id)) {
            try {
                app(TelegramService::class)->sendToUser(
                    (string) $student->telegram_chat_id,
                    "<b>{$title}</b>\n\n" . e($message)
                );
            } catch (\Throwable $e) {
                Log::warning('English group application telegram notify failed: ' . $e->getMessage(), [
                    'application_id' => $application->id,
                    'student_id' => $student->id,
                ]);
            }
        }
    }
}
