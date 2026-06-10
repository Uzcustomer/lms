<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            O'quv reja solishtirma
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">

            <div class="mb-4 flex items-center justify-between flex-wrap gap-3">
                <a href="{{ route('admin.oquv-reja.index') }}" class="text-blue-600 hover:underline text-sm">&larr; O'quv reja to'g'riligi</a>
                <a href="{{ route('admin.oquv-reja.compare-export', ['reference_id' => $reference->id, 'working_id' => $working->id]) }}"
                   class="px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-md hover:bg-green-700">
                    Excelga yuklab olish
                </a>
            </div>

            <div class="bg-white shadow-sm rounded-lg p-4 mb-6">
                <div class="text-sm text-gray-600">
                    <span class="font-semibold text-blue-700">{{ $reference->name }}</span>
                    (namunaviy)
                    <span class="mx-2">&harr;</span>
                    <span class="font-semibold text-purple-700">{{ $working->name }}</span>
                    (ishchi)
                </div>
            </div>

            @php
                $statusClasses = [
                    \App\Services\CurriculumComparisonService::STATUS_OK => 'bg-green-100 text-green-800',
                    \App\Services\CurriculumComparisonService::STATUS_NAME => 'bg-yellow-100 text-yellow-800',
                    \App\Services\CurriculumComparisonService::STATUS_HOURS => 'bg-red-100 text-red-800',
                    \App\Services\CurriculumComparisonService::STATUS_CREDIT => 'bg-red-100 text-red-800',
                    \App\Services\CurriculumComparisonService::STATUS_HOURS_CREDIT => 'bg-red-100 text-red-800',
                    \App\Services\CurriculumComparisonService::STATUS_MISSING_IN_WORKING => 'bg-red-200 text-red-900',
                    \App\Services\CurriculumComparisonService::STATUS_MISSING_IN_REFERENCE => 'bg-purple-100 text-purple-800',
                ];
                $fmt = fn($v) => $v === null ? '—' : rtrim(rtrim(number_format((float) $v, 2, '.', ' '), '0'), '.');
                $fmtDiff = function ($v) {
                    if ($v === null) return '—';
                    if (abs($v) < 0.011) return '0';
                    $s = rtrim(rtrim(number_format(abs($v), 2, '.', ' '), '0'), '.');
                    return ($v > 0 ? '+' : '-') . $s;
                };
            @endphp

            {{-- Holatlar statistikasi --}}
            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-3 mb-6">
                @foreach($statusClasses as $status => $class)
                    <div class="bg-white shadow-sm rounded-lg p-3">
                        <div class="text-xs text-gray-500">{{ $status }}</div>
                        <div class="text-xl font-semibold {{ ($comparison['stats'][$status] ?? 0) > 0 && $status !== \App\Services\CurriculumComparisonService::STATUS_OK ? 'text-red-600' : 'text-gray-900' }}">
                            {{ $comparison['stats'][$status] ?? 0 }}
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">T/r</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">Blok</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">Fan nomi (namunaviy)</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">Ishchi rejadagi nomi (farqli bo'lsa)</th>
                            <th class="px-3 py-2 text-right font-medium text-gray-600">Nam. soat</th>
                            <th class="px-3 py-2 text-right font-medium text-gray-600">Ishchi soat</th>
                            <th class="px-3 py-2 text-right font-medium text-gray-600">Soat farqi</th>
                            <th class="px-3 py-2 text-right font-medium text-gray-600">Nam. kredit</th>
                            <th class="px-3 py-2 text-right font-medium text-gray-600">Ishchi kredit</th>
                            <th class="px-3 py-2 text-right font-medium text-gray-600">Kredit farqi</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">Kurs(lar)</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">Semestr(lar)</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">Holati</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">Izoh</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                        @foreach($comparison['rows'] as $row)
                            <tr class="hover:bg-gray-50 {{ $row['status'] === \App\Services\CurriculumComparisonService::STATUS_OK ? '' : 'bg-red-50/40' }}">
                                <td class="px-3 py-2">{{ $loop->iteration }}</td>
                                <td class="px-3 py-2 text-gray-500">{{ $row['block'] }}</td>
                                <td class="px-3 py-2">{{ $row['ref_name'] ?? '—' }}</td>
                                <td class="px-3 py-2 text-amber-700">
                                    {{ $row['name_differs'] || $row['ref_name'] === null ? $row['work_name'] : '' }}
                                </td>
                                <td class="px-3 py-2 text-right">{{ $fmt($row['ref_hours']) }}</td>
                                <td class="px-3 py-2 text-right">{{ $fmt($row['work_hours']) }}</td>
                                <td class="px-3 py-2 text-right {{ ($row['hours_diff'] ?? 0) != 0 ? 'font-semibold text-red-600' : '' }}">{{ $fmtDiff($row['hours_diff']) }}</td>
                                <td class="px-3 py-2 text-right">{{ $fmt($row['ref_credit']) }}</td>
                                <td class="px-3 py-2 text-right">{{ $fmt($row['work_credit']) }}</td>
                                <td class="px-3 py-2 text-right {{ ($row['credit_diff'] ?? 0) != 0 ? 'font-semibold text-red-600' : '' }}">{{ $fmtDiff($row['credit_diff']) }}</td>
                                <td class="px-3 py-2">{{ $row['kurslar'] }}</td>
                                <td class="px-3 py-2">{{ $row['semestrlar'] }}</td>
                                <td class="px-3 py-2">
                                    <span class="px-2 py-1 rounded text-xs font-medium whitespace-nowrap {{ $statusClasses[$row['status']] ?? 'bg-gray-100 text-gray-700' }}">
                                        {{ $row['status'] }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-gray-500">{{ $row['note'] }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                        <tfoot class="bg-gray-50 font-semibold">
                        <tr>
                            <td class="px-3 py-2" colspan="4">JAMI</td>
                            <td class="px-3 py-2 text-right">{{ $fmt($comparison['totals']['ref_hours']) }}</td>
                            <td class="px-3 py-2 text-right">{{ $fmt($comparison['totals']['work_hours']) }}</td>
                            <td class="px-3 py-2 text-right {{ $comparison['totals']['hours_diff'] != 0 ? 'text-red-600' : '' }}">{{ $fmtDiff($comparison['totals']['hours_diff']) }}</td>
                            <td class="px-3 py-2 text-right">{{ $fmt($comparison['totals']['ref_credit']) }}</td>
                            <td class="px-3 py-2 text-right">{{ $fmt($comparison['totals']['work_credit']) }}</td>
                            <td class="px-3 py-2 text-right {{ $comparison['totals']['credit_diff'] != 0 ? 'text-red-600' : '' }}">{{ $fmtDiff($comparison['totals']['credit_diff']) }}</td>
                            <td class="px-3 py-2" colspan="4"></td>
                        </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
