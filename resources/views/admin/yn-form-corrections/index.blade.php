<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Tuzatish dalolatnomalari
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <p class="text-sm text-gray-600 mb-4">
                    Yakuniy qilingan YN shakllaridan keyin kelgan sababli arizalar va boshqa tuzatishlar audit izi.
                </p>

                {{-- Statistika kartochkalari --}}
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                    <div class="rounded-lg border border-amber-200 bg-amber-50 p-4">
                        <div class="text-xs font-semibold text-amber-700 uppercase">Kechikkan sababli</div>
                        <div class="text-2xl font-bold text-amber-900 mt-1">{{ $stats['late_sababli'] }}</div>
                        <div class="text-xs text-amber-700 mt-1">PDF dalolatnoma kerak</div>
                    </div>
                    <div class="rounded-lg border border-blue-200 bg-blue-50 p-4">
                        <div class="text-xs font-semibold text-blue-700 uppercase">Yakuniy qilingan</div>
                        <div class="text-2xl font-bold text-blue-900 mt-1">{{ $stats['finalized'] }}</div>
                        <div class="text-xs text-blue-700 mt-1">Shakl yopilishlari</div>
                    </div>
                    <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                        <div class="text-xs font-semibold text-gray-700 uppercase">Jami yozuvlar</div>
                        <div class="text-2xl font-bold text-gray-900 mt-1">{{ $stats['total'] }}</div>
                    </div>
                </div>

                {{-- Filtrlar --}}
                <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-3 mb-4">
                    <select name="correction_type" class="rounded-lg border-gray-300 text-sm">
                        <option value="">— Tur (barchasi) —</option>
                        <option value="late_sababli" @selected(request('correction_type') === 'late_sababli')>Kechikkan sababli</option>
                        <option value="finalized" @selected(request('correction_type') === 'finalized')>Yakuniylash</option>
                        <option value="moved_to_qoshimcha" @selected(request('correction_type') === 'moved_to_qoshimcha')>Qo'shimchaga ko'chirilgan</option>
                    </select>
                    <select name="attempt" class="rounded-lg border-gray-300 text-sm">
                        <option value="">— Urinish (barchasi) —</option>
                        <option value="1" @selected(request('attempt') === '1')>Asosiy (12-shakl)</option>
                        <option value="2" @selected(request('attempt') === '2')>1-urinish (12a)</option>
                        <option value="3" @selected(request('attempt') === '3')>2-urinish (12b)</option>
                    </select>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Talaba / fan / guruh"
                           class="rounded-lg border-gray-300 text-sm" />
                    <div class="flex gap-2">
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700">
                            Filtr
                        </button>
                        <a href="{{ route('admin.yn-form-corrections.index') }}"
                           class="px-4 py-2 bg-gray-200 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-300">
                            Tozalash
                        </a>
                    </div>
                </form>

                {{-- Jadval --}}
                <div class="overflow-x-auto rounded-lg border border-gray-200">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="px-3 py-2 text-left font-semibold text-gray-700">Sana</th>
                                <th class="px-3 py-2 text-left font-semibold text-gray-700">Tur</th>
                                <th class="px-3 py-2 text-left font-semibold text-gray-700">Urinish</th>
                                <th class="px-3 py-2 text-left font-semibold text-gray-700">Talaba</th>
                                <th class="px-3 py-2 text-left font-semibold text-gray-700">Fan</th>
                                <th class="px-3 py-2 text-left font-semibold text-gray-700">Guruh</th>
                                <th class="px-3 py-2 text-left font-semibold text-gray-700">Sabab</th>
                                <th class="px-3 py-2 text-left font-semibold text-gray-700">Kim</th>
                                <th class="px-3 py-2 text-center font-semibold text-gray-700">Amal</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-100">
                            @forelse($corrections as $c)
                                @php
                                    $typeColors = [
                                        'late_sababli' => 'bg-amber-100 text-amber-800',
                                        'finalized' => 'bg-blue-100 text-blue-800',
                                        'moved_to_qoshimcha' => 'bg-violet-100 text-violet-800',
                                        'removed_from_12a' => 'bg-green-100 text-green-800',
                                        'removed_from_12b' => 'bg-green-100 text-green-800',
                                    ];
                                    $typeLabels = [
                                        'late_sababli' => 'Kechikkan sababli',
                                        'finalized' => 'Yakuniylash',
                                        'moved_to_qoshimcha' => 'Qo\'shimchaga',
                                        'removed_from_12a' => '12a dan chiqdi',
                                        'removed_from_12b' => '12b dan chiqdi',
                                    ];
                                    $typeLabel = $typeLabels[$c->correction_type] ?? $c->correction_type;
                                    $typeColor = $typeColors[$c->correction_type] ?? 'bg-gray-100 text-gray-800';
                                    $attemptLabel = match ((int)($c->attempt ?? 1)) { 2 => '12a', 3 => '12b', default => 'Asosiy' };
                                @endphp
                                <tr class="hover:bg-gray-50">
                                    <td class="px-3 py-2 text-gray-900 whitespace-nowrap">
                                        {{ $c->performed_at ? \Carbon\Carbon::parse($c->performed_at)->format('d.m.Y H:i') : '-' }}
                                    </td>
                                    <td class="px-3 py-2">
                                        <span class="inline-block px-2 py-0.5 rounded-full text-xs font-medium {{ $typeColor }}">{{ $typeLabel }}</span>
                                    </td>
                                    <td class="px-3 py-2 text-gray-700">{{ $attemptLabel }}</td>
                                    <td class="px-3 py-2 text-gray-900">
                                        @if($c->student_name)
                                            <div class="font-medium">{{ $c->student_name }}</div>
                                            <div class="text-[10px] text-gray-500">{{ $c->student_hemis_id }}</div>
                                        @else
                                            <span class="text-gray-400">— umumiy —</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-gray-700">{{ $c->subject_name ?? '—' }}</td>
                                    <td class="px-3 py-2 text-gray-700">{{ $c->group_name ?? '—' }}</td>
                                    <td class="px-3 py-2 text-gray-600 text-xs max-w-xs truncate" title="{{ $c->reason }}">
                                        {{ $c->reason }}
                                        @if($c->absence_excuse_id)
                                            <br><span class="text-[10px] text-amber-600">Ariza #{{ $c->absence_excuse_id }} ({{ $c->excuse_doc_number ?? '?' }})</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-gray-600 text-xs">{{ $c->performed_by_name ?? '—' }}</td>
                                    <td class="px-3 py-2 text-center">
                                        @if($c->correction_type === 'late_sababli')
                                            <a href="{{ route('admin.yn-form-corrections.pdf', $c->id) }}" target="_blank"
                                               class="inline-flex items-center px-3 py-1 bg-amber-600 text-white text-xs font-medium rounded hover:bg-amber-700">
                                                <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                                PDF
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="px-3 py-8 text-center text-gray-500">Hech qanday tuzatish topilmadi.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $corrections->links() }}
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
