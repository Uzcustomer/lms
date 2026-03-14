<?php

namespace App\Services;

use App\Models\DocumentTemplate;
use App\Models\StudentContract;
use PhpOffice\PhpWord\TemplateProcessor;
use Illuminate\Support\Facades\Storage;

class StudentContractService
{
    /**
     * Shartnoma Word hujjatini yaratish
     * Avval yuklangan shablondan foydalanadi, agar shablon bo'lmasa - xatolik beradi
     */
    public function generateContractDocument(StudentContract $contract): string
    {
        $templateType = $contract->contract_type === StudentContract::TYPE_4_PARTY
            ? 'student_contract_4'
            : 'student_contract_3';

        $template = DocumentTemplate::getActiveByType($templateType);

        if (!$template) {
            throw new \RuntimeException(
                'Faol shablon topilmadi. Admin panelda "Shablonlar" bo\'limiga o\'tib, "'
                . ($contract->contract_type === StudentContract::TYPE_4_PARTY ? '4 tomonlama' : '3 tomonlama')
                . ' ishga joylashish shartnomasi" shablonini (.docx) yuklang.'
            );
        }

        $templatePath = Storage::disk('public')->path($template->file_path);

        if (!file_exists($templatePath)) {
            throw new \RuntimeException('Shablon fayli serverda topilmadi: ' . $template->file_original_name);
        }

        $processor = new TemplateProcessor($templatePath);

        // Buzilgan makrolarni tuzatish
        $this->fixBrokenMacros($processor);

        $contractYear = now()->year;

        // Talaba (Bitiruvchi) ma'lumotlari
        $processor->setValue('student_name', mb_strtoupper($contract->student_full_name));
        $processor->setValue('student_address', $contract->student_address ?? '');
        $processor->setValue('student_phone', $contract->student_phone ?? '');
        $processor->setValue('student_passport', $contract->student_passport ?? '');
        $processor->setValue('student_bank_account', $contract->student_bank_account ?? '');
        $processor->setValue('student_bank_mfo', $contract->student_bank_mfo ?? '');
        $processor->setValue('student_inn', $contract->student_inn ?? '');

        // Akademik ma'lumotlar
        $processor->setValue('group_name', $contract->group_name ?? '');
        $processor->setValue('department_name', $contract->department_name ?? '');
        $processor->setValue('specialty_name', $contract->specialty_name ?? '');
        $processor->setValue('specialty_field', $contract->specialty_field ?? ($contract->specialty_name ?? ''));
        $processor->setValue('contract_year', (string) $contractYear);

        // Ish beruvchi (Potensial ish beruvchi) ma'lumotlari
        $processor->setValue('employer_name', $contract->employer_name ?? '');
        $processor->setValue('employer_address', $contract->employer_address ?? '');
        $processor->setValue('employer_phone', $contract->employer_phone ?? '');
        $processor->setValue('employer_director_name', $contract->employer_director_name ?? '');
        $processor->setValue('employer_director_position', $contract->employer_director_position ?? '');
        $processor->setValue('employer_bank_account', $contract->employer_bank_account ?? '');
        $processor->setValue('employer_bank_mfo', $contract->employer_bank_mfo ?? '');
        $processor->setValue('employer_inn', $contract->employer_inn ?? '');

        // 4-tomon ma'lumotlari (agar 4 tomonlama bo'lsa)
        if ($contract->contract_type === StudentContract::TYPE_4_PARTY) {
            $processor->setValue('fourth_party_name', $contract->fourth_party_name ?? '');
            $processor->setValue('fourth_party_address', $contract->fourth_party_address ?? '');
            $processor->setValue('fourth_party_phone', $contract->fourth_party_phone ?? '');
            $processor->setValue('fourth_party_director_name', $contract->fourth_party_director_name ?? '');
        }

        // Faylni saqlash
        $dir = 'student-contracts';
        $storagePath = storage_path('app/public/' . $dir);
        if (!is_dir($storagePath)) {
            mkdir($storagePath, 0755, true);
        }

        $filename = $dir . '/shartnoma_' . $contract->id . '_' . time() . '.docx';
        $fullPath = storage_path('app/public/' . $filename);

        $processor->saveAs($fullPath);

        return $filename;
    }

    /**
     * Word shablon ichidagi buzilgan ${...} makrolarni tuzatish
     */
    private function fixBrokenMacros(TemplateProcessor $processor): void
    {
        $reflection = new \ReflectionClass($processor);
        $property = $reflection->getProperty('tempDocumentMainPart');
        $property->setAccessible(true);

        $xml = $property->getValue($processor);
        $xml = $this->mergeBrokenMacros($xml);
        $property->setValue($processor, $xml);

        try {
            $headersProp = $reflection->getProperty('tempDocumentHeaders');
            $headersProp->setAccessible(true);
            $headers = $headersProp->getValue($processor);
            foreach ($headers as $key => $header) {
                $headers[$key] = $this->mergeBrokenMacros($header);
            }
            $headersProp->setValue($processor, $headers);
        } catch (\Throwable $e) {
            // Header yo'q bo'lsa e'tibor bermaslik
        }
    }

    /**
     * XML ichidagi bo'lingan ${...} makrolarni birlashtirish
     */
    private function mergeBrokenMacros(string $xml): string
    {
        $runBreak = '<\\/w:t>.*?<w:t[^>]*>';

        // $ va { orasidagi run break'ni olib tashlash
        $xml = preg_replace(
            '#\x24(' . $runBreak . ')\x7B#sU',
            "\x24\x7B",
            $xml
        ) ?? $xml;

        // ${ va } orasidagi run break'larni takroriy olib tashlash
        for ($i = 0; $i < 15; $i++) {
            $before = $xml;
            $xml = preg_replace(
                '#(\x24\x7B[^\x7D<]*)' . $runBreak . '([^\x7D<]*\x7D)#sU',
                '$1$2',
                $xml
            ) ?? $before;

            if ($xml === $before) {
                break;
            }
        }

        // ${m_num } kabi bo'sh joylarni tozalash
        $xml = preg_replace_callback(
            '#\x24\x7B([^<\x7D]+)\x7D#u',
            function ($m) {
                return '${' . trim($m[1]) . '}';
            },
            $xml
        ) ?? $xml;

        return $xml;
    }
}
