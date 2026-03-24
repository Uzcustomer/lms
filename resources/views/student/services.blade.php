<x-student-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-sm text-gray-800 leading-tight">
            Xizmatlar
        </h2>
    </x-slot>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 px-3">
        <div class="grid grid-cols-2 gap-3">

            {{-- 1. Sababli ariza --}}
            <a href="{{ route('student.absence-excuses.index') }}"
               class="flex flex-col items-center bg-white rounded-xl border border-gray-200 overflow-hidden active:scale-[0.98] transition-all duration-150"
               style="padding:16px 10px; box-shadow: 0 4px 14px rgba(59, 130, 246, 0.25);">
                <div class="w-14 h-14 rounded-2xl bg-orange-100 flex items-center justify-center mb-3">
                    <svg class="w-7 h-7 text-orange-600" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                    </svg>
                </div>
                <span class="text-sm font-bold text-gray-800 text-center leading-tight">Sababli ariza</span>
                <span class="text-[11px] text-gray-400 mt-1 text-center">Dars qoldirish arizasi</span>
            </a>

            {{-- 2. Apellyatsiya --}}
            <a href="{{ route('student.appeals.index') }}"
               class="flex flex-col items-center bg-white rounded-xl border border-gray-200 overflow-hidden active:scale-[0.98] transition-all duration-150"
               style="padding:16px 10px; box-shadow: 0 4px 14px rgba(59, 130, 246, 0.25);">
                <div class="w-14 h-14 rounded-2xl bg-purple-100 flex items-center justify-center mb-3">
                    <svg class="w-7 h-7 text-purple-600" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0-10.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285zm0 13.036h.008v.008H12v-.008z" />
                    </svg>
                </div>
                <span class="text-sm font-bold text-gray-800 text-center leading-tight">Apellyatsiya</span>
                <span class="text-[11px] text-gray-400 mt-1 text-center">Imtihon natijalari bo'yicha</span>
            </a>

            {{-- 3. Pasport ma'lumotlari (faqat bitiruvchilar uchun) --}}
            @if(Auth::guard('student')->user()->is_graduate)
            <a href="{{ route('student.passport.index') }}"
               class="flex flex-col items-center bg-white rounded-xl border border-gray-200 overflow-hidden active:scale-[0.98] transition-all duration-150"
               style="padding:16px 10px; box-shadow: 0 4px 14px rgba(59, 130, 246, 0.25);">
                <div class="w-14 h-14 rounded-2xl bg-indigo-100 flex items-center justify-center mb-3">
                    <svg class="w-7 h-7 text-indigo-600" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 9h3.75M15 12h3.75M15 15h3.75M4.5 19.5h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5zm6-10.125a1.875 1.875 0 11-3.75 0 1.875 1.875 0 013.75 0zm1.294 6.336a6.721 6.721 0 01-3.17.789 6.721 6.721 0 01-3.168-.789 3.376 3.376 0 016.338 0z" />
                    </svg>
                </div>
                <span class="text-sm font-bold text-gray-800 text-center leading-tight">Pasport ma'lumotlari</span>
                <span class="text-[11px] text-gray-400 mt-1 text-center">Shaxsiy hujjatlar</span>
            </a>
            @endif

            {{-- 4. Shartnoma (faqat bitiruvchilar uchun) --}}
            @if(Auth::guard('student')->user()->is_graduate)
            <a href="{{ route('student.contracts.index') }}"
               class="flex flex-col items-center bg-white rounded-xl border border-gray-200 overflow-hidden active:scale-[0.98] transition-all duration-150"
               style="padding:16px 10px; box-shadow: 0 4px 14px rgba(59, 130, 246, 0.25);">
                <div class="w-14 h-14 rounded-2xl bg-teal-100 flex items-center justify-center mb-3">
                    <svg class="w-7 h-7 text-teal-600" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75h6m-6 3h4" />
                    </svg>
                </div>
                <span class="text-sm font-bold text-gray-800 text-center leading-tight">Shartnoma</span>
                <span class="text-[11px] text-gray-400 mt-1 text-center">3 va 4 tomonlama</span>
            </a>
            @endif

        </div>
    </div>
</x-student-app-layout>
