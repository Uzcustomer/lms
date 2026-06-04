<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            Vedomost — {{ ($merged->group_name ?? $submission->group_name) }} / {{ ($merged->subject_name ?? $submission->subject_name) }}
        </h2>
    </x-slot>

    @php
        $statusBadge = [
            'pending'   => ['Kutilmoqda', '#475569', '#f1f5f9'],
            'received'  => ['Qabul qilindi', '#1d4ed8', '#dbeafe'],
            'reviewing' => ['Tekshirilmoqda', '#b45309', '#fef3c7'],
            'approved'  => ['Tasdiqlandi', '#166534', '#dcfce7'],
            'rejected'  => ['Rad etildi', '#b91c1c', '#fee2e2'],
        ];
        $closingFormLabels = [
            'oski' => 'Faqat OSKI', 'test' => 'Faqat Test', 'oski_test' => 'OSKI + Test',
            'normativ' => 'Normativ', 'sinov' => 'Sinov (test)',
        ];
        $b = $statusBadge[$submission->status] ?? ['—','#475569','#f1f5f9'];
        $v = $submission;
        $overdue = $v->deadline && \Carbon\Carbon::parse($v->deadline)->isPast() && !\Carbon\Carbon::parse($v->deadline)->isToday() && $v->status !== 'approved';
        $actionLabels = ['upload'=>'Yuklandi','review'=>'Tekshirishga olindi','approve'=>'Tasdiqlandi','reject'=>'Rad etildi'];
    @endphp

    <div class="py-4">
        <div class="max-w-5xl mx-auto sm:px-4 lg:px-6">
            <a href="{{ url()->previous() }}" style="color:#64748b;text-decoration:none;font-size:13px;">← Orqaga</a>

            @if(session('success'))
                <div style="background:#dcfce7;color:#166534;padding:10px 16px;border-radius:8px;margin:12px 0;border:1px solid #bbf7d0;">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div style="background:#fee2e2;color:#b91c1c;padding:10px 16px;border-radius:8px;margin:12px 0;border:1px solid #fecaca;">{{ session('error') }}</div>
            @endif
            @if($errors->any())
                <div style="background:#fee2e2;color:#b91c1c;padding:10px 16px;border-radius:8px;margin:12px 0;border:1px solid #fecaca;">
                    @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
                </div>
            @endif

            {{-- Asosiy ma'lumot --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100" style="padding:18px;margin-top:12px;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
                    <h3 style="font-size:16px;font-weight:700;color:#1e293b;">Ma'lumotlar</h3>
                    <span style="background:{{ $b[2] }};color:{{ $b[1] }};padding:5px 14px;border-radius:999px;font-size:13px;font-weight:700;">{{ $b[0] }}</span>
                </div>
                @php $m = $merged ?? $v; @endphp
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:10px 24px;font-size:14px;">
                    <div>
                        <span style="color:#94a3b8;">Guruh:</span> <b>{{ $m->group_name }}</b>
                        @if(($m->merge_count ?? 1) > 1)
                            <div style="font-size:12px;color:#64748b;">Guruhchalar: {{ $m->subgroup_label }}</div>
                        @endif
                    </div>
                    <div><span style="color:#94a3b8;">Fan:</span> <b>{{ $m->subject_name }}</b></div>
                    <div><span style="color:#94a3b8;">Yo'nalish:</span> {{ $v->specialty_name }}</div>
                    <div><span style="color:#94a3b8;">Kafedra:</span> {{ $m->department_name ?? $v->department_name }}</div>
                    <div><span style="color:#94a3b8;">Yopilish shakli:</span> {{ $closingFormLabels[$v->closing_form] ?? $v->closing_form }}</div>
                    <div><span style="color:#94a3b8;">O'qituvchi(lar):</span> {{ $m->teacher_name ?? '—' }} <span style="color:#94a3b8;">{{ $m->teacher_phone ? '('.$m->teacher_phone.')' : '' }}</span></div>
                    <div><span style="color:#94a3b8;">Fan mas'uli:</span> {{ $m->fan_masuli_name ?? '—' }} <span style="color:#94a3b8;">{{ $m->fan_masuli_phone ? '('.$m->fan_masuli_phone.')' : '' }}</span></div>
                    <div><span style="color:#94a3b8;">Kafedra mudiri:</span> {{ $m->kafedra_mudiri_name ?? '—' }} <span style="color:#94a3b8;">{{ $m->kafedra_mudiri_phone ? '('.$m->kafedra_mudiri_phone.')' : '' }}</span></div>
                    <div><span style="color:#94a3b8;">Asos sana:</span> {{ $v->base_date ? \Carbon\Carbon::parse($v->base_date)->format('d.m.Y') : '—' }} ({{ $v->base_type === 'lesson' ? 'oxirgi dars' : ($v->base_type === 'exam' ? 'YN' : '') }})</div>
                    <div><span style="color:#94a3b8;">Muddat:</span> <b style="{{ $overdue ? 'color:#b91c1c;' : '' }}">{{ $v->deadline ? \Carbon\Carbon::parse($v->deadline)->format('d.m.Y') : '—' }}</b> {{ $overdue ? '(kechikkan)' : '' }}</div>
                </div>

                @if($v->pdf_path || $v->excel_path)
                    <div style="margin-top:14px;padding-top:14px;border-top:1px solid #f1f5f9;display:flex;gap:10px;flex-wrap:wrap;">
                        @if($v->pdf_path)
                            <a href="{{ route('admin.vedomost-submission.file', [$v->id, 'pdf']) }}" style="background:#fee2e2;color:#b91c1c;padding:8px 14px;border-radius:8px;text-decoration:none;font-size:13px;">📄 PDF yuklab olish</a>
                        @endif
                        @if($v->excel_path)
                            <a href="{{ route('admin.vedomost-submission.file', [$v->id, 'excel']) }}" style="background:#dcfce7;color:#166534;padding:8px 14px;border-radius:8px;text-decoration:none;font-size:13px;">📊 Excel yuklab olish</a>
                        @endif
                        @if($v->uploaded_at)
                            <span style="color:#94a3b8;font-size:12px;align-self:center;">Yuklagan: {{ $v->uploaded_by_name }} · {{ \Carbon\Carbon::parse($v->uploaded_at)->format('d.m.Y H:i') }}</span>
                        @endif
                    </div>
                @endif

                @if($v->status === 'rejected' && $v->rejection_reason)
                    <div style="margin-top:14px;background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:12px;">
                        <div style="font-weight:700;color:#b91c1c;font-size:13px;margin-bottom:4px;">Rad etish sababi (xatolar):</div>
                        <div style="color:#7f1d1d;font-size:14px;white-space:pre-wrap;">{{ $v->rejection_reason }}</div>
                    </div>
                @endif
            </div>

            {{-- Amallar --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100" style="padding:18px;margin-top:14px;">
                <h3 style="font-size:16px;font-weight:700;color:#1e293b;margin-bottom:12px;">Amallar</h3>

                @if(in_array($v->status, ['pending','rejected']))
                    {{-- Yuklash --}}
                    <form method="POST" action="{{ route('admin.vedomost-submission.upload', $v->id) }}" enctype="multipart/form-data" style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;">
                        @csrf
                        <div>
                            <label style="font-size:12px;color:#64748b;display:block;margin-bottom:4px;">Skaner (PDF)</label>
                            <input type="file" name="pdf" accept="application/pdf" required>
                        </div>
                        <div>
                            <label style="font-size:12px;color:#64748b;display:block;margin-bottom:4px;">Excel (ixtiyoriy)</label>
                            <input type="file" name="excel" accept=".xlsx,.xls">
                        </div>
                        <button type="submit" style="background:#1a3268;color:#fff;border:none;padding:9px 18px;border-radius:8px;cursor:pointer;">
                            {{ $v->status === 'rejected' ? 'Qayta yuklash' : 'Yuklash va qabul qilish' }}
                        </button>
                    </form>
                @endif

                @if($v->status === 'received')
                    <form method="POST" action="{{ route('admin.vedomost-submission.review', $v->id) }}" style="display:inline-block;margin-right:8px;">
                        @csrf
                        <button type="submit" style="background:#b45309;color:#fff;border:none;padding:9px 18px;border-radius:8px;cursor:pointer;">Tekshirishga olish</button>
                    </form>
                @endif

                @if(in_array($v->status, ['received','reviewing']))
                    <form method="POST" action="{{ route('admin.vedomost-submission.approve', $v->id) }}" style="display:inline-block;margin-right:8px;"
                          onsubmit="return confirm('Vedomost tasdiqlansinmi?');">
                        @csrf
                        <button type="submit" style="background:#166534;color:#fff;border:none;padding:9px 18px;border-radius:8px;cursor:pointer;">✔ Tasdiqlash</button>
                    </form>

                    <form method="POST" action="{{ route('admin.vedomost-submission.reject', $v->id) }}" style="margin-top:12px;">
                        @csrf
                        <label style="font-size:12px;color:#64748b;display:block;margin-bottom:4px;">Rad etish sababi (xatolarni yozing)</label>
                        <textarea name="rejection_reason" rows="3" style="width:100%;border:1px solid #cbd5e1;border-radius:8px;padding:8px;" placeholder="Vedomostdagi xatolarni batafsil yozing..."></textarea>
                        <button type="submit" style="margin-top:8px;background:#b91c1c;color:#fff;border:none;padding:9px 18px;border-radius:8px;cursor:pointer;"
                                onclick="return confirm('Vedomost rad etilsinmi? O\'qituvchi, fan mas\'uli, kafedra mudiri va o\'quv prorektoriga xabar boradi.');">
                            ✕ Rad etish
                        </button>
                    </form>
                @endif

                @if($v->status === 'approved')
                    <div style="color:#166534;font-size:14px;">Bu vedomost tasdiqlangan. Tasdiqlagan: <b>{{ $v->reviewed_by_name }}</b> · {{ $v->reviewed_at ? \Carbon\Carbon::parse($v->reviewed_at)->format('d.m.Y H:i') : '' }}</div>
                @endif
            </div>

            {{-- AI tekshiruv (tavsiya) --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100" style="padding:18px;margin-top:14px;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                    <h3 style="font-size:16px;font-weight:700;color:#1e293b;">🤖 AI tekshiruv <span style="font-weight:400;color:#94a3b8;font-size:13px;">(tavsiya — yakuniy qaror registratorda)</span></h3>
                    @if($v->pdf_path && $aiConfigured && !in_array($v->ai_check_status, ['queued','running']))
                        <form method="POST" action="{{ route('admin.vedomost-submission.ai-check', $v->id) }}">
                            @csrf
                            <button type="submit" style="background:#6d28d9;color:#fff;border:none;padding:8px 16px;border-radius:8px;cursor:pointer;">
                                {{ $v->ai_check_status === 'done' || $v->ai_check_status === 'error' ? 'Qayta tekshirish' : 'AI tekshirish' }}
                            </button>
                        </form>
                    @endif
                </div>

                @if(!$aiConfigured)
                    <div style="color:#b45309;font-size:13px;">AI tekshiruv sozlanmagan (ANTHROPIC_API_KEY yo'q).</div>
                @elseif(!$v->pdf_path)
                    <div style="color:#94a3b8;font-size:13px;">Avval skaner (PDF) yuklang.</div>
                @elseif(in_array($v->ai_check_status, ['queued','running']))
                    <div id="ai-progress" style="display:flex;align-items:center;gap:12px;background:#fef3c7;border:1px solid #fde68a;border-radius:8px;padding:12px 14px;">
                        <span class="ai-spinner"></span>
                        <div>
                            <div id="ai-stage" style="color:#b45309;font-size:14px;font-weight:600;">
                                {{ $v->ai_check_status === 'running' ? 'Claude tahlil qilmoqda...' : 'Navbatda kutilmoqda...' }}
                            </div>
                            <div style="color:#92700a;font-size:12px;">O'tgan vaqt: <span id="ai-elapsed">0s</span> · tayyor bo'lishi bilan natija o'zi chiqadi</div>
                        </div>
                    </div>
                    <style>
                        .ai-spinner { width:22px;height:22px;border:3px solid #fde68a;border-top-color:#b45309;border-radius:50%;display:inline-block;animation:ai-spin 0.8s linear infinite;flex-shrink:0; }
                        @keyframes ai-spin { to { transform: rotate(360deg); } }
                    </style>
                    <script>
                        (function () {
                            const url = "{{ route('admin.vedomost-submission.ai-status', $v->id) }}";
                            const start = Date.now();
                            const elapsedEl = document.getElementById('ai-elapsed');
                            const stageEl = document.getElementById('ai-stage');
                            const stages = { queued: 'Navbatda kutilmoqda...', running: 'Claude tahlil qilmoqda...' };

                            setInterval(function () {
                                if (elapsedEl) elapsedEl.textContent = Math.floor((Date.now() - start) / 1000) + 's';
                            }, 1000);

                            function poll() {
                                fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, cache: 'no-store' })
                                    .then(function (r) { return r.json(); })
                                    .then(function (d) {
                                        if (d.status === 'done' || d.status === 'error') {
                                            location.reload();
                                            return;
                                        }
                                        if (stageEl && stages[d.status]) stageEl.textContent = stages[d.status];
                                        setTimeout(poll, 3000);
                                    })
                                    .catch(function () { setTimeout(poll, 5000); });
                            }
                            setTimeout(poll, 3000);
                        })();
                    </script>
                @elseif($v->ai_check_status === 'error')
                    <div style="background:#fee2e2;color:#b91c1c;padding:10px;border-radius:8px;font-size:13px;">Xatolik: {{ $v->ai_error }}</div>
                @elseif($v->ai_check_status === 'done')
                    @php $r = $v->ai_result ?? []; $disc = $r['discrepancies'] ?? []; $sig = $r['signatures'] ?? []; @endphp
                    <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;">
                        @if(($v->ai_verdict ?? '') === 'ok')
                            <span style="background:#dcfce7;color:#166534;padding:5px 14px;border-radius:999px;font-weight:700;">✅ Nomuvofiqlik topilmadi</span>
                        @else
                            <span style="background:#fee2e2;color:#b91c1c;padding:5px 14px;border-radius:999px;font-weight:700;">⚠️ {{ count($disc) }} ta nomuvofiqlik</span>
                        @endif
                        <span style="color:#94a3b8;font-size:12px;">{{ $v->ai_checked_at ? \Carbon\Carbon::parse($v->ai_checked_at)->format('d.m.Y H:i') : '' }}</span>
                    </div>
                    @if($v->ai_summary)
                        <div style="font-size:14px;color:#334155;margin-bottom:10px;">{{ $v->ai_summary }}</div>
                    @endif

                    @if(!empty($sig))
                        <div style="font-size:13px;margin-bottom:10px;">
                            Imzolar:
                            <span style="margin-left:6px;">O'qituvchi: {!! ($sig['oqituvchi'] ?? false) ? '✅' : '❌' !!}</span>
                            <span style="margin-left:10px;">Dekan: {!! ($sig['dekan'] ?? false) ? '✅' : '❌' !!}</span>
                            <span style="margin-left:10px;">Kafedra mudiri: {!! ($sig['kafedra_mudiri'] ?? false) ? '✅' : '❌' !!}</span>
                            <span style="margin-left:10px;">Muhr (M.O'): {!! ($sig['muhr'] ?? false) ? '✅' : '❌' !!}</span>
                        </div>
                    @endif

                    @if(!empty($disc))
                        <div style="overflow-x:auto;">
                            <table style="width:100%;border-collapse:collapse;font-size:13px;">
                                <thead>
                                    <tr style="background:#f8fafc;text-align:left;">
                                        <th style="padding:6px;">Maydon</th>
                                        <th style="padding:6px;">Jiddiylik</th>
                                        <th style="padding:6px;">Tizimda</th>
                                        <th style="padding:6px;">Skanerda</th>
                                        <th style="padding:6px;">Izoh</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($disc as $d)
                                        @php $sev = $d['severity'] ?? 'low'; $sc = $sev==='high'?'#b91c1c':($sev==='medium'?'#b45309':'#64748b'); @endphp
                                        <tr style="border-top:1px solid #f1f5f9;">
                                            <td style="padding:6px;font-weight:600;">{{ $d['field'] ?? '' }}</td>
                                            <td style="padding:6px;color:{{ $sc }};font-weight:600;">{{ $sev }}</td>
                                            <td style="padding:6px;">{{ $d['expected'] ?? '' }}</td>
                                            <td style="padding:6px;">{{ $d['found'] ?? '' }}</td>
                                            <td style="padding:6px;color:#475569;">{{ $d['note'] ?? '' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                @else
                    <div style="color:#94a3b8;font-size:13px;">Hali tekshirilmagan. "AI tekshirish" tugmasini bosing.</div>
                @endif
            </div>

            {{-- Timeline --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100" style="padding:18px;margin-top:14px;">
                <h3 style="font-size:16px;font-weight:700;color:#1e293b;margin-bottom:12px;">Jarayon tarixi</h3>
                @forelse($submission->logs as $log)
                    <div style="display:flex;gap:12px;padding:8px 0;border-bottom:1px solid #f8fafc;">
                        <div style="min-width:130px;color:#94a3b8;font-size:12px;">{{ $log->created_at->format('d.m.Y H:i') }}</div>
                        <div style="flex:1;font-size:13px;">
                            <b>{{ $actionLabels[$log->action] ?? $log->action }}</b>
                            <span style="color:#94a3b8;">— {{ $log->user_name }}</span>
                            @if($log->note)<div style="color:#7f1d1d;margin-top:2px;white-space:pre-wrap;">{{ $log->note }}</div>@endif
                        </div>
                    </div>
                @empty
                    <div style="color:#94a3b8;font-size:13px;">Hali amallar yo'q.</div>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
