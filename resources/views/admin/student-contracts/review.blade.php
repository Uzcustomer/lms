<x-app-layout>
    <div class="p-4 sm:ml-64">
        <div class="mt-14 max-w-5xl">

            <div class="flex items-center gap-3 mb-6">
                <a href="{{ route('admin.student-contracts.index') }}" class="text-gray-500 hover:text-gray-700">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </a>
                <h1 class="text-2xl font-bold text-gray-800">Shartnomani ko'rib chiqish</h1>
                <span class="inline-flex px-3 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-700">
                    {{ $studentContract->status_label }}
                </span>
            </div>

            @if(session('error'))
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">{{ session('error') }}</div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                {{-- Chap ustun - Talaba ma'lumotlari (faqat ko'rish) --}}
                <div class="space-y-4">
                    <div class="bg-white rounded-lg shadow-sm p-5">
                        <h3 class="text-sm font-semibold text-gray-500 uppercase mb-3">Talaba (Bitiruvchi) ma'lumotlari</h3>
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between"><span class="text-gray-500">FIO:</span> <span class="font-semibold">{{ $studentContract->student_full_name }}</span></div>
                            <div class="flex justify-between"><span class="text-gray-500">HEMIS ID:</span> <span>{{ $studentContract->student_hemis_id }}</span></div>
                            <div class="flex justify-between"><span class="text-gray-500">Guruh:</span> <span>{{ $studentContract->group_name }}</span></div>
                            <div class="flex justify-between"><span class="text-gray-500">Fakultet:</span> <span>{{ $studentContract->department_name }}</span></div>
                            <div class="flex justify-between"><span class="text-gray-500">Yo'nalish:</span> <span>{{ $studentContract->specialty_name }}</span></div>
                            <div class="flex justify-between"><span class="text-gray-500">Shartnoma turi:</span>
                                <span class="font-semibold {{ $studentContract->contract_type === '4_tomonlama' ? 'text-purple-700' : 'text-blue-700' }}">{{ $studentContract->type_label }}</span>
                            </div>
                            <hr class="my-2">
                            <div class="flex justify-between"><span class="text-gray-500">Manzil:</span> <span>{{ $studentContract->student_address ?? '-' }}</span></div>
                            <div class="flex justify-between"><span class="text-gray-500">Telefon:</span> <span>{{ $studentContract->student_phone ?? '-' }}</span></div>
                            <div class="flex justify-between"><span class="text-gray-500">Passport:</span> <span>{{ $studentContract->student_passport ?? '-' }}</span></div>
                            <div class="flex justify-between"><span class="text-gray-500">Bank hisob:</span> <span>{{ $studentContract->student_bank_account ?? '-' }}</span></div>
                            <div class="flex justify-between"><span class="text-gray-500">MFO:</span> <span>{{ $studentContract->student_bank_mfo ?? '-' }}</span></div>
                            <div class="flex justify-between"><span class="text-gray-500">INN:</span> <span>{{ $studentContract->student_inn ?? '-' }}</span></div>
                            <div class="flex justify-between"><span class="text-gray-500">Mutaxassislik:</span> <span>{{ $studentContract->specialty_field ?? '-' }}</span></div>
                        </div>
                    </div>

                    @if($studentContract->contract_type === '4_tomonlama')
                    <div class="bg-white rounded-lg shadow-sm p-5">
                        <h3 class="text-sm font-semibold text-gray-500 uppercase mb-3">Talaba kiritgan 4-tomon ma'lumotlari</h3>
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between"><span class="text-gray-500">Nomi:</span> <span>{{ $studentContract->fourth_party_name ?? '-' }}</span></div>
                            <div class="flex justify-between"><span class="text-gray-500">Manzil:</span> <span>{{ $studentContract->fourth_party_address ?? '-' }}</span></div>
                            <div class="flex justify-between"><span class="text-gray-500">Telefon:</span> <span>{{ $studentContract->fourth_party_phone ?? '-' }}</span></div>
                            <div class="flex justify-between"><span class="text-gray-500">Rahbar:</span> <span>{{ $studentContract->fourth_party_director_name ?? '-' }}</span></div>
                        </div>
                    </div>
                    @endif
                </div>

                {{-- O'ng ustun - Registrator to'ldirishi mumkin bo'lgan qism --}}
                <div class="space-y-4">
                    {{-- Tasdiqlash formasi --}}
                    <form method="POST" action="{{ route('admin.student-contracts.approve', $studentContract) }}" id="approveForm">
                        @csrf
                        <div class="bg-white rounded-lg shadow-sm p-5">
                            <h3 class="text-sm font-semibold text-green-600 uppercase mb-3">Potensial ish beruvchi ma'lumotlari</h3>
                            <p class="text-xs text-gray-500 mb-3">Talaba kiritgan ma'lumotlarni tekshiring yoki to'ldiring</p>

                            <div class="space-y-3">
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Tashkilot nomi</label>
                                    <input type="text" name="employer_name" value="{{ old('employer_name', $studentContract->employer_name) }}"
                                           class="w-full rounded-lg border-gray-300 text-sm focus:ring-blue-500 focus:border-blue-500" placeholder="Tashkilot to'liq nomi">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Manzil</label>
                                    <input type="text" name="employer_address" value="{{ old('employer_address', $studentContract->employer_address) }}"
                                           class="w-full rounded-lg border-gray-300 text-sm focus:ring-blue-500 focus:border-blue-500">
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 mb-1">Telefon</label>
                                        <input type="text" name="employer_phone" value="{{ old('employer_phone', $studentContract->employer_phone) }}"
                                               class="w-full rounded-lg border-gray-300 text-sm focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 mb-1">INN</label>
                                        <input type="text" name="employer_inn" value="{{ old('employer_inn', $studentContract->employer_inn) }}"
                                               class="w-full rounded-lg border-gray-300 text-sm focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 mb-1">Rahbar FIO</label>
                                        <input type="text" name="employer_director_name" value="{{ old('employer_director_name', $studentContract->employer_director_name) }}"
                                               class="w-full rounded-lg border-gray-300 text-sm focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 mb-1">Lavozim</label>
                                        <input type="text" name="employer_director_position" value="{{ old('employer_director_position', $studentContract->employer_director_position) }}"
                                               class="w-full rounded-lg border-gray-300 text-sm focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 mb-1">Bank hisob raqam</label>
                                        <input type="text" name="employer_bank_account" value="{{ old('employer_bank_account', $studentContract->employer_bank_account) }}"
                                               class="w-full rounded-lg border-gray-300 text-sm focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 mb-1">MFO</label>
                                        <input type="text" name="employer_bank_mfo" value="{{ old('employer_bank_mfo', $studentContract->employer_bank_mfo) }}"
                                               class="w-full rounded-lg border-gray-300 text-sm focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                </div>
                            </div>
                        </div>

                        @if($studentContract->contract_type === '4_tomonlama')
                        <div class="bg-white rounded-lg shadow-sm p-5 mt-4">
                            <h3 class="text-sm font-semibold text-purple-600 uppercase mb-3">4-tomon ma'lumotlari (tahrirlash)</h3>
                            <div class="space-y-3">
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Nomi</label>
                                    <input type="text" name="fourth_party_name" value="{{ old('fourth_party_name', $studentContract->fourth_party_name) }}"
                                           class="w-full rounded-lg border-gray-300 text-sm focus:ring-blue-500 focus:border-blue-500">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Manzil</label>
                                    <input type="text" name="fourth_party_address" value="{{ old('fourth_party_address', $studentContract->fourth_party_address) }}"
                                           class="w-full rounded-lg border-gray-300 text-sm focus:ring-blue-500 focus:border-blue-500">
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 mb-1">Telefon</label>
                                        <input type="text" name="fourth_party_phone" value="{{ old('fourth_party_phone', $studentContract->fourth_party_phone) }}"
                                               class="w-full rounded-lg border-gray-300 text-sm focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 mb-1">Rahbar FIO</label>
                                        <input type="text" name="fourth_party_director_name" value="{{ old('fourth_party_director_name', $studentContract->fourth_party_director_name) }}"
                                               class="w-full rounded-lg border-gray-300 text-sm focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endif

                        <div class="flex gap-3 mt-4">
                            <button type="submit" class="flex-1 px-4 py-2.5 bg-green-600 text-white text-sm font-semibold rounded-lg hover:bg-green-700 transition">
                                Tasdiqlash va hujjat yaratish
                            </button>
                        </div>
                    </form>

                    {{-- Rad etish formasi --}}
                    <form method="POST" action="{{ route('admin.student-contracts.reject', $studentContract) }}" x-data="{ showReject: false }">
                        @csrf
                        <button type="button" @click="showReject = !showReject"
                                class="w-full px-4 py-2 bg-red-50 text-red-600 text-sm font-medium rounded-lg hover:bg-red-100 transition border border-red-200">
                            Rad etish
                        </button>
                        <div x-show="showReject" x-transition class="mt-3 bg-red-50 rounded-lg p-4 border border-red-200">
                            <label class="block text-xs font-medium text-red-700 mb-1">Rad etish sababi</label>
                            <textarea name="reject_reason" rows="3" required
                                      class="w-full rounded-lg border-red-300 text-sm focus:ring-red-500 focus:border-red-500"
                                      placeholder="Rad etish sababini yozing..."></textarea>
                            <button type="submit" class="mt-2 px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700 transition">
                                Rad etishni tasdiqlash
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
