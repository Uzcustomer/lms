{{-- Ariza yuborish modal --}}
<div x-show="showModal"
     x-cloak
     class="fixed inset-0 z-50"
     @keydown.escape.window="closeModal()">
    {{-- Backdrop --}}
    <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity" @click="closeModal()"></div>

    <div class="fixed inset-0 flex items-end sm:items-center justify-center p-0 sm:p-4 pointer-events-none">
        {{-- Modal content — mobile: bottom-sheet, desktop: centered.
             Mobile pastki nav bar (~64px) ustida turishi uchun maxH cheklangan
             va submit tugmalari sticky pastda qoladi. --}}
        <div class="relative bg-white rounded-t-2xl sm:rounded-2xl shadow-xl w-full sm:max-w-md text-left z-10 pointer-events-auto flex flex-col"
             style="max-height: calc(100vh - 80px);"
             x-transition:enter="ease-out duration-200"
             x-transition:enter-start="opacity-0 translate-y-4"
             x-transition:enter-end="opacity-100 translate-y-0">

            {{-- Sticky header --}}
            <div class="flex items-start justify-between p-4 sm:p-5 border-b border-gray-100 flex-shrink-0">
                <h3 class="text-base font-bold text-gray-900">{{ __("Arizani yuborish") }}</h3>
                <button type="button" @click="closeModal()" class="text-gray-400 hover:text-gray-600 -m-1 p-1">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <form action="{{ route('student.retake.store') }}"
                  method="POST"
                  enctype="multipart/form-data"
                  class="flex flex-col flex-1 min-h-0">
                @csrf

                {{-- Scrollable body --}}
                <div class="flex-1 overflow-y-auto px-4 sm:px-5 py-4 space-y-4">
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

                    {{-- Tushuntirish xati --}}
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

                {{-- Sticky footer (tugmalar) --}}
                <div class="flex gap-2 px-4 sm:px-5 py-3 border-t border-gray-100 bg-white flex-shrink-0 rounded-b-2xl">
                    <button type="button"
                            @click="closeModal()"
                            class="flex-1 px-4 py-2.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">
                        {{ __("Bekor qilish") }}
                    </button>
                    <button type="submit"
                            class="flex-1 px-4 py-2.5 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 active:bg-blue-800">
                        {{ __("Yuborish") }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
