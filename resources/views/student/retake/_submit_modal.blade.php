{{-- Ariza yuborish modal --}}
<div x-show="showModal"
     x-cloak
     x-data="{ submitting: false }"
     class="fixed inset-0 z-50 overflow-y-auto"
     @keydown.escape.window="if (!submitting) closeModal()">
    {{-- Backdrop --}}
    <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity"
         @click="if (!submitting) closeModal()"></div>

    {{-- Modal har doim markazda (mobil va desktopda) --}}
    <div class="relative min-h-full flex items-center justify-center p-3 sm:p-4">
        <div class="relative bg-white rounded-2xl shadow-xl w-full sm:max-w-md text-left z-10 my-4"
             x-transition:enter="ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100">

            <div class="flex items-start justify-between p-4 sm:p-5 border-b border-gray-100">
                <h3 class="text-base font-bold text-gray-900">{{ __("Arizani yuborish") }}</h3>
                <button type="button" @click="closeModal()" :disabled="submitting"
                        :class="submitting ? 'opacity-30 cursor-not-allowed' : 'hover:text-gray-600'"
                        class="text-gray-400 -m-1 p-1">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <form action="{{ route('student.retake.store') }}"
                  method="POST"
                  enctype="multipart/form-data"
                  @submit="submitting = true">
                @csrf

                {{-- Body --}}
                <div class="px-4 sm:px-5 py-4 space-y-4">
                    {{-- Tanlangan fanlar --}}
                    <div>
                        <p class="text-xs font-medium text-gray-700 mb-2">{{ __("Tanlangan fanlar") }}</p>
                        <div class="bg-gray-50 rounded-lg p-3 space-y-1.5">
                            <template x-for="(s, i) in selected" :key="i">
                                <div class="flex items-center justify-between text-xs">
                                    <span class="text-gray-700">
                                        <span x-text="s.semester_name"></span> —
                                        <span class="font-medium" x-text="s.subject_name"></span>
                                    </span>
                                    <span class="text-gray-500" x-text="s.credit.toFixed(1) + ' kr'"></span>
                                    <input type="hidden" :name="'subjects[' + i + '][subject_id]'" :value="s.subject_id">
                                    <input type="hidden" :name="'subjects[' + i + '][semester_id]'" :value="s.semester_id">
                                </div>
                            </template>
                        </div>
                        <div class="mt-2 flex justify-between items-center text-xs">
                            <span class="text-gray-500">
                                {{ __("Jami") }}:
                                <span class="font-semibold text-gray-800" x-text="totalCredits.toFixed(1)"></span>
                                {{ __("kredit") }}
                            </span>
                            <span class="font-semibold text-blue-700" x-text="formatMoney(totalAmount) + ' UZS'"></span>
                        </div>
                    </div>

                    {{-- Tushuntirish xati — file input HECH QACHON disabled BO'LMASLIGI KERAK, aks holda yuborilmaydi --}}
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">
                            {{ __("Dekanat tasdig'idagi tushuntirish xati") }} <span class="text-red-500">*</span>
                        </label>
                        <p class="text-[11px] text-gray-600 mb-2">
                            {{ __("Dekanat tomonidan tasdiqlangan tushuntirish xati yuklansin.") }}
                        </p>
                        <input type="file"
                               name="receipt"
                               accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
                               required
                               class="block w-full text-xs text-gray-700 file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                        <p class="text-[11px] text-gray-500 mt-1">
                            PDF, DOC, JPG, PNG · max {{ $receiptMaxMb }} MB
                        </p>
                        <p class="text-[11px] text-amber-700 mt-1">
                            <span class="font-medium">{{ __("Izoh") }}:</span>
                            {{ __("Uning haqiqiyligi dekan tomonidan tekshiriladi.") }}
                        </p>
                    </div>

                    {{-- Izoh --}}
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">
                            {{ __("Izoh (ixtiyoriy)") }}
                        </label>
                        <textarea name="comment"
                                  rows="3"
                                  maxlength="{{ \App\Services\Retake\RetakeApplicationService::MAX_COMMENT_LENGTH }}"
                                  class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                  placeholder="{{ __('Qo\'shimcha ma\'lumot...') }}"></textarea>
                    </div>
                </div>

                {{-- Tugmalar --}}
                <div class="flex gap-2 px-4 sm:px-5 py-3 border-t border-gray-100 bg-white rounded-b-2xl">
                    <button type="button"
                            @click="closeModal()"
                            :disabled="submitting"
                            :class="submitting ? 'opacity-50 cursor-not-allowed' : 'hover:bg-gray-200'"
                            class="flex-1 px-4 py-2.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg">
                        {{ __("Bekor qilish") }}
                    </button>
                    <button type="submit"
                            :disabled="submitting"
                            :class="submitting ? 'bg-blue-400 cursor-wait' : 'bg-blue-600 hover:bg-blue-700 active:bg-blue-800'"
                            class="flex-1 px-4 py-2.5 text-sm font-medium text-white rounded-lg inline-flex items-center justify-center gap-2">
                        <svg x-show="submitting" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span x-text="submitting ? '{{ __('Yuborilmoqda...') }}' : '{{ __('Yuborish') }}'"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Loading overlay — submit paytida butun ekran bloklanadi (lekin form input'larini disabled qilmaymiz) --}}
    <div x-show="submitting" x-cloak
         class="fixed inset-0 z-[60] bg-white/40 backdrop-blur-sm flex items-center justify-center pointer-events-auto">
        <div class="bg-white rounded-xl shadow-2xl px-6 py-4 flex items-center gap-3">
            <svg class="w-6 h-6 animate-spin text-blue-600" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <div class="text-sm">
                <p class="font-medium text-gray-900">{{ __("Ariza yuborilmoqda") }}</p>
                <p class="text-xs text-gray-500">{{ __("Iltimos kuting...") }}</p>
            </div>
        </div>
    </div>
</div>

