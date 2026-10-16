<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">Import — ustunlarni moslash</h2>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            @include('admin.monitoring.partials.nav')

            <p class="text-sm text-gray-600 mb-3">
                Fayl: <b>{{ $import->file_name }}</b> · {{ $import->rows_total }} ta qator ·
                {{ \App\Models\MonitoringDataImport::KINDS[$import->kind] ?? $import->kind }}
                @if($import->kind === 'tavsiya_etilganlar') · o'quv yili {{ data_get($import->meta, 'education_year') }} @endif
            </p>

            <form method="POST" action="{{ route('admin.monitoring.importlar.process', $import) }}"
                  x-data="{ typeCol: '{{ $guess['order_type_raw'] ?? '' }}' }" class="space-y-6">
                @csrf
                <div class="bg-white rounded-lg shadow p-5">
                    <h3 class="font-semibold mb-3 text-sm">Har bir maydon uchun fayldagi ustunni tanlang</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                        @foreach($fields as $key => $field)
                            <div>
                                <label class="block text-gray-600">{{ $field['label'] }} @if($field['required'])<span class="text-red-600">*</span>@endif</label>
                                <select name="map[{{ $key }}]" class="w-full border-gray-300 rounded" @if($key === 'order_type_raw') x-model="typeCol" @endif>
                                    <option value="">— yo'q —</option>
                                    @foreach($headers as $i => $header)
                                        <option value="{{ $i }}" @selected(($guess[$key] ?? null) === $i)>{{ $header !== '' ? $header : ($i + 1) . '-ustun' }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endforeach
                    </div>
                </div>

                @if($import->kind === 'buyruqlar')
                    <div class="bg-white rounded-lg shadow p-5 text-sm">
                        <h3 class="font-semibold mb-1">Buyruq turlarini moslash</h3>
                        <p class="text-gray-600 mb-3">Fayldagi har bir tur nomi qaysi turga tegishli? Taxmin avtomatik qilingan — tekshirib chiqing.
                            Jadval «Buyruq turi» ustuni tanlanganda ko'rinadi.</p>
                        @foreach($distinct as $col => $values)
                            <table class="min-w-full mb-2" x-show="typeCol === '{{ $col }}'" x-cloak>
                                @foreach($values as $value)
                                    <tr class="border-t">
                                        <td class="py-1 pr-4">{{ $value }}</td>
                                        <td class="py-1">
                                            <select name="type_map[{{ $col }}][{{ $value }}]" class="border-gray-300 rounded">
                                                @foreach($orderTypes as $typeKey => $typeLabel)
                                                    <option value="{{ $typeKey }}" @selected(($typeGuesses[$col][$value] ?? 'boshqa') === $typeKey)>{{ $typeLabel }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                    </tr>
                                @endforeach
                            </table>
                        @endforeach
                    </div>
                @endif

                <div class="bg-white rounded-lg shadow p-5 overflow-x-auto">
                    <h3 class="font-semibold mb-2 text-sm">Namuna (birinchi 5 qator)</h3>
                    <table class="min-w-full text-xs">
                        <thead><tr>@foreach($headers as $header)<th class="px-2 py-1 text-left bg-gray-50">{{ $header }}</th>@endforeach</tr></thead>
                        <tbody>
                        @foreach($sample as $row)
                            <tr class="border-t">@foreach(array_keys($headers) as $i)<td class="px-2 py-1">{{ $row[$i] ?? '' }}</td>@endforeach</tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded">Import qilish</button>
            </form>
        </div>
    </div>
</x-app-layout>
