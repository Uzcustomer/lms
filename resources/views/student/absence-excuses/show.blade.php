<x-student-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-2">
            <a href="{{ route('student.absence-excuses.index') }}" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5"/></svg>
            </a>
            <h2 class="font-semibold text-sm text-gray-800 leading-tight">
                Ariza #{{ $excuse->id }}
            </h2>
        </div>
    </x-slot>

    @php
        $statusStyles = [
            'yellow' => ['bg' => '#fffbeb', 'border' => '#fde68a', 'accent' => '#f59e0b', 'text' => '#92400e', 'icon_bg' => 'bg-yellow-100'],
            'green'  => ['bg' => '#ecfdf5', 'border' => '#a7f3d0', 'accent' => '#10b981', 'text' => '#065f46', 'icon_bg' => 'bg-green-100'],
            'red'    => ['bg' => '#fef2f2', 'border' => '#fecaca', 'accent' => '#ef4444', 'text' => '#991b1b', 'icon_bg' => 'bg-red-100'],
            'blue'   => ['bg' => '#eff6ff', 'border' => '#bfdbfe', 'accent' => '#3b82f6', 'text' => '#1e40af', 'icon_bg' => 'bg-blue-100'],
            'gray'   => ['bg' => '#f9fafb', 'border' => '#e5e7eb', 'accent' => '#6b7280', 'text' => '#374151', 'icon_bg' => 'bg-gray-100'],
        ];
        $s = $statusStyles[$excuse->status_color] ?? $statusStyles['gray'];

        $typeStyles = [
            'jn'   => ['accent' => '#3b82f6', 'bg' => '#eff6ff', 'border' => '#bfdbfe', 'text' => '#1e40af', 'badge' => 'bg-blue-100 text-blue-700', 'icon' => 'M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25z'],
            'mt'   => ['accent' => '#a855f7', 'bg' => '#faf5ff', 'border' => '#e9d5ff', 'text' => '#7e22ce', 'badge' => 'bg-purple-100 text-purple-700', 'icon' => 'M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25'],
            'oski' => ['accent' => '#f97316', 'bg' => '#fff7ed', 'border' => '#fed7aa', 'text' => '#c2410c', 'badge' => 'bg-orange-100 text-orange-700', 'icon' => 'M4.26 10.147a60.438 60.438 0 00-.491 6.347A48.627 48.627 0 0112 20.904a48.627 48.627 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.636 50.636 0 00-2.658-.813A59.906 59.906 0 0112 3.493a59.903 59.903 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0112 13.489a50.702 50.702 0 017.74-3.342M6.75 15a.75.75 0 100-1.5.75.75 0 000 1.5zm0 0v-3.675A55.378 55.378 0 0112 8.443m-7.007 11.55A5.981 5.981 0 006.75 15.75v-1.5'],
            'test' => ['accent' => '#ef4444', 'bg' => '#fef2f2', 'border' => '#fecaca', 'text' => '#991b1b', 'badge' => 'bg-red-100 text-red-700', 'icon' => 'M11.35 3.836c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m8.9-4.414c.376.023.75.05 1.124.08 1.131.094 1.976 1.057 1.976 2.192V16.5A2.25 2.25 0 0118 18.75h-2.25m-7.5-10.5H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V18.75m-7.5-10.5h6.375c.621 0 1.125.504 1.125 1.125v9.375m-8.25-3l1.5 1.5 3-3.75'],
        ];
    @endphp

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 px-3 pb-6">

        {{-- Status banner --}}
        <div class="rounded-xl border overflow-hidden mb-3" style="background:{{ $s['bg'] }};border-color:{{ $s['border'] }};">
            <div class="flex items-center gap-3" style="padding:12px 14px;">
                <div class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0" style="background:{{ $s['accent'] }};">
                    @if($excuse->isApproved())
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                    @elseif($excuse->isRejected())
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    @else
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    @endif
                </div>
                <div class="flex-1 min-w-0">
                    <h3 class="text-sm font-bold" style="color:{{ $s['text'] }};">{{ $excuse->status_label }}</h3>
                    <p class="text-[11px] mt-0.5" style="color:{{ $s['text'] }};opacity:0.7;">Ariza #{{ $excuse->id }} &bull; {{ $excuse->created_at->format('d.m.Y H:i') }}</p>
                </div>
            </div>
        </div>

        {{-- Ma'lumotlar --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm mb-3 overflow-hidden">
            {{-- Sabab --}}
            <div class="flex items-center gap-3 border-b border-gray-100" style="padding:10px 14px;">
                <div class="w-8 h-8 rounded-lg bg-indigo-50 flex items-center justify-center flex-shrink-0">
                    <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z"/><path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6z"/></svg>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-[10px] text-gray-400 uppercase font-semibold tracking-wider">Sabab</p>
                    <p class="text-sm font-semibold text-gray-800">{{ $excuse->reason_label }}</p>
                </div>
            </div>
            {{-- Sanalar --}}
            <div class="flex items-center gap-3 border-b border-gray-100" style="padding:10px 14px;">
                <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center flex-shrink-0">
                    <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"/></svg>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-[10px] text-gray-400 uppercase font-semibold tracking-wider">Sanalar</p>
                    <p class="text-sm font-semibold text-gray-800">{{ $excuse->start_date->format('d.m.Y') }} — {{ $excuse->end_date->format('d.m.Y') }}</p>
                </div>
            </div>
            {{-- Izoh --}}
            @if($excuse->description)
                <div class="flex items-start gap-3 border-b border-gray-100" style="padding:10px 14px;">
                    <div class="w-8 h-8 rounded-lg bg-gray-50 flex items-center justify-center flex-shrink-0 mt-0.5">
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 01.865-.501 48.172 48.172 0 003.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z"/></svg>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-[10px] text-gray-400 uppercase font-semibold tracking-wider">Izoh</p>
                        <p class="text-sm text-gray-700">{{ $excuse->description }}</p>
                    </div>
                </div>
            @endif
            {{-- Hujjat --}}
            <div class="flex items-center gap-3" style="padding:10px 14px;">
                <div class="w-8 h-8 rounded-lg bg-amber-50 flex items-center justify-center flex-shrink-0">
                    <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-[10px] text-gray-400 uppercase font-semibold tracking-wider">Hujjat</p>
                    <a href="{{ route('student.absence-excuses.download', $excuse->id) }}"
                       class="text-sm font-medium text-indigo-600 hover:text-indigo-800 truncate block">
                        {{ $excuse->file_original_name }}
                    </a>
                </div>
                <a href="{{ route('student.absence-excuses.download', $excuse->id) }}"
                   class="w-8 h-8 rounded-lg bg-indigo-50 flex items-center justify-center flex-shrink-0 hover:bg-indigo-100 transition">
                    <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                </a>
            </div>
        </div>

        {{-- Tasdiqlangan / Rad etilgan info --}}
        @if($excuse->isApproved())
            <div class="bg-green-50 rounded-xl border border-green-200 mb-3 overflow-hidden" style="padding:12px 14px;">
                <div class="flex items-center gap-2 mb-2">
                    <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span class="text-xs font-bold text-green-700">Tasdiqlangan</span>
                </div>
                <div class="space-y-1 text-xs text-green-700">
                    <p><span class="font-medium">Tasdiqlagan:</span> {{ $excuse->reviewed_by_name }}</p>
                    <p><span class="font-medium">Sana:</span> {{ $excuse->reviewed_at->format('d.m.Y H:i') }}</p>
                </div>
                @if($excuse->approved_pdf_path)
                    <a href="{{ route('student.absence-excuses.download-pdf', $excuse->id) }}"
                       class="mt-3 w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-green-600 text-white text-xs font-bold rounded-lg hover:bg-green-700 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                        PDF hujjatni yuklab olish
                    </a>
                @endif
            </div>
        @endif

        @if($excuse->isRejected())
            <div class="bg-red-50 rounded-xl border border-red-200 mb-3 overflow-hidden" style="padding:12px 14px;">
                <div class="flex items-center gap-2 mb-2">
                    <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span class="text-xs font-bold text-red-700">Rad etilgan</span>
                </div>
                <div class="space-y-1 text-xs text-red-700">
                    <p><span class="font-medium">Rad etgan:</span> {{ $excuse->reviewed_by_name }}</p>
                    <p><span class="font-medium">Sana:</span> {{ $excuse->reviewed_at->format('d.m.Y H:i') }}</p>
                    @if($excuse->rejection_reason)
                        <p><span class="font-medium">Sabab:</span> {{ $excuse->rejection_reason }}</p>
                    @endif
                </div>
            </div>
        @endif

        {{-- O'tkazib yuborilgan nazoratlar - alohida cardlar --}}
        @if($excuse->makeups->isNotEmpty())
            <div class="mb-3">
                <div class="flex items-center justify-between mb-2" style="padding:0 2px;">
                    <h4 class="text-xs font-bold text-gray-600 uppercase tracking-wider">O'tkazib yuborilgan nazoratlar</h4>
                    @if($excuse->makeups->contains(fn($m) => !$m->makeup_date))
                        <a href="{{ route('student.absence-excuses.schedule-check', $excuse->id) }}"
                           class="text-[11px] text-indigo-600 hover:text-indigo-800 font-bold">
                            Sanalarni tanlash &rarr;
                        </a>
                    @endif
                </div>
                <div class="flex flex-col" style="gap:6px;">
                    @foreach($excuse->makeups as $makeup)
                        @php
                            $t = $typeStyles[$makeup->assessment_type] ?? ['accent' => '#6b7280', 'bg' => '#f9fafb', 'border' => '#e5e7eb', 'text' => '#374151', 'badge' => 'bg-gray-100 text-gray-700', 'icon' => 'M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25z'];
                        @endphp
                        <div class="rounded-xl border overflow-hidden shadow-sm" style="background:{{ $t['bg'] }};border-color:{{ $t['border'] }};">
                            <div class="flex items-center" style="padding:10px 12px;gap:10px;">
                                {{-- Left accent --}}
                                <div class="w-1 self-stretch rounded-full flex-shrink-0" style="background:{{ $t['accent'] }};"></div>
                                {{-- Icon --}}
                                <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0" style="background:{{ $t['accent'] }};">
                                    <svg class="w-4.5 h-4.5 text-white" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $t['icon'] }}"/></svg>
                                </div>
                                {{-- Content --}}
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2">
                                        <span class="px-2 py-0.5 text-[10px] font-bold rounded-full {{ $t['badge'] }}">{{ $makeup->assessment_type_label }}</span>
                                        <span class="px-2 py-0.5 text-[10px] font-bold rounded-full bg-{{ $makeup->status_color }}-100 text-{{ $makeup->status_color }}-800">{{ $makeup->status_label }}</span>
                                    </div>
                                    <p class="text-xs font-semibold truncate mt-1" style="color:{{ $t['text'] }};">{{ $makeup->subject_name }}</p>
                                    <div class="flex items-center gap-3 mt-1 text-[11px] text-gray-500">
                                        <span>Asl: <span class="font-medium text-gray-700">{{ $makeup->original_date->format('d.m.Y') }}</span></span>
                                        @if($makeup->makeup_date)
                                            <span>Qayta: <span class="font-bold text-indigo-600">{{ $makeup->makeup_date->format('d.m.Y') }}</span></span>
                                        @else
                                            <span class="text-amber-500 font-medium italic">Tanlanmagan</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            @if($excuse->makeups->contains(fn($m) => !$m->makeup_date))
                <a href="{{ route('student.absence-excuses.schedule-check', $excuse->id) }}"
                   class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 bg-indigo-600 text-white text-sm font-bold rounded-xl hover:bg-indigo-700 transition shadow-sm mb-3">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"/></svg>
                    Qayta topshirish sanalarini tanlash
                </a>
            @endif
        @endif

    </div>
</x-student-app-layout>
