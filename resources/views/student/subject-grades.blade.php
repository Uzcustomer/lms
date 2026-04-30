@php
    function getStatusBadge($status) {
        $statuses = [
            'pending' => ['color' => 'yellow', 'text' => __('Kutilmoqda')],
            'recorded' => ['color' => 'green', 'text' => __('Baholangan')],
            'retake' => ['color' => 'blue', 'text' => __('Qayta topshirilgan')],
            'closed' => ['color' => 'red', 'text' => __('Yopilgan')],
        ];

        return $statuses[$status] ?? ['color' => 'gray', 'text' => __('Noma\'lum')];
    }

    function statusStyle($status) {
        $styles = [
            'pending'  => ['bg' => 'rgba(250, 204, 21, 0.18)',  'border' => 'rgba(250, 204, 21, 0.45)',  'color' => '#854d0e', 'dot' => '#eab308'],
            'recorded' => ['bg' => 'rgba(34, 197, 94, 0.18)',   'border' => 'rgba(34, 197, 94, 0.45)',   'color' => '#166534', 'dot' => '#22c55e'],
            'retake'   => ['bg' => 'rgba(59, 130, 246, 0.18)',  'border' => 'rgba(59, 130, 246, 0.45)',  'color' => '#1e40af', 'dot' => '#3b82f6'],
            'closed'   => ['bg' => 'rgba(239, 68, 68, 0.18)',   'border' => 'rgba(239, 68, 68, 0.45)',   'color' => '#991b1b', 'dot' => '#ef4444'],
        ];
        return $styles[$status] ?? ['bg' => 'rgba(148, 163, 184, 0.18)', 'border' => 'rgba(148, 163, 184, 0.45)', 'color' => '#475569', 'dot' => '#94a3b8'];
    }
@endphp
<x-student-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-sm text-gray-800 leading-tight">
            {{ __('Fanga tegishli baholar') }}
        </h2>
    </x-slot>

    @push('styles')
    <style>
        .aurora-page {
            position: relative;
            min-height: calc(100vh - 80px);
            padding: 16px 0 40px;
            overflow: hidden;
            isolation: isolate;
        }
        .aurora-page::before,
        .aurora-page::after {
            content: '';
            position: absolute;
            inset: -20%;
            z-index: -1;
            pointer-events: none;
        }
        .aurora-page::before {
            background:
                radial-gradient(1100px 700px at 12% 8%, rgba(99,102,241,0.32), transparent 60%),
                radial-gradient(900px 600px at 92% 18%, rgba(236,72,153,0.28), transparent 65%),
                radial-gradient(1000px 700px at 50% 100%, rgba(20,184,166,0.30), transparent 65%),
                radial-gradient(700px 500px at 85% 90%, rgba(245,158,11,0.22), transparent 60%);
            filter: blur(40px);
            animation: aurora-drift 22s ease-in-out infinite alternate;
        }
        .aurora-page::after {
            background:
                radial-gradient(600px 400px at 70% 30%, rgba(168,85,247,0.22), transparent 65%),
                radial-gradient(800px 500px at 20% 70%, rgba(56,189,248,0.20), transparent 65%);
            filter: blur(60px);
            animation: aurora-drift 28s ease-in-out infinite alternate-reverse;
        }
        @keyframes aurora-drift {
            0%   { transform: translate3d(0, 0, 0) scale(1); }
            50%  { transform: translate3d(-3%, 2%, 0) scale(1.06); }
            100% { transform: translate3d(2%, -2%, 0) scale(1.02); }
        }

        .glass-container {
            max-width: 980px;
            margin: 0 auto;
            padding: 0 16px;
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.55);
            backdrop-filter: blur(18px) saturate(160%);
            -webkit-backdrop-filter: blur(18px) saturate(160%);
            border: 1px solid rgba(255, 255, 255, 0.55);
            border-radius: 18px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08), inset 0 1px 0 rgba(255,255,255,0.7);
            transition: transform 0.18s ease, box-shadow 0.18s ease;
        }
        .glass-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 16px 40px rgba(15, 23, 42, 0.12), inset 0 1px 0 rgba(255,255,255,0.8);
        }

        .grades-stack {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .grade-item {
            padding: 16px 18px;
        }
        .grade-item-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 12px;
            padding-bottom: 10px;
            border-bottom: 1px dashed rgba(100, 116, 139, 0.25);
        }
        .grade-item-num {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 30px; height: 30px;
            border-radius: 9px;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: #fff;
            font-weight: 700;
            font-size: 13px;
            box-shadow: 0 4px 10px rgba(99,102,241,0.35);
            flex-shrink: 0;
        }
        .grade-item-date {
            font-size: 13px;
            font-weight: 700;
            color: #1e293b;
        }
        .grade-status-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
            border-width: 1px;
            border-style: solid;
        }
        .grade-status-pill .dot {
            width: 7px; height: 7px;
            border-radius: 50%;
            display: inline-block;
        }

        .grade-fields {
            display: grid;
            grid-template-columns: 1fr;
            gap: 8px;
        }
        .grade-field {
            display: grid;
            grid-template-columns: 130px 1fr;
            align-items: start;
            padding: 6px 10px;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.42);
            border: 1px solid rgba(255, 255, 255, 0.55);
            font-size: 13px;
        }
        .grade-field-label {
            color: #64748b;
            font-weight: 600;
            font-size: 11.5px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .grade-field-value {
            color: #0f172a;
            font-weight: 600;
            word-break: break-word;
        }
        .grade-value-num {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 3px 12px;
            border-radius: 8px;
            background: linear-gradient(135deg, rgba(99,102,241,0.18), rgba(236,72,153,0.18));
            border: 1px solid rgba(99,102,241,0.3);
            color: #312e81;
            font-weight: 800;
            font-size: 14px;
        }
        .grade-value-nb {
            color: #b91c1c;
            font-weight: 800;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #475569;
        }

        .yn-section {
            margin-top: 22px;
            padding: 18px 20px;
        }
        .yn-section h3 {
            font-size: 15px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 12px;
        }
        .yn-banner {
            border-radius: 12px;
            padding: 12px 14px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
            font-weight: 600;
        }
        .yn-banner.info  { background: rgba(59,130,246,0.12);  border: 1px solid rgba(59,130,246,0.32); color: #1e40af; }
        .yn-banner.ok    { background: rgba(34,197,94,0.12);   border: 1px solid rgba(34,197,94,0.32);  color: #166534; }
        .yn-banner.warn  { background: rgba(234,179,8,0.14);   border: 1px solid rgba(234,179,8,0.35);  color: #854d0e; }
        .yn-banner.error { background: rgba(239,68,68,0.12);   border: 1px solid rgba(239,68,68,0.32);  color: #991b1b; }

        .yn-btn {
            padding: 9px 18px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 700;
            border: none;
            cursor: pointer;
            transition: transform 0.15s, box-shadow 0.15s;
        }
        .yn-btn:hover { transform: translateY(-1px); }
        .yn-btn-green  { background: linear-gradient(135deg, #16a34a, #22c55e); color: #fff; box-shadow: 0 6px 14px rgba(34,197,94,0.35); }
        .yn-btn-green:hover { box-shadow: 0 8px 18px rgba(34,197,94,0.45); }
        .yn-btn-red    { background: linear-gradient(135deg, #dc2626, #ef4444); color: #fff; box-shadow: 0 6px 14px rgba(239,68,68,0.35); }
        .yn-btn-red:hover { box-shadow: 0 8px 18px rgba(239,68,68,0.45); }

        @media (max-width: 600px) {
            .grade-field { grid-template-columns: 100px 1fr; }
            .grade-item-head { flex-wrap: wrap; }
            .glass-container { padding: 0 12px; }
        }
    </style>
    @endpush

    <div class="aurora-page">
        <div class="glass-container">
            @if(count($grades) === 0)
                <div class="glass-card empty-state">
                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin: 0 auto 8px; color:#94a3b8;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h4m2 6H7a2 2 0 01-2-2V4a2 2 0 012-2h7l5 5v11a2 2 0 01-2 2z" />
                    </svg>
                    <div style="font-size: 14px; font-weight: 600;">{{ __('Bu fan bo\'yicha baholar yo\'q') }}</div>
                </div>
            @else
                <div class="grades-stack">
                    @foreach($grades as $index => $grade)
                        @php $st = statusStyle($grade->status); $badge = getStatusBadge($grade->status); @endphp
                        <div class="glass-card grade-item">
                            <div class="grade-item-head">
                                <div style="display:flex;align-items:center;gap:10px;">
                                    <span class="grade-item-num">{{ $index + 1 }}</span>
                                    <span class="grade-item-date">{{ format_date($grade->lesson_date) }}</span>
                                </div>
                                <span class="grade-status-pill" style="background: {{ $st['bg'] }}; border-color: {{ $st['border'] }}; color: {{ $st['color'] }};">
                                    <span class="dot" style="background: {{ $st['dot'] }};"></span>
                                    {{ __($badge['text']) }}
                                </span>
                            </div>

                            <div class="grade-fields">
                                <div class="grade-field">
                                    <div class="grade-field-label">{{ __('Fan') }}</div>
                                    <div class="grade-field-value">{{ $grade->subject_name }}</div>
                                </div>
                                <div class="grade-field">
                                    <div class="grade-field-label">{{ __("Mashg'ulot turi") }}</div>
                                    <div class="grade-field-value">{{ $grade->training_type_name }}</div>
                                </div>
                                <div class="grade-field">
                                    <div class="grade-field-label">{{ __('Juftlik') }}</div>
                                    <div class="grade-field-value">{{ $grade->lesson_pair_name }} <span style="color:#64748b;font-weight:500;font-size:12px;">({{ $grade->lesson_pair_start_time }} - {{ $grade->lesson_pair_end_time }})</span></div>
                                </div>
                                <div class="grade-field">
                                    <div class="grade-field-label">{{ __('Xodim') }}</div>
                                    <div class="grade-field-value">{{ $grade->employee_name }}</div>
                                </div>
                                <div class="grade-field">
                                    <div class="grade-field-label">{{ __('Baho') }}</div>
                                    <div class="grade-field-value">
                                        @if($grade->status == 'pending')
                                            @if($grade->reason == 'absent')
                                                <span class="grade-value-nb">0 (NB)</span>
                                            @else
                                                <span class="grade-value-num">{{ $grade->grade }}</span>
                                            @endif
                                        @elseif($grade->status == 'retake')
                                            <span class="grade-value-num">{{ $grade->grade ?? '0 (NB)' }} → {{ $grade->retake_grade }}</span>
                                        @else
                                            <span class="grade-value-num">{{ $grade->grade }}</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- YN rozilik bo'limi --}}
            @if(isset($subjectId))
            <div class="glass-card yn-section">
                <h3>{{ __('Yakuniy nazorat (YN) ga rozilik') }}</h3>

                @if(isset($ynSubmission) && $ynSubmission)
                    <div class="yn-banner info">
                        <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                        </svg>
                        {{ __('YN ga yuborilgan') }} ({{ $ynSubmission->submitted_at->format('d.m.Y H:i') }}). {{ __('Baholar qulflangan') }}.
                    </div>
                @elseif(isset($ynConsent) && $ynConsent)
                    <div style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;">
                        @if($ynConsent->status === 'approved')
                            <div class="yn-banner ok" style="flex:1;min-width:240px;">
                                <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                <span>{{ __('YN topshirishga rozilik yuborildi') }} <span style="font-weight:500;opacity:0.85;">({{ $ynConsent->submitted_at->format('d.m.Y H:i') }})</span></span>
                            </div>
                        @else
                            <div class="yn-banner error" style="flex:1;min-width:240px;">
                                <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                                </svg>
                                <span>{{ __('YN topshirishdan rad etildi') }} <span style="font-weight:500;opacity:0.85;">({{ $ynConsent->submitted_at->format('d.m.Y H:i') }})</span></span>
                            </div>
                        @endif
                        <form method="POST" action="{{ route('student.yn-consent') }}">
                            @csrf
                            <input type="hidden" name="subject_id" value="{{ $subjectId }}">
                            @if($ynConsent->status === 'approved')
                                <button type="submit" name="status" value="rejected" class="yn-btn yn-btn-red"
                                    onclick="return confirm('{{ __("Rozilikni bekor qilmoqchimisiz?") }}')">
                                    {{ __('Bekor qilish') }}
                                </button>
                            @else
                                <button type="submit" name="status" value="approved" class="yn-btn yn-btn-green"
                                    onclick="return confirm('{{ __("YN topshirishga rozilik berasizmi?") }}')">
                                    {{ __('Rozilik berish') }}
                                </button>
                            @endif
                        </form>
                    </div>
                @else
                    <div class="yn-banner warn" style="margin-bottom:12px;">
                        <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l6.518 11.59c.75 1.334-.213 2.98-1.742 2.98H3.481c-1.53 0-2.493-1.646-1.743-2.98l6.519-11.59zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                        {{ __("Darslar tugagandan so'ng, yakuniy nazorat (YN) ga kirishga ruxsat berish uchun roziligingizni bildiring.") }}
                    </div>
                    <div style="display:flex;flex-wrap:wrap;gap:10px;">
                        <form method="POST" action="{{ route('student.yn-consent') }}">
                            @csrf
                            <input type="hidden" name="subject_id" value="{{ $subjectId }}">
                            <button type="submit" name="status" value="approved" class="yn-btn yn-btn-green"
                                onclick="return confirm('{{ __("YN topshirishga tayyorman — rozilik berasizmi?") }}')">
                                {{ __('YN topshirishga tayyorman') }}
                            </button>
                        </form>
                        <form method="POST" action="{{ route('student.yn-consent') }}">
                            @csrf
                            <input type="hidden" name="subject_id" value="{{ $subjectId }}">
                            <button type="submit" name="status" value="rejected" class="yn-btn yn-btn-red"
                                onclick="return confirm('{{ __("YN topshirishga rozi emasligingizni bildirasizmi?") }}')">
                                {{ __('Rozi emasman') }}
                            </button>
                        </form>
                    </div>
                @endif
            </div>
            @endif
        </div>
    </div>
</x-student-app-layout>
