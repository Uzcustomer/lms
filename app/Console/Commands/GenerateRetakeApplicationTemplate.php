<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;

/**
 * Qayta o'qish arizasi DOCX shablonini generatsiya qiladi.
 *
 * Output: resources/templates/Retake/Ariza-shablon.docx
 *
 * Shablon foydalanuvchi tomonidan tasdiqlangan matn asosida tuzilgan.
 * Placeholders: {{faculty_name}}, {{dean_name}}, {{group_name}},
 * {{student_full_name}}, {{semester_number}}, {{subjects_list}},
 * {{submission_date}}.
 */
class GenerateRetakeApplicationTemplate extends Command
{
    protected $signature = 'retake:generate-template
                            {--force : Mavjud faylni qayta yozish}';

    protected $description = 'Qayta o\'qish arizasi DOCX shablonini generatsiya qilish';

    public function handle(): int
    {
        $directory = resource_path('templates/Retake');
        $path = $directory . '/Ariza-shablon.docx';

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        if (file_exists($path) && ! $this->option('force')) {
            $this->warn("Shablon allaqachon mavjud: {$path}");
            $this->warn("--force bilan qayta yozish mumkin.");
            return self::SUCCESS;
        }

        $phpWord = new PhpWord();
        $phpWord->setDefaultFontName('Times New Roman');
        $phpWord->setDefaultFontSize(12);

        $section = $phpWord->addSection([
            'marginLeft' => 1701,
            'marginRight' => 1134,
            'marginTop' => 1134,
            'marginBottom' => 1134,
        ]);

        // ── Yuqori o'ng tomonda manzil bloki ──
        $addressTable = $section->addTable([
            'borderSize' => 0,
            'cellMargin' => 0,
        ]);
        $addressRow = $addressTable->addRow();
        $addressRow->addCell(4500); // bo'sh chap ustun
        $addressCell = $addressRow->addCell(5000);

        $addressStyle = ['alignment' => Jc::BOTH, 'spaceAfter' => 0];
        $addressCell->addText(
            'Toshkent davlat tibbiyot universiteti Termiz filiali {{faculty_name}} fakulteti dekani {{dean_name}}ga',
            ['name' => 'Times New Roman', 'size' => 12],
            $addressStyle,
        );
        $addressCell->addText(
            '{{faculty_name}} fakulteti {{group_name}} guruh talabasi',
            ['name' => 'Times New Roman', 'size' => 12],
            $addressStyle,
        );
        $addressCell->addText(
            '{{student_full_name}} (F.I.SH) tomonidan',
            ['name' => 'Times New Roman', 'size' => 12],
            $addressStyle,
        );

        $section->addTextBreak(2);

        // ── ARIZA sarlavhasi (markazda, qalin) ──
        $section->addText(
            'ARIZA',
            ['name' => 'Times New Roman', 'size' => 14, 'bold' => true],
            ['alignment' => Jc::CENTER, 'spaceAfter' => 240],
        );

        // ── Asosiy matn ──
        $section->addText(
            "\tMen, {{faculty_name}} fakulteti {{group_name}} guruh talabasi {{student_full_name}} (F.I.Sh.), ushbu ariza orqali shuni ma'lum qilamanki, akademik qarzdorligim mavjud bo'lgan {{semester_number}}-semestr {{subjects_list}} fan(i/lar)ini o'z hisobimdan qayta o'qish uchun ruxsat berishingizni so'rayman.",
            ['name' => 'Times New Roman', 'size' => 12],
            ['alignment' => Jc::BOTH, 'spaceAfter' => 240, 'lineHeight' => 1.5],
        );

        $section->addText(
            "\tMazkur qayta o'qish uchun to'lov xabarnomasi (qayta o'qish) ilova qilinadi.",
            ['name' => 'Times New Roman', 'size' => 12],
            ['alignment' => Jc::BOTH, 'spaceAfter' => 480, 'lineHeight' => 1.5],
        );

        $section->addTextBreak(3);

        // ── Pastki imzo qatori (chap: Talaba, o'ng: sana) ──
        $signatureTable = $section->addTable([
            'borderSize' => 0,
            'cellMargin' => 0,
        ]);
        $signatureRow = $signatureTable->addRow();
        $leftCell = $signatureRow->addCell(6000);
        $rightCell = $signatureRow->addCell(3500);

        $leftCell->addText(
            'Talaba: {{student_full_name}} F.I.SH',
            ['name' => 'Times New Roman', 'size' => 12],
            ['alignment' => Jc::START, 'spaceAfter' => 0],
        );
        $rightCell->addText(
            '{{submission_date}}',
            ['name' => 'Times New Roman', 'size' => 12],
            ['alignment' => Jc::END, 'spaceAfter' => 0],
        );

        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($path);

        $this->info("Shablon yaratildi: {$path}");
        $this->info("Hajmi: " . number_format(filesize($path)) . " bayt");

        return self::SUCCESS;
    }
}
