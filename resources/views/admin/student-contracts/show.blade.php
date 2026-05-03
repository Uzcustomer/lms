<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Shartnoma #{{ $studentContract->id }}</h2>
    </x-slot>

    <div class="py-4">
        <div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8">

            <a href="{{ route('admin.student-contracts.index') }}" class="inline-flex items-center gap-1 text-sm font-medium mb-4" style="color: #2b5ea7;">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5"/></svg>
                Barcha shartnomalar
            </a>

            @if(session('success'))
                <div class="mb-4 p-3 bg-emerald-50 border border-emerald-200 rounded-xl text-sm text-emerald-700 font-medium">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-xl text-sm text-red-700 font-medium">{{ session('error') }}</div>
            @endif

            @php
                $statusConfig = [
                    'pending' => ['bg' => '#fef3c7', 'border' => '#fde68a', 'color' => '#92400e', 'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z', 'label' => 'Kutilmoqda'],
                    'registrar_review' => ['bg' => '#dbeafe', 'border' => '#93c5fd', 'color' => '#1e40af', 'icon' => 'M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z', 'label' => 'Ko\'rib chiqilmoqda'],
                    'approved' => ['bg' => '#d1fae5', 'border' => '#a7f3d0', 'color' => '#065f46', 'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z', 'label' => 'Tasdiqlangan'],
                    'rejected' => ['bg' => '#fee2e2', 'border' => '#fecaca', 'color' => '#991b1b', 'icon' => 'M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z', 'label' => 'Rad etilgan'],
                ];
                $sc = $statusConfig[$studentContract->status] ?? $statusConfig['pending'];
            @endphp

            {{-- Status banner --}}
            <div class="mb-5 p-4 rounded-xl border flex items-center gap-3" style="background: {{ $sc['bg'] }}; border-color: {{ $sc['border'] }}; color: {{ $sc['color'] }};">
                <svg class="w-6 h-6 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $sc['icon'] }}"/></svg>
                <div>
                    <div class="font-bold text-sm">{{ $sc['label'] }}</div>
                    <div class="text-xs opacity-80">Ariza #{{ $studentContract->id }} &middot; {{ $studentContract->created_at->format('d.m.Y H:i') }}</div>
                </div>
                @if($studentContract->status === 'approved' && $studentContract->document_path)
                    <a href="{{ route('admin.student-contracts.download', $studentContract) }}" class="ml-auto inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold rounded-lg text-white transition" style="background: linear-gradient(135deg, #059669, #10b981);">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        Yuklab olish
                    </a>
                @endif
            </div>

            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                {{-- Talaba ma'lumotlari --}}
                <div class="grid grid-cols-1 lg:grid-cols-2">
                    <div class="p-5 border-b lg:border-r border-gray-100">
                        <div class="flex items-center gap-2 mb-4">
                            <div class="w-7 h-7 rounded-lg flex items-center justify-center" style="background: linear-gradient(135deg, #2b5ea7, #3b82f6);">
                                <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>
                            </div>
                            <div class="text-[11px] font-bold text-gray-400 uppercase tracking-wide">Talaba ma'lumotlari</div>
                        </div>
                        <div class="space-y-2.5">
                            <div class="flex justify-between"><span class="text-xs text-gray-400">F.I.O</span><span class="text-sm font-semibold text-gray-800">{{ $studentContract->student_full_name }}</span></div>
                            <div class="flex justify-between"><span class="text-xs text-gray-400">HEMIS ID</span><span class="text-sm font-semibold text-gray-700">{{ $studentContract->student_hemis_id }}</span></div>
                            <div class="flex justify-between"><span class="text-xs text-gray-400">Guruh</span><span class="text-sm font-semibold text-gray-700">{{ $studentContract->group_name }}</span></div>
                            <div class="flex justify-between"><span class="text-xs text-gray-400">Fakultet</span><span class="text-sm font-semibold text-gray-700">{{ $studentContract->department_name }}</span></div>
                            <div class="flex justify-between"><span class="text-xs text-gray-400">Yo'nalish</span><span class="text-sm font-semibold text-gray-700">{{ $studentContract->specialty_name }}</span></div>
                            <div class="flex justify-between"><span class="text-xs text-gray-400">Kurs</span><span class="text-sm font-semibold text-gray-700">{{ $studentContract->level_name }}</span></div>
                            <div class="flex justify-between"><span class="text-xs text-gray-400">Shartnoma turi</span><span class="text-sm font-semibold text-gray-700">{{ $studentContract->type_label }}</span></div>
                            @if($studentContract->specialty_field)
                            <div class="flex justify-between"><span class="text-xs text-gray-400">Mutaxassislik</span><span class="text-sm font-semibold text-gray-700">{{ $studentContract->specialty_field }}</span></div>
                            @endif
                        </div>
                    </div>

                    {{-- Bitiruvchi rekvizitlari --}}
                    <div class="p-5 border-b border-gray-100">
                        <div class="flex items-center gap-2 mb-4">
                            <div class="w-7 h-7 rounded-lg flex items-center justify-center" style="background: linear-gradient(135deg, #7c3aed, #8b5cf6);">
                                <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 9h3.75M15 12h3.75M15 15h3.75M4.5 19.5h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5zm6-10.125a1.875 1.875 0 11-3.75 0 1.875 1.875 0 013.75 0zm1.294 6.336a6.721 6.721 0 01-3.17.789 6.721 6.721 0 01-3.168-.789 3.376 3.376 0 016.338 0z"/></svg>
                            </div>
                            <div class="text-[11px] font-bold text-gray-400 uppercase tracking-wide">Bitiruvchi rekvizitlari</div>
                        </div>
                        <div class="space-y-2.5">
                            <div class="flex justify-between"><span class="text-xs text-gray-400">Manzil</span><span class="text-sm font-semibold text-gray-700">{{ $studentContract->student_address ?? '—' }}</span></div>
                            <div class="flex justify-between"><span class="text-xs text-gray-400">Telefon</span><span class="text-sm font-semibold text-gray-700">{{ $studentContract->student_phone ?? '—' }}</span></div>
                            <div class="flex justify-between"><span class="text-xs text-gray-400">Passport</span><span class="text-sm font-semibold text-gray-700">{{ $studentContract->student_passport ?? '—' }}</span></div>
                            <div class="flex justify-between"><span class="text-xs text-gray-400">Bank hisob</span><span class="text-sm font-semibold text-gray-700">{{ $studentContract->student_bank_account ?? '—' }}</span></div>
                            <div class="flex justify-between"><span class="text-xs text-gray-400">MFO</span><span class="text-sm font-semibold text-gray-700">{{ $studentContract->student_bank_mfo ?? '—' }}</span></div>
                            <div class="flex justify-between"><span class="text-xs text-gray-400">INN</span><span class="text-sm font-semibold text-gray-700">{{ $studentContract->student_inn ?? '—' }}</span></div>
                        </div>
                    </div>
                </div>

                {{-- Ish beruvchi --}}
                <div class="grid grid-cols-1 lg:grid-cols-2">
                    <div class="p-5 border-b lg:border-r border-gray-100">
                        <div class="flex items-center gap-2 mb-4">
                            <div class="w-7 h-7 rounded-lg flex items-center justify-center" style="background: linear-gradient(135deg, #059669, #10b981);">
                                <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3H21m-3.75 3H21"/></svg>
                            </div>
                            <div class="text-[11px] font-bold text-gray-400 uppercase tracking-wide">Potensial ish beruvchi</div>
                        </div>
                        <div class="space-y-2.5">
                            <div class="flex justify-between"><span class="text-xs text-gray-400">Nomi</span><span class="text-sm font-semibold text-gray-700">{{ $studentContract->employer_name ?? '—' }}</span></div>
                            <div class="flex justify-between"><span class="text-xs text-gray-400">Manzil</span><span class="text-sm font-semibold text-gray-700">{{ $studentContract->employer_address ?? '—' }}</span></div>
                            <div class="flex justify-between"><span class="text-xs text-gray-400">Telefon</span><span class="text-sm font-semibold text-gray-700">{{ $studentContract->employer_phone ?? '—' }}</span></div>
                            <div class="flex justify-between"><span class="text-xs text-gray-400">Rahbar</span><span class="text-sm font-semibold text-gray-700">{{ $studentContract->employer_director_name ?? '—' }}</span></div>
                            <div class="flex justify-between"><span class="text-xs text-gray-400">Lavozim</span><span class="text-sm font-semibold text-gray-700">{{ $studentContract->employer_director_position ?? '—' }}</span></div>
                            <div class="flex justify-between"><span class="text-xs text-gray-400">Bank hisob</span><span class="text-sm font-semibold text-gray-700">{{ $studentContract->employer_bank_account ?? '—' }}</span></div>
                            <div class="flex justify-between"><span class="text-xs text-gray-400">MFO</span><span class="text-sm font-semibold text-gray-700">{{ $studentContract->employer_bank_mfo ?? '—' }}</span></div>
                            <div class="flex justify-between"><span class="text-xs text-gray-400">INN</span><span class="text-sm font-semibold text-gray-700">{{ $studentContract->employer_inn ?? '—' }}</span></div>
                        </div>
                    </div>

                    @if($studentContract->contract_type === '4_tomonlama')
                    {{-- 4-tomon --}}
                    <div class="p-5 border-b border-gray-100">
                        <div class="flex items-center gap-2 mb-4">
                            <div class="w-7 h-7 rounded-lg flex items-center justify-center" style="background: linear-gradient(135deg, #d97706, #f59e0b);">
                                <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772"/></svg>
                            </div>
                            <div class="text-[11px] font-bold text-gray-400 uppercase tracking-wide">To'rtinchi tomon (MFY/Tuman)</div>
                        </div>
                        <div class="space-y-2.5">
                            <div class="flex justify-between"><span class="text-xs text-gray-400">Nomi</span><span class="text-sm font-semibold text-gray-700">{{ $studentContract->fourth_party_name ?? '—' }}</span></div>
                            <div class="flex justify-between"><span class="text-xs text-gray-400">Manzil</span><span class="text-sm font-semibold text-gray-700">{{ $studentContract->fourth_party_address ?? '—' }}</span></div>
                            <div class="flex justify-between"><span class="text-xs text-gray-400">Telefon</span><span class="text-sm font-semibold text-gray-700">{{ $studentContract->fourth_party_phone ?? '—' }}</span></div>
                            <div class="flex justify-between"><span class="text-xs text-gray-400">Rahbar</span><span class="text-sm font-semibold text-gray-700">{{ $studentContract->fourth_party_director_name ?? '—' }}</span></div>
                        </div>
                    </div>
                    @else
                    <div class="p-5 border-b border-gray-100"></div>
                    @endif
                </div>

                {{-- Ko'rib chiqish --}}
                @if($studentContract->reviewed_at)
                <div class="p-5" style="background: #f8fafc;">
                    <div class="flex items-center gap-2 mb-3">
                        <div class="w-7 h-7 rounded-lg flex items-center justify-center" style="background: linear-gradient(135deg, #475569, #64748b);">
                            <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <div class="text-[11px] font-bold text-gray-400 uppercase tracking-wide">Ko'rib chiqish</div>
                    </div>
                    <div class="flex gap-8 text-sm">
                        <div><span class="text-xs text-gray-400">Ko'rib chiqqan:</span> <span class="font-semibold text-gray-800">{{ $studentContract->reviewer?->full_name ?? '—' }}</span></div>
                        <div><span class="text-xs text-gray-400">Sana:</span> <span class="font-semibold text-gray-800">{{ $studentContract->reviewed_at->format('d.m.Y H:i') }}</span></div>
                    </div>
                    @if($studentContract->reject_reason)
                        <div class="mt-3 p-3 rounded-lg bg-red-50 border border-red-100 text-sm text-red-700">
                            <span class="font-semibold">Rad etish sababi:</span> {{ $studentContract->reject_reason }}
                        </div>
                    @endif
                </div>
                @endif
            </div>

            {{-- Amallar --}}
            @if($studentContract->status === 'approved' && $studentContract->document_path)
            <div class="mt-4 flex gap-3">
                <form method="POST" action="{{ route('admin.student-contracts.regenerate', $studentContract) }}">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2.5 text-xs font-semibold rounded-lg text-white transition" style="background: linear-gradient(135deg, #2b5ea7, #3b82f6);">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182"/></svg>
                        Hujjatni qayta yaratish
                    </button>
                </form>
            </div>
            @endif

        </div>
    </div>
</x-app-layout>
