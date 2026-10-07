@extends('kiosk.fan-testi.layout')

@section('title', "Universitet tarmog'i")

@section('styles')
        .n-ip {
            display: inline-block; margin-top: 14px; padding: 6px 12px;
            border: 1px dashed var(--line); border-radius: 5px;
            color: var(--muted); font-size: 13px; font-variant-numeric: tabular-nums;
        }
@endsection

@section('content')
    <div class="k-card">
        <div class="k-head">
            <div class="k-eyebrow">Nazorat testi</div>
            <h1>Test faqat universitet tarmog'idan ochiladi</h1>
            <p>Testni universitetdagi kompyuter sinfida, universitet internet tarmog'iga ulangan holda oching.</p>
        </div>
        <div class="k-body">
            <p class="k-note" style="margin-top:0">
                Agar siz universitetda bo'lsangiz va bu xabar chiqayotgan bo'lsa, o'qituvchiga yoki
                LMS administratoriga quyidagi manzilni ayting.
            </p>
            <div style="text-align:center"><span class="n-ip">IP: {{ $ip }}</span></div>
        </div>
    </div>
@endsection
