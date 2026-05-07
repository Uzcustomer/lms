@php
    $w = $template['width_mm'];
    $h = $template['height_mm'];
    $titleText = trim(($template['institution'] ?? '') . ' ' . ($template['branch'] ?? ''));
    $ctaText = $template['description'] ?: "QR kodni skanerlang va xodim xizmatini xolis baholang";
    $padX = 1.5;
    $padY = 1.5;
    $titleH = $titleText !== '' ? 4.5 : 0;
    $gapV = $titleText !== '' ? 1.0 : 0;
    $rowH = $h - $padY * 2 - $titleH - $gapV;
    $rowW = $w - $padX * 2;
    $qrSize = max(15, min($rowH, $rowW * 0.55));
    $leftW = max(8, $rowW - $qrSize - 1.5);
    $logoSize = $qrSize * 0.18;
@endphp
<div id="{{ $cardId }}" style="width:{{ $w }}mm; height:{{ $h }}mm; padding:{{ $padY }}mm {{ $padX }}mm; box-sizing:border-box; display:flex; flex-direction:column; font-family:Arial,sans-serif; background:white; overflow:hidden;">
    @if($titleText !== '')
    <div style="height:{{ $titleH }}mm; display:flex; align-items:center; justify-content:center; text-align:center;">
        <div style="font-size:2.0mm; color:#1e3a8a; font-weight:700; line-height:1.1; white-space:nowrap;">{{ $titleText }}</div>
    </div>
    <div style="height:{{ $gapV }}mm;"></div>
    @endif
    <div style="flex:1 1 auto; display:flex; flex-direction:row; align-items:center; gap:1.5mm;">
        <div style="width:{{ $leftW }}mm; display:flex; align-items:center; justify-content:center; text-align:center;">
            <div style="font-size:2.4mm; color:#0f172a; font-weight:600; line-height:1.2;">
                {{ $ctaText }}
            </div>
        </div>
        <div style="width:{{ $qrSize }}mm; height:{{ $qrSize }}mm; flex:0 0 auto; position:relative; display:flex; align-items:center; justify-content:center;">
            <div style="width:100%; height:100%; display:flex; align-items:center; justify-content:center;">
                {!! str_replace('<svg ', '<svg style="width:100%;height:100%;display:block;" ', QrCode::size(300)->errorCorrection('H')->margin(0)->generate(route('staff-evaluate.form', $teacher->eval_qr_token))) !!}
            </div>
            @if($template['show_logo'])
            <div style="position:absolute; top:50%; left:50%; transform:translate(-50%,-50%);">
                <div style="background:white; border-radius:50%; padding:0.3mm; display:flex;">
                    <img src="{{ asset('logo.png') }}" alt="Logo" style="width:{{ $logoSize }}mm; height:{{ $logoSize }}mm; border-radius:50%;">
                </div>
            </div>
            @endif
        </div>
    </div>
</div>
