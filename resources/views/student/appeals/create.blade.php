<x-student-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-sm text-gray-800 leading-tight">
            Apellyatsiya topshirish
        </h2>
    </x-slot>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 px-3" x-data="appealForm()">
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">

            {{-- Info banner --}}
            <div class="px-4 py-3 bg-purple-50 border-b border-purple-100">
                <div class="flex items-start gap-2">
                    <svg class="w-5 h-5 text-purple-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                    </svg>
                    <div>
                        <p class="text-xs text-purple-700 font-medium">Imtihon natijasiga e'tiroz bildirish uchun apellyatsiya topshiring.</p>
                        <p class="text-[11px] text-purple-500 mt-0.5">Baho qo'yilganidan 24 soat ichida apellyatsiya topshirish mumkin. Muddati o'tgan baholarga apellyatsiya berish imkoni yo'q.</p>
                    </div>
                </div>
            </div>

            @error('student_grade_id')
                <div class="mx-4 mt-3 px-3 py-2.5 rounded-lg bg-red-50 border border-red-200 text-red-700 text-xs font-medium flex items-start gap-2">
                    <svg class="w-4 h-4 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/>
                    </svg>
                    {{ $message }}
                </div>
            @enderror

            <form method="POST" action="{{ route('student.appeals.store') }}" enctype="multipart/form-data" class="p-4 space-y-5">
                @csrf
                <input type="hidden" name="student_grade_id" :value="selectedGradeId">

                {{-- Baho turi filter --}}
                @if(!$grades->isEmpty())
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Baho turi</label>
                        <select x-model="typeFilter"
                                class="w-full rounded-xl border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Barcha turlar</option>
                            @foreach($grades->pluck('training_type_name')->filter()->unique()->values() as $typeName)
                                <option value="{{ $typeName }}">{{ $typeName }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                {{-- Baholar ro'yxati --}}
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Bahoni tanlang <span class="text-red-500">*</span>
                    </label>

                    @if($grades->isEmpty())
                        <div class="px-4 py-3 rounded-lg bg-yellow-50 border border-yellow-200 text-yellow-700 text-xs">
                            Hozircha baholar topilmadi.
                        </div>
                    @else
                        <div class="space-y-2 max-h-[400px] overflow-y-auto rounded-xl border border-gray-200 p-2">
                            @foreach($grades as $g)
                                <div x-show="!typeFilter || typeFilter === '{{ $g['training_type_name'] }}'"
                                     @click="selectGrade({{ $g['id'] }}, {{ $g['can_appeal'] ? 'true' : 'false' }})"
                                     class="relative rounded-xl border p-3 cursor-pointer transition"
                                     :class="selectedGradeId == {{ $g['id'] }}
                                         ? 'border-indigo-400 bg-indigo-50 ring-2 ring-indigo-300'
                                         : '{{ $g['can_appeal'] ? 'border-gray-200 bg-white hover:border-indigo-200 hover:bg-indigo-50/30' : 'border-gray-100 bg-gray-50 opacity-60' }}'">

                                    {{-- Ustki qism: fan nomi + baho --}}
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="min-w-0 flex-1">
                                            <p class="text-sm font-semibold text-gray-800 truncate">{{ $g['subject_name'] }}</p>
                                            <p class="text-sm font-bold text-green-600 mt-0.5">{{ $g['training_type_name'] }}</p>
                                        </div>
                                        <div class="flex-shrink-0 text-right">
                                            <span class="inline-flex items-center justify-center w-10 h-10 rounded-full text-sm font-bold
                                                {{ $g['grade'] >= 86 ? 'bg-green-100 text-green-700' : ($g['grade'] >= 71 ? 'bg-blue-100 text-blue-700' : ($g['grade'] >= 56 ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700')) }}">
                                                {{ $g['grade'] }}
                                            </span>
                                        </div>
                                    </div>

                                    {{-- Pastki qism: o'qituvchi, sanalar --}}
                                    <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-gray-500">
                                        @if($g['employee_name'])
                                            <span class="flex items-center gap-1">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0"/>
                                                </svg>
                                                {{ $g['employee_name'] }}
                                            </span>
                                        @endif
                                        @if($g['lesson_date'])
                                            <span class="flex items-center gap-1">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75"/>
                                                </svg>
                                                {{ $g['lesson_date'] }}
                                            </span>
                                        @endif
                                        @if($g['graded_at'])
                                            <span class="flex items-center gap-1">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                </svg>
                                                Qo'yilgan: {{ $g['graded_at'] }}
                                            </span>
                                        @endif
                                    </div>

                                    {{-- Status badge --}}
                                    @if($g['can_appeal'])
                                        <div class="mt-2">
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-green-100 text-green-700">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                </svg>
                                                Apellyatsiya mumkin
                                            </span>
                                        </div>
                                    @else
                                        <div class="mt-2">
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-gray-200 text-gray-500">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                </svg>
                                                Muddat tugagan (24 soat)
                                            </span>
                                        </div>
                                    @endif

                                    {{-- Select indicator --}}
                                    <div x-show="selectedGradeId == {{ $g['id'] }}" class="absolute top-2 right-2">
                                        <svg class="w-5 h-5 text-indigo-600" fill="currentColor" viewBox="0 0 24 24">
                                            <path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12zm13.36-1.814a.75.75 0 10-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 00-1.06 1.06l2.25 2.25a.75.75 0 001.14-.094l3.75-5.25z" clip-rule="evenodd"/>
                                        </svg>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Xato modal --}}
                <div x-show="showErrorModal" x-transition.opacity style="position:fixed;inset:0;z-index:99999;display:none;" class="flex items-center justify-center">
                    <div @click="showErrorModal = false" style="position:absolute;inset:0;background:rgba(0,0,0,0.4);"></div>
                    <div x-show="showErrorModal" x-transition.scale.90 class="relative bg-white rounded-2xl shadow-2xl mx-4 w-full max-w-sm overflow-hidden" @click.away="showErrorModal = false">
                        <div class="flex flex-col items-center px-5 pt-6 pb-4">
                            <div class="w-14 h-14 rounded-full bg-red-100 flex items-center justify-center mb-3">
                                <svg class="w-7 h-7 text-red-500" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <h3 class="text-base font-bold text-gray-800 mb-1">Muddat tugagan</h3>
                            <p class="text-sm text-gray-500 text-center">Baho qo'yilganidan 24 soat o'tgan. Faqat 24 soat ichida apellyatsiya topshirish mumkin.</p>
                        </div>
                        <div class="px-5 pb-5 pt-1">
                            <button @click="showErrorModal = false"
                                    class="w-full py-2.5 rounded-xl bg-red-500 hover:bg-red-600 text-white text-sm font-semibold transition">
                                Tushundim
                            </button>
                        </div>
                    </div>
                </div>

                {{-- 2. Sabab --}}
                <div x-show="selectedGradeId" x-transition>
                    <label for="reason" class="block text-sm font-semibold text-gray-700 mb-1.5">
                        Apellyatsiya sababi <span class="text-red-500">*</span>
                    </label>
                    <textarea name="reason" id="reason" rows="5" required minlength="20" maxlength="2000"
                              placeholder="Nima uchun bu bahoga e'tiroz bildiryapsiz? Batafsil yozing..."
                              x-on:input="charCount = $el.value.length"
                              class="w-full rounded-xl border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 @error('reason') border-red-300 @enderror">{{ old('reason') }}</textarea>
                    <div class="flex justify-between mt-1">
                        @error('reason')
                            <p class="text-xs text-red-600">{{ $message }}</p>
                        @else
                            <p class="text-[11px] text-gray-400">Kamida 20 ta belgi</p>
                        @enderror
                        <p class="text-[11px] text-gray-400"><span x-text="charCount"></span>/2000</p>
                    </div>
                </div>

                {{-- 3. Fayl yuklash --}}
                <div x-show="selectedGradeId" x-transition>
                    <label for="file" class="block text-sm font-semibold text-gray-700 mb-1.5">
                        Qo'shimcha hujjat <span class="text-gray-400 font-normal">(ixtiyoriy)</span>
                    </label>
                    <div class="relative">
                        <input type="file" name="file" id="file" accept=".pdf,.jpg,.jpeg,.png"
                               class="w-full text-sm text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-medium file:bg-gray-800 file:text-white hover:file:bg-gray-700 file:cursor-pointer file:transition @error('file') border-red-300 @enderror">
                    </div>
                    @error('file')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @else
                        <p class="mt-1 text-[11px] text-gray-400">PDF, JPG, PNG. Maksimum 5MB.</p>
                    @enderror
                </div>

                {{-- Submit --}}
                <div class="pt-2" x-show="selectedGradeId" x-transition>
                    <button type="submit"
                            class="inline-flex items-center justify-center gap-1.5 px-5 py-2 bg-indigo-600 hover:bg-indigo-700 disabled:bg-gray-300 disabled:cursor-not-allowed text-white font-medium rounded-lg transition shadow-sm text-sm w-full sm:w-auto">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" />
                        </svg>
                        Apellyatsiya topshirish
                    </button>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
    <script>
        function appealForm() {
            return {
                selectedGradeId: '{{ old("student_grade_id", "") }}',
                typeFilter: '',
                charCount: {{ old('reason') ? strlen(old('reason')) : 0 }},
                showErrorModal: false,

                selectGrade(id, canAppeal) {
                    if (!canAppeal) {
                        this.showErrorModal = true;
                        this.selectedGradeId = '';
                        return;
                    }
                    this.selectedGradeId = this.selectedGradeId == id ? '' : id;
                }
            }
        }
    </script>
    @endpush
</x-student-app-layout>
