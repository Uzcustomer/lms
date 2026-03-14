<x-student-app-layout>
    <div class="py-6">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">

            <div class="flex items-center gap-3 mb-6">
                <a href="{{ route('student.contracts.index') }}" class="text-gray-500 hover:text-gray-700">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </a>
                <h1 class="text-2xl font-bold text-gray-800">Ishga joylashish shartnomasi arizasi</h1>
            </div>

            @if($errors->any())
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4">
                    <ul class="list-disc list-inside text-sm">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('student.contracts.store') }}" x-data="contractForm()">
                @csrf

                {{-- Talaba ma'lumotlari (avtomatik) --}}
                <div class="bg-white rounded-lg shadow-sm p-5 mb-4">
                    <h3 class="text-sm font-semibold text-gray-500 uppercase mb-3">Sizning ma'lumotlaringiz</h3>
                    <div class="grid grid-cols-2 gap-2 text-sm">
                        <div><span class="text-gray-500">FIO:</span> <span class="font-medium">{{ $student->full_name }}</span></div>
                        <div><span class="text-gray-500">Guruh:</span> <span class="font-medium">{{ $student->group_name }}</span></div>
                        <div><span class="text-gray-500">Fakultet:</span> <span class="font-medium">{{ $student->department_name }}</span></div>
                        <div><span class="text-gray-500">Yo'nalish:</span> <span class="font-medium">{{ $student->specialty_name }}</span></div>
                    </div>
                </div>

                {{-- Shartnoma turi --}}
                <div class="bg-white rounded-lg shadow-sm p-5 mb-4">
                    <h3 class="text-sm font-semibold text-gray-500 uppercase mb-3">Shartnoma turi</h3>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="relative flex items-center p-4 border-2 rounded-lg cursor-pointer transition"
                               :class="contractType === '3_tomonlama' ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:border-gray-300'">
                            <input type="radio" name="contract_type" value="3_tomonlama" x-model="contractType" class="sr-only">
                            <div>
                                <p class="font-semibold text-sm" :class="contractType === '3_tomonlama' ? 'text-blue-700' : 'text-gray-700'">3 tomonlama</p>
                                <p class="text-xs text-gray-500 mt-1">OTM, ish beruvchi va bitiruvchi</p>
                            </div>
                        </label>
                        <label class="relative flex items-center p-4 border-2 rounded-lg cursor-pointer transition"
                               :class="contractType === '4_tomonlama' ? 'border-purple-500 bg-purple-50' : 'border-gray-200 hover:border-gray-300'">
                            <input type="radio" name="contract_type" value="4_tomonlama" x-model="contractType" class="sr-only">
                            <div>
                                <p class="font-semibold text-sm" :class="contractType === '4_tomonlama' ? 'text-purple-700' : 'text-gray-700'">4 tomonlama</p>
                                <p class="text-xs text-gray-500 mt-1">OTM, ish beruvchi, bitiruvchi va MFY/tuman</p>
                            </div>
                        </label>
                    </div>
                </div>

                {{-- Bitiruvchi rekvizitlari --}}
                <div class="bg-white rounded-lg shadow-sm p-5 mb-4">
                    <h3 class="text-sm font-semibold text-blue-600 uppercase mb-3">Bitiruvchi rekvizitlari (siz to'ldirasiz)</h3>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Manzil <span class="text-red-500">*</span></label>
                            <input type="text" name="student_address" value="{{ old('student_address') }}" required
                                   class="w-full rounded-lg border-gray-300 text-sm focus:ring-blue-500 focus:border-blue-500"
                                   placeholder="Masalan: Sariosiyo tumani Gulobod mfy, Mustaqillik ko'chasi 55-uy">
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Telefon <span class="text-red-500">*</span></label>
                                <input type="text" name="student_phone" value="{{ old('student_phone', $student->phone) }}" required
                                       class="w-full rounded-lg border-gray-300 text-sm focus:ring-blue-500 focus:border-blue-500"
                                       placeholder="+998901234567">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Passport seriya va raqam</label>
                                <input type="text" name="student_passport" value="{{ old('student_passport') }}"
                                       class="w-full rounded-lg border-gray-300 text-sm focus:ring-blue-500 focus:border-blue-500"
                                       placeholder="AA1234567">
                            </div>
                        </div>
                        <div class="grid grid-cols-3 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Bank hisob raqam</label>
                                <input type="text" name="student_bank_account" value="{{ old('student_bank_account') }}"
                                       class="w-full rounded-lg border-gray-300 text-sm focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">MFO</label>
                                <input type="text" name="student_bank_mfo" value="{{ old('student_bank_mfo') }}"
                                       class="w-full rounded-lg border-gray-300 text-sm focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">INN</label>
                                <input type="text" name="student_inn" value="{{ old('student_inn') }}"
                                       class="w-full rounded-lg border-gray-300 text-sm focus:ring-blue-500 focus:border-blue-500">
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Mutaxassislik yo'nalishi</label>
                            <input type="text" name="specialty_field" value="{{ old('specialty_field') }}"
                                   class="w-full rounded-lg border-gray-300 text-sm focus:ring-blue-500 focus:border-blue-500"
                                   placeholder="Masalan: Davolash">
                        </div>
                    </div>
                </div>

                {{-- Potensial ish beruvchi --}}
                <div class="bg-white rounded-lg shadow-sm p-5 mb-4">
                    <h3 class="text-sm font-semibold text-green-600 uppercase mb-3">Potensial ish beruvchi (agar ma'lum bo'lsa)</h3>
                    <p class="text-xs text-gray-500 mb-3">Bu ma'lumotlarni registrator ofisi ham to'ldirishi mumkin</p>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Tashkilot nomi</label>
                            <input type="text" name="employer_name" value="{{ old('employer_name') }}"
                                   class="w-full rounded-lg border-gray-300 text-sm focus:ring-blue-500 focus:border-blue-500"
                                   placeholder="Ish beruvchi tashkilot to'liq nomi">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Manzil</label>
                            <input type="text" name="employer_address" value="{{ old('employer_address') }}"
                                   class="w-full rounded-lg border-gray-300 text-sm focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Telefon</label>
                                <input type="text" name="employer_phone" value="{{ old('employer_phone') }}"
                                       class="w-full rounded-lg border-gray-300 text-sm focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">INN</label>
                                <input type="text" name="employer_inn" value="{{ old('employer_inn') }}"
                                       class="w-full rounded-lg border-gray-300 text-sm focus:ring-blue-500 focus:border-blue-500">
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Rahbar FIO</label>
                                <input type="text" name="employer_director_name" value="{{ old('employer_director_name') }}"
                                       class="w-full rounded-lg border-gray-300 text-sm focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Lavozim</label>
                                <input type="text" name="employer_director_position" value="{{ old('employer_director_position') }}"
                                       class="w-full rounded-lg border-gray-300 text-sm focus:ring-blue-500 focus:border-blue-500">
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Bank hisob raqam</label>
                                <input type="text" name="employer_bank_account" value="{{ old('employer_bank_account') }}"
                                       class="w-full rounded-lg border-gray-300 text-sm focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">MFO</label>
                                <input type="text" name="employer_bank_mfo" value="{{ old('employer_bank_mfo') }}"
                                       class="w-full rounded-lg border-gray-300 text-sm focus:ring-blue-500 focus:border-blue-500">
                            </div>
                        </div>
                    </div>
                </div>

                {{-- 4-tomon (faqat 4-tomonlama tanlanganda) --}}
                <div x-show="contractType === '4_tomonlama'" x-transition class="bg-white rounded-lg shadow-sm p-5 mb-4">
                    <h3 class="text-sm font-semibold text-purple-600 uppercase mb-3">4-tomon ma'lumotlari (MFY/Tuman)</h3>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Nomi <span class="text-red-500">*</span></label>
                            <input type="text" name="fourth_party_name" value="{{ old('fourth_party_name') }}"
                                   class="w-full rounded-lg border-gray-300 text-sm focus:ring-blue-500 focus:border-blue-500"
                                   placeholder="Masalan: Sariosiyo tumani Gulobod mfy">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Manzil</label>
                            <input type="text" name="fourth_party_address" value="{{ old('fourth_party_address') }}"
                                   class="w-full rounded-lg border-gray-300 text-sm focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Telefon</label>
                                <input type="text" name="fourth_party_phone" value="{{ old('fourth_party_phone') }}"
                                       class="w-full rounded-lg border-gray-300 text-sm focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Rahbar FIO</label>
                                <input type="text" name="fourth_party_director_name" value="{{ old('fourth_party_director_name') }}"
                                       class="w-full rounded-lg border-gray-300 text-sm focus:ring-blue-500 focus:border-blue-500">
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Submit --}}
                <div class="flex gap-3">
                    <button type="submit" class="flex-1 px-6 py-3 bg-blue-600 text-white text-sm font-semibold rounded-lg hover:bg-blue-700 transition shadow-sm">
                        Ariza yuborish
                    </button>
                    <a href="{{ route('student.contracts.index') }}" class="px-6 py-3 bg-gray-200 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-300 transition">
                        Bekor qilish
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script>
        function contractForm() {
            return {
                contractType: '{{ old('contract_type', '3_tomonlama') }}'
            }
        }
    </script>
</x-student-app-layout>
