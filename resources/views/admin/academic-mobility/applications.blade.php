<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">Akademik mobillik arizalari</h2>
    </x-slot>

    <div class="py-4">
        <div class="max-w-full mx-auto sm:px-4 lg:px-6">
            @if(session('success'))
                <div class="am-alert">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    {{ session('success') }}
                </div>
            @endif

            <section class="am-overview">
                <header class="am-hero">
                    <div class="am-hero-title">
                        <span class="am-hero-icon">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6M7 3h7l5 5v11a2 2 0 01-2 2H7a2 2 0 01-2-2V5a2 2 0 012-2z"/>
                            </svg>
                        </span>
                        <div>
                            <h1>Akademik mobillik arizalari</h1>
                            <p>Yuborilgan arizalar va biriktirilgan hujjatlar</p>
                        </div>
                    </div>
                    <a href="{{ route('admin.academic-mobility.index') }}" class="am-back-btn">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7 7-7m-7 7h18"/>
                        </svg>
                        Talabalar ro'yxati
                    </a>
                </header>

                <div class="am-stats">
                    <article class="am-stat am-stat-blue">
                        <span class="am-stat-icon">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6M7 3h7l5 5v11a2 2 0 01-2 2H7a2 2 0 01-2-2V5a2 2 0 012-2z"/>
                            </svg>
                        </span>
                        <div><span>Jami arizalar</span><strong>{{ $stats['total'] }}</strong></div>
                    </article>
                    <article class="am-stat am-stat-amber">
                        <span class="am-stat-icon">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 2m6-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </span>
                        <div><span>Yangi</span><strong>{{ $stats['pending'] }}</strong></div>
                    </article>
                    <article class="am-stat am-stat-green">
                        <span class="am-stat-icon">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828L18 9.828a4 4 0 00-5.657-5.657L5.757 10.757a6 6 0 108.486 8.486L20 13.486"/>
                            </svg>
                        </span>
                        <div><span>Hujjatli</span><strong>{{ $stats['withDocument'] }}</strong></div>
                    </article>
                    <article class="am-stat am-stat-violet">
                        <span class="am-stat-icon">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 012-2h10a2 2 0 012 2v12a2 2 0 01-2 2z"/>
                            </svg>
                        </span>
                        <div><span>Bugun</span><strong>{{ $stats['today'] }}</strong></div>
                    </article>
                </div>
            </section>

            <section class="am-list-card">
                <form method="GET" action="{{ route('admin.academic-mobility.applications') }}" class="am-search-panel">
                    <div class="am-search-heading">
                        <span>Talaba qidirish</span>
                        <small>Ism yoki familiyani kiriting</small>
                    </div>
                    <div class="am-search-controls">
                        <div class="am-search-input">
                            <span>
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                </svg>
                            </span>
                            <input name="search" value="{{ request('search') }}" placeholder="Talabaning ism-familiyasi...">
                        </div>
                        <button type="submit" class="am-search-btn">Qidirish</button>
                        @if(request()->filled('search'))
                            <a href="{{ route('admin.academic-mobility.applications') }}" class="am-clear-btn">Tozalash</a>
                        @endif
                    </div>
                </form>

                <div class="am-result-bar">
                    <span>Topildi: <strong>{{ $applications->total() }}</strong> ta ariza</span>
                </div>

                <div class="am-table-scroll">
                    <table class="am-table">
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
                                        'pending' => ['Yangi', 'am-status-pending'],
                                        'approved' => ['Qabul qilingan', 'am-status-approved'],
                                        'rejected' => ['Rad etilgan', 'am-status-rejected'],
                                    ];
                                    [$statusLabel, $statusClass] = $statusStyles[$application->status] ?? [$application->status, 'am-status-default'];
                                @endphp
                                <tr>
                                    <td>
                                        <div class="am-student-name">{{ $application->student?->full_name ?? 'Talaba topilmadi' }}</div>
                                        <div class="am-muted">HEMIS: {{ $application->student?->hemis_id ?? '-' }}</div>
                                        <div class="am-muted">ID: {{ $application->student?->student_id_number ?? '-' }}</div>
                                    </td>
                                    <td>
                                        <div class="am-faculty">{{ $application->student?->department_name ?? '-' }}</div>
                                        <div class="am-specialty">{{ $application->student?->specialty_name ?? '-' }}</div>
                                        <div class="am-study-badges">
                                            <span class="am-course">{{ $application->student?->level_name ?? '-' }}</span>
                                            <span class="am-group">{{ $application->student?->group_name ?? '-' }}</span>
                                        </div>
                                    </td>
                                    <td class="am-phone">{{ $application->phone }}</td>
                                    <td><div class="am-reason" title="{{ $application->reason }}">{{ $application->reason }}</div></td>
                                    <td>
                                        @if($application->document_path)
                                            <a href="{{ route('admin.academic-mobility.document', $application) }}" target="_blank" rel="noopener noreferrer" class="am-document-btn" title="{{ $application->document_name }}">
                                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 16V4m0 12 4-4m-4 4-4-4M5 20h14"/>
                                                </svg>
                                                Yuklash
                                            </a>
                                            <div class="am-file-name" title="{{ $application->document_name }}">{{ $application->document_name }}</div>
                                        @else
                                            <span class="am-no-document">Hujjat yo'q</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="am-creator">{{ $application->created_by_name ?? '-' }}</div>
                                        <div class="am-muted">{{ $application->created_at?->format('d.m.Y H:i') }}</div>
                                    </td>
                                    <td><span class="am-status {{ $statusClass }}">{{ $statusLabel }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="am-empty">Ism-familiya bo'yicha arizalar topilmadi.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($applications->hasPages())
                    <div class="am-pagination">{{ $applications->links() }}</div>
                @endif
            </section>
        </div>
    </div>

    <style>
        .am-alert { display:flex;align-items:center;gap:10px;margin-bottom:14px;border:1px solid #a7f3d0;border-radius:10px;background:#ecfdf5;padding:11px 14px;font-size:13px;font-weight:600;color:#047857; }
        .am-alert svg { width:19px;height:19px;flex:0 0 19px; }
        .am-overview,.am-list-card { overflow:hidden;border:1px solid #dbe4ef;border-radius:12px;background:#fff;box-shadow:0 4px 16px rgba(15,23,42,.06); }
        .am-overview { margin-bottom:14px; }
        .am-hero { min-height:72px;display:flex;align-items:center;justify-content:space-between;gap:16px;padding:14px 18px;color:#fff;background:linear-gradient(135deg,#1f4f91,#2b67ae 58%,#3b82c4); }
        .am-hero-title { display:flex;align-items:center;gap:12px; }
        .am-hero-title h1 { margin:0;font-size:18px;line-height:1.25;font-weight:800;color:#fff; }
        .am-hero-title p { margin:3px 0 0;font-size:12px;color:#dbeafe; }
        .am-hero-icon { width:42px;height:42px;display:flex;align-items:center;justify-content:center;flex:0 0 42px;border:1px solid rgba(255,255,255,.24);border-radius:11px;background:rgba(255,255,255,.13); }
        .am-hero-icon svg { width:22px;height:22px; }
        .am-back-btn { height:36px;display:inline-flex;align-items:center;gap:7px;border:1px solid rgba(255,255,255,.35);border-radius:8px;background:rgba(255,255,255,.14);padding:0 13px;font-size:12px;font-weight:700;color:#fff;text-decoration:none;transition:.18s;white-space:nowrap; }
        .am-back-btn:hover { background:rgba(255,255,255,.24); }
        .am-back-btn svg { width:16px;height:16px; }
        .am-stats { display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;padding:12px;background:#f8fafc; }
        .am-stat { min-height:72px;display:flex;align-items:center;gap:11px;border:1px solid #e2e8f0;border-radius:10px;background:#fff;padding:11px 13px;box-shadow:0 2px 7px rgba(15,23,42,.04); }
        .am-stat-icon { width:40px;height:40px;display:flex;align-items:center;justify-content:center;flex:0 0 40px;border-radius:10px; }
        .am-stat-icon svg { width:20px;height:20px; }
        .am-stat span:not(.am-stat-icon) { display:block;font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.05em;color:#64748b; }
        .am-stat strong { display:block;margin-top:2px;font-size:21px;line-height:1;font-weight:800;color:#172554; }
        .am-stat-blue { border-top:3px solid #3b82f6; }.am-stat-blue .am-stat-icon { background:#eff6ff;color:#2563eb; }
        .am-stat-amber { border-top:3px solid #f59e0b; }.am-stat-amber .am-stat-icon { background:#fffbeb;color:#d97706; }
        .am-stat-green { border-top:3px solid #10b981; }.am-stat-green .am-stat-icon { background:#ecfdf5;color:#059669; }
        .am-stat-violet { border-top:3px solid #8b5cf6; }.am-stat-violet .am-stat-icon { background:#f5f3ff;color:#7c3aed; }
        .am-search-panel { display:flex;align-items:flex-end;justify-content:space-between;gap:18px;padding:14px 18px;background:linear-gradient(135deg,#f0f4f8,#e8edf5);border-bottom:2px solid #dbe4ef; }
        .am-search-heading span { display:block;font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.04em;color:#334155; }
        .am-search-heading small { display:block;margin-top:2px;font-size:11px;color:#64748b;white-space:nowrap; }
        .am-search-controls { width:min(680px,100%);display:flex;align-items:center;gap:8px; }
        .am-search-input { height:38px;min-width:0;display:flex;flex:1 1 auto;overflow:hidden;border:1px solid #cbd5e1;border-radius:9px;background:#fff; }
        .am-search-input:focus-within { border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,.12); }
        .am-search-input span { width:40px;display:flex;align-items:center;justify-content:center;flex:0 0 40px;border-right:1px solid #e2e8f0;background:#eff6ff;color:#2563eb; }
        .am-search-input svg { width:17px;height:17px; }
        .am-search-input input { min-width:0;flex:1;border:0!important;outline:0!important;padding:0 11px;font-size:13px;color:#1e293b;box-shadow:none!important; }
        .am-search-btn,.am-clear-btn { height:38px;display:inline-flex;align-items:center;justify-content:center;border-radius:8px;padding:0 16px;font-size:12px;font-weight:700;text-decoration:none;cursor:pointer;transition:.18s;white-space:nowrap; }
        .am-search-btn { border:1px solid #2563eb;background:#2563eb;color:#fff;box-shadow:0 3px 9px rgba(37,99,235,.2); }
        .am-search-btn:hover { background:#1d4ed8; }
        .am-clear-btn { border:1px solid #cbd5e1;background:#fff;color:#475569; }
        .am-clear-btn:hover { background:#f8fafc; }
        .am-result-bar { padding:8px 18px;border-bottom:1px solid #e2e8f0;background:#f8fafc;font-size:11px;color:#64748b; }
        .am-table-scroll { overflow-x:auto; }
        .am-table { width:100%;border-collapse:separate;border-spacing:0;font-size:12.5px; }
        .am-table thead tr { background:linear-gradient(135deg,#e8edf5,#dbe4ef,#d1d9e6); }
        .am-table th { padding:11px 10px;text-align:left;border-bottom:2px solid #cbd5e1;font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#334155;white-space:nowrap; }
        .am-table td { padding:10px;vertical-align:middle;border-bottom:1px solid #eef2f7;line-height:1.4;color:#475569; }
        .am-table tbody tr:nth-child(even) { background:#f8fafc; }.am-table tbody tr:nth-child(odd) { background:#fff; }
        .am-table tbody tr:hover { background:#eff6ff;box-shadow:inset 4px 0 0 #2b5ea7; }
        .am-student-name { font-weight:700;color:#1e40af;white-space:nowrap; }
        .am-muted { margin-top:2px;font-size:10.5px;color:#94a3b8; }
        .am-faculty { font-weight:600;color:#047857; }.am-specialty { max-width:230px;color:#0e7490;white-space:normal; }
        .am-study-badges { display:flex;flex-wrap:wrap;gap:4px;margin-top:5px; }
        .am-course,.am-group { display:inline-block;border-radius:5px;padding:2px 6px;font-size:10px;font-weight:600; }
        .am-course { border:1px solid #ddd6fe;background:#ede9fe;color:#5b21b6; }.am-group { background:#1e4b8a;color:#fff; }
        .am-phone { white-space:nowrap;color:#334155!important; }
        .am-reason { max-width:300px;display:-webkit-box;overflow:hidden;-webkit-box-orient:vertical;-webkit-line-clamp:3;white-space:normal; }
        .am-document-btn { display:inline-flex;align-items:center;gap:5px;border-radius:6px;background:#e0f2fe;padding:5px 9px;font-size:11px;font-weight:700;color:#0369a1;text-decoration:none; }
        .am-document-btn:hover { background:#bae6fd; }.am-document-btn svg { width:15px;height:15px; }
        .am-file-name { max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;margin-top:3px;font-size:10px;color:#94a3b8; }
        .am-no-document { display:inline-block;border-radius:6px;background:#f1f5f9;padding:5px 8px;font-size:10.5px;font-weight:600;color:#94a3b8; }
        .am-creator { font-weight:600;color:#334155; }.am-status { display:inline-block;border-radius:999px;padding:4px 9px;font-size:10.5px;font-weight:700;white-space:nowrap; }
        .am-status-pending { background:#fef3c7;color:#b45309; }.am-status-approved { background:#d1fae5;color:#047857; }
        .am-status-rejected { background:#fee2e2;color:#b91c1c; }.am-status-default { background:#e2e8f0;color:#475569; }
        .am-empty { padding:48px!important;text-align:center;color:#64748b!important; }
        .am-pagination { border-top:1px solid #e2e8f0;padding:10px 16px; }
        @media (max-width:900px) { .am-stats { grid-template-columns:repeat(2,minmax(0,1fr)); }.am-search-panel { align-items:stretch;flex-direction:column; }.am-search-controls { width:100%; } }
        @media (max-width:560px) { .am-hero { align-items:flex-start;flex-direction:column; }.am-stats { grid-template-columns:1fr; }.am-search-controls { flex-wrap:wrap; }.am-search-input { flex-basis:100%; }.am-search-btn,.am-clear-btn { flex:1; } }
    </style>
</x-app-layout>
