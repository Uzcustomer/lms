<?php

namespace App\Console\Commands;

use App\Models\Schedule;
use Illuminate\Console\Command;

class RestoreSchedules extends Command
{
    protected $signature = 'schedules:restore {--education-year= : O\'quv yili kodi (masalan 2025)}';

    protected $description = 'Soft-delete qilingan jadvallarni tiklash (API xatolik tufayli yo\'qolgan ma\'lumotlar uchun)';

    public function handle(): int
    {
        $educationYear = $this->option('education-year');

        $query = Schedule::onlyTrashed();

        if ($educationYear) {
            $query->where('education_year_code', $educationYear);
        }

        $count = $query->count();

        if ($count === 0) {
            $this->info('Tiklanadigan jadval topilmadi.');
            return self::SUCCESS;
        }

        $this->info("{$count} ta soft-deleted jadval topildi.");

        if (!$this->confirm("Tiklamoqchimisiz?")) {
            $this->info('Bekor qilindi.');
            return self::SUCCESS;
        }

        $restored = $query->restore();
        $this->info("{$restored} ta jadval muvaffaqiyatli tiklandi.");

        return self::SUCCESS;
    }
}
