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
        <div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8 space-y-4">

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
                <a href="{{ route('admin.visa-applications.stats-list', ['type' => 'total']) }}"
                   class="block text-left rounded-2xl shadow-sm border border-sky-200 p-4 transition hover:-translate-y-0.5"
                   style="background:linear-gradient(135deg,#eff6ff,#dbeafe);">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="text-[11px] font-bold uppercase tracking-wide text-sky-700">Xorijiy fuqarolar jami</div>
                            <div class="mt-2 text-3xl font-black text-slate-800">{{ number_format($visaStats['total_foreign_citizens'] ?? 0) }}</div>
                            <div class="mt-2 text-xs text-slate-600">Bosib umumiy ro'yxatni oching</div>
                        </div>
                        <div class="w-10 h-10 rounded-xl flex items-center justify-center text-sky-700" style="background:rgba(255,255,255,0.7);">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 17L17 7M17 7H9M17 7v8"/></svg>
                        </div>
                    </div>
                </a>
                <a href="{{ route('admin.visa-applications.stats-list', ['type' => 'submitted']) }}"
                   class="block text-left rounded-2xl shadow-sm border border-emerald-200 p-4 transition hover:-translate-y-0.5"
                   style="background:linear-gradient(135deg,#ecfdf5,#d1fae5);">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="text-[11px] font-bold uppercase tracking-wide text-emerald-700">Ariza topshirganlar</div>
                            <div class="mt-2 text-3xl font-black text-slate-800">{{ number_format($visaStats['submitted_applications'] ?? 0) }}</div>
                            <div class="mt-2 text-xs text-slate-600">Bosib topshirganlar ro'yxatini oching</div>
                        </div>
                        <div class="w-10 h-10 rounded-xl flex items-center justify-center text-emerald-700" style="background:rgba(255,255,255,0.7);">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 17L17 7M17 7H9M17 7v8"/></svg>
                        </div>
                    </div>
                </a>
                <a href="{{ route('admin.visa-applications.stats-list', ['type' => 'not_submitted']) }}"
                   class="block text-left rounded-2xl shadow-sm border border-amber-200 p-4 transition hover:-translate-y-0.5"
                   style="background:linear-gradient(135deg,#fff7ed,#fde68a);">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="text-[11px] font-bold uppercase tracking-wide text-amber-700">Farqi</div>
                            <div class="mt-2 text-3xl font-black text-slate-800">{{ number_format($visaStats['not_submitted'] ?? 0) }}</div>
                            <div class="mt-2 text-xs text-slate-600">Bosib topshirmaganlar ro'yxatini oching</div>
                        </div>
                        <div class="w-10 h-10 rounded-xl flex items-center justify-center text-amber-700" style="background:rgba(255,255,255,0.7);">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 17L17 7M17 7H9M17 7v8"/></svg>
                        </div>
                    </div>
                </a>
            </div>

            {{--
            <template x-teleport="body">
                <div x-show="statsModal"
                     x-cloak
                     class="fixed inset-0 z-[90] overflow-hidden"
                     style="background:rgba(15,23,42,0.55);backdrop-filter:blur(5px);"
                     @click.self="statsModal = null">
                    <div class="h-screen w-full flex items-start justify-center p-4 sm:p-6">
                        <div class="max-w-6xl w-full h-[calc(100vh-2rem)] sm:h-[calc(100vh-3rem)] min-h-0 bg-white rounded-3xl shadow-2xl border border-slate-200 overflow-hidden flex flex-col">
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

                    <div class="flex-1 min-h-0 overflow-y-auto overscroll-contain">
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
            --}}

            {{-- FILTER + STATS --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="px-6 py-3 border-b border-gray-100 flex items-center justify-between gap-2 flex-wrap" style="background: linear-gradient(135deg, #e8edf5, #dbe4ef);">
                    <div class="font-bold text-gray-800 text-sm">Jadval filtrlari</div>
                    <div class="flex items-center gap-2">
                        <a href="{{ route('admin.visa-applications.export', request()->query()) }}"
                           class="px-3 py-1.5 text-xs font-bold rounded-lg border flex items-center gap-1.5"
                           style="background:#fff;border-color:#10b981;color:#047857;">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                            Excel
                        </a>
                        <a href="{{ route('admin.visa-applications.index') }}"
                           class="px-3 py-1.5 text-xs font-bold rounded-lg border flex items-center gap-1.5"
                           style="background:#fff;border-color:#cbd5e1;color:#475569;">
                            Tozalash
                        </a>
                    </div>
                </div>
                <form method="GET" action="{{ route('admin.visa-applications.index') }}" class="p-4 border-t border-slate-100">
                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-3">
                        <div>
                            <label class="va-filter-label">Talaba ID</label>
                            <input type="text" name="student_id_number" value="{{ request('student_id_number') }}" class="va-filter-input" placeholder="Talaba ID">
                        </div>
                        <div>
                            <label class="va-filter-label">F.I.Sh</label>
                            <input type="text" name="full_name" value="{{ request('full_name') }}" class="va-filter-input" placeholder="F.I.Sh">
                        </div>
                        <div>
                            <label class="va-filter-label">Davlati</label>
                            <select name="country_name" class="va-filter-input">
                                <option value="">Barchasi</option>
                                @foreach($filterOptions['countries'] as $value)
                                    <option value="{{ $value }}" @selected(request('country_name') === $value)>{{ $value }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="va-filter-label">Kurs</label>
                            <select name="course_name" class="va-filter-input">
                                <option value="">Barchasi</option>
                                @foreach($filterOptions['courses'] as $value)
                                    <option value="{{ $value }}" @selected(request('course_name') === $value)>{{ $value }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="va-filter-label">Fakultet</label>
                            <select name="department_name" class="va-filter-input">
                                <option value="">Barchasi</option>
                                @foreach($filterOptions['departments'] as $value)
                                    <option value="{{ $value }}" @selected(request('department_name') === $value)>{{ $value }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="va-filter-label">Yo'nalish</label>
                            <select name="specialty_name" class="va-filter-input">
                                <option value="">Barchasi</option>
                                @foreach($filterOptions['specialties'] as $value)
                                    <option value="{{ $value }}" @selected(request('specialty_name') === $value)>{{ $value }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="va-filter-label">Guruh</label>
                            <select name="group_name" class="va-filter-input">
                                <option value="">Barchasi</option>
                                @foreach($filterOptions['groups'] as $value)
                                    <option value="{{ $value }}" @selected(request('group_name') === $value)>{{ $value }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="va-filter-label">Firma</label>
                            <select name="firm_display" class="va-filter-input">
                                <option value="">Barchasi</option>
                                @foreach($filterOptions['firms'] as $value)
                                    <option value="{{ $value }}" @selected(request('firm_display') === $value)>{{ $value }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="va-filter-label">Ariza berganligi</label>
                            <select name="application_presence" class="va-filter-input">
                                <option value="">Barchasi</option>
                                <option value="submitted" @selected(($applicationPresence ?? '') === 'submitted')>Berilgan</option>
                                <option value="not_submitted" @selected(($applicationPresence ?? '') === 'not_submitted')>Berilmagan</option>
                            </select>
                        </div>
                        <div>
                            <label class="va-filter-label">Holat</label>
                            <select name="status" class="va-filter-input">
                                <option value="">Barchasi</option>
                                @foreach($statusMeta as $key => $m)
                                    <option value="{{ $key }}" @selected(($status ?? '') === $key)>{{ $m['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="mt-3 flex items-center justify-between gap-3 flex-wrap">
                        <div class="flex flex-wrap gap-2">
                            <span class="px-3 py-1.5 text-xs font-bold rounded-lg" style="background:#eff6ff;color:#1d4ed8;">Jami: {{ $visaStats['total_foreign_citizens'] ?? 0 }}</span>
                            <span class="px-3 py-1.5 text-xs font-bold rounded-lg" style="background:#ecfdf5;color:#047857;">Berilgan: {{ $visaStats['submitted_applications'] ?? 0 }}</span>
                            <span class="px-3 py-1.5 text-xs font-bold rounded-lg" style="background:#fff7ed;color:#b45309;">Berilmagan: {{ $visaStats['not_submitted'] ?? 0 }}</span>
                        </div>
                        <button type="submit" class="px-4 py-2 text-xs font-bold rounded-lg text-white" style="background:linear-gradient(135deg,#2b5ea7,#3b7ddb);">
                            Filtrlash
                        </button>
                    </div>
                </form>
                <div class="px-4 py-2 border-t border-slate-100 text-xs text-slate-500">
                    Holat hisoblagichlari:
                    @foreach($statusMeta as $key => $m)
                        <span class="inline-flex items-center gap-1 mr-3">
                            <span class="w-2 h-2 rounded-full" style="background:{{ $m['fg'] }};"></span>
                            {{ $m['label'] }}: {{ $counts[$key] ?? 0 }}
                        </span>
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
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden" x-data="{ open: null }">
                    <div class="overflow-x-auto">
                        <table class="va-table">
                            <thead>
                                <tr>
                                    <th style="width:48px;text-align:center;"></th>
                                    <th>Talaba ID</th>
                                    <th>F.I.Sh</th>
                                    <th>Davlati</th>
                                    <th>Kurs</th>
                                    <th>Fakultet</th>
                                    <th>Yo'nalish</th>
                                    <th>Guruh</th>
                                    <th>Firma</th>
                                    <th>Ariza berganligi</th>
                                    <th>Holat</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($applications as $row)
                                    @php
                                        $student = $row->student;
                                        $app = $row->application;
                                        $profile = $row->student_profile ?? [];
                                        $m = $statusMeta[$row->application_status ?? 'pending'] ?? $statusMeta['pending'];
                                        $isProcessed = $app ? in_array($app->status, ['approved', 'rejected']) : false;
                                    @endphp
                                    <tr class="va-main-row" @click="open = open === 'row-{{ $student->id }}' ? null : 'row-{{ $student->id }}'">
                                        <td style="text-align:center;" @click.stop>
                                            @if($app)
                                                <input type="checkbox"
                                                       class="va-row-cb w-4 h-4 cursor-pointer accent-blue-600"
                                                       value="{{ $app->id }}"
                                                       onchange="window.vaBulkUpdate && window.vaBulkUpdate();">
                                            @else
                                                <span class="va-empty-mark">—</span>
                                            @endif
                                        </td>
                                        <td class="va-muted-cell">{{ $profile['student_id_number'] ?? '—' }}</td>
                                        <td>
                                            <div class="va-name-cell">
                                                <div class="va-name-text">{{ $student->full_name }}</div>
                                                <div class="va-subtext">
                                                    @if($app)
                                                        Ariza #{{ $row->application_number }} · {{ optional($row->created_at)->format('d.m.Y H:i') ?? '—' }}
                                                    @else
                                                        Hali ariza topshirmagan
                                                    @endif
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="va-country-cell">
                                                <div class="va-country-main">{{ $profile['country_name'] ?? '—' }}</div>
                                                @if(!empty($profile['citizenship_name']) && ($profile['citizenship_name'] !== ($profile['country_name'] ?? null)))
                                                    <div class="va-subtext">{{ $profile['citizenship_name'] }}</div>
                                                @endif
                                            </div>
                                        </td>
                                        <td><span class="va-chip va-chip-violet">{{ $profile['course_name'] ?? '—' }}</span></td>
                                        <td><span class="va-text-emerald">{{ $profile['department_name'] ?? '—' }}</span></td>
                                        <td><span class="va-text-cyan" title="{{ $profile['specialty_name'] ?? '' }}">{{ \Illuminate\Support\Str::limit($profile['specialty_name'] ?? '—', 28) }}</span></td>
                                        <td><span class="va-chip va-chip-indigo">{{ $profile['group_name'] ?? '—' }}</span></td>
                                        <td><span class="va-firm-text">{{ $profile['firm_display'] ?? '—' }}</span></td>
                                        <td>
                                            @if($row->submitted)
                                                <span class="va-status-pill" style="background:#dcfce7;color:#166534;border:1px solid #bbf7d0;">Berilgan</span>
                                            @else
                                                <span class="va-status-pill" style="background:#fff7ed;color:#b45309;border:1px solid #fcd34d;">Berilmagan</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($app)
                                                <div class="flex flex-col gap-1">
                                                    <span class="va-status-pill"
                                                          style="background:{{ $m['bg'] }};color:{{ $m['fg'] }};border:1px solid {{ $m['border'] }};">
                                                        {{ $m['label'] }}
                                                    </span>
                                                    @if($row->reviewed_at)
                                                        <span class="va-subtext">{{ $row->reviewed_at->format('d.m.Y H:i') }}</span>
                                                    @endif
                                                </div>
                                            @else
                                                <span class="va-empty-mark">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr x-show="open === 'row-{{ $student->id }}'" x-cloak class="va-detail-row">
                                        <td colspan="11" class="p-0">
                                            <div class="va-detail-wrap" @click.stop>
                                                <div class="p-4 sm:p-5 space-y-4">
                                                    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
                                                        <div>
                                                            <div class="va-section-title">Talaba ma'lumotlari</div>
                                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-2 text-sm bg-slate-50 rounded-lg p-3 border border-slate-200">
                                                                <div><span class="text-slate-500">F.I.Sh:</span> <strong class="text-slate-800">{{ $student->full_name }}</strong></div>
                                                                <div><span class="text-slate-500">Talaba ID:</span> <strong class="text-slate-800">{{ $profile['student_id_number'] ?? '—' }}</strong></div>
                                                                <div><span class="text-slate-500">Davlati:</span> <strong class="text-slate-800">{{ $profile['country_name'] ?? '—' }}</strong></div>
                                                                <div><span class="text-slate-500">Fuqaroligi:</span> <strong class="text-slate-800">{{ $profile['citizenship_name'] ?? '—' }}</strong></div>
                                                                <div><span class="text-slate-500">Fakultet:</span> <strong class="text-slate-800">{{ $profile['department_name'] ?? '—' }}</strong></div>
                                                                <div><span class="text-slate-500">Yo'nalish:</span> <strong class="text-slate-800">{{ $profile['specialty_name'] ?? '—' }}</strong></div>
                                                                <div><span class="text-slate-500">Guruh:</span> <strong class="text-slate-800">{{ $profile['group_name'] ?? '—' }}</strong></div>
                                                                <div><span class="text-slate-500">Firma:</span> <strong class="text-slate-800">{{ $profile['firm_display'] ?? '—' }}</strong></div>
                                                                @if($app)
                                                                    <div><span class="text-slate-500">Tug'ilgan sana:</span> <strong class="text-slate-800">{{ optional($app->birth_date)->format('d.m.Y') ?? '—' }}</strong></div>
                                                                    <div><span class="text-slate-500">Pasport raqami:</span> <strong class="text-slate-800">{{ $app->passport_number }}</strong></div>
                                                                    <div><span class="text-slate-500">Telefon:</span> <strong class="text-slate-800">{{ $app->phone_number }}</strong></div>
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
                                                                @endif
                                                            </div>
                                                        </div>
                                                        <div>
                                                            <div class="va-section-title">Yuklangan fayllar</div>
                                                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                                                                @if($app && $app->passport_pdf_path)
                                                                    <a href="{{ route('admin.visa-applications.file', [$app, 'passport']) }}" target="_blank" rel="noopener" class="va-doc-card">
                                                                        <div class="va-doc-icon" style="background:#fee2e2;color:#dc2626;">PDF</div>
                                                                        <div class="flex-1 min-w-0">
                                                                            <div class="va-doc-title">Passport copies</div>
                                                                            <div class="va-doc-subtitle">Yangi tabda ochiladi</div>
                                                                        </div>
                                                                    </a>
                                                                @endif
                                                                @if($app && $app->application_pdf_path)
                                                                    <a href="{{ route('admin.visa-applications.file', [$app, 'application']) }}" target="_blank" rel="noopener" class="va-doc-card">
                                                                        <div class="va-doc-icon" style="background:#dbeafe;color:#2563eb;">PDF</div>
                                                                        <div class="flex-1 min-w-0">
                                                                            <div class="va-doc-title">Application</div>
                                                                            <div class="va-doc-subtitle">Yangi tabda ochiladi</div>
                                                                        </div>
                                                                    </a>
                                                                @endif
                                                                @if($app && $app->receipt_pdf_path)
                                                                    <a href="{{ route('admin.visa-applications.file', [$app, 'billing-document']) }}" target="_blank" rel="noopener" class="va-doc-card">
                                                                        <div class="va-doc-icon" style="background:#d1fae5;color:#059669;">PDF</div>
                                                                        <div class="flex-1 min-w-0">
                                                                            <div class="va-doc-title">Billing document</div>
                                                                            <div class="va-doc-subtitle">Yangi tabda ochiladi</div>
                                                                        </div>
                                                                    </a>
                                                                @endif
                                                                @if(!$app)
                                                                    <div class="sm:col-span-3 rounded-lg border border-dashed border-amber-300 bg-amber-50 px-4 py-6 text-sm text-amber-700">
                                                                        Bu talaba hali visa application topshirmagan.
                                                                    </div>
                                                                @endif
                                                            </div>
                                                        </div>
                                                    </div>

                                                    @if($app && ($app->admin_note || $app->reviewed_at))
                                                        <div class="bg-amber-50 border border-amber-200 rounded-lg p-3">
                                                            <div class="text-[11px] font-bold text-amber-700 uppercase tracking-wide mb-1">Admin izoh</div>
                                                            <div class="text-sm text-amber-900">{{ $app->admin_note ?: '—' }}</div>
                                                            @if($app->reviewed_at)
                                                                <div class="text-[11px] text-amber-700 mt-1">Ko'rib chiqilgan: {{ $app->reviewed_at->format('d.m.Y H:i') }}</div>
                                                            @endif
                                                        </div>
                                                    @endif

                                                    <div class="flex flex-wrap items-center gap-2 pt-2 border-t border-slate-100" x-data="{ showApprove: false, showReject: false }">
                                                        @if($app && !$isProcessed)
                                                            <button type="button" @click="showApprove = !showApprove; showReject = false"
                                                                    class="px-3 py-2 text-xs font-bold text-white rounded-lg transition flex items-center gap-1.5"
                                                                    style="background:linear-gradient(135deg,#10b981,#059669);">
                                                                Qabul qilish
                                                            </button>
                                                            <button type="button" @click="showReject = !showReject; showApprove = false"
                                                                    class="px-3 py-2 text-xs font-bold text-white rounded-lg transition flex items-center gap-1.5"
                                                                    style="background:linear-gradient(135deg,#ef4444,#dc2626);">
                                                                Rad etish
                                                            </button>
                                                        @endif

                                                        @if($app)
                                                        <form method="POST" action="{{ route('admin.visa-applications.destroy', $app) }}"
                                                              onsubmit="return confirm('Arizani butunlay o\'chirishni tasdiqlaysizmi?');"
                                                              class="inline-block">
                                                            @csrf @method('DELETE')
                                                            <button type="submit"
                                                                    class="px-3 py-2 text-xs font-bold rounded-lg transition"
                                                                    style="background:#fff;border:1px solid #cbd5e1;color:#475569;">
                                                                O'chirish
                                                            </button>
                                                        </form>
                                                        @endif

                                                        @if($app && !$isProcessed)
                                                            <form x-show="showApprove" x-cloak method="POST" action="{{ route('admin.visa-applications.approve', $app) }}"
                                                                  class="w-full bg-emerald-50 border border-emerald-200 rounded-lg p-3 mt-2 flex flex-col sm:flex-row gap-2">
                                                                @csrf
                                                                <input type="hidden" name="redirect_status" value="approved">
                                                                <input type="text" name="admin_note" placeholder="Izoh (ixtiyoriy)"
                                                                       class="flex-1 px-3 py-2 text-sm border border-emerald-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                                                                <button type="submit" class="px-4 py-2 text-sm font-bold text-white rounded-lg whitespace-nowrap"
                                                                        style="background:linear-gradient(135deg,#10b981,#059669);">Tasdiqlash</button>
                                                            </form>

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
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="mt-4">{{ $applications->links() }}</div>
            @endif

        </div>
    </div>

    <style>
        .va-filter-label { display: block; margin-bottom: 6px; font-size: 11px; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.04em; }
        .va-filter-input { width: 100%; height: 38px; padding: 0 10px; border: 1px solid #cbd5e1; border-radius: 10px; background: #fff; font-size: 13px; color: #0f172a; box-shadow: 0 1px 2px rgba(0,0,0,0.04); }
        .va-filter-input:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,0.15); }
        .va-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 13px; }
        .va-table thead { position: sticky; top: 0; z-index: 10; }
        .va-table thead tr { background: linear-gradient(135deg, #e8edf5, #dbe4ef, #d1d9e6); }
        .va-table th { padding: 12px 10px; text-align: left; font-weight: 700; font-size: 11px; color: #334155; text-transform: uppercase; letter-spacing: 0.05em; white-space: nowrap; border-bottom: 2px solid #cbd5e1; }
        .va-table td { padding: 10px 10px; vertical-align: middle; line-height: 1.4; border-bottom: 1px solid #eef2f7; }
        .va-main-row { cursor: pointer; transition: all 0.15s; }
        .va-main-row:nth-child(4n-3),
        .va-main-row:nth-child(4n-2) { background: #fff; }
        .va-main-row:nth-child(4n-1),
        .va-main-row:nth-child(4n) { background: #f8fafc; }
        .va-main-row:hover { background: #eff6ff !important; box-shadow: inset 4px 0 0 #2b5ea7; }
        .va-main-row td:first-child { background: inherit; }
        .va-detail-row td { background: #f8fbff; border-bottom: 1px solid #dbe4ef; }
        .va-detail-wrap { border-top: 1px solid #dbe4ef; }
        .va-name-text { color: #1e40af; font-weight: 800; font-size: 13px; }
        .va-subtext { font-size: 11px; color: #94a3b8; margin-top: 2px; }
        .va-muted-cell { color: #64748b; font-size: 12px; }
        .va-country-main { color: #0f172a; font-weight: 600; font-size: 12.5px; }
        .va-chip { display: inline-block; padding: 3px 9px; border-radius: 7px; font-size: 11.5px; font-weight: 700; line-height: 1.4; white-space: nowrap; }
        .va-chip-violet { background: #ede9fe; color: #5b21b6; border: 1px solid #ddd6fe; }
        .va-chip-indigo { background: linear-gradient(135deg, #1a3268, #2b5ea7); color: #fff; }
        .va-text-emerald { color: #047857; font-size: 12.5px; font-weight: 600; }
        .va-text-cyan { color: #0e7490; font-size: 12.5px; font-weight: 600; display: inline-block; max-width: 220px; white-space: normal; word-break: break-word; }
        .va-firm-text { color: #1f2937; font-size: 12.5px; font-weight: 500; }
        .va-status-pill { display: inline-flex; align-items: center; justify-content: center; padding: 4px 11px; border-radius: 999px; font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.04em; white-space: nowrap; }
        .va-section-title { font-size: 11px; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 8px; }
        .va-empty-mark { color: #cbd5e1; font-size: 14px; font-weight: 700; }
        .va-doc-card { display: flex; align-items: center; gap: 10px; padding: 12px; background: #fff; border: 2px solid #e2e8f0; border-radius: 12px; transition: all 0.15s; }
        .va-doc-card:hover { border-color: #60a5fa; }
        .va-doc-icon { width: 40px; height: 40px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 800; flex-shrink: 0; }
        .va-doc-title { font-size: 13px; font-weight: 700; color: #0f172a; }
        .va-doc-subtitle { font-size: 11px; color: #94a3b8; margin-top: 1px; }

        @media (max-width: 1024px) {
            .va-table th, .va-table td { padding: 9px 8px; }
            .va-text-cyan { max-width: 180px; }
        }
    </style>

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
