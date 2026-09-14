<?php

namespace App\Console\Commands;

use App\Models\Auditorium;
use App\Models\Beacon;
use Illuminate\Console\Command;

/**
 * Register (or re-map) one BLE beacon to an auditorium.
 *
 *   php artisan attendance:beacon-add E2C56DB5-DFFB-48D2-B060-D0F5A71096E0 10001 29299 204 --label="2-bino 204"
 */
class AttendanceBeaconAdd extends Command
{
    protected $signature = 'attendance:beacon-add
        {uuid : iBeacon proximity UUID (36 chars)}
        {major : Major (0-65535)}
        {minor : Minor (0-65535)}
        {auditorium_code : auditoriums.code this beacon is installed in}
        {--label= : Free-text label (building/floor/room)}
        {--inactive : Register but keep disabled}';

    protected $description = 'Map a BLE beacon (uuid/major/minor) to an auditorium for beacon attendance';

    public function handle(): int
    {
        $uuid = strtolower(trim($this->argument('uuid')));
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $uuid)) {
            $this->error("UUID noto'g'ri formatda: {$uuid}");
            return self::FAILURE;
        }

        $major = (int) $this->argument('major');
        $minor = (int) $this->argument('minor');
        if ($major < 0 || $major > 65535 || $minor < 0 || $minor > 65535) {
            $this->error('Major/Minor 0..65535 oralig\'ida bo\'lishi kerak.');
            return self::FAILURE;
        }

        $code = trim($this->argument('auditorium_code'));
        $auditorium = Auditorium::where('code', $code)->first();
        if (!$auditorium) {
            $this->warn("auditoriums jadvalida '{$code}' kodi topilmadi — baribir saqlanadi, nomi bo'sh qoladi.");
        }

        $beacon = Beacon::updateOrCreate(
            ['uuid' => $uuid, 'major' => $major, 'minor' => $minor],
            [
                'auditorium_code' => $code,
                'auditorium_name' => $auditorium?->name,
                'label' => $this->option('label'),
                'active' => !$this->option('inactive'),
            ]
        );

        $this->info(($beacon->wasRecentlyCreated ? 'Qo\'shildi' : 'Yangilandi') . ": #{$beacon->id}  {$uuid}  {$major}/{$minor}  → {$code}"
            . ($auditorium ? " ({$auditorium->name})" : ''));

        return self::SUCCESS;
    }
}
