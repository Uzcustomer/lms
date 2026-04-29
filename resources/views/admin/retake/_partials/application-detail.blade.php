{{-- Ariza tafsiloti (dekan + registrator + o'quv bo'limi uchun umumiy)
     Parametrlar:
     - $application: RetakeApplication (with student, period, retakeGroup, logs)
     - $approveRoute, $rejectRoute (string|null) — null bo'lsa amal tugmalari yashirinadi
     - $downloadFileRoute (string) — fayl yuklab olish uchun
--}}
@php
    $hasReceipt = $application->receipt_path !== null;
    $hasDocument = $application->generated_doc_path !== null;
    $isMine = $application->dean_status?->value === 'pending'
        || $application->registrar_status?->value === 'pending';
@endphp

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

    {{-- Asosiy ma'lumotlar --}}
    <div class="lg:col-span-2 space-y-4">

        {{-- Talaba va fan --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
            <h3 class="text-sm font-semibold text-gray-700 mb-3">Talaba va fan</h3>
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-3 text-sm">
                <div>
                    <dt class="text-xs text-gray-500">Talaba</dt>
                    <dd class="font-medium text-gray-800">{{ $application->student?->full_name }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">Guruh</dt>
                    <dd class="font-medium text-gray-800">{{ $application->student?->group_name }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">Fakultet</dt>
                    <dd class="text-gray-800">{{ $application->student?->department_name }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">Yo'nalish</dt>
                    <dd class="text-gray-800">{{ $application->student?->specialty_name }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">Kurs</dt>
                    <dd class="text-gray-800">{{ $application->student?->level_name }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">Yuborilgan</dt>
                    <dd class="text-gray-800">{{ $application->submitted_at?->format('d.m.Y H:i') }}</dd>
                </div>
                <div class="sm:col-span-2 pt-3 border-t border-gray-100">
                    <dt class="text-xs text-gray-500">Fan</dt>
                    <dd class="font-semibold text-gray-800 text-base">{{ $application->subject_name }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">Semestr</dt>
                    <dd class="text-gray-800">{{ $application->semester_name }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">Kredit</dt>
                    <dd class="text-gray-800 font-semibold">{{ number_format((float) $application->credit, 2) }}</dd>
                </div>
            </dl>

            @if($application->student_note)
                <div class="mt-4 pt-3 border-t border-gray-100">
                    <dt class="text-xs text-gray-500 mb-1">Talabaning izohi</dt>
                    <dd class="text-sm text-gray-700 whitespace-pre-wrap">{{ $application->student_note }}</dd>
                </div>
            @endif
        </div>

        {{-- Fayllar --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
            <h3 class="text-sm font-semibold text-gray-700 mb-3">Hujjatlar</h3>
            <div class="space-y-2">
                @if($hasReceipt)
                    <a href="{{ route($downloadFileRoute, ['id' => $application->id, 'type' => 'receipt']) }}"
                       class="flex items-center gap-3 p-3 bg-gray-50 hover:bg-blue-50 rounded-lg border border-gray-200 hover:border-blue-300 transition">
                        <svg class="w-8 h-8 text-blue-600 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0115 18.257V17.25m6-12V15a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 15V5.25m18 0A2.25 2.25 0 0018.75 3H5.25A2.25 2.25 0 003 5.25m18 0V12a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 12V5.25"/>
                        </svg>
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-medium text-gray-800 truncate">Kvitansiya</div>
                            <div class="text-xs text-gray-500 truncate">
                                {{ $application->receipt_original_name ?? 'kvitansiya' }} —
                                {{ number_format($application->receipt_size / 1024, 1) }} KB
                            </div>
                        </div>
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                        </svg>
                    </a>
                @else
                    <div class="p-3 bg-gray-50 rounded-lg text-xs text-gray-500">Kvitansiya yuklanmagan.</div>
                @endif

                @if($hasDocument)
                    <a href="{{ route($downloadFileRoute, ['id' => $application->id, 'type' => 'document']) }}"
                       class="flex items-center gap-3 p-3 bg-gray-50 hover:bg-blue-50 rounded-lg border border-gray-200 hover:border-blue-300 transition">
                        <svg class="w-8 h-8 text-emerald-600 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
                        </svg>
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-medium text-gray-800">Avto-generatsiya qilingan ariza</div>
                            <div class="text-xs text-gray-500">DOCX format</div>
                        </div>
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                        </svg>
                    </a>
                @endif
            </div>
        </div>

        {{-- Audit log --}}
        @if($application->logs->isNotEmpty())
            <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">Tarix</h3>
                <div class="space-y-3">
                    @foreach($application->logs as $log)
                        <div class="flex items-start gap-3">
                            <div class="w-2 h-2 rounded-full bg-gray-300 mt-1.5 flex-shrink-0"></div>
                            <div class="flex-1">
                                <div class="text-xs text-gray-500">{{ $log->created_at?->format('d.m.Y H:i') }}</div>
                                <div class="text-sm text-gray-800">
                                    @php
                                        $actionLabels = [
                                            'submitted' => 'Yuborildi',
                                            'resubmitted' => 'Qayta yuborildi',
                                            'dean_approved' => 'Dekan tasdiqladi',
                                            'dean_rejected' => 'Dekan rad etdi',
                                            'registrar_approved' => 'Registrator tasdiqladi',
                                            'registrar_rejected' => 'Registrator rad etdi',
                                            'academic_dept_approved' => "O'quv bo'limi tasdiqladi",
                                            'academic_dept_rejected' => "O'quv bo'limi rad etdi",
                                            'assigned_to_group' => 'Guruhga biriktirildi',
                                        ];
                                        $actionVal = $log->action?->value ?? '';
                                    @endphp
                                    {{ $actionLabels[$actionVal] ?? $actionVal }}
                                </div>
                                @if($log->note)
                                    <div class="text-xs text-gray-600 mt-0.5 italic">"{{ $log->note }}"</div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    {{-- Yon panel: holatlar va amallar --}}
    <div class="space-y-4">

        {{-- Bosqichlar --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
            <h3 class="text-sm font-semibold text-gray-700 mb-3">Bosqichlar</h3>
            <div class="space-y-3">
                @php
                    $stages = [
                        ['Dekan', $application->dean_status?->value, $application->dean_rejection_reason, $application->dean_reviewed_at],
                        ['Registrator', $application->registrar_status?->value, $application->registrar_rejection_reason, $application->registrar_reviewed_at],
                        ["O'quv bo'limi", $application->academic_dept_status?->value, $application->academic_dept_rejection_reason, $application->academic_dept_reviewed_at],
                    ];
                @endphp
                @foreach($stages as [$name, $status, $reason, $at])
                    <div class="flex items-start gap-3">
                        <div class="flex-shrink-0 w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold
                            @if($status === 'approved') bg-emerald-100 text-emerald-700
                            @elseif($status === 'rejected') bg-red-100 text-red-700
                            @elseif($status === 'pending') bg-yellow-100 text-yellow-700
                            @else bg-gray-100 text-gray-400
                            @endif">
                            @if($status === 'approved') ✓
                            @elseif($status === 'rejected') ✕
                            @elseif($status === 'pending') …
                            @else –
                            @endif
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-medium text-gray-800">{{ $name }}</div>
                            <div class="text-xs text-gray-500">
                                @if($status === 'approved') Tasdiqlangan
                                @elseif($status === 'rejected') Rad etilgan
                                @elseif($status === 'pending') Kutilmoqda
                                @else Boshlanmagan
                                @endif
                                @if($at) — {{ $at->format('d.m.Y') }} @endif
                            </div>
                            @if($status === 'rejected' && $reason)
                                <div class="text-xs text-red-700 mt-1 whitespace-pre-wrap">{{ $reason }}</div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Tasdiqlangan guruh --}}
        @if($application->retakeGroup)
            @php $group = $application->retakeGroup; @endphp
            <div class="bg-emerald-50 rounded-xl border border-emerald-200 p-5">
                <h3 class="text-sm font-semibold text-emerald-800 mb-3">Tasdiqlangan guruh</h3>
                <dl class="space-y-2 text-sm">
                    <div>
                        <dt class="text-xs text-emerald-700">Guruh</dt>
                        <dd class="font-medium text-emerald-900">{{ $group->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-emerald-700">Sanalar</dt>
                        <dd class="text-emerald-900">{{ $group->start_date->format('d.m.Y') }} → {{ $group->end_date->format('d.m.Y') }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-emerald-700">O'qituvchi</dt>
                        <dd class="text-emerald-900">{{ $group->teacher?->full_name }}</dd>
                    </div>
                </dl>
            </div>
        @endif

        {{-- Amal tugmalari --}}
        @if($approveRoute && $rejectRoute)
            <div x-data="{ showReject: false, reason: '' }" class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">Qaroringiz</h3>

                <div x-show="!showReject" class="space-y-2">
                    <form method="POST" action="{{ route($approveRoute, $application->id) }}">
                        @csrf
                        <button type="submit"
                                onclick="return confirm('Tasdiqlashni xohlaysizmi?')"
                                class="w-full px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-lg">
                            Tasdiqlash
                        </button>
                    </form>
                    <button type="button" @click="showReject = true"
                            class="w-full px-4 py-2.5 bg-red-600 hover:bg-red-700 text-white text-sm font-semibold rounded-lg">
                        Rad etish
                    </button>
                </div>

                <form x-show="showReject" x-cloak method="POST" action="{{ route($rejectRoute, $application->id) }}" class="space-y-2">
                    @csrf
                    <textarea name="rejection_reason" x-model="reason"
                              minlength="10" maxlength="500" required rows="4"
                              class="w-full text-sm rounded-lg border-gray-300"
                              placeholder="Rad etish sababini yozing (10-500 belgi)"></textarea>
                    <div class="text-xs text-gray-500 text-right">
                        <span x-text="reason.length"></span>/500
                    </div>
                    <div class="flex gap-2">
                        <button type="button" @click="showReject = false"
                                class="flex-1 px-3 py-2 bg-white border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50">
                            Bekor
                        </button>
                        <button type="submit"
                                class="flex-1 px-3 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-semibold rounded-lg">
                            Rad etish
                        </button>
                    </div>
                </form>
            </div>
        @endif
    </div>
</div>
