<?php

namespace App\Services\Monitoring\Detectors;

use App\Models\MonitoringDetector;
use App\Models\MonitoringNoticeLog;
use App\Models\MonitoringOrder;
use App\Services\Monitoring\Contracts\Detector;
use App\Services\Monitoring\DetectorResult;
use App\Services\Monitoring\Finding;
use App\Services\Monitoring\Imports\ImportSheet;
use App\Support\WorkdayCalculator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * E-1. Akademik ta'til / chetlashtirish / kursda qoldirish buyrug'i → 1 ish kunida ijara
 * kotibi va buxgalteriyaga xabar. (Ijara buyrug'i 7-band; VMQ 605 33)
 *
 * Trigger: buyruqlar reyestridagi order_types turidagi buyruqlar (since_date dan keyingi),
 *          faqat ijarada yashovchi talabalar bo'yicha (buyurtmachi qarori, 16.10.2026).
 *          Ijara HEMIS turar joyi bo'yicha aniqlanadi: accommodation_codes berilgan bo'lsa kod,
 *          aks holda accommodation_keywords nomda bo'lsa. Talaba HEMIS'da topilmasa — tekshiriladi
 *          (turar joyi noma'lum, o'tkazib yuborilmasin).
 * Kutilgan harakat: har bir qabul qiluvchiga (recipients) xabarnoma yozuvi (M3), sent_at ≤ muddat.
 */
class E1OrderNotice implements Detector
{
    public function run(array $params, MonitoringDetector $detector): DetectorResult
    {
        $workdays = max(0, (int) ($params['workdays'] ?? 1));
        $types = (array) ($params['order_types'] ?? ['akademik_tatil', 'chetlashtirish', 'kursda_qoldirish']);
        $recipients = (array) ($params['recipients'] ?? ['ijara_kotibi', 'buxgalteriya']);
        $since = !empty($params['since_date']) ? Carbon::parse($params['since_date'])->toDateString() : null;
        $keepLate = ($params['late_completion'] ?? 'auto_close') === 'keep_open';
        $now = now();

        $result = new DetectorResult();

        $orders = MonitoringOrder::whereIn('order_type', $types)
            ->when($since, fn ($q) => $q->where('order_date', '>=', $since))
            ->orderBy('order_date')
            ->get();
        if ($orders->isEmpty()) {
            return $result;
        }

        $firstSent = MonitoringNoticeLog::whereIn('order_id', $orders->pluck('id'))
            ->selectRaw('order_id, recipient_kind, MIN(sent_at) as first_sent_at')
            ->groupBy('order_id', 'recipient_kind')
            ->get()
            ->groupBy('order_id');

        $students = DB::table('students')
            ->whereIn('hemis_id', $orders->pluck('student_hemis_id')->filter()->all())
            ->get(['hemis_id', 'department_id', 'accommodation_code', 'accommodation_name'])
            ->keyBy('hemis_id');

        foreach ($orders as $order) {
            $student = $order->student_hemis_id ? $students->get($order->student_hemis_id) : null;
            $applies = self::appliesTo($student, $params);
            if ($applies === false) {
                continue; // ijarada yashamaydi — xabarnoma talab qilinmaydi
            }

            $deadline = WorkdayCalculator::addWorkdays($order->order_date, $workdays)->endOfDay();
            if ($now->lte($deadline)) {
                continue;
            }

            $sent = collect($firstSent->get($order->id, []))->keyBy('recipient_kind');
            $missing = [];
            $late = [];
            foreach ($recipients as $kind) {
                $log = $sent->get($kind);
                if ($log === null) {
                    $missing[] = MonitoringNoticeLog::RECIPIENT_KINDS[$kind] ?? $kind;
                } elseif (Carbon::parse($log->first_sent_at)->gt($deadline)) {
                    $late[] = MonitoringNoticeLog::RECIPIENT_KINDS[$kind] ?? $kind;
                }
            }

            if ($missing === [] && (!$keepLate || $late === [])) {
                continue;
            }

            $faculty = $student?->department_id;

            $result->add(new Finding(
                fingerprint: "E-1:{$order->id}",
                subjectType: 'buyruq',
                subjectLabel: "Buyruq № {$order->order_number} ({$order->order_date->format('d.m.Y')}, {$order->typeLabel()}) — {$order->full_name}",
                subjectRef: (string) $order->id,
                details: [
                    'buyruq_raqami' => $order->order_number,
                    'buyruq_sanasi' => $order->order_date->toDateString(),
                    'turi' => $order->typeLabel(),
                    'muddat' => $deadline->toDateTimeString(),
                    'turar_joy' => $student?->accommodation_name ?? 'noma\'lum (talaba HEMIS\'da topilmadi)',
                    'xabar_berilmagan' => $missing,
                    'kechikib_xabar_berilgan' => $late,
                ],
                facultyHemisId: $faculty ? (int) $faculty : null,
            ));
        }

        return $result;
    }

    /**
     * Qoida shu talabaga qo'llanadimi: true — ijarada, false — ijarada emas, null — noma'lum
     * (talaba topilmagan). Kod ham, kalit so'z ham berilmasa — barcha talabalarga qo'llanadi.
     */
    public static function appliesTo(?object $student, array $params): ?bool
    {
        $codes = array_map('strval', (array) ($params['accommodation_codes'] ?? []));
        $keywords = (array) ($params['accommodation_keywords'] ?? []);
        if ($codes === [] && $keywords === []) {
            return true;
        }
        if ($student === null) {
            return null;
        }

        if ($codes !== [] && in_array((string) $student->accommodation_code, $codes, true)) {
            return true;
        }
        $name = ImportSheet::normalize($student->accommodation_name ?? '');
        foreach ($keywords as $keyword) {
            if ($name !== '' && str_contains($name, ImportSheet::normalize((string) $keyword))) {
                return true;
            }
        }

        return false;
    }
}
