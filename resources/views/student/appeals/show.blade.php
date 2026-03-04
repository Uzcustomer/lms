<x-student-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-sm text-gray-800 leading-tight">
            Apellyatsiya #{{ $appeal->id }}
        </h2>
    </x-slot>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 px-3">

        @if(session('success'))
            <div class="mb-4 px-4 py-3 rounded-lg bg-green-50 border border-green-200 text-green-700 text-sm">
                {{ session('success') }}
            </div>
        @endif

        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">

            {{-- Status header --}}
            <div class="px-4 py-3 bg-{{ $appeal->getStatusColor() }}-50 border-b border-{{ $appeal->getStatusColor() }}-100">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold
                            bg-{{ $appeal->getStatusColor() }}-100 text-{{ $appeal->getStatusColor() }}-700">
                            <svg class="mr-1.5 h-2 w-2 fill-current" viewBox="0 0 8 8"><circle cx="4" cy="4" r="3"/></svg>
                            {{ $appeal->getStatusLabel() }}
                        </span>
                    </div>
                    <span class="text-xs text-gray-500">{{ $appeal->created_at->format('d.m.Y H:i') }}</span>
                </div>
            </div>

            <div class="p-4 space-y-4">
                {{-- Imtihon ma'lumotlari --}}
                <div>
                    <h3 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Imtihon ma'lumotlari</h3>
                    <div class="bg-gray-50 rounded-lg p-3 space-y-2">
                        <div class="flex justify-between">
                            <span class="text-xs text-gray-500">Fan</span>
                            <span class="text-sm font-semibold text-gray-800">{{ $appeal->subject_name }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-xs text-gray-500">Nazorat turi</span>
                            <span class="text-sm text-gray-700">{{ $appeal->training_type_name }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-xs text-gray-500">Joriy baho</span>
                            <span class="text-sm font-bold text-gray-800">{{ $appeal->current_grade }} ball</span>
                        </div>
                        @if($appeal->employee_name)
                        <div class="flex justify-between">
                            <span class="text-xs text-gray-500">O'qituvchi</span>
                            <span class="text-sm text-gray-700">{{ $appeal->employee_name }}</span>
                        </div>
                        @endif
                        @if($appeal->exam_date)
                        <div class="flex justify-between">
                            <span class="text-xs text-gray-500">Sana</span>
                            <span class="text-sm text-gray-700">{{ $appeal->exam_date->format('d.m.Y') }}</span>
                        </div>
                        @endif
                    </div>
                </div>

                {{-- Apellyatsiya sababi --}}
                <div>
                    <h3 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Apellyatsiya sababi</h3>
                    <div class="bg-gray-50 rounded-lg p-3">
                        <p class="text-sm text-gray-700 whitespace-pre-line">{{ $appeal->reason }}</p>
                    </div>
                </div>

                {{-- Yuklangan fayl --}}
                @if($appeal->file_path)
                <div>
                    <h3 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Yuklangan hujjat</h3>
                    <a href="{{ route('student.appeals.download', $appeal->id) }}"
                       class="inline-flex items-center gap-2 px-3 py-2 bg-gray-50 hover:bg-gray-100 rounded-lg border border-gray-200 transition">
                        <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m.75 12l3 3m0 0l3-3m-3 3v-6m-1.5-9H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                        </svg>
                        <span class="text-sm text-gray-700 font-medium">{{ $appeal->file_original_name }}</span>
                    </a>
                </div>
                @endif

                {{-- Izohlar --}}
                <div>
                    <h3 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Izohlar ({{ $appeal->comments->count() }})</h3>
                    @if($appeal->comments->count() > 0)
                        <div class="space-y-2 mb-3 max-h-80 overflow-y-auto">
                            @foreach($appeal->comments->sortBy('created_at') as $comment)
                                <div class="flex gap-2 {{ $comment->user_type === 'student' ? 'flex-row-reverse' : '' }}">
                                    <div class="flex-shrink-0 w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold text-white {{ $comment->user_type === 'admin' ? 'bg-indigo-600' : 'bg-green-600' }}">
                                        {{ $comment->user_type === 'admin' ? 'A' : 'T' }}
                                    </div>
                                    <div class="max-w-[80%] {{ $comment->user_type === 'admin' ? 'bg-indigo-50 border-indigo-100' : 'bg-green-50 border-green-100' }} border rounded-lg p-2.5">
                                        <div class="flex items-center gap-2 mb-0.5">
                                            <span class="text-xs font-bold {{ $comment->user_type === 'admin' ? 'text-indigo-700' : 'text-green-700' }}">
                                                {{ $comment->user_name }}
                                            </span>
                                            <span class="text-xs text-gray-400">{{ $comment->created_at->format('d.m.Y H:i') }}</span>
                                        </div>
                                        <p class="text-sm text-gray-700 whitespace-pre-line">{{ $comment->comment }}</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-xs text-gray-400 mb-3">Hali izoh yo'q.</p>
                    @endif

                    <form method="POST" action="{{ route('student.appeals.comment', $appeal->id) }}" class="flex gap-2">
                        @csrf
                        <input type="text" name="comment" required minlength="3" maxlength="1000"
                               class="flex-1 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                               placeholder="Izoh yozing...">
                        <button type="submit"
                                class="inline-flex items-center px-3 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-lg hover:bg-indigo-700 transition flex-shrink-0">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                        </button>
                    </form>
                    @error('comment')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Ko'rib chiqish natijasi --}}
                @if($appeal->status === 'approved' || $appeal->status === 'rejected')
                <div>
                    <h3 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Ko'rib chiqish natijasi</h3>
                    <div class="bg-{{ $appeal->getStatusColor() }}-50 rounded-lg p-3 border border-{{ $appeal->getStatusColor() }}-100 space-y-2">
                        @if($appeal->reviewed_by_name)
                        <div class="flex justify-between">
                            <span class="text-xs text-gray-500">Ko'rib chiqqan</span>
                            <span class="text-sm text-gray-700">{{ $appeal->reviewed_by_name }}</span>
                        </div>
                        @endif
                        @if($appeal->reviewed_at)
                        <div class="flex justify-between">
                            <span class="text-xs text-gray-500">Sana</span>
                            <span class="text-sm text-gray-700">{{ $appeal->reviewed_at->format('d.m.Y H:i') }}</span>
                        </div>
                        @endif
                        @if($appeal->new_grade !== null)
                        <div class="flex justify-between">
                            <span class="text-xs text-gray-500">Yangi baho</span>
                            <span class="text-sm font-bold text-green-700">{{ $appeal->new_grade }} ball</span>
                        </div>
                        @endif
                        @if($appeal->review_comment)
                        <div class="mt-2 pt-2 border-t border-{{ $appeal->getStatusColor() }}-200">
                            <p class="text-xs text-gray-500 mb-1">Izoh:</p>
                            <p class="text-sm text-gray-700">{{ $appeal->review_comment }}</p>
                        </div>
                        @endif
                    </div>
                </div>
                @endif
            </div>
        </div>

        {{-- Orqaga --}}
        <div class="mt-4">
            <a href="{{ route('student.appeals.index') }}"
               class="inline-flex items-center gap-1 text-sm text-indigo-600 hover:text-indigo-800 font-medium">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                </svg>
                Barcha arizalar
            </a>
        </div>
    </div>
</x-student-app-layout>
