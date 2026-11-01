<?php

namespace App\Services\Timetable;

use App\Models\Department;
use App\Models\Teacher;

/**
 * Kafedra — id bo'yicha. HEMIS'da bir kafedra nomi bilan bir nechta yozuv bo'lishi mumkin (asosiy va o'rindoshlik):
 * bunday yozuvlar bitta kafedra hisoblanadi. Asosiy yozuv — kafedra mudiri biriktirilgani (bir nechtasida bo'lsa yoki
 * hech birida bo'lmasa — eng kichik department_hemis_id). Tasdiqlovchi kafedra va uning mudiri shu asosiy yozuvdan.
 */
class KafedraResolver
{
    /** @var array<int, int[]>|null kafedra id => shu nomdagi barcha kafedra id lari */
    private ?array $groups = null;

    /** @var array<int, int> kafedra id => asosiy kafedra id */
    private array $primary = [];

    public static function nameKey(?string $name): string
    {
        $n = mb_strtolower(trim((string) $name));
        $n = str_replace(["\u{2018}", "\u{2019}", "\u{02BC}", "\u{02BB}", '`', '´'], "'", $n);

        return (string) preg_replace('/\s+/u', ' ', $n);
    }

    /** Shu kafedra bilan bir xil nomli (asosiy + o'rindoshlik) kafedralar id lari; o'zi ham. */
    public function group(int $departmentId): array
    {
        if ($this->groups === null) {
            $byName = [];
            foreach (Department::where('structure_type_code', 12)->get(['department_hemis_id', 'name']) as $d) {
                $byName[self::nameKey($d->name)][] = (int) $d->department_hemis_id;
            }
            $this->groups = [];
            foreach ($byName as $ids) {
                sort($ids);
                foreach ($ids as $id) {
                    $this->groups[$id] = $ids;
                }
            }
        }

        return $this->groups[$departmentId] ?? [$departmentId];
    }

    /** Asosiy yozuv: mudiri biriktirilgani; bo'lmasa — eng kichik id. */
    public function primary(int $departmentId): int
    {
        if ($departmentId <= 0) {
            return $departmentId;
        }
        if (isset($this->primary[$departmentId])) {
            return $this->primary[$departmentId];
        }
        $ids = $this->group($departmentId);
        $pick = $ids[0];
        if (count($ids) > 1) {
            $withHead = array_values(array_filter($ids, fn ($id) => $this->headsOf([$id])->isNotEmpty()));
            $pick = $withHead[0] ?? $ids[0];
        }
        foreach ($ids as $id) {
            $this->primary[$id] = $pick;
        }

        return $pick;
    }

    /** Ikki kafedra id si bitta kafedrami (bir xil nomli yozuvlar ham). */
    public function same(int $a, int $b): bool
    {
        return $a > 0 && $b > 0 && in_array($b, $this->group($a), true);
    }

    /** O'qituvchining (mudirning) kafedrasi — asosiy yozuv id si. */
    public function teacherDepartment(?object $teacher): int
    {
        $id = (int) ($teacher?->head_department_hemis_id ?: ($teacher?->department_hemis_id ?? 0));

        return $id > 0 ? $this->primary($id) : 0;
    }

    /** Kafedra (va uning bir xil nomli yozuvlari) mudirlari. */
    public function heads(int $departmentId)
    {
        return $this->headsOf($this->group($departmentId));
    }

    private function headsOf(array $ids)
    {
        $existing = \Spatie\Permission\Models\Role::where('name', 'kafedra_mudiri')->exists();
        if (!$existing) {
            return collect();
        }

        return Teacher::role('kafedra_mudiri')->where('status', true)->get()
            ->filter(fn (Teacher $t) => in_array((int) ($t->head_department_hemis_id ?: $t->department_hemis_id), $ids, true))->values();
    }
}
