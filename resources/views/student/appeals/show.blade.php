<x-student-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-sm text-gray-800 leading-tight">
            Apellyatsiya #{{ $appeal->id }}
        </h2>
    </x-slot>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 px-3">

        @if(session('success'))
            <div class="mb-3 px-4 py-2.5 rounded-lg bg-green-50 border border-green-200 text-green-700 text-sm flex items-center gap-2">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                {{ session('success') }}
            </div>
        @endif

        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">

            {{-- Status header --}}
            @php
                $statusColors = [
                    'pending' => ['bg' => 'bg-amber-50', 'border' => 'border-amber-200', 'badge' => 'bg-amber-100 text-amber-700', 'dot' => 'bg-amber-500'],
                    'reviewing' => ['bg' => 'bg-blue-50', 'border' => 'border-blue-200', 'badge' => 'bg-blue-100 text-blue-700', 'dot' => 'bg-blue-500'],
                    'approved' => ['bg' => 'bg-emerald-50', 'border' => 'border-emerald-200', 'badge' => 'bg-emerald-100 text-emerald-700', 'dot' => 'bg-emerald-500'],
                    'rejected' => ['bg' => 'bg-red-50', 'border' => 'border-red-200', 'badge' => 'bg-red-100 text-red-700', 'dot' => 'bg-red-500'],
                ];
                $sc = $statusColors[$appeal->status] ?? $statusColors['pending'];
            @endphp
            <div class="px-4 py-3 {{ $sc['bg'] }} border-b {{ $sc['border'] }}">
                <div class="flex items-center justify-between">
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold {{ $sc['badge'] }}">
                        <span class="w-1.5 h-1.5 rounded-full {{ $sc['dot'] }} mr-1.5"></span>
                        {{ $appeal->getStatusLabel() }}
                    </span>
                    <span class="text-[11px] text-gray-500">{{ $appeal->created_at->format('d.m.Y H:i') }}</span>
                </div>
            </div>

            <div class="p-4 space-y-4">

                {{-- Fan va baho --}}
                <div class="flex items-start gap-3">
                    <div class="flex-1 min-w-0">
                        <h3 class="text-sm font-bold text-gray-900 leading-tight">{{ $appeal->subject_name }}</h3>
                        <p class="text-[11px] text-gray-500 mt-0.5">{{ $appeal->training_type_name }}</p>
                    </div>
                    <div class="flex items-center gap-2 flex-shrink-0">
                        <div class="text-center">
                            <div class="w-11 h-11 rounded-full flex items-center justify-center text-sm font-bold
                                {{ $appeal->current_grade >= 86 ? 'bg-emerald-100 text-emerald-700' : ($appeal->current_grade >= 71 ? 'bg-blue-100 text-blue-700' : ($appeal->current_grade >= 56 ? 'bg-amber-100 text-amber-700' : 'bg-red-100 text-red-700')) }}">
                                {{ $appeal->current_grade }}
                            </div>
                        </div>
                        @if($appeal->new_grade !== null)
                            <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
                            <div class="text-center">
                                <div class="w-11 h-11 rounded-full flex items-center justify-center text-sm font-bold bg-emerald-100 text-emerald-700">
                                    {{ $appeal->new_grade }}
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- O'qituvchi va sana --}}
                <div class="flex flex-wrap gap-x-4 gap-y-1">
                    @if($appeal->employee_name)
                        <div class="flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0"/></svg>
                            <span class="text-xs text-gray-600">{{ $appeal->employee_name }}</span>
                        </div>
                    @endif
                    @if($appeal->exam_date)
                        <div class="flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"/></svg>
                            <span class="text-xs text-gray-600">{{ $appeal->exam_date->format('d.m.Y') }}</span>
                        </div>
                    @endif
                </div>

                {{-- Sabab --}}
                <div>
                    <p class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-1.5">Sabab</p>
                    <div class="bg-gray-50 rounded-lg px-3 py-2.5">
                        <p class="text-[13px] text-gray-700 whitespace-pre-line leading-relaxed">{{ $appeal->reason }}</p>
                    </div>
                </div>

                {{-- Yuklangan fayl --}}
                @if($appeal->file_path)
                <div>
                    <a href="{{ route('student.appeals.download', $appeal->id) }}"
                       class="inline-flex items-center gap-2 px-3 py-2 bg-gray-50 hover:bg-gray-100 rounded-lg border border-gray-200 transition">
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m.75 12l3 3m0 0l3-3m-3 3v-6m-1.5-9H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                        </svg>
                        <span class="text-xs text-gray-600 font-medium">{{ $appeal->file_original_name }}</span>
                    </a>
                </div>
                @endif

                {{-- Ko'rib chiqish natijasi --}}
                @if($appeal->status === 'approved' || $appeal->status === 'rejected')
                <div class="rounded-lg {{ $appeal->status === 'approved' ? 'bg-emerald-50 border border-emerald-200' : 'bg-red-50 border border-red-200' }} p-3">
                    <div class="flex items-center gap-2 mb-2">
                        @if($appeal->status === 'approved')
                            <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <span class="text-xs font-bold text-emerald-700">Qabul qilindi</span>
                        @else
                            <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <span class="text-xs font-bold text-red-700">Rad etildi</span>
                        @endif
                    </div>
                    <div class="flex flex-wrap gap-x-4 gap-y-1 text-[11px] text-gray-500">
                        @if($appeal->reviewed_by_name)
                            <span>{{ $appeal->reviewed_by_name }}</span>
                        @endif
                        @if($appeal->reviewed_at)
                            <span>{{ $appeal->reviewed_at->format('d.m.Y H:i') }}</span>
                        @endif
                    </div>
                    @if($appeal->review_comment)
                        <p class="text-[13px] text-gray-700 mt-2 leading-relaxed">{{ $appeal->review_comment }}</p>
                    @endif
                </div>
                @endif

                {{-- Izohlar --}}
                @if($appeal->relationLoaded('comments'))
                <div>
                    <p class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-2">
                        Izohlar @if($appeal->comments->count() > 0)<span class="text-gray-500">({{ $appeal->comments->count() }})</span>@endif
                    </p>
                    @if($appeal->comments->count() > 0)
                        <div class="space-y-2 mb-3 max-h-80 overflow-y-auto">
                            @foreach($appeal->comments->sortBy('created_at') as $comment)
                                <div class="flex gap-2 {{ $comment->user_type === 'student' ? 'flex-row-reverse' : '' }}">
                                    <div class="flex-shrink-0 w-6 h-6 rounded-full flex items-center justify-center text-[10px] font-bold text-white {{ $comment->user_type === 'admin' ? 'bg-indigo-500' : 'bg-emerald-500' }}">
                                        {{ $comment->user_type === 'admin' ? 'A' : 'T' }}
                                    </div>
                                    <div class="max-w-[80%] {{ $comment->user_type === 'admin' ? 'bg-indigo-50 border-indigo-100' : 'bg-emerald-50 border-emerald-100' }} border rounded-lg px-3 py-2">
                                        <div class="flex items-center gap-1.5 mb-0.5">
                                            <span class="text-[10px] font-semibold {{ $comment->user_type === 'admin' ? 'text-indigo-600' : 'text-emerald-600' }}">
                                                {{ $comment->user_name }}
                                            </span>
                                            <span class="text-[10px] text-gray-400">{{ $comment->created_at->format('d.m H:i') }}</span>
                                        </div>
                                        <p class="text-[13px] text-gray-700 whitespace-pre-line leading-relaxed">{{ $comment->comment }}</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <form method="POST" action="{{ route('student.appeals.comment', $appeal->id) }}" class="flex gap-2">
                        @csrf
                        <input type="text" name="comment" required minlength="3" maxlength="1000"
                               class="flex-1 rounded-full border-gray-200 shadow-sm focus:border-indigo-400 focus:ring-indigo-400 text-sm px-4 py-2"
                               placeholder="Izoh yozing...">
                        <button type="submit"
                                class="w-9 h-9 flex items-center justify-center bg-indigo-600 text-white rounded-full hover:bg-indigo-700 transition flex-shrink-0">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                        </button>
                    </form>
                    @error('comment')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                @endif

            </div>
        </div>

        {{-- Orqaga --}}
        <div class="mt-4 mb-6">
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
