<?php

namespace App\Services\Monitoring;

/**
 * Detektor natijasi.
 *
 * `evaluatedScopes` — detektor shu run'da to'liq tekshirgan qamrovlar (masalan
 * «2026:11» semestri). Avtomatik yopish faqat shu qamrovlardagi istisnolarga
 * qo'llanadi: semestr almashganda eski semestr istisnolari «bartaraf bo'ldi»
 * deb yopilib ketmaydi. null — barcha istisnolar qamrovda.
 */
final class DetectorResult
{
    /** @var array<string, Finding> */
    private array $findings = [];

    public function __construct(public ?array $evaluatedScopes = null)
    {
    }

    public function add(Finding $finding): self
    {
        $this->findings[$finding->fingerprint] = $finding;

        return $this;
    }

    /** @return array<string, Finding> */
    public function findings(): array
    {
        return $this->findings;
    }

    public function count(): int
    {
        return count($this->findings);
    }
}
