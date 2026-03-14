<x-student-app-layout>
    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">

            <div class="flex items-center justify-between mb-6">
                <h1 class="text-2xl font-bold text-gray-800">Bitiruvchi shartnomalar</h1>
                @if($student->is_graduate)
                    <a href="{{ route('student.contracts.create') }}"
                       class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-semibold rounded-lg hover:bg-blue-700 transition shadow-sm">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Yangi ariza
                    </a>
                @endif
            </div>

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

            @if(!$student->is_graduate)
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6 text-center">
                    <svg class="w-12 h-12 mx-auto mb-3 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                    </svg>
                    <p class="text-yellow-700 font-medium">Shartnoma xizmati faqat bitiruvchi kurs talabalari uchun mavjud.</p>
                </div>
            @elseif($contracts->isEmpty())
                <div class="bg-white rounded-lg shadow-sm p-12 text-center">
                    <svg class="w-16 h-16 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <h3 class="text-lg font-semibold text-gray-700 mb-2">Shartnoma mavjud emas</h3>
                    <p class="text-gray-500 mb-4">Ish bilan ta'minlash shartnomasi uchun ariza yuborishingiz mumkin.</p>
                    <a href="{{ route('student.contracts.create') }}"
                       class="inline-flex items-center px-6 py-2.5 bg-blue-600 text-white text-sm font-semibold rounded-lg hover:bg-blue-700 transition">
                        Ariza yuborish
                    </a>
                </div>
            @else
                <div class="space-y-4">
                    @foreach($contracts as $contract)
                        <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                            <div class="p-5">
                                <div class="flex items-start justify-between mb-3">
                                    <div>
                                        <h3 class="text-lg font-semibold text-gray-800">{{ $contract->type_label }}</h3>
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

                                <div class="grid grid-cols-2 gap-2 text-sm text-gray-600 mb-3">
                                    <div>Mutaxassislik: <span class="font-medium text-gray-800">{{ $contract->specialty_field ?? $contract->specialty_name }}</span></div>
                                    <div>Manzil: <span class="font-medium text-gray-800">{{ $contract->student_address }}</span></div>
                                </div>

                                @if($contract->employer_name)
                                    <div class="text-sm text-gray-600 mb-3">
                                        Ish beruvchi: <span class="font-medium text-gray-800">{{ $contract->employer_name }}</span>
                                    </div>
                                @endif

                                @if($contract->status === 'rejected' && $contract->reject_reason)
                                    <div class="bg-red-50 rounded-lg p-3 mt-3">
                                        <p class="text-sm text-red-700"><strong>Rad etish sababi:</strong> {{ $contract->reject_reason }}</p>
                                    </div>
                                @endif

                                <div class="flex items-center gap-3 mt-4 pt-3 border-t border-gray-100">
                                    <a href="{{ route('student.contracts.show', $contract) }}"
                                       class="inline-flex items-center px-3 py-1.5 bg-gray-100 text-gray-700 text-xs font-medium rounded-lg hover:bg-gray-200 transition">
                                        Batafsil ko'rish
                                    </a>
                                    @if($contract->status === 'approved' && $contract->document_path)
                                        <a href="{{ route('student.contracts.download', $contract) }}"
                                           class="inline-flex items-center px-3 py-1.5 bg-green-600 text-white text-xs font-medium rounded-lg hover:bg-green-700 transition">
                                            <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                            Shartnomani yuklab olish
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-student-app-layout>
