<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Shartnomani ko'rib chiqish</h2>
    </x-slot>

    <style>
        .review-input { height: 36px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; padding: 0 12px; width: 100%; outline: none; transition: all 0.2s; }
        .review-input:focus { border-color: #2b5ea7; box-shadow: 0 0 0 2px rgba(43,94,167,0.15); }
    </style>

    <div class="py-4">
        <div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8">

            <a href="{{ route('admin.student-contracts.index') }}" class="inline-flex items-center gap-1 text-sm font-medium mb-4" style="color: #2b5ea7;">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5"/></svg>
                Barcha shartnomalar
            </a>

            @if(session('error'))
                <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-xl text-sm text-red-700 font-medium">{{ session('error') }}</div>
            @endif

            {{-- Status --}}
            <div class="mb-5 p-4 rounded-xl border flex items-center gap-3" style="background: #dbeafe; border-color: #93c5fd; color: #1e40af;">
                <svg class="w-6 h-6 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                <div>
                    <div class="font-bold text-sm">{{ $studentContract->status_label }}</div>
                    <div class="text-xs opacity-80">{{ $studentContract->student_full_name }} &middot; {{ $studentContract->type_label }} &middot; {{ $studentContract->created_at->format('d.m.Y H:i') }}</div>
                </div>
            </div>

            <form method="POST" action="{{ route('admin.student-contracts.approve', $studentContract) }}">
                @csrf
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                {{-- Chap ustun — talaba ma'lumotlari --}}
                <div class="space-y-4">
                    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                        <div class="px-5 py-3 flex items-center gap-2" style="background: linear-gradient(135deg, #e8edf5, #dbe4ef); border-bottom: 2px solid #cbd5e1;">
                            <div class="w-6 h-6 rounded-md flex items-center justify-center" style="background: linear-gradient(135deg, #2b5ea7, #3b82f6);">
                                <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>
                            </div>
                            <span class="text-[11px] font-bold text-gray-500 uppercase tracking-wide">Talaba (Bitiruvchi)</span>
                        </div>
                        <div class="p-4 space-y-2">
                            <div class="flex justify-between text-sm"><span class="text-gray-400 text-xs">F.I.O</span><span class="font-semibold text-gray-800">{{ $studentContract->student_full_name }}</span></div>
                            <div class="flex justify-between text-sm"><span class="text-gray-400 text-xs">HEMIS ID</span><span class="font-semibold text-gray-700">{{ $studentContract->student_hemis_id }}</span></div>
                            <div class="flex justify-between text-sm"><span class="text-gray-400 text-xs">Guruh</span><span class="font-semibold text-gray-700">{{ $studentContract->group_name }}</span></div>
                            <div class="flex justify-between text-sm"><span class="text-gray-400 text-xs">Fakultet</span><span class="font-semibold text-gray-700">{{ $studentContract->department_name }}</span></div>
                            <div class="flex justify-between text-sm"><span class="text-gray-400 text-xs">Yo'nalish</span><span class="font-semibold text-gray-700">{{ $studentContract->specialty_name }}</span></div>
                            <div class="flex justify-between text-sm"><span class="text-gray-400 text-xs">Turi</span><span class="font-bold {{ $studentContract->contract_type === '4_tomonlama' ? 'text-purple-700' : 'text-blue-700' }}">{{ $studentContract->type_label }}</span></div>
                            <div style="border-top: 1px solid #f1f5f9; margin: 6px 0;"></div>
                            <div class="flex justify-between text-sm"><span class="text-gray-400 text-xs">Manzil</span><span class="text-gray-700">{{ $studentContract->student_address ?? '—' }}</span></div>
                            <div class="flex justify-between text-sm"><span class="text-gray-400 text-xs">Telefon</span><span class="text-gray-700">{{ $studentContract->student_phone ?? '—' }}</span></div>
                            <div class="flex justify-between text-sm"><span class="text-gray-400 text-xs">Passport</span><span class="text-gray-700">{{ $studentContract->student_passport ?? '—' }}</span></div>
                            <div class="flex justify-between text-sm"><span class="text-gray-400 text-xs">Bank hisob</span><span class="text-gray-700">{{ $studentContract->student_bank_account ?? '—' }}</span></div>
                            <div class="flex justify-between text-sm"><span class="text-gray-400 text-xs">MFO / INN</span><span class="text-gray-700">{{ $studentContract->student_bank_mfo ?? '—' }} / {{ $studentContract->student_inn ?? '—' }}</span></div>
                            <div class="flex justify-between text-sm"><span class="text-gray-400 text-xs">Mutaxassislik</span><span class="text-gray-700">{{ $studentContract->specialty_field ?? '—' }}</span></div>
                        </div>
                    </div>

                    @if($studentContract->contract_type === '4_tomonlama')
                    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                        <div class="px-5 py-3 flex items-center gap-2" style="background: linear-gradient(135deg, #f5f3ff, #ede9fe); border-bottom: 2px solid #ddd6fe;">
                            <div class="w-6 h-6 rounded-md flex items-center justify-center" style="background: linear-gradient(135deg, #7c3aed, #8b5cf6);">
                                <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719"/></svg>
                            </div>
                            <span class="text-[11px] font-bold text-gray-500 uppercase tracking-wide">4-tomon (talaba kiritgan)</span>
                        </div>
                        <div class="p-4 space-y-2">
                            <div class="flex justify-between text-sm"><span class="text-gray-400 text-xs">Nomi</span><span class="text-gray-700">{{ $studentContract->fourth_party_name ?? '—' }}</span></div>
                            <div class="flex justify-between text-sm"><span class="text-gray-400 text-xs">Manzil</span><span class="text-gray-700">{{ $studentContract->fourth_party_address ?? '—' }}</span></div>
                            <div class="flex justify-between text-sm"><span class="text-gray-400 text-xs">Telefon</span><span class="text-gray-700">{{ $studentContract->fourth_party_phone ?? '—' }}</span></div>
                            <div class="flex justify-between text-sm"><span class="text-gray-400 text-xs">Rahbar</span><span class="text-gray-700">{{ $studentContract->fourth_party_director_name ?? '—' }}</span></div>
                        </div>
                    </div>
                    @endif
                </div>

                {{-- O'ng ustun — ish beruvchi + amallar --}}
                <div>
                        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                            <div class="px-5 py-3 flex items-center gap-2" style="background: linear-gradient(135deg, #ecfdf5, #d1fae5); border-bottom: 2px solid #a7f3d0;">
                                <div class="w-6 h-6 rounded-md flex items-center justify-center" style="background: linear-gradient(135deg, #059669, #10b981);">
                                    <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3H21m-3.75 3H21"/></svg>
                                </div>
                                <span class="text-[11px] font-bold text-gray-500 uppercase tracking-wide">Potensial ish beruvchi</span>
                                <span class="text-[10px] text-gray-400 ml-auto">Tekshiring yoki to'ldiring</span>
                            </div>
                            <div class="p-5 space-y-3">
                                <div>
                                    <label class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1 block">Tashkilot nomi</label>
                                    <input type="text" name="employer_name" value="{{ old('employer_name', $studentContract->employer_name) }}" class="review-input" placeholder="Tashkilot to'liq nomi">
                                </div>
                                <div>
                                    <label class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1 block">Manzil</label>
                                    <input type="text" name="employer_address" value="{{ old('employer_address', $studentContract->employer_address) }}" class="review-input">
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1 block">Telefon</label>
                                        <input type="text" name="employer_phone" value="{{ old('employer_phone', $studentContract->employer_phone) }}" class="review-input">
                                    </div>
                                    <div>
                                        <label class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1 block">INN</label>
                                        <input type="text" name="employer_inn" value="{{ old('employer_inn', $studentContract->employer_inn) }}" class="review-input">
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1 block">Rahbar F.I.O</label>
                                        <input type="text" name="employer_director_name" value="{{ old('employer_director_name', $studentContract->employer_director_name) }}" class="review-input">
                                    </div>
                                    <div>
                                        <label class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1 block">Lavozim</label>
                                        <input type="text" name="employer_director_position" value="{{ old('employer_director_position', $studentContract->employer_director_position) }}" class="review-input">
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1 block">Bank hisob raqam</label>
                                        <input type="text" name="employer_bank_account" value="{{ old('employer_bank_account', $studentContract->employer_bank_account) }}" class="review-input">
                                    </div>
                                    <div>
                                        <label class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1 block">MFO</label>
                                        <input type="text" name="employer_bank_mfo" value="{{ old('employer_bank_mfo', $studentContract->employer_bank_mfo) }}" class="review-input">
                                    </div>
                                </div>
                            </div>

                            @if($studentContract->contract_type === '4_tomonlama')
                            <div class="px-5 py-3 flex items-center gap-2" style="background: linear-gradient(135deg, #f5f3ff, #ede9fe); border-top: 1px solid #e5e7eb; border-bottom: 1px solid #ddd6fe;">
                                <span class="text-[11px] font-bold text-gray-500 uppercase tracking-wide">4-tomon ma'lumotlari (tahrirlash)</span>
                            </div>
                            <div class="p-5 space-y-3">
                                <div>
                                    <label class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1 block">Nomi</label>
                                    <input type="text" name="fourth_party_name" value="{{ old('fourth_party_name', $studentContract->fourth_party_name) }}" class="review-input">
                                </div>
                                <div>
                                    <label class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1 block">Manzil</label>
                                    <input type="text" name="fourth_party_address" value="{{ old('fourth_party_address', $studentContract->fourth_party_address) }}" class="review-input">
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1 block">Telefon</label>
                                        <input type="text" name="fourth_party_phone" value="{{ old('fourth_party_phone', $studentContract->fourth_party_phone) }}" class="review-input">
                                    </div>
                                    <div>
                                        <label class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1 block">Rahbar F.I.O</label>
                                        <input type="text" name="fourth_party_director_name" value="{{ old('fourth_party_director_name', $studentContract->fourth_party_director_name) }}" class="review-input">
                                    </div>
                                </div>
                            </div>
                            @endif

                        </div>
                </div>
            </div>

            {{-- Amallar --}}
            <div class="mt-4 flex justify-end gap-3">
                <button type="submit" class="px-8 py-2.5 text-sm font-semibold rounded-lg text-white transition" style="background: linear-gradient(135deg, #059669, #10b981);"
                        onmouseover="this.style.boxShadow='0 4px 12px rgba(5,150,105,0.3)'" onmouseout="this.style.boxShadow='none'">
                    Tasdiqlash va hujjat yaratish
                </button>
                <button type="button" onclick="document.getElementById('reject-panel').style.display = document.getElementById('reject-panel').style.display === 'none' ? 'block' : 'none'"
                        class="px-8 py-2.5 text-sm font-semibold rounded-lg transition" style="background: #fee2e2; color: #991b1b; border: 1px solid #fecaca;">
                    Rad etish
                </button>
            </div>
            </form>

            <form method="POST" action="{{ route('admin.student-contracts.reject', $studentContract) }}" class="mt-3">
                @csrf
                <div id="reject-panel" style="display: none;" class="p-4 bg-red-50 rounded-xl border border-red-200">
                    <label class="text-[11px] font-bold text-red-700 uppercase tracking-wide mb-1 block">Rad etish sababi</label>
                    <textarea name="reject_reason" rows="3" required class="review-input" style="height: auto; border-color: #fca5a5;" placeholder="Sababni yozing..."></textarea>
                    <div class="flex justify-end mt-2">
                        <button type="submit" class="px-8 py-2 text-sm font-semibold rounded-lg text-white" style="background: linear-gradient(135deg, #991b1b, #dc2626);">
                            Rad etishni tasdiqlash
                        </button>
                    </div>
                </div>
            </form>

        </div>
    </div>
</x-app-layout>
