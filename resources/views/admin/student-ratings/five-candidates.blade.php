<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">5 ga da'vogar talabalar</h2>
            <a href="{{ route('admin.student-ratings.index') }}" class="text-sm text-blue-600 hover:underline">&larr; Talabalar reytingi</a>
        </div>
    </x-slot>

    <div class="bg-white shadow rounded-lg p-5 mb-6">
        <form method="GET" action="{{ route('admin.student-ratings.five-candidates') }}" class="flex flex-wrap items-end gap-4">
            <div class="w-[160px]">
                <label for="fc-from" class="block text-sm font-medium text-gray-700 mb-1">Davr boshi</label>
                <input type="date" id="fc-from" name="from" value="{{ $from }}" class="w-full border-gray-300 rounded-lg text-sm">
            </div>
            <div class="w-[160px]">
                <label for="fc-to" class="block text-sm font-medium text-gray-700 mb-1">Davr oxiri</label>
                <input type="date" id="fc-to" name="to" value="{{ $to }}" class="w-full border-gray-300 rounded-lg text-sm">
            </div>
            <div class="flex-1 min-w-[220px]">
                <label for="fc-faculty" class="block text-sm font-medium text-gray-700 mb-1">Fakultet</label>
                <select id="fc-faculty" name="faculty" class="w-full border-gray-300 rounded-lg text-sm">
                    <option value="">Barcha fakultetlar</option>
                    @foreach($faculties as $f)
                        <option value="{{ $f }}" {{ $faculty === $f ? 'selected' : '' }}>{{ $f }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700 font-medium">Ko'rsatish</button>
            <a href="{{ route('admin.student-ratings.five-candidates.export', request()->query() + ['from' => $from, 'to' => $to]) }}"
               class="px-4 py-2 bg-green-600 text-white rounded-lg text-sm hover:bg-green-700 font-medium flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                Excel (kunlik baholar)
            </a>
        </form>
        <div class="mt-3 text-xs text-gray-500">
            Jami: {{ $total }} ta talaba · davr: {{ \Carbon\Carbon::parse($from)->format('d.m.Y') }} – {{ \Carbon\Carbon::parse($to)->format('d.m.Y') }}.
            Joriy baholar — shu davrdagi kunlik baholar o'rtachasi (fan bo'yicha); 90 dan past — qizil.
            Excelda har fakultet alohida varaqda, har bir baho sanasi va turi (joriy, MT, ON, OSKI, Test) bilan.
        </div>
    </div>

    @forelse($byFaculty as $facultyName => $students)
        <div class="bg-white shadow rounded-lg mb-6 overflow-hidden">
            <div class="px-5 py-3 border-b bg-slate-50 flex items-center justify-between">
                <h3 class="font-semibold text-gray-800">{{ $facultyName }}</h3>
                <span class="text-xs text-gray-500">{{ $students->count() }} ta talaba</span>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-gray-600 text-xs uppercase">
                        <tr>
                            <th class="px-3 py-2 text-left w-10">№</th>
                            <th class="px-3 py-2 text-left">F.I.Sh.</th>
                            <th class="px-3 py-2 text-left">Guruh</th>
                            <th class="px-3 py-2 text-left">Yo'nalish</th>
                            <th class="px-3 py-2 text-left">Semestr</th>
                            <th class="px-3 py-2 text-center">JN reyting</th>
                            <th class="px-3 py-2 text-left">Joriy baholar (fan bo'yicha o'rtacha)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($students as $i => $s)
                            @php $subs = $averages[$s->hemis_id] ?? []; $jn = $ratings[$s->hemis_id] ?? null; @endphp
                            <tr class="align-top hover:bg-blue-50/40">
                                <td class="px-3 py-2 text-gray-500">{{ $loop->iteration }}</td>
                                <td class="px-3 py-2 font-medium text-gray-800">{{ $s->full_name }}</td>
                                <td class="px-3 py-2 whitespace-nowrap">{{ $s->group_name }}</td>
                                <td class="px-3 py-2">{{ $s->specialty_name }}</td>
                                <td class="px-3 py-2 whitespace-nowrap">{{ $s->semester_name }}</td>
                                <td class="px-3 py-2 text-center font-semibold {{ $jn !== null && $jn < 90 ? 'text-red-600' : 'text-gray-800' }}">
                                    {{ $jn !== null ? number_format((float) $jn, 1) : '—' }}
                                </td>
                                <td class="px-3 py-2">
                                    @if($subs)
                                        <div class="flex flex-wrap gap-1.5">
                                            @foreach($subs as $sub)
                                                <span class="inline-flex items-center gap-1 rounded border px-2 py-0.5 text-xs {{ $sub['average'] < 90 ? 'border-red-200 bg-red-50 text-red-700' : 'border-gray-200 bg-gray-50 text-gray-700' }}"
                                                      title="{{ $sub['days'] }} kunlik baho">
                                                    {{ $sub['name'] }}: <b>{{ rtrim(rtrim(number_format($sub['average'], 1), '0'), '.') }}</b>
                                                </span>
                                            @endforeach
                                        </div>
                                    @else
                                        <span class="text-xs text-gray-400">Davrda baho yo'q</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @empty
        <div class="bg-white shadow rounded-lg p-8 text-center text-gray-500">
            "5 ga da'vogar" deb belgilangan talaba yo'q. Talaba sahifasida "5 ga da'vogar" belgisini qo'ying.
        </div>
    @endforelse
</x-app-layout>
