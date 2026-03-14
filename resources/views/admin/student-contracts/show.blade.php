<x-app-layout>
    <div class="p-4 sm:ml-64">
        <div class="mt-14 max-w-4xl">

            <div class="flex items-center gap-3 mb-6">
                <a href="{{ route('admin.student-contracts.index') }}" class="text-gray-500 hover:text-gray-700">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </a>
                <h1 class="text-2xl font-bold text-gray-800">Shartnoma #{{ $studentContract->id }}</h1>
                @php
                    $statusColors = [
                        'pending' => 'bg-yellow-100 text-yellow-700',
                        'registrar_review' => 'bg-blue-100 text-blue-700',
                        'approved' => 'bg-green-100 text-green-700',
                        'rejected' => 'bg-red-100 text-red-700',
                    ];
                @endphp
                <span class="inline-flex px-3 py-1 text-xs font-semibold rounded-full {{ $statusColors[$studentContract->status] ?? '' }}">
                    {{ $studentContract->status_label }}
                </span>
            </div>

            @if(session('success'))
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">{{ session('error') }}</div>
            @endif

            <div class="space-y-4">
                {{-- Talaba ma'lumotlari --}}
                <div class="bg-white rounded-lg shadow-sm p-5">
                    <h3 class="text-sm font-semibold text-gray-500 uppercase mb-3">Talaba ma'lumotlari</h3>
                    <div class="grid grid-cols-2 gap-3 text-sm">
                        <div><span class="text-gray-500">FIO:</span> <span class="font-medium">{{ $studentContract->student_full_name }}</span></div>
                        <div><span class="text-gray-500">HEMIS ID:</span> <span class="font-medium">{{ $studentContract->student_hemis_id }}</span></div>
                        <div><span class="text-gray-500">Guruh:</span> <span class="font-medium">{{ $studentContract->group_name }}</span></div>
                        <div><span class="text-gray-500">Fakultet:</span> <span class="font-medium">{{ $studentContract->department_name }}</span></div>
                        <div><span class="text-gray-500">Yo'nalish:</span> <span class="font-medium">{{ $studentContract->specialty_name }}</span></div>
                        <div><span class="text-gray-500">Kurs:</span> <span class="font-medium">{{ $studentContract->level_name }}</span></div>
                        <div><span class="text-gray-500">Shartnoma turi:</span> <span class="font-medium">{{ $studentContract->type_label }}</span></div>
                        <div><span class="text-gray-500">Mutaxassislik:</span> <span class="font-medium">{{ $studentContract->specialty_field ?? '-' }}</span></div>
                    </div>
                </div>

                {{-- Bitiruvchi rekvizitlari --}}
                <div class="bg-white rounded-lg shadow-sm p-5">
                    <h3 class="text-sm font-semibold text-gray-500 uppercase mb-3">Bitiruvchi rekvizitlari</h3>
                    <div class="grid grid-cols-2 gap-3 text-sm">
                        <div><span class="text-gray-500">Manzil:</span> <span class="font-medium">{{ $studentContract->student_address ?? '-' }}</span></div>
                        <div><span class="text-gray-500">Telefon:</span> <span class="font-medium">{{ $studentContract->student_phone ?? '-' }}</span></div>
                        <div><span class="text-gray-500">Passport:</span> <span class="font-medium">{{ $studentContract->student_passport ?? '-' }}</span></div>
                        <div><span class="text-gray-500">Bank hisob:</span> <span class="font-medium">{{ $studentContract->student_bank_account ?? '-' }}</span></div>
                        <div><span class="text-gray-500">MFO:</span> <span class="font-medium">{{ $studentContract->student_bank_mfo ?? '-' }}</span></div>
                        <div><span class="text-gray-500">INN:</span> <span class="font-medium">{{ $studentContract->student_inn ?? '-' }}</span></div>
                    </div>
                </div>

                {{-- Potensial ish beruvchi --}}
                <div class="bg-white rounded-lg shadow-sm p-5">
                    <h3 class="text-sm font-semibold text-gray-500 uppercase mb-3">Potensial ish beruvchi</h3>
                    <div class="grid grid-cols-2 gap-3 text-sm">
                        <div><span class="text-gray-500">Nomi:</span> <span class="font-medium">{{ $studentContract->employer_name ?? '-' }}</span></div>
                        <div><span class="text-gray-500">Manzil:</span> <span class="font-medium">{{ $studentContract->employer_address ?? '-' }}</span></div>
                        <div><span class="text-gray-500">Telefon:</span> <span class="font-medium">{{ $studentContract->employer_phone ?? '-' }}</span></div>
                        <div><span class="text-gray-500">Rahbar:</span> <span class="font-medium">{{ $studentContract->employer_director_name ?? '-' }}</span></div>
                        <div><span class="text-gray-500">Lavozim:</span> <span class="font-medium">{{ $studentContract->employer_director_position ?? '-' }}</span></div>
                        <div><span class="text-gray-500">Bank hisob:</span> <span class="font-medium">{{ $studentContract->employer_bank_account ?? '-' }}</span></div>
                        <div><span class="text-gray-500">MFO:</span> <span class="font-medium">{{ $studentContract->employer_bank_mfo ?? '-' }}</span></div>
                        <div><span class="text-gray-500">INN:</span> <span class="font-medium">{{ $studentContract->employer_inn ?? '-' }}</span></div>
                    </div>
                </div>

                @if($studentContract->contract_type === '4_tomonlama')
                {{-- 4-tomon --}}
                <div class="bg-white rounded-lg shadow-sm p-5">
                    <h3 class="text-sm font-semibold text-gray-500 uppercase mb-3">To'rtinchi tomon (MFY/Tuman)</h3>
                    <div class="grid grid-cols-2 gap-3 text-sm">
                        <div><span class="text-gray-500">Nomi:</span> <span class="font-medium">{{ $studentContract->fourth_party_name ?? '-' }}</span></div>
                        <div><span class="text-gray-500">Manzil:</span> <span class="font-medium">{{ $studentContract->fourth_party_address ?? '-' }}</span></div>
                        <div><span class="text-gray-500">Telefon:</span> <span class="font-medium">{{ $studentContract->fourth_party_phone ?? '-' }}</span></div>
                        <div><span class="text-gray-500">Rahbar:</span> <span class="font-medium">{{ $studentContract->fourth_party_director_name ?? '-' }}</span></div>
                    </div>
                </div>
                @endif

                {{-- Ko'rib chiqish ma'lumotlari --}}
                @if($studentContract->reviewed_at)
                <div class="bg-white rounded-lg shadow-sm p-5">
                    <h3 class="text-sm font-semibold text-gray-500 uppercase mb-3">Ko'rib chiqish</h3>
                    <div class="grid grid-cols-2 gap-3 text-sm">
                        <div><span class="text-gray-500">Ko'rib chiqqan:</span> <span class="font-medium">{{ $studentContract->reviewer?->full_name ?? '-' }}</span></div>
                        <div><span class="text-gray-500">Sana:</span> <span class="font-medium">{{ $studentContract->reviewed_at->format('d.m.Y H:i') }}</span></div>
                        @if($studentContract->reject_reason)
                        <div class="col-span-2"><span class="text-gray-500">Rad etish sababi:</span> <span class="font-medium text-red-600">{{ $studentContract->reject_reason }}</span></div>
                        @endif
                    </div>
                </div>
                @endif

                {{-- Amallar --}}
                <div class="flex gap-3">
                    @if($studentContract->status === 'approved' && $studentContract->document_path)
                        <a href="{{ route('admin.student-contracts.download', $studentContract) }}"
                           class="inline-flex items-center px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 transition">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            Hujjatni yuklab olish
                        </a>
                        <form method="POST" action="{{ route('admin.student-contracts.regenerate', $studentContract) }}">
                            @csrf
                            <button type="submit" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition">
                                Qayta yaratish
                            </button>
                        </form>
                    @endif
                    <a href="{{ route('admin.student-contracts.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-200 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-300 transition">
                        Orqaga
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
