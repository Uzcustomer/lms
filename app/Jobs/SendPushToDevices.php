<?php

namespace App\Jobs;

use App\Models\DeviceToken;
use App\Services\FcmService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Push one message to every registered device of the given owners.
 * Dead tokens (uninstalled app) are dropped as FCM reports them.
 */
class SendPushToDevices implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 300;

    /**
     * @param  'student'|'teacher'  $ownerType
     * @param  int[]  $ownerIds
     * @param  array{title?: string, body?: string}  $notification
     * @param  array<string, scalar|null>  $data
     */
    public function __construct(
        public string $ownerType,
        public array $ownerIds,
        public array $notification,
        public array $data = [],
    ) {
    }

    public function handle(FcmService $fcm): void
    {
        if ($this->ownerIds === [] || !$fcm->enabled()) {
            return;
        }

        DeviceToken::where('owner_type', $this->ownerType)
            ->whereIn('owner_id', $this->ownerIds)
            ->orderBy('id')
            ->chunk(200, function ($tokens) use ($fcm) {
                foreach ($tokens as $device) {
                    $result = $fcm->send($device->token, $this->notification, $this->data);
                    if ($result === FcmService::RESULT_INVALID_TOKEN) {
                        $device->delete();
                    }
                }
            });
    }
}
