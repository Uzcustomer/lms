<?php

namespace App\Console\Commands;

use App\Services\Monitoring\MonitoringCatalog;
use Illuminate\Console\Command;

class MonitoringSyncCatalog extends Command
{
    protected $signature = 'monitoring:sync-catalog
                            {--path= : Katalog fayli (standart: docs/detectors.yaml)}
                            {--reset-params : Paneldagi parametr va yoqish holatini ham yaml\'dagiga qaytarish}';

    protected $description = 'docs/detectors.yaml katalogini monitoring_detectors jadvaliga yozadi';

    public function handle(MonitoringCatalog $catalog): int
    {
        if ($this->option('reset-params')
            && !$this->confirm('Paneldan o\'zgartirilgan barcha parametrlar yaml\'dagi qiymatlarga qaytadi. Davom etilsinmi?')) {
            return self::SUCCESS;
        }

        $result = $catalog->sync($this->option('path') ?: null, (bool) $this->option('reset-params'));
        $this->info("Katalog: jami {$result['total']}, yangi {$result['created']}, yangilangan {$result['updated']}.");

        return self::SUCCESS;
    }
}
