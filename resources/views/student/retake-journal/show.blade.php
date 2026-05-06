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

    <div class="py-4 sm:py-6 px-3 sm:px-6 lg:px-8 max-w-5xl mx-auto">
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

        {{-- Guruh ma'lumotlari (mobile uchun ixcham) --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-3 sm:p-4 mb-3">
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 sm:gap-3 text-xs sm:text-sm">
                <div>
                    <p class="text-[10px] sm:text-xs text-gray-500 uppercase">{{ __("Guruh") }}</p>
                    <p class="font-medium text-gray-900 mt-0.5 text-xs sm:text-sm">{{ $group->name }}</p>
                </div>
                <div>
                    <p class="text-[10px] sm:text-xs text-gray-500 uppercase">{{ __("O'qituvchi") }}</p>
                    <p class="font-medium text-gray-900 mt-0.5 text-xs sm:text-sm truncate">{{ $group->teacher_name ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-[10px] sm:text-xs text-gray-500 uppercase">{{ __("Sanalar") }}</p>
                    <p class="font-medium text-gray-900 mt-0.5 text-[11px]">
                        {{ $group->start_date->format('Y-m-d') }}<br class="sm:hidden">→ {{ $group->end_date->format('Y-m-d') }}
                    </p>
                </div>
                <div>
                    <p class="text-[10px] sm:text-xs text-gray-500 uppercase">{{ __("Davr") }}</p>
                    <p class="font-medium text-gray-900 mt-0.5 text-xs sm:text-sm">{{ count($dates) }} {{ __("kun") }}</p>
                </div>
            </div>
        </div>

        {{-- Baholar jadvali — mobilda ixcham --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-3 sm:px-5 py-3 border-b border-gray-100 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-gray-900">{{ __("Mening baholarim") }}</h3>
                @php
                    $values = $grades->map(fn ($g) => $g->grade)->filter(fn ($v) => $v !== null);
                    $avg = $values->isNotEmpty() ? round($values->avg(), 1) : null;
                @endphp
                @if($avg !== null)
                    <span class="text-xs px-2 py-0.5 rounded-full bg-blue-100 text-blue-800 font-medium">
                        {{ __("O'rtacha") }}: {{ $avg }}
                    </span>
                @endif
            </div>

            {{-- Mobile: kartalar --}}
            <div class="sm:hidden divide-y divide-gray-100">
                @foreach($dates as $d)
                    @php $g = $grades->get($d); @endphp
                    <div class="px-3 py-2.5 flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-gray-900">{{ \Carbon\Carbon::parse($d)->format('d M, D') }}</p>
                            @if($g?->comment)
                                <p class="text-[11px] text-gray-500 truncate">{{ $g->comment }}</p>
                            @endif
                        </div>
                        <div class="flex-shrink-0">
                            @if($g && $g->grade !== null)
                                @php
                                    $v = (float) $g->grade;
                                    $bg = $v >= 75 ? 'bg-green-100 text-green-800' : ($v >= 60 ? 'bg-amber-100 text-amber-800' : 'bg-red-100 text-red-800');
                                @endphp
                                <span class="inline-flex items-center justify-center w-10 h-10 rounded-full font-bold text-sm {{ $bg }}">
                                    {{ rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.') }}
                                </span>
                            @else
                                <span class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-gray-100 text-gray-400 text-lg">—</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Desktop: jadval --}}
            <div class="hidden sm:block overflow-x-auto">
                <table class="min-w-full text-xs">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium text-gray-500 uppercase">{{ __("Sana") }}</th>
                            <th class="px-3 py-2 text-center font-medium text-gray-500 uppercase">{{ __("Baho") }}</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-500 uppercase">{{ __("Izoh") }}</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-500 uppercase">{{ __("Qo'yilgan") }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($dates as $d)
                            @php $g = $grades->get($d); @endphp
                            <tr class="hover:bg-gray-50">
                                <td class="px-3 py-2 text-gray-700">{{ \Carbon\Carbon::parse($d)->format('Y-m-d') }}</td>
                                <td class="px-3 py-2 text-center">
                                    @if($g && $g->grade !== null)
                                        @php
                                            $v = (float) $g->grade;
                                            $color = $v >= 75 ? 'text-green-700' : ($v >= 60 ? 'text-amber-700' : 'text-red-700');
                                        @endphp
                                        <span class="font-semibold {{ $color }}">{{ rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.') }}</span>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-gray-700">{{ $g?->comment ?? '' }}</td>
                                <td class="px-3 py-2 text-gray-500 text-[11px]">
                                    @if($g && $g->graded_at)
                                        {{ $g->graded_by_name }} · {{ $g->graded_at->format('Y-m-d H:i') }}
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-blue-50">
                        <tr>
                            <td class="px-3 py-2 font-semibold text-gray-700">{{ __("O'rtacha") }}</td>
                            <td class="px-3 py-2 text-center">
                                @if($avg !== null)
                                    <span class="font-bold text-blue-700">{{ $avg }}</span>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
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
