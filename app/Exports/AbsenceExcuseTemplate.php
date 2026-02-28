<?php

namespace App\Exports;

use App\Models\AbsenceExcuse;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AbsenceExcuseTemplate implements FromArray, WithHeadings, WithStyles, WithEvents
{
    public function headings(): array
    {
        return [
            'Talaba HEMIS ID',
            'Sabab',
            'Boshlanish sanasi',
            'Tugash sanasi',
            'Hujjat raqami',
            'Izoh',
            'Holat',
        ];
    }

    public function array(): array
    {
        return [
            ['11001234567890', 'kasallik', '01.02.2026', '10.02.2026', 'AE-001', 'Kasallik malumotnomasi bor', 'approved'],
            ['11009876543210', 'nikoh_toyi', '15.02.2026', '20.02.2026', 'AE-002', '', 'approved'],
            ['11005555666677', 'yaqin_qarindosh', '05.02.2026', '08.02.2026', '', 'Yaqin qarindosh vafoti', 'pending'],
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->getColumnDimension('A')->setWidth(20);
        $sheet->getColumnDimension('B')->setWidth(22);
        $sheet->getColumnDimension('C')->setWidth(18);
        $sheet->getColumnDimension('D')->setWidth(18);
        $sheet->getColumnDimension('E')->setWidth(16);
        $sheet->getColumnDimension('F')->setWidth(35);
        $sheet->getColumnDimension('G')->setWidth(14);

        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FF4472C4'],
                ],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // Sabab ustuniga dropdown qo'shish (B2:B1000)
                $reasonKeys = implode(',', array_keys(AbsenceExcuse::REASONS));
                $validation = $sheet->getCell('B2')->getDataValidation();
                $validation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
                $validation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_INFORMATION);
                $validation->setAllowBlank(false);
                $validation->setShowInputMessage(true);
                $validation->setShowErrorMessage(true);
                $validation->setShowDropDown(true);
                $validation->setFormula1('"' . $reasonKeys . '"');
                $validation->setPromptTitle('Sabab tanlang');
                $validation->setPrompt('kasallik, tibbiy_korik, nikoh_toyi, yaqin_qarindosh, homiladorlik, musobaqa_tadbir, tabiiy_ofat, xorijlik_viza');

                // Holat ustuniga dropdown qo'shish (G2:G1000)
                $statusValidation = $sheet->getCell('G2')->getDataValidation();
                $statusValidation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
                $statusValidation->setFormula1('"approved,pending,rejected"');
                $statusValidation->setShowDropDown(true);

                // Validatsiyani ko'p qatorlarga tarqatish
                for ($i = 3; $i <= 500; $i++) {
                    $sheet->getCell("B{$i}")->setDataValidation(clone $validation);
                    $sheet->getCell("G{$i}")->setDataValidation(clone $statusValidation);
                }

                // Izoh qo'shish (2-sheet)
                $spreadsheet = $sheet->getParent();
                $helpSheet = $spreadsheet->createSheet();
                $helpSheet->setTitle('Yo\'riqnoma');

                $helpSheet->setCellValue('A1', 'Ustun');
                $helpSheet->setCellValue('B1', 'Tavsif');
                $helpSheet->setCellValue('C1', 'Majburiy');

                $help = [
                    ['Talaba HEMIS ID', 'Talabaning HEMIS tizimdagi ID raqami (14 xonali)', 'Ha'],
                    ['Sabab', 'Sababli dars qoldirish asosi (quyidagi ro\'yxatdan)', 'Ha'],
                    ['Boshlanish sanasi', 'Format: KK.OO.YYYY (masalan: 01.02.2026)', 'Ha'],
                    ['Tugash sanasi', 'Format: KK.OO.YYYY (masalan: 10.02.2026)', 'Ha'],
                    ['Hujjat raqami', 'Ariza/buyruq raqami', 'Yo\'q'],
                    ['Izoh', 'Qo\'shimcha izoh', 'Yo\'q'],
                    ['Holat', 'approved / pending / rejected (standart: approved)', 'Yo\'q'],
                ];

                $row = 2;
                foreach ($help as $h) {
                    $helpSheet->setCellValue("A{$row}", $h[0]);
                    $helpSheet->setCellValue("B{$row}", $h[1]);
                    $helpSheet->setCellValue("C{$row}", $h[2]);
                    $row++;
                }

                // Sabablar ro'yxati
                $row += 1;
                $helpSheet->setCellValue("A{$row}", 'Sabab kodi');
                $helpSheet->setCellValue("B{$row}", 'Tavsifi');
                $helpSheet->getStyle("A{$row}:B{$row}")->getFont()->setBold(true);
                $row++;

                foreach (AbsenceExcuse::REASONS as $key => $data) {
                    $helpSheet->setCellValue("A{$row}", $key);
                    $helpSheet->setCellValue("B{$row}", $data['label']);
                    $row++;
                }

                $helpSheet->getColumnDimension('A')->setWidth(22);
                $helpSheet->getColumnDimension('B')->setWidth(60);
                $helpSheet->getColumnDimension('C')->setWidth(12);
                $helpSheet->getStyle('A1:C1')->getFont()->setBold(true);
            },
        ];
    }
}
