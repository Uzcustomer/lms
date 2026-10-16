<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">Monitoring — Registrator ofisi nazorati</h2>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            @include('admin.monitoring.partials.nav')

            @if($warningDetectors->isNotEmpty())
                <div class="p-3 mb-4 text-sm bg-yellow-50 border border-yellow-300 rounded-lg">
                    <b>Sinov rejimi:</b> {{ $warningDetectors->implode(', ') }} — ogohlantirish rejimida. Topilgan istisnolar shu yerda
                    ko'rinadi, lekin mas'ullarga hali biriktirilmaydi va xabar yuborilmaydi. Natijalar to'g'ri bo'lsa, detektor
                    sozlamasida «Faqat ogohlantirish» belgisini olib tashlang — keyingi ishga tushirishda istisnolar mas'ullarga
                    biriktiriladi va xabar boradi.
                </div>
            @endif

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                @foreach([
                    ['ochiq', 'Ochiq istisnolar', 'text-orange-700', 'yuborilgan'],
                    ['muddati_otgan', "Muddati o'tgan", 'text-red-700', 'muddati_otgan'],
                    ['shu_hafta_yopilgan', 'Shu hafta yopilgan', 'text-green-700', 'yopildi'],
                    ['ogohlantirish', 'Ogohlantirish (prognoz)', 'text-yellow-700', 'ogohlantirish'],
                ] as [$key, $label, $color, $filter])
                    <a href="{{ route('admin.monitoring.istisnolar.index', ['holat' => $filter]) }}" class="block p-4 bg-white rounded-lg shadow hover:shadow-md" style="text-decoration:none;">
                        <div class="text-sm text-gray-500">{{ $label }}</div>
                        <div class="text-3xl font-bold {{ $color }}">{{ $cards[$key] }}</div>
                    </a>
                @endforeach
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <div class="bg-white rounded-lg shadow p-4">
                    <h3 class="font-semibold mb-3">Detektor guruhlari kesimida (ochiq)</h3>
                    <table class="min-w-full text-sm">
                        <thead><tr class="text-left text-gray-500"><th class="py-1">Guruh</th><th class="py-1 text-right">Ochiq</th><th class="py-1 text-right">Ogohlantirish</th><th class="py-1 text-right">Muddati o'tgan</th></tr></thead>
                        <tbody>
                        @forelse($byGroup as $row)
                            <tr class="border-t">
                                <td class="py-1"><a href="{{ route('admin.monitoring.istisnolar.index', ['guruh' => $row->group_name]) }}">{{ $row->group_name }}</a></td>
                                <td class="py-1 text-right">{{ $row->open_count }}</td>
                                <td class="py-1 text-right text-yellow-700">{{ $row->warning_count }}</td>
                                <td class="py-1 text-right {{ $row->overdue_count ? 'text-red-700 font-semibold' : '' }}">{{ $row->overdue_count }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-2 text-gray-500">Ochiq istisno yo'q.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="bg-white rounded-lg shadow p-4">
                    <h3 class="font-semibold mb-3">Fakultetlar kesimida (ochiq)</h3>
                    <table class="min-w-full text-sm">
                        <thead><tr class="text-left text-gray-500"><th class="py-1">Fakultet</th><th class="py-1 text-right">Ochiq</th><th class="py-1 text-right">Ogohlantirish</th><th class="py-1 text-right">Muddati o'tgan</th></tr></thead>
                        <tbody>
                        @forelse($byFaculty as $row)
                            <tr class="border-t">
                                <td class="py-1">
                                    @if($row->faculty_hemis_id)
                                        <a href="{{ route('admin.monitoring.istisnolar.index', ['fakultet' => $row->faculty_hemis_id]) }}">{{ $row->faculty_name ?? $row->faculty_hemis_id }}</a>
                                    @else
                                        <span class="text-gray-500">Bo'linmasiz (umumiy)</span>
                                    @endif
                                </td>
                                <td class="py-1 text-right">{{ $row->open_count }}</td>
                                <td class="py-1 text-right text-yellow-700">{{ $row->warning_count }}</td>
                                <td class="py-1 text-right {{ $row->overdue_count ? 'text-red-700 font-semibold' : '' }}">{{ $row->overdue_count }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-2 text-gray-500">Ochiq istisno yo'q.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-4 mb-6">
                <h3 class="font-semibold mb-3">Ishlayotgan detektorlar</h3>
                <table class="min-w-full text-sm">
                    <thead><tr class="text-left text-gray-500"><th class="py-1">Kod</th><th class="py-1">Qoida</th><th class="py-1 text-right">Ochiq</th><th class="py-1 text-right">Ogohlantirish</th><th class="py-1">Oxirgi ishga tushish</th></tr></thead>
                    <tbody>
                    @forelse($byDetector as $d)
                        <tr class="border-t">
                            <td class="py-1 font-semibold"><a href="{{ route('admin.monitoring.istisnolar.index', ['detector_id' => $d->id]) }}">{{ $d->code }}</a></td>
                            <td class="py-1">{{ $d->title }} @if($d->warning_mode)<span class="text-xs text-yellow-700">(ogohlantirish rejimi)</span>@endif</td>
                            <td class="py-1 text-right">{{ $d->open_count }}</td>
                            <td class="py-1 text-right text-yellow-700">{{ $d->warning_count }}</td>
                            <td class="py-1">
                                @if($d->lastRun)
                                    {{ $d->lastRun->started_at->format('d.m.Y H:i') }} —
                                    <span class="{{ $d->lastRun->status === 'xato' ? 'text-red-700' : 'text-green-700' }}">{{ $d->lastRun->status }}</span>
                                @else
                                    <span class="text-gray-500">hali ishga tushmagan</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-2 text-gray-500">Yoqilgan detektor yo'q.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-white rounded-lg shadow p-4">
                    <h3 class="font-semibold mb-3">Oxirgi ishga tushirishlar</h3>
                    <table class="min-w-full text-sm">
                        <tbody>
                        @forelse($lastRuns as $run)
                            <tr class="border-t">
                                <td class="py-1">{{ $run->started_at->format('d.m H:i') }}</td>
                                <td class="py-1 font-semibold">{{ $run->detector?->code }}</td>
                                <td class="py-1">
                                    @if($run->status === 'xato')
                                        <span class="text-red-700" title="{{ $run->error_message }}">xato</span>
                                    @else
                                        topildi {{ $run->found_count }}, yangi {{ $run->opened_count }}, yopildi {{ $run->closed_count }}
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td class="py-2 text-gray-500">Hali ishga tushirilmagan.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="bg-white rounded-lg shadow p-4">
                    <h3 class="font-semibold mb-3">Oxirgi importlar</h3>
                    <table class="min-w-full text-sm">
                        <tbody>
                        @forelse($lastImports as $import)
                            <tr class="border-t">
                                <td class="py-1">{{ $import->created_at->format('d.m H:i') }}</td>
                                <td class="py-1">{{ \App\Models\MonitoringDataImport::KINDS[$import->kind] ?? $import->kind }}</td>
                                <td class="py-1">{{ $import->status }}: {{ $import->rows_ok }}/{{ $import->rows_total }}@if($import->rows_error) <span class="text-red-700">({{ $import->rows_error }} xato)</span>@endif</td>
                            </tr>
                        @empty
                            <tr><td class="py-2 text-gray-500">Import qilinmagan.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                    <p class="mt-3 text-xs text-gray-500">Yillik kalendar sanalari va oylik svod — 2-bosqichda.</p>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
