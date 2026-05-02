<x-student-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-sm text-gray-800 leading-tight">
            {{ __("Nogironlik ma'lumotlarim") }}
        </h2>
    </x-slot>

    <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 px-3 pb-6">
        @if(session('success'))
            <div class="mb-4 p-4 bg-green-50 border border-green-200 text-green-700 rounded-lg text-sm">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="mb-4 p-4 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm">
                {{ session('error') }}
            </div>
        @endif

        @if($errors->any())
            <div class="mb-4 p-4 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm">
                <ul class="list-disc list-inside">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if($disabilityInfo)
            <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg flex items-center gap-2">
                <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
                </svg>
                <span class="text-sm font-medium text-green-700">{{ __("Ma'lumotlaringiz saqlangan. Kerak bo'lsa tahrirlashingiz mumkin.") }}</span>
            </div>
        @endif

        <div class="bg-white shadow rounded-lg p-5 border border-gray-200">
            <div class="flex items-center gap-3 mb-5">
                <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 13a3 3 0 100-6 3 3 0 000 6zm6.5 4.5L20 21M9 13c-3 0-5 2-5 5v3h10m1-6a4 4 0 014 4"/>
                </svg>
                <h4 class="text-lg font-semibold text-gray-800">{{ __("Nogironlik ma'lumotlari") }}</h4>
            </div>

            <p class="text-xs text-gray-600 mb-4">
                {{ __("Ijtimoiy toifa") }}: <span class="font-semibold text-gray-800">{{ $student->social_category_name ?: '-' }}</span>
            </p>

            <form method="POST" action="{{ route('student.disability-info.store') }}" class="space-y-4" enctype="multipart/form-data">
                @csrf

                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        {{ __("Ko'rikdan o'tgan sana") }} <span class="text-red-500">*</span>
                    </label>
                    <input type="date" name="examined_at" required
                           value="{{ old('examined_at', optional($disabilityInfo?->examined_at)->format('Y-m-d')) }}"
                           max="{{ now()->format('Y-m-d') }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        {{ __("Nogironlik guruhi") }} <span class="text-red-500">*</span>
                    </label>
                    <select name="disability_group" required
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="">{{ __("Tanlang...") }}</option>
                        @foreach(\App\Models\StudentDisabilityInfo::GROUPS as $key => $label)
                            <option value="{{ $key }}" {{ old('disability_group', $disabilityInfo?->disability_group) === $key ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        {{ __("Nogironlik sababi") }} <span class="text-red-500">*</span>
                    </label>
                    <textarea name="disability_reason" required rows="3" maxlength="500"
                              placeholder="{{ __('Nogironlik sababini batafsil yozing...') }}"
                              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">{{ old('disability_reason', $disabilityInfo?->disability_reason) }}</textarea>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        {{ __("Nogironlik muddati (tugash sanasi)") }} <span class="text-red-500">*</span>
                    </label>
                    <input type="date" name="disability_duration" required
                           value="{{ old('disability_duration', optional($disabilityInfo?->disability_duration)->format('Y-m-d')) }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        {{ __("Ko'rikdan qayta o'tish muddati") }} <span class="text-red-500">*</span>
                    </label>
                    <input type="date" name="reexamination_at" required
                           value="{{ old('reexamination_at', optional($disabilityInfo?->reexamination_at)->format('Y-m-d')) }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        {{ __("Nogironlik malumotnomasi (PDF)") }}
                        @if(!$disabilityInfo?->certificate_path)<span class="text-red-500">*</span>@endif
                    </label>
                    @if($disabilityInfo?->certificate_path)
                        <div class="mb-2 flex items-center gap-2 p-2 bg-green-50 border border-green-200 rounded">
                            <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            <a href="{{ route('student.disability-info.file') }}" target="_blank" class="text-xs text-green-700 font-semibold hover:underline">{{ __("Yuklangan malumotnomani ko'rish") }}</a>
                        </div>
                    @endif
                    <input type="file" name="certificate" accept="application/pdf"
                           {{ $disabilityInfo?->certificate_path ? '' : 'required' }}
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    <p class="text-xs text-gray-500 mt-1">
                        @if($disabilityInfo?->certificate_path)
                            {{ __("Yangi PDF tanlasangiz, eskisi almashtiriladi.") }}
                        @else
                            {{ __("Faqat PDF, 5MB gacha.") }}
                        @endif
                    </p>
                </div>

                <div class="flex gap-3 pt-2">
                    <a href="{{ route('student.dashboard') }}"
                       class="flex-1 px-4 py-2.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition text-center">
                        {{ __('Ortga') }}
                    </a>
                    <button type="submit"
                            class="flex-1 px-4 py-2.5 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition">
                        {{ __('Saqlash') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-student-app-layout>
