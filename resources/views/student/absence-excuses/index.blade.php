<x-student-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-sm text-gray-800 leading-tight">
            Sababli arizalarim
        </h2>
    </x-slot>

    <div x-data="{ openId: null }">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            @if(session('success'))
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-3 mx-3 sm:mx-0 text-sm font-medium">
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-3 mx-3 sm:mx-0 text-sm font-medium">
                    {{ session('error') }}
                </div>
            @endif

            {{-- Yangi ariza tugmasi --}}
            <div class="px-3 sm:px-0 mb-3">
                <a href="{{ route('student.absence-excuses.create') }}"
                   class="inline-flex items-center gap-2 px-4 py-2.5 bg-indigo-600 text-white text-sm font-semibold rounded-lg hover:bg-indigo-700 transition shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                    Yangi ariza
                </a>
            </div>

            @if($excuses->isEmpty())
                <div class="text-center py-12">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">Ariza topilmadi</h3>
                    <p class="mt-1 text-sm text-gray-500">Hozircha ariza topshirmagansiz.</p>
                </div>
            @else
                <div class="flex flex-col px-3 sm:px-0" style="gap:6px;">
                    @foreach($excuses as $excuse)
                        @php
                            $statusColors = [
                                'yellow' => ['bg' => '#fffbeb', 'border' => '#fde68a', 'accent' => '#f59e0b', 'text' => '#92400e', 'badge_bg' => 'bg-yellow-100', 'badge_text' => 'text-yellow-800'],
                                'green' => ['bg' => '#ecfdf5', 'border' => '#a7f3d0', 'accent' => '#10b981', 'text' => '#065f46', 'badge_bg' => 'bg-green-100', 'badge_text' => 'text-green-800'],
                                'red' => ['bg' => '#fef2f2', 'border' => '#fecaca', 'accent' => '#ef4444', 'text' => '#991b1b', 'badge_bg' => 'bg-red-100', 'badge_text' => 'text-red-800'],
                                'blue' => ['bg' => '#eff6ff', 'border' => '#bfdbfe', 'accent' => '#3b82f6', 'text' => '#1e40af', 'badge_bg' => 'bg-blue-100', 'badge_text' => 'text-blue-800'],
                                'gray' => ['bg' => '#f9fafb', 'border' => '#e5e7eb', 'accent' => '#6b7280', 'text' => '#374151', 'badge_bg' => 'bg-gray-100', 'badge_text' => 'text-gray-800'],
                            ];
                            $c = $statusColors[$excuse->status_color] ?? $statusColors['gray'];
                        @endphp
                        <div class="overflow-hidden rounded-xl border shadow-sm" style="background:{{ $c['bg'] }};border-color:{{ $c['border'] }};">
                            {{-- Accordion header --}}
                            <button @click="openId = openId === {{ $excuse->id }} ? null : {{ $excuse->id }}"
                                    class="w-full flex items-center text-left active:scale-[0.99] transition-all duration-150"
                                    style="padding:10px 12px;gap:10px;">
                                {{-- Left accent --}}
                                <div class="w-1 self-stretch rounded-full flex-shrink-0" style="background:{{ $c['accent'] }};"></div>
                                {{-- Content --}}
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center justify-between gap-2">
                                        <h3 class="text-sm font-bold truncate" style="color:{{ $c['text'] }};">{{ $excuse->reason_label }}</h3>
                                        <span class="px-2 py-0.5 text-[10px] font-bold rounded-full flex-shrink-0 {{ $c['badge_bg'] }} {{ $c['badge_text'] }}">
                                            {{ $excuse->status_label }}
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-3 mt-1">
                                        <span class="text-[11px] text-gray-500 flex items-center gap-1">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"/></svg>
                                            {{ $excuse->start_date->format('d.m.Y') }} - {{ $excuse->end_date->format('d.m.Y') }}
                                        </span>
                                    </div>
                                </div>
                                {{-- Chevron --}}
                                <svg class="w-5 h-5 flex-shrink-0 transition-transform duration-200" style="color:{{ $c['accent'] }};"
                                     :class="openId === {{ $excuse->id }} ? 'rotate-180' : ''"
                                     fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/>
                                </svg>
                            </button>

                            {{-- Accordion body --}}
                            <div x-show="openId === {{ $excuse->id }}" x-collapse>
                                <div style="padding:0 12px 12px 12px;margin-left:14px;border-top:1px solid {{ $c['border'] }};">
                                    <div class="pt-3 space-y-2">
                                        {{-- Yuborilgan vaqt --}}
                                        <div class="flex items-center gap-2 text-xs text-gray-600">
                                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                            Yuborilgan: <span class="font-medium">{{ $excuse->created_at->format('d.m.Y H:i') }}</span>
                                        </div>

                                        {{-- Amallar --}}
                                        <div class="flex items-center gap-2 pt-1">
                                            <a href="{{ route('student.absence-excuses.show', $excuse->id) }}"
                                               class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg transition"
                                               style="background:{{ $c['accent'] }};color:white;">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                                Ko'rish
                                            </a>
                                            @if($excuse->isApproved() && $excuse->approved_pdf_path)
                                                <a href="{{ route('student.absence-excuses.download-pdf', $excuse->id) }}"
                                                   class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-green-500 text-white text-xs font-medium rounded-lg hover:bg-green-600 transition">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                                                    PDF
                                                </a>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-4 px-3 sm:px-0">
                    {{ $excuses->links() }}
                </div>
            @endif
        </div>
    </div>
</x-student-app-layout>
