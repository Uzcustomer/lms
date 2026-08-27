<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;

class StudentDistributionController extends Controller
{
    private const MAX_DISPLAY_ROWS = 10000;

    public function index()
    {
        return view('admin.student-distribution.index', [
            'headers' => [], 'rows' => [], 'sheetName' => null, 'fileName' => null,
            'totalRows' => 0, 'truncated' => false,
        ]);
    }

    public function upload(Request $request)
    {
        $request->validate([
            'student_file' => 'required|file|mimes:xlsx,xls,csv,txt|max:20480',
        ], [
            'student_file.required' => 'Excel faylni tanlang.',
            'student_file.file' => 'Yuklangan faylni o\'qib bo\'lmadi.',
            'student_file.mimes' => 'Faqat XLSX, XLS yoki CSV fayl yuklang.',
            'student_file.max' => 'Fayl hajmi 20 MB dan oshmasligi kerak.',
        ]);

        $file = $request->file('student_file');

        try {
            $reader = IOFactory::createReaderForFile($file->getRealPath());
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($file->getRealPath());
            $sheet = $spreadsheet->getSheet(0);
            $rawRows = $sheet->toArray(null, true, true, true);
            $nonEmptyRows = array_values(array_filter($rawRows, function ($row) {
                foreach ($row as $value) {
                    if ($this->cellText($value) !== '') return true;
                }
                return false;
            }));

            if (!$nonEmptyRows) {
                return back()->withInput()->withErrors(['student_file' => 'Excel faylda to\'ldirilgan qator topilmadi.']);
            }

            $headers = array_values(array_shift($nonEmptyRows));
            $columnCount = count($headers);
            foreach ($nonEmptyRows as $row) $columnCount = max($columnCount, count($row));
            $headers = $this->normalizeRow($headers, $columnCount, true);

            $totalRows = count($nonEmptyRows);
            $truncated = $totalRows > self::MAX_DISPLAY_ROWS;
            $rows = array_map(
                fn ($row) => $this->normalizeRow($row, $columnCount),
                array_slice($nonEmptyRows, 0, self::MAX_DISPLAY_ROWS)
            );

            return view('admin.student-distribution.index', compact(
                'headers', 'rows', 'totalRows', 'truncated'
            ) + [
                'sheetName' => $sheet->getTitle(),
                'fileName' => $file->getClientOriginalName(),
            ]);
        } catch (\Throwable $e) {
            report($e);
            return back()->withInput()->withErrors(['student_file' => 'Excel faylni o\'qishda xatolik yuz berdi. Fayl formatini tekshiring.']);
        }
    }

    private function normalizeRow(array $row, int $columnCount, bool $header = false): array
    {
        $values = array_values($row);
        $normalized = [];
        for ($index = 0; $index < $columnCount; $index++) {
            $value = $this->cellText($values[$index] ?? '');
            $normalized[] = $value !== '' || !$header ? $value : 'Ustun ' . ($index + 1);
        }
        return $normalized;
    }

    private function cellText($value): string
    {
        if ($value instanceof \DateTimeInterface) return $value->format('d.m.Y H:i');
        return trim((string) $value);
    }
}
