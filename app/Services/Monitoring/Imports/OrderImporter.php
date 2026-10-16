<?php

namespace App\Services\Monitoring\Imports;

use App\Models\MonitoringDataImport;
use App\Models\MonitoringOrder;
use App\Models\MonitoringTransferChecklist;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Buyruqlar reyestri importi (HEMIS'dan eksport qilingan yoki qo'lda tuzilgan Excel/CSV).
 * Qayta yuklash xavfsiz: raqam + sana + talaba bo'yicha takrorlanmaydi (dedupe_key).
 */
class OrderImporter
{
    public const FIELDS = [
        'order_number' => ['label' => 'Buyruq raqami', 'required' => true, 'aliases' => ['buyruq raqami', 'raqami', 'номер', 'number']],
        'order_date' => ['label' => 'Buyruq sanasi', 'required' => true, 'aliases' => ['buyruq sanasi', 'sana', 'дата', 'date']],
        'order_type_raw' => ['label' => 'Buyruq turi', 'required' => true, 'aliases' => ['buyruq turi', 'turi', 'тип', 'type']],
        'student_hemis_id' => ['label' => 'Talaba HEMIS ID', 'required' => false, 'aliases' => ['hemis id', 'hemis_id']],
        'student_id_number' => ['label' => 'Talaba ID raqami', 'required' => false, 'aliases' => ['talaba id', 'id raqam', 'student id']],
        'full_name' => ['label' => 'F.I.Sh.', 'required' => false, 'aliases' => ['f.i.sh', 'fish', 'familiya', 'ф.и.о', 'full name']],
        'basis' => ['label' => 'Asos / izoh', 'required' => false, 'aliases' => ['asos', 'izoh', 'основание']],
    ];

    public function __construct(private StudentMatcher $matcher)
    {
    }

    public static function guessType(?string $raw): string
    {
        $value = ImportSheet::normalize($raw);
        foreach (config('monitoring.order_types', []) as $key => $type) {
            foreach ($type['keywords'] ?? [] as $keyword) {
                if ($value !== '' && str_contains($value, ImportSheet::normalize($keyword))) {
                    return $key;
                }
            }
        }

        return 'boshqa';
    }

    /**
     * @param  array<string, ?int>  $map      maydon → ustun indeksi
     * @param  array<string, string>  $typeMap  xom tur → normallashtirilgan tur
     */
    public function import(MonitoringDataImport $import, ImportSheet $sheet, array $map, array $typeMap = []): MonitoringDataImport
    {
        $types = array_keys(config('monitoring.order_types', []));
        $errors = [];
        $ok = 0;

        foreach ($sheet->rows as $i => $row) {
            $line = $i + 2; // 1-qator — sarlavha
            try {
                $number = ImportSheet::cell($row, $map['order_number'] ?? null);
                $date = ImportSheet::date($row[$map['order_date'] ?? -1] ?? null);
                $rawType = ImportSheet::cell($row, $map['order_type_raw'] ?? null);
                $hemisId = ImportSheet::cell($row, $map['student_hemis_id'] ?? null);
                $idNumber = ImportSheet::cell($row, $map['student_id_number'] ?? null);
                $fullName = ImportSheet::cell($row, $map['full_name'] ?? null);

                if (!$number || !$date || !$rawType) {
                    $errors[] = ['row' => $line, 'message' => 'Buyruq raqami, sanasi yoki turi bo\'sh / noto\'g\'ri.'];
                    continue;
                }
                if (!$hemisId && !$idNumber && !$fullName) {
                    $errors[] = ['row' => $line, 'message' => 'Talaba ko\'rsatilmagan (HEMIS ID, ID raqam yoki F.I.Sh.).'];
                    continue;
                }

                $type = $typeMap[$rawType] ?? self::guessType($rawType);
                if (!in_array($type, $types, true)) {
                    $type = 'boshqa';
                }

                [$studentHemisId] = $this->matcher->match($hemisId, $idNumber, null, $fullName);

                DB::transaction(function () use ($import, $number, $date, $type, $rawType, $studentHemisId, $hemisId, $idNumber, $fullName, $row, $map) {
                    $order = MonitoringOrder::updateOrCreate(
                        ['dedupe_key' => MonitoringOrder::makeDedupeKey($number, $date, $studentHemisId ?? $hemisId, $idNumber, $fullName)],
                        [
                            'data_import_id' => $import->id,
                            'order_number' => $number,
                            'order_date' => $date,
                            'order_type' => $type,
                            'order_type_raw' => mb_substr($rawType, 0, 255),
                            'student_hemis_id' => $studentHemisId,
                            'student_id_number' => $idNumber,
                            'full_name' => $fullName ?? DB::table('students')->where('hemis_id', $studentHemisId)->value('full_name'),
                            'basis' => ($b = ImportSheet::cell($row, $map['basis'] ?? null)) ? mb_substr($b, 0, 500) : null,
                        ]
                    );

                    // E-7: ko'chirib kelgan talabaga yig'majild cheklisti ochiladi.
                    if ($type === 'kochirib_kelish' && !MonitoringTransferChecklist::where('order_id', $order->id)->exists()) {
                        MonitoringTransferChecklist::create([
                            'order_id' => $order->id,
                            'student_hemis_id' => $order->student_hemis_id,
                            'student_id_number' => $order->student_id_number,
                            'full_name' => $order->full_name ?? '—',
                            'arrived_at' => $order->order_date,
                            'items' => [],
                        ]);
                    }
                });
                $ok++;
            } catch (Throwable $e) {
                $errors[] = ['row' => $line, 'message' => mb_substr($e->getMessage(), 0, 300)];
            }
        }

        $import->forceFill([
            'status' => MonitoringDataImport::STATUS_DONE,
            'rows_total' => count($sheet->rows),
            'rows_ok' => $ok,
            'rows_error' => count($errors),
            'errors' => array_slice($errors, 0, 500),
        ])->save();

        return $import;
    }
}
