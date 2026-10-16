<?php

namespace App\Http\Controllers\Admin\Monitoring;

use App\Http\Controllers\Controller;
use App\Jobs\RunMonitoringDetectorJob;
use App\Models\MonitoringDetector;
use App\Services\Monitoring\MonitoringAccess;
use Illuminate\Http\Request;

/**
 * Detektorlar sozlamasi (TZ 6.5): yoqish/o'chirish, parametrlar, standart mas'ul,
 * «hozir ishga tushir». O'zgarishlar MonitoringDetector (LogsActivity) orqali audit'ga yoziladi.
 */
class MonitoringDetectorController extends Controller
{
    public function index()
    {
        $actor = MonitoringAccess::actor();
        abort_unless(MonitoringAccess::canViewAll($actor), 403);

        $detectors = MonitoringDetector::query()
            ->withCount(['istisnolar as open_count' => fn ($q) => $q->open()])
            ->with('lastRun')
            ->orderBy('id')
            ->get()
            ->groupBy('group_name');

        return view('admin.monitoring.detektorlar.index', [
            'detectors' => $detectors,
            'canManage' => MonitoringAccess::canManage($actor),
        ]);
    }

    public function edit(MonitoringDetector $detector)
    {
        abort_unless(MonitoringAccess::canManage(MonitoringAccess::actor()), 403);

        $detector->load(['defaultOwner', 'lastRun']);
        $runs = $detector->runs()->latest('started_at')->limit(15)->get();

        return view('admin.monitoring.detektorlar.edit', [
            'detector' => $detector,
            'runs' => $runs,
            'paramFields' => $this->paramFields($detector),
        ]);
    }

    public function update(Request $request, MonitoringDetector $detector)
    {
        abort_unless(MonitoringAccess::canManage(MonitoringAccess::actor()), 403);

        $request->validate([
            'due_workdays' => 'required|integer|min:1|max:60',
            'severity' => 'required|in:' . implode(',', array_keys(MonitoringDetector::SEVERITIES)),
            'default_owner' => 'nullable|string|max:40',
            'params' => 'nullable|array',
        ]);

        $owner = MonitoringAccess::resolveOwnerKey($request->input('default_owner'));
        if ($request->filled('default_owner') && $owner === null) {
            return back()->withInput()->with('error', 'Tanlangan xodim topilmadi.');
        }

        $params = $detector->params ?? [];
        $input = (array) $request->input('params', []);
        foreach ($this->paramFields($detector) as $key => $field) {
            $params[$key] = match ($field['type']) {
                'bool' => !empty($input[$key]),
                'int' => (int) ($input[$key] ?? $field['value']),
                'float' => (float) ($input[$key] ?? $field['value']),
                'list' => array_values(array_filter(array_map('trim', explode(',', (string) ($input[$key] ?? ''))), fn ($v) => $v !== '')),
                'staff' => MonitoringAccess::ownerKey(MonitoringAccess::resolveOwnerKey($input[$key] ?? null)) ?? '',
                default => trim((string) ($input[$key] ?? '')),
            };
        }

        $detector->fill([
            'enabled' => $request->boolean('enabled'),
            'warning_mode' => $request->boolean('warning_mode'),
            'due_workdays' => (int) $request->input('due_workdays'),
            'severity' => $request->input('severity'),
            'default_owner_type' => $owner?->getMorphClass(),
            'default_owner_id' => $owner?->getKey(),
            'params' => $params,
        ])->save();

        return redirect()->route('admin.monitoring.detektorlar.edit', $detector)
            ->with('success', 'Sozlamalar saqlandi — keyingi ishga tushirishda kuchga kiradi.');
    }

    public function run(MonitoringDetector $detector)
    {
        $actor = MonitoringAccess::actor();
        abort_unless(MonitoringAccess::canManage($actor), 403);

        if (!$detector->isImplemented()) {
            return back()->with('error', "{$detector->code} detektori hali amalga oshirilmagan.");
        }

        RunMonitoringDetectorJob::dispatch($detector->id, $actor?->getMorphClass(), $actor?->getKey());

        return back()->with('success', "{$detector->code} navbatga qo'yildi. Natija bir necha daqiqada «Oxirgi ishga tushirishlar» jadvalida ko'rinadi.");
    }

    /**
     * Mavjud parametrlar turini qiymatidan aniqlaydi (bool, son, ro'yxat, xodim, matn).
     *
     * @return array<string, array{type: string, value: mixed, label: ?string}>
     */
    private function paramFields(MonitoringDetector $detector): array
    {
        $fields = [];
        foreach ((array) ($detector->params ?? []) as $key => $value) {
            $type = match (true) {
                str_starts_with($key, 'recipient_') => 'staff',
                is_bool($value) => 'bool',
                is_int($value) => 'int',
                is_float($value) => 'float',
                is_array($value) => 'list',
                default => 'string',
            };
            $fields[$key] = [
                'type' => $type,
                'value' => $value,
                'label' => $type === 'staff' ? MonitoringAccess::displayName(MonitoringAccess::resolveOwnerKey(is_string($value) ? $value : null)) : null,
            ];
        }

        return $fields;
    }
}
