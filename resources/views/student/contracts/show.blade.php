<x-student-app-layout>
    <div class="py-6">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">

            <div class="flex items-center gap-3 mb-6">
                <a href="{{ route('student.contracts.index') }}" class="text-gray-500 hover:text-gray-700">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </a>
                <h1 class="text-2xl font-bold text-gray-800">Shartnoma #{{ $contract->id }}</h1>
                @php
                    $statusColors = [
                        'pending' => 'bg-yellow-100 text-yellow-700',
                        'registrar_review' => 'bg-blue-100 text-blue-700',
                        'approved' => 'bg-green-100 text-green-700',
                        'rejected' => 'bg-red-100 text-red-700',
                    ];
                @endphp
                <span class="inline-flex px-3 py-1 text-xs font-semibold rounded-full {{ $statusColors[$contract->status] ?? '' }}">
                    {{ $contract->status_label }}
                </span>
            </div>

            <div class="space-y-4">
                {{-- Asosiy ma'lumotlar --}}
                <div class="bg-white rounded-lg shadow-sm p-5">
                    <h3 class="text-sm font-semibold text-gray-500 uppercase mb-3">Shartnoma ma'lumotlari</h3>
                    <div class="grid grid-cols-2 gap-3 text-sm">
                        <div><span class="text-gray-500">Turi:</span> <span class="font-medium">{{ $contract->type_label }}</span></div>
                        <div><span class="text-gray-500">Sana:</span> <span class="font-medium">{{ $contract->created_at->format('d.m.Y H:i') }}</span></div>
                        <div><span class="text-gray-500">Mutaxassislik:</span> <span class="font-medium">{{ $contract->specialty_field ?? '-' }}</span></div>
                        <div><span class="text-gray-500">Holat:</span> <span class="font-medium">{{ $contract->status_label }}</span></div>
                    </div>
                </div>

                {{-- Bitiruvchi rekvizitlari --}}
                <div class="bg-white rounded-lg shadow-sm p-5">
                    <h3 class="text-sm font-semibold text-blue-600 uppercase mb-3">Sizning rekvizitlaringiz</h3>
                    <div class="grid grid-cols-2 gap-3 text-sm">
                        <div><span class="text-gray-500">Manzil:</span> <span class="font-medium">{{ $contract->student_address ?? '-' }}</span></div>
                        <div><span class="text-gray-500">Telefon:</span> <span class="font-medium">{{ $contract->student_phone ?? '-' }}</span></div>
                        <div><span class="text-gray-500">Passport:</span> <span class="font-medium">{{ $contract->student_passport ?? '-' }}</span></div>
                        <div><span class="text-gray-500">Bank hisob:</span> <span class="font-medium">{{ $contract->student_bank_account ?? '-' }}</span></div>
                        <div><span class="text-gray-500">MFO:</span> <span class="font-medium">{{ $contract->student_bank_mfo ?? '-' }}</span></div>
                        <div><span class="text-gray-500">INN:</span> <span class="font-medium">{{ $contract->student_inn ?? '-' }}</span></div>
                    </div>
                </div>

                {{-- Ish beruvchi --}}
                @if($contract->employer_name)
                <div class="bg-white rounded-lg shadow-sm p-5">
                    <h3 class="text-sm font-semibold text-green-600 uppercase mb-3">Potensial ish beruvchi</h3>
                    <div class="grid grid-cols-2 gap-3 text-sm">
                        <div><span class="text-gray-500">Nomi:</span> <span class="font-medium">{{ $contract->employer_name }}</span></div>
                        <div><span class="text-gray-500">Manzil:</span> <span class="font-medium">{{ $contract->employer_address ?? '-' }}</span></div>
                        <div><span class="text-gray-500">Telefon:</span> <span class="font-medium">{{ $contract->employer_phone ?? '-' }}</span></div>
                        <div><span class="text-gray-500">Rahbar:</span> <span class="font-medium">{{ $contract->employer_director_name ?? '-' }}</span></div>
                    </div>
                </div>
                @endif

                {{-- 4-tomon --}}
                @if($contract->contract_type === '4_tomonlama' && $contract->fourth_party_name)
                <div class="bg-white rounded-lg shadow-sm p-5">
                    <h3 class="text-sm font-semibold text-purple-600 uppercase mb-3">4-tomon</h3>
                    <div class="grid grid-cols-2 gap-3 text-sm">
                        <div><span class="text-gray-500">Nomi:</span> <span class="font-medium">{{ $contract->fourth_party_name }}</span></div>
                        <div><span class="text-gray-500">Manzil:</span> <span class="font-medium">{{ $contract->fourth_party_address ?? '-' }}</span></div>
                        <div><span class="text-gray-500">Telefon:</span> <span class="font-medium">{{ $contract->fourth_party_phone ?? '-' }}</span></div>
                        <div><span class="text-gray-500">Rahbar:</span> <span class="font-medium">{{ $contract->fourth_party_director_name ?? '-' }}</span></div>
                    </div>
                </div>
                @endif

                @if($contract->status === 'rejected' && $contract->reject_reason)
                <div class="bg-red-50 rounded-lg p-4 border border-red-200">
                    <h3 class="text-sm font-semibold text-red-700 mb-1">Rad etish sababi</h3>
                    <p class="text-sm text-red-600">{{ $contract->reject_reason }}</p>
                </div>
                @endif

                {{-- Amallar --}}
                <div class="flex gap-3">
                    @if($contract->status === 'approved' && $contract->document_path)
                        <a href="{{ route('student.contracts.download', $contract) }}"
                           class="inline-flex items-center px-5 py-2.5 bg-green-600 text-white text-sm font-semibold rounded-lg hover:bg-green-700 transition">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            Shartnomani yuklab olish
                        </a>
                    @endif
                    <a href="{{ route('student.contracts.index') }}" class="inline-flex items-center px-5 py-2.5 bg-gray-200 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-300 transition">
                        Orqaga
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-student-app-layout>
