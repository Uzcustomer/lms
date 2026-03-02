<x-student-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-sm text-gray-800 leading-tight">
            Apellyatsiya arizalarim
        </h2>
    </x-slot>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 px-3">

        {{-- Yangi ariza topshirish --}}
        <div class="mb-4">
            <a href="{{ route('student.appeals.create') }}"
               class="inline-flex items-center gap-2 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl transition shadow-sm">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                Yangi apellyatsiya
            </a>
        </div>

        @if(session('success'))
            <div class="mb-4 px-4 py-3 rounded-lg bg-green-50 border border-green-200 text-green-700 text-sm">
                {{ session('success') }}
            </div>
        @endif

        @if($appeals->isEmpty())
            <div class="bg-white rounded-xl border border-gray-200 p-8 text-center">
                <div class="w-16 h-16 rounded-full bg-purple-50 flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-purple-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0-10.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285zm0 13.036h.008v.008H12v-.008z" />
                    </svg>
                </div>
                <p class="text-gray-500 text-sm">Hozircha apellyatsiya arizalari yo'q</p>
                <p class="text-gray-400 text-xs mt-1">Imtihon natijalariga e'tiroz bildirish uchun yangi ariza topshiring</p>
            </div>
        @else
            <div class="space-y-3">
                @foreach($appeals as $appeal)
                    <a href="{{ route('student.appeals.show', $appeal->id) }}"
                       class="block bg-white rounded-xl border border-gray-200 p-4 hover:shadow-md transition">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex-1 min-w-0">
                                <h4 class="text-sm font-bold text-gray-800 truncate">{{ $appeal->subject_name }}</h4>
                                <p class="text-xs text-gray-500 mt-0.5">{{ $appeal->training_type_name }}</p>
                                <div class="flex items-center gap-3 mt-2">
                                    <span class="text-xs text-gray-500">
                                        Baho: <span class="font-bold text-gray-700">{{ $appeal->current_grade }}</span>
                                    </span>
                                    @if($appeal->exam_date)
                                        <span class="text-xs text-gray-400">{{ $appeal->exam_date->format('d.m.Y') }}</span>
                                    @endif
                                </div>
                            </div>
                            <div class="flex flex-col items-end gap-1.5">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold
                                    bg-{{ $appeal->getStatusColor() }}-100 text-{{ $appeal->getStatusColor() }}-700">
                                    <svg class="mr-1 h-1.5 w-1.5 fill-current" viewBox="0 0 8 8"><circle cx="4" cy="4" r="3"/></svg>
                                    {{ $appeal->getStatusLabel() }}
                                </span>
                                <span class="text-[11px] text-gray-400">{{ $appeal->created_at->format('d.m.Y') }}</span>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>

            <div class="mt-4">
                {{ $appeals->links() }}
            </div>
        @endif
    </div>
</x-student-app-layout>
