<?php

namespace App\Exports;

use Box\Spout\Writer\Common\Creator\WriterEntityFactory;
use App\Models\Teacher;
use Illuminate\Http\Request;

class TeacherExport
{
    protected $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function export()
    {
        $writer = WriterEntityFactory::createXLSXWriter();
        $fileName = 'xodimlar_' . date('Y_m_d_H_i_s') . '.xlsx';
        $writer->openToBrowser($fileName);

        $headingRow = WriterEntityFactory::createRowFromArray($this->headings());
        $writer->addRow($headingRow);

        $query = $this->buildQuery();

        $index = 1;
        foreach ($query->cursor() as $teacher) {
            $roles = $teacher->roles->pluck('name')->map(function ($name) {
                return \App\Enums\ProjectRole::tryFrom($name)?->label() ?? $name;
            })->implode(', ');

            $rowData = [
                $index++,
                $teacher->employee_id_number,
                $teacher->full_name,
                $teacher->birth_date ? format_date($teacher->birth_date) : '',
                $teacher->gender ?? '',
                $teacher->department ?? '',
                $teacher->staff_position ?? '',
                $teacher->employment_form ?? '',
                $teacher->employee_type ?? '',
                $teacher->employee_status ?? '',
                $teacher->phone ?? '',
                $teacher->telegram_username ?? '',
                $teacher->telegram_verified_at ? 'Ha' : 'Yo\'q',
                $roles,
                $teacher->status ? 'Faol' : 'Nofaol',
                $teacher->is_active ? 'Aktiv' : 'Noaktiv',
                $teacher->contract_number ?? '',
                $teacher->contract_date ? format_date($teacher->contract_date) : '',
                $teacher->decree_number ?? '',
                $teacher->decree_date ? format_date($teacher->decree_date) : '',
            ];

            $dataRow = WriterEntityFactory::createRowFromArray($rowData);
            $writer->addRow($dataRow);
        }

        $writer->close();
    }

    private function buildQuery()
    {
        $query = Teacher::with('roles');

        if ($this->request->filled('search')) {
            $searchTerm = $this->request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('full_name', 'like', "%{$searchTerm}%")
                    ->orWhere('employee_id_number', 'like', "%{$searchTerm}%")
                    ->orWhere('department', 'like', "%{$searchTerm}%");
            });
        }

        if ($this->request->filled('department')) {
            $query->where('department', $this->request->department);
        }

        if ($this->request->filled('staff_position')) {
            $query->where('staff_position', $this->request->staff_position);
        }

        if ($this->request->filled('role')) {
            $roleName = $this->request->role;
            $query->whereHas('roles', fn ($q) => $q->where('name', $roleName));
        }

        if ($this->request->filled('status')) {
            $query->where('status', $this->request->status);
        }

        if ($this->request->filled('is_active')) {
            $query->where('is_active', $this->request->is_active);
        } else {
            $query->where('is_active', true);
        }

        return $query->orderBy('full_name');
    }

    private function headings(): array
    {
        return [
            '№',
            'ID raqami',
            'F.I.O',
            'Tug\'ilgan sana',
            'Jinsi',
            'Kafedra',
            'Lavozim',
            'Ish turi',
            'Xodim turi',
            'Holati',
            'Telefon',
            'Telegram',
            'Telegram tasdiqlangan',
            'Rollar',
            'Status',
            'Faollik',
            'Shartnoma raqami',
            'Shartnoma sanasi',
            'Buyruq raqami',
            'Buyruq sanasi',
        ];
    }
}
