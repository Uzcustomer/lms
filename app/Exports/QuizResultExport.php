<?php

namespace App\Exports;

use App\Models\HemisQuizResult;
use Box\Spout\Writer\Common\Creator\WriterEntityFactory;
use Illuminate\Http\Request;

class QuizResultExport
{
    protected $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function export()
    {
        $writer = WriterEntityFactory::createXLSXWriter();
        $fileName = 'quiz_natijalar_' . date('Y_m_d_H_i_s') . '.xlsx';
        $writer->openToBrowser($fileName);

        $headingRow = WriterEntityFactory::createRowFromArray($this->headings());
        $writer->addRow($headingRow);

        $query = $this->buildQuery();

        $index = 1;
        foreach ($query->cursor() as $result) {
            $rowData = [
                $index++,
                $result->attempt_id,
                $result->student_id,
                $result->student_name,
                $result->faculty,
                $result->direction,
                $result->semester,
                $result->fan_id,
                $result->fan_name,
                $result->quiz_type,
                $result->attempt_name,
                $result->shakl,
                $result->grade,
                $result->old_grade,
                $result->date_start ? $result->date_start->format('Y-m-d H:i') : '',
                $result->date_finish ? $result->date_finish->format('Y-m-d H:i') : '',
            ];

            $dataRow = WriterEntityFactory::createRowFromArray($rowData);
            $writer->addRow($dataRow);
        }

        $writer->close();
    }

    private function buildQuery()
    {
        $query = HemisQuizResult::where('is_active', 1);

        if ($this->request->filled('faculty')) {
            $query->where('faculty', $this->request->faculty);
        }

        if ($this->request->filled('direction')) {
            $query->where('direction', $this->request->direction);
        }

        if ($this->request->filled('semester')) {
            $query->where('semester', $this->request->semester);
        }

        if ($this->request->filled('fan_name')) {
            $query->where('fan_name', 'LIKE', '%' . $this->request->fan_name . '%');
        }

        if ($this->request->filled('student_name')) {
            $query->where('student_name', 'LIKE', '%' . $this->request->student_name . '%');
        }

        if ($this->request->filled('student_id')) {
            $query->where('student_id', $this->request->student_id);
        }

        if ($this->request->filled('quiz_type')) {
            $query->where('quiz_type', $this->request->quiz_type);
        }

        if ($this->request->filled('date_from')) {
            $query->whereDate('date_finish', '>=', $this->request->date_from);
        }

        if ($this->request->filled('date_to')) {
            $query->whereDate('date_finish', '<=', $this->request->date_to);
        }

        return $query->orderByDesc('date_finish');
    }

    private function headings(): array
    {
        return [
            '№',
            'attempt_id',
            'student_id',
            'student_name',
            'faculty',
            'direction',
            'semester',
            'fan_id',
            'fan_name',
            'quiz_type',
            'attempt_name',
            'shakl',
            'grade',
            'old_grade',
            'date_start',
            'date_finish',
        ];
    }
}
