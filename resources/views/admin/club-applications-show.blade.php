<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Ariza: {{ $application->club_name }}</h2>
    </x-slot>

    <div class="py-4">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">

            <a href="{{ route('admin.club-applications.index') }}" class="inline-flex items-center gap-1 text-sm font-medium mb-4" style="color: #2b5ea7;">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5"/></svg>
                Barcha arizalar
            </a>

            {{-- Status banner --}}
            @if($application->status === 'pending')
                <div class="mb-4 p-3 rounded-xl border flex items-center gap-2 text-sm font-medium" style="background: #fef3c7; border-color: #fde68a; color: #92400e;">
                    <svg class="w-5 h-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/></svg>
                    Ariza ko'rib chiqilmoqda — kutilmoqda
                </div>
            @elseif($application->status === 'approved')
                <div class="mb-4 p-3 rounded-xl border flex items-center gap-2 text-sm font-medium" style="background: #d1fae5; border-color: #a7f3d0; color: #065f46;">
                    <svg class="w-5 h-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                    Talaba to'garakka biriktirilgan
                </div>
            @else
                <div class="mb-4 p-3 rounded-xl border flex items-center gap-2 text-sm font-medium" style="background: #fee2e2; border-color: #fecaca; color: #991b1b;">
                    <svg class="w-5 h-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
                    Ariza rad etilgan@if($application->reject_reason): {{ $application->reject_reason }}@endif
                </div>
            @endif

            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="grid grid-cols-1 md:grid-cols-2">
                    {{-- Talaba --}}
                    <div class="p-5 border-b md:border-r border-gray-100">
                        <div class="text-[11px] font-semibold text-gray-400 uppercase tracking-wide mb-3">Talaba ma'lumotlari</div>
                        <div class="space-y-3">
                            <div>
                                <div class="text-[11px] text-gray-400">F.I.O</div>
                                <div class="font-bold text-gray-800 text-sm">{{ $application->student_name }}</div>
                            </div>
                            <div class="flex gap-6">
                                <div>
                                    <div class="text-[11px] text-gray-400">Guruh</div>
                                    <div class="font-semibold text-gray-700 text-sm">{{ $application->group_name ?? '—' }}</div>
                                </div>
                                <div>
                                    <div class="text-[11px] text-gray-400">HEMIS ID</div>
                                    <div class="font-semibold text-gray-700 text-sm">{{ $application->student_hemis_id }}</div>
                                </div>
                            </div>
                            <div>
                                <div class="text-[11px] text-gray-400">Ariza sanasi</div>
                                <div class="font-semibold text-gray-700 text-sm">{{ $application->created_at->format('d.m.Y H:i') }}</div>
                            </div>
                        </div>
                    </div>

                    {{-- To'garak --}}
                    <div class="p-5 border-b border-gray-100">
                        <div class="text-[11px] font-semibold text-gray-400 uppercase tracking-wide mb-3">To'garak ma'lumotlari</div>
                        <div class="space-y-3">
                            <div>
                                <div class="text-[11px] text-gray-400">To'garak nomi</div>
                                <div class="font-bold text-gray-800 text-sm">{{ $application->club_name }}</div>
                            </div>
                            <div>
                                <div class="text-[11px] text-gray-400">Kafedra</div>
                                <div class="font-semibold text-gray-700 text-sm">{{ $application->kafedra_name ?? '—' }}</div>
                            </div>
                            <div class="flex gap-6">
                                @if($application->club_place)
                                <div>
                                    <div class="text-[11px] text-gray-400">Mashg'ulot joyi</div>
                                    <div class="font-semibold text-gray-700 text-sm">{{ $application->club_place }}</div>
                                </div>
                                @endif
                                @if($application->club_day)
                                <div>
                                    <div class="text-[11px] text-gray-400">Kun / Soat</div>
                                    <div class="font-semibold text-gray-700 text-sm">{{ $application->club_day }}@if($application->club_time), {{ $application->club_time }}@endif</div>
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Statistika --}}
                @php
                    $sameClub = \App\Models\ClubMembership::where('club_name', $application->club_name)->get();
                    $totalApps = $sameClub->count();
                    $approvedApps = $sameClub->where('status', 'approved')->count();
                    $pendingApps = $sameClub->where('status', 'pending')->count();
                    $rejectedApps = $sameClub->where('status', 'rejected')->count();
                @endphp
                <div class="p-5 border-b border-gray-100" style="background: #f8fafc;">
                    <div class="text-[11px] font-semibold text-gray-400 uppercase tracking-wide mb-3">Shu to'garak bo'yicha statistika</div>
                    <div class="flex gap-4">
                        <div class="flex-1 text-center p-3 rounded-lg bg-white border border-gray-100">
                            <div class="text-lg font-bold" style="color: #1a3268;">{{ $totalApps }}</div>
                            <div class="text-[11px] text-gray-400 font-medium">Jami ariza</div>
                        </div>
                        <div class="flex-1 text-center p-3 rounded-lg bg-white border border-gray-100">
                            <div class="text-lg font-bold text-emerald-600">{{ $approvedApps }}</div>
                            <div class="text-[11px] text-gray-400 font-medium">Biriktirilgan</div>
                        </div>
                        <div class="flex-1 text-center p-3 rounded-lg bg-white border border-gray-100">
                            <div class="text-lg font-bold text-amber-600">{{ $pendingApps }}</div>
                            <div class="text-[11px] text-gray-400 font-medium">Kutilmoqda</div>
                        </div>
                        <div class="flex-1 text-center p-3 rounded-lg bg-white border border-gray-100">
                            <div class="text-lg font-bold text-red-500">{{ $rejectedApps }}</div>
                            <div class="text-[11px] text-gray-400 font-medium">Rad etilgan</div>
                        </div>
                    </div>
                </div>

                {{-- Kafedra mudiri uchun: tasdiqlash / rad etish --}}
                @php
                    $activeRole = session('active_role', '');
                    $canManage = in_array($activeRole, ['kafedra_mudiri']);
                @endphp
                @if($canManage && $application->status === 'pending')
                    <div class="p-5" x-data="{ showReject: false }">
                        <div class="text-[11px] font-semibold text-gray-400 uppercase tracking-wide mb-3">Qaror qabul qilish</div>
                        <div class="flex gap-3">
                            <form method="POST" action="{{ route('admin.club-applications.approve', $application) }}" class="flex-1">
                                @csrf
                                <button type="submit" class="w-full py-2.5 text-sm font-semibold rounded-lg text-white transition" style="background: linear-gradient(135deg, #059669, #10b981);">
                                    To'garakka biriktirish
                                </button>
                            </form>
                            <div class="flex-1">
                                <button type="button" @click="showReject = !showReject" class="w-full py-2.5 text-sm font-semibold rounded-lg text-white transition" style="background: linear-gradient(135deg, #dc2626, #ef4444);">
                                    Rad etish
                                </button>
                                <form method="POST" action="{{ route('admin.club-applications.reject', $application) }}" x-show="showReject" x-cloak class="mt-3">
                                    @csrf
                                    <textarea name="reject_reason" rows="2" placeholder="Rad etish sababini yozing..." class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-red-200 focus:border-red-400"></textarea>
                                    <button type="submit" class="mt-2 w-full py-2 text-sm font-semibold rounded-lg text-white transition" style="background: linear-gradient(135deg, #991b1b, #dc2626);">
                                        Rad etishni tasdiqlash
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                @endif
            </div>

        </div>
    </div>
</x-app-layout>
