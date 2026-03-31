<div class="border-t border-gray-200 pt-5">
    {{-- Tug'ilgan joy --}}
    <h5 class="text-sm font-semibold text-gray-700 mb-3 border-b pb-2">{{ __('Tug\'ilgan joy') }}</h5>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div>
            <span class="block text-xs text-gray-500">{{ __('Davlat') }}</span>
            <span class="text-sm font-medium text-gray-800">{{ $visaInfo->birth_country }}</span>
        </div>
        <div>
            <span class="block text-xs text-gray-500">{{ __('Viloyat') }}</span>
            <span class="text-sm font-medium text-gray-800">{{ $visaInfo->birth_region }}</span>
        </div>
        <div>
            <span class="block text-xs text-gray-500">{{ __('Shahar') }}</span>
            <span class="text-sm font-medium text-gray-800">{{ $visaInfo->birth_city }}</span>
        </div>
    </div>

    {{-- Pasport ma'lumotlari --}}
    <h5 class="text-sm font-semibold text-gray-700 mb-3 border-b pb-2">{{ __('Pasport ma\'lumotlari') }}</h5>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
        <div>
            <span class="block text-xs text-gray-500">{{ __('Pasport berilgan joy') }}</span>
            <span class="text-sm font-medium text-gray-800">{{ $visaInfo->passport_issued_place }}</span>
        </div>
        <div>
            <span class="block text-xs text-gray-500">{{ __('Pasport raqami') }}</span>
            <span class="text-sm font-medium text-gray-800">{{ $visaInfo->passport_number }}</span>
        </div>
        <div>
            <span class="block text-xs text-gray-500">{{ __('Pasport amal qilish muddati') }}</span>
            <span class="text-sm font-medium text-gray-800">{{ $visaInfo->passport_expiry_date?->format('d.m.Y') }}</span>
        </div>
        <div>
            <span class="block text-xs text-gray-500">{{ __('Tug\'ilgan sanasi') }}</span>
            <span class="text-sm font-medium text-gray-800">{{ $visaInfo->birth_date?->format('d.m.Y') }}</span>
        </div>
    </div>

    {{-- Vaqtinchalik ro'yxatga qo'yish --}}
    <h5 class="text-sm font-semibold text-gray-700 mb-3 border-b pb-2">{{ __('Vaqtinchalik ro\'yxatga qo\'yish') }}</h5>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
        <div>
            <span class="block text-xs text-gray-500">{{ __('Boshlanish sanasi') }}</span>
            <span class="text-sm font-medium text-gray-800">{{ $visaInfo->registration_start_date?->format('d.m.Y') }}</span>
        </div>
        <div>
            <span class="block text-xs text-gray-500">{{ __('Tugash sanasi') }}</span>
            <span class="text-sm font-medium text-gray-800">{{ $visaInfo->registration_end_date?->format('d.m.Y') }}</span>
            @php $regDays = $visaInfo->registrationDaysLeft(); @endphp
            @if($regDays !== null && $regDays <= 7)
                <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-full mt-1
                    {{ $regDays <= 3 ? 'bg-red-100 text-red-700' : ($regDays <= 5 ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700') }}">
                    {{ $regDays <= 0 ? 'Muddati tugagan!' : $regDays . ' kun qoldi' }}
                </span>
            @endif
        </div>
    </div>

    {{-- Viza ma'lumotlari --}}
    <h5 class="text-sm font-semibold text-gray-700 mb-3 border-b pb-2">{{ __('Viza ma\'lumotlari') }}</h5>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
        <div>
            <span class="block text-xs text-gray-500">{{ __('Viza raqami') }}</span>
            <span class="text-sm font-medium text-gray-800">{{ $visaInfo->visa_number }}</span>
        </div>
        <div>
            <span class="block text-xs text-gray-500">{{ __('Viza turi') }}</span>
            <span class="text-sm font-medium text-gray-800">{{ $visaInfo->visa_type }}</span>
        </div>
        <div>
            <span class="block text-xs text-gray-500">{{ __('Kirishlar soni') }}</span>
            <span class="text-sm font-medium text-gray-800">{{ $visaInfo->visa_entries_count }}</span>
        </div>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
        <div>
            <span class="block text-xs text-gray-500">{{ __('Viza boshlanish sanasi') }}</span>
            <span class="text-sm font-medium text-gray-800">{{ $visaInfo->visa_start_date?->format('d.m.Y') }}</span>
        </div>
        <div>
            <span class="block text-xs text-gray-500">{{ __('Viza tugash sanasi') }}</span>
            <span class="text-sm font-medium text-gray-800">{{ $visaInfo->visa_end_date?->format('d.m.Y') }}</span>
            @php $visaDays = $visaInfo->visaDaysLeft(); @endphp
            @if($visaDays !== null && $visaDays <= 30)
                <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-full mt-1
                    {{ $visaDays <= 15 ? 'bg-red-100 text-red-700' : ($visaDays <= 20 ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700') }}">
                    {{ $visaDays <= 0 ? 'Muddati tugagan!' : $visaDays . ' kun qoldi' }}
                </span>
            @endif
        </div>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div>
            <span class="block text-xs text-gray-500">{{ __('Istiqomat muddati') }}</span>
            <span class="text-sm font-medium text-gray-800">{{ $visaInfo->visa_stay_days }} {{ __('kun') }}</span>
        </div>
        <div>
            <span class="block text-xs text-gray-500">{{ __('Viza berilgan joy') }}</span>
            <span class="text-sm font-medium text-gray-800">{{ $visaInfo->visa_issued_place }}</span>
        </div>
        <div>
            <span class="block text-xs text-gray-500">{{ __('Viza berilgan vaqti') }}</span>
            <span class="text-sm font-medium text-gray-800">{{ $visaInfo->visa_issued_date?->format('d.m.Y') }}</span>
        </div>
    </div>

    {{-- Firma --}}
    <h5 class="text-sm font-semibold text-gray-700 mb-3 border-b pb-2">{{ __('Firma') }}</h5>
    <div class="mb-6">
        <span class="text-sm font-medium text-gray-800">{{ $visaInfo->firm_display }}</span>
    </div>

    {{-- Hujjatlar --}}
    <h5 class="text-sm font-semibold text-gray-700 mb-3 border-b pb-2">{{ __('Yuklangan hujjatlar') }}</h5>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        @if($visaInfo->passport_scan_path)
        <a href="{{ route('student.visa-info.file', 'passport_scan_path') }}" target="_blank"
           class="flex items-center gap-2 p-3 bg-gray-50 rounded-lg border border-gray-200 hover:bg-gray-100 transition">
            <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
            </svg>
            <span class="text-sm text-gray-700">{{ __('Pasport skaneri') }}</span>
        </a>
        @endif
        @if($visaInfo->visa_scan_path)
        <a href="{{ route('student.visa-info.file', 'visa_scan_path') }}" target="_blank"
           class="flex items-center gap-2 p-3 bg-gray-50 rounded-lg border border-gray-200 hover:bg-gray-100 transition">
            <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
            </svg>
            <span class="text-sm text-gray-700">{{ __('Viza skaneri') }}</span>
        </a>
        @endif
        @if($visaInfo->registration_doc_path)
        <a href="{{ route('student.visa-info.file', 'registration_doc_path') }}" target="_blank"
           class="flex items-center gap-2 p-3 bg-gray-50 rounded-lg border border-gray-200 hover:bg-gray-100 transition">
            <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
            </svg>
            <span class="text-sm text-gray-700">{{ __('Ro\'yxatga olish hujjati') }}</span>
        </a>
        @endif
    </div>
</div>
