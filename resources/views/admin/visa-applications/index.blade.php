<x-app-layout>
    <x-slot name="header">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap;">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Viza arizalar</h2>
            <a href="{{ route('admin.international-students.index') }}" style="display:inline-flex;align-items:center;gap:5px;padding:6px 14px;font-size:12px;font-weight:600;color:#475569;background:#f1f5f9;border:1px solid #cbd5e1;border-radius:8px;text-decoration:none;transition:all 0.15s;">
                <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
                Xalqaro talabalar
            </a>
        </div>
    </x-slot>

    @php
        $statusMeta = [
            'pending'   => ['label' => 'Kutilmoqda',    'bg' => '#fef3c7', 'fg' => '#92400e', 'border' => '#fde68a'],
            'reviewing' => ['label' => 'Ko\'rilmoqda',  'bg' => '#dbeafe', 'fg' => '#1e40af', 'border' => '#bfdbfe'],
            'approved'  => ['label' => 'Qabul qilindi', 'bg' => '#d1fae5', 'fg' => '#065f46', 'border' => '#a7f3d0'],
            'rejected'  => ['label' => 'Rad etilgan',   'bg' => '#fee2e2', 'fg' => '#991b1b', 'border' => '#fecaca'],
        ];
        $total = array_sum($counts);
    @endphp

    <div class="py-6">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4"
             x-data="{ statsModal: null }"
             x-effect="document.documentElement.classList.toggle('overflow-hidden', !!statsModal); document.body.classList.toggle('overflow-hidden', !!statsModal);"
             @keydown.escape.window="statsModal = null">

            @if(session('success'))
                <div class="bg-white rounded-xl border border-emerald-200 shadow-sm overflow-hidden">
                    <div class="px-5 py-3 flex items-center gap-3" style="background: linear-gradient(135deg, #ecfdf5, #d1fae5);">
                        <svg class="w-5 h-5 text-emerald-600 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        <span class="text-sm font-semibold text-emerald-800">{{ session('success') }}</span>
                    </div>
                </div>
            @endif

            @if(session('error'))
                <div class="bg-white rounded-xl border border-red-200 shadow-sm overflow-hidden">
                    <div class="px-5 py-3 flex items-center gap-3" style="background: linear-gradient(135deg, #fef2f2, #fee2e2);">
                        <svg class="w-5 h-5 text-red-600 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.008v.008H12v-.008Zm8.25-.75a8.25 8.25 0 11-16.5 0 8.25 8.25 0 0116.5 0Z"/></svg>
                        <span class="text-sm font-semibold text-red-800">{{ session('error') }}</span>
                    </div>
                </div>
            @endif

            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <button type="button"
                        @click="statsModal = 'total'"
                        :class="statsModal === 'total' ? 'ring-2 ring-sky-300 ring-offset-2' : ''"
                        class="text-left rounded-2xl shadow-sm border border-sky-200 p-4 transition hover:-translate-y-0.5"
                        style="background:linear-gradient(135deg,#eff6ff,#dbeafe);">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="text-[11px] font-bold uppercase tracking-wide text-sky-700">Xorijiy fuqarolar jami</div>
                            <div class="mt-2 text-3xl font-black text-slate-800">{{ number_format($visaStats['total_foreign_citizens'] ?? 0) }}</div>
                            <div class="mt-2 text-xs text-slate-600">Bosib umumiy ro'yxatni oching</div>
                        </div>
                        <div class="w-10 h-10 rounded-xl flex items-center justify-center text-sky-700" style="background:rgba(255,255,255,0.7);">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/></svg>
                        </div>
                    </div>
                </button>
                <button type="button"
                        @click="statsModal = 'submitted'"
                        :class="statsModal === 'submitted' ? 'ring-2 ring-emerald-300 ring-offset-2' : ''"
                        class="text-left rounded-2xl shadow-sm border border-emerald-200 p-4 transition hover:-translate-y-0.5"
                        style="background:linear-gradient(135deg,#ecfdf5,#d1fae5);">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="text-[11px] font-bold uppercase tracking-wide text-emerald-700">Ariza topshirganlar</div>
                            <div class="mt-2 text-3xl font-black text-slate-800">{{ number_format($visaStats['submitted_applications'] ?? 0) }}</div>
                            <div class="mt-2 text-xs text-slate-600">Bosib topshirganlar ro'yxatini oching</div>
                        </div>
                        <div class="w-10 h-10 rounded-xl flex items-center justify-center text-emerald-700" style="background:rgba(255,255,255,0.7);">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/></svg>
                        </div>
                    </div>
                </button>
                <button type="button"
                        @click="statsModal = 'not_submitted'"
                        :class="statsModal === 'not_submitted' ? 'ring-2 ring-amber-300 ring-offset-2' : ''"
                        class="text-left rounded-2xl shadow-sm border border-amber-200 p-4 transition hover:-translate-y-0.5"
                        style="background:linear-gradient(135deg,#fff7ed,#fde68a);">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="text-[11px] font-bold uppercase tracking-wide text-amber-700">Farqi</div>
                            <div class="mt-2 text-3xl font-black text-slate-800">{{ number_format($visaStats['not_submitted'] ?? 0) }}</div>
                            <div class="mt-2 text-xs text-slate-600">Bosib topshirmaganlar ro'yxatini oching</div>
                        </div>
                        <div class="w-10 h-10 rounded-xl flex items-center justify-center text-amber-700" style="background:rgba(255,255,255,0.7);">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/></svg>
                        </div>
                    </div>
                </button>
            </div>

            <template x-teleport="body">
                <div x-show="statsModal"
                     x-cloak
                     class="fixed inset-0 z-[90] overflow-y-auto overscroll-contain"
                     style="background:rgba(15,23,42,0.55);backdrop-filter:blur(5px);"
                     @click.self="statsModal = null">
                    <div class="min-h-screen w-full flex items-start justify-center p-4 sm:p-6">
                        <div class="max-w-6xl w-full bg-white rounded-3xl shadow-2xl border border-slate-200 overflow-hidden">
                    <div class="px-5 sm:px-6 py-4 border-b border-slate-200 flex items-start justify-between gap-4"
                         x-show="statsModal === 'total'"
                         style="background:linear-gradient(135deg,#eff6ff,#dbeafe);">
                        <div>
                            <div class="text-lg font-bold text-sky-900">Xorijiy fuqarolar umumiy ro'yxati</div>
                            <div class="text-sm text-sky-700 mt-1">{{ number_format($visaStats['total_foreign_citizens'] ?? 0) }} ta talaba</div>
                        </div>
                        <button type="button" @click="statsModal = null" class="w-10 h-10 rounded-xl flex items-center justify-center bg-white/80 text-sky-800 hover:bg-white">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                    <div class="px-5 sm:px-6 py-4 border-b border-slate-200 flex items-start justify-between gap-4"
                         x-show="statsModal === 'submitted'"
                         style="background:linear-gradient(135deg,#ecfdf5,#d1fae5);">
                        <div>
                            <div class="text-lg font-bold text-emerald-900">Visa application topshirganlar</div>
                            <div class="text-sm text-emerald-700 mt-1">{{ number_format($visaStats['submitted_applications'] ?? 0) }} ta talaba</div>
                        </div>
                        <button type="button" @click="statsModal = null" class="w-10 h-10 rounded-xl flex items-center justify-center bg-white/80 text-emerald-800 hover:bg-white">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                    <div class="px-5 sm:px-6 py-4 border-b border-slate-200 flex items-start justify-between gap-4"
                         x-show="statsModal === 'not_submitted'"
                         style="background:linear-gradient(135deg,#fff7ed,#fde68a);">
                        <div>
                            <div class="text-lg font-bold text-amber-900">Visa application topshirmaganlar</div>
                            <div class="text-sm text-amber-700 mt-1">{{ number_format($visaStats['not_submitted'] ?? 0) }} ta talaba</div>
                        </div>
                        <button type="button" @click="statsModal = null" class="w-10 h-10 rounded-xl flex items-center justify-center bg-white/80 text-amber-800 hover:bg-white">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>

                    <div class="max-h-[78vh] overflow-y-auto overscroll-contain">
                        <div x-show="statsModal === 'total'" class="p-4 sm:p-6">
                            <div class="overflow-x-auto rounded-2xl border border-slate-200">
                                <table class="min-w-full text-sm">
                                    <thead class="sticky top-0 z-10 bg-slate-900 text-white">
                                        <tr>
                                            <th class="px-4 py-3 text-left font-semibold">#</th>
                                            <th class="px-4 py-3 text-left font-semibold">Talaba</th>
                                            <th class="px-4 py-3 text-left font-semibold">Kurs</th>
                                            <th class="px-4 py-3 text-left font-semibold">Yo'nalish</th>
                                            <th class="px-4 py-3 text-left font-semibold">Guruh</th>
                                            <th class="px-4 py-3 text-left font-semibold">Holati</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 bg-white">
                                        @forelse($visaStats['lists']['total'] ?? [] as $index => $student)
                                            @php $studentStatusMeta = $statusMeta[$student['application_status'] ?? 'pending'] ?? $statusMeta['pending']; @endphp
                                            <tr class="hover:bg-slate-50">
                                                <td class="px-4 py-3 text-slate-500">{{ $index + 1 }}</td>
                                                <td class="px-4 py-3">
                                                    <div class="font-semibold text-slate-800">{{ $student['full_name'] }}</div>
                                                    <div class="mt-1 text-xs text-slate-500">Student ID: {{ $student['student_id_number'] ?: '—' }}</div>
                                                </td>
                                                <td class="px-4 py-3 text-slate-700">{{ $student['course_name'] ?: '—' }}</td>
                                                <td class="px-4 py-3 text-slate-700">{{ $student['specialty_name'] ?: ($student['department_name'] ?: '—') }}</td>
                                                <td class="px-4 py-3 text-slate-700">{{ $student['group_name'] ?: '—' }}</td>
                                                <td class="px-4 py-3">
                                                    @if(!empty($student['application_number']))
                                                        <div class="flex flex-wrap items-center gap-2">
                                                            <span class="px-2 py-1 rounded-full text-[10px] font-bold uppercase"
                                                                  style="background:{{ $studentStatusMeta['bg'] }};color:{{ $studentStatusMeta['fg'] }};border:1px solid {{ $studentStatusMeta['border'] }};">
                                                                {{ $studentStatusMeta['label'] }}
                                                            </span>
                                                            <span class="text-xs font-bold text-slate-600">#{{ $student['application_number'] }}</span>
                                                        </div>
                                                    @else
                                                        <span class="px-2 py-1 rounded-full text-[10px] font-bold uppercase"
                                                              style="background:#fff7ed;color:#b45309;border:1px solid #fcd34d;">
                                                            Topshirmagan
                                                        </span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="6" class="px-4 py-10 text-center text-slate-500">Ro'yxat bo'sh.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div x-show="statsModal === 'submitted'" class="p-4 sm:p-6">
                            <div class="overflow-x-auto rounded-2xl border border-slate-200">
                                <table class="min-w-full text-sm">
                                    <thead class="sticky top-0 z-10 bg-emerald-700 text-white">
                                        <tr>
                                            <th class="px-4 py-3 text-left font-semibold">#</th>
                                            <th class="px-4 py-3 text-left font-semibold">Talaba</th>
                                            <th class="px-4 py-3 text-left font-semibold">Kurs</th>
                                            <th class="px-4 py-3 text-left font-semibold">Yo'nalish</th>
                                            <th class="px-4 py-3 text-left font-semibold">Guruh</th>
                                            <th class="px-4 py-3 text-left font-semibold">Ariza</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 bg-white">
                                        @forelse($visaStats['lists']['submitted'] ?? [] as $index => $student)
                                            @php $studentStatusMeta = $statusMeta[$student['application_status'] ?? 'pending'] ?? $statusMeta['pending']; @endphp
                                            <tr class="hover:bg-emerald-50/50">
                                                <td class="px-4 py-3 text-slate-500">{{ $index + 1 }}</td>
                                                <td class="px-4 py-3">
                                                    <div class="font-semibold text-slate-800">{{ $student['full_name'] }}</div>
                                                    <div class="mt-1 text-xs text-slate-500">Student ID: {{ $student['student_id_number'] ?: '—' }}</div>
                                                </td>
                                                <td class="px-4 py-3 text-slate-700">{{ $student['course_name'] ?: '—' }}</td>
                                                <td class="px-4 py-3 text-slate-700">{{ $student['specialty_name'] ?: ($student['department_name'] ?: '—') }}</td>
                                                <td class="px-4 py-3 text-slate-700">{{ $student['group_name'] ?: '—' }}</td>
                                                <td class="px-4 py-3">
                                                    <div class="flex flex-col gap-1">
                                                        <div class="flex flex-wrap items-center gap-2">
                                                            <span class="px-2 py-1 rounded-full text-[10px] font-bold uppercase"
                                                                  style="background:{{ $studentStatusMeta['bg'] }};color:{{ $studentStatusMeta['fg'] }};border:1px solid {{ $studentStatusMeta['border'] }};">
                                                                {{ $studentStatusMeta['label'] }}
                                                            </span>
                                                            <span class="text-xs font-bold text-slate-600">#{{ $student['application_number'] }}</span>
                                                        </div>
                                                        <div class="text-xs text-slate-500">Yuborgan: {{ $student['submitted_at'] ?: '—' }}</div>
                                                    </div>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="6" class="px-4 py-10 text-center text-slate-500">Ro'yxat bo'sh.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div x-show="statsModal === 'not_submitted'" class="p-4 sm:p-6">
                            <div class="overflow-x-auto rounded-2xl border border-slate-200">
                                <table class="min-w-full text-sm">
                                    <thead class="sticky top-0 z-10 bg-amber-600 text-white">
                                        <tr>
                                            <th class="px-4 py-3 text-left font-semibold">#</th>
                                            <th class="px-4 py-3 text-left font-semibold">Talaba</th>
                                            <th class="px-4 py-3 text-left font-semibold">Kurs</th>
                                            <th class="px-4 py-3 text-left font-semibold">Yo'nalish</th>
                                            <th class="px-4 py-3 text-left font-semibold">Guruh</th>
                                            <th class="px-4 py-3 text-left font-semibold">Holati</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 bg-white">
                                        @forelse($visaStats['lists']['not_submitted'] ?? [] as $index => $student)
                                            <tr class="hover:bg-amber-50/50">
                                                <td class="px-4 py-3 text-slate-500">{{ $index + 1 }}</td>
                                                <td class="px-4 py-3">
                                                    <div class="font-semibold text-slate-800">{{ $student['full_name'] }}</div>
                                                    <div class="mt-1 text-xs text-slate-500">Student ID: {{ $student['student_id_number'] ?: '—' }}</div>
                                                </td>
                                                <td class="px-4 py-3 text-slate-700">{{ $student['course_name'] ?: '—' }}</td>
                                                <td class="px-4 py-3 text-slate-700">{{ $student['specialty_name'] ?: ($student['department_name'] ?: '—') }}</td>
                                                <td class="px-4 py-3 text-slate-700">{{ $student['group_name'] ?: '—' }}</td>
                                                <td class="px-4 py-3">
                                                    <span class="px-2 py-1 rounded-full text-[10px] font-bold uppercase"
                                                          style="background:#fff7ed;color:#b45309;border:1px solid #fcd34d;">
                                                        Topshirmagan
                                                    </span>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="6" class="px-4 py-10 text-center text-slate-500">Ro'yxat bo'sh.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                    </div>
                </div>
            </template>

            {{-- FILTER + STATS --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="px-6 py-3 border-b border-gray-100 flex items-center justify-between gap-2 flex-wrap" style="background: linear-gradient(135deg, #e8edf5, #dbe4ef);">
                    <div class="font-bold text-gray-800 text-sm">Holat bo'yicha filtr</div>
                    <div class="flex items-center gap-2">
                        {{-- Excel eksport --}}
                        <a href="{{ route('admin.visa-applications.export', array_filter(['status' => $status])) }}"
                           class="px-3 py-1.5 text-xs font-bold rounded-lg border flex items-center gap-1.5"
                           style="background:#fff;border-color:#10b981;color:#047857;">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                            Excel
                        </a>
                        {{-- Hammasi / Faqat oxirgi toggle --}}
                        @php
                            $toggleParams = array_filter(['status' => $status]);
                            if (!$showAll) $toggleParams['all'] = 1;
                        @endphp
                        <a href="{{ route('admin.visa-applications.index', $toggleParams) }}"
                           title="{{ $showAll ? 'Faqat oxirgi arizalarni ko\'rsatish' : 'Talabaning oldingi qayta arizalarini ham ko\'rsatish' }}"
                           class="px-3 py-1.5 text-xs font-bold rounded-lg border flex items-center gap-1.5"
                           style="background:{{ $showAll ? '#fef3c7' : '#fff' }};border-color:{{ $showAll ? '#f59e0b' : '#cbd5e1' }};color:{{ $showAll ? '#92400e' : '#475569' }};">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            {{ $showAll ? 'Tarix bilan' : 'Faqat oxirgi' }}
                        </a>
                    </div>
                </div>
                <div class="p-3 flex flex-wrap gap-2">
                    <a href="{{ route('admin.visa-applications.index', array_filter(['all' => $showAll ? 1 : null])) }}"
                       class="px-3 py-1.5 text-xs font-bold rounded-lg border transition flex items-center gap-2"
                       style="background:{{ !$status ? '#2b5ea7' : '#fff' }};color:{{ !$status ? '#fff' : '#475569' }};border-color:{{ !$status ? '#2b5ea7' : '#e2e8f0' }};">
                        Hammasi
                        <span style="background:rgba(255,255,255,0.2);padding:1px 6px;border-radius:999px;{{ !$status ? '' : 'background:#f1f5f9;color:#475569;' }}">{{ $total }}</span>
                    </a>
                    @foreach($statusMeta as $key => $m)
                        <a href="{{ route('admin.visa-applications.index', array_filter(['status' => $key, 'all' => $showAll ? 1 : null])) }}"
                           class="px-3 py-1.5 text-xs font-bold rounded-lg border transition flex items-center gap-2"
                           style="background:{{ $status === $key ? $m['fg'] : $m['bg'] }};color:{{ $status === $key ? '#fff' : $m['fg'] }};border-color:{{ $m['border'] }};">
                            {{ $m['label'] }}
                            <span style="background:rgba(255,255,255,0.2);padding:1px 6px;border-radius:999px;{{ $status === $key ? '' : 'background:rgba(0,0,0,0.08);' }}">{{ $counts[$key] ?? 0 }}</span>
                        </a>
                    @endforeach
                </div>
            </div>

            @if(!$applications->isEmpty())
                <div class="bg-white rounded-xl border border-emerald-200 shadow-sm p-3 flex items-center justify-start gap-3 flex-wrap">
                    <button type="button"
                            id="vaCheckAllBtn"
                            onclick="vaToggleCheckAll();"
                            class="px-3 py-2 text-xs font-bold rounded-lg border flex items-center gap-1.5 text-white"
                            style="background:linear-gradient(135deg,#16a34a,#22c55e);border-color:#16a34a;">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M12 5l7 7-7 7"/></svg>
                        Hammasini belgilash
                    </button>
                    <div class="text-sm font-semibold text-slate-700">
                        Joriy sahifadagi arizalarni ommaviy tanlash
                    </div>
                </div>
            @endif

            {{-- BULK TOOLBAR --}}
            @if(!$applications->isEmpty())
            <div id="bulkBar" x-data="{ count: 0 }"
                 x-init="window.vaBulkUpdate = () => { count = document.querySelectorAll('.va-row-cb:checked').length; window.vaSyncCheckAllButton && window.vaSyncCheckAllButton(); };"
                 x-show="count > 0" x-cloak
                 class="bg-white rounded-xl border-2 shadow-sm overflow-hidden sticky top-2 z-20"
                 style="border-color:#2b5ea7;">
                <div class="px-4 py-2.5 flex items-center gap-2 flex-wrap" style="background: linear-gradient(135deg,#dbeafe,#bfdbfe);">
                    <span class="text-xs font-bold text-blue-900">
                        <span x-text="count"></span> ta tanlandi
                    </span>
                    <div class="flex-1"></div>

                    {{-- Holatga ko'chirish --}}
                    @foreach($statusMeta as $key => $m)
                        <button type="button"
                                onclick="vaBulkSubmit('{{ route('admin.visa-applications.bulk-update') }}', 'POST', '{{ $key }}', 'Tanlangan arizalarni \'{{ $m['label'] }}\' bosqichiga o\'tkazasizmi?');"
                                class="px-2.5 py-1.5 text-[11px] font-bold rounded-lg border flex items-center gap-1"
                                style="background:{{ $m['bg'] }};color:{{ $m['fg'] }};border-color:{{ $m['border'] }};">
                            {{ $m['label'] }}
                        </button>
                    @endforeach

                    {{-- Telex --}}
                    <button type="button"
                            onclick="vaBulkSubmit('{{ route('admin.visa-applications.telex') }}', 'POST', null, 'Tanlangan arizalar uchun telex hujjat yaratasizmi?');"
                            class="px-2.5 py-1.5 text-[11px] font-bold text-white rounded-lg flex items-center gap-1"
                            style="background:linear-gradient(135deg,#7c3aed,#5b21b6);">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        Telex
                    </button>

                    @if($status === 'approved')
                        <button type="button"
                                onclick="vaBulkSubmit('{{ route('admin.visa-applications.download-documents') }}', 'POST', null, 'Tanlangan arizalarning hujjatlarini bitta ZIP qilib yuklab olasizmi?');"
                                class="px-2.5 py-1.5 text-[11px] font-bold text-white rounded-lg flex items-center gap-1"
                                style="background:linear-gradient(135deg,#0f766e,#0d9488);">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V4.5m0 12 4.5-4.5M12 16.5l-4.5-4.5M4.5 19.5h15"/></svg>
                            Hujjatlarni yuklash
                        </button>
                    @endif

                    {{-- Excel (faqat tanlanganlar) --}}
                    <button type="button"
                            onclick="vaBulkSubmit('{{ route('admin.visa-applications.export') }}', 'GET');"
                            class="px-2.5 py-1.5 text-[11px] font-bold rounded-lg border flex items-center gap-1"
                            style="background:#fff;border-color:#10b981;color:#047857;">
                        Excel
                    </button>

                    <button type="button" onclick="document.querySelectorAll('.va-row-cb').forEach(c=>c.checked=false); window.vaBulkUpdate && window.vaBulkUpdate();"
                            class="px-2.5 py-1.5 text-[11px] font-bold rounded-lg border"
                            style="background:#fff;border-color:#cbd5e1;color:#475569;">
                        Bekor qilish
                    </button>
                </div>
            </div>
            @endif

            {{-- ARIZALAR --}}
            @if($applications->isEmpty())
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-10 text-center">
                    <svg class="w-14 h-14 mx-auto text-slate-300 mb-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21"/>
                    </svg>
                    <div class="text-sm font-semibold text-slate-600">Bu holatda arizalar yo'q</div>
                </div>
            @else
                <div class="space-y-2" x-data="{ open: null }">
                    @foreach($applications as $app)
                        @php $m = $statusMeta[$app->status] ?? $statusMeta['pending']; @endphp
                        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden flex items-stretch">
                            {{-- CHECKBOX --}}
                            <label class="flex items-center justify-center px-3 border-r border-slate-100 bg-slate-50 cursor-pointer hover:bg-slate-100"
                                   onclick="event.stopPropagation();">
                                <input type="checkbox"
                                       class="va-row-cb w-4 h-4 cursor-pointer accent-blue-600"
                                       value="{{ $app->id }}"
                                       onchange="window.vaBulkUpdate && window.vaBulkUpdate();">
                            </label>
                            <div class="flex-1 min-w-0">
                            {{-- ACCORDION HEADER --}}
                            <button type="button"
                                    @click="open = (open === {{ $app->id }}) ? null : {{ $app->id }}"
                                    class="w-full px-4 sm:px-5 py-3 flex items-center justify-between gap-3 hover:bg-slate-50 transition text-left">
                                <div class="flex items-center gap-3 min-w-0 flex-1">
                                    <div class="flex flex-col items-center w-12 flex-shrink-0">
                                        <span class="text-[10px] font-semibold text-slate-500 uppercase tracking-wide">№</span>
                                        <span class="text-base font-bold text-slate-800">{{ $app->application_number }}</span>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="font-semibold text-sm text-slate-800 truncate">
                                            {{ $app->last_name }} {{ $app->first_name }} {{ $app->middle_name }}
                                        </div>
                                        <div class="text-xs text-slate-500 mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-0.5">
                                            <span>Student ID: <strong>{{ $app->student_number }}</strong></span>
                                            <span class="hidden sm:inline">·</span>
                                            <span>{{ $app->created_at->format('d.m.Y H:i') }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 flex-shrink-0">
                                    <span class="px-2 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide"
                                          style="background:{{ $m['bg'] }};color:{{ $m['fg'] }};border:1px solid {{ $m['border'] }};">
                                        {{ $m['label'] }}
                                    </span>
                                    <svg class="w-4 h-4 text-slate-400 transition-transform" :class="open === {{ $app->id }} ? 'rotate-180' : ''"
                                         fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/>
                                    </svg>
                                </div>
                            </button>

                            {{-- ACCORDION BODY --}}
                            <div x-show="open === {{ $app->id }}" x-collapse class="border-t border-gray-100">
                                <div class="p-4 sm:p-5 space-y-4">
                                    {{-- TALABA MA'LUMOTLARI --}}
                                    <div>
                                        <div class="text-[11px] font-bold text-slate-500 uppercase tracking-wide mb-2">Talaba ma'lumotlari</div>
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-2 text-sm bg-slate-50 rounded-lg p-3 border border-slate-200">
                                            <div><span class="text-slate-500">Familiya:</span> <strong class="text-slate-800">{{ $app->last_name }}</strong></div>
                                            <div><span class="text-slate-500">Ism:</span> <strong class="text-slate-800">{{ $app->first_name }}</strong></div>
                                            <div><span class="text-slate-500">Otasining ismi:</span> <strong class="text-slate-800">{{ $app->middle_name ?: '—' }}</strong></div>
                                            <div><span class="text-slate-500">Tug'ilgan sana:</span> <strong class="text-slate-800">{{ optional($app->birth_date)->format('d.m.Y') ?? '—' }}</strong></div>
                                            <div><span class="text-slate-500">Pasport raqami:</span> <strong class="text-slate-800">{{ $app->passport_number }}</strong></div>
                                            <div><span class="text-slate-500">Student ID:</span> <strong class="text-slate-800">{{ $app->student_number }}</strong></div>
                                            <div><span class="text-slate-500">Telefon raqami:</span> <strong class="text-slate-800">{{ $app->phone_number }}</strong></div>
                                            <div>
                                                <span class="text-slate-500">{{ ucfirst($app->messenger_type ?? 'telegram') }}:</span>
                                                @php
                                                    $uname = ltrim($app->messenger_username ?? '', '@');
                                                    $tgLink = $uname ? 'https://t.me/' . $uname : null;
                                                    $waLink = $uname && $app->phone_number ? 'https://wa.me/' . preg_replace('/\D/', '', $app->phone_number) : null;
                                                    $link = ($app->messenger_type === 'whatsapp') ? $waLink : $tgLink;
                                                @endphp
                                                @if($link)
                                                    <a href="{{ $link }}" target="_blank" rel="noopener" class="font-bold text-blue-600 hover:underline">@{{ $uname }}</a>
                                                @else
                                                    <strong class="text-slate-800">@{{ $uname ?: '—' }}</strong>
                                                @endif
                                            </div>
                                            <div class="sm:col-span-2"><span class="text-slate-500">Yuborilgan:</span> <strong class="text-slate-800">{{ $app->created_at->format('d.m.Y H:i') }}</strong></div>
                                        </div>
                                    </div>

                                    {{-- FAYLLAR --}}
                                    <div>
                                        <div class="text-[11px] font-bold text-slate-500 uppercase tracking-wide mb-2">Yuklangan fayllar</div>
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                            @if($app->passport_pdf_path)
                                                <a href="{{ route('admin.visa-applications.file', [$app, 'passport']) }}" target="_blank" rel="noopener"
                                                   class="flex items-center gap-3 p-3 bg-white border-2 border-slate-200 hover:border-blue-400 rounded-lg transition group">
                                                    <div class="w-9 h-9 rounded-lg bg-red-100 flex items-center justify-center flex-shrink-0">
                                                        <svg class="w-5 h-5 text-red-600" fill="currentColor" viewBox="0 0 24 24"><path d="M9 0a2 2 0 0 0-2 2v2H3v18h18V4H9V2a2 2 0 0 0 2-2H9zm2 7h2v2h2v2h-2v6h-2v-6H9V9h2V7z"/></svg>
                                                    </div>
                                                    <div class="flex-1 min-w-0">
                                                        <div class="text-sm font-semibold text-slate-800">Passport copies</div>
                                                        <div class="text-[11px] text-slate-500 truncate">PDF · yangi tabda ochiladi</div>
                                                    </div>
                                                    <svg class="w-4 h-4 text-slate-400 group-hover:text-blue-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/></svg>
                                                </a>
                                            @endif
                                            @if($app->application_pdf_path)
                                                <a href="{{ route('admin.visa-applications.file', [$app, 'application']) }}" target="_blank" rel="noopener"
                                                   class="flex items-center gap-3 p-3 bg-white border-2 border-slate-200 hover:border-blue-400 rounded-lg transition group">
                                                    <div class="w-9 h-9 rounded-lg bg-blue-100 flex items-center justify-center flex-shrink-0">
                                                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                                    </div>
                                                    <div class="flex-1 min-w-0">
                                                        <div class="text-sm font-semibold text-slate-800">Filled application</div>
                                                        <div class="text-[11px] text-slate-500 truncate">PDF · yangi tabda ochiladi</div>
                                                    </div>
                                                    <svg class="w-4 h-4 text-slate-400 group-hover:text-blue-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/></svg>
                                                </a>
                                            @endif
                                        </div>
                                    </div>

                                    {{-- ADMIN NOTE (mavjud bo'lsa) --}}
                                    @if($app->admin_note || $app->reviewed_at)
                                        <div class="bg-amber-50 border border-amber-200 rounded-lg p-3">
                                            <div class="text-[11px] font-bold text-amber-700 uppercase tracking-wide mb-1">Admin izoh</div>
                                            <div class="text-sm text-amber-900">{{ $app->admin_note ?: '—' }}</div>
                                            @if($app->reviewed_at)
                                                <div class="text-[11px] text-amber-700 mt-1">Ko'rib chiqilgan: {{ $app->reviewed_at->format('d.m.Y H:i') }}</div>
                                            @endif
                                        </div>
                                    @endif

                                    {{-- HARAKATLAR --}}
                                    @php $isProcessed = in_array($app->status, ['approved', 'rejected']); @endphp
                                    <div class="flex flex-wrap items-center gap-2 pt-2 border-t border-slate-100">
                                        <div x-data="{ showApprove: false, showReject: false }" class="flex flex-wrap gap-2 w-full">
                                            @if(!$isProcessed)
                                                {{-- Qabul qilish --}}
                                                <button type="button" @click="showApprove = !showApprove; showReject = false"
                                                        class="px-3 py-2 text-xs font-bold text-white rounded-lg transition flex items-center gap-1.5"
                                                        style="background:linear-gradient(135deg,#10b981,#059669);">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                                    Qabul qilish
                                                </button>
                                                {{-- Rad etish --}}
                                                <button type="button" @click="showReject = !showReject; showApprove = false"
                                                        class="px-3 py-2 text-xs font-bold text-white rounded-lg transition flex items-center gap-1.5"
                                                        style="background:linear-gradient(135deg,#ef4444,#dc2626);">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                                    Rad etish
                                                </button>
                                            @else
                                                {{-- Ko'rilib chiqilgan ariza — qisqa kim/qachon yorlig'i --}}
                                                <div class="flex-1 text-xs text-slate-500 italic">
                                                    Ko'rib chiqilgan
                                                    @if($app->reviewed_at)
                                                        · <strong class="text-slate-700">{{ $app->reviewed_at->format('d.m.Y H:i') }}</strong>
                                                    @endif
                                                </div>
                                            @endif

                                            {{-- O'chirish (har doim) --}}
                                            <form method="POST" action="{{ route('admin.visa-applications.destroy', $app) }}"
                                                  onsubmit="return confirm('Arizani butunlay o\'chirishni tasdiqlaysizmi?');"
                                                  class="inline-block">
                                                @csrf @method('DELETE')
                                                <button type="submit"
                                                        class="px-3 py-2 text-xs font-bold rounded-lg transition flex items-center gap-1.5"
                                                        style="background:#fff;border:1px solid #cbd5e1;color:#475569;">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
                                                    O'chirish
                                                </button>
                                            </form>

                                            @if(!$isProcessed)
                                                {{-- Approve form (collapse) --}}
                                                <form x-show="showApprove" x-cloak method="POST" action="{{ route('admin.visa-applications.approve', $app) }}"
                                                      class="w-full bg-emerald-50 border border-emerald-200 rounded-lg p-3 mt-2 flex flex-col sm:flex-row gap-2">
                                                    @csrf
                                                    <input type="hidden" name="redirect_status" value="approved">
                                                    <input type="text" name="admin_note" placeholder="Izoh (ixtiyoriy)"
                                                           class="flex-1 px-3 py-2 text-sm border border-emerald-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                                                    <button type="submit" class="px-4 py-2 text-sm font-bold text-white rounded-lg whitespace-nowrap"
                                                            style="background:linear-gradient(135deg,#10b981,#059669);">Tasdiqlash</button>
                                                </form>

                                                {{-- Reject form (collapse) --}}
                                                <form x-show="showReject" x-cloak method="POST" action="{{ route('admin.visa-applications.reject', $app) }}"
                                                      class="w-full bg-red-50 border border-red-200 rounded-lg p-3 mt-2 flex flex-col sm:flex-row gap-2">
                                                    @csrf
                                                    <input type="hidden" name="redirect_status" value="rejected">
                                                    <input type="text" name="admin_note" placeholder="Sabab (tavsiya etiladi)"
                                                           class="flex-1 px-3 py-2 text-sm border border-red-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none">
                                                    <button type="submit" class="px-4 py-2 text-sm font-bold text-white rounded-lg whitespace-nowrap"
                                                            style="background:linear-gradient(135deg,#ef4444,#dc2626);">Tasdiqlash</button>
                                                </form>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                            </div>{{-- /flex-1 wrapper --}}
                        </div>
                    @endforeach
                </div>

                <div class="mt-4">{{ $applications->links() }}</div>
            @endif

        </div>
    </div>

    <script>
        function vaSelectedCheckboxes() {
            return Array.from(document.querySelectorAll('.va-row-cb'));
        }

        function vaSyncCheckAllButton() {
            const btn = document.getElementById('vaCheckAllBtn');
            if (!btn) return;

            const checkboxes = vaSelectedCheckboxes();
            const allChecked = checkboxes.length > 0 && checkboxes.every(cb => cb.checked);

            btn.textContent = allChecked ? 'Belgilashni bekor qilish' : 'Hammasini belgilash';
            btn.dataset.checked = allChecked ? '1' : '0';
        }

        function vaToggleCheckAll() {
            const checkboxes = vaSelectedCheckboxes();
            if (checkboxes.length === 0) return;

            const shouldCheck = checkboxes.some(cb => !cb.checked);
            checkboxes.forEach(cb => {
                cb.checked = shouldCheck;
            });

            window.vaBulkUpdate && window.vaBulkUpdate();
            vaSyncCheckAllButton();
        }

        // Tanlangan id'lardan vaqtinchalik form yasab, kerakli URLga POST/GET qiladi.
        function vaBulkSubmit(url, method, status = null, confirmMsg = null) {
            const ids = Array.from(document.querySelectorAll('.va-row-cb:checked')).map(el => el.value);
            if (ids.length === 0) {
                alert('Avval kamida bitta arizani tanlang.');
                return;
            }
            if (confirmMsg && !confirm(confirmMsg)) return;

            const form = document.createElement('form');
            form.method = method;
            form.action = url;
            form.style.display = 'none';

            if (method.toUpperCase() === 'POST') {
                const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                    || document.querySelector('input[name="_token"]')?.value;
                if (csrf) {
                    const t = document.createElement('input');
                    t.type = 'hidden';
                    t.name = '_token';
                    t.value = csrf;
                    form.appendChild(t);
                }
            }

            ids.forEach(id => {
                const i = document.createElement('input');
                i.type = 'hidden';
                i.name = 'ids[]';
                i.value = id;
                form.appendChild(i);
            });

            if (status) {
                const a = document.createElement('input');
                a.type = 'hidden';
                a.name = 'action';
                a.value = status;
                form.appendChild(a);
            }

            document.body.appendChild(form);
            form.submit();
        }

        document.addEventListener('DOMContentLoaded', function () {
            vaSyncCheckAllButton();
        });
    </script>
</x-app-layout>
