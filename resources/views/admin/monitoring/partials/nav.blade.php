@php
    $monActor = \App\Services\Monitoring\MonitoringAccess::actor();
    $monViewAll = \App\Services\Monitoring\MonitoringAccess::canViewAll($monActor);
    $monMine = \App\Services\Monitoring\MonitoringAccess::openAssignedCount($monActor);
    $monTabs = array_filter([
        $monViewAll ? ['admin.monitoring.index', 'Panel', 'admin.monitoring.index'] : null,
        $monViewAll ? ['admin.monitoring.istisnolar.index', 'Istisnolar', 'admin.monitoring.istisnolar.*'] : null,
        ['admin.monitoring.mine', 'Menga biriktirilganlar' . ($monMine ? " ({$monMine})" : ''), 'admin.monitoring.mine'],
        $monViewAll ? ['admin.monitoring.detektorlar.index', 'Detektorlar', 'admin.monitoring.detektorlar.*'] : null,
        $monViewAll ? ['admin.monitoring.buyruqlar.index', 'Buyruqlar va xabarnomalar', 'admin.monitoring.buyruqlar.*'] : null,
        $monViewAll ? ['admin.monitoring.tavsiyalar.index', 'Tavsiya etilganlar (A-5)', 'admin.monitoring.tavsiyalar.*'] : null,
        $monViewAll ? ['admin.monitoring.yigmajild.index', "Yig'majild (E-7)", 'admin.monitoring.yigmajild.*'] : null,
        $monViewAll ? ['admin.monitoring.importlar.index', 'Importlar', 'admin.monitoring.importlar.*'] : null,
    ]);
@endphp
<div class="mb-4 flex flex-wrap gap-2 border-b border-gray-200 pb-2">
    @foreach($monTabs as [$route, $label, $pattern])
        <a href="{{ route($route) }}"
           class="px-3 py-1.5 text-sm rounded-md {{ request()->routeIs($pattern) ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}"
           style="text-decoration: none;">{{ $label }}</a>
    @endforeach
</div>

@if (session('success'))
    <div class="p-3 mb-4 text-sm text-green-700 bg-green-100 rounded-lg">{{ session('success') }}</div>
@endif
@if (session('error'))
    <div class="p-3 mb-4 text-sm text-red-700 bg-red-100 rounded-lg">{{ session('error') }}</div>
@endif
@if ($errors->any())
    <div class="p-3 mb-4 text-sm text-red-700 bg-red-100 rounded-lg">
        @foreach ($errors->all() as $error)
            <div>{{ $error }}</div>
        @endforeach
    </div>
@endif
