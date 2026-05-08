<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $teacher->full_name }} — Baholar
        </h2>
    </x-slot>

    <div class="py-4">
        <div class="max-w-full mx-auto sm:px-4 lg:px-6">

            {{-- Orqaga --}}
            <div class="mb-3">
                <a href="{{ route('admin.staff-evaluation.index') }}" class="back-link">
                    <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    Orqaga
                </a>
            </div>

            @if(session('success'))
                <div class="mb-4 p-3 rounded-lg flex items-center gap-2" style="background:#dcfce7;border:1px solid #86efac;color:#166534;">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    <span class="text-sm font-medium">{{ session('success') }}</span>
                </div>
            @endif

            {{-- Xodim ma'lumotlari --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 mb-4">
                <div class="flex items-center gap-4">
                    <div class="staff-avatar">
                        {{ mb_substr($teacher->full_name, 0, 1) }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <div style="font-size:18px;font-weight:700;color:#0f172a;">{{ $teacher->full_name }}</div>
                        @if($teacher->staff_position)
                            <div style="font-size:13px;color:#64748b;margin-top:2px;">{{ $teacher->staff_position }}</div>
                        @endif
                        @if($teacher->department)
                            <div style="font-size:12px;color:#94a3b8;margin-top:2px;">{{ $teacher->department }}</div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Statistika kartalar --}}
            <div class="stats-grid mb-4">
                {{-- O'rtacha baho --}}
                <div class="stat-card stat-rating">
                    <div class="stat-card-inner">
                        <div class="stat-icon" style="background:linear-gradient(135deg,#fef3c7,#fde68a);">
                            <svg style="width:22px;height:22px;color:#d97706;" fill="currentColor" viewBox="0 0 24 24"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>
                        </div>
                        <div>
                            <div class="stat-label">O'rtacha baho</div>
                            <div class="stat-value">
                                @if($avgRating)
                                    <span style="color:#d97706;">{{ number_format($avgRating, 1) }}</span>
                                    <span style="font-size:14px;color:#94a3b8;">/ 5</span>
                                @else
                                    —
                                @endif
                            </div>
                            @if($avgRating)
                                <div style="display:flex;gap:1px;margin-top:4px;">
                                    @for($i = 1; $i <= 5; $i++)
                                        <span style="color:{{ $i <= round($avgRating) ? '#f59e0b' : '#e2e8f0' }};font-size:14px;">&#9733;</span>
                                    @endfor
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Jami baholar --}}
                <div class="stat-card">
                    <div class="stat-card-inner">
                        <div class="stat-icon" style="background:linear-gradient(135deg,#dbeafe,#bfdbfe);">
                            <svg style="width:22px;height:22px;color:#2563eb;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                        </div>
                        <div>
                            <div class="stat-label">Jami baholar</div>
                            <div class="stat-value" style="color:#2563eb;">{{ $totalCount }}</div>
                            <div style="font-size:11px;color:#94a3b8;margin-top:4px;">{{ $totalCount > 0 ? 'ta baho qoldirildi' : 'Hali baho yo\'q' }}</div>
                        </div>
                    </div>
                </div>

                {{-- Taqsimot --}}
                <div class="stat-card stat-distribution">
                    <div style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.04em;margin-bottom:10px;">Taqsimot</div>
                    @foreach($ratingDistribution as $star => $count)
                    @php
                        $pct = $totalCount > 0 ? ($count / $totalCount * 100) : 0;
                        if ($star >= 4) $barColor = 'linear-gradient(90deg,#16a34a,#22c55e)';
                        elseif ($star == 3) $barColor = 'linear-gradient(90deg,#f59e0b,#fbbf24)';
                        else $barColor = 'linear-gradient(90deg,#dc2626,#ef4444)';
                    @endphp
                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:5px;">
                        <span style="font-size:11px;font-weight:600;color:#475569;width:22px;display:inline-flex;align-items:center;gap:2px;">{{ $star }}<span style="color:#f59e0b;">&#9733;</span></span>
                        <div style="flex:1;height:8px;background:#f1f5f9;border-radius:4px;overflow:hidden;">
                            <div style="height:100%;width:{{ $pct }}%;background:{{ $barColor }};border-radius:4px;transition:width 0.3s;"></div>
                        </div>
                        <span style="font-size:11px;font-weight:600;color:#64748b;width:24px;text-align:right;">{{ $count }}</span>
                    </div>
                    @endforeach
                </div>
            </div>

            {{-- QR kod bloki --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 mb-4">
                @if($teacher->eval_qr_token)
                <div class="qr-block">
                    <div class="qr-image-wrap">
                        <div class="qr-image">
                            {!! QrCode::size(180)->errorCorrection('H')->margin(1)->generate(route('staff-evaluate.form', $teacher->eval_qr_token)) !!}
                            <div class="qr-logo-overlay">
                                <div class="qr-logo-bg">
                                    <img src="{{ asset('logo.png') }}" alt="Logo" style="width:42px;height:42px;border-radius:50%;">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div style="flex:1;min-width:0;">
                        <h3 style="font-size:16px;font-weight:700;color:#0f172a;margin-bottom:4px;">QR kod</h3>
                        <div style="font-size:12px;color:#64748b;margin-bottom:14px;">
                            <div style="margin-bottom:6px;color:#475569;font-weight:600;">Havola:</div>
                            <code style="background:#f1f5f9;padding:6px 10px;border-radius:6px;font-size:11px;color:#1e3a8a;display:block;word-break:break-all;border:1px solid #e2e8f0;">{{ route('staff-evaluate.form', $teacher->eval_qr_token) }}</code>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <a href="{{ route('admin.staff-evaluation.download-qr', $teacher) }}" class="btn-indigo">
                                <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/></svg>
                                SVG yuklab olish
                            </a>
                            <form method="POST" action="{{ route('admin.staff-evaluation.regenerate-qr', $teacher) }}"
                                  onsubmit="return confirm('QR kod qayta yaratiladi va barcha eski baholar o\'chiriladi. Davom etasizmi?')">
                                @csrf
                                <button type="submit" class="btn-amber">
                                    <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                    Qayta yaratish
                                </button>
                            </form>
                            <form method="POST" action="{{ route('admin.staff-evaluation.delete-qr', $teacher) }}"
                                  onsubmit="return confirm('QR kod va barcha baholar butunlay o\'chiriladi. Davom etasizmi?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn-red">
                                    <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V4a2 2 0 012-2h2a2 2 0 012 2v3"/></svg>
                                    O'chirish
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                @else
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h3 style="font-size:15px;font-weight:700;color:#0f172a;">QR kod</h3>
                        <p style="font-size:12px;color:#94a3b8;margin-top:2px;">QR kod hali yaratilmagan</p>
                    </div>
                    <form method="POST" action="{{ route('admin.staff-evaluation.generate-qr', $teacher) }}">
                        @csrf
                        <button type="submit" class="btn-success">
                            <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                            QR yaratish
                        </button>
                    </form>
                </div>
                @endif
            </div>

            {{-- Filtrlar va eksport --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-4">
                <div class="flex flex-wrap items-center gap-2">
                    <span style="font-size:12px;font-weight:700;color:#475569;text-transform:uppercase;margin-right:4px;">Filtr:</span>
                    <a href="{{ route('admin.staff-evaluation.show', $teacher) }}"
                       class="filter-pill {{ !request('rating') ? 'filter-pill-active' : '' }}">
                        Barchasi <span class="pill-count">{{ $totalCount }}</span>
                    </a>
                    @foreach($ratingDistribution as $star => $count)
                        @php
                            if ($star >= 4) $color = 'green';
                            elseif ($star == 3) $color = 'amber';
                            else $color = 'red';
                        @endphp
                        <a href="{{ route('admin.staff-evaluation.show', ['teacher' => $teacher, 'rating' => $star]) }}"
                           class="filter-pill filter-pill-{{ $color }} {{ request('rating') == $star ? 'filter-pill-active filter-pill-active-' . $color : '' }}">
                            {{ $star }} <span style="color:#f59e0b;">&#9733;</span> <span class="pill-count">{{ $count }}</span>
                        </a>
                    @endforeach

                    <a href="{{ route('admin.staff-evaluation.export-excel', array_merge(['teacher' => $teacher], request()->only('rating'))) }}"
                       class="ml-auto btn-emerald">
                        <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        Excel
                    </a>
                </div>
            </div>

            {{-- Baholar va izohlar --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:16px;">
                    <svg style="width:20px;height:20px;color:#2b5ea7;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                    <h3 style="font-size:16px;font-weight:700;color:#0f172a;">
                        Baholar va izohlar
                        @if(request('rating'))
                            <span style="font-weight:500;color:#94a3b8;font-size:14px;">— {{ request('rating') }} yulduzli</span>
                        @endif
                    </h3>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
                    @forelse($evaluations as $eval)
                    @php
                        if ($eval->rating >= 4) $cardClass = 'eval-card-green';
                        elseif ($eval->rating == 3) $cardClass = 'eval-card-amber';
                        else $cardClass = 'eval-card-red';
                        $orderNum = ($evaluations->currentPage() - 1) * $evaluations->perPage() + $loop->iteration;
                    @endphp
                    <div class="eval-card {{ $cardClass }}">
                        <div class="eval-num">{{ $orderNum }}</div>
                        <div style="flex:1;min-width:0;">
                            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:6px;">
                                <div style="display:flex;gap:1px;">
                                    @for($i = 1; $i <= 5; $i++)
                                        <span style="font-size:16px;color:{{ $i <= $eval->rating ? '#f59e0b' : '#e2e8f0' }};">&#9733;</span>
                                    @endfor
                                </div>
                                <span style="font-size:11px;color:#94a3b8;display:inline-flex;align-items:center;gap:4px;">
                                    <svg style="width:12px;height:12px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    {{ $eval->created_at->format('d.m.Y H:i') }}
                                </span>
                            </div>
                            @if($eval->comment)
                                <p style="font-size:13px;color:#334155;line-height:1.5;margin-bottom:6px;">{{ $eval->comment }}</p>
                            @else
                                <p style="font-size:12px;color:#cbd5e1;font-style:italic;margin-bottom:6px;">Izoh qoldirilmagan</p>
                            @endif
                            <div style="display:inline-flex;align-items:center;gap:5px;font-size:11px;color:#64748b;background:rgba(255,255,255,0.6);padding:3px 8px;border-radius:6px;">
                                <svg style="width:11px;height:11px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                {{ $eval->student ? ($eval->student->short_name ?? $eval->student->full_name) : 'Anonim' }}
                            </div>
                        </div>
                    </div>
                    @empty
                    <div class="col-span-full text-center py-10" style="color:#94a3b8;">
                        <svg style="width:48px;height:48px;margin:0 auto 8px;" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                        <p style="font-size:14px;font-weight:500;">
                            @if(request('rating'))
                                {{ request('rating') }} yulduzli baholar yo'q
                            @else
                                Hali baholar yo'q
                            @endif
                        </p>
                    </div>
                    @endforelse
                </div>

                @if($evaluations->hasPages())
                <div style="margin-top:16px;border-top:1px solid #e2e8f0;padding-top:12px;">
                    {{ $evaluations->links() }}
                </div>
                @endif
            </div>
        </div>
    </div>

    @push('styles')
    <style>
        .back-link { display:inline-flex;align-items:center;gap:6px;font-size:13px;font-weight:600;color:#2b5ea7;text-decoration:none;padding:6px 10px;border-radius:6px;transition:all .15s; }
        .back-link:hover { background:#eff6ff;color:#1e3a8a; }

        .staff-avatar { width:60px;height:60px;border-radius:50%;background:linear-gradient(135deg,#1a3268,#2b5ea7);color:#fff;display:flex;align-items:center;justify-content:center;font-size:24px;font-weight:700;flex-shrink:0; }

        .stats-grid { display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:14px; }
        .stat-card { background:#fff;border-radius:12px;padding:18px;box-shadow:0 1px 3px rgba(0,0,0,0.04);border:1px solid #f1f5f9; }
        .stat-card-inner { display:flex;align-items:center;gap:14px; }
        .stat-icon { width:48px;height:48px;border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0; }
        .stat-label { font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.04em;margin-bottom:2px; }
        .stat-value { font-size:28px;font-weight:800;color:#0f172a;line-height:1.1; }

        .qr-block { display:flex;flex-wrap:wrap;align-items:center;gap:24px; }
        .qr-image-wrap { flex-shrink:0; }
        .qr-image { position:relative;display:inline-block;padding:10px;background:#fff;border:2px solid #e2e8f0;border-radius:12px; }
        .qr-logo-overlay { position:absolute;inset:0;display:flex;align-items:center;justify-content:center;pointer-events:none; }
        .qr-logo-bg { background:#fff;border-radius:50%;padding:5px;box-shadow:0 0 0 2px #fff; }

        .btn-indigo, .btn-amber, .btn-red, .btn-success, .btn-emerald { display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border-radius:8px;font-size:12px;font-weight:700;text-decoration:none;border:none;cursor:pointer;transition:all .15s; }
        .btn-indigo { background:linear-gradient(135deg,#4338ca,#6366f1);color:#fff;box-shadow:0 2px 4px rgba(99,102,241,0.25); }
        .btn-indigo:hover { background:linear-gradient(135deg,#3730a3,#4f46e5);transform:translateY(-1px); }
        .btn-amber { background:linear-gradient(135deg,#d97706,#f59e0b);color:#fff;box-shadow:0 2px 4px rgba(245,158,11,0.25); }
        .btn-amber:hover { background:linear-gradient(135deg,#b45309,#d97706);transform:translateY(-1px); }
        .btn-red { background:linear-gradient(135deg,#b91c1c,#dc2626);color:#fff;box-shadow:0 2px 4px rgba(220,38,38,0.25); }
        .btn-red:hover { background:linear-gradient(135deg,#991b1b,#b91c1c);transform:translateY(-1px); }
        .btn-success { background:linear-gradient(135deg,#15803d,#16a34a);color:#fff;box-shadow:0 2px 4px rgba(22,163,74,0.25); }
        .btn-success:hover { background:linear-gradient(135deg,#166534,#15803d);transform:translateY(-1px); }
        .btn-emerald { background:linear-gradient(135deg,#047857,#10b981);color:#fff;box-shadow:0 2px 4px rgba(16,185,129,0.25); }
        .btn-emerald:hover { background:linear-gradient(135deg,#065f46,#047857);transform:translateY(-1px); }

        .filter-pill { display:inline-flex;align-items:center;gap:6px;padding:6px 12px;border-radius:8px;font-size:12px;font-weight:600;text-decoration:none;background:#f1f5f9;color:#475569;border:1px solid #e2e8f0;transition:all .15s; }
        .filter-pill:hover { background:#e2e8f0; }
        .filter-pill-green { background:#f0fdf4;color:#15803d;border-color:#bbf7d0; }
        .filter-pill-green:hover { background:#dcfce7; }
        .filter-pill-amber { background:#fffbeb;color:#b45309;border-color:#fde68a; }
        .filter-pill-amber:hover { background:#fef3c7; }
        .filter-pill-red { background:#fef2f2;color:#b91c1c;border-color:#fecaca; }
        .filter-pill-red:hover { background:#fee2e2; }
        .filter-pill-active { background:linear-gradient(135deg,#1a3268,#2b5ea7);color:#fff;border-color:transparent; }
        .filter-pill-active-green { background:linear-gradient(135deg,#15803d,#16a34a)!important;color:#fff!important;border-color:transparent!important; }
        .filter-pill-active-amber { background:linear-gradient(135deg,#d97706,#f59e0b)!important;color:#fff!important;border-color:transparent!important; }
        .filter-pill-active-red { background:linear-gradient(135deg,#b91c1c,#dc2626)!important;color:#fff!important;border-color:transparent!important; }
        .pill-count { background:rgba(255,255,255,0.5);padding:1px 6px;border-radius:6px;font-size:11px;font-weight:700; }
        .filter-pill-active .pill-count, .filter-pill-active-green .pill-count, .filter-pill-active-amber .pill-count, .filter-pill-active-red .pill-count { background:rgba(255,255,255,0.25); }

        .eval-card { display:flex;gap:12px;padding:14px;border-radius:10px;border:1px solid;transition:all .15s; }
        .eval-card:hover { transform:translateY(-1px);box-shadow:0 4px 12px rgba(0,0,0,0.05); }
        .eval-card-green { background:linear-gradient(135deg,#f0fdf4,#dcfce7);border-color:#bbf7d0; }
        .eval-card-amber { background:linear-gradient(135deg,#fffbeb,#fef3c7);border-color:#fde68a; }
        .eval-card-red { background:linear-gradient(135deg,#fef2f2,#fee2e2);border-color:#fecaca; }

        .eval-num { width:28px;height:28px;border-radius:50%;background:#fff;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;color:#475569;flex-shrink:0;border:1px solid rgba(0,0,0,0.05); }

        .col-span-full { grid-column:1/-1; }

        @media (max-width: 640px) {
            .stat-value { font-size:22px; }
            .qr-block { gap:14px; }
        }
    </style>
    @endpush
</x-app-layout>
