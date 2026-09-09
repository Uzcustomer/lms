<?php

namespace App\Console\Commands\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Import natijasini yangi / yangilangan / o'zgarmagan bo'yicha sanaydi.
 *
 * `updateOrCreate` ikkalasini ham jimgina bajaradi, shu sabab importdan keyin
 * "245 ta" degan son HEMISda nima o'zgarganini aytmaydi. Bu trait har bir
 * yozuvni uch holatdan biriga ajratadi va oxirida ixcham hisobot beradi.
 *
 * Foydalanish:
 *   use ReportsImportChanges;
 *   ...
 *   $this->resetImportStats();
 *   $model = Curriculum::updateOrCreate([...], [...]);
 *   $this->trackImport($model, $model->name);
 *   ...
 *   $this->renderImportSummary('O\'quv rejalar');
 */
trait ReportsImportChanges
{
    private int $importCreated = 0;
    private int $importUpdated = 0;
    private int $importUnchanged = 0;

    /** Yangi qo'shilganlar nomi — hisobotda ko'rsatiladi. */
    private array $importCreatedNames = [];

    /** O'zgarganlar nomi. */
    private array $importUpdatedNames = [];

    protected function resetImportStats(): void
    {
        $this->importCreated = 0;
        $this->importUpdated = 0;
        $this->importUnchanged = 0;
        $this->importCreatedNames = [];
        $this->importUpdatedNames = [];
    }

    /**
     * Bitta yozuvni sanaydi.
     *
     * `wasRecentlyCreated` — yangi yaratilgan; `wasChanged()` — mavjud yozuv
     * saqlash paytida o'zgargan. Ikkalasi ham false bo'lsa, HEMISdagi qiymat
     * bazadagi bilan bir xil edi.
     */
    protected function trackImport(Model $model, ?string $name = null): void
    {
        $name = trim((string) $name);

        if ($model->wasRecentlyCreated) {
            $this->importCreated++;
            if ($name !== '') {
                $this->importCreatedNames[] = $name;
            }
            return;
        }

        if ($model->wasChanged()) {
            $this->importUpdated++;
            if ($name !== '') {
                $this->importUpdatedNames[] = $name;
            }
            return;
        }

        $this->importUnchanged++;
    }

    protected function importTotal(): int
    {
        return $this->importCreated + $this->importUpdated + $this->importUnchanged;
    }

    /** Telegram va konsol uchun bir qatorli xulosa. */
    protected function importSummaryLine(): string
    {
        return sprintf(
            'jami %d · yangi %d · yangilandi %d · o\'zgarmadi %d',
            $this->importTotal(),
            $this->importCreated,
            $this->importUpdated,
            $this->importUnchanged
        );
    }

    /**
     * Konsolga jadval va nomlar ro'yxatini chiqaradi.
     * $label — "O'quv rejalar" kabi nom.
     */
    protected function renderImportSummary(string $label, int $nameLimit = 15): void
    {
        $this->newLine();
        $this->info($label . ' importi tugadi:');
        $this->table(
            ['Holat', 'Soni'],
            [
                ['Jami ko\'rildi', $this->importTotal()],
                ['Yangi qo\'shildi', $this->importCreated],
                ['Yangilandi', $this->importUpdated],
                ['O\'zgarmadi', $this->importUnchanged],
            ]
        );

        $this->listNames('YANGI', $this->importCreatedNames, $nameLimit);
        $this->listNames('YANGILANDI', $this->importUpdatedNames, $nameLimit);

        if ($this->importCreated === 0 && $this->importUpdated === 0) {
            $this->line('  HEMISda yangilik yo\'q — hamma yozuv bazadagi bilan bir xil.');
        }
    }

    private function listNames(string $title, array $names, int $limit): void
    {
        if (empty($names)) {
            return;
        }

        $shown = array_slice($names, 0, $limit);
        $this->line('  <fg=cyan>' . $title . ':</> ' . implode(', ', $shown)
            . (count($names) > $limit ? ' … (+' . (count($names) - $limit) . ' ta)' : ''));
    }

    /** Telegram xabari uchun to'liq matn. */
    protected function importTelegramMessage(string $label, int $nameLimit = 10): string
    {
        $text = "✅ {$label} importi tugadi\n" . $this->importSummaryLine();

        if ($this->importCreatedNames) {
            $shown = array_slice($this->importCreatedNames, 0, $nameLimit);
            $text .= "\n\nYangi: " . implode(', ', $shown)
                . (count($this->importCreatedNames) > $nameLimit
                    ? ' … (+' . (count($this->importCreatedNames) - $nameLimit) . ' ta)'
                    : '');
        }

        return $text;
    }
}
