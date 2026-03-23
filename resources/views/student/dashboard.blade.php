<x-student-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-sm text-gray-800 leading-tight">
            {{ __('Talaba boshqaruv paneli') }}
        </h2>
    </x-slot>

    <div class="pb-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-2xl font-bold">Xush kelibsiz, {{ Auth::guard('student')->user()->full_name }}
                            !</h3>
                        <span class="px-3 py-1 text-sm font-semibold text-white bg-green-500 rounded-full">Aktiv</span>
                    </div>


                    @if(Auth::guard('student')->user()->is_graduate)
                    <div class="bg-white shadow rounded-lg p-5 mb-6 border border-gray-200" x-data="{ showPassportForm: false }">
                        <div class="flex items-center gap-3 mb-4">
                            <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 9h3.75M15 12h3.75M15 15h3.75M4.5 19.5h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5zm6-10.125a1.875 1.875 0 11-3.75 0 1.875 1.875 0 013.75 0zm1.294 6.336a6.721 6.721 0 01-3.17.789 6.721 6.721 0 01-3.168-.789 3.376 3.376 0 016.338 0z" />
                            </svg>
                            <h4 class="text-lg font-semibold text-gray-800">Pasport ma'lumotlarim</h4>
                        </div>
                        <div class="flex items-center gap-3" style="margin-bottom: 10px;">
                            @if($studentPassport)
                                <span class="inline-flex items-center gap-1 px-3 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-700 border border-green-200">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
                                    </svg>
                                    To'ldirilgan
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 px-3 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-700 border border-red-200">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/>
                                    </svg>
                                    To'ldirilmagan
                                </span>
                            @endif
                            <button @click="showPassportForm = !showPassportForm" type="button"
                                    class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium rounded-lg transition"
                                    :class="showPassportForm ? 'bg-gray-200 text-gray-700' : '{{ $studentPassport ? 'bg-yellow-500 text-white hover:bg-yellow-600' : 'bg-indigo-600 text-white hover:bg-indigo-700' }}'">
                                <span x-text="showPassportForm ? 'Yopish' : '{{ $studentPassport ? 'Tahrirlash' : 'To\'ldirish' }}'"></span>
                            </button>
                        </div>

                        <div x-show="showPassportForm" x-transition class="border-t border-gray-200 pt-5">
                            <form method="POST" action="{{ route('student.passport.store') }}" enctype="multipart/form-data">
                                @csrf
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">To'liq ism familiyangiz (pasport bilan bir xil) <span class="text-red-500">*</span></label>
                                        <input type="text" name="full_name_uz" value="{{ $studentPassport->full_name_uz ?? '' }}" required
                                               class="w-full rounded-lg border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Inglizcha to'liq ism familiyangiz (pasport bilan bir xil) <span class="text-red-500">*</span></label>
                                        <input type="text" name="full_name_en" value="{{ $studentPassport->full_name_en ?? '' }}" required
                                               class="w-full rounded-lg border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Passport seriya va raqamingiz <span class="text-red-500">*</span></label>
                                        <div class="flex gap-2">
                                            <input type="text" name="passport_series" value="{{ $studentPassport->passport_series ?? '' }}" required
                                                   class="w-24 rounded-lg border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500 uppercase tracking-widest font-semibold text-center"
                                                   placeholder="AA" maxlength="2"
                                                   oninput="this.value = this.value.replace(/[^A-Za-z]/g, '').toUpperCase()">
                                            <input type="text" name="passport_number" value="{{ $studentPassport->passport_number ?? '' }}" required
                                                   class="flex-1 rounded-lg border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500 tracking-wide"
                                                   placeholder="1234567" maxlength="7"
                                                   oninput="this.value = this.value.replace(/\D/g, '')">
                                        </div>
                                    </div>
                                    <div></div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Pasport oldi tarafi <span class="text-gray-400">(max 1MB)</span> <span class="text-red-500">*</span></label>
                                        <input type="file" name="passport_front" accept=".jpg,.jpeg,.pdf" {{ $studentPassport ? '' : 'required' }}
                                               onchange="checkFileSize(this)"
                                               class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                                        <p class="text-xs text-red-600 mt-1 hidden" data-file-error></p>
                                        @if($studentPassport?->passport_front_path)
                                            <p class="text-xs text-green-600 mt-1">Yuklangan (qayta yuklash ixtiyoriy)</p>
                                        @endif
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Pasport orqa tarafi <span class="text-gray-400">(max 1MB)</span> <span class="text-red-500">*</span></label>
                                        <input type="file" name="passport_back" accept=".jpg,.jpeg,.pdf" {{ $studentPassport ? '' : 'required' }}
                                               onchange="checkFileSize(this)"
                                               class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                                        <p class="text-xs text-red-600 mt-1 hidden" data-file-error></p>
                                        @if($studentPassport?->passport_back_path)
                                            <p class="text-xs text-green-600 mt-1">Yuklangan (qayta yuklash ixtiyoriy)</p>
                                        @endif
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Xorijga chiqish pasporti <span class="text-gray-400">(max 1MB)</span> <span class="text-red-500">*</span></label>
                                        <input type="file" name="foreign_passport" accept=".jpg,.jpeg,.pdf" {{ $studentPassport ? '' : 'required' }}
                                               onchange="checkFileSize(this)"
                                               class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                                        <p class="text-xs text-red-600 mt-1 hidden" data-file-error></p>
                                        @if($studentPassport?->foreign_passport_path)
                                            <p class="text-xs text-green-600 mt-1">Yuklangan (qayta yuklash ixtiyoriy)</p>
                                        @endif
                                    </div>
                                </div>
                                <div class="text-right" style="margin-top: 10px;">
                                    <button type="submit"
                                            class="inline-flex items-center gap-2 px-6 py-2.5 bg-indigo-600 text-white text-sm font-semibold rounded-lg hover:bg-indigo-700 transition shadow-sm">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
                                        </svg>
                                        Saqlash
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    @endif

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                        <div class="bg-blue-100 p-4 rounded-lg text-center">
                            <h4 class="text-lg font-semibold mb-2">Talaba GPA si</h4>
                            <p class="text-3xl font-bold text-blue-600">{{$avgGpa }}</p>
                        </div>
                        <div class="bg-green-100 p-4 rounded-lg text-center">
                            <h4 class="text-lg font-semibold mb-2">Qoldirgan darslar soni</h4>
                            <p class="text-3xl font-bold text-green-600">{{ $totalAbsent }} ta
                                </p>
                        </div>
                        <div class="bg-yellow-100 p-4 rounded-lg text-center">
                            <h4 class="text-lg font-semibold mb-2">Qayta topshirishlar soni</h4>
                            <p class="text-3xl font-bold text-yellow-600">{{ $debtSubjectsCount }} ta</p>
                        </div>
                    </div>

                    <div class="bg-white shadow rounded-lg p-6 mb-6">
                        <h4 class="text-lg font-semibold mb-4">Tezkor havolalar</h4>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <a href="{{ route('student.schedule') }}"
                               class="flex items-center justify-center p-4 bg-indigo-100 rounded-lg hover:bg-indigo-200 transition">
                                <svg class="w-6 h-6 mr-2 text-indigo-600" fill="none" stroke="currentColor"
                                     viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                </svg>
                                Dars jadvali
                            </a>
                            <a href="{{ route('student.attendance') }}"
                               class="flex items-center justify-center p-4 bg-green-100 rounded-lg hover:bg-green-200 transition">
                                <svg class="w-6 h-6 mr-2 text-green-600" fill="none" stroke="currentColor"
                                     viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                                </svg>
                                Davomat
                            </a>
                            <a href="{{ route('student.subjects') }}"
                               class="flex items-center justify-center p-4 bg-yellow-100 rounded-lg hover:bg-yellow-200 transition">
                                <svg class="w-6 h-6 mr-2 text-yellow-600" fill="none" stroke="currentColor"
                                     viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                                </svg>
                                Joriy fanlar
                            </a>
                            <a href="{{ route('student.pending-lessons') }}"
                               class="flex items-center justify-center p-4 bg-red-100 rounded-lg hover:bg-red-200 transition">
                                <svg class="w-6 h-6 mr-2 text-red-600" fill="none" stroke="currentColor"
                                     viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                                </svg>
                                Qayta topshirish
                            </a>
                            <a href="{{ route('student.exam-schedule') }}"
                               class="flex items-center justify-center p-4 bg-purple-100 rounded-lg hover:bg-purple-200 transition">
                                <svg class="w-6 h-6 mr-2 text-purple-600" fill="none" stroke="currentColor"
                                     viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"></path>
                                </svg>
                                Imtihon jadvali
                            </a>
                        </div>
                    </div>

                    <div class="bg-white shadow rounded-lg p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h4 class="text-lg font-semibold">Ish e'lonlari</h4>
                            <a href="{{ route('student.job-listings') }}" class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">Barchasi</a>
                        </div>
                        <div class="text-center py-8">
                            <svg class="mx-auto h-12 w-12 text-green-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                            </svg>
                            <p class="mt-3 text-base text-gray-600">Ushbu havola orqali bo'sh ish o'rinlarini ko'rishingiz mumkin</p>
                            <a href="https://tashmedunitf.uz/ish-elonlari/" target="_blank" rel="noopener noreferrer"
                               class="mt-5 inline-flex items-center gap-2 px-6 py-3 rounded-xl text-white font-semibold text-base shadow-lg transition hover:opacity-90 bg-green-600 hover:bg-green-700">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                                </svg>
                                Kirish
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<script>
function checkFileSize(input) {
    var errorEl = input.parentElement.querySelector('[data-file-error]');
    if (input.files.length > 0 && input.files[0].size > 1024 * 1024) {
        errorEl.textContent = 'Fayl hajmi 1MB dan oshmasligi kerak!';
        errorEl.classList.remove('hidden');
        input.value = '';
    } else {
        errorEl.textContent = '';
        errorEl.classList.add('hidden');
    }
}
</script>
</x-student-app-layout>
