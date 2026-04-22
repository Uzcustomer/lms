<x-student-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-sm text-gray-800 leading-tight">Ishga joylashish shartnomasi</h2>
    </x-slot>

    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 px-3 pb-6">

        @if(session('success'))
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4 text-sm">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4 text-sm">{{ session('error') }}</div>
        @endif
        @if($errors->any())
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4">
                <ul class="list-disc list-inside text-sm">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Mavjud arizalar --}}
        @if($contracts->isNotEmpty())
            <h3 class="text-base font-bold text-gray-800 mb-3">Yuborilgan arizalar</h3>
            <div class="space-y-3 mb-6">
                @foreach($contracts as $contract)
                    @php
                        $sc = [
                            'pending' => ['bg' => '#fef3c7', 'border' => '#fde68a', 'color' => '#92400e', 'label' => 'Kutilmoqda — ko\'rib chiqilmoqda'],
                            'registrar_review' => ['bg' => '#dbeafe', 'border' => '#93c5fd', 'color' => '#1e40af', 'label' => 'Registrator ko\'rib chiqmoqda'],
                            'approved' => ['bg' => '#d1fae5', 'border' => '#a7f3d0', 'color' => '#065f46', 'label' => 'Tasdiqlangan — yuklab olishingiz mumkin'],
                            'rejected' => ['bg' => '#fee2e2', 'border' => '#fecaca', 'color' => '#991b1b', 'label' => 'Rad etilgan'],
                        ];
                        $s = $sc[$contract->status] ?? $sc['pending'];
                    @endphp
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                        <div class="p-4 rounded-t-xl" style="background: {{ $s['bg'] }}; border-bottom: 1px solid {{ $s['border'] }};">
                            <div class="flex items-center justify-between">
                                <div>
                                    <div class="font-bold text-sm" style="color: {{ $s['color'] }};">{{ $s['label'] }}</div>
                                    <div class="text-xs mt-0.5" style="color: {{ $s['color'] }}; opacity: 0.7;">{{ $contract->type_label }} &middot; #{{ str_pad($contract->id, 4, '0', STR_PAD_LEFT) }} &middot; {{ $contract->created_at->format('d.m.Y H:i') }}</div>
                                </div>
                                @if($contract->status === 'approved' && $contract->document_path)
                                    <a href="{{ route('student.contracts.download', $contract) }}"
                                       style="display: inline-flex; align-items: center; gap: 6px; padding: 8px 20px; background: linear-gradient(135deg, #059669, #10b981); color: #fff; font-size: 13px; font-weight: 600; border-radius: 8px; text-decoration: none;">
                                        <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                                        Yuklab olish
                                    </a>
                                @endif
                            </div>
                        </div>
                        <div class="p-4">
                            <div class="grid grid-cols-2 gap-2 text-sm">
                                <div><span class="text-gray-400 text-xs">Ish beruvchi:</span> <span class="font-medium text-gray-700">{{ $contract->employer_name ?: '—' }}</span></div>
                                <div><span class="text-gray-400 text-xs">Rahbar:</span> <span class="font-medium text-gray-700">{{ $contract->employer_director_name ?: '—' }}</span></div>
                            </div>
                            @if($contract->status === 'rejected' && $contract->reject_reason)
                                <div class="mt-3 p-3 bg-red-50 rounded-lg border border-red-100 text-sm text-red-700">
                                    <strong>Rad etish sababi:</strong> {{ $contract->reject_reason }}
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Yangi ariza --}}
        @php
            $hasActive = $contracts->whereIn('status', ['pending', 'registrar_review', 'approved'])->isNotEmpty();
        @endphp
        @if(!$hasActive)
            <h3 class="text-base font-bold text-gray-800 mb-3">Yangi ariza yuborish</h3>
            <form method="POST" action="{{ route('student.contracts.store') }}" x-data="{ contractType: '3_tomonlama' }">
                @csrf

                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 mb-4">
                    <label class="block text-sm font-semibold text-gray-700 mb-3">Shartnoma turini tanlang <span class="text-red-500">*</span></label>
                    <select name="contract_type" x-model="contractType" required class="w-full rounded-lg border-gray-300 text-sm focus:ring-blue-500 focus:border-blue-500">
                        <option value="3_tomonlama">3 tomonlama shartnoma</option>
                        <option value="4_tomonlama">4 tomonlama shartnoma</option>
                    </select>
                </div>

                <div class="bg-green-50 rounded-xl shadow-sm border border-green-200 p-5 mb-4">
                    <h3 class="text-sm font-semibold text-green-700 uppercase mb-4">Bitiruvchi ma'lumotlari</h3>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs font-medium text-green-600 mb-1">Manzil <span class="text-red-500">*</span></label>
                            <input type="text" name="student_address" value="{{ old('student_address', $placeholderData['student_address'] ?? '') }}" required
                                   class="w-full rounded-lg border-green-300 text-sm focus:ring-green-500 focus:border-green-500 bg-white" placeholder="Tuman, MFY, ko'cha, uy">
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-green-600 mb-1">Telefon <span class="text-red-500">*</span></label>
                                <input type="text" name="student_phone" value="{{ old('student_phone', $placeholderData['student_phone'] ?? '') }}" required
                                       class="w-full rounded-lg border-green-300 text-sm focus:ring-green-500 focus:border-green-500 bg-white" placeholder="+998901234567">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-green-600 mb-1">Passport</label>
                                <input type="text" name="student_passport" value="{{ old('student_passport') }}"
                                       class="w-full rounded-lg border-green-300 text-sm focus:ring-green-500 focus:border-green-500 bg-white" placeholder="AA1234567">
                            </div>
                        </div>
                        <div class="grid grid-cols-3 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-green-600 mb-1">Bank hisob</label>
                                <input type="text" name="student_bank_account" value="{{ old('student_bank_account') }}" class="w-full rounded-lg border-green-300 text-sm focus:ring-green-500 focus:border-green-500 bg-white">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-green-600 mb-1">MFO</label>
                                <input type="text" name="student_bank_mfo" value="{{ old('student_bank_mfo') }}" class="w-full rounded-lg border-green-300 text-sm focus:ring-green-500 focus:border-green-500 bg-white">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-green-600 mb-1">INN (JSHSHIR)</label>
                                <input type="text" name="student_inn" value="{{ old('student_inn') }}" class="w-full rounded-lg border-green-300 text-sm focus:ring-green-500 focus:border-green-500 bg-white" placeholder="12345678901234">
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-green-600 mb-1">Mutaxassislik</label>
                            <input type="text" name="specialty_field" value="{{ old('specialty_field') }}" class="w-full rounded-lg border-green-300 text-sm focus:ring-green-500 focus:border-green-500 bg-white">
                        </div>
                    </div>
                </div>

                <div class="bg-blue-50 rounded-xl shadow-sm border border-blue-200 p-5 mb-4">
                    <h3 class="text-sm font-semibold text-blue-700 uppercase mb-4">Potensial ish beruvchi</h3>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs font-medium text-blue-600 mb-1">Tashkilot nomi</label>
                            <input type="text" name="employer_name" value="{{ old('employer_name') }}" class="w-full rounded-lg border-blue-300 text-sm focus:ring-blue-500 focus:border-blue-500 bg-white">
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-blue-600 mb-1">Rahbar F.I.O</label>
                                <input type="text" name="employer_director_name" value="{{ old('employer_director_name') }}" class="w-full rounded-lg border-blue-300 text-sm focus:ring-blue-500 focus:border-blue-500 bg-white">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-blue-600 mb-1">Lavozim</label>
                                <input type="text" name="employer_director_position" value="{{ old('employer_director_position') }}" class="w-full rounded-lg border-blue-300 text-sm focus:ring-blue-500 focus:border-blue-500 bg-white">
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-blue-600 mb-1">Manzil</label>
                                <input type="text" name="employer_address" value="{{ old('employer_address') }}" class="w-full rounded-lg border-blue-300 text-sm focus:ring-blue-500 focus:border-blue-500 bg-white">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-blue-600 mb-1">Telefon</label>
                                <input type="text" name="employer_phone" value="{{ old('employer_phone') }}" class="w-full rounded-lg border-blue-300 text-sm focus:ring-blue-500 focus:border-blue-500 bg-white">
                            </div>
                        </div>
                    </div>
                </div>

                <div x-show="contractType === '4_tomonlama'" x-transition class="bg-purple-50 rounded-xl shadow-sm border border-purple-200 p-5 mb-4">
                    <h3 class="text-sm font-semibold text-purple-600 uppercase mb-4">4-tomon ma'lumotlari</h3>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs font-medium text-purple-600 mb-1">Tuman sog'liqni saqlash boshqarmasi <span class="text-red-500">*</span></label>
                            <input type="text" name="fourth_party_name" value="{{ old('fourth_party_name') }}" class="w-full rounded-lg border-purple-300 text-sm focus:ring-purple-500 focus:border-purple-500 bg-white"
                                   x-bind:required="contractType === '4_tomonlama'">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-purple-600 mb-1">Boshlig'i <span class="text-red-500">*</span></label>
                            <input type="text" name="fourth_party_director_name" value="{{ old('fourth_party_director_name') }}" class="w-full rounded-lg border-purple-300 text-sm focus:ring-purple-500 focus:border-purple-500 bg-white"
                                   x-bind:required="contractType === '4_tomonlama'">
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-purple-600 mb-1">Manzil</label>
                                <input type="text" name="fourth_party_address" value="{{ old('fourth_party_address') }}" class="w-full rounded-lg border-purple-300 text-sm focus:ring-purple-500 focus:border-purple-500 bg-white">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-purple-600 mb-1">Telefon</label>
                                <input type="text" name="fourth_party_phone" value="{{ old('fourth_party_phone') }}" class="w-full rounded-lg border-purple-300 text-sm focus:ring-purple-500 focus:border-purple-500 bg-white">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="text-center">
                    <button type="submit" style="padding: 12px 50px; background: linear-gradient(135deg, #2b5ea7, #3b82f6); color: #fff; font-size: 14px; font-weight: 600; border: none; border-radius: 12px; cursor: pointer;">
                        Ariza yuborish
                    </button>
                </div>
            </form>
        @elseif(!$contracts->where('status', 'rejected')->isEmpty() && $hasActive)
        @else
            <div class="bg-blue-50 rounded-xl border border-blue-200 p-4 text-center text-sm text-blue-700 font-medium">
                Sizda faol ariza mavjud. Yangi ariza yuborish uchun avvalgisi ko'rib chiqilishini kuting.
            </div>
        @endif
    </div>
</x-student-app-layout>
