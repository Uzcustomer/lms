@extends('kiosk.fan-testi.layout')

@section('title', 'Test kompyuteri')

@section('content')
    <div class="k-card">
        <div class="k-head">
            <div class="k-eyebrow">Test kompyuterlari</div>
            <h1>Kompyuterni ro'yxatdan o'tkazish</h1>
            <p>Bu sahifadan foydalanish uchun shu kompyuterda LMS ga administrator sifatida kiring, so'ng sahifani qayta oching.</p>
        </div>
        <div class="k-body">
            <a class="k-btn k-btn-lg" href="{{ route('admin.login') }}">LMS ga kirish</a>
        </div>
    </div>
@endsection
