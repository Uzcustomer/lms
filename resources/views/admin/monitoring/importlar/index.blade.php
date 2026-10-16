<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">Monitoring — ma'lumot importi</h2>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            @include('admin.monitoring.partials.nav')

            @if($canWork)
                <div class="bg-white rounded-lg shadow p-5 mb-6 text-sm">
                    <h3 class="font-semibold mb-2">Yangi import</h3>
                    <ul class="list-disc ml-5 mb-3 text-gray-600">
                        <li><b>Buyruqlar reyestri</b> — HEMIS'dan eksport qilingan yoki qo'lda tuzilgan ro'yxat (D-2, E-1, A-5, E-7 uchun). Qayta yuklash xavfsiz: bir xil buyruq ikki marta yozilmaydi.</li>
                        <li><b>Tavsiya etilganlar (mandat)</b> — kontrakt bo'yicha tavsiya etilganlar (A-5). Mandat qog'ozda kelgan bo'lsa, ro'yxatni shablonga kiriting va mandat skanini dalil sifatida biriktiring.</li>
                    </ul>
                    <div class="mb-3 flex flex-wrap gap-3">
                        @foreach(\App\Models\MonitoringDataImport::KINDS as $kind => $label)
                            <a href="{{ route('admin.monitoring.importlar.template', $kind) }}" class="text-blue-700">Shablon: {{ $label }}</a>
                        @endforeach
                    </div>
                    <form method="POST" action="{{ route('admin.monitoring.importlar.upload') }}" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
                        @csrf
                        <div>
                            <label class="block text-gray-600">Turi</label>
                            <select name="kind" class="w-full border-gray-300 rounded">
                                @foreach(\App\Models\MonitoringDataImport::KINDS as $kind => $label)
                                    <option value="{{ $kind }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-gray-600">Fayl (Excel / CSV)</label>
                            <input type="file" name="file" required accept=".xlsx,.xls,.csv">
                        </div>
                        <div>
                            <label class="block text-gray-600">Dalil skani (ixtiyoriy)</label>
                            <input type="file" name="evidence" accept=".pdf,.jpg,.jpeg,.png">
                        </div>
                        <div>
                            <label class="block text-gray-600">O'quv yili (mandat uchun)</label>
                            <input type="text" name="education_year" value="{{ $defaultYear }}" class="w-full border-gray-300 rounded" placeholder="2026-2027">
                        </div>
                        <div class="md:col-span-4">
                            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded">Yuklash va ustunlarni moslash</button>
                        </div>
                    </form>
                </div>
            @endif

            <div class="bg-white rounded-lg shadow overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-left text-gray-600">
                    <tr><th class="px-3 py-2">Sana</th><th class="px-3 py-2">Turi</th><th class="px-3 py-2">Fayl</th><th class="px-3 py-2">Holat</th><th class="px-3 py-2">Qatorlar</th><th class="px-3 py-2">Kim</th></tr>
                    </thead>
                    <tbody>
                    @forelse($imports as $import)
                        <tr class="border-t align-top">
                            <td class="px-3 py-2 whitespace-nowrap">{{ $import->created_at->format('d.m.Y H:i') }}</td>
                            <td class="px-3 py-2">{{ \App\Models\MonitoringDataImport::KINDS[$import->kind] ?? $import->kind }}</td>
                            <td class="px-3 py-2">
                                {{ $import->file_name }}
                                @if($import->evidence_path)<div><a href="{{ route('admin.monitoring.importlar.evidence', $import) }}">📎 dalil</a></div>@endif
                            </td>
                            <td class="px-3 py-2">
                                {{ $import->status }}
                                @if($import->status === 'kutilmoqda' && $canWork)
                                    <div><a href="{{ route('admin.monitoring.importlar.mapping', $import) }}">ustunlarni moslash →</a></div>
                                @endif
                            </td>
                            <td class="px-3 py-2">
                                {{ $import->rows_ok }} / {{ $import->rows_total }}
                                @if($import->rows_error)
                                    <details class="text-red-700"><summary>{{ $import->rows_error }} xato</summary>
                                        <ul class="text-xs">@foreach(array_slice($import->errors ?? [], 0, 50) as $e)<li>{{ $e['row'] }}-qator: {{ $e['message'] }}</li>@endforeach</ul>
                                    </details>
                                @endif
                            </td>
                            <td class="px-3 py-2">{{ \App\Services\Monitoring\MonitoringAccess::displayName($import->importedBy) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-3 py-4 text-gray-500">Import qilinmagan.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $imports->links() }}</div>
        </div>
    </div>
</x-app-layout>
