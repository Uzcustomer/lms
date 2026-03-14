<?php

namespace App\Services;

use App\Models\StudentContract;
use PhpOffice\PhpWord\TemplateProcessor;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Style\Font;
use Illuminate\Support\Facades\Storage;

class StudentContractService
{
    /**
     * Shartnoma Word hujjatini yaratish
     */
    public function generateContractDocument(StudentContract $contract): string
    {
        $phpWord = new PhpWord();

        // Default font
        $phpWord->setDefaultFontName('Times New Roman');
        $phpWord->setDefaultFontSize(12);

        $section = $phpWord->addSection([
            'marginTop' => 1000,
            'marginBottom' => 1000,
            'marginLeft' => 1200,
            'marginRight' => 800,
        ]);

        $titleStyle = ['bold' => true, 'size' => 13, 'name' => 'Times New Roman'];
        $boldStyle = ['bold' => true, 'size' => 12, 'name' => 'Times New Roman'];
        $normalStyle = ['size' => 12, 'name' => 'Times New Roman'];
        $highlightStyle = ['bold' => true, 'size' => 12, 'name' => 'Times New Roman', 'bgColor' => 'FFFF00'];
        $italicStyle = ['italic' => true, 'size' => 12, 'name' => 'Times New Roman'];
        $centerParagraph = ['alignment' => 'center', 'spaceAfter' => 100];
        $justifyParagraph = ['alignment' => 'both', 'spaceAfter' => 60, 'lineHeight' => 1.15];

        $contractYear = now()->year;
        $is4Party = $contract->contract_type === StudentContract::TYPE_4_PARTY;

        // ===== SARLAVHA =====
        $section->addText(
            'Yosh mutaxassisni ish bilan ta\'minlashga ko\'maklashish to\'g\'risida',
            array_merge($titleStyle, ['italic' => true]),
            $centerParagraph
        );
        $section->addText('SHARTNOMA', $titleStyle, $centerParagraph);
        $section->addTextBreak(1);

        $partyText = $is4Party ? '4 tomonlama' : '3 tomonlama';
        $section->addText(
            "(Oliy ta'lim muassasasi talabasi, oliy ta'lim muassasasi va potensial ish beruvchi o'rtasida tuziladi).",
            $italicStyle,
            $centerParagraph
        );

        $section->addTextBreak(1);

        // ===== KIRISH QISMI =====
        $textRun = $section->addTextRun($justifyParagraph);
        $textRun->addText('    Toshkent tibbiyot akademiyasi Termiz filiali nomidan muassasa Nizomiga asoslanib ish yurituvchi direktor ', $normalStyle);
        $textRun->addText('F.A.Otamuradov', $boldStyle);
        $textRun->addText(' birinchi tomondan, TTA TF nomidan korxona ustaviga asoslanib ish ko\'ruvchi ', $normalStyle);
        $textRun->addText($contract->department_name ?? 'Surxondaryo viloyati', $highlightStyle);
        $textRun->addText(' sog\'liqni saqlash bosh boshqarmasi ustaviga asosan ', $normalStyle);
        $textRun->addText($contract->employer_director_name ?? '____________________', $highlightStyle);
        $textRun->addText(' ikkinchi tomondan, (keyingi o\'rinlarda "potensial ish beruvchi" deb nomlanadi) va TTA Temiz filialini ', $normalStyle);
        $textRun->addText($contract->specialty_field ?? ($contract->specialty_name ?? 'Davolash'), $highlightStyle);
        $textRun->addText(' mutaxassisligi bo\'yicha ' . $contractYear . '-yilda bitiruvchi ', $normalStyle);
        $textRun->addText(mb_strtoupper($contract->student_full_name), $highlightStyle);

        if ($is4Party) {
            $textRun->addText(' uchinchi tomondan, va ', $normalStyle);
            $textRun->addText($contract->fourth_party_name ?? '____________________', $highlightStyle);
            $textRun->addText(' (Bitiruvchi" deb nomlanadi) to\'rtinchi tomondan, ', $normalStyle);
        } else {
            $textRun->addText(' (Bitiruvchi" deb nomlanadi) uchinchi tomondan, ', $normalStyle);
        }

        $textRun->addText('Vazirlar Mahkamasining 2002 yil 4 iyundagi 198-sonli qarorining 6-bandiga asosan yosh mutaxassislarni ishga joylashishiga ko\'maklashish maqsadida ushbu quyidagilar haqida tuzilar.', $normalStyle);

        $section->addTextBreak(1);

        // ===== I. SHARTNOMANING PREDMETI =====
        $section->addText('I. SHARTNOMANING PREDMETI:', $boldStyle, $centerParagraph);
        $textRun = $section->addTextRun($justifyParagraph);
        $textRun->addText('1.1 Oliy ta\'limga ega bo\'lgan yosh mutaxassis (bakalavr, magistrlar)ning intelektual salohiyatidan unumli foydalanish va ularni ish bilan ta\'minlashda ko\'maklashish.', $normalStyle);

        $section->addTextBreak(1);

        // ===== II. TOMONLARNING HUQUQLARI =====
        $section->addText('II. TOMONLARNING HUQUQLARI:', $boldStyle, $centerParagraph);

        $section->addText('2.1. Oliy ta\'lim muassasasi:', $boldStyle, $justifyParagraph);
        $section->addText('2.1.1. Noyob ta\'lim yo\'nalishlari va mataxassisliklar bo\'yicha extiyoj ko\'p bo\'lgan taqdirda «potensial ish beruvchi» larni tender asosida tanlash.', $normalStyle, $justifyParagraph);
        $section->addText('2.1.2. Talaba o\'quv amaliyotini o\'tash uchun "Potensial ish beruvchi"ning intellektual va moddiy texnikaviy bazasining imkoniyatlarni o\'rganish va shu asosida tegishli taklif kiritish.', $normalStyle, $justifyParagraph);

        $section->addText('2.2. "Potensial ish beruvchi"', $boldStyle, $justifyParagraph);
        $section->addText('2.2.1. Toshkent tibbiyot akademiyasi Termiz filialining o\'quv reja va dasturlari bilan tanishish hamda o\'z faoliyatiga bog\'liq bo\'lgan takliflar kiritish.', $normalStyle, $justifyParagraph);

        $section->addTextBreak(1);

        // ===== III. TOMONLARNING MAJBURIYATLARI =====
        $section->addText('III. TOMONLARNING MAJBURIYATLARI:', $boldStyle, $centerParagraph);

        $section->addText('3.1. Oliy ta\'lim muassasasi:', $boldStyle, $justifyParagraph);
        $section->addText('3.1.1. Mutaxassisni tayyorlash sifatiga mas\'ul bo\'lish.', $normalStyle, $justifyParagraph);
        $section->addText('3.1.2. "Potensial ish beruvchi" tomonidan taklif etilgan o\'quv reja va dasturlariga o\'zgartirishlar kiritish.', $normalStyle, $justifyParagraph);
        $section->addText('3.1.3. "Bitiruvchi" ni "potensial ish beruvchi" ga amaliyot uchun jo\'natishda zarur ko\'rsatmalarni berish.', $normalStyle, $justifyParagraph);

        $section->addText('3.2. "Potensial ish beruvchi":', $boldStyle, $justifyParagraph);
        $section->addText('3.2.1. Oliy ta\'lim muassasasi bitiruvchisini ("Bitiruvchi" ni) kasbiy bilim va malakasiga muvofiq lavozimga ishga qabul qilish.', $normalStyle, $justifyParagraph);
        $section->addText('3.2.2. "Bitiruvchi" ni zarur turar-joy bilan ta\'minlash borasida ko\'maklashish.', $normalStyle, $justifyParagraph);
        $section->addText('3.2.3. Qonunchilikda belgilangan tartibda ish haqi to\'lash.', $normalStyle, $justifyParagraph);

        $section->addText('3.3. "Bitiruvchi":', $boldStyle, $justifyParagraph);
        $section->addText('3.3.1. O\'quv jarayonida olgan bilim va ko\'nikmalarini amalda qo\'llash.', $normalStyle, $justifyParagraph);
        $section->addText('3.3.2. Ichki tartib qoidalariga rioya qilish.', $normalStyle, $justifyParagraph);
        $section->addText('3.3.3. Kamida 3 (uch) yil muddatga "potensial ish beruvchi" da ishlash.', $normalStyle, $justifyParagraph);

        if ($is4Party) {
            $section->addText('3.4. To\'rtinchi tomon:', $boldStyle, $justifyParagraph);
            $section->addText('3.4.1. Bitiruvchining ish bilan ta\'minlanishiga ko\'maklashish va nazorat qilish.', $normalStyle, $justifyParagraph);
        }

        $section->addTextBreak(1);

        // ===== IV. TOMONLARNING JAVOBGARLIGI =====
        $section->addText('IV. TOMONLARNING JAVOBGARLIGI:', $boldStyle, $centerParagraph);
        $section->addText('4.1. Taraflar ushbu shartnoma shartlarini bajarmaslik yoki lozim darajada bajarmaslik uchun O\'zbekiston Respublikasi qonunchiligiga muvofiq javobgar bo\'ladi.', $normalStyle, $justifyParagraph);

        $section->addTextBreak(1);

        // ===== V. SHARTNOMANING AMAL QILISH MUDDATI VA UNI BEKOR QILISH TARTIBI =====
        $section->addText('V. SHARTNOMANING AMAL QILISH MUDDATI VA UNI BEKOR QILISH TARTIBI:', $boldStyle, $centerParagraph);
        $section->addText('5.1. Taraflardan birining talabi bilan shartnoma quyidagi hollarda qonunda belgilangan tartibda bekor qilinishi mumkin:', $normalStyle, $justifyParagraph);
        $section->addText('5.1.1. "Bitiruvchi" ning malakasi, bilimi ushbu mutaxassis uchun amalda o\'rnatilgan Davlat standartlari talablariga javob bermasa.', $normalStyle, $justifyParagraph);
        $section->addText('5.1.2. "Potensial ish beruvchi" tomonidan shartnoma majburiyatlari bajarilmagan taqdirda', $normalStyle, $justifyParagraph);
        $section->addText('5.2. Ushbu shartnoma tomonlarning barchasi imzolanganidan so\'ng kuchga kiradi hamda shartnoma to\'liq bajarilgunga qadar amalda bo\'ladi.', $normalStyle, $justifyParagraph);

        $section->addTextBreak(1);

        // ===== TOMONLARNING REKVIZITLARI =====
        $section->addText('TOMONLARNING REKVIZITLARI:', $boldStyle, $centerParagraph);

        // Jadval - 3 yoki 4 ustun
        $colCount = $is4Party ? 4 : 3;
        $colWidth = $is4Party ? 2300 : 3000;

        $table = $section->addTable([
            'borderSize' => 0,
            'cellMarginTop' => 50,
            'cellMarginBottom' => 50,
        ]);

        $cellStyle = ['valign' => 'top'];
        $smallStyle = ['size' => 10, 'name' => 'Times New Roman'];
        $smallBoldStyle = ['size' => 10, 'name' => 'Times New Roman', 'bold' => true];
        $smallHighlightStyle = ['size' => 10, 'name' => 'Times New Roman', 'bold' => true, 'bgColor' => 'FFFF00'];

        // === Sarlavha qatori ===
        $row = $table->addRow();

        // 1-ustun: Oliy ta'lim muassasasi
        $cell = $row->addCell($colWidth, $cellStyle);
        $cell->addText('Toshkent tibbiyot akademiyasi', $smallBoldStyle, ['spaceAfter' => 30]);
        $cell->addText('Termiz filiali', $smallBoldStyle, ['spaceAfter' => 30]);
        $cell->addText('Manzili: Termiz shahar, I.A.Karimov ko\'chasi 64-uy', $smallStyle, ['spaceAfter' => 30]);
        $cell->addText('Tel: 0376-223-47-20', $smallStyle, ['spaceAfter' => 30]);
        $cell->addText('E-mail: ttatf.uz', $smallStyle, ['spaceAfter' => 30]);
        $cell->addText('SHXR: 400910860224017094l0005', $smallStyle, ['spaceAfter' => 30]);
        $cell->addText('INN: 00491', $smallStyle, ['spaceAfter' => 30]);
        $cell->addText('', $smallStyle, ['spaceAfter' => 80]);
        $cell->addText('Imzo_______________', $smallStyle, ['spaceAfter' => 30]);
        $cell->addText('Sana_______________', $smallStyle, ['spaceAfter' => 30]);

        // 2-ustun: Potensial ish beruvchi
        $cell = $row->addCell($colWidth, $cellStyle);
        $cell->addText('"Potensial ish beruvchi"', $smallBoldStyle, ['spaceAfter' => 30]);
        $cell->addText('_______________________', $smallStyle, ['spaceAfter' => 30]);
        $cell->addText('Manzili: ' . ($contract->employer_address ?? '_______________'), $smallStyle, ['spaceAfter' => 30]);
        $cell->addText('Tel: ' . ($contract->employer_phone ?? '_______________'), $smallStyle, ['spaceAfter' => 30]);
        $cell->addText('Faks:_______________', $smallStyle, ['spaceAfter' => 30]);
        $cell->addText('x/r: ' . ($contract->employer_bank_account ?? '_______________'), $smallStyle, ['spaceAfter' => 30]);
        $cell->addText('MFO: ' . ($contract->employer_bank_mfo ?? '_______________'), $smallStyle, ['spaceAfter' => 30]);
        $cell->addText('INN: ' . ($contract->employer_inn ?? '_______________'), $smallStyle, ['spaceAfter' => 30]);
        $cell->addText('', $smallStyle, ['spaceAfter' => 80]);
        $cell->addText('Imzo_______________', $smallStyle, ['spaceAfter' => 30]);
        $cell->addText('Sana_______________', $smallStyle, ['spaceAfter' => 30]);

        // 3-ustun: Bitiruvchi
        $cell = $row->addCell($colWidth, $cellStyle);
        $cell->addText('Bitiruvchi', $smallBoldStyle, ['spaceAfter' => 30]);
        $cell->addText(mb_strtoupper($contract->student_full_name), $smallHighlightStyle, ['spaceAfter' => 30]);
        $cell->addText($contract->student_address ?? '', $smallHighlightStyle, ['spaceAfter' => 30]);
        $cell->addText('Tel ' . ($contract->student_phone ?? ''), $smallHighlightStyle, ['spaceAfter' => 30]);
        $cell->addText($contract->student_passport ?? '', $smallStyle, ['spaceAfter' => 30]);
        $cell->addText($contract->student_bank_account ?? '', $smallStyle, ['spaceAfter' => 30]);
        $cell->addText('', $smallStyle, ['spaceAfter' => 80]);
        $cell->addText('Imzo_______________', $smallStyle, ['spaceAfter' => 30]);
        $cell->addText('Sana_______________', $smallStyle, ['spaceAfter' => 30]);

        // 4-ustun (agar 4 tomonlama bo'lsa)
        if ($is4Party) {
            $cell = $row->addCell($colWidth, $cellStyle);
            $cell->addText('To\'rtinchi tomon', $smallBoldStyle, ['spaceAfter' => 30]);
            $cell->addText($contract->fourth_party_name ?? '_______________', $smallHighlightStyle, ['spaceAfter' => 30]);
            $cell->addText($contract->fourth_party_address ?? '', $smallStyle, ['spaceAfter' => 30]);
            $cell->addText('Tel: ' . ($contract->fourth_party_phone ?? '_______________'), $smallStyle, ['spaceAfter' => 30]);
            $cell->addText($contract->fourth_party_director_name ?? '', $smallStyle, ['spaceAfter' => 30]);
            $cell->addText('', $smallStyle, ['spaceAfter' => 80]);
            $cell->addText('Imzo_______________', $smallStyle, ['spaceAfter' => 30]);
            $cell->addText('Sana_______________', $smallStyle, ['spaceAfter' => 30]);
        }

        // Faylni saqlash
        $dir = 'student-contracts';
        $storagePath = storage_path('app/public/' . $dir);
        if (!is_dir($storagePath)) {
            mkdir($storagePath, 0755, true);
        }

        $filename = $dir . '/shartnoma_' . $contract->id . '_' . time() . '.docx';
        $fullPath = storage_path('app/public/' . $filename);

        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($fullPath);

        return $filename;
    }
}
