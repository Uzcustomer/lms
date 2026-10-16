<?php

namespace App\Http\Controllers\Admin\Monitoring;

use App\Http\Controllers\Controller;
use App\Models\MonitoringDataImport;
use App\Models\MonitoringDetector;
use App\Models\MonitoringIstisno;
use App\Models\MonitoringRun;
use App\Services\Monitoring\MonitoringAccess;
use Illuminate\Support\Facades\DB;

/**
 * Administrator paneli (TZ 6.1): ochiq / muddati o'tgan / shu hafta yopilgan istisnolar,
 * detektor guruhlari va bo'linmalar kesimi, oxirgi run'lar va importlar.
 */
class MonitoringDashboardController extends Controller
{
    public function index()
    {
        $actor = MonitoringAccess::actor();
        if (!MonitoringAccess::canViewAll($actor)) {
            return redirect()->route('admin.monitoring.mine');
        }

        $real = MonitoringIstisno::query()->where('warning_only', false);

        $cards = [
            'ochiq' => (clone $real)->open()->count(),
            'muddati_otgan' => (clone $real)->overdue()->count(),
            'shu_hafta_yopilgan' => (clone $real)->whereIn('status', [MonitoringIstisno::STATUS_YOPILDI, MonitoringIstisno::STATUS_ASOSLI])
                ->where('closed_at', '>=', now()->startOfWeek())->count(),
            'ogohlantirish' => MonitoringIstisno::query()->open()->where('warning_only', true)->count(),
        ];

        // Ochiq — mas'ulga yuborilgan; ogohlantirish — sinov rejimidagi (faqat panelda ko'rinadi).
        $counts = 'SUM(CASE WHEN i.warning_only = 0 THEN 1 ELSE 0 END) as open_count, '
            . 'SUM(CASE WHEN i.warning_only = 1 THEN 1 ELSE 0 END) as warning_count, '
            . 'SUM(CASE WHEN i.warning_only = 0 AND i.due_at < ? THEN 1 ELSE 0 END) as overdue_count';

        $byGroup = DB::table('monitoring_istisnos as i')
            ->join('monitoring_detectors as d', 'd.id', '=', 'i.detector_id')
            ->whereIn('i.status', MonitoringIstisno::OPEN_STATUSES)
            ->groupBy('d.group_name')
            ->selectRaw("d.group_name, {$counts}", [now()])
            ->orderByDesc('open_count')
            ->orderByDesc('warning_count')
            ->get();

        $byFaculty = DB::table('monitoring_istisnos as i')
            ->leftJoin('departments as f', 'f.department_hemis_id', '=', 'i.faculty_hemis_id')
            ->whereIn('i.status', MonitoringIstisno::OPEN_STATUSES)
            ->groupBy('i.faculty_hemis_id', 'f.name')
            ->selectRaw("i.faculty_hemis_id, f.name as faculty_name, {$counts}", [now()])
            ->orderByDesc('open_count')
            ->orderByDesc('warning_count')
            ->get();

        $byDetector = MonitoringDetector::query()
            ->where('enabled', true)
            ->withCount([
                'istisnolar as open_count' => fn ($q) => $q->open()->where('warning_only', false),
                'istisnolar as warning_count' => fn ($q) => $q->open()->where('warning_only', true),
            ])
            ->with('lastRun')
            ->orderBy('code')
            ->get()
            ->filter(fn ($d) => $d->isImplemented());

        // Xavfsiz ishga tushirish: ogohlantirish rejimidagi yoqilgan detektorlar (mas'ullarga hali yuborilmaydi).
        $warningDetectors = $byDetector->filter(fn ($d) => $d->warning_mode)->pluck('code')->values();

        $lastRuns = MonitoringRun::with('detector')->latest('started_at')->limit(10)->get();
        $lastImports = MonitoringDataImport::latest()->limit(5)->get();

        return view('admin.monitoring.index', compact('cards', 'byGroup', 'byFaculty', 'byDetector', 'warningDetectors', 'lastRuns', 'lastImports'));
    }
}
