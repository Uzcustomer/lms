<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Hujjat tekshiruvi - TDTU Termiz filiali</title>
    <link rel="icon" href="{{ asset('favicon.png') }}" type="image/png">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f0f2f5;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            max-width: 900px;
            width: 100%;
        }
        .card {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.08);
            width: 100%;
            overflow: hidden;
        }
        .card-header {
            background: linear-gradient(135deg, #1a365d 0%, #2b6cb0 100%);
            padding: 24px;
            text-align: center;
            color: #ffffff;
        }
        .card-header img {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            margin-bottom: 12px;
            border: 3px solid rgba(255,255,255,0.3);
        }
        .card-header h1 {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 4px;
        }
        .card-header p {
            font-size: 13px;
            opacity: 0.85;
        }
        .status-badge {
            display: inline-block;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            margin: 16px 0;
            background: #c6f6d5;
            color: #22543d;
        }
        .card-body {
            padding: 24px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #edf2f7;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .info-label {
            font-size: 13px;
            color: #718096;
            font-weight: 500;
        }
        .info-value {
            font-size: 13px;
            color: #1a202c;
            font-weight: 600;
            text-align: right;
            max-width: 60%;
        }
        .card-footer {
            padding: 16px 24px;
            background: #f7fafc;
            text-align: center;
            font-size: 11px;
            color: #a0aec0;
            border-top: 1px solid #edf2f7;
        }
        .verification-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 48px;
            height: 48px;
            border-radius: 50%;
            margin: 0 auto 12px;
            background: #c6f6d5;
        }
        .verification-icon svg { width: 24px; height: 24px; }
        .pdf-section {
            margin-top: 16px;
        }
        .pdf-embed {
            width: 100%;
            height: 600px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
        }
        .pdf-download-btn {
            display: inline-block;
            margin-top: 12px;
            padding: 10px 20px;
            background: linear-gradient(135deg, #1a365d 0%, #2b6cb0 100%);
            color: #ffffff;
            text-decoration: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            transition: opacity 0.2s;
        }
        .pdf-download-btn:hover {
            opacity: 0.9;
        }
        .pdf-download-btn svg {
            width: 16px;
            height: 16px;
            vertical-align: middle;
            margin-right: 6px;
        }
        @media (max-width: 640px) {
            .pdf-embed {
                height: 400px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header">
                <img src="{{ asset('logo.png') }}" alt="Logo">
                <h1>TDTU Termiz filiali</h1>
                <p>Hujjat haqiqiyligini tekshirish</p>
            </div>

            <div class="card-body" style="text-align: center;">
                <div class="verification-icon">
                    <svg fill="none" stroke="#22543d" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path>
                    </svg>
                </div>
                <span class="status-badge">Haqiqiy hujjat</span>
            </div>

            <div class="card-body" style="padding-top: 0;">
                <div class="info-row">
                    <span class="info-label">Hujjat turi</span>
                    <span class="info-value">{{ $verification->document_type }}</span>
                </div>
                @if($verification->subject_name)
                <div class="info-row">
                    <span class="info-label">Fan</span>
                    <span class="info-value">{{ $verification->subject_name }}</span>
                </div>
                @endif
                @if($verification->group_names)
                <div class="info-row">
                    <span class="info-label">Guruh</span>
                    <span class="info-value">{{ $verification->group_names }}</span>
                </div>
                @endif
                @if($verification->semester_name)
                <div class="info-row">
                    <span class="info-label">Semestr</span>
                    <span class="info-value">{{ $verification->semester_name }}</span>
                </div>
                @endif
                @if($verification->department_name)
                <div class="info-row">
                    <span class="info-label">Fakultet</span>
                    <span class="info-value">{{ $verification->department_name }}</span>
                </div>
                @endif
                <div class="info-row">
                    <span class="info-label">Yaratilgan sana</span>
                    <span class="info-value">{{ $verification->generated_at->format('d.m.Y H:i') }}</span>
                </div>
                @if($verification->generated_by)
                <div class="info-row">
                    <span class="info-label">Yaratgan</span>
                    <span class="info-value">{{ $verification->generated_by }}</span>
                </div>
                @endif
            </div>

            @if($documentUrl)
            <div class="card-body pdf-section" style="padding-top: 0;">
                <embed src="{{ $documentUrl }}" type="application/pdf" class="pdf-embed">
                <div style="text-align: center;">
                    <a href="{{ $documentUrl }}" target="_blank" class="pdf-download-btn">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        Hujjatni yuklab olish
                    </a>
                </div>
            </div>
            @endif

            <div class="card-footer">
                TDTU Termiz filiali mark platformasi &copy; {{ date('Y') }}
            </div>
        </div>
    </div>
</body>
</html>
