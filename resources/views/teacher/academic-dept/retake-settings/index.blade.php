<x-teacher-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __("Qayta o'qish — Sozlamalar") }}
        </h2>
    </x-slot>

    <div class="py-6 px-4 sm:px-6 lg:px-8 max-w-3xl mx-auto">

        @if(session('success'))
            <div class="bg-green-50 border border-green-200 rounded-lg p-3 mb-4 text-sm text-green-800">
                {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="bg-red-50 border border-red-200 rounded-lg p-3 mb-4 text-sm text-red-800">
                <ul class="list-disc list-inside">@foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach</ul>
            </div>
        @endif

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <form method="POST" action="{{ route('admin.retake-settings.update') }}" class="space-y-5">
                @csrf @method('PUT')

                @php
                    $creditPrice = $settings->get('credit_price');
                    $minGroupSize = $settings->get('min_group_size');
                    $receiptMaxMb = $settings->get('receipt_max_mb');
                    $rejectMin = $settings->get('reject_reason_min_length');
                @endphp

                {{-- Kredit narxi --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        {{ __("Bir kredit narxi (UZS)") }} <span class="text-red-500">*</span>
                    </label>
                    <input type="number"
                           name="credit_price"
                           step="100"
                           min="0"
                           value="{{ old('credit_price', $creditPrice?->value ?? 175000) }}"
                           required
                           class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg">
                    <p class="text-[11px] text-gray-500 mt-1">
                        {{ __("Talaba ariza yuborganda summa avtomatik hisoblanadi: tanlangan fanlar kreditlari × shu narx") }}
                    </p>
                    @if($creditPrice?->updated_by_name)
                        <p class="text-[11px] text-gray-400 mt-0.5">
                            {{ __("Oxirgi yangilangan") }}: {{ $creditPrice->updated_by_name }} · {{ $creditPrice->updated_at->format('Y-m-d H:i') }}
                        </p>
                    @endif
                </div>

                {{-- Min guruh hajmi --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        {{ __("Guruhda minimal talabalar soni") }} <span class="text-red-500">*</span>
                    </label>
                    <input type="number"
                           name="min_group_size"
                           min="1"
                           value="{{ old('min_group_size', $minGroupSize?->value ?? 1) }}"
                           required
                           class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg">
                    <p class="text-[11px] text-gray-500 mt-1">
                        {{ __("O'quv bo'limi shundan kam talaba bilan guruh shakllantira olmaydi") }}
                    </p>
                </div>

                {{-- Kvitansiya max hajm --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        {{ __("Kvitansiya fayli max hajmi (MB)") }} <span class="text-red-500">*</span>
                    </label>
                    <input type="number"
                           name="receipt_max_mb"
                           min="1" max="50"
                           value="{{ old('receipt_max_mb', $receiptMaxMb?->value ?? 5) }}"
                           required
                           class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg">
                    <p class="text-[11px] text-gray-500 mt-1">
                        {{ __("Talaba kvitansiya yuklayotganda fayl shundan katta bo'lsa rad etiladi") }}
                    </p>
                </div>

                {{-- Rad etish sababi min --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        {{ __("Rad etish sababi minimal belgilar soni") }} <span class="text-red-500">*</span>
                    </label>
                    <input type="number"
                           name="reject_reason_min_length"
                           min="3" max="200"
                           value="{{ old('reject_reason_min_length', $rejectMin?->value ?? 10) }}"
                           required
                           class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg">
                    <p class="text-[11px] text-gray-500 mt-1">
                        {{ __("Dekan/Registrator/O'quv bo'limi rad etganda sabab shundan kam bo'lmaydi") }}
                    </p>
                </div>

                <div class="pt-4 border-t border-gray-100">
                    <button type="submit"
                            class="px-5 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        {{ __("Saqlash") }}
                    </button>
                </div>
            </form>
        </div>

        <div class="mt-4 bg-yellow-50 border border-yellow-200 rounded-xl p-4">
            <p class="text-xs text-yellow-800">
                ⚠️ {{ __("Eslatma") }}: {{ __("Kredit narxi o'zgartirilsa, eski arizalar saqlangan summa bilan qoladi (yuborilgan vaqtdagi narx). Faqat yangi arizalar yangi narx bilan hisoblanadi.") }}
            </p>
        </div>
    </div>
</x-teacher-app-layout>
