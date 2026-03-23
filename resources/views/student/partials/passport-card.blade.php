<div class="bg-white shadow rounded-lg p-5 mb-6 border border-gray-200" x-data="{ showPassportForm: false, previewImage: null }">
    {{-- Header: title left, status badge top-right --}}
    <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-3">
            <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 9h3.75M15 12h3.75M15 15h3.75M4.5 19.5h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5zm6-10.125a1.875 1.875 0 11-3.75 0 1.875 1.875 0 013.75 0zm1.294 6.336a6.721 6.721 0 01-3.17.789 6.721 6.721 0 01-3.168-.789 3.376 3.376 0 016.338 0z" />
            </svg>
            <h4 class="text-lg font-semibold text-gray-800">Pasport ma'lumotlarim</h4>
        </div>
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
    </div>

    {{-- Uploaded files viewer (shown when passport exists and form is open) --}}
    @if($studentPassport)
        <div x-show="showPassportForm" x-transition class="mb-5">
            <p class="text-sm font-medium text-gray-600 mb-3">Yuklangan hujjatlar:</p>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                @if($studentPassport->passport_front_path)
                    <div class="border border-gray-200 rounded-lg p-3 bg-gray-50">
                        <p class="text-xs font-medium text-gray-500 mb-2">Pasport oldi tarafi</p>
                        @if(Str::endsWith($studentPassport->passport_front_path, ['.jpg', '.jpeg', '.png']))
                            <img src="{{ asset('storage/' . $studentPassport->passport_front_path) }}" alt="Pasport oldi"
                                 class="w-full h-32 object-cover rounded cursor-pointer hover:opacity-80 transition"
                                 @click="previewImage = '{{ asset('storage/' . $studentPassport->passport_front_path) }}'">
                        @else
                            <a href="{{ asset('storage/' . $studentPassport->passport_front_path) }}" target="_blank"
                               class="inline-flex items-center gap-1 text-sm text-indigo-600 hover:text-indigo-800">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
                                </svg>
                                PDF faylni ko'rish
                            </a>
                        @endif
                    </div>
                @endif
                @if($studentPassport->passport_back_path)
                    <div class="border border-gray-200 rounded-lg p-3 bg-gray-50">
                        <p class="text-xs font-medium text-gray-500 mb-2">Pasport orqa tarafi</p>
                        @if(Str::endsWith($studentPassport->passport_back_path, ['.jpg', '.jpeg', '.png']))
                            <img src="{{ asset('storage/' . $studentPassport->passport_back_path) }}" alt="Pasport orqa"
                                 class="w-full h-32 object-cover rounded cursor-pointer hover:opacity-80 transition"
                                 @click="previewImage = '{{ asset('storage/' . $studentPassport->passport_back_path) }}'">
                        @else
                            <a href="{{ asset('storage/' . $studentPassport->passport_back_path) }}" target="_blank"
                               class="inline-flex items-center gap-1 text-sm text-indigo-600 hover:text-indigo-800">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
                                </svg>
                                PDF faylni ko'rish
                            </a>
                        @endif
                    </div>
                @endif
                @if($studentPassport->foreign_passport_path)
                    <div class="border border-gray-200 rounded-lg p-3 bg-gray-50">
                        <p class="text-xs font-medium text-gray-500 mb-2">Xorijga chiqish pasporti</p>
                        @if(Str::endsWith($studentPassport->foreign_passport_path, ['.jpg', '.jpeg', '.png']))
                            <img src="{{ asset('storage/' . $studentPassport->foreign_passport_path) }}" alt="Xorijga chiqish pasporti"
                                 class="w-full h-32 object-cover rounded cursor-pointer hover:opacity-80 transition"
                                 @click="previewImage = '{{ asset('storage/' . $studentPassport->foreign_passport_path) }}'">
                        @else
                            <a href="{{ asset('storage/' . $studentPassport->foreign_passport_path) }}" target="_blank"
                               class="inline-flex items-center gap-1 text-sm text-indigo-600 hover:text-indigo-800">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
                                </svg>
                                PDF faylni ko'rish
                            </a>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- Form (shown when toggled) --}}
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
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Pasport orqa tarafi <span class="text-gray-400">(max 1MB)</span> <span class="text-red-500">*</span></label>
                    <input type="file" name="passport_back" accept=".jpg,.jpeg,.pdf" {{ $studentPassport ? '' : 'required' }}
                           onchange="checkFileSize(this)"
                           class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                    <p class="text-xs text-red-600 mt-1 hidden" data-file-error></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Xorijga chiqish pasporti <span class="text-gray-400">(max 1MB)</span> <span class="text-red-500">*</span></label>
                    <input type="file" name="foreign_passport" accept=".jpg,.jpeg,.pdf" {{ $studentPassport ? '' : 'required' }}
                           onchange="checkFileSize(this)"
                           class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                    <p class="text-xs text-red-600 mt-1 hidden" data-file-error></p>
                </div>
            </div>
            <div class="text-right mt-4">
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

    {{-- Button at the bottom --}}
    <div class="mt-4" x-show="!showPassportForm">
        <button @click="showPassportForm = true" type="button"
                class="w-full inline-flex items-center justify-center gap-1.5 px-4 py-2.5 text-sm font-medium rounded-lg transition {{ $studentPassport ? 'bg-yellow-500 text-white hover:bg-yellow-600' : 'bg-indigo-600 text-white hover:bg-indigo-700' }}">
            {{ $studentPassport ? 'Tahrirlash' : "To'ldirish" }}
        </button>
    </div>
    <div class="mt-4" x-show="showPassportForm">
        <button @click="showPassportForm = false" type="button"
                class="w-full inline-flex items-center justify-center gap-1.5 px-4 py-2.5 text-sm font-medium rounded-lg transition bg-gray-200 text-gray-700 hover:bg-gray-300">
            Yopish
        </button>
    </div>

    {{-- Image preview modal --}}
    <div x-show="previewImage" x-transition.opacity
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4"
         @click.self="previewImage = null" @keydown.escape.window="previewImage = null">
        <div class="relative max-w-3xl w-full">
            <button @click="previewImage = null" class="absolute -top-3 -right-3 bg-white rounded-full p-1.5 shadow-lg hover:bg-gray-100 z-10">
                <svg class="w-5 h-5 text-gray-700" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
            <img :src="previewImage" class="w-full rounded-lg shadow-2xl" alt="Preview">
        </div>
    </div>
</div>
