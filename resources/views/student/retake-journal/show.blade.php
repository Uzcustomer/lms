<x-student-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-2 flex-wrap">
            <a href="{{ route('student.retake-journal.index') }}" class="text-sm text-blue-600 hover:underline">
                ← {{ __("Jurnal ro'yxati") }}
            </a>
            <span class="text-gray-300">/</span>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ $group->subject_name }}
            </h2>
        </div>
    </x-slot>

    @php
        $values = $grades->map(fn ($g) => $g->grade)->filter(fn ($v) => $v !== null);
        $avg = $values->isNotEmpty() ? (int) round($values->avg()) : null;
        $gradedCount = $values->count();
        $totalDays = count($dates);
        $passCount = $values->filter(fn ($v) => (float) $v >= 60)->count();
        $failCount = $gradedCount - $passCount;
    @endphp

    <div class="py-3 sm:py-6 px-2 sm:px-6 lg:px-8 max-w-5xl mx-auto">
        @if(session('success'))
            <div class="bg-green-50 border border-green-200 rounded-lg p-3 mb-3 text-sm text-green-800">
                {{ session('success') }}
            </div>
        @endif
        @if($errors->any())
            <div class="bg-red-50 border border-red-200 rounded-lg p-3 mb-3 text-sm text-red-800">
                <ul class="list-disc list-inside">@foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach</ul>
            </div>
        @endif

        {{-- Yuqori karta: fan + o'qituvchi + sanalar (gradient) --}}
        <div class="bg-gradient-to-br from-blue-600 to-indigo-700 text-white rounded-2xl shadow-lg p-4 sm:p-5 mb-3">
            <p class="text-[10px] uppercase tracking-wider text-blue-100 font-semibold">{{ __("Qayta o'qish") }}</p>
            <h3 class="text-lg sm:text-xl font-bold mt-1 truncate">{{ $group->subject_name }}</h3>
            <p class="text-xs text-blue-100 mt-0.5 truncate">{{ $group->name }}</p>

            <div class="grid grid-cols-3 gap-2 mt-4 pt-3 border-t border-white/20">
                <div>
                    <p class="text-[9px] uppercase text-blue-100">{{ __("O'qituvchi") }}</p>
                    <p class="text-[11px] font-medium mt-0.5 truncate">{{ $group->teacher_name ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-[9px] uppercase text-blue-100">{{ __("Davr") }}</p>
                    <p class="text-[11px] font-medium mt-0.5">{{ $totalDays }} {{ __("kun") }}</p>
                </div>
                <div>
                    <p class="text-[9px] uppercase text-blue-100">{{ __("Sanalar") }}</p>
                    <p class="text-[11px] font-medium mt-0.5">
                        {{ $group->start_date->format('d.m') }} — {{ $group->end_date->format('d.m.Y') }}
                    </p>
                </div>
            </div>
        </div>

        {{-- Statistika kartalari (mobile-friendly grid) --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mb-3">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-3 text-center">
                <p class="text-[9px] uppercase text-gray-500 tracking-wide">{{ __("O'rtacha") }}</p>
                @if($avg !== null)
                    @php $avgClass = $avg >= 75 ? 'text-green-600' : ($avg >= 60 ? 'text-amber-600' : 'text-red-600'); @endphp
                    <p class="text-2xl font-bold {{ $avgClass }} mt-1">{{ $avg }}</p>
                @else
                    <p class="text-2xl font-bold text-gray-300 mt-1">—</p>
                @endif
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-3 text-center">
                <p class="text-[9px] uppercase text-gray-500 tracking-wide">{{ __("Baholangan") }}</p>
                <p class="text-2xl font-bold text-blue-600 mt-1">{{ $gradedCount }}<span class="text-sm text-gray-400">/{{ $totalDays }}</span></p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-3 text-center">
                <p class="text-[9px] uppercase text-gray-500 tracking-wide">{{ __("O'tilgan") }}</p>
                <p class="text-2xl font-bold text-green-600 mt-1">{{ $passCount }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-3 text-center">
                <p class="text-[9px] uppercase text-gray-500 tracking-wide">{{ __("Yiqilgan") }}</p>
                <p class="text-2xl font-bold text-red-500 mt-1">{{ $failCount }}</p>
            </div>
        </div>

        {{-- Kunlik baholar — ixcham grid (mobile + desktop bir xil) --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-gray-900">{{ __("Kunlik baholar") }}</h3>
                <div class="flex items-center gap-1.5 text-[10px]">
                    <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-green-500"></span><span class="text-gray-500">≥75</span></span>
                    <span class="inline-flex items-center gap-1 ml-1"><span class="w-2 h-2 rounded-full bg-amber-500"></span><span class="text-gray-500">60-74</span></span>
                    <span class="inline-flex items-center gap-1 ml-1"><span class="w-2 h-2 rounded-full bg-red-500"></span><span class="text-gray-500">&lt;60</span></span>
                </div>
            </div>

            <div class="p-3">
                <div class="grid grid-cols-4 sm:grid-cols-6 md:grid-cols-8 gap-2">
                    @foreach($dates as $d)
                        @php
                            $g = $grades->get($d);
                            $v = $g?->grade;
                            $hasV = $v !== null;
                            if ($hasV) {
                                $f = (float) $v;
                                $cls = $f >= 75 ? 'bg-green-50 border-green-300 text-green-700' : ($f >= 60 ? 'bg-amber-50 border-amber-300 text-amber-700' : 'bg-red-50 border-red-300 text-red-700');
                            } else {
                                $cls = 'bg-gray-50 border-gray-200 text-gray-300';
                            }
                            $carb = \Carbon\Carbon::parse($d);
                        @endphp
                        <div class="border-2 {{ $cls }} rounded-lg p-2 text-center transition hover:shadow-sm"
                             title="{{ $carb->format('Y-m-d') }}{{ $g?->comment ? ' — ' . $g->comment : '' }}">
                            <p class="text-[10px] uppercase font-semibold opacity-70">{{ $carb->format('d.m') }}</p>
                            <p class="text-xl font-bold mt-0.5 leading-tight">
                                @if($hasV)
                                    {{ rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.') }}
                                @else
                                    <span class="text-2xl">—</span>
                                @endif
                            </p>
                            <p class="text-[9px] opacity-60">{{ $carb->format('D') }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Mustaqil ta'lim --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 mt-4 overflow-hidden">
            <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between flex-wrap gap-2">
                <h3 class="text-sm font-semibold text-gray-900">{{ __("Mustaqil ta'lim") }}</h3>
                @if($mustaqil)
                    @if($mustaqil->grade !== null)
                        @php
                            $mv = (float) $mustaqil->grade;
                            $mc = $mv >= 75 ? 'bg-green-100 text-green-800' : ($mv >= 60 ? 'bg-amber-100 text-amber-800' : 'bg-red-100 text-red-800');
                        @endphp
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $mc }}">
                            {{ __("Baho") }}: {{ rtrim(rtrim(number_format($mv, 2, '.', ''), '0'), '.') }}
                        </span>
                    @else
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                            {{ __("Tekshirilmoqda") }}
                        </span>
                    @endif
                @endif
            </div>

            <div class="p-5 space-y-3">
                @if($mustaqil && $mustaqil->file_path)
                    <div class="bg-gray-50 rounded-lg p-3 flex items-center justify-between flex-wrap gap-2">
                        <div class="flex items-center gap-2 min-w-0">
                            <svg class="w-5 h-5 text-blue-600 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                            </svg>
                            <div class="min-w-0">
                                <a href="{{ route('student.retake-journal.mustaqil-download', $group->id) }}"
                                   class="text-sm font-medium text-blue-600 hover:underline truncate block">
                                    {{ $mustaqil->original_filename ?? __("Fayl") }}
                                </a>
                                <p class="text-[11px] text-gray-500 mt-0.5">
                                    {{ __("Yuklangan") }}: {{ $mustaqil->submitted_at?->format('Y-m-d H:i') }}
                                </p>
                            </div>
                        </div>
                    </div>

                    @if($mustaqil->student_comment)
                        <div class="text-xs">
                            <span class="text-gray-500">{{ __("Sizning izohingiz") }}:</span>
                            <span class="text-gray-700">{{ $mustaqil->student_comment }}</span>
                        </div>
                    @endif

                    @if($mustaqil->grade !== null)
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
                            <p class="text-xs text-blue-900">
                                <span class="font-semibold">{{ __("O'qituvchi bahosi") }}:</span>
                                {{ rtrim(rtrim(number_format($mustaqil->grade, 2, '.', ''), '0'), '.') }}
                            </p>
                            @if($mustaqil->teacher_comment)
                                <p class="text-xs text-blue-800 mt-1">
                                    <span class="font-medium">{{ __("Izoh") }}:</span>
                                    {{ $mustaqil->teacher_comment }}
                                </p>
                            @endif
                            @if($mustaqil->graded_by_name)
                                <p class="text-[11px] text-blue-700 mt-1">
                                    {{ $mustaqil->graded_by_name }} · {{ $mustaqil->graded_at?->format('Y-m-d H:i') }}
                                </p>
                            @endif
                        </div>
                    @endif
                @endif

                {{-- Yuklash formasi --}}
                @if($isEditable)
                    <form method="POST"
                          action="{{ route('student.retake-journal.mustaqil-upload', $group->id) }}"
                          enctype="multipart/form-data"
                          class="space-y-3 pt-2 border-t border-gray-100">
                        @csrf
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">
                                @if($mustaqil)
                                    {{ __("Faylni qayta yuklash") }} (max 5 MB)
                                @else
                                    {{ __("Fayl yuklash") }} (max 5 MB) <span class="text-red-500">*</span>
                                @endif
                            </label>
                            <input type="file"
                                   name="file"
                                   accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.zip,.rar"
                                   required
                                   class="block w-full text-xs text-gray-700 file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                            <p class="text-[11px] text-gray-500 mt-1">PDF, DOC, JPG, PNG, ZIP, RAR · max 5 MB</p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">
                                {{ __("Izoh (ixtiyoriy)") }}
                            </label>
                            <textarea name="comment"
                                      rows="2"
                                      maxlength="1000"
                                      class="w-full px-3 py-2 text-xs border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"></textarea>
                        </div>
                        <button type="submit"
                                class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition shadow-sm">
                            @if($mustaqil) {{ __("Qayta yuklash") }} @else {{ __("Yuklash") }} @endif
                        </button>

                        @if($mustaqil)
                            <p class="text-[11px] text-amber-700">
                                ⚠️ {{ __("Qayta yuklasangiz, mavjud baho va izoh bekor qilinadi va o'qituvchi qaytadan tekshiradi.") }}
                            </p>
                        @endif
                    </form>
                @else
                    <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 text-xs text-amber-900">
                        ⚠️ {{ __("Guruh muddati tugagan, fayl yuklash mumkin emas") }}
                    </div>
                @endif
            </div>
        </div>

    </div>
</x-student-app-layout>
