<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">To'garak arizalari</h2>
    </x-slot>

    <style>
        .stat-card { cursor: pointer; transition: all 0.2s; position: relative; }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        .stat-card.active::after { content: ''; position: absolute; bottom: -2px; left: 20%; right: 20%; height: 3px; border-radius: 3px; }
        .stat-card.active-pending::after { background: linear-gradient(90deg, #f59e0b, #fbbf24); }
        .stat-card.active-approved::after { background: linear-gradient(90deg, #10b981, #34d399); }
        .stat-card.active-rejected::after { background: linear-gradient(90deg, #ef4444, #f87171); }
        .stat-card.active-all::after { background: linear-gradient(90deg, #2b5ea7, #3b82f6); }
        .club-table { font-size: 13px; border-collapse: separate; width: 100%; }
        .club-table thead { background: linear-gradient(135deg, #e8edf5, #dbe4ef); position: sticky; top: 0; z-index: 1; }
        .club-table th { padding: 10px 14px; font-weight: 600; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #475569; border-bottom: 2px solid #cbd5e1; }
        .club-table tbody tr { transition: all 0.15s; }
        .club-table tbody tr:nth-child(even) { background: #f8fafc; }
        .club-table tbody tr:hover { background: #eff6ff; box-shadow: inset 4px 0 0 #2b5ea7; }
        .club-table td { padding: 10px 14px; border-bottom: 1px solid #f1f5f9; }
        .club-accordion { transition: all 0.2s; border: 1.5px solid #93c5fd !important; }
        .club-accordion:hover { border-color: #3b82f6 !important; box-shadow: 0 2px 8px rgba(43,94,167,0.12); }
    </style>

    <div class="py-4">
        <div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8">

            @if(session('success'))
                <div class="mb-4 p-3 bg-emerald-50 border border-emerald-200 rounded-xl text-sm text-emerald-700 font-medium">{{ session('success') }}</div>
            @endif

            @php
                $pending = $applications->where('status', 'pending');
                $approved = $applications->where('status', 'approved');
                $rejected = $applications->where('status', 'rejected');
                $byClub = $applications->groupBy('club_name');
                $activeRole = session('active_role', '');
                $isKafedraMudiri = $activeRole === 'kafedra_mudiri';
            @endphp

            <div x-data="{ filter: 'all' }">
                {{-- Stats row --}}
                <div class="grid grid-cols-4 gap-3 mb-5">
                    <div class="stat-card rounded-xl p-4 border flex items-center gap-3"
                         :class="filter === 'all' ? 'bg-blue-50 border-blue-300 active active-all shadow-sm' : 'bg-white border-gray-200'"
                         @click="filter = 'all'">
                        <div class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0" style="background: linear-gradient(135deg, #2b5ea7, #3b82f6);">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                        </div>
                        <div>
                            <div class="text-xl font-bold" style="color: #1a3268;">{{ $applications->count() }}</div>
                            <div class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide">Jami</div>
                        </div>
                    </div>
                    <div class="stat-card rounded-xl p-4 border flex items-center gap-3"
                         :class="filter === 'pending' ? 'bg-amber-50 border-amber-300 active active-pending shadow-sm' : 'bg-white border-gray-200'"
                         @click="filter = 'pending'">
                        <div class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0" style="background: linear-gradient(135deg, #d97706, #f59e0b);">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <div>
                            <div class="text-xl font-bold text-amber-700">{{ $pending->count() }}</div>
                            <div class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide">Kutilmoqda</div>
                        </div>
                    </div>
                    <div class="stat-card rounded-xl p-4 border flex items-center gap-3"
                         :class="filter === 'approved' ? 'bg-emerald-50 border-emerald-300 active active-approved shadow-sm' : 'bg-white border-gray-200'"
                         @click="filter = 'approved'">
                        <div class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0" style="background: linear-gradient(135deg, #059669, #10b981);">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <div>
                            <div class="text-xl font-bold text-emerald-700">{{ $approved->count() }}</div>
                            <div class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide">Biriktirilgan</div>
                        </div>
                    </div>
                    <div class="stat-card rounded-xl p-4 border flex items-center gap-3"
                         :class="filter === 'rejected' ? 'bg-red-50 border-red-300 active active-rejected shadow-sm' : 'bg-white border-gray-200'"
                         @click="filter = 'rejected'">
                        <div class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0" style="background: linear-gradient(135deg, #dc2626, #ef4444);">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <div>
                            <div class="text-xl font-bold text-red-600">{{ $rejected->count() }}</div>
                            <div class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide">Rad etilgan</div>
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
                            $kafedraRaw = $clubApps->first()->kafedra_name ?? '';
                            $schedule = trim(($clubApps->first()->club_day ?? '') . ' ' . ($clubApps->first()->club_time ?? ''));

                            // "...kafedrasi" gacha kesib kafedra nomini ajratish
                            $kafedra = $kafedraRaw;
                            if (preg_match('/^(.+?kafedrasi)\b/iu', $kafedraRaw, $m)) {
                                $kafedra = $m[1];
                            }

                            // Kafedra mudirini topish
                            $deptHemisId = $clubApps->first()->department_hemis_id;
                            if (!$deptHemisId && $kafedra) {
                                $deptHemisId = \App\Models\Department::whereRaw('LOWER(name) LIKE ?', ['%' . mb_strtolower($kafedra) . '%'])->value('department_hemis_id');
                            }
                            $masul = $deptHemisId
                                ? \App\Models\Teacher::where('department_hemis_id', $deptHemisId)
                                    ->whereHas('roles', fn($q) => $q->where('name', 'kafedra_mudiri'))
                                    ->value('full_name')
                                : null;
                        @endphp
                        <div class="club-accordion bg-white rounded-xl shadow-sm" style="margin-bottom: 10px;"
                             x-data="{ open: false }"
                             x-show="filter === 'all'
                                 || (filter === 'pending' && {{ $clubPending }} > 0)
                                 || (filter === 'approved' && {{ $clubApproved }} > 0)
                                 || (filter === 'rejected' && {{ $clubRejected }} > 0)">
                            <div class="flex items-center justify-between cursor-pointer select-none" style="padding: 18px 20px 18px 12px;" @click="open = !open">
                                <div class="flex items-center gap-3 min-w-0">
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0 text-sm font-bold text-white" style="background: linear-gradient(135deg, #2b5ea7, #3b82f6);">{{ $loop->iteration }}</div>
                                    <div class="min-w-0">
                                        <div class="font-bold text-gray-800 truncate" style="font-size: 15px;">{{ $clubName }}</div>
                                        <div class="text-gray-400" style="font-size: 13px;">{{ $kafedra }}@if($schedule) &middot; {{ $schedule }}@endif @if($masul) &middot; <span class="text-gray-600 font-medium">{{ $masul }}</span>@endif</div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2.5 flex-shrink-0">
                                    <div class="flex items-center gap-1.5" style="font-size: 12px;">
                                        @if($clubPending > 0)
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md font-semibold" style="background: #fef3c7; color: #92400e;">{{ $clubPending }} kutilmoqda</span>
                                        @endif
                                        @if($clubApproved > 0)
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md font-semibold" style="background: #d1fae5; color: #065f46;">{{ $clubApproved }} biriktirilgan</span>
                                        @endif
                                        @if($clubRejected > 0)
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md font-semibold" style="background: #fee2e2; color: #991b1b;">{{ $clubRejected }} rad</span>
                                        @endif
                                    </div>
                                    <svg class="w-4 h-4 text-gray-400 transition-transform duration-200" :class="open && 'rotate-180'" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                                </div>
                            </div>
                            <div x-show="open" x-collapse x-cloak>
                                <div class="overflow-x-auto">
                                    <table class="club-table">
                                        <thead>
                                            <tr>
                                                <th class="text-center" style="width: 40px;">#</th>
                                                <th class="text-left">Talaba</th>
                                                <th class="text-left">Guruh</th>
                                                <th class="text-left">Kafedra</th>
                                                <th class="text-left">Masul shaxs</th>
                                                <th class="text-left">Ariza sanasi</th>
                                                <th class="text-center">Holati</th>
                                                @if($isKafedraMudiri)
                                                    <th class="text-center">Amal</th>
                                                @endif
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($clubApps->sortByDesc('created_at')->values() as $i => $app)
                                                <tr x-show="filter === 'all' || filter === '{{ $app->status }}'" x-data="{ rejectOpen: false }">
                                                    <td class="text-center text-gray-400 font-medium">{{ $i + 1 }}</td>
                                                    <td class="font-semibold text-gray-800">{{ $app->student_name }}</td>
                                                    <td class="text-gray-600">{{ $app->group_name ?? '—' }}</td>
                                                    <td class="text-gray-500 text-xs">{{ $app->kafedra_name ?? '—' }}</td>
                                                    <td class="text-gray-600 text-xs">{{ $masul ?? '—' }}</td>
                                                    <td class="text-gray-500">{{ $app->created_at->format('d.m.Y H:i') }}</td>
                                                    <td class="text-center">
                                                        @if($app->status === 'pending')
                                                            <span class="inline-flex px-2.5 py-1 rounded-md text-[11px] font-semibold" style="background: #fef3c7; color: #92400e;">Kutilmoqda</span>
                                                        @elseif($app->status === 'approved')
                                                            <span class="inline-flex px-2.5 py-1 rounded-md text-[11px] font-semibold" style="background: #d1fae5; color: #065f46;">Biriktirilgan</span>
                                                        @else
                                                            <span class="inline-flex px-2.5 py-1 rounded-md text-[11px] font-semibold" style="background: #fee2e2; color: #991b1b;">Rad etilgan</span>
                                                        @endif
                                                    </td>
                                                    @if($isKafedraMudiri)
                                                        <td class="text-center" style="min-width: 200px;">
                                                            @if($app->status === 'pending')
                                                                <div class="flex items-center justify-center gap-2">
                                                                    <form method="POST" action="{{ route('admin.club-applications.approve', $app) }}">
                                                                        @csrf
                                                                        <button type="submit" class="px-3 py-1.5 text-[11px] font-semibold rounded-md text-white transition" style="background: linear-gradient(135deg, #059669, #10b981);">Biriktirish</button>
                                                                    </form>
                                                                    <button type="button" @click="rejectOpen = !rejectOpen" class="px-3 py-1.5 text-[11px] font-semibold rounded-md text-white transition" style="background: linear-gradient(135deg, #dc2626, #ef4444);">Rad etish</button>
                                                                </div>
                                                                <div x-show="rejectOpen" x-cloak class="mt-2">
                                                                    <form method="POST" action="{{ route('admin.club-applications.reject', $app) }}" class="flex gap-1">
                                                                        @csrf
                                                                        <input type="text" name="reject_reason" placeholder="Sabab..." class="flex-1 text-xs border border-gray-300 rounded-md px-2 py-1.5 focus:ring-1 focus:ring-red-300 focus:border-red-400">
                                                                        <button type="submit" class="px-2.5 py-1.5 text-[11px] font-semibold rounded-md text-white" style="background: #991b1b;">OK</button>
                                                                    </form>
                                                                </div>
                                                            @elseif($app->status === 'rejected' && $app->reject_reason)
                                                                <span class="text-xs text-red-500 italic">{{ $app->reject_reason }}</span>
                                                            @else
                                                                <span class="text-gray-300">—</span>
                                                            @endif
                                                        </td>
                                                    @endif
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    @endforeach
                @else
                    <div class="bg-white rounded-xl border border-gray-200 p-12 text-center">
                        <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z"/></svg>
                        <div class="text-gray-400 text-sm">Arizalar mavjud emas</div>
                    </div>
                @endif
            </div>

        </div>
    </div>
</x-app-layout>
