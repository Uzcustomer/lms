<?php

namespace App\Services\Monitoring\Imports;

use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

/**
 * Yuklangan Excel/CSV faylning birinchi varag'i: sarlavhalar (1-qator) va ma'lumot qatorlari.
 */
final class ImportSheet
{
    /** @param array<int, array<int, mixed>> $rows */
    private function __construct(public readonly array $headers, public readonly array $rows)
    {
    }

    public static function read(string $path, string $disk): self
    {
        $sheets = Excel::toArray(new class {}, $path, $disk);
        $all = $sheets[0] ?? [];

        $headers = array_map(fn ($h) => trim((string) $h), array_shift($all) ?? []);
        $rows = array_values(array_filter($all, fn ($r) => collect($r)->filter(fn ($v) => trim((string) $v) !== '')->isNotEmpty()));

        return new self($headers, $rows);
    }

    /**
     * Har bir ustun uchun takrorlanmas qiymatlar (moslash ekranida buyruq turlari uchun).
     *
     * @return array<int, string[]>
     */
    public function distinctByColumn(int $limit = 40): array
    {
        $result = [];
        foreach (array_keys($this->headers) as $i) {
            $values = [];
            foreach ($this->rows as $row) {
                $v = trim((string) ($row[$i] ?? ''));
                if ($v !== '' && !in_array($v, $values, true)) {
                    $values[] = $v;
                    if (count($values) >= $limit) {
                        break;
                    }
                }
            }
            $result[$i] = $values;
        }

        return $result;
    }

    /** Sarlavha nomidan maydonni taxmin qilish: [maydon => ustun indeksi]. */
    public function guess(array $fieldAliases): array
    {
        $map = [];
        foreach ($fieldAliases as $field => $aliases) {
            foreach ($this->headers as $i => $header) {
                $h = self::normalize($header);
                foreach ($aliases as $alias) {
                    if ($h !== '' && str_contains($h, self::normalize($alias))) {
                        $map[$field] ??= $i;
                        break 2;
                    }
                }
            }
        }

        return $map;
    }

    public static function normalize(?string $value): string
    {
        $v = mb_strtolower(trim((string) $value));
        $v = str_replace(['ʻ', 'ʼ', '‘', '’', '`', '´'], "'", $v);

        return preg_replace('/\s+/u', ' ', $v);
    }

    public static function cell(array $row, ?int $index): ?string
    {
        if ($index === null || !array_key_exists($index, $row)) {
            return null;
        }
        $v = trim((string) $row[$index]);

        return $v === '' ? null : $v;
    }

    /** Excel sana raqami yoki dd.mm.yyyy / yyyy-mm-dd matni → Y-m-d. */
    public static function date($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value) && (float) $value > 20000 && (float) $value < 80000) {
            return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value))->toDateString();
        }
        foreach (['d.m.Y', 'Y-m-d', 'd/m/Y', 'd-m-Y', 'd.m.y'] as $format) {
            try {
                $date = Carbon::createFromFormat($format, trim((string) $value));
                if ($date !== false && $date->format($format) === trim((string) $value)) {
                    return $date->toDateString();
                }
            } catch (Throwable) {
                // keyingi format
            }
        }

        return null;
    }
}
