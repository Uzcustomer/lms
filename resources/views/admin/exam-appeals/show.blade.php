<x-app-layout>
    <div class="p-4 sm:ml-64">
        <div class="mt-14">

            {{-- Header --}}
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between mb-6 gap-3">
                <div>
                    <a href="{{ route('admin.exam-appeals.index') }}"
                       class="inline-flex items-center text-sm text-gray-500 hover:text-indigo-600 dark:text-gray-400 dark:hover:text-indigo-400 transition mb-1">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                        Barcha apellyatsiyalar
                    </a>
                    <h1 class="text-2xl font-bold text-gray-800 dark:text-white">Apellyatsiya #{{ $appeal->id }}</h1>
                </div>
                <span class="px-4 py-1.5 inline-flex items-center text-sm font-semibold rounded-full
                    @if($appeal->status === 'pending') bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300
                    @elseif($appeal->status === 'reviewing') bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300
                    @elseif($appeal->status === 'approved') bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300
                    @else bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300 @endif">
                    @if($appeal->status === 'pending')
                        <span class="w-2 h-2 rounded-full bg-yellow-500 mr-2 animate-pulse"></span>
                    @elseif($appeal->status === 'reviewing')
                        <span class="w-2 h-2 rounded-full bg-blue-500 mr-2 animate-pulse"></span>
                    @elseif($appeal->status === 'approved')
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    @else
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    @endif
                    {{ $appeal->getStatusLabel() }}
                </span>
            </div>

            @if(session('success'))
                <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-700 dark:text-green-300 px-4 py-3 rounded-lg mb-5 flex items-center">
                    <svg class="w-5 h-5 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    {{ session('success') }}
                </div>
            @endif

            {{-- ═══════ 1-QATOR: Talaba | Imtihon | Baho ═══════ --}}
            <div style="display: flex; gap: 10px; margin-bottom: 20px;">

                {{-- Talaba ma'lumotlari --}}
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden" style="flex: 1;">
                    <div class="px-4 h-12 flex items-center rounded-t-xl" style="background-color: #1e40af;">
                        <svg class="w-5 h-5 mr-2 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        <h3 class="text-base font-bold text-white">Talaba ma'lumotlari</h3>
                    </div>
                    <div class="p-4">
                        <dl class="space-y-2">
                            <div class="flex justify-between">
                                <dt class="text-sm text-gray-500 dark:text-gray-400">F.I.O</dt>
                                <dd class="text-sm font-bold text-gray-900 dark:text-white text-right max-w-[60%]">{{ $appeal->student->full_name ?? '-' }}</dd>
                            </div>
                            <div class="flex justify-between border-t border-gray-100 dark:border-gray-700 pt-2">
                                <dt class="text-sm text-gray-500 dark:text-gray-400">Talaba ID</dt>
                                <dd class="text-sm font-bold text-gray-900 dark:text-white font-mono">{{ $appeal->student->student_id_number ?? '-' }}</dd>
                            </div>
                            <div class="flex justify-between border-t border-gray-100 dark:border-gray-700 pt-2">
                                <dt class="text-sm text-gray-500 dark:text-gray-400">Guruh</dt>
                                <dd class="text-sm font-bold text-gray-900 dark:text-white">{{ $appeal->student->group_name ?? '-' }}</dd>
                            </div>
                            <div class="flex justify-between border-t border-gray-100 dark:border-gray-700 pt-2">
                                <dt class="text-sm text-gray-500 dark:text-gray-400">Topshirilgan</dt>
                                <dd class="text-sm font-bold text-gray-900 dark:text-white">{{ $appeal->created_at->format('d.m.Y H:i') }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>

                {{-- Imtihon ma'lumotlari --}}
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden" style="flex: 1;">
                    <div class="px-4 h-12 flex items-center rounded-t-xl" style="background-color: #1e40af;">
                        <svg class="w-5 h-5 mr-2 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        <h3 class="text-base font-bold text-white">Imtihon ma'lumotlari</h3>
                    </div>
                    <div class="p-4">
                        <dl class="space-y-2">
                            <div class="flex justify-between items-start">
                                <dt class="text-sm text-gray-500 dark:text-gray-400">Fan nomi</dt>
                                <dd class="text-sm font-bold text-gray-900 dark:text-white text-right max-w-[60%]">{{ $appeal->subject_name }}</dd>
                            </div>
                            <div class="flex justify-between border-t border-gray-100 dark:border-gray-700 pt-2">
                                <dt class="text-sm text-gray-500 dark:text-gray-400">Nazorat turi</dt>
                                <dd>
                                    <span class="inline-flex px-2.5 py-0.5 text-sm font-bold rounded-full bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300">
                                        {{ $appeal->training_type_name }}
                                    </span>
                                </dd>
                            </div>
                            <div class="flex justify-between border-t border-gray-100 dark:border-gray-700 pt-2">
                                <dt class="text-sm text-gray-500 dark:text-gray-400">O'qituvchi</dt>
                                <dd class="text-sm font-bold text-gray-900 dark:text-white">{{ $appeal->employee_name ?? '-' }}</dd>
                            </div>
                            @if($appeal->exam_date)
                            <div class="flex justify-between border-t border-gray-100 dark:border-gray-700 pt-2">
                                <dt class="text-sm text-gray-500 dark:text-gray-400">Imtihon sanasi</dt>
                                <dd class="text-sm font-bold text-gray-900 dark:text-white">{{ \Carbon\Carbon::parse($appeal->exam_date)->format('d.m.Y') }}</dd>
                            </div>
                            @endif
                        </dl>
                    </div>
                </div>

                {{-- Baho --}}
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden" style="flex: 1;">
                    <div class="px-4 h-12 flex items-center rounded-t-xl" style="background-color: #1e40af;">
                        <svg class="w-5 h-5 mr-2 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
                        <h3 class="text-base font-bold text-white">Baho</h3>
                    </div>
                    <div class="p-4 text-center">
                        <div class="mb-3">
                            <span class="inline-flex items-center justify-center w-20 h-20 rounded-full text-2xl font-bold
                                {{ $appeal->current_grade >= 86 ? 'bg-green-100 text-green-700' : ($appeal->current_grade >= 71 ? 'bg-blue-100 text-blue-700' : ($appeal->current_grade >= 56 ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700')) }}">
                                {{ $appeal->current_grade }}
                            </span>
                            <p class="text-xs text-gray-400 mt-1">joriy baho</p>
                        </div>
                        @if($appeal->new_grade !== null)
                            <div class="flex items-center justify-center gap-2">
                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                                <span class="inline-flex items-center justify-center w-20 h-20 rounded-full text-2xl font-bold bg-green-100 text-green-700">
                                    {{ $appeal->new_grade }}
                                </span>
                            </div>
                            <p class="text-xs text-gray-400 mt-1">yangi baho</p>
                        @endif
                    </div>
                </div>
            </div>

            {{-- ═══════ 2-QATOR: Sabab + Ko'rib chiqish / Fayl ═══════ --}}
            <div style="display: flex; gap: 10px; margin-bottom: 20px;">

                {{-- Apellyatsiya sababi --}}
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden" style="flex: 1;">
                    <div class="px-4 h-12 flex items-center rounded-t-xl" style="background-color: #1e40af;">
                        <svg class="w-5 h-5 mr-2 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/></svg>
                        <h3 class="text-base font-bold text-white">Apellyatsiya sababi</h3>
                    </div>
                    <div class="p-4">
                        <p class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-line leading-relaxed">{{ $appeal->reason }}</p>

                        @if($appeal->file_path)
                            <div class="mt-4 pt-3 border-t border-gray-100 dark:border-gray-700">
                                <p class="text-sm font-bold text-gray-500 dark:text-gray-400 mb-2">Qo'shimcha hujjat</p>
                                @php
                                    $ext = strtolower(pathinfo($appeal->file_original_name, PATHINFO_EXTENSION));
                                    $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                                @endphp
                                @if($isImage)
                                    <div class="rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 mb-3">
                                        <img src="{{ asset('storage/' . $appeal->file_path) }}" alt="Qo'shimcha hujjat"
                                             class="w-full h-auto object-contain" style="max-height: 180px;">
                                    </div>
                                @else
                                    <div class="flex items-center gap-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg p-3 mb-3">
                                        <div class="w-10 h-10 rounded-lg bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center flex-shrink-0">
                                            <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                        </div>
                                        <div class="min-w-0">
                                            <p class="text-sm font-bold text-gray-900 dark:text-white truncate">{{ $appeal->file_original_name }}</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase">{{ $ext }} fayl</p>
                                        </div>
                                    </div>
                                @endif
                                <div class="flex gap-3">
                                    <a href="{{ route('admin.exam-appeals.download', $appeal->id) }}" target="_blank"
                                       class="flex-1 inline-flex items-center justify-center px-4 py-2 bg-blue-600 text-white text-sm font-semibold rounded-lg hover:bg-blue-700 transition">
                                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                        Ko'rish
                                    </a>
                                    <a href="{{ route('admin.exam-appeals.download', $appeal->id) }}" download
                                       class="flex-1 inline-flex items-center justify-center px-4 py-2 bg-green-600 text-white text-sm font-semibold rounded-lg hover:bg-green-700 transition">
                                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                        Yuklab olish
                                    </a>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Ko'rib chiqish natijasi yoki Qo'shimcha ma'lumot --}}
                @if(in_array($appeal->status, ['approved', 'rejected']))
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden" style="flex: 1;">
                        <div class="px-4 h-12 flex items-center rounded-t-xl" style="background-color: #1e40af;">
                            <svg class="w-5 h-5 mr-2 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                            <h3 class="text-base font-bold text-white">Ko'rib chiqish natijasi</h3>
                        </div>
                        <div class="p-4">
                            <dl class="space-y-2">
                                @if($appeal->reviewed_by_name)
                                <div class="flex justify-between">
                                    <dt class="text-sm text-gray-500 dark:text-gray-400">Ko'rib chiqqan</dt>
                                    <dd class="text-sm font-bold text-gray-900 dark:text-white">{{ $appeal->reviewed_by_name }}</dd>
                                </div>
                                @endif
                                @if($appeal->reviewed_at)
                                <div class="flex justify-between border-t border-gray-100 dark:border-gray-700 pt-2">
                                    <dt class="text-sm text-gray-500 dark:text-gray-400">Sana</dt>
                                    <dd class="text-sm font-bold text-gray-900 dark:text-white">{{ $appeal->reviewed_at->format('d.m.Y H:i') }}</dd>
                                </div>
                                @endif
                                <div class="flex justify-between border-t border-gray-100 dark:border-gray-700 pt-2">
                                    <dt class="text-sm text-gray-500 dark:text-gray-400">Natija</dt>
                                    <dd>
                                        <span class="inline-flex items-center px-2.5 py-0.5 text-xs font-bold rounded-full
                                            {{ $appeal->status === 'approved' ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300' : 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300' }}">
                                            {{ $appeal->getStatusLabel() }}
                                        </span>
                                    </dd>
                                </div>
                            </dl>
                            @if($appeal->status === 'rejected' && $appeal->review_comment)
                                <div class="mt-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-3">
                                    <p class="text-sm font-bold text-red-600 dark:text-red-400 mb-1">Rad etish sababi:</p>
                                    <p class="text-sm text-red-700 dark:text-red-300">{{ $appeal->review_comment }}</p>
                                </div>
                            @elseif($appeal->status === 'approved' && $appeal->review_comment)
                                <div class="mt-3 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-3">
                                    <p class="text-sm font-bold text-green-600 dark:text-green-400 mb-1">Izoh:</p>
                                    <p class="text-sm text-green-700 dark:text-green-300">{{ $appeal->review_comment }}</p>
                                </div>
                            @endif
                        </div>
                    </div>
                @else
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden" style="flex: 1;">
                        <div class="px-4 h-12 flex items-center rounded-t-xl" style="background-color: #1e40af;">
                            <svg class="w-5 h-5 mr-2 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <h3 class="text-base font-bold text-white">Qo'shimcha ma'lumot</h3>
                        </div>
                        <div class="p-4">
                            <dl class="space-y-2">
                                <div class="flex justify-between">
                                    <dt class="text-sm text-gray-500 dark:text-gray-400">Ariza raqami</dt>
                                    <dd class="text-sm font-bold text-gray-900 dark:text-white">#{{ $appeal->id }}</dd>
                                </div>
                                <div class="flex justify-between border-t border-gray-100 dark:border-gray-700 pt-2">
                                    <dt class="text-sm text-gray-500 dark:text-gray-400">Holat</dt>
                                    <dd>
                                        <span class="inline-flex items-center px-2.5 py-0.5 text-xs font-bold rounded-full
                                            {{ $appeal->status === 'reviewing' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300' : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300' }}">
                                            @if($appeal->status === 'pending')
                                                <span class="w-1.5 h-1.5 rounded-full bg-yellow-500 mr-1.5 animate-pulse"></span>
                                            @else
                                                <span class="w-1.5 h-1.5 rounded-full bg-blue-500 mr-1.5 animate-pulse"></span>
                                            @endif
                                            {{ $appeal->getStatusLabel() }}
                                        </span>
                                    </dd>
                                </div>
                                <div class="flex justify-between border-t border-gray-100 dark:border-gray-700 pt-2">
                                    <dt class="text-sm text-gray-500 dark:text-gray-400">Fayl biriktirilgan</dt>
                                    <dd class="text-sm font-bold text-gray-900 dark:text-white">{{ $appeal->file_path ? 'Ha' : 'Yo\'q' }}</dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                @endif
            </div>

            {{-- ═══════ Izohlar ═══════ --}}
            @if($appeal->relationLoaded('comments'))
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden mb-5" style="width: 50%;">
                <div class="px-4 h-12 flex items-center rounded-t-xl" style="background-color: #1e40af;">
                    <svg class="w-5 h-5 mr-2 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                    <h3 class="text-base font-bold text-white">Izohlar ({{ $appeal->comments->count() }})</h3>
                </div>
                <div class="p-4">
                    @if($appeal->comments->count() > 0)
                        <div class="space-y-3 mb-4 max-h-96 overflow-y-auto">
                            @foreach($appeal->comments->sortBy('created_at') as $comment)
                                <div class="flex gap-3 {{ $comment->user_type === 'admin' ? '' : 'flex-row-reverse' }}">
                                    <div class="flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold text-white {{ $comment->user_type === 'admin' ? 'bg-indigo-600' : 'bg-green-600' }}">
                                        {{ $comment->user_type === 'admin' ? 'A' : 'T' }}
                                    </div>
                                    <div class="max-w-[75%] {{ $comment->user_type === 'admin' ? 'bg-indigo-50 dark:bg-indigo-900/20 border-indigo-200 dark:border-indigo-800' : 'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-800' }} border rounded-lg p-3">
                                        <div class="flex items-center gap-2 mb-1">
                                            <span class="text-xs font-bold {{ $comment->user_type === 'admin' ? 'text-indigo-700 dark:text-indigo-300' : 'text-green-700 dark:text-green-300' }}">
                                                {{ $comment->user_name }}
                                            </span>
                                            <span class="text-xs text-gray-400">{{ $comment->created_at->format('d.m.Y H:i') }}</span>
                                        </div>
                                        <p class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-line">{{ $comment->comment }}</p>
                                        @if($comment->file_path)
                                            <a href="{{ route('admin.exam-appeals.comment.download', $comment->id) }}"
                                               class="inline-flex items-center gap-1 mt-1.5 text-xs text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300 bg-indigo-50 dark:bg-indigo-900/30 px-2 py-1 rounded">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                                {{ $comment->file_original_name }}
                                            </a>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-gray-400 dark:text-gray-500 mb-4">Hali izoh yo'q.</p>
                    @endif

                    {{-- Izoh yozish formasi --}}
                    <form method="POST" action="{{ route('admin.exam-appeals.comment', $appeal->id) }}" class="flex gap-2"
                          onsubmit="var v=document.getElementById('appeal-comment-input').value.trim(); if(v.length<3){alert('Izoh kamida 3 ta belgi bo\'lishi kerak');return false;}">
                        @csrf
                        <input type="text" name="comment" id="appeal-comment-input" minlength="3" maxlength="1000"
                               class="flex-1 rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                               placeholder="Izoh yozing...">
                        <button type="submit"
                                class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-lg hover:bg-indigo-700 transition flex-shrink-0">
                            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                            Yuborish
                        </button>
                    </form>
                    @error('comment')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>
            @endif

            {{-- ═══════ Tasdiqlash / Rad etish ═══════ --}}
            @if(in_array($appeal->status, ['pending', 'reviewing']))
                <div class="flex justify-end mb-6" x-data="{ showReject: {{ $errors->has('review_comment') ? 'true' : 'false' }} }">
                    <div class="flex flex-col items-end gap-3">
                        <div class="flex gap-3">
                            <form method="POST" action="{{ route('admin.exam-appeals.approve', $appeal->id) }}" id="approveForm"
                                  onsubmit="return handleApproveSubmit()">
                                @csrf
                                <input type="hidden" name="comment_text" id="approveCommentText">
                                <button type="submit"
                                        class="inline-flex items-center px-5 py-2 bg-green-600 text-white text-sm font-semibold rounded-lg hover:bg-green-700 transition">
                                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    Qabul qilish
                                </button>
                            </form>
                            <button @click="showReject = !showReject"
                                    class="inline-flex items-center px-5 py-2 bg-red-600 text-white text-sm font-semibold rounded-lg hover:bg-red-700 transition">
                                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                Rad etish
                            </button>
                        </div>
                        <div x-show="showReject" x-transition class="w-full max-w-md">
                            <form method="POST" action="{{ route('admin.exam-appeals.reject', $appeal->id) }}" id="rejectForm"
                                  onsubmit="return handleRejectSubmit()"
                                  class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                                @csrf
                                <input type="hidden" name="comment_text" id="rejectCommentText">
                                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Rad etish sababi</label>
                                <textarea name="review_comment" id="rejectReviewComment" rows="3" maxlength="1000"
                                          class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-red-500 focus:ring-red-500 text-sm mb-2"
                                          placeholder="Nima uchun rad etilyapti..."></textarea>
                                <p id="rejectError" class="mb-2 text-sm text-red-600 hidden"></p>
                                @error('review_comment')
                                    <p class="mb-2 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                                <div class="flex justify-end">
                                    <button type="submit"
                                            class="px-4 py-2 bg-red-700 text-white text-sm font-semibold rounded-lg hover:bg-red-800 transition">
                                        Rad etishni tasdiqlash
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <script>
                    function handleApproveSubmit() {
                        var input = document.getElementById('appeal-comment-input');
                        if (input) {
                            document.getElementById('approveCommentText').value = input.value;
                        }
                        return confirm('Apellyatsiyani qabul qilmoqchimisiz?');
                    }
                    function handleRejectSubmit() {
                        var textarea = document.getElementById('rejectReviewComment');
                        var errorEl = document.getElementById('rejectError');
                        var value = textarea.value.trim();

                        errorEl.classList.add('hidden');
                        errorEl.textContent = '';

                        if (value.length < 5) {
                            errorEl.textContent = 'Rad etish sababini yozing (kamida 5 ta belgi).';
                            errorEl.classList.remove('hidden');
                            textarea.focus();
                            return false;
                        }

                        var input = document.getElementById('appeal-comment-input');
                        if (input) {
                            document.getElementById('rejectCommentText').value = input.value;
                        }
                        return true;
                    }
                </script>
            @endif

        </div>
    </div>
</x-app-layout>
