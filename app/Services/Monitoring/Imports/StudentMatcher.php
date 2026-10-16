<?php

namespace App\Services\Monitoring\Imports;

use Illuminate\Support\Facades\DB;

/**
 * Import qatoridagi shaxsni mark'dagi talabaga (HEMIS nusxasi) bog'lash.
 * Tartib: HEMIS ID → talaba ID raqami → pasport → F.I.Sh. (faqat bitta mos kelsa).
 */
class StudentMatcher
{
    /**
     * @return array{0: ?int, 1: ?string}  [student_hemis_id, usul]
     */
    public function match(?string $hemisId, ?string $idNumber, ?string $passport, ?string $fullName, array $fioScope = []): array
    {
        if ($hemisId && ctype_digit($hemisId) && DB::table('students')->where('hemis_id', $hemisId)->exists()) {
            return [(int) $hemisId, 'hemis_id'];
        }

        if ($idNumber) {
            $found = DB::table('students')->where('student_id_number', $idNumber)->value('hemis_id');
            if ($found) {
                return [(int) $found, 'id_raqam'];
            }
        }

        if ($passport) {
            $normalized = strtoupper(preg_replace('/\s+/', '', $passport));
            if (preg_match('/^([A-Z]{2})(\d{5,9})$/', $normalized, $m)) {
                $found = DB::table('students')
                    ->whereRaw('UPPER(TRIM(passport_serial)) = ?', [$m[1]])
                    ->whereRaw('TRIM(passport_number) = ?', [$m[2]])
                    ->value('hemis_id');
                if ($found) {
                    return [(int) $found, 'passport'];
                }
            }
        }

        if ($fullName) {
            $name = ImportSheet::normalize($fullName);
            $query = DB::table('students')->whereRaw('LOWER(TRIM(full_name)) = ?', [$name]);
            foreach ($fioScope as $column => $value) {
                $query->where($column, $value);
            }
            $ids = $query->limit(2)->pluck('hemis_id');
            if ($ids->count() === 1) {
                return [(int) $ids->first(), 'fio'];
            }
        }

        return [null, null];
    }
}
