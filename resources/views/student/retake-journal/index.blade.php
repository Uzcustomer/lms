<x-student-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-sm text-gray-800 leading-tight">
            {{ __("Qayta o'qish jurnali") }}
        </h2>
    </x-slot>

    <div class="pb-6">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-4 sm:p-6 bg-white border-b border-gray-200">

                    @if($groups->isEmpty())
                        <div class="text-center py-12">
                            <div style="width:72px;height:72px;border-radius:50%;background:#fef2f2;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
                                <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                                </svg>
                            </div>
                            <p class="text-sm text-gray-600 font-medium">
                                {{ __("Sizda hozircha qayta o'qish guruhlari yo'q") }}
                            </p>
                            <p class="text-xs text-gray-400 mt-1">
                                {{ __("Qayta o'qishga ariza topshirganingizdan so'ng bu yerda ko'rinadi") }}
                            </p>
                        </div>
                    @else
                        {{-- Desktop: jadval --}}
                        <div class="hidden sm:block overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">#</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Fan') }}</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __("Guruh") }}</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __("O'qituvchi") }}</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __("Telefon") }}</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __("Davr") }}</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider"></th>
                                </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($groups as $i => $g)
                                    <tr class="hover:bg-red-50/30 transition">
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 font-medium">{{ $i + 1 }}</td>
                                        <td class="px-4 py-3 text-sm font-semibold text-gray-900">{{ $g->subject_name }}</td>
                                        <td class="px-4 py-3 text-xs text-gray-600">{{ $g->name }}</td>
                                        <td class="px-4 py-3 text-xs text-gray-700">{{ $g->teacher_name ?? '—' }}</td>
                                        <td class="px-4 py-3 text-xs">
                                            @if(!empty($g->teacher_phones))
                                                <div class="flex flex-wrap items-center gap-1">
                                                    @foreach($g->teacher_phones as $phone)
                                                        <a href="tel:{{ preg_replace('/[^+\d]/', '', $phone) }}"
                                                           class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-red-50 text-red-700 hover:bg-red-100 font-medium">
                                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h2.5a1 1 0 011 .76l1 4a1 1 0 01-.5 1.12L7.6 9.6a11.04 11.04 0 005.6 5.6l.7-1.4a1 1 0 011.12-.5l4 1a1 1 0 01.76 1V18a2 2 0 01-2 2A15 15 0 013 5z"/></svg>
                                                            {{ $phone }}
                                                        </a>
                                                    @endforeach
                                                </div>
                                            @else
                                                <span class="text-gray-400">—</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-xs text-gray-600">
                                            {{ $g->start_date->format('d.m.Y') }}
                                            <span class="text-gray-400">→</span>
                                            {{ $g->end_date->format('d.m.Y') }}
                                        </td>
                                        <td class="px-4 py-3 text-right whitespace-nowrap">
                                            <a href="{{ route('student.retake-journal.show', $g->id) }}"
                                               class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 transition shadow-sm">
                                                {{ __("Ochish") }}
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>

                        {{-- Mobile: kartochkalar --}}
                        <div class="sm:hidden space-y-3">
                            @foreach($groups as $i => $g)
                                <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
                                    <div class="px-4 py-3 bg-gradient-to-r from-red-600 to-red-700 text-white">
                                        <div class="flex items-center justify-between gap-2">
                                            <p class="text-sm font-bold truncate">{{ $g->subject_name }}</p>
                                            <span class="text-[10px] px-2 py-0.5 rounded-full bg-white/20 font-semibold">#{{ $i + 1 }}</span>
                                        </div>
                                        <p class="text-[11px] text-red-100 mt-0.5 truncate">{{ $g->name }}</p>
                                    </div>
                                    <div class="p-3 space-y-2">
                                        <div class="text-xs text-gray-700">
                                            <span class="text-gray-500">{{ __("O'qituvchi") }}:</span>
                                            <span class="font-medium">{{ $g->teacher_name ?? '—' }}</span>
                                        </div>
                                        @if(!empty($g->teacher_phones))
                                            <div class="flex flex-wrap items-center gap-1">
                                                @foreach($g->teacher_phones as $phone)
                                                    <a href="tel:{{ preg_replace('/[^+\d]/', '', $phone) }}"
                                                       class="inline-flex items-center gap-1 px-2 py-1 rounded-md bg-red-50 text-red-700 text-[11px] font-medium">
                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h2.5a1 1 0 011 .76l1 4a1 1 0 01-.5 1.12L7.6 9.6a11.04 11.04 0 005.6 5.6l.7-1.4a1 1 0 011.12-.5l4 1a1 1 0 01.76 1V18a2 2 0 01-2 2A15 15 0 013 5z"/></svg>
                                                        {{ $phone }}
                                                    </a>
                                                @endforeach
                                            </div>
                                        @endif
                                        <div class="text-[11px] text-gray-500 flex items-center gap-1">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                            {{ $g->start_date->format('d.m.Y') }} → {{ $g->end_date->format('d.m.Y') }}
                                        </div>
                                        <a href="{{ route('student.retake-journal.show', $g->id) }}"
                                           class="block text-center px-3 py-2 text-xs font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 transition">
                                            {{ __("Jurnalni ochish") }} →
                                        </a>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif

                </div>
            </div>
        </div>
    </div>
</x-student-app-layout>
