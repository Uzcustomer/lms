<x-student-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-sm text-gray-800 leading-tight">
            Shartnoma
        </h2>
    </x-slot>

    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 px-3 py-6" x-data="{ contractType: '3_tomonlama' }">

        {{-- Shartnoma turi tanlash --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 mb-4">
            <label class="block text-sm font-semibold text-gray-700 mb-3">Shartnoma turini tanlang</label>
            <select x-model="contractType" class="w-full rounded-lg border-gray-300 text-sm focus:ring-blue-500 focus:border-blue-500">
                <option value="3_tomonlama">3 tomonlama shartnoma</option>
                <option value="4_tomonlama">4 tomonlama shartnoma</option>
            </select>
        </div>

        {{-- Talaba ma'lumotlari (placeholderlar) --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 mb-4">
            <h3 class="text-sm font-semibold text-gray-500 uppercase mb-4">Shartnomadagi ma'lumotlaringiz</h3>
            <div class="space-y-3">
                <div class="flex items-start border-b border-gray-100 pb-3">
                    <span class="text-xs font-medium text-gray-400 w-36 flex-shrink-0 pt-0.5">student_name</span>
                    <span class="text-sm font-semibold text-gray-800">{{ $placeholderData['student_name'] }}</span>
                </div>
                <div class="flex items-start border-b border-gray-100 pb-3">
                    <span class="text-xs font-medium text-gray-400 w-36 flex-shrink-0 pt-0.5">student_address</span>
                    <span class="text-sm text-gray-800">{{ $placeholderData['student_address'] ?: '—' }}</span>
                </div>
                <div class="flex items-start border-b border-gray-100 pb-3">
                    <span class="text-xs font-medium text-gray-400 w-36 flex-shrink-0 pt-0.5">specialty_name</span>
                    <span class="text-sm text-gray-800">{{ $placeholderData['specialty_name'] }}</span>
                </div>
                <div class="flex items-start border-b border-gray-100 pb-3">
                    <span class="text-xs font-medium text-gray-400 w-36 flex-shrink-0 pt-0.5">contract_year</span>
                    <span class="text-sm text-gray-800">{{ $placeholderData['contract_year'] }}</span>
                </div>
                <div class="flex items-start border-b border-gray-100 pb-3">
                    <span class="text-xs font-medium text-gray-400 w-36 flex-shrink-0 pt-0.5">student_phone</span>
                    <span class="text-sm text-gray-800">{{ $placeholderData['student_phone'] ?: '—' }}</span>
                </div>
                <div class="flex items-start border-b border-gray-100 pb-3">
                    <span class="text-xs font-medium text-gray-400 w-36 flex-shrink-0 pt-0.5">student_passport</span>
                    <span class="text-sm text-gray-800">{{ $placeholderData['student_passport'] ?: '—' }}</span>
                </div>
                <div class="flex items-start">
                    <span class="text-xs font-medium text-gray-400 w-36 flex-shrink-0 pt-0.5">student_inn</span>
                    <span class="text-sm text-gray-800">{{ $placeholderData['student_inn'] ?: '—' }}</span>
                </div>
            </div>
        </div>

        {{-- 4 tomonlama qo'shimcha ma'lumotlar --}}
        <div x-show="contractType === '4_tomonlama'" x-transition class="bg-purple-50 rounded-xl shadow-sm border border-purple-200 p-5 mb-4">
            <h3 class="text-sm font-semibold text-purple-600 uppercase mb-3">4-tomon qo'shimcha maydonlar</h3>
            <div class="space-y-2 text-sm text-purple-700">
                <div class="flex items-center gap-2">
                    <span class="text-xs font-medium text-purple-400 w-36 flex-shrink-0">fourth_party_name</span>
                    <span>MFY/tuman nomi</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-xs font-medium text-purple-400 w-36 flex-shrink-0">fourth_party_address</span>
                    <span>4-tomon manzili</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-xs font-medium text-purple-400 w-36 flex-shrink-0">fourth_party_phone</span>
                    <span>4-tomon telefon</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-xs font-medium text-purple-400 w-36 flex-shrink-0">fourth_party_director_name</span>
                    <span>4-tomon rahbar FIO</span>
                </div>
            </div>
        </div>

        {{-- Ariza yuborish tugmasi --}}
        @if($student->is_graduate)
            <div class="text-center mb-6">
                <a href="{{ route('student.contracts.create') }}"
                   class="inline-flex items-center gap-2 px-6 py-3 bg-blue-600 text-white text-sm font-semibold rounded-xl hover:bg-blue-700 transition shadow-sm">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                    </svg>
                    Shartnoma arizasi yuborish
                </a>
            </div>
        @endif

        {{-- Mavjud shartnomalar ro'yxati --}}
        @if(session('success'))
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4">
                {{ session('error') }}
            </div>
        @endif

        @if($contracts->isNotEmpty())
            <h3 class="text-lg font-bold text-gray-800 mb-3">Yuborilgan arizalar</h3>
            <div class="space-y-4">
                @foreach($contracts as $contract)
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                        <div class="p-5">
                            <div class="flex items-start justify-between mb-3">
                                <div>
                                    <h3 class="text-base font-semibold text-gray-800">{{ $contract->type_label }}</h3>
                                    <p class="text-sm text-gray-500">Ariza #{{ $contract->id }} | {{ $contract->created_at->format('d.m.Y H:i') }}</p>
                                </div>
                                @php
                                    $statusColors = [
                                        'pending' => 'bg-yellow-100 text-yellow-700 border-yellow-200',
                                        'registrar_review' => 'bg-blue-100 text-blue-700 border-blue-200',
                                        'approved' => 'bg-green-100 text-green-700 border-green-200',
                                        'rejected' => 'bg-red-100 text-red-700 border-red-200',
                                    ];
                                @endphp
                                <span class="inline-flex px-3 py-1 text-xs font-semibold rounded-full border {{ $statusColors[$contract->status] ?? '' }}">
                                    {{ $contract->status_label }}
                                </span>
                            </div>

                            @if($contract->status === 'rejected' && $contract->reject_reason)
                                <div class="bg-red-50 rounded-lg p-3 mt-2">
                                    <p class="text-sm text-red-700"><strong>Rad etish sababi:</strong> {{ $contract->reject_reason }}</p>
                                </div>
                            @endif

                            <div class="flex items-center gap-3 mt-4 pt-3 border-t border-gray-100">
                                <a href="{{ route('student.contracts.show', $contract) }}"
                                   class="inline-flex items-center px-3 py-1.5 bg-gray-100 text-gray-700 text-xs font-medium rounded-lg hover:bg-gray-200 transition">
                                    Batafsil
                                </a>
                                @if($contract->status === 'approved' && $contract->document_path)
                                    <a href="{{ route('student.contracts.download', $contract) }}"
                                       class="inline-flex items-center px-3 py-1.5 bg-green-600 text-white text-xs font-medium rounded-lg hover:bg-green-700 transition">
                                        <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                        Yuklab olish
                                    </a>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-student-app-layout>
