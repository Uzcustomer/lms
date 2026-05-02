<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <title>{{ __('retake.cert_title') }}</title>
    <style>
        @page { margin: 1.6cm 1.5cm 1.4cm 1.5cm; }
        body { font-family: DejaVu Sans, sans-serif; color: #1a1a1a; font-size: 11px; }

        .top { width: 100%; }
        .top td { vertical-align: middle; }
        .top .logo-cell { width: 80px; }
        .top .logo-cell img { width: 70px; height: 70px; }
        .top .title-cell { text-align: center; }
        .top .title-cell .uni { font-size: 13px; font-weight: bold; letter-spacing: 0.4px; }
        .top .title-cell .filial { font-size: 10.5px; color: #4b5563; margin-top: 2px; }
        .top .badge-cell { width: 130px; text-align: right; }
        .top .badge { display: inline-block; padding: 5px 10px; background: #ecfdf5; border: 1px solid #10b981; color: #065f46; font-size: 10px; font-weight: bold; border-radius: 4px; }

        .divider { border-top: 2px solid #1f2937; margin: 12px 0 16px; }

        .h1 { text-align: center; font-size: 16px; font-weight: bold; margin: 0 0 4px; letter-spacing: 0.5px; }
        .h1-sub { text-align: center; font-size: 11px; color: #4b5563; margin-bottom: 16px; }

        table.info-grid { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        table.info-grid td { padding: 4px 6px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        table.info-grid td.label { width: 28%; color: #6b7280; font-size: 10.5px; }

        h2.section { font-size: 12px; margin: 16px 0 6px; color: #1f2937; }

        table.subjects { width: 100%; border-collapse: collapse; margin-top: 4px; font-size: 10.5px; }
        table.subjects th { background: #f3f4f6; padding: 6px; text-align: left; border: 1px solid #d1d5db; font-weight: bold; }
        table.subjects td { padding: 6px; border: 1px solid #d1d5db; vertical-align: top; }

        .totals { margin-top: 8px; font-size: 11px; text-align: right; }
        .stamp { margin-top: 14px; font-size: 10px; color: #6b7280; }

        h2.signatures-title { font-size: 12px; margin: 22px 0 8px; color: #1f2937; }
        table.signatures { width: 100%; border-collapse: collapse; }
        table.signatures td { width: 33.33%; padding: 8px 10px; vertical-align: top; border: 1px solid #d1d5db; }
        table.signatures .role { font-size: 9.5px; text-transform: uppercase; color: #6b7280; letter-spacing: 0.4px; margin-bottom: 2px; }
        table.signatures .name { font-size: 11px; font-weight: bold; color: #111827; }
        table.signatures .date { font-size: 9.5px; color: #6b7280; margin-top: 2px; }
        table.signatures .empty { font-size: 10px; color: #9ca3af; font-style: italic; }

        table.footer { width: 100%; border-collapse: collapse; margin-top: 24px; border-top: 1px solid #d1d5db; padding-top: 10px; }
        table.footer td { vertical-align: middle; padding: 8px 0; }
        table.footer .qr-cell { width: 130px; text-align: left; }
        table.footer .qr-cell img { width: 110px; height: 110px; }
        table.footer .verify-cell { padding-left: 14px; font-size: 9px; color: #4b5563; }
        table.footer .verify-cell .verify-token { font-family: monospace; word-break: break-all; }
    </style>
</head>
<body>

    <table class="top">
        <tr>
            <td class="logo-cell">
                @if($logoAbsPath)
                    <img src="{{ $logoAbsPath }}" alt="logo">
                @endif
            </td>
            <td class="title-cell">
                <div class="uni">{{ __('retake.cert_university') }}</div>
                <div class="filial">{{ __('retake.cert_filial') }}</div>
            </td>
            <td class="badge-cell">
                <span class="badge">{{ __('retake.cert_badge_approved') }} ✓</span>
            </td>
        </tr>
    </table>

    <div class="divider"></div>

    <div class="h1">{{ __('retake.cert_title') }}</div>
    <div class="h1-sub">{{ __('retake.cert_subtitle') }}</div>

    <table class="info-grid">
        <tr>
            <td class="label">{{ __('retake.label_student') }}</td>
            <td><strong>{{ $student->full_name ?? '—' }}</strong></td>
        </tr>
        <tr>
            <td class="label">{{ __('retake.label_hemis_id') }}</td>
            <td>{{ $group->student_hemis_id }}</td>
        </tr>
        <tr>
            <td class="label">{{ __('retake.label_faculty') }}</td>
            <td>{{ $student->department_name ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">{{ __('retake.label_specialty') }}</td>
            <td>{{ $student->specialty_name ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">{{ __('retake.label_course_group') }}</td>
            <td>{{ $student->level_name ?? $student->level_code }} · {{ $student->group_name ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">{{ __('retake.label_uuid') }}</td>
            <td style="font-family: monospace; font-size: 10px;">{{ $group->group_uuid }}</td>
        </tr>
        <tr>
            <td class="label">{{ __('retake.label_submitted_at') }}</td>
            <td>{{ $group->created_at->format('Y-m-d H:i') }}</td>
        </tr>
    </table>

    <h2 class="section">{{ __('retake.section_subjects') }}</h2>
    <table class="subjects">
        <thead>
            <tr>
                <th style="width: 4%;">{{ __('retake.col_num') }}</th>
                <th>{{ __('retake.col_subject') }}</th>
                <th style="width: 14%;">{{ __('retake.col_semester') }}</th>
                <th style="width: 7%; text-align: right;">{{ __('retake.col_credit') }}</th>
                <th style="width: 22%;">{{ __('retake.col_teacher') }}</th>
                <th style="width: 17%;">{{ __('retake.col_dates') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($approvedApps as $i => $app)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>
                        {{ $app->subject_name }}
                        @if($app->retakeGroup)
                            <div style="color:#6b7280; font-size: 9.5px; margin-top:2px;">
                                {{ __('retake.inline_group') }}: {{ $app->retakeGroup->name }}
                            </div>
                        @endif
                    </td>
                    <td>{{ $app->semester_name }}</td>
                    <td style="text-align: right;">{{ number_format((float)$app->credit, 1) }}</td>
                    <td>{{ $app->retakeGroup?->teacher_name ?? '—' }}</td>
                    <td>
                        @if($app->retakeGroup)
                            {{ $app->retakeGroup->start_date?->format('Y-m-d') }}
                            <br>
                            {{ $app->retakeGroup->end_date?->format('Y-m-d') }}
                        @else
                            —
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals">
        <strong>{{ __('retake.totals_credits', ['credits' => number_format($totalCredits, 1)]) }}</strong>
        &nbsp;·&nbsp;
        {{ __('retake.totals_amount') }} <strong>{{ number_format($totalAmount, 0, '.', ' ') }} UZS</strong>
    </div>

    <h2 class="signatures-title">{{ __('retake.sig_section') }}</h2>
    <table class="signatures">
        <tr>
            <td>
                <div class="role">{{ __('retake.sig_dean') }}</div>
                @if($signers['dean']['name'] ?? null)
                    <div class="name">{{ $signers['dean']['name'] }}</div>
                    <div class="date">{{ $signers['dean']['date'] ?? '' }}</div>
                @else
                    <div class="empty">{{ __('retake.sig_no_signature') }}</div>
                @endif
            </td>
            <td>
                <div class="role">{{ __('retake.sig_registrar') }}</div>
                @if($signers['registrar']['name'] ?? null)
                    <div class="name">{{ $signers['registrar']['name'] }}</div>
                    <div class="date">{{ $signers['registrar']['date'] ?? '' }}</div>
                @else
                    <div class="empty">{{ __('retake.sig_no_signature') }}</div>
                @endif
            </td>
            <td>
                <div class="role">{{ __('retake.sig_academic') }}</div>
                @if($signers['academic']['name'] ?? null)
                    <div class="name">{{ $signers['academic']['name'] }}</div>
                    <div class="date">{{ $signers['academic']['date'] ?? '' }}</div>
                @else
                    <div class="empty">{{ __('retake.sig_no_signature') }}</div>
                @endif
            </td>
        </tr>
    </table>

    <div class="stamp">
        {{ __('retake.stamp_note') }}
    </div>

    <table class="footer">
        <tr>
            <td class="qr-cell">
                <img src="{{ $qrAbsPath }}" alt="QR">
            </td>
            <td class="verify-cell">
                <div><strong>{{ __('retake.verify_label') }}</strong></div>
                <div>{{ $verifyUrl }}</div>
                <div class="verify-token">{{ __('retake.verify_token') }} {{ $verificationToken }}</div>
                <div style="margin-top:6px;">{{ __('retake.verify_generated_at') }} {{ now()->format('Y-m-d H:i') }}</div>
            </td>
        </tr>
    </table>

</body>
</html>
