@php
    $w = $template['width_mm'];
    $h = $template['height_mm'];
    $titleText = trim(($template['institution'] ?? '') . ' ' . ($template['branch'] ?? ''));
    $ctaText = $template['description'] ?: "QR kodni skanerlang va xodim xizmatini xolis baholang";
    $qrSize = $template['qr_size_mm'];
    $qrX    = $template['qr_x_mm'];
    $qrY    = $template['qr_y_mm'];
    $tX     = $template['text_x_mm'];
    $tY     = $template['text_y_mm'];
    $tW     = $template['text_w_mm'];
    $tH     = $template['text_h_mm'];
    $tSize  = $template['text_size_mm'];
    $logoSize = $qrSize * 0.18;
@endphp
<div id="{{ $cardId }}" style="width:{{ $w }}mm; height:{{ $h }}mm; box-sizing:border-box; position:relative; font-family:Arial,sans-serif; background:white; overflow:hidden;">
    @if($titleText !== '')
    <div style="position:absolute; left:{{ $template['title_x_mm'] }}mm; top:{{ $template['title_y_mm'] }}mm; width:{{ $template['title_w_mm'] }}mm; height:{{ $template['title_h_mm'] }}mm; display:flex; align-items:center; justify-content:center; text-align:center;">
        <div style="font-size:{{ $template['title_size_mm'] }}mm; color:#1e3a8a; font-weight:700; line-height:1.1;">{{ $titleText }}</div>
    </div>
    @endif
    <div style="position:absolute; left:{{ $tX }}mm; top:{{ $tY }}mm; width:{{ $tW }}mm; height:{{ $tH }}mm; display:flex; align-items:center; justify-content:center; text-align:center;">
        <div style="font-size:{{ $tSize }}mm; color:#0f172a; font-weight:600; line-height:1.2;">
            {{ $ctaText }}
        </div>
    </div>
    <div style="position:absolute; left:{{ $qrX }}mm; top:{{ $qrY }}mm; width:{{ $qrSize }}mm; height:{{ $qrSize }}mm;">
        {!! str_replace('<svg ', '<svg style="width:100%;height:100%;display:block;" ', QrCode::size(300)->errorCorrection('H')->margin(0)->generate(route('staff-evaluate.form', $teacher->eval_qr_token))) !!}
        @if($template['show_logo'])
        <div style="position:absolute; top:50%; left:50%; transform:translate(-50%,-50%);">
            <div style="background:white; border-radius:50%; padding:0.3mm; display:flex;">
                <img src="{{ asset('logo.png') }}" alt="Logo" style="width:{{ $logoSize }}mm; height:{{ $logoSize }}mm; border-radius:50%;">
            </div>
        </div>
        @endif
    </div>
</div>
