<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Taqsimlash sahifasining Excel fayli — ikki varaq:
 *   "Talabalar" — guruhlar bo'yicha talabalar ro'yxati (eski/yangi holat);
 *   "Guruhlar"  — sahifadagi guruhlar ro'yxati: fakultet, yo'nalish, til,
 *                 talaba soni, sig'im va holat.
 */
class DistributionExport implements WithMultipleSheets
{
    use Exportable;

    public function __construct(
        private Collection $groups,
        private string $heading = 'Guruhlar bo\'yicha talabalar',
        private array $modes = ['old']
    ) {
    }

    public function sheets(): array
    {
        // Guruhlar varag'ida son rejimga qarab: faqat "old" bo'lsa LMS dagi son,
        // "new" yoki taqqoslash bo'lsa reja qo'llangan son.
        $mode = $this->modes === ['old'] ? 'old' : 'new';

        return [
            new DistributionGroupStudentsExport($this->groups, $this->heading, $this->modes),
            new DistributionGroupsSheet($this->groups, $this->heading, $mode),
        ];
    }
}
