<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $curriculum->typeLabel() }}: {{ $curriculum->name }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">

            @if(session('success'))
                <div class="mb-4 p-4 bg-green-100 border border-green-300 text-green-800 rounded-lg">{{ session('success') }}</div>
            @endif

            <div class="mb-4 flex items-center justify-between">
                <a href="{{ route('admin.oquv-reja.index') }}" class="text-blue-600 hover:underline text-sm">&larr; Barcha o'quv rejalar</a>
                <div class="flex gap-2">
                    <a href="{{ route('admin.oquv-reja.export', [$curriculum, 'format' => 'jadval']) }}"
                       class="inline-flex items-center gap-1 px-3 py-1.5 text-sm bg-white border border-gray-300 rounded-lg hover:bg-gray-50 text-gray-700">
                        ⬇ Jadval (xlsx)
                    </a>
                    <a href="{{ route('admin.oquv-reja.export', [$curriculum, 'format' => 'setka']) }}"
                       class="inline-flex items-center gap-1 px-3 py-1.5 text-sm bg-white border border-gray-300 rounded-lg hover:bg-gray-50 text-gray-700">
                        ⬇ Setka (xlsx)
                    </a>
                </div>
            </div>

            @php
                $totalHours  = $curriculum->subjects->sum(fn($s) =>
                    (float) ($s->total_hours ?? ((float)($s->audit_total ?? 0) + (float)($s->independent ?? 0))));
                $totalCredit = $curriculum->subjects->sum(fn($s) => (float) $s->credit);
                $fmt = fn($v) => $v === null ? '' : rtrim(rtrim(number_format((float) $v, 2, '.', ' '), '0'), '.');

                // Vizual guruhlash: ketma-ket bir xil (blok+kod+nom) qatorlarni birlashtirib ko'rsat
                $rows = [];
                $prevKey = null;
                $subjectNum = 0;
                foreach ($curriculum->subjects as $subject) {
                    $key = ($subject->block ?? '') . '|' . ($subject->subject_code ?? '') . '|' . $subject->subject_name;
                    $isContinuation = ($key === $prevKey);
                    if (!$isContinuation) {
                        $subjectNum++;
                    }
                    $rows[] = ['subject' => $subject, 'continuation' => $isContinuation, 'num' => $subjectNum];
                    $prevKey = $key;
                }
            @endphp

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-white shadow-sm rounded-lg p-4">
                    <div class="text-sm text-gray-500">Fan qatorlari</div>
                    <div class="text-2xl font-semibold text-gray-900">{{ $curriculum->subjects->count() }}</div>
                </div>
                <div class="bg-white shadow-sm rounded-lg p-4">
                    <div class="text-sm text-gray-500">Fanlar (nom bo'yicha)</div>
                    <div class="text-2xl font-semibold text-gray-900">{{ $curriculum->subjects->unique(fn($s) => mb_strtolower($s->subject_name))->count() }}</div>
                </div>
                <div class="bg-white shadow-sm rounded-lg p-4">
                    <div class="text-sm text-gray-500">Jami soat</div>
                    <div class="text-2xl font-semibold text-blue-600">{{ $fmt($totalHours) }}</div>
                </div>
                <div class="bg-white shadow-sm rounded-lg p-4">
                    <div class="text-sm text-gray-500">Jami kredit</div>
                    <div class="text-2xl font-semibold text-green-600">{{ $fmt($totalCredit) }}</div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">#</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">Blok</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">Fan kodi</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">Fan nomi</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">Namunaviy rejadagi nomi</th>
                            <th class="px-3 py-2 text-right font-medium text-gray-600">Kurs</th>
                            <th class="px-3 py-2 text-right font-medium text-gray-600">Semestr</th>
                            <th class="px-3 py-2 text-right font-medium text-gray-600">Umumiy soat</th>
                            <th class="px-3 py-2 text-right font-medium text-gray-600">Ma'ruza</th>
                            <th class="px-3 py-2 text-right font-medium text-gray-600">Amaliy</th>
                            <th class="px-3 py-2 text-right font-medium text-gray-600">Lab.</th>
                            <th class="px-3 py-2 text-right font-medium text-gray-600">Seminar</th>
                            <th class="px-3 py-2 text-right font-medium text-gray-600">Mustaqil</th>
                            <th class="px-3 py-2 text-right font-medium text-gray-600">Kredit</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">Izoh</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                        @foreach($rows as $item)
                            @php $subject = $item['subject']; $cont = $item['continuation']; @endphp
                            <tr class="{{ $cont ? 'bg-blue-50/40' : 'hover:bg-gray-50' }}
                                       {{ $cont ? 'border-l-2 border-blue-300' : '' }}">
                                {{-- # --}}
                                <td class="px-3 py-2 text-gray-400">
                                    @if(!$cont) {{ $item['num'] }} @else <span class="text-blue-400 pl-1">↳</span> @endif
                                </td>
                                {{-- Blok --}}
                                <td class="px-3 py-2 {{ $cont ? 'text-gray-300' : '' }}">
                                    @if(!$cont) {{ $subject->block }} @endif
                                </td>
                                {{-- Fan kodi --}}
                                <td class="px-3 py-2 {{ $cont ? 'text-gray-300' : '' }}">
                                    @if(!$cont) {{ $subject->subject_code }} @endif
                                </td>
                                {{-- Fan nomi --}}
                                <td class="px-3 py-2 {{ $cont ? 'text-gray-400 italic pl-6' : '' }}">
                                    @if(!$cont)
                                        {{ $subject->subject_name }}
                                    @else
                                        {{ $subject->subject_name }}
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-amber-700">
                                    @if(!$cont) {{ $subject->reference_name }} @endif
                                </td>
                                <td class="px-3 py-2 text-right">{{ $subject->kurs }}</td>
                                <td class="px-3 py-2 text-right font-medium {{ $cont ? 'text-blue-600' : '' }}">{{ $subject->semester }}</td>
                                <td class="px-3 py-2 text-right">{{ $fmt($subject->total_hours) }}</td>
                                <td class="px-3 py-2 text-right">{{ $fmt($subject->lecture) }}</td>
                                <td class="px-3 py-2 text-right">{{ $fmt($subject->practice) }}</td>
                                <td class="px-3 py-2 text-right">{{ $fmt($subject->laboratory) }}</td>
                                <td class="px-3 py-2 text-right">{{ $fmt($subject->seminar) }}</td>
                                <td class="px-3 py-2 text-right">{{ $fmt($subject->independent) }}</td>
                                <td class="px-3 py-2 text-right font-medium">{{ $fmt($subject->credit) }}</td>
                                <td class="px-3 py-2 text-gray-500 text-xs">{{ $subject->note }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
