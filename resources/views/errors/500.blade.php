@php
    // Tafsilot faqat admin/superadminga: oddiy foydalanuvchiga xato matni
    // ko'rsatilmaydi (unda fayl yo'llari va so'rov ma'lumotlari bo'ladi).
    $errUser = auth()->guard('web')->user() ?? auth()->guard('teacher')->user();
    $errCanSee = false;
    try {
        $errCanSee = $errUser && method_exists($errUser, 'hasAnyRole') && $errUser->hasAnyRole(['superadmin', 'admin']);
    } catch (\Throwable $e) {
        $errCanSee = false;
    }

    $errDetails = null;
    if ($errCanSee && isset($exception) && $exception instanceof \Throwable) {
        $root = $exception;
        while ($root->getPrevious()) {
            $root = $root->getPrevious();
        }
        $errDetails = [
            'class' => get_class($root),
            'message' => $root->getMessage(),
            'file' => $root->getFile() . ':' . $root->getLine(),
            'url' => request()->fullUrl(),
            'trace' => collect(explode("\n", $root->getTraceAsString()))->take(20)->implode("\n"),
        ];
    }
@endphp
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>500 — Server xatosi</title>
    <style>
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
               background: #1a202c; color: #cbd5e0; font-family: system-ui, -apple-system, "Segoe UI", sans-serif; padding: 24px; }
        .err-wrap { text-align: center; max-width: 560px; }
        .err-code { font-size: 22px; letter-spacing: .12em; color: #718096; }
        .err-code b { color: #e2e8f0; font-weight: 600; }
        .err-text { margin: 14px 0 0; font-size: 15px; line-height: 1.6; color: #a0aec0; }
        .err-actions { margin-top: 22px; display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; }
        .err-btn { padding: 9px 18px; border-radius: 9px; font-size: 14px; font-weight: 600; cursor: pointer;
                   border: 1px solid #4a5568; background: #2d3748; color: #e2e8f0; text-decoration: none; }
        .err-btn:hover { background: #374151; }
        .err-btn.is-primary { background: #3182ce; border-color: #3182ce; color: #fff; }

        .err-modal { position: fixed; inset: 0; display: none; align-items: center; justify-content: center;
                     padding: 16px; background: rgba(0,0,0,.6); }
        .err-modal.is-open { display: flex; }
        .err-box { width: 100%; max-width: 900px; max-height: calc(100vh - 32px); display: flex; flex-direction: column;
                   background: #0f1620; border: 1px solid #2d3748; border-radius: 14px; overflow: hidden; text-align: left; }
        .err-head { display: flex; justify-content: space-between; align-items: center; gap: 12px;
                    padding: 14px 18px; background: #7f1d1d; color: #fff; font-weight: 700; font-size: 15px; }
        .err-close { border: 0; background: rgba(255,255,255,.18); color: #fff; border-radius: 8px;
                     padding: 5px 12px; font-size: 13px; font-weight: 700; cursor: pointer; }
        .err-body { padding: 16px 18px; overflow: auto; }
        .err-row { margin-bottom: 14px; }
        .err-label { font-size: 11px; text-transform: uppercase; letter-spacing: .08em; color: #718096; margin-bottom: 4px; }
        .err-value { font-family: ui-monospace, "Cascadia Code", Consolas, monospace; font-size: 13px; line-height: 1.55;
                     color: #e2e8f0; white-space: pre-wrap; word-break: break-word; }
        .err-value.is-message { color: #fca5a5; font-size: 14px; }
        .err-trace { background: #0b1017; border: 1px solid #1f2937; border-radius: 10px; padding: 12px;
                     font-family: ui-monospace, Consolas, monospace; font-size: 12px; line-height: 1.6; color: #94a3b8;
                     white-space: pre; overflow-x: auto; }
    </style>
</head>
<body>
    <div class="err-wrap">
        <div class="err-code"><b>500</b> &nbsp;|&nbsp; SERVER ERROR</div>
        <p class="err-text">Sahifani ochishda xatolik yuz berdi. Qayta urinib ko'ring — takrorlansa, administratorga xabar bering.</p>
        <div class="err-actions">
            <a class="err-btn" href="{{ url()->previous() }}">Orqaga</a>
            <a class="err-btn" href="{{ url('/') }}">Bosh sahifa</a>
            @if($errDetails)
                <button type="button" class="err-btn is-primary" onclick="document.getElementById('errModal').classList.add('is-open')">
                    Xatolik tafsiloti
                </button>
            @endif
        </div>
    </div>

    @if($errDetails)
        <div class="err-modal" id="errModal" onclick="if (event.target === this) this.classList.remove('is-open')">
            <div class="err-box">
                <div class="err-head">
                    <span>Xatolik tafsiloti</span>
                    <button type="button" class="err-close" onclick="document.getElementById('errModal').classList.remove('is-open')">Yopish</button>
                </div>
                <div class="err-body">
                    <div class="err-row">
                        <div class="err-label">Xabar</div>
                        <div class="err-value is-message">{{ $errDetails['message'] }}</div>
                    </div>
                    <div class="err-row">
                        <div class="err-label">Turi</div>
                        <div class="err-value">{{ $errDetails['class'] }}</div>
                    </div>
                    <div class="err-row">
                        <div class="err-label">Fayl</div>
                        <div class="err-value">{{ $errDetails['file'] }}</div>
                    </div>
                    <div class="err-row">
                        <div class="err-label">Manzil</div>
                        <div class="err-value">{{ $errDetails['url'] }}</div>
                    </div>
                    <div class="err-row" style="margin-bottom:0;">
                        <div class="err-label">Stek (birinchi 20 qator)</div>
                        <div class="err-trace">{{ $errDetails['trace'] }}</div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</body>
</html>
