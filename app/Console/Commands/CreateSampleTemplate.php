<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\Style\Font;

class CreateSampleTemplate extends Command
{
    protected $signature = 'template:create-sample';
    protected $description = 'Sababli ariza uchun namuna Word shablon yaratish';

    public function handle()
    {
        $phpWord = new PhpWord();

        // Shrift sozlamalari
        $phpWord->setDefaultFontName('Times New Roman');
        $phpWord->setDefaultFontSize(14);

        // Sahifa
        $section = $phpWord->addSection([
            'marginTop' => 800,
            'marginBottom' => 1200,
            'marginLeft' => 1200,
            'marginRight' => 800,
        ]);

        // Gerb joy (rasm qo'yish uchun bo'sh joy)
        $section->addText('', [], ['alignment' => Jc::CENTER]);

        // Universitet nomi
        $section->addText(
            "O'ZBEKISTON RESPUBLIKASI SOG'LIQNI SAQLASH VAZIRLIGI",
            ['bold' => true, 'size' => 11, 'color' => '003366'],
            ['alignment' => Jc::CENTER, 'spaceAfter' => 0]
        );
        $section->addText(
            'TOSHKENT DAVLAT TIBBIYOT UNIVERSITETI TERMIZ FILIALI',
            ['bold' => true, 'size' => 11, 'color' => '003366'],
            ['alignment' => Jc::CENTER, 'spaceAfter' => 0]
        );

        // Inglizcha
        $section->addText(
            'MINISTRY OF THE HEALTH OF THE REPUBLIC OF UZBEKISTAN',
            ['italic' => true, 'size' => 9, 'color' => '444444'],
            ['alignment' => Jc::CENTER, 'spaceAfter' => 0]
        );
        $section->addText(
            'TERMEZ BRANCH OF TASHKENT STATE MEDICAL UNIVERSITY',
            ['italic' => true, 'size' => 9, 'color' => '444444'],
            ['alignment' => Jc::CENTER, 'spaceAfter' => 100]
        );

        // Manzil
        $section->addText(
            '190100, Surxondaryo viloyati, Termiz shahri | Tel/Fax: (0376) 000-00-00 | web: www.tdtutf.uz',
            ['size' => 8, 'color' => '666666'],
            ['alignment' => Jc::CENTER, 'spaceAfter' => 0]
        );

        // Chiziq
        $section->addText('', [], ['spaceAfter' => 0]);
        $section->addLine(['weight' => 2, 'width' => 450, 'height' => 0, 'color' => '003366']);

        $section->addTextBreak(1);

        // Sana va raqam (chap)  +  Sarlavha (o'ng) - jadval orqali
        $table = $section->addTable(['borderSize' => 0, 'cellMargin' => 0]);
        $row = $table->addRow();

        // Chap ustun: sana va raqam
        $cell1 = $row->addCell(4000);
        $cell1->addText(
            '${review_date_full}',
            ['size' => 12],
            ['spaceAfter' => 0]
        );
        $cell1->addText(
            '${order_number} - son',
            ['size' => 12],
            ['spaceAfter' => 0]
        );

        // O'ng ustun: sarlavha
        $cell2 = $row->addCell(6000);
        $cell2->addText(
            'REGISTRATOR OFISI',
            ['bold' => true, 'size' => 16, 'color' => '003366'],
            ['alignment' => Jc::CENTER, 'spaceAfter' => 0]
        );
        $cell2->addText(
            'FARMOYISHI',
            ['bold' => true, 'size' => 16, 'color' => '003366'],
            ['alignment' => Jc::CENTER, 'spaceAfter' => 0]
        );
        $cell2->addText(
            '${academic_year} o\'quv yili',
            ['bold' => true, 'size' => 12],
            ['alignment' => Jc::CENTER, 'spaceAfter' => 100]
        );

        $section->addTextBreak(1);

        // Asosiy matn
        $section->addText(
            'Toshkent davlat tibbiyot universiteti Termiz filiali ${department_name} ${group_name} guruh talabasi ${student_name} (HEMIS ID: ${student_hemis_id}) ${reason} sababli ${start_date} - ${end_date} kunlari (${days_count} kun) darslardan qayta topshirish sharti bilan ozod etilsin.',
            ['size' => 14],
            ['alignment' => Jc::BOTH, 'indentation' => ['firstLine' => 500], 'spaceAfter' => 200, 'lineHeight' => 1.5]
        );

        $section->addText(
            'Qayta topshirishga ruxsat berilsin va qo\'shimcha qaydnoma asosida yuqorida ko\'rsatilgan muddatda topshirishga ruxsat berilsin hamda Hemis platformasida shaxsiy grafik orqali baholari qayd etilsin.',
            ['size' => 14],
            ['alignment' => Jc::BOTH, 'indentation' => ['firstLine' => 500], 'spaceAfter' => 200, 'lineHeight' => 1.5]
        );

        $section->addText(
            'Asos: Talaba tomonidan taqdim etilgan ${reason_document}.',
            ['size' => 14, 'bold' => true],
            ['alignment' => Jc::BOTH, 'indentation' => ['firstLine' => 500], 'spaceAfter' => 200, 'lineHeight' => 1.5]
        );

        $section->addTextBreak(2);

        // Imzo qismi
        $sigTable = $section->addTable(['borderSize' => 0]);
        $sigRow = $sigTable->addRow();
        $sigRow->addCell(5000)->addText(
            'Bo\'lim boshlig\'i',
            ['bold' => true, 'size' => 14],
            ['spaceAfter' => 0]
        );
        $sigRow->addCell(5000)->addText(
            '${reviewer_name}',
            ['bold' => true, 'size' => 14],
            ['alignment' => Jc::RIGHT, 'spaceAfter' => 0]
        );

        $section->addTextBreak(3);

        // Ijrochi
        $section->addText(
            'Ijrochi: ${reviewer_name}',
            ['size' => 9, 'color' => '666666'],
            ['spaceAfter' => 0]
        );
        $section->addText(
            'Sana: ${review_date}',
            ['size' => 9, 'color' => '666666'],
            ['spaceAfter' => 0]
        );

        $section->addTextBreak(1);

        // QR kod joyi
        $section->addText(
            '${qr_code}',
            ['size' => 10],
            ['alignment' => Jc::RIGHT]
        );

        // Faylni saqlash
        $outputDir = storage_path('app/public/document-templates');
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $outputPath = $outputDir . '/namuna_sababli_ariza_shablon.docx';
        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($outputPath);

        $this->info('Namuna shablon yaratildi: ' . $outputPath);
        $this->info('Admin paneldan "Shablonlar" bo\'limiga o\'ting va shu faylni yuklang.');

        return 0;
    }
}
