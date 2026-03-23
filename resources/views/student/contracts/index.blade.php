<x-student-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-sm text-gray-800 leading-tight">
            Shartnoma
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

        <form method="POST" action="{{ route('student.contracts.generate') }}">
            @csrf

            {{-- Shartnoma turi tanlash --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 mb-4">
                <label class="block text-sm font-semibold text-gray-700 mb-3">Shartnoma turini tanlang</label>
                <select name="contract_type" x-model="contractType" class="w-full rounded-lg border-gray-300 text-sm focus:ring-blue-500 focus:border-blue-500">
                    <option value="3_tomonlama">3 tomonlama shartnoma</option>
                    <option value="4_tomonlama">4 tomonlama shartnoma</option>
                </select>
            </div>

            {{-- Talaba ma'lumotlari --}}
            <div class="bg-green-50 rounded-xl shadow-sm border border-green-200 p-5 mb-4">
                <h3 class="text-sm font-semibold text-green-700 uppercase mb-4">Shartnomadagi ma'lumotlaringiz</h3>
                <div class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-green-600 mb-1">F.I.SH</label>
                        <input type="text" name="student_name" value="{{ $placeholderData['student_name'] }}"
                               class="w-full rounded-lg border-green-300 text-sm focus:ring-green-500 focus:border-green-500 bg-white">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-green-600 mb-1">Manzil</label>
                        <input type="text" name="student_address" value="{{ $placeholderData['student_address'] }}"
                               class="w-full rounded-lg border-green-300 text-sm focus:ring-green-500 focus:border-green-500 bg-white"
                               placeholder="Tuman, MFY, ko'cha, uy">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-green-600 mb-1">Yo'nalish</label>
                        <input type="text" name="specialty_name" value="{{ $placeholderData['specialty_name'] }}"
                               class="w-full rounded-lg border-green-300 text-sm focus:ring-green-500 focus:border-green-500 bg-white">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-green-600 mb-1">Bitirish yili</label>
                        <input type="text" name="contract_year" value="{{ $placeholderData['contract_year'] }}"
                               class="w-full rounded-lg border-green-300 text-sm focus:ring-green-500 focus:border-green-500 bg-white">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-green-600 mb-1">Telefon</label>
                        <input type="text" name="student_phone" value="{{ $placeholderData['student_phone'] }}"
                               class="w-full rounded-lg border-green-300 text-sm focus:ring-green-500 focus:border-green-500 bg-white"
                               placeholder="+998901234567">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-green-600 mb-1">Passport seriya va raqami</label>
                        <div class="flex gap-2">
                            <input type="text" name="student_passport_series" value="{{ $placeholderData['student_passport_series'] }}"
                                   class="w-24 rounded-lg border-green-300 text-sm focus:ring-green-500 focus:border-green-500 bg-white uppercase tracking-widest font-semibold text-center"
                                   placeholder="AA" maxlength="2"
                                   oninput="this.value = this.value.replace(/[^A-Za-z]/g, '').toUpperCase()">
                            <input type="text" name="student_passport_number" value="{{ $placeholderData['student_passport_number'] }}"
                                   class="flex-1 rounded-lg border-green-300 text-sm focus:ring-green-500 focus:border-green-500 bg-white tracking-wide"
                                   placeholder="1234567" maxlength="7"
                                   oninput="this.value = this.value.replace(/\D/g, '')">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-green-600 mb-1">Passport JSHSHIR</label>
                        <input type="text" name="student_inn" value="{{ $placeholderData['student_inn'] }}"
                               class="w-full rounded-lg border-green-300 text-sm focus:ring-green-500 focus:border-green-500 bg-white tracking-wide"
                               placeholder="12345678901234" maxlength="14"
                               oninput="this.value = this.value.replace(/\D/g, '')">
                    </div>
                </div>
            </div>

            {{-- Ish beruvchi ma'lumotlari --}}
            <div class="bg-blue-50 rounded-xl shadow-sm border border-blue-200 p-5 mb-4">
                <h3 class="text-sm font-semibold text-blue-700 uppercase mb-4">Ish beruvchi ma'lumotlari</h3>
                <div class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-blue-600 mb-1">Ish beruvchi F.I.SH</label>
                        <input type="text" name="employer_name" value="{{ $placeholderData['employer_name'] }}"
                               class="w-full rounded-lg border-blue-300 text-sm focus:ring-blue-500 focus:border-blue-500 bg-white"
                               placeholder="Tashkilot nomi">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-blue-600 mb-1">Direktor F.I.SH</label>
                        <input type="text" name="employer_director_name" value="{{ $placeholderData['employer_director_name'] }}"
                               class="w-full rounded-lg border-blue-300 text-sm focus:ring-blue-500 focus:border-blue-500 bg-white"
                               placeholder="Rahbar to'liq ismi">
                    </div>
                </div>
            </div>

            {{-- 4 tomonlama qo'shimcha ma'lumotlar --}}
            <div x-show="contractType === '4_tomonlama'" x-transition class="bg-purple-50 rounded-xl shadow-sm border border-purple-200 p-5 mb-4">
                <h3 class="text-sm font-semibold text-purple-600 uppercase mb-4">4-tomon ma'lumotlari</h3>
                <div class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-purple-600 mb-1">MFY/tuman nomi</label>
                        <input type="text" name="fourth_party_name" value=""
                               class="w-full rounded-lg border-purple-300 text-sm focus:ring-purple-500 focus:border-purple-500 bg-white"
                               placeholder="MFY yoki tuman nomi">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-purple-600 mb-1">4-tomon manzili</label>
                        <input type="text" name="fourth_party_address" value=""
                               class="w-full rounded-lg border-purple-300 text-sm focus:ring-purple-500 focus:border-purple-500 bg-white"
                               placeholder="Manzil">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-purple-600 mb-1">4-tomon telefon</label>
                        <input type="text" name="fourth_party_phone" value=""
                               class="w-full rounded-lg border-purple-300 text-sm focus:ring-purple-500 focus:border-purple-500 bg-white"
                               placeholder="+998...">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-purple-600 mb-1">4-tomon rahbar F.I.SH</label>
                        <input type="text" name="fourth_party_director_name" value=""
                               class="w-full rounded-lg border-purple-300 text-sm focus:ring-purple-500 focus:border-purple-500 bg-white"
                               placeholder="Rahbar to'liq ismi">
                    </div>
                </div>
            </div>

            {{-- Saqlash --}}
            <div class="bg-yellow-50 rounded-xl border border-yellow-200 p-4 mb-4">
                <p class="text-sm text-yellow-700">Ma'lumotlaringizni tekshiring, xatolik bo'lsa tuzating va bo'sh joylarni to'ldiring.</p>
            </div>
            <div class="text-center mb-6">
                <button type="submit" style="padding: 10px 50px;" class="inline-flex items-center gap-2 bg-green-600 text-white text-sm font-semibold rounded-xl hover:bg-green-700 transition shadow-sm">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
                    </svg>
                    Saqlash
                </button>
            </div>
        </form>

        {{-- Tayyor shartnomalar --}}
        @if($contracts->isNotEmpty())
            <h3 class="text-lg font-bold text-gray-800 mb-3">Yuborilgan arizalar</h3>
            <div class="space-y-4">
                @foreach($contracts as $contract)
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                        <div class="p-5">
                            <div class="mb-3">
                                <h3 class="text-base font-semibold text-gray-800">{{ $contract->type_label }}</h3>
                                <p class="text-sm text-gray-500">Ariza #{{ $contract->id }} | {{ $contract->created_at->format('d.m.Y H:i') }}</p>
                            </div>

                            @if($contract->status === 'rejected' && $contract->reject_reason)
                                <div class="bg-red-50 rounded-lg p-3 mt-2">
                                    <p class="text-sm text-red-700"><strong>Rad etish sababi:</strong> {{ $contract->reject_reason }}</p>
                                </div>
                            @endif

                            <div class="flex items-center gap-3 mt-4 pt-3 border-t border-gray-100">
                                @if($contract->document_path)
                                    <a href="{{ route('student.contracts.download', $contract) }}"
                                       class="inline-flex items-center px-3 py-1.5 bg-green-600 text-white text-xs font-medium rounded-lg hover:bg-green-700 transition">
                                        <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                        Yuklab olish
                                    </a>
                                @endif
                                <form method="POST" action="{{ route('student.contracts.destroy', $contract) }}" class="inline"
                                      onsubmit="return confirm('Haqiqatan ham bu shartnomani o\'chirmoqchimisiz?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="inline-flex items-center px-3 py-1.5 bg-red-600 text-white text-xs font-medium rounded-lg hover:bg-red-700 transition">
                                        <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        O'chirish
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-student-app-layout>
