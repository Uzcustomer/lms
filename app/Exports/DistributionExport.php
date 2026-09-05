<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Taqsimlash sahifasining Excel fayli — ikki varaq:
 *   "Talabalar" — guruhlar bo'yicha talabalar ro'yxati (eski/yangi holat);
 *                 taqsimlanadigan guruhlar uchun yassi ro'yxat;
 *   "Guruhlar"  — sahifadagi guruhlar ro'yxati: fakultet, yo'nalish, til,
 *                 talaba soni, sig'im va holat.
 */
class DistributionExport implements WithMultipleSheets
{
    use Exportable;

    /**
     * @param bool $sourcesOnly "Taqsimlanadigan guruhlar" vkladkasi: talabalar
     *                          bloklarga bo'linmay, yassi ro'yxat bo'lib chiqadi.
     */
    public function __construct(
        private Collection $groups,
        private string $heading = 'Guruhlar bo\'yicha talabalar',
        private array $modes = ['old'],
        private bool $sourcesOnly = false
    ) {
    }

    public function sheets(): array
    {
        // Guruhlar varag'ida son rejimga qarab: faqat "old" bo'lsa LMS dagi son,
        // "new" yoki taqqoslash bo'lsa reja qo'llangan son.
        $mode = $this->modes === ['old'] ? 'old' : 'new';

        $students = $this->sourcesOnly
            ? new DistributionSourceStudentsSheet($this->groups, $this->heading, $mode)
            : new DistributionGroupStudentsExport($this->groups, $this->heading, $this->modes);

        return [
            $students,
            new DistributionGroupsSheet($this->groups, $this->heading, $mode),
        ];
    }
}
