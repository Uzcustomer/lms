<?php

namespace App\Imports;

use App\Models\Auditorium;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Auditoriyalarni Excel (.xlsx/.csv) fayldan import qilish (dars jadvali tuzish
 * dialogidan). Sarlavhalar (WithHeadingRow — kichik harf, bo'sh joy "_"):
 *
 *   kod | nomi | sigim | bino | turi
 *
 * Muqobil sarlavhalar ham qabul qilinadi: code/name/volume/building/type.
 * Kod bo'yicha upsert — mavjud auditoriya yangilanadi, yo'q bo'lsa qo'shiladi.
 */
class AuditoriumImport implements ToCollection, WithHeadingRow
{
    /** @var int Yangi qo'shilgan qatorlar. */
    public int $imported = 0;

    /** @var int Yangilangan qatorlar. */
    public int $updated = 0;

    /** @var array Xatolik qatorlari: [['row' => n, 'error' => '...']]. */
    public array $errors = [];

    public function collection(Collection $rows): void
    {
        foreach ($rows as $i => $row) {
            $r = $row->toArray();
            $code = trim((string) ($r['kod'] ?? $r['code'] ?? ''));
            $name = trim((string) ($r['nomi'] ?? $r['nom'] ?? $r['name'] ?? ''));

            if ($code === '' && $name === '') {
                continue; // bo'sh qator
            }
            if ($code === '') {
                $this->errors[] = ['row' => $i + 2, 'error' => 'Kod bo\'sh'];
                continue;
            }

            $volume = (int) ($r['sigim'] ?? $r['sig_im'] ?? $r['volume'] ?? 0);
            $building = trim((string) ($r['bino'] ?? $r['building'] ?? '')) ?: null;
            $type = trim((string) ($r['turi'] ?? $r['type'] ?? '')) ?: null;

            $existing = Auditorium::where('code', $code)->first();
            $payload = [
                'name'                 => $name ?: $code,
                'volume'               => $volume,
                'active'               => true,
                'building_name'        => $building,
                'auditorium_type_name' => $type,
            ];

            if ($existing) {
                $existing->update($payload);
                $this->updated++;
            } else {
                Auditorium::create(array_merge(['code' => $code], $payload));
                $this->imported++;
            }
        }
    }
}
