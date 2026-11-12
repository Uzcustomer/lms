<?php

namespace App\Console\Commands;

use App\Models\Teacher;
use App\Services\YnqPending;
use Illuminate\Console\Command;

/**
 * Kichik guruhlar qaydnomasi modali ("tasdig'ingiz kutilmoqda") o'qituvchiga nega chiqmayotganini
 * tekshirish: rollari, dars jadvalidagi guruh × fanlari, kichik guruhlar oilasi va tasdiqlar — har qadam.
 */
class YnqPendingDebug extends Command
{
    protected $signature = 'ynq:pending {teacher : O\'qituvchi — id, hemis_id yoki F.I.Sh. qismi}';

    protected $description = "Kichik guruhlar qaydnomasi modali o'qituvchiga chiqishini tekshirish";

    public function handle(): int
    {
        $q = (string) $this->argument('teacher');
        $teachers = ctype_digit($q)
            ? Teacher::where('id', $q)->orWhere('hemis_id', $q)->get()
            : Teacher::where('full_name', 'like', '%' . $q . '%')->limit(5)->get();
        if ($teachers->isEmpty()) {
            $this->error("O'qituvchi topilmadi");

            return self::FAILURE;
        }
        foreach ($teachers as $teacher) {
            $this->info("{$teacher->full_name} (id {$teacher->id}, hemis {$teacher->hemis_id})");
            $roles = $teacher->getRoleNames()->implode(', ');
            $this->line("  Rollari: " . ($roles ?: '—') . (str_contains($roles, 'oqituvchi') ? '' : "  ← modal faqat «oqituvchi» rolida chiqadi"));
            $items = YnqPending::debug($teacher, fn (string $line) => $this->line('  ' . $line));
            YnqPending::forget($teacher->id);
            $this->line('  Natija: ' . (count($items) ? count($items) . ' ta qaydnoma bo\'yicha modal chiqadi' : 'modal chiqmaydi'));
        }

        return self::SUCCESS;
    }
}
