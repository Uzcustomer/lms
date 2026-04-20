<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">To'garak arizalari</h2>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto px-4 sm:px-6 lg:px-8">

            @if(session('success'))
                <div class="mb-4 p-3 bg-green-50 border border-green-200 rounded-lg text-sm text-green-700">{{ session('success') }}</div>
            @endif

            @php
                $pending = $applications->where('status', 'pending');
                $approved = $applications->where('status', 'approved');
                $rejected = $applications->where('status', 'rejected');
                $byClub = $applications->groupBy('club_name');
            @endphp

            {{-- Stats row --}}
            <div class="flex gap-4 mb-6">
                <div class="flex-1 bg-yellow-50 border border-yellow-200 rounded-xl p-4 flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-yellow-200 flex items-center justify-center">
                        <svg class="w-5 h-5 text-yellow-700" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <div>
                        <div class="text-2xl font-bold text-yellow-700">{{ $pending->count() }}</div>
                        <div class="text-xs text-yellow-600">Kutilmoqda</div>
                    </div>
                </div>
                <div class="flex-1 bg-green-50 border border-green-200 rounded-xl p-4 flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-green-200 flex items-center justify-center">
                        <svg class="w-5 h-5 text-green-700" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    </div>
                    <div>
                        <div class="text-2xl font-bold text-green-700">{{ $approved->count() }}</div>
                        <div class="text-xs text-green-600">Tasdiqlangan</div>
                    </div>
                </div>
                <div class="flex-1 bg-red-50 border border-red-200 rounded-xl p-4 flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-red-200 flex items-center justify-center">
                        <svg class="w-5 h-5 text-red-700" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </div>
                    <div>
                        <div class="text-2xl font-bold text-red-700">{{ $rejected->count() }}</div>
                        <div class="text-xs text-red-600">Rad etilgan</div>
                    </div>
                </div>
            </div>

            {{-- Applications grouped by club --}}
            @if($applications->count() > 0)
                @foreach($byClub as $clubName => $clubApps)
                    @php
                        $clubPending = $clubApps->where('status', 'pending')->count();
                        $clubApproved = $clubApps->where('status', 'approved')->count();
                        $clubRejected = $clubApps->where('status', 'rejected')->count();
                        $kafedra = $clubApps->first()->kafedra_name ?? '—';
                        $masul = $clubApps->first()->club_day ? $clubApps->first()->club_day . ', ' . ($clubApps->first()->club_time ?? '') : '';
                    @endphp
                    <div class="bg-white rounded-xl border border-gray-200 shadow-sm mb-4" x-data="{ open: false }">
                        <div class="flex items-center justify-between px-5 py-4 cursor-pointer hover:bg-gray-50 transition rounded-xl" @click="open = !open">
                            <div class="flex items-center gap-4 min-w-0">
                                <div class="w-10 h-10 rounded-full bg-indigo-100 flex items-center justify-center flex-shrink-0">
                                    <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z"/></svg>
                                </div>
                                <div class="min-w-0">
                                    <div class="font-bold text-gray-800 text-sm truncate">{{ $clubName }}</div>
                                    <div class="text-xs text-gray-500">{{ $kafedra }}</div>
                                </div>
                            </div>
                            <div class="flex items-center gap-3 flex-shrink-0">
                                <div class="flex items-center gap-2 text-xs">
                                    @if($clubPending > 0)
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-yellow-100 text-yellow-700 font-semibold">
                                            <span class="w-1.5 h-1.5 rounded-full bg-yellow-500"></span> {{ $clubPending }}
                                        </span>
                                    @endif
                                    @if($clubApproved > 0)
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-green-100 text-green-700 font-semibold">
                                            <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span> {{ $clubApproved }}
                                        </span>
                                    @endif
                                    @if($clubRejected > 0)
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-red-100 text-red-700 font-semibold">
                                            <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span> {{ $clubRejected }}
                                        </span>
                                    @endif
                                    <span class="text-gray-400 font-medium">{{ $clubApps->count() }} ta</span>
                                </div>
                                <svg class="w-5 h-5 text-gray-400 transition-transform" :class="open && 'rotate-180'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                            </div>
                        </div>
                        <div x-show="open" x-cloak class="border-t border-gray-100">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="bg-gray-50 text-xs text-gray-500 uppercase">
                                        <th class="px-5 py-2 text-left font-semibold">#</th>
                                        <th class="px-5 py-2 text-left font-semibold">Talaba</th>
                                        <th class="px-5 py-2 text-left font-semibold">Guruh</th>
                                        <th class="px-5 py-2 text-left font-semibold">Kafedra</th>
                                        <th class="px-5 py-2 text-left font-semibold">Sana</th>
                                        <th class="px-5 py-2 text-center font-semibold">Status</th>
                                        <th class="px-5 py-2 text-center font-semibold">Amal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($clubApps->sortByDesc('created_at') as $i => $app)
                                        <tr class="border-t border-gray-50 hover:bg-gray-50 transition">
                                            <td class="px-5 py-3 text-gray-400">{{ $i + 1 }}</td>
                                            <td class="px-5 py-3 font-semibold text-gray-800">{{ $app->student_name }}</td>
                                            <td class="px-5 py-3 text-gray-600">{{ $app->group_name ?? '—' }}</td>
                                            <td class="px-5 py-3 text-gray-600">{{ $app->kafedra_name ?? '—' }}</td>
                                            <td class="px-5 py-3 text-gray-500">{{ $app->created_at->format('d.m.Y H:i') }}</td>
                                            <td class="px-5 py-3 text-center">
                                                @if($app->status === 'pending')
                                                    <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold bg-yellow-100 text-yellow-700">Kutilmoqda</span>
                                                @elseif($app->status === 'approved')
                                                    <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold bg-green-100 text-green-700">Tasdiqlangan</span>
                                                @else
                                                    <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold bg-red-100 text-red-700">Rad etilgan</span>
                                                @endif
                                            </td>
                                            <td class="px-5 py-3 text-center">
                                                <a href="{{ route('admin.club-applications.show', $app) }}" class="inline-flex items-center gap-1 px-3 py-1.5 bg-indigo-600 text-white text-xs font-semibold rounded-lg hover:bg-indigo-700 transition">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                                    Ko'rish
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endforeach
            @else
                <div class="bg-white rounded-xl border border-gray-200 p-8 text-center text-gray-400 text-sm">
                    Arizalar mavjud emas
                </div>
            @endif

        </div>
    </div>
</x-app-layout>
