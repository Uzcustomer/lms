<form method="POST" action="{{ route('student.visa-info.store') }}" enctype="multipart/form-data" id="visaForm"
      x-data="{ showAgreementModal: false }">
    @csrf

    {{-- Ogohlantirish --}}
    <div class="mb-5 p-4 bg-blue-50 border border-blue-300 rounded-lg flex items-start gap-3">
        <svg class="w-6 h-6 text-blue-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z"/>
        </svg>
        <p class="text-sm text-blue-800 font-medium">{{ __('Barcha ma\'lumotlarni to\'g\'ri va aniq kiriting. Bu ma\'lumotlar registrator ofisi tomonidan tekshiriladi.') }}</p>
    </div>

    {{-- HEMIS --}}
    <div class="mb-5 p-3 rounded-lg border border-gray-200" style="background:linear-gradient(135deg,#f0f4f8,#e8edf5);">
        <div style="font-size:10px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:8px;">{{ __('Shaxsiy ma\'lumotlar (HEMIS)') }}</div>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <div><span class="text-[10px] text-gray-400">{{ __('F.I.Sh') }}</span><br><b class="text-sm text-gray-800">{{ $student->full_name }}</b></div>
            <div><span class="text-[10px] text-gray-400">{{ __('Tug\'ilgan sana') }}</span><br><span class="text-sm text-gray-800">{{ $student->birth_date?->format('d.m.Y') ?? '—' }}</span></div>
            <div><span class="text-[10px] text-gray-400">{{ __('Fuqaroligi') }}</span><br><span class="text-sm text-gray-800">{{ $student->citizenship_name ?? '—' }}</span></div>
            <div><span class="text-[10px] text-gray-400">{{ __('Davlat') }}</span><br><span class="text-sm text-gray-800">{{ $student->country_name ?? '—' }}</span></div>
        </div>
    </div>

    {{-- Tug'ilgan joy --}}
    <div class="mb-4 p-4 bg-white rounded-lg border border-gray-200">
        <div style="font-size:11px;font-weight:700;color:#0f766e;margin-bottom:10px;">{{ __("Tug'ilgan joy") }}</div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            {{-- Davlat --}}
            <div x-data="searchSelect({
                items: {{ json_encode([
                    'Afghanistan','Albania','Algeria','Angola','Argentina','Armenia','Australia','Austria','Azerbaijan',
                    'Bahrain','Bangladesh','Belarus','Belgium','Bhutan','Bolivia','Bosnia','Brazil','Brunei','Bulgaria',
                    'Cambodia','Cameroon','Canada','Chad','Chile','China','Colombia','Congo','Croatia','Cuba','Cyprus','Czech Republic',
                    'Denmark','Ecuador','Egypt','Eritrea','Estonia','Ethiopia','Finland','France',
                    'Georgia','Germany','Ghana','Greece','Guatemala','Guinea',
                    'Haiti','Honduras','Hungary','India','Indonesia','Iran','Iraq','Ireland','Israel','Italy',
                    'Jamaica','Japan','Jordan','Kazakhstan','Kenya','Korea','Kuwait','Kyrgyzstan',
                    'Laos','Latvia','Lebanon','Libya','Lithuania','Madagascar','Malaysia','Maldives','Mali','Mexico','Moldova','Mongolia','Morocco','Mozambique','Myanmar',
                    'Nepal','Netherlands','New Zealand','Nicaragua','Niger','Nigeria','Norway',
                    'Oman','Pakistan','Palestine','Panama','Paraguay','Peru','Philippines','Poland','Portugal',
                    'Qatar','Romania','Russia','Saudi Arabia','Senegal','Serbia','Singapore','Slovakia','Slovenia','Somalia','South Africa','Spain','Sri Lanka','Sudan','Sweden','Switzerland','Syria',
                    'Tajikistan','Tanzania','Thailand','Tunisia','Turkey','Turkmenistan',
                    'UAE','Uganda','Ukraine','United Kingdom','United States','Uruguay','Uzbekistan',
                    'Venezuela','Vietnam','Yemen','Zambia','Zimbabwe'
                ]) }},
                value: '{{ old('birth_country', $visaInfo?->birth_country ?? '') }}'
            })">
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Davlat') }} <span class="text-red-500">*</span></label>
                <div class="relative">
                    <input type="text" x-model="search" @focus="open=true" @click="open=true" @input="open=true"
                           placeholder="{{ __('Qidiring...') }}"
                           class="w-full rounded-lg text-sm border-gray-300 focus:ring-indigo-500 focus:border-indigo-500" autocomplete="off">
                    <input type="hidden" name="birth_country" :value="value" required>
                    <div x-show="open && filtered.length > 0" @click.away="open=false" x-transition
                         class="absolute z-50 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                        <template x-for="item in filtered" :key="item">
                            <div @click="select(item)" x-text="item"
                                 class="px-3 py-2 text-sm cursor-pointer hover:bg-indigo-50 hover:text-indigo-700 transition"></div>
                        </template>
                    </div>
                    <template x-if="value && !open">
                        <span class="absolute right-2 top-1/2 -translate-y-1/2 text-xs text-green-600 font-semibold" x-text="value"></span>
                    </template>
                </div>
                @error('birth_country') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- Viloyat --}}
            <div x-data="regionSelect({ value: '{{ old('birth_region', $visaInfo?->birth_region ?? '') }}' })" x-effect="updateRegions($store.birthCountry)">
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Viloyat') }} <span class="text-red-500">*</span></label>
                <div class="relative">
                    <input type="text" x-model="search" @focus="open=true" @click="open=true" @input="open=true"
                           placeholder="{{ __('Qidiring...') }}"
                           class="w-full rounded-lg text-sm border-gray-300 focus:ring-indigo-500 focus:border-indigo-500" autocomplete="off">
                    <input type="hidden" name="birth_region" :value="value" required>
                    <div x-show="open && filtered.length > 0" @click.away="open=false" x-transition
                         class="absolute z-50 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                        <template x-for="item in filtered" :key="item">
                            <div @click="selectItem(item)" x-text="item"
                                 class="px-3 py-2 text-sm cursor-pointer hover:bg-indigo-50 hover:text-indigo-700 transition"></div>
                        </template>
                    </div>
                    <template x-if="value && !open">
                        <span class="absolute right-2 top-1/2 -translate-y-1/2 text-xs text-green-600 font-semibold" x-text="value"></span>
                    </template>
                </div>
                @error('birth_region') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- Shahar --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Shahar') }} <span class="text-red-500">*</span></label>
                <input type="text" name="birth_city" value="{{ old('birth_city', $visaInfo?->birth_city ?? '') }}" required
                       placeholder="{{ __('Shahar nomini kiriting') }}"
                       class="w-full rounded-lg text-sm border-gray-300 focus:ring-indigo-500 focus:border-indigo-500">
                @error('birth_city') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
        </div>
    </div>

    {{-- Pasport --}}
    <div class="mb-4 p-4 bg-white rounded-lg border border-gray-200">
        <div style="font-size:11px;font-weight:700;color:#1e40af;margin-bottom:10px;">{{ __('Pasport ma\'lumotlari') }}</div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Pasport raqami') }} <span class="text-red-500">*</span></label>
            <input type="text" name="passport_number" value="{{ old('passport_number', $visaInfo?->passport_number ?? '') }}" required
                   placeholder="Masalan: AA1234567"
                   class="w-full rounded-lg text-sm border-gray-300 focus:ring-indigo-500 focus:border-indigo-500">
            @error('passport_number') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Pasport berilgan joy') }} <span class="text-red-500">*</span></label>
            <input type="text" name="passport_issued_place" value="{{ old('passport_issued_place', $visaInfo?->passport_issued_place ?? '') }}" required
                   class="w-full rounded-lg text-sm border-gray-300 focus:ring-indigo-500 focus:border-indigo-500">
            @error('passport_issued_place') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Pasport berilgan sana') }} <span class="text-red-500">*</span></label>
            <input type="text" data-datepicker="true" readonly name="passport_issued_date" value="{{ old('passport_issued_date', $visaInfo?->passport_issued_date?->format('Y-m-d') ?? '') }}" required
                   class="w-full rounded-lg text-sm border-gray-300 focus:ring-indigo-500 focus:border-indigo-500">
            @error('passport_issued_date') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Pasport muddati tugash sanasi') }} <span class="text-red-500">*</span></label>
            <input type="text" data-datepicker="true" readonly name="passport_expiry_date" value="{{ old('passport_expiry_date', $visaInfo?->passport_expiry_date?->format('Y-m-d') ?? '') }}" required
                   class="w-full rounded-lg text-sm border-gray-300 focus:ring-indigo-500 focus:border-indigo-500">
            @error('passport_expiry_date') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>
    </div>
    </div>

    {{-- Registratsiya + Viza yonma-yon --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
        <div class="p-4 bg-white rounded-lg border border-gray-200">
            <div style="font-size:11px;font-weight:700;color:#16a34a;margin-bottom:4px;">Vaqtinchalik ro'yxatga qo'yish (registratsiya) / Temporary Registration</div>
            <div style="font-size:10px;color:#64748b;margin-bottom:10px;font-style:italic;">Vaqtinchalik registratsiya — pasportingizning orqasida yopishtirilgan qog'ozda / The paper attached to the back of your passport</div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Boshlanish sanasi') }} <span class="text-red-500">*</span></label>
            <input type="text" data-datepicker="true" readonly name="registration_start_date" value="{{ old('registration_start_date', $visaInfo?->registration_start_date?->format('Y-m-d') ?? '') }}" required
                   class="w-full rounded-lg text-sm border-gray-300 focus:ring-indigo-500 focus:border-indigo-500">
            @error('registration_start_date') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Tugash sanasi') }} <span class="text-red-500">*</span></label>
            <input type="text" data-datepicker="true" readonly name="registration_end_date" value="{{ old('registration_end_date', $visaInfo?->registration_end_date?->format('Y-m-d') ?? '') }}" required
                   class="w-full rounded-lg text-sm border-gray-300 focus:ring-indigo-500 focus:border-indigo-500">
            @error('registration_end_date') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>
    </div>
    </div>

    {{-- Viza --}}
    <div class="p-4 bg-white rounded-lg border border-gray-200">
        <div style="font-size:11px;font-weight:700;color:#d97706;margin-bottom:10px;">{{ __('Viza ma\'lumotlari') }}</div>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Viza raqami') }} <span class="text-red-500">*</span></label>
            <input type="text" name="visa_number" value="{{ old('visa_number', $visaInfo?->visa_number ?? '') }}" required
                   class="w-full rounded-lg text-sm border-gray-300 focus:ring-indigo-500 focus:border-indigo-500">
            @error('visa_number') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Viza turi') }} <span class="text-red-500">*</span></label>
            <select name="visa_type" required class="w-full rounded-lg text-sm border-gray-300 focus:ring-indigo-500 focus:border-indigo-500">
                <option value="">{{ __('Tanlang') }}</option>
                @foreach(\App\Models\StudentVisaInfo::VISA_TYPES as $key => $label)
                    <option value="{{ $key }}" {{ old('visa_type', $visaInfo?->visa_type ?? '') === $key ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
            @error('visa_type') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Kirishlar soni') }} <span class="text-red-500">*</span></label>
            <select name="visa_entries_count" required class="w-full rounded-lg text-sm border-gray-300 focus:ring-indigo-500 focus:border-indigo-500">
                <option value="">{{ __('Tanlang') }}</option>
                @for($i = 1; $i <= 10; $i++)
                    <option value="{{ $i }}" {{ (int) old('visa_entries_count', $visaInfo?->visa_entries_count ?? '') === $i ? 'selected' : '' }}>{{ $i }}</option>
                @endfor
            </select>
            @error('visa_entries_count') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Viza boshlanish sanasi') }} <span class="text-red-500">*</span></label>
            <input type="text" data-datepicker="true" readonly name="visa_start_date" value="{{ old('visa_start_date', $visaInfo?->visa_start_date?->format('Y-m-d') ?? '') }}" required
                   class="w-full rounded-lg text-sm border-gray-300 focus:ring-indigo-500 focus:border-indigo-500">
            @error('visa_start_date') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Viza tugash sanasi') }} <span class="text-red-500">*</span></label>
            <input type="text" data-datepicker="true" readonly name="visa_end_date" value="{{ old('visa_end_date', $visaInfo?->visa_end_date?->format('Y-m-d') ?? '') }}" required
                   class="w-full rounded-lg text-sm border-gray-300 focus:ring-indigo-500 focus:border-indigo-500">
            @error('visa_end_date') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Istiqomat muddati (kunlarda)') }} <span class="text-red-500">*</span></label>
            <input type="number" name="visa_stay_days" min="1" value="{{ old('visa_stay_days', $visaInfo?->visa_stay_days ?? '') }}" required
                   class="w-full rounded-lg text-sm border-gray-300 focus:ring-indigo-500 focus:border-indigo-500">
            @error('visa_stay_days') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Viza berilgan joy') }} <span class="text-red-500">*</span></label>
            <input type="text" name="visa_issued_place" value="{{ old('visa_issued_place', $visaInfo?->visa_issued_place ?? '') }}" required
                   class="w-full rounded-lg text-sm border-gray-300 focus:ring-indigo-500 focus:border-indigo-500">
            @error('visa_issued_place') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Viza berilgan vaqti') }} <span class="text-red-500">*</span></label>
            <input type="text" data-datepicker="true" readonly name="visa_issued_date" value="{{ old('visa_issued_date', $visaInfo?->visa_issued_date?->format('Y-m-d') ?? '') }}" required
                   class="w-full rounded-lg text-sm border-gray-300 focus:ring-indigo-500 focus:border-indigo-500">
            @error('visa_issued_date') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>
    </div>
    </div>
    </div>

    {{-- Chegaradan kirish --}}
    <div class="mb-4 p-4 bg-white rounded-lg border border-gray-200">
        <div style="font-size:11px;font-weight:700;color:#7c3aed;margin-bottom:10px;">{{ __('Chegaradan kirish') }}</div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Chegaradan kirgan sana') }} <span class="text-red-500">*</span></label>
            <input type="text" data-datepicker="true" readonly name="entry_date" value="{{ old('entry_date', $visaInfo?->entry_date?->format('Y-m-d') ?? '') }}" required
                   class="w-full rounded-lg text-sm border-gray-300 focus:ring-indigo-500 focus:border-indigo-500">
            @error('entry_date') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>
    </div>
    </div>

    {{-- Hujjatlar --}}
    <div class="mb-4 p-4 bg-white rounded-lg border border-gray-200">
        <div style="font-size:11px;font-weight:700;color:#dc2626;margin-bottom:10px;">{{ __('Hujjatlar yuklash') }}</div>
    <div class="mb-4 p-3 bg-amber-50 border border-amber-200 rounded-lg flex items-start gap-2">
        <svg class="w-5 h-5 text-amber-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
        <div class="text-xs text-amber-800">
            <p class="font-bold mb-1">{{ __('Hujjatlarni sifatli skaner qiling!') }}</p>
            <p>{{ __("Faqat PDF formatida, aniq va o'qilishi mumkin bo'lgan skaner yuklang. Sifatsiz yoki xiralashgan fayllar rad etiladi. Telefon kamerasi bilan suratga olganda yaxshi yorug'likda, to'g'ri burchakda oling.") }}</p>
        </div>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Pasport skaneri (PDF)') }} <span class="text-red-500">*</span></label>
            @if($visaInfo?->passport_scan_path)
                <div class="mb-2">
                    <a href="{{ route('student.visa-info.file', 'passport_scan_path') }}" target="_blank" class="text-xs text-indigo-600 hover:underline">{{ __('Yuklangan faylni ko\'rish') }}</a>
                </div>
            @endif
            <input type="file" name="passport_scan" accept=".pdf" onchange="checkPdfSize(this)"
                   class="w-full text-sm border-gray-300 rounded-lg file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-sm file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
            <p data-file-error class="text-xs text-red-600 mt-1 hidden"></p>
            @error('passport_scan') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Viza skaneri (PDF)') }} <span class="text-red-500">*</span></label>
            @if($visaInfo?->visa_scan_path)
                <div class="mb-2">
                    <a href="{{ route('student.visa-info.file', 'visa_scan_path') }}" target="_blank" class="text-xs text-indigo-600 hover:underline">{{ __('Yuklangan faylni ko\'rish') }}</a>
                </div>
            @endif
            <input type="file" name="visa_scan" accept=".pdf" onchange="checkPdfSize(this)"
                   class="w-full text-sm border-gray-300 rounded-lg file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-sm file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
            <p data-file-error class="text-xs text-red-600 mt-1 hidden"></p>
            @error('visa_scan') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Ro\'yxatga olish hujjati (PDF)') }} <span class="text-red-500">*</span></label>
            @if($visaInfo?->registration_doc_path)
                <div class="mb-2">
                    <a href="{{ route('student.visa-info.file', 'registration_doc_path') }}" target="_blank" class="text-xs text-indigo-600 hover:underline">{{ __('Yuklangan faylni ko\'rish') }}</a>
                </div>
            @endif
            <input type="file" name="registration_doc" accept=".pdf" onchange="checkPdfSize(this)"
                   class="w-full text-sm border-gray-300 rounded-lg file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-sm file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
            <p data-file-error class="text-xs text-red-600 mt-1 hidden"></p>
            @error('registration_doc') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>
    </div>
    </div>

    {{-- Saqlash tugmasi --}}
    <div class="flex justify-end mt-6">
        <button type="button" @click="showAgreementModal = true"
                class="px-6 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 focus:ring-4 focus:ring-indigo-300 transition">
            {{ __('Saqlash') }}
        </button>
    </div>

    {{-- Javobgarlik modali --}}
    <div x-show="showAgreementModal" x-transition class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50" style="display: none;">
        <div class="bg-white rounded-xl shadow-2xl max-w-md w-full mx-4 p-6" @click.away="showAgreementModal = false">
            <div class="text-center mb-4">
                <svg class="w-12 h-12 text-yellow-500 mx-auto mb-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
                </svg>
                <h3 class="text-lg font-semibold text-gray-800 mb-2">{{ __('Javobgarlik') }}</h3>
                <p class="text-sm text-gray-600">
                    {{ __('Men taqdim etgan barcha ma\'lumotlar va yuklangan hujjatlarning haqiqiyligi hamda to\'g\'riligiga shaxsan javobgarlikni o\'z zimnamga olaman') }}
                </p>
            </div>
            <input type="hidden" name="agreement_accepted" id="agreement_input" value="0">
            <div class="flex gap-3 mt-6">
                <button type="button" @click="showAgreementModal = false"
                        class="flex-1 px-4 py-2.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition">
                    {{ __('Bekor qilish') }}
                </button>
                <button type="button"
                        @click="document.getElementById('agreement_input').value = '1'; document.getElementById('visaForm').submit();"
                        class="flex-1 px-4 py-2.5 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition">
                    {{ __('Qabul qilaman') }}
                </button>
            </div>
        </div>
    </div>
</form>
