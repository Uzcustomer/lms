<?php

namespace App\Services\Retake;

use App\Enums\RetakeLogAction;
use App\Models\RetakeApplication;
use App\Models\RetakeApplicationLog;
use App\Models\Teacher;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Carbon;

/**
 * Audit log uchun yagona kirish nuqtasi. Har bosqich o'zgarishi shu yerdan
 * yozib olinadi (kim, qachon, qaysi amal, izoh).
 */
class RetakeLogService
{
    public function log(
        RetakeApplication $application,
        RetakeLogAction $action,
        ?Authenticatable $actor = null,
        ?string $note = null,
    ): RetakeApplicationLog {
        return RetakeApplicationLog::create([
            'application_id' => $application->id,
            'actor_id' => $actor?->getAuthIdentifier(),
            'actor_guard' => $this->guardForActor($actor),
            'action' => $action->value,
            'note' => $note,
            'created_at' => Carbon::now(),
        ]);
    }

    public function guardForActor(?Authenticatable $actor): ?string
    {
        if ($actor === null) {
            return null;
        }

        return $actor instanceof Teacher ? 'teacher' : 'web';
    }
}
