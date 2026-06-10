<x-app-layout>
    <x-slot name="header">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap;">
            <div>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ $title }}</h2>
                <div class="mt-1 text-sm text-gray-500">{{ number_format($count) }} ta talaba</div>
            </div>
            <a href="{{ route('admin.visa-applications.index') }}" style="display:inline-flex;align-items:center;gap:5px;padding:6px 14px;font-size:12px;font-weight:600;color:#475569;background:#f1f5f9;border:1px solid #cbd5e1;border-radius:8px;text-decoration:none;transition:all 0.15s;">
                <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
                Arizalarga qaytish
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
            <div class="rounded-3xl border shadow-sm overflow-hidden" style="border-color: {{ $theme['border'] }}; background: linear-gradient(135deg, {{ $theme['bg'] }}, #ffffff);">
                <div class="px-5 py-4 flex items-center justify-between gap-3 flex-wrap">
                    <div>
                        <div class="text-xs font-bold uppercase tracking-[0.2em]" style="color: {{ $theme['fg'] }};">Visa statistics</div>
                        <div class="mt-2 text-2xl font-black text-slate-800">{{ $title }}</div>
                    </div>
                    <div class="rounded-2xl px-4 py-3 text-right" style="background: rgba(255,255,255,0.8); border: 1px solid {{ $theme['border'] }};">
                        <div class="text-xs font-semibold text-slate-500">Jami</div>
                        <div class="text-3xl font-black" style="color: {{ $theme['fg'] }};">{{ number_format($count) }}</div>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead style="background: {{ $theme['table'] }};" class="text-white">
                            <tr>
                                <th class="px-4 py-4 text-left font-semibold">#</th>
                                <th class="px-4 py-4 text-left font-semibold">Talaba</th>
                                <th class="px-4 py-4 text-left font-semibold">Kurs</th>
                                <th class="px-4 py-4 text-left font-semibold">Yo'nalish</th>
                                <th class="px-4 py-4 text-left font-semibold">Guruh</th>
                                <th class="px-4 py-4 text-left font-semibold">Holati</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @forelse($rows as $index => $student)
                                @php
                                    $studentStatusMeta = $statusMeta[$student['application_status'] ?? 'pending'] ?? $statusMeta['pending'];
                                    $hasApplication = !empty($student['application_number']);
                                @endphp
                                <tr class="hover:bg-slate-50">
                                    <td class="px-4 py-4 text-slate-500 align-top">{{ $index + 1 }}</td>
                                    <td class="px-4 py-4">
                                        <div class="font-bold text-slate-800">{{ $student['full_name'] }}</div>
                                        <div class="mt-1 text-xs text-slate-500">Student ID: {{ $student['student_id_number'] ?: '-' }}</div>
                                        <div class="mt-1 text-xs text-slate-400">HEMIS ID: {{ $student['hemis_id'] ?: '-' }}</div>
                                    </td>
                                    <td class="px-4 py-4 text-slate-700">{{ $student['course_name'] ?: '-' }}</td>
                                    <td class="px-4 py-4 text-slate-700">
                                        <div>{{ $student['specialty_name'] ?: ($student['department_name'] ?: '-') }}</div>
                                        @if(!empty($student['department_name']) && !empty($student['specialty_name']))
                                            <div class="mt-1 text-xs text-slate-400">{{ $student['department_name'] }}</div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4 text-slate-700">{{ $student['group_name'] ?: '-' }}</td>
                                    <td class="px-4 py-4">
                                        @if($hasApplication)
                                            <div class="flex flex-col gap-2">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase"
                                                          style="background:{{ $studentStatusMeta['bg'] }};color:{{ $studentStatusMeta['fg'] }};border:1px solid {{ $studentStatusMeta['border'] }};">
                                                        {{ $studentStatusMeta['label'] }}
                                                    </span>
                                                    <span class="text-xs font-bold text-slate-600">#{{ $student['application_number'] }}</span>
                                                </div>
                                                <div class="text-xs text-slate-500">Yuborilgan: {{ $student['submitted_at'] ?: '-' }}</div>
                                            </div>
                                        @else
                                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase"
                                                  style="background:#fff7ed;color:#b45309;border:1px solid #fcd34d;">
                                                Topshirmagan
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-12 text-center text-slate-500">Ro'yxat bo'sh.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
