<x-student-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-sm text-gray-800 leading-tight">
            Apellyatsiya topshirish
        </h2>
    </x-slot>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 px-3">
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">

            {{-- Info banner --}}
            <div class="px-4 py-3 bg-purple-50 border-b border-purple-100">
                <div class="flex items-start gap-2">
                    <svg class="w-5 h-5 text-purple-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                    </svg>
                    <div>
                        <p class="text-xs text-purple-700 font-medium">Imtihon natijasiga e'tiroz bildirish uchun apellyatsiya topshiring.</p>
                        <p class="text-[11px] text-purple-500 mt-0.5">Bahoni tanlang, sababni batafsil yozing. Ariza ko'rib chiqiladi.</p>
                    </div>
                </div>
            </div>

            <form method="POST" action="{{ route('student.appeals.store') }}" enctype="multipart/form-data" class="p-4 space-y-5">
                @csrf

                {{-- 1. Baho tanlash --}}
                <div>
                    <label for="student_grade_id" class="block text-sm font-semibold text-gray-700 mb-1.5">
                        Bahoni tanlang <span class="text-red-500">*</span>
                    </label>
                    @if($grades->isEmpty())
                        <div class="px-4 py-3 rounded-lg bg-yellow-50 border border-yellow-200 text-yellow-700 text-xs">
                            Hozircha apellyatsiya topshirish mumkin bo'lgan baholar topilmadi.
                        </div>
                    @else
                        <select name="student_grade_id" id="student_grade_id" required
                                class="w-full rounded-xl border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 @error('student_grade_id') border-red-300 @enderror">
                            <option value="">-- Bahoni tanlang --</option>
                            @foreach($grades as $g)
                                <option value="{{ $g['id'] }}" {{ old('student_grade_id') == $g['id'] ? 'selected' : '' }}>
                                    {{ $g['label'] }}{{ $g['lesson_date'] ? ' - ' . $g['lesson_date'] : '' }}
                                </option>
                            @endforeach
                        </select>
                    @endif
                    @error('student_grade_id')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Tanlangan baho ma'lumotlari --}}
                <div id="gradeInfo" class="hidden px-4 py-3 rounded-xl bg-gray-50 border border-gray-200">
                    <div class="grid grid-cols-2 gap-2 text-xs">
                        <div>
                            <span class="text-gray-500">Fan:</span>
                            <span id="infoSubject" class="font-medium text-gray-800"></span>
                        </div>
                        <div>
                            <span class="text-gray-500">Turi:</span>
                            <span id="infoType" class="font-medium text-gray-800"></span>
                        </div>
                        <div>
                            <span class="text-gray-500">Baho:</span>
                            <span id="infoGrade" class="font-bold text-gray-800"></span>
                        </div>
                        <div>
                            <span class="text-gray-500">O'qituvchi:</span>
                            <span id="infoTeacher" class="font-medium text-gray-800"></span>
                        </div>
                    </div>
                </div>

                {{-- 2. Sabab --}}
                <div>
                    <label for="reason" class="block text-sm font-semibold text-gray-700 mb-1.5">
                        Apellyatsiya sababi <span class="text-red-500">*</span>
                    </label>
                    <textarea name="reason" id="reason" rows="5" required minlength="20" maxlength="2000"
                              placeholder="Nima uchun bu bahoga e'tiroz bildiryapsiz? Batafsil yozing..."
                              class="w-full rounded-xl border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 @error('reason') border-red-300 @enderror">{{ old('reason') }}</textarea>
                    <div class="flex justify-between mt-1">
                        @error('reason')
                            <p class="text-xs text-red-600">{{ $message }}</p>
                        @else
                            <p class="text-[11px] text-gray-400">Kamida 20 ta belgi</p>
                        @enderror
                        <p class="text-[11px] text-gray-400"><span id="charCount">0</span>/2000</p>
                    </div>
                </div>

                {{-- 3. Fayl yuklash --}}
                <div>
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
                <div class="pt-2">
                    <button type="submit" {{ $grades->isEmpty() ? 'disabled' : '' }}
                            class="inline-flex items-center justify-center gap-1.5 px-5 py-2 bg-indigo-600 hover:bg-indigo-700 disabled:bg-gray-300 disabled:cursor-not-allowed text-white font-medium rounded-lg transition shadow-sm text-sm">
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
        document.addEventListener('DOMContentLoaded', function() {
            const grades = @json($grades);
            const select = document.getElementById('student_grade_id');
            const infoBox = document.getElementById('gradeInfo');
            const textarea = document.getElementById('reason');
            const charCount = document.getElementById('charCount');

            // Baho tanlanganda info ko'rsatish
            select.addEventListener('change', function() {
                const selected = grades.find(g => g.id == this.value);
                if (selected) {
                    document.getElementById('infoSubject').textContent = selected.subject_name;
                    document.getElementById('infoType').textContent = selected.training_type_name;
                    document.getElementById('infoGrade').textContent = selected.grade + ' ball';
                    document.getElementById('infoTeacher').textContent = selected.employee_name || '-';
                    infoBox.classList.remove('hidden');
                } else {
                    infoBox.classList.add('hidden');
                }
            });

            // Belgi soni
            textarea.addEventListener('input', function() {
                charCount.textContent = this.value.length;
            });
            charCount.textContent = textarea.value.length;
        });
    </script>
    @endpush
</x-student-app-layout>
