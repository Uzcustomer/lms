<?php

namespace App\Console\Commands;

use App\Models\RetakeApplicationGroup;
use App\Services\Retake\RetakeDocumentService;
use Illuminate\Console\Command;

class RegenerateRetakeDocx extends Command
{
    protected $signature = 'retake:regenerate-docx {--group= : Faqat berilgan guruh ID uchun}';

    protected $description = 'Mavjud qayta o\'qish arizalarining DOCX faylini yangi shablon bo\'yicha qayta generatsiya qiladi';

    public function handle(RetakeDocumentService $documentService): int
    {
        $query = RetakeApplicationGroup::query()->with(['student', 'applications']);

        if ($groupId = $this->option('group')) {
            $query->where('id', $groupId);
        }

        $count = (clone $query)->count();
        if ($count === 0) {
            $this->warn('Hech qanday guruh topilmadi.');
            return self::SUCCESS;
        }

        $this->info("{$count} ta guruh uchun DOCX qayta generatsiya qilinadi...");
        $bar = $this->output->createProgressBar($count);

        $ok = 0;
        $fail = 0;

        $query->chunk(50, function ($groups) use ($documentService, $bar, &$ok, &$fail) {
            foreach ($groups as $group) {
                try {
                    if (!$group->student) {
                        $fail++;
                        $bar->advance();
                        continue;
                    }
                    $group->docx_path = $documentService->generateDocx($group);
                    $group->save();
                    $ok++;
                } catch (\Throwable $e) {
                    $fail++;
                    $this->error("\nGuruh #{$group->id}: " . $e->getMessage());
                }
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);
        $this->info("Tayyor. Muvaffaqiyatli: {$ok}, xatolik: {$fail}");

        return self::SUCCESS;
    }
}
