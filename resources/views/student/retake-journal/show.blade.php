<x-student-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-2 flex-wrap">
            <a href="{{ route('student.retake-journal.index') }}" class="text-sm text-red-600 hover:underline">
                ← {{ __("Jurnal ro'yxati") }}
            </a>
            <span class="text-gray-300">/</span>
            <h2 class="font-semibold text-sm text-gray-800 leading-tight truncate">
                {{ $group->subject_name }}
            </h2>
        </div>
    </x-slot>

    @php
        $jn = $application?->joriy_score;
        $jnInt = $jn !== null ? (int) round((float) $jn) : null;
    @endphp

    <div class="pb-6">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">

            @if(session('success'))
                <div class="bg-green-50 border border-green-200 rounded-lg p-3 text-sm text-green-800 flex items-center gap-2">
                    <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                    {{ session('success') }}
                </div>
            @endif

            @if($errors->any())
                <div class="bg-red-50 border border-red-200 rounded-lg p-3 text-sm text-red-800">
                    <ul class="list-disc list-inside">@foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach</ul>
                </div>
            @endif

            {{-- Yuqori karta: fan + o'qituvchi + sanalar (LMS qizil dizayn) --}}
            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden border border-gray-200">
                <div class="bg-gradient-to-br from-red-600 to-red-700 text-white p-4 sm:p-5">
                    <div class="flex items-start justify-between gap-3 flex-wrap">
                        <div class="min-w-0 flex-1">
                            <p class="text-[10px] uppercase tracking-wider text-red-100 font-semibold">{{ __("Qayta o'qish jurnali") }}</p>
                            <h3 class="text-lg sm:text-xl font-bold mt-1">{{ $group->subject_name }}</h3>
                            <p class="text-xs text-red-100 mt-0.5">{{ $group->name }}</p>
                        </div>
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-semibold bg-white text-red-700 flex-shrink-0">
                            {{-- Muddat hali amal qilsa "Davom etmoqda", aks holda guruh status nomi --}}
                            @if($isEditable)
                                {{ __("Davom etmoqda") }}
                            @else
                                {{ $group->statusLabel() ?? $group->status }}
                            @endif
                        </span>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 divide-y sm:divide-y-0 sm:divide-x divide-gray-100">
                    <div class="p-4">
                        <p class="text-[10px] uppercase text-gray-500 font-semibold">{{ __("O'qituvchi") }}</p>
                        <p class="text-sm font-medium text-gray-900 mt-1">{{ $group->teacher_name ?? '—' }}</p>
                        @if(!empty($group->teacher_phones))
                            <div class="mt-2 flex flex-wrap items-center gap-1">
                                @foreach($group->teacher_phones as $phone)
                                    <a href="tel:{{ preg_replace('/[^+\d]/', '', $phone) }}"
                                       class="inline-flex items-center gap-1 px-2 py-1 rounded-md bg-red-50 text-red-700 hover:bg-red-100 text-xs font-medium transition">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h2.5a1 1 0 011 .76l1 4a1 1 0 01-.5 1.12L7.6 9.6a11.04 11.04 0 005.6 5.6l.7-1.4a1 1 0 011.12-.5l4 1a1 1 0 01.76 1V18a2 2 0 01-2 2A15 15 0 013 5z"/></svg>
                                        {{ $phone }}
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div class="p-4">
                        <p class="text-[10px] uppercase text-gray-500 font-semibold">{{ __("Davr") }}</p>
                        <p class="text-sm font-medium text-gray-900 mt-1">
                            {{ $group->start_date->format('d.m.Y') }}
                            <span class="text-gray-400">→</span>
                            {{ $group->end_date->format('d.m.Y') }}
                        </p>
                        <p class="text-[11px] text-gray-500 mt-1">
                            {{ __("Semestr") }}: {{ $group->semester_name }}
                        </p>
                    </div>

                    <div class="p-4">
                        <p class="text-[10px] uppercase text-gray-500 font-semibold">{{ __("Baholash turi") }}</p>
                        <p class="text-sm font-medium text-gray-900 mt-1">
                            @switch($group->assessment_type)
                                @case('oske') OSKE @break
                                @case('test') TEST @break
                                @case('oske_test') OSKE + TEST @break
                                @case('sinov_fan') {{ __("Sinov fan") }} @break
                                @default —
                            @endswitch
                        </p>
                        @if($group->oske_date || $group->test_date)
                            <p class="text-[11px] text-gray-500 mt-1">
                                @if($group->oske_date) OSKE: {{ $group->oske_date->format('d.m.Y') }} @endif
                                @if($group->oske_date && $group->test_date) · @endif
                                @if($group->test_date) TEST: {{ $group->test_date->format('d.m.Y') }} @endif
                            </p>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Joriy nazorat (yagona JN bahosi) --}}
            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden border border-gray-200">
                <div class="px-4 sm:px-5 py-3 border-b border-gray-200 bg-gray-50 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-800">{{ __("Joriy nazorat (JN)") }}</h3>
                    <span class="text-[11px] text-gray-500">{{ __("Yagona JN bahongiz") }}</span>
                </div>
                <div class="p-6 text-center">
                    @if($jnInt !== null)
                        @php
                            $jnClass = $jnInt >= 75
                                ? 'text-green-600 bg-green-50 border-green-300'
                                : ($jnInt >= 60 ? 'text-amber-600 bg-amber-50 border-amber-300' : 'text-red-600 bg-red-50 border-red-300');
                        @endphp
                        <div class="inline-flex flex-col items-center justify-center w-32 h-32 rounded-full border-4 {{ $jnClass }}">
                            <p class="text-5xl font-bold leading-none">{{ $jnInt }}</p>
                            <p class="text-[10px] uppercase tracking-wider mt-1 opacity-70">JN</p>
                        </div>
                        @if($application?->joriy_graded_at)
                            <p class="text-[11px] text-gray-500 mt-3">
                                {{ $application->joriy_graded_by_name }} ·
                                {{ $application->joriy_graded_at->format('d.m.Y H:i') }}
                            </p>
                        @endif
                    @else
                        <div class="inline-flex flex-col items-center justify-center w-32 h-32 rounded-full border-4 border-gray-200 text-gray-300">
                            <p class="text-5xl font-bold leading-none">—</p>
                            <p class="text-[10px] uppercase tracking-wider mt-1">JN</p>
                        </div>
                        <p class="text-[11px] text-gray-500 mt-3">{{ __("O'qituvchi hali baho qo'ymagan") }}</p>
                    @endif
                </div>
            </div>

            {{-- Mustaqil ta'lim --}}
            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden border border-gray-200">
                <div class="px-4 sm:px-5 py-3 border-b border-gray-200 bg-gray-50 flex items-center justify-between flex-wrap gap-2">
                    <h3 class="text-sm font-semibold text-gray-800">{{ __("Mustaqil ta'lim") }}</h3>
                    @if($mustaqil)
                        @if($mustaqil->grade !== null)
                            @php
                                $mv = (float) $mustaqil->grade;
                                $mc = $mv >= 75 ? 'bg-green-100 text-green-800' : ($mv >= 60 ? 'bg-amber-100 text-amber-800' : 'bg-red-100 text-red-800');
                            @endphp
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $mc }}">
                                <svg class="mr-1.5 h-2 w-2 flex-shrink-0" fill="currentColor" viewBox="0 0 8 8"><circle cx="4" cy="4" r="3"/></svg>
                                {{ __("Baho") }}: {{ rtrim(rtrim(number_format($mv, 2, '.', ''), '0'), '.') }}
                            </span>
                        @else
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                <svg class="mr-1.5 h-2 w-2 text-yellow-400" fill="currentColor" viewBox="0 0 8 8"><circle cx="4" cy="4" r="3"/></svg>
                                {{ __("Tekshirilmoqda") }}
                            </span>
                        @endif
                    @endif
                </div>

                <div class="p-4 sm:p-5 space-y-3">
                    @if($mustaqil && $mustaqil->file_path)
                        <div class="bg-gray-50 border border-gray-200 rounded-lg p-3 flex items-center justify-between flex-wrap gap-2">
                            <div class="flex items-center gap-2 min-w-0">
                                <div class="w-9 h-9 rounded-lg bg-red-100 flex items-center justify-center flex-shrink-0">
                                    <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                                    </svg>
                                </div>
                                <div class="min-w-0">
                                    <a href="{{ route('student.retake-journal.mustaqil-download', $group->id) }}"
                                       class="text-sm font-medium text-red-700 hover:underline truncate block">
                                        {{ $mustaqil->original_filename ?? __("Fayl") }}
                                    </a>
                                    <p class="text-[11px] text-gray-500 mt-0.5">
                                        {{ __("Yuklangan") }}: {{ $mustaqil->submitted_at?->format('d.m.Y H:i') }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        @if($mustaqil->student_comment)
                            <div class="text-xs bg-blue-50/50 border border-blue-100 rounded-lg p-2">
                                <span class="text-gray-500">{{ __("Sizning izohingiz") }}:</span>
                                <span class="text-gray-700">{{ $mustaqil->student_comment }}</span>
                            </div>
                        @endif

                        @if($mustaqil->grade !== null)
                            <div class="bg-red-50 border border-red-200 rounded-lg p-3">
                                <p class="text-xs text-red-900">
                                    <span class="font-semibold">{{ __("O'qituvchi bahosi") }}:</span>
                                    {{ rtrim(rtrim(number_format($mustaqil->grade, 2, '.', ''), '0'), '.') }}
                                </p>
                                @if($mustaqil->teacher_comment)
                                    <p class="text-xs text-red-800 mt-1">
                                        <span class="font-medium">{{ __("Izoh") }}:</span>
                                        {{ $mustaqil->teacher_comment }}
                                    </p>
                                @endif
                                @if($mustaqil->graded_by_name)
                                    <p class="text-[11px] text-red-700 mt-1">
                                        {{ $mustaqil->graded_by_name }} · {{ $mustaqil->graded_at?->format('d.m.Y H:i') }}
                                    </p>
                                @endif
                            </div>
                        @endif
                    @endif

                    {{-- Yuklash formasi --}}
                    @php
                        $maxAttempts = \App\Models\RetakeMustaqilSubmission::MAX_ATTEMPTS;
                        $passGrade = \App\Models\RetakeMustaqilSubmission::PASS_GRADE;
                        $attemptCount = (int) ($mustaqil->attempt_count ?? 0);
                        $mustaqilPassed = $mustaqil && $mustaqil->grade !== null && (float) $mustaqil->grade >= $passGrade;
                        $attemptsLeft = max(0, $maxAttempts - $attemptCount);
                        $mustaqilExhausted = $mustaqil && $attemptCount >= $maxAttempts;
                        $canUploadMustaqil = $isEditable && !$mustaqilPassed && !$mustaqilExhausted;
                    @endphp

                    @if($mustaqilPassed)
                        <div class="bg-green-50 border border-green-200 rounded-lg p-3 text-xs text-green-800 flex items-start gap-2">
                            <svg class="w-4 h-4 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                            <span>{{ __("Siz mustaqil ta'limdan o'tdingiz (60+ baho) — qayta yuklash shart emas.") }}</span>
                        </div>
                    @elseif(!$isEditable)
                        <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 text-xs text-amber-900 flex items-start gap-2">
                            <svg class="w-4 h-4 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                            <span>{{ __("Guruh muddati tugagan, fayl yuklash mumkin emas") }}</span>
                        </div>
                    @elseif($mustaqilExhausted)
                        <div class="bg-red-50 border border-red-200 rounded-lg p-3 text-xs text-red-800 flex items-start gap-2">
                            <svg class="w-4 h-4 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                            <span>{{ __("Mustaqil ta'lim uchun :n marta urinish imkoni tugadi.", ['n' => $maxAttempts]) }}</span>
                        </div>
                    @else
                        <form method="POST"
                              action="{{ route('student.retake-journal.mustaqil-upload', $group->id) }}"
                              enctype="multipart/form-data"
                              class="space-y-3 pt-3 border-t border-gray-100">
                            @csrf
                            <div class="flex items-center justify-between flex-wrap gap-1">
                                <label class="block text-xs font-medium text-gray-700">
                                    @if($mustaqil)
                                        {{ __("Faylni qayta yuklash") }} (max 5 MB)
                                    @else
                                        {{ __("Fayl yuklash") }} (max 5 MB) <span class="text-red-500">*</span>
                                    @endif
                                </label>
                                <span class="text-[11px] font-medium px-2 py-0.5 rounded-full bg-gray-100 text-gray-600">
                                    {{ __("Urinish") }}: {{ $attemptCount }}/{{ $maxAttempts }}
                                </span>
                            </div>
                            <input type="file"
                                   name="file"
                                   accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.zip,.rar"
                                   required
                                   class="block w-full text-xs text-gray-700 file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-medium file:bg-red-50 file:text-red-700 hover:file:bg-red-100">
                            <p class="text-[11px] text-gray-500">PDF, DOC, JPG, PNG, ZIP, RAR · max 5 MB</p>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">
                                    {{ __("Izoh (ixtiyoriy)") }}
                                </label>
                                <textarea name="comment"
                                          rows="2"
                                          maxlength="1000"
                                          class="w-full px-3 py-2 text-xs border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500"></textarea>
                            </div>
                            <button type="submit"
                                    class="inline-flex items-center gap-1 px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 transition shadow-sm">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                                @if($mustaqil) {{ __("Qayta yuklash") }} @else {{ __("Yuklash") }} @endif
                            </button>

                            <p class="text-[11px] text-amber-700 bg-amber-50 border border-amber-200 rounded p-2">
                                ⚠️ {{ __("Mustaqil ta'limni eng ko'pi :n marta yuklash mumkin. 60+ baho olsangiz qayta yuklash yopiladi.", ['n' => $maxAttempts]) }}
                                @if($mustaqil)
                                    {{ __("Qayta yuklasangiz, mavjud baho bekor qilinadi va o'qituvchi qaytadan tekshiradi.") }}
                                @endif
                                {{ __("Sizda yana :n marta imkon bor.", ['n' => $attemptsLeft]) }}
                            </p>
                        </form>
                    @endif
                </div>
            </div>

        </div>
    </div>
</x-student-app-layout>
