<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">Akademik mobillik arizalari</h2>
    </x-slot>

    <div class="py-4">
        <div class="max-w-full mx-auto sm:px-4 lg:px-6">
            @if(session('success'))
                <div class="mb-4 flex items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    {{ session('success') }}
                </div>
            @endif

            <div class="mb-4 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="relative flex flex-wrap items-center justify-between gap-4 overflow-hidden bg-gradient-to-r from-[#1f4f91] via-[#2b67ae] to-[#3b82c4] px-5 py-4 text-white">
                    <div class="absolute -right-10 -top-20 h-44 w-44 rounded-full bg-white/10"></div>
                    <div class="relative flex items-center gap-3">
                        <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-white/15 ring-1 ring-white/20">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6M7 3h7l5 5v11a2 2 0 01-2 2H7a2 2 0 01-2-2V5a2 2 0 012-2z"/>
                            </svg>
                        </span>
                        <div>
                            <h1 class="text-lg font-bold">Akademik mobillik arizalari</h1>
                            <p class="mt-0.5 text-xs text-blue-100">Yuborilgan arizalarni qidiring, filtrlang va hujjatlarini ko'ring</p>
                        </div>
                    </div>
                    <a href="{{ route('admin.academic-mobility.index') }}"
                       class="relative inline-flex items-center gap-2 rounded-lg bg-white/15 px-4 py-2 text-sm font-semibold text-white ring-1 ring-white/25 transition hover:bg-white/25">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7 7-7m-7 7h18"/>
                        </svg>
                        Talabalar ro'yxati
                    </a>
                </div>

                <div class="grid grid-cols-2 gap-px bg-slate-200 lg:grid-cols-4">
                    <div class="stat-card">
                        <span class="stat-icon bg-blue-50 text-blue-600">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6M7 3h7l5 5v11a2 2 0 01-2 2H7a2 2 0 01-2-2V5a2 2 0 012-2z"/></svg>
                        </span>
                        <div><div class="stat-label">Jami arizalar</div><div class="stat-value">{{ $stats['total'] }}</div></div>
                    </div>
                    <div class="stat-card">
                        <span class="stat-icon bg-amber-50 text-amber-600">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 2m6-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </span>
                        <div><div class="stat-label">Yangi</div><div class="stat-value">{{ $stats['pending'] }}</div></div>
                    </div>
                    <div class="stat-card">
                        <span class="stat-icon bg-emerald-50 text-emerald-600">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828L18 9.828a4 4 0 00-5.657-5.657L5.757 10.757a6 6 0 108.486 8.486L20 13.486"/></svg>
                        </span>
                        <div><div class="stat-label">Hujjatli</div><div class="stat-value">{{ $stats['withDocument'] }}</div></div>
                    </div>
                    <div class="stat-card">
                        <span class="stat-icon bg-violet-50 text-violet-600">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        </span>
                        <div><div class="stat-label">Bugun</div><div class="stat-value">{{ $stats['today'] }}</div></div>
                    </div>
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <form method="GET" action="{{ route('admin.academic-mobility.applications') }}" class="application-filter">
                    <div class="filter-row">
                        <div class="filter-item" style="flex:2;min-width:260px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#3b82f6;"></span> Qidiruv</label>
                            <input name="search" value="{{ request('search') }}" placeholder="Talaba, HEMIS ID, telefon, guruh yoki sabab..." class="filter-input">
                        </div>
                        <div class="filter-item" style="flex:1;min-width:190px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#10b981;"></span> Fakultet</label>
                            <select name="department" class="filter-input">
                                <option value="">Barchasi</option>
                                @foreach($filterOptions['departments'] as $item)
                                    <option value="{{ $item->department_id }}" @selected((string) request('department') === (string) $item->department_id)>{{ $item->department_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="filter-item" style="flex:1;min-width:220px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#06b6d4;"></span> Yo'nalish</label>
                            <select name="specialty" class="filter-input">
                                <option value="">Barchasi</option>
                                @foreach($filterOptions['specialties'] as $item)
                                    <option value="{{ $item->specialty_id }}" @selected((string) request('specialty') === (string) $item->specialty_id)>{{ $item->specialty_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="filter-item" style="min-width:130px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#8b5cf6;"></span> Kurs</label>
                            <select name="level_code" class="filter-input">
                                <option value="">Barchasi</option>
                                @foreach($filterOptions['levels'] as $item)
                                    <option value="{{ $item->level_code }}" @selected((string) request('level_code') === (string) $item->level_code)>{{ $item->level_name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="filter-row">
                        <div class="filter-item" style="min-width:150px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#f59e0b;"></span> Holat</label>
                            <select name="status" class="filter-input">
                                <option value="all" @selected(request('status', 'all') === 'all')>Barchasi</option>
                                <option value="pending" @selected(request('status') === 'pending')>Yangi</option>
                                <option value="approved" @selected(request('status') === 'approved')>Qabul qilingan</option>
                                <option value="rejected" @selected(request('status') === 'rejected')>Rad etilgan</option>
                            </select>
                        </div>
                        <div class="filter-item" style="min-width:160px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#22c55e;"></span> Hujjat</label>
                            <select name="has_document" class="filter-input">
                                <option value="">Barchasi</option>
                                <option value="yes" @selected(request('has_document') === 'yes')>Mavjud</option>
                                <option value="no" @selected(request('has_document') === 'no')>Mavjud emas</option>
                            </select>
                        </div>
                        <div class="filter-item" style="min-width:150px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#ec4899;"></span> Boshlanish sana</label>
                            <input type="date" name="date_from" value="{{ request('date_from') }}" class="filter-input">
                        </div>
                        <div class="filter-item" style="min-width:150px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#f97316;"></span> Tugash sana</label>
                            <input type="date" name="date_to" value="{{ request('date_to') }}" class="filter-input">
                        </div>
                        <div class="filter-actions">
                            <button type="submit" class="filter-submit">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                </svg>
                                Filtrlash
                            </button>
                            <a href="{{ route('admin.academic-mobility.applications') }}" class="filter-clear">Tozalash</a>
                        </div>
                    </div>
                </form>

                <div class="table-summary">
                    <span>Natija: <strong>{{ $applications->total() }}</strong> ta ariza</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="application-table">
                        <thead>
                            <tr>
                                <th>Talaba</th>
                                <th>O'qish ma'lumoti</th>
                                <th>Telefon</th>
                                <th>Ariza sababi</th>
                                <th>Hujjat</th>
                                <th>Yuborgan / sana</th>
                                <th>Holat</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($applications as $application)
                                @php
                                    $statusStyles = [
                                        'pending' => ['Yangi', 'status-pending'],
                                        'approved' => ['Qabul qilingan', 'status-approved'],
                                        'rejected' => ['Rad etilgan', 'status-rejected'],
                                    ];
                                    [$statusLabel, $statusClass] = $statusStyles[$application->status] ?? [$application->status, 'status-default'];
                                @endphp
                                <tr>
                                    <td>
                                        <div class="student-name">{{ $application->student?->full_name ?? 'Talaba topilmadi' }}</div>
                                        <div class="cell-muted">HEMIS: {{ $application->student?->hemis_id ?? '-' }}</div>
                                        <div class="cell-muted">ID: {{ $application->student?->student_id_number ?? '-' }}</div>
                                    </td>
                                    <td>
                                        <div class="faculty-text">{{ $application->student?->department_name ?? '-' }}</div>
                                        <div class="specialty-text">{{ $application->student?->specialty_name ?? '-' }}</div>
                                        <div class="mt-1 flex flex-wrap gap-1">
                                            <span class="course-badge">{{ $application->student?->level_name ?? '-' }}</span>
                                            <span class="group-badge">{{ $application->student?->group_name ?? '-' }}</span>
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap">{{ $application->phone }}</td>
                                    <td><div class="reason-text" title="{{ $application->reason }}">{{ $application->reason }}</div></td>
                                    <td>
                                        @if($application->document_path)
                                            <a href="{{ route('admin.academic-mobility.document', $application) }}" class="document-btn" title="{{ $application->document_name }}">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828L18 9.828a4 4 0 00-5.657-5.657L5.757 10.757a6 6 0 108.486 8.486L20 13.486"/>
                                                </svg>
                                                Yuklash
                                            </a>
                                            <div class="mt-1 max-w-[150px] truncate text-[10px] text-slate-400">{{ $application->document_name }}</div>
                                            @if($application->document_size)
                                                <div class="text-[10px] text-slate-400">{{ number_format($application->document_size / 1048576, 2) }} MB</div>
                                            @endif
                                        @else
                                            <span class="no-document">Hujjat yo'q</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="font-medium text-slate-700">{{ $application->created_by_name ?? '-' }}</div>
                                        <div class="cell-muted">{{ $application->created_at?->format('d.m.Y H:i') }}</div>
                                    </td>
                                    <td><span class="status-badge {{ $statusClass }}">{{ $statusLabel }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="empty-state">Tanlangan filtrlar bo'yicha arizalar topilmadi.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($applications->hasPages())
                    <div class="border-t border-slate-200 px-4 py-3">{{ $applications->links() }}</div>
                @endif
            </div>
        </div>
    </div>

    <style>
        .stat-card { display:flex;align-items:center;gap:12px;background:#fff;padding:14px 18px; }
        .stat-icon { display:flex;width:42px;height:42px;align-items:center;justify-content:center;border-radius:12px; }
        .stat-icon svg { width:21px;height:21px; }
        .stat-label { font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#64748b; }
        .stat-value { margin-top:2px;font-size:22px;font-weight:800;line-height:1;color:#172554; }
        .application-filter { padding:16px 20px 12px;background:linear-gradient(135deg,#f0f4f8,#e8edf5);border-bottom:2px solid #dbe4ef; }
        .filter-row { display:flex;gap:10px;flex-wrap:wrap;margin-bottom:10px;align-items:flex-end; }
        .filter-row:last-child { margin-bottom:0; }
        .filter-label { display:flex;align-items:center;gap:5px;margin-bottom:4px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#475569; }
        .fl-dot { width:7px;height:7px;border-radius:50%;display:inline-block;flex-shrink:0; }
        .filter-input { width:100%;height:36px;padding:0 10px;border:1px solid #cbd5e1;border-radius:8px;background:#fff;font-size:.8rem;font-weight:500;color:#1e293b;box-shadow:0 1px 2px rgba(0,0,0,.04); }
        .filter-input:focus { outline:none;border-color:#2b5ea7;box-shadow:0 0 0 2px rgba(43,94,167,.18); }
        .filter-actions { display:flex;align-items:center;gap:8px; }
        .filter-submit { display:inline-flex;height:36px;align-items:center;gap:7px;border:0;border-radius:8px;background:linear-gradient(135deg,#2b5ea7,#3b7ddb);padding:0 18px;font-size:13px;font-weight:700;color:#fff;box-shadow:0 2px 8px rgba(43,94,167,.25); }
        .filter-clear { display:inline-flex;height:36px;align-items:center;border-radius:8px;background:#fff;padding:0 16px;font-size:13px;font-weight:600;color:#475569;border:1px solid #cbd5e1; }
        .table-summary { display:flex;align-items:center;justify-content:space-between;background:#f8fafc;padding:9px 20px;font-size:12px;color:#64748b;border-bottom:1px solid #e2e8f0; }
        .application-table { width:100%;border-collapse:separate;border-spacing:0;font-size:12.5px; }
        .application-table thead tr { background:linear-gradient(135deg,#e8edf5,#dbe4ef,#d1d9e6); }
        .application-table th { padding:11px 10px;text-align:left;font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#334155;white-space:nowrap;border-bottom:2px solid #cbd5e1; }
        .application-table td { padding:11px 10px;vertical-align:middle;line-height:1.4;border-bottom:1px solid #eef2f7; }
        .application-table tbody tr:nth-child(even) { background:#f8fafc; }
        .application-table tbody tr:nth-child(odd) { background:#fff; }
        .application-table tbody tr:hover { background:#eff6ff;box-shadow:inset 4px 0 0 #2b5ea7; }
        .student-name { font-weight:700;color:#1e40af;white-space:nowrap; }
        .cell-muted { margin-top:2px;font-size:10.5px;color:#94a3b8; }
        .faculty-text { font-weight:600;color:#047857; }
        .specialty-text { max-width:230px;color:#0e7490;white-space:normal; }
        .course-badge { border:1px solid #ddd6fe;border-radius:5px;background:#ede9fe;padding:2px 6px;font-size:10px;font-weight:600;color:#5b21b6; }
        .group-badge { border-radius:5px;background:#1e4b8a;padding:2px 6px;font-size:10px;font-weight:600;color:#fff; }
        .reason-text { max-width:300px;display:-webkit-box;overflow:hidden;-webkit-box-orient:vertical;-webkit-line-clamp:3;white-space:normal;color:#475569; }
        .document-btn { display:inline-flex;align-items:center;gap:5px;border-radius:6px;background:#e0f2fe;padding:5px 9px;font-size:11px;font-weight:700;color:#0369a1;transition:.15s; }
        .document-btn:hover { background:#bae6fd;color:#075985; }
        .no-document { display:inline-block;border-radius:6px;background:#f1f5f9;padding:5px 8px;font-size:10.5px;font-weight:600;color:#94a3b8; }
        .status-badge { display:inline-block;border-radius:999px;padding:4px 9px;font-size:10.5px;font-weight:700;white-space:nowrap; }
        .status-pending { background:#fef3c7;color:#b45309; }
        .status-approved { background:#d1fae5;color:#047857; }
        .status-rejected { background:#fee2e2;color:#b91c1c; }
        .status-default { background:#e2e8f0;color:#475569; }
        .empty-state { padding:48px !important;text-align:center;color:#64748b; }
        @media (max-width:640px) {
            .stat-card { padding:12px; }
            .stat-value { font-size:19px; }
            .application-filter { padding:12px; }
        }
    </style>
</x-app-layout>
