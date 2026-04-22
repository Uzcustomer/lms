<x-student-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-sm text-gray-800 leading-tight">
            {{ __('Shartnoma') }}
        </h2>
    </x-slot>

    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 px-3 pb-6" x-data="{ contractType: '3_tomonlama' }">

        @if(session('success'))
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4">
                {{ session('error') }}
            </div>
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

        <form method="POST" action="{{ route('student.contracts.store') }}">
            @csrf

            {{-- Shartnoma turi tanlash --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 mb-4">
                <label class="block text-sm font-semibold text-gray-700 mb-3">{{ __('Shartnoma turini tanlang') }} <span class="text-red-500">*</span></label>
                <select name="contract_type" x-model="contractType" required class="w-full rounded-lg border-gray-300 text-sm focus:ring-blue-500 focus:border-blue-500">
                    <option value="3_tomonlama">{{ __('3 tomonlama shartnoma') }}</option>
                    <option value="4_tomonlama">{{ __('4 tomonlama shartnoma') }}</option>
                </select>
            </div>

            {{-- Talaba ma'lumotlari --}}
            <div class="bg-green-50 rounded-xl shadow-sm border border-green-200 p-5 mb-4">
                <h3 class="text-sm font-semibold text-green-700 uppercase mb-4">{{ __('Shartnomadagi ma\'lumotlaringiz') }}</h3>
                <div class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-green-600 mb-1">{{ __('F.I.SH') }} <span class="text-red-500">*</span></label>
                        <input type="text" name="student_name" value="{{ $placeholderData['student_name'] }}" required
                               class="w-full rounded-lg border-green-300 text-sm focus:ring-green-500 focus:border-green-500 bg-white">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-green-600 mb-1">{{ __('Manzil') }} <span class="text-red-500">*</span></label>
                        <input type="text" name="student_address" value="{{ $placeholderData['student_address'] }}" required
                               class="w-full rounded-lg border-green-300 text-sm focus:ring-green-500 focus:border-green-500 bg-white"
                               placeholder="{{ __('Tuman, MFY, ko\'cha, uy') }}">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-green-600 mb-1">{{ __('Yo\'nalish') }} <span class="text-red-500">*</span></label>
                        <input type="text" name="specialty_name" value="{{ $placeholderData['specialty_name'] }}" required
                               class="w-full rounded-lg border-green-300 text-sm focus:ring-green-500 focus:border-green-500 bg-white">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-green-600 mb-1">{{ __('Bitirish yili') }} <span class="text-red-500">*</span></label>
                        <input type="text" name="contract_year" value="{{ $placeholderData['contract_year'] }}" required
                               class="w-full rounded-lg border-green-300 text-sm focus:ring-green-500 focus:border-green-500 bg-white">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-green-600 mb-1">{{ __('Telefon') }} <span class="text-red-500">*</span></label>
                        <input type="text" name="student_phone" value="{{ $placeholderData['student_phone'] }}" required
                               class="w-full rounded-lg border-green-300 text-sm focus:ring-green-500 focus:border-green-500 bg-white"
                               placeholder="+998901234567">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-green-600 mb-1">{{ __('Passport seriya va raqami') }} <span class="text-red-500">*</span></label>
                        <div class="flex gap-2">
                            <input type="text" name="student_passport_series" value="{{ $placeholderData['student_passport_series'] }}" required
                                   class="w-24 rounded-lg border-green-300 text-sm focus:ring-green-500 focus:border-green-500 bg-white uppercase tracking-widest font-semibold text-center"
                                   placeholder="AA" maxlength="2"
                                   oninput="this.value = this.value.replace(/[^A-Za-z]/g, '').toUpperCase()">
                            <input type="text" name="student_passport_number" value="{{ $placeholderData['student_passport_number'] }}" required
                                   class="flex-1 rounded-lg border-green-300 text-sm focus:ring-green-500 focus:border-green-500 bg-white tracking-wide"
                                   placeholder="1234567" maxlength="7"
                                   oninput="this.value = this.value.replace(/\D/g, '')">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-green-600 mb-1">{{ __('Passport JSHSHIR') }} <span class="text-red-500">*</span></label>
                        <input type="text" name="student_inn" value="{{ $placeholderData['student_inn'] }}" required
                               class="w-full rounded-lg border-green-300 text-sm focus:ring-green-500 focus:border-green-500 bg-white tracking-wide"
                               placeholder="12345678901234" maxlength="14"
                               oninput="this.value = this.value.replace(/\D/g, '')">
                    </div>
                </div>
            </div>

            {{-- 3-tomon ma'lumotlari --}}
            <div class="bg-blue-50 rounded-xl shadow-sm border border-blue-200 p-5 mb-4">
                <h3 class="text-sm font-semibold text-blue-700 uppercase mb-4">{{ __('3-tomon ma\'lumotlari') }}</h3>
                <div class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-blue-600 mb-1">{{ __('Viloyat sog\'liqni saqlash bosh boshqarmasi') }} <span class="text-red-500">*</span></label>
                        <input type="text" name="employer_name" value="{{ $student->province_name ?? $placeholderData['employer_name'] }}" required
                               class="w-full rounded-lg border-blue-300 text-sm bg-gray-100 text-gray-700"
                               {{ $student->province_name ? 'readonly' : '' }}>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-blue-600 mb-1">{{ __('Viloyat sog\'liqni saqlash bosh boshqarmasi boshlig\'i') }} <span class="text-red-500">*</span></label>
                        <input type="text" name="employer_director_name" value="{{ $placeholderData['employer_director_name'] }}" required
                               class="w-full rounded-lg border-blue-300 text-sm focus:ring-blue-500 focus:border-blue-500 bg-white"
                               placeholder="{{ __('Rahbar to\'liq ismi') }}">
                    </div>
                </div>
            </div>

            {{-- 4 tomonlama qo'shimcha ma'lumotlar --}}
            <div x-show="contractType === '4_tomonlama'" x-transition class="bg-purple-50 rounded-xl shadow-sm border border-purple-200 p-5 mb-4">
                <h3 class="text-sm font-semibold text-purple-600 uppercase mb-4">{{ __('4-tomon ma\'lumotlari') }}</h3>
                <div class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-purple-600 mb-1">{{ __('Tuman sog\'liqni saqlash bosh boshqarmasi') }} <span class="text-red-500">*</span></label>
                        <input type="text" name="fourth_party_name" value="{{ $student->district_name ?? '' }}"
                               class="w-full rounded-lg border-purple-300 text-sm {{ $student->district_name ? 'bg-gray-100 text-gray-700' : 'focus:ring-purple-500 focus:border-purple-500 bg-white' }}"
                               {{ $student->district_name ? 'readonly' : '' }}
                               placeholder="{{ __('Tuman nomini kiriting') }}"
                               x-bind:required="contractType === '4_tomonlama'">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-purple-600 mb-1">{{ __('Tuman sog\'liqni saqlash bosh boshqarmasi boshlig\'i') }} <span class="text-red-500">*</span></label>
                        <input type="text" name="fourth_party_director_name" value=""
                               class="w-full rounded-lg border-purple-300 text-sm focus:ring-purple-500 focus:border-purple-500 bg-white"
                               placeholder="{{ __('Rahbar to\'liq ismi') }}"
                               x-bind:required="contractType === '4_tomonlama'">
                    </div>
                </div>
            </div>

            {{-- Shartnoma olish --}}
            <div class="bg-yellow-50 rounded-xl border border-yellow-200 p-4 mb-4">
                <p class="text-sm text-yellow-700">{{ __('Ma\'lumotlaringizni tekshiring, xatolik bo\'lsa tuzating va bo\'sh joylarni to\'ldiring.') }} <span class="text-red-500">*</span> {{ __('bilan belgilangan maydonlar majburiy.') }}</p>
            </div>
            <div class="text-center mb-6">
                <button type="submit" style="padding: 10px 50px;" class="inline-flex items-center gap-2 bg-green-600 text-white text-sm font-semibold rounded-xl hover:bg-green-700 transition shadow-sm">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
                    </svg>
                    {{ __('Ariza yuborish') }}
                </button>
            </div>
        </form>

        {{-- Tayyor shartnomalar --}}
        @if($contracts->isNotEmpty())
            <h3 class="text-lg font-bold text-gray-800 mb-3">{{ __('Yuborilgan arizalar') }}</h3>
            <div class="space-y-4">
                @foreach($contracts as $contract)
                    @php
                        $sc = [
                            'pending' => ['bg' => '#fef3c7', 'border' => '#fde68a', 'color' => '#92400e', 'label' => 'Kutilmoqda'],
                            'registrar_review' => ['bg' => '#dbeafe', 'border' => '#93c5fd', 'color' => '#1e40af', 'label' => 'Ko\'rib chiqilmoqda'],
                            'approved' => ['bg' => '#d1fae5', 'border' => '#a7f3d0', 'color' => '#065f46', 'label' => 'Tasdiqlangan'],
                            'rejected' => ['bg' => '#fee2e2', 'border' => '#fecaca', 'color' => '#991b1b', 'label' => 'Rad etilgan'],
                        ];
                        $s = $sc[$contract->status] ?? $sc['pending'];
                    @endphp
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                        <div style="background: {{ $s['bg'] }}; border-bottom: 1px solid {{ $s['border'] }}; padding: 12px 20px;">
                            <div class="flex items-center justify-between">
                                <div>
                                    <span style="font-size: 13px; font-weight: 700; color: {{ $s['color'] }};">{{ $s['label'] }}</span>
                                    <span style="font-size: 12px; color: {{ $s['color'] }}; opacity: 0.7;"> &middot; {{ $contract->type_label }} &middot; #{{ str_pad($contract->id, 4, '0', STR_PAD_LEFT) }} &middot; {{ $contract->created_at->format('d.m.Y H:i') }}</span>
                                </div>
                                @if($contract->status === 'approved' && $contract->document_path)
                                    <a href="{{ route('student.contracts.download', $contract) }}"
                                       style="display: inline-flex; align-items: center; gap: 5px; padding: 6px 16px; background: linear-gradient(135deg, #059669, #10b981); color: #fff; font-size: 12px; font-weight: 600; border-radius: 8px; text-decoration: none;">
                                        <svg style="width: 14px; height: 14px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                                        Yuklab olish
                                    </a>
                                @endif
                            </div>
                        </div>
                        <div class="p-4">
                            <div class="text-sm text-gray-600">
                                <span class="text-gray-400 text-xs">Ish beruvchi:</span> <span class="font-medium text-gray-700">{{ $contract->employer_name ? $contract->employer_name . ' viloyati sog\'liqni saqlash bosh boshqarmasi' : '—' }}</span>
                            </div>
                            @if($contract->status === 'rejected' && $contract->reject_reason)
                                <div class="mt-2 p-2.5 bg-red-50 rounded-lg border border-red-100 text-sm text-red-700">
                                    <strong>Rad etish sababi:</strong> {{ $contract->reject_reason }}
                                </div>
                            @endif
                            @if($contract->status === 'pending')
                                <div class="mt-2 text-xs text-amber-600">Ariza ko'rib chiqilmoqda. Tasdiqlangandan keyin hujjatni yuklab olishingiz mumkin.</div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-student-app-layout>
