<?php

namespace App\Services\Monitoring;

use Illuminate\Database\Eloquent\Model;

/**
 * Detektor topgan bitta holat. Bir xil holat har safar bir xil `fingerprint` bilan
 * qaytishi shart — shunda istisno takrorlanmaydi.
 */
final class Finding
{
    public function __construct(
        public readonly string $fingerprint,
        public readonly string $subjectType,
        public readonly string $subjectLabel,
        public readonly ?string $subjectRef = null,
        public readonly array $details = [],
        public readonly ?string $scopeKey = null,
        public readonly ?int $facultyHemisId = null,
        public readonly ?int $departmentHemisId = null,
        public readonly ?Model $owner = null,
        public readonly bool $warningOnly = false,
    ) {
    }
}
