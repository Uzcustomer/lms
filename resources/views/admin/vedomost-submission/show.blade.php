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
        $actionLabels = ['upload'=>'Yuklandi','review'=>'Tekshirishga olindi','approve'=>'Tasdiqlandi','reject'=>'Rad etildi','reupload_permit'=>'Qayta yuklashga ruxsat berildi','ai_edit'=>'AI natijasi tuzatildi'];
        $activeRole = session('active_role', '');
        $isAdminActor = in_array($activeRole, ['superadmin','admin','kichik_admin','registrator_ofisi'], true);
        $isViceRector = in_array($activeRole, ['superadmin','oquv_prorektori'], true);
        $reuploadPermitted = $v->status === 'rejected' && $v->reupload_allowed_at;
        $canUploadNow = in_array($v->status, ['pending','received']) || $reuploadPermitted;
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
                    <div><span style="color:#94a3b8;">Shakl:</span> <b>{{ \App\Models\VedomostSubmission::formLabel($v->form_type ?? '12') }}</b></div>
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

                @if($reuploadPermitted)
                    <div style="margin-top:14px;background:#ecfdf5;border:1px solid #a7f3d0;border-radius:8px;padding:12px;">
                        <div style="font-weight:700;color:#047857;font-size:13px;">✔ Qayta yuklashga ruxsat berilgan</div>
                        <div style="color:#065f46;font-size:13px;margin-top:2px;">
                            Ruxsat bergan: <b>{{ $v->reupload_allowed_by_name ?? '—' }}</b>
                            · {{ \Carbon\Carbon::parse($v->reupload_allowed_at)->format('d.m.Y H:i') }}
                        </div>
                    </div>
                @endif
            </div>

            {{-- AI tekshiruv (tavsiya) --}}
            @php
                $aiRes = $v->ai_result ?? [];
                $aiV2 = ($aiRes['version'] ?? 1) >= 2 && !empty($aiRes['report']);
                $rep = $aiV2 ? $aiRes['report'] : null;
                // Rad etish sababiga tayyor matn: AI topgan (xodim tuzatgandan keyingi) xatolar.
                $aiRejectText = '';
                if ($rep && in_array($v->ai_verdict, ['issues', 'wrong_document'], true)) {
                    $lines = [];
                    if ($v->ai_verdict === 'wrong_document') { $lines[] = $rep['summary']; }
                    foreach ($rep['header'] as $h) { if ($h['status'] === 'bad') { $lines[] = "{$h['label']}: tizimda «{$h['system']}», vedomostda «{$h['scan']}»"; } }
                    foreach ($rep['doc_issues'] as $d) { if ($d['t'] === 'bad') { $lines[] = $d['m']; } }
                    foreach ($rep['rows'] as $row) {
                        if (in_array($row['status'], ['bad', 'missing', 'extra'], true)) {
                            $msgs = collect($row['flags'])->whereIn('t', ['bad', 'missing', 'extra'])->pluck('m')->implode('; ');
                            $lines[] = trim(($row['n'] ? $row['n'] . '. ' : '') . $row['fio'] . ': ' . $msgs);
                        }
                    }
                    $aiRejectText = implode("\n", array_slice($lines, 0, 60));
                }
                $vt = [
                    'ok' => ['Mos keladi', '#166534', '#dcfce7'],
                    'issues' => ['Nomuvofiqlik bor', '#713f12', '#fef08a'],
                    'review' => ["Ko'z bilan tekshirish kerak", '#7c2d12', '#fed7aa'],
                    'wrong_document' => ["Noto'g'ri fayl", '#991b1b', '#fee2e2'],
                ][$v->ai_verdict ?? ''] ?? ['Nomuvofiqlik bor', '#713f12', '#fef08a'];
            @endphp
            <div class="bg-white rounded-xl shadow-sm border border-gray-100" style="padding:18px;margin-top:14px;" id="ai-card">
                <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:12px;">
                    <h3 style="font-size:16px;font-weight:700;color:#1e293b;display:flex;align-items:center;gap:8px;">
                        <svg style="width:18px;height:18px" viewBox="0 0 24 24" fill="none" stroke="#6d28d9" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                        AI tekshiruv <span style="font-weight:400;color:#94a3b8;font-size:13px;">tavsiya — yakuniy qaror registratorda</span>
                    </h3>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;">
                        @if($aiV2 && $v->ai_check_status === 'done')
                            <a href="{{ route('admin.vedomost-submission.ai-report', $v->id) }}" target="_blank" rel="noopener"
                               style="background:#1e40af;color:#fff;height:38px;padding:0 16px;border-radius:8px;text-decoration:none;font-weight:600;font-size:14px;display:inline-flex;align-items:center;gap:6px;">
                                <svg style="width:16px;height:16px" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3h18v18H3zM3 9h18M3 15h18M9 3v18"/></svg>
                                Natijani ochish
                            </a>
                            <a href="{{ route('admin.vedomost-submission.ai-report.excel', $v->id) }}"
                               style="background:#fff;color:#334155;border:1px solid #cbd5e1;height:38px;padding:0 14px;border-radius:8px;text-decoration:none;font-weight:600;font-size:14px;display:inline-flex;align-items:center;">Excel</a>
                        @endif
                        @if($v->pdf_path && $aiConfigured && $isAdminActor && !in_array($v->ai_check_status, ['queued','running']) && $v->status !== 'approved')
                            <form method="POST" action="{{ route('admin.vedomost-submission.ai-check', $v->id) }}"
                                  @if($v->ai_check_status === 'done') onsubmit="return confirm('Qayta tekshirilsinmi? Kiritilgan tuzatishlar o\'chadi.');" @endif>
                                @csrf
                                <button type="submit" style="background:#6d28d9;color:#fff;border:none;height:38px;padding:0 16px;border-radius:8px;cursor:pointer;font-weight:600;font-size:14px;">
                                    {{ $v->ai_check_status === 'done' || $v->ai_check_status === 'error' ? 'Qayta tekshirish' : 'AI tekshirish' }}
                                </button>
                            </form>
                        @endif
                    </div>
                </div>

                @if(!$aiConfigured)
                    <div style="color:#b45309;font-size:13px;">AI tekshiruv sozlanmagan — AI sozlamalarida Gemini API kaliti qo'shilmagan.</div>
                @elseif(!$v->pdf_path)
                    <div style="color:#94a3b8;font-size:13px;">Avval skaner (PDF) yuklang.</div>
                @elseif(in_array($v->ai_check_status, ['queued','running']))
                    <div id="ai-progress" style="display:flex;align-items:center;gap:12px;background:#fef3c7;border:1px solid #fde68a;border-radius:8px;padding:12px 14px;">
                        <span class="ai-spinner"></span>
                        <div>
                            <div id="ai-stage" style="color:#b45309;font-size:14px;font-weight:600;">
                                {{ $v->ai_check_status === 'running' ? 'AI (Gemini) tahlil qilmoqda...' : 'Navbatda kutilmoqda...' }}
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
                            const stages = { queued: 'Navbatda kutilmoqda...', running: 'AI (Gemini) tahlil qilmoqda...' };

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
                @elseif($v->ai_check_status === 'done' && $aiV2)
                    @php $cnt = $rep['counts']; @endphp
                    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:10px;">
                        <span style="background:{{ $vt[2] }};color:{{ $vt[1] }};padding:5px 14px;border-radius:999px;font-weight:700;">{{ $vt[0] }}</span>
                        <span style="color:#94a3b8;font-size:12px;">{{ $v->ai_checked_at ? \Carbon\Carbon::parse($v->ai_checked_at)->format('d.m.Y H:i') : '' }}@if(!empty($aiRes['model'])) · {{ $aiRes['model'] }}@endif @if(!empty($cnt['edited'])) · {{ $cnt['edited'] }} qator xodim tomonidan tuzatilgan @endif</span>
                    </div>
                    <div style="font-size:14px;color:#334155;margin-bottom:12px;">{{ $rep['summary'] }}</div>
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:8px;margin-bottom:12px;">
                        @foreach([['Mos', $cnt['ok'], '#166534', '#f0fdf4'], ['Xato', $cnt['bad'], '#713f12', '#fefce8'], ["Ko'z bilan tekshirish", $cnt['ocr'], '#7c2d12', '#fff7ed'], ["PDFda yo'q", $cnt['missing'], '#991b1b', '#fef2f2'], ['Ortiqcha', $cnt['extra'], '#6b21a8', '#faf5ff']] as [$l, $n, $fg, $bg])
                            <div style="background:{{ $bg }};border-radius:10px;padding:10px 12px;">
                                <div style="font-size:22px;font-weight:800;color:{{ $fg }};font-variant-numeric:tabular-nums;">{{ $n }}</div>
                                <div style="font-size:12px;color:#475569;">{{ $l }}</div>
                            </div>
                        @endforeach
                    </div>
                    @php
                        $hdrBad = collect($rep['header'])->whereIn('status', ['bad']);
                        $sigNames = ['oqituvchi' => "O'qituvchi", 'dekan' => 'Dekan', 'kafedra_mudiri' => 'Kafedra mudiri', 'muhr' => 'Muhr'];
                    @endphp
                    <div style="display:flex;gap:6px;flex-wrap:wrap;font-size:12px;margin-bottom:6px;">
                        @foreach($sigNames as $k => $l)
                            @php $ok = !empty($rep['signatures'][$k]); @endphp
                            <span style="background:{{ $ok ? '#dcfce7' : '#fee2e2' }};color:{{ $ok ? '#166534' : '#991b1b' }};padding:3px 10px;border-radius:999px;font-weight:600;">{{ $ok ? '✓' : '✕' }} {{ $l }}</span>
                        @endforeach
                        @foreach($rep['header'] as $h)
                            @if(in_array($h['key'], ['fan', 'guruh', 'soat', 'kredit', 'maruzachi', 'amaliyot']))
                                @php $hok = $h['status'] === 'ok'; @endphp
                                <span title="{{ $h['note'] }}" style="background:{{ $hok ? '#f1f5f9' : ($h['status'] === 'bad' ? '#fef08a' : '#fed7aa') }};color:{{ $hok ? '#334155' : '#713f12' }};padding:3px 10px;border-radius:999px;font-weight:600;">{{ $hok ? '✓' : '!' }} {{ $h['label'] }}</span>
                            @endif
                        @endforeach
                    </div>
                @elseif($v->ai_check_status === 'done')

                    @php $r = $v->ai_result ?? []; $disc = $r['discrepancies'] ?? []; $sig = $r['signatures'] ?? []; @endphp
                    <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;">
                        @if(($v->ai_verdict ?? '') === 'ok')
                            <span style="background:#dcfce7;color:#166534;padding:5px 14px;border-radius:999px;font-weight:700;">✅ Nomuvofiqlik topilmadi</span>
                        @else
                            <span style="background:#fee2e2;color:#b91c1c;padding:5px 14px;border-radius:999px;font-weight:700;">⚠️ {{ count($disc) }} ta nomuvofiqlik</span>
                        @endif
                        <span style="color:#94a3b8;font-size:12px;">{{ $v->ai_checked_at ? \Carbon\Carbon::parse($v->ai_checked_at)->format('d.m.Y H:i') : '' }}@if(!empty($r['model'])) · {{ $r['model'] }}@endif</span>
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
                    @if($v->ai_error)
                        <div style="background:#fef3c7;color:#92400e;padding:10px;border-radius:8px;font-size:13px;margin-bottom:8px;">{{ $v->ai_error }}</div>
                    @endif
                    <div style="color:#94a3b8;font-size:13px;">Hali tekshirilmagan. "AI tekshirish" tugmasini bosing.</div>
                @endif
            </div>

            {{-- Amallar --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100" style="padding:18px;margin-top:14px;">
                <h3 style="font-size:16px;font-weight:700;color:#1e293b;margin-bottom:12px;">Amallar</h3>

                {{-- Rad etilgan: qayta yuklash uchun o'quv prorektori ruxsati kerak --}}
                @if($v->status === 'rejected' && !$reuploadPermitted)
                    <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:12px;margin-bottom:12px;">
                        <div style="color:#92400e;font-size:13px;">
                            ⚠ Bu vedomost rad etilgan. Qayta yuklash uchun avval <b>o'quv prorektori</b> ruxsat berishi kerak.
                        </div>
                        @if($isViceRector)
                            <form method="POST" action="{{ route('admin.vedomost-submission.allow-reupload', $v->id) }}" style="margin-top:10px;"
                                  onsubmit="return confirm('Bu vedomostni qayta yuklashga ruxsat berilsinmi?');">
                                @csrf
                                <button type="submit" style="background:#047857;color:#fff;border:none;padding:9px 18px;border-radius:8px;cursor:pointer;">
                                    🔓 Qayta yuklashga ruxsat berish
                                </button>
                            </form>
                        @endif
                    </div>
                @endif

                {{-- Yuklash / almashtirish — admin/registrator uchun --}}
                @if($canUploadNow && $isAdminActor)
                    @if($v->status === 'received')
                        <div style="font-size:12px;color:#b45309;margin-bottom:8px;">
                            Vedomost hali tekshirishga olinmagan — fayl noto'g'ri bo'lsa, almashtirishingiz mumkin.
                        </div>
                    @endif
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
                            {{ $v->status === 'received' ? 'Faylni almashtirish' : ($v->status === 'rejected' ? 'Qayta yuklash' : 'Yuklash va qabul qilish') }}
                        </button>
                    </form>
                @endif

                @if($v->status === 'received' && $isAdminActor)
                    <form method="POST" action="{{ route('admin.vedomost-submission.review', $v->id) }}" style="display:inline-block;margin-right:8px;">
                        @csrf
                        <button type="submit" style="background:#b45309;color:#fff;border:none;padding:9px 18px;border-radius:8px;cursor:pointer;">Tekshirishga olish</button>
                    </form>
                @endif

                @if(in_array($v->status, ['received','reviewing']) && $isAdminActor)
                    @if($approvalGate['blocked'])
                        <div style="display:inline-flex;align-items:center;gap:8px;background:#fef3c7;color:#92400e;border:1px solid #fde68a;padding:9px 14px;border-radius:8px;font-size:13px;">
                            <button type="button" disabled style="background:#166534;color:#fff;border:none;padding:6px 14px;border-radius:6px;opacity:.5;cursor:not-allowed;">✔ Tasdiqlash</button>
                            {{ $approvalGate['message'] }}
                        </div>
                    @elseif($approvalGate['needs_reason'])
                        <form method="POST" action="{{ route('admin.vedomost-submission.approve', $v->id) }}"
                              style="background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:12px;margin-bottom:12px;"
                              onsubmit="return confirm('AI natijasiga qaramay vedomost tasdiqlansinmi?');">
                            @csrf
                            <label for="approve_reason" style="font-size:13px;color:#92400e;display:block;margin-bottom:6px;font-weight:600;">{{ $approvalGate['message'] }}</label>
                            <textarea id="approve_reason" name="approve_reason" rows="2" required minlength="5" style="width:100%;border:1px solid #fcd34d;border-radius:8px;padding:8px;" placeholder="Masalan: farqlar AI o'qish xatosi, PDF bilan solishtirib tekshirildi"></textarea>
                            <button type="submit" style="margin-top:8px;background:#166534;color:#fff;border:none;padding:9px 18px;border-radius:8px;cursor:pointer;">✔ Sabab bilan tasdiqlash</button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('admin.vedomost-submission.approve', $v->id) }}" style="display:inline-block;margin-right:8px;"
                              onsubmit="return confirm('Vedomost tasdiqlansinmi?');">
                            @csrf
                            <button type="submit" style="background:#166534;color:#fff;border:none;padding:9px 18px;border-radius:8px;cursor:pointer;">✔ Tasdiqlash</button>
                        </form>
                    @endif

                    <form method="POST" action="{{ route('admin.vedomost-submission.reject', $v->id) }}" style="margin-top:12px;">
                        @csrf
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">
                            <label for="rejection_reason" style="font-size:12px;color:#64748b;">Rad etish sababi (xatolarni yozing)</label>
                            @if(!empty($aiRejectText))
                                <button type="button" onclick="document.getElementById('rejection_reason').value = @js($aiRejectText);"
                                        style="background:none;border:none;color:#1e40af;font-size:12px;font-weight:600;cursor:pointer;text-decoration:underline;">AI topgan xatolarni qo'shish</button>
                            @endif
                        </div>
                        <textarea id="rejection_reason" name="rejection_reason" rows="4" style="width:100%;border:1px solid #cbd5e1;border-radius:8px;padding:8px;" placeholder="Vedomostdagi xatolarni batafsil yozing..."></textarea>
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
