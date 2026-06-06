<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\StudentSurveyAnswer;
use App\Models\StudentSurveyCompletion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SurveyController extends Controller
{
    /**
     * So'rovnoma modalini ko'rsatish — talaba "So'rovnoma" tugmasini bosganda
     * yoki muddat tugagandan keyin majburiy holatda.
     */
    public function show()
    {
        $student = auth('student')->user();
        if (!$student) abort(401);

        // Admin survey'ni o'chirib qo'ygan bo'lsa, dashboard'ga yo'naltiramiz
        if (!\App\Http\Controllers\Admin\StudentSurveyController::isActive()) {
            return redirect()->route('student.dashboard');
        }

        $config = config('student_survey');

        // Allaqachon bajarganmi?
        $alreadyCompleted = StudentSurveyCompletion::where('survey_key', $config['key'])
            ->where('student_hemis_id', $student->hemis_id)
            ->exists();

        $config['deadline'] = \App\Http\Controllers\Admin\StudentSurveyController::currentDeadline();
        $deadlinePassed = strtotime($config['deadline']) < time();

        return view('student.survey.show', [
            'survey'           => $config,
            'student'          => $student,
            'alreadyCompleted' => $alreadyCompleted,
            'deadlinePassed'   => $deadlinePassed,
        ]);
    }

    /**
     * Submit qilish: javoblarni DB ga + CSV faylga yozish, completion belgilash.
     */
    public function submit(Request $request)
    {
        $student = auth('student')->user();
        if (!$student) abort(401);

        if (!\App\Http\Controllers\Admin\StudentSurveyController::isActive()) {
            return response()->json([
                'success' => false,
                'message' => "So'rovnoma hozircha faol emas.",
            ], 403);
        }

        $config = config('student_survey');
        $surveyKey = $config['key'];

        // Allaqachon bajargan bo'lsa — qaytarmaymiz
        $alreadyCompleted = StudentSurveyCompletion::where('survey_key', $surveyKey)
            ->where('student_hemis_id', $student->hemis_id)
            ->exists();
        if ($alreadyCompleted) {
            return response()->json([
                'success' => false,
                'message' => "Siz bu so'rovnomani allaqachon bajargansiz.",
            ], 409);
        }

        $answersInput = $request->input('answers', []);
        if (!is_array($answersInput) || empty($answersInput)) {
            return response()->json([
                'success' => false,
                'message' => "Javoblar yuborilmadi.",
            ], 422);
        }

        // Server tomondan ham majburiy savollarni tekshirish.
        // Conditional savollar — shart bajarilmasa, javob kerak emas.
        $errors = $this->validateAnswers($config['questions'], $answersInput);
        if (!empty($errors)) {
            return response()->json([
                'success' => false,
                'message' => "Quyidagi savollarga javob to'liq emas: " . implode(', ', $errors),
                'errors'  => $errors,
            ], 422);
        }

        $sessionToken = (string) Str::uuid();

        try {
            DB::transaction(function () use ($surveyKey, $sessionToken, $answersInput, $student, $config) {
                foreach ($answersInput as $qid => $ans) {
                    if (is_array($ans)) {
                        StudentSurveyAnswer::create([
                            'survey_key'       => $surveyKey,
                            'student_hemis_id' => $student->hemis_id,
                            'session_token'    => $sessionToken,
                            'question_id'      => (string) $qid,
                            'answer'           => null,
                            'answer_multi'     => array_values($ans),
                        ]);
                    } else {
                        StudentSurveyAnswer::create([
                            'survey_key'       => $surveyKey,
                            'student_hemis_id' => $student->hemis_id,
                            'session_token'    => $sessionToken,
                            'question_id'      => (string) $qid,
                            'answer'           => (string) $ans,
                            'answer_multi'     => null,
                        ]);
                    }
                }

                StudentSurveyCompletion::create([
                    'survey_key'      => $surveyKey,
                    'student_hemis_id' => $student->hemis_id,
                    'completed_at'    => now(),
                ]);

                // XLSX ga yozish — bitta qator, ustunlar: talaba ma'lumotlari + har savol matnli javobi
                $this->appendToXlsx($config, $student, $answersInput);
            });
        } catch (\Throwable $e) {
            Log::error('Student survey submit failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => "Saqlashda xatolik. Iltimos qaytadan urinib ko'ring.",
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => "Rahmat! Javoblaringiz qabul qilindi.",
        ]);
    }

    /**
     * Server tomondan tekshirish: majburiy savollar javobli bo'lsin, conditional
     * savollar shart bajarilsa javob talab qilinsin, "Boshqa" tanlangan bo'lsa
     * matn bo'sh bo'lmasin.
     *
     * @return string[] xato savol ID lar ro'yxati
     */
    private function validateAnswers(array $questions, array $answers): array
    {
        $errors = [];
        foreach ($questions as $q) {
            $qid = $q['id'];

            // Conditional — shart bajarilmasa, savol kerak emas
            if (!empty($q['show_if'])) {
                $parentId  = $q['show_if']['question_id'];
                $whenOpt   = $q['show_if']['when_option'];
                $parentAns = $answers[$parentId] ?? null;
                if ($parentAns === null) {
                    continue; // ota savolga javob yo'q → bu savol ham talab qilinmaydi
                }
                // Parent radio bo'lsa — string, checkbox bo'lsa — massiv. Faqat radio
                // bilan ishlashga moslangan (config'da "show_if" faqat radio'da)
                if (!is_string($parentAns) || strpos($parentAns, $whenOpt) !== 0) {
                    continue;
                }
            }

            $required = $q['required'] ?? true;
            if (!$required) continue;

            $val = $answers[$qid] ?? null;
            if ($q['type'] === 'text') {
                if (!is_string($val) || trim($val) === '') {
                    $errors[] = $qid;
                }
                continue;
            }
            if ($q['type'] === 'radio') {
                if (!is_string($val) || trim($val) === '') {
                    $errors[] = $qid;
                    continue;
                }
                // "Boshqa" bo'lsa, matn ham bo'sh bo'lmasin (format: "other:Matn")
                if (strpos($val, 'other:') === 0) {
                    $otherText = trim(substr($val, 6));
                    if ($otherText === '') $errors[] = $qid;
                }
            } elseif ($q['type'] === 'checkbox') {
                if (!is_array($val) || count($val) === 0) {
                    $errors[] = $qid;
                    continue;
                }
                foreach ($val as $v) {
                    if (is_string($v) && strpos($v, 'other:') === 0) {
                        $otherText = trim(substr($v, 6));
                        if ($otherText === '') {
                            $errors[] = $qid;
                            break;
                        }
                    }
                }
            }
        }
        return $errors;
    }

    /**
     * Bitta talabaning javobini XLSX ga qator sifatida qo'shish.
     * Fayl: storage/app/surveys/{survey_key}.xlsx
     *
     * Birinchi qator — stillangan sarlavha (rangli fon, oq matn, balandroq).
     * Mavjud bo'lsa yuklanadi, oxiriga qator qo'shiladi, qayta saqlanadi.
     * Parallel yozishlar uchun alohida lock fayl orqali flock ishlatiladi.
     */
    private function appendToXlsx(array $config, $student, array $answers): void
    {
        $dir = storage_path('app/surveys');
        if (!is_dir($dir)) {
            File::makeDirectory($dir, 0755, true);
        }
        $path = $dir . '/' . $config['key'] . '.xlsx';
        $lockPath = $path . '.lock';

        $headers = ['Talaba ID', 'F.I.SH', 'Fakultet', "Yo'nalish", 'Kurs', 'Guruh', 'Semestr'];
        foreach ($config['questions'] as $q) {
            $headers[] = ($q['id']) . '. ' . $q['text'];
        }

        $row = [
            (string) ($student->student_id_number ?? ''),
            $student->full_name ?? '',
            $student->department_name ?? '',
            $student->specialty_name ?? '',
            $student->level_name ?? ($student->level_code ?? ''),
            $student->group_name ?? '',
            $student->semester_name ?? ($student->semester_code ?? ''),
        ];
        foreach ($config['questions'] as $q) {
            $row[] = $this->formatAnswerForCsv($q, $answers[$q['id']] ?? null);
        }

        $lockFp = fopen($lockPath, 'c');
        if (!$lockFp) return;
        if (!flock($lockFp, LOCK_EX)) {
            fclose($lockFp);
            return;
        }

        try {
            if (file_exists($path)) {
                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
                $sheet = $spreadsheet->getActiveSheet();
                $nextRow = $sheet->getHighestDataRow() + 1;
            } else {
                $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
                $sheet = $spreadsheet->getActiveSheet();
                $sheet->setTitle('Javoblar');
                $this->writeXlsxHeader($sheet, $headers);
                $nextRow = 2;
            }

            // Talaba ID — matn sifatida (juda uzun raqamlar uchun)
            $sheet->setCellValueExplicit('A' . $nextRow, $row[0], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $col = 1;
            foreach (array_slice($row, 1) as $val) {
                $sheet->setCellValueByColumnAndRow($col + 1, $nextRow, $val);
                $col++;
            }
            // Data qatori uchun yuqori cherchirov + matn wrap
            $sheet->getRowDimension($nextRow)->setRowHeight(28);
            $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));
            $sheet->getStyle("A{$nextRow}:{$lastCol}{$nextRow}")->getAlignment()
                ->setWrapText(true)
                ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);

            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save($path);

            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        } finally {
            flock($lockFp, LOCK_UN);
            fclose($lockFp);
        }
    }

    /**
     * Birinchi qatorni yozish + stil: rangli fon, oq qalin matn, balandroq qator,
     * birinchi qator yuqorida muzlatish, ustun kengligi auto.
     */
    private function writeXlsxHeader($sheet, array $headers): void
    {
        foreach ($headers as $i => $h) {
            $sheet->setCellValueByColumnAndRow($i + 1, 1, $h);
        }

        $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));
        $range = "A1:{$lastCol}1";

        $sheet->getStyle($range)->applyFromArray([
            'font' => [
                'bold'  => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size'  => 11,
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4F46E5'],
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical'   => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                'wrapText'   => true,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color'       => ['rgb' => '3730A3'],
                ],
            ],
        ]);

        $sheet->getRowDimension(1)->setRowHeight(56);
        $sheet->freezePane('A2');

        // Birinchi 7 ustun (talaba ma'lumotlari) — maqbul kenglik
        $fixedWidths = ['A' => 16, 'B' => 32, 'C' => 22, 'D' => 22, 'E' => 10, 'F' => 14, 'G' => 14];
        foreach ($fixedWidths as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }
        // Qolgan savol ustunlari — auto-fit talab qilinmasin, qo'lda 42
        for ($i = 8; $i <= count($headers); $i++) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
            $sheet->getColumnDimension($col)->setWidth(42);
        }
    }

    /**
     * Javobni CSV uchun inson o'qiy oladigan matnga aylantirish:
     * - radio: variant matni yoki "Boshqa: <foydalanuvchi matni>"
     * - checkbox: vergul bilan ajratilgan variantlar
     */
    private function formatAnswerForCsv(array $question, $value): string
    {
        if ($value === null || $value === '') return '';

        // Erkin matnli savol — qiymatni o'zicha qaytaramiz
        if (($question['type'] ?? '') === 'text') {
            return is_string($value) ? trim($value) : '';
        }

        $optionsById = [];
        foreach ($question['options'] ?? [] as $opt) {
            $optionsById[$opt['id']] = $opt;
        }

        $resolve = function ($v) use ($optionsById) {
            // "Boshqa: matn" formatida bo'lsa
            if (is_string($v) && strpos($v, 'other:') === 0) {
                $txt = trim(substr($v, 6));
                return "Boshqa: " . $txt;
            }
            if (is_string($v) && isset($optionsById[$v])) {
                return $optionsById[$v]['text'];
            }
            return (string) $v;
        };

        if (is_array($value)) {
            return implode(' | ', array_map($resolve, $value));
        }
        return $resolve($value);
    }
}
