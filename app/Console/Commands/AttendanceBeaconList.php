<?php

namespace App\Console\Commands;

use App\Models\Beacon;
use App\Models\PresenceLog;
use Illuminate\Console\Command;

class AttendanceBeaconList extends Command
{
    protected $signature = 'attendance:beacon-list {--all : Include inactive beacons}';

    protected $description = 'List registered attendance beacons with their last sighting';

    public function handle(): int
    {
        $query = Beacon::orderBy('auditorium_code');
        if (!$this->option('all')) {
            $query->where('active', true);
        }
        $beacons = $query->get();

        if ($beacons->isEmpty()) {
            $this->line('Beacon ro\'yxati bo\'sh. Qo\'shish: php artisan attendance:beacon-add <uuid> <major> <minor> <auditorium_code>');
            return self::SUCCESS;
        }

        $rows = $beacons->map(function (Beacon $b) {
            $last = PresenceLog::where('beacon_id', $b->id)->latest('seen_at')->first();

            return [
                $b->id,
                $b->auditorium_code,
                $b->auditorium_name ?? '-',
                $b->uuid,
                "{$b->major}/{$b->minor}",
                $b->active ? 'faol' : 'o\'chiq',
                $last ? $last->seen_at->format('d.m H:i') : '-',
            ];
        });

        $this->table(['#', 'Xona kodi', 'Xona', 'UUID', 'Major/Minor', 'Holat', 'Oxirgi signal'], $rows);

        return self::SUCCESS;
    }
}
