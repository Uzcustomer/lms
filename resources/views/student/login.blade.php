<x-guest-layout>
    <x-auth-session-status class="mb-4" :status="session('status')" style="color:#fff; text-align:center;" />

    {{-- ===== DESKTOP ===== --}}
    <div class="login-card desktop-split" style="display:grid; grid-template-columns:1fr 1fr; width:100%; max-width:880px;">

        {{-- CHAP: Talaba --}}
        <div class="split-left">
            <h2 class="form-heading">Talaba kirishi</h2>
            <p class="form-subtitle">Login va parol bilan tizimga kiring</p>

            <form method="POST" action="{{ route('student.login.post') }}">
                @csrf
                <div class="input-wrap">
                    <svg class="icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0" /></svg>
                    <input class="login-input" type="text" name="login" value="{{ old('login') }}" placeholder="Login" required autofocus autocomplete="login" />
                </div>
                @if($errors->has('login'))<div class="form-error">{{ $errors->first('login') }}</div>@endif

                <div class="input-wrap">
                    <svg class="icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" /></svg>
                    <input id="sp" class="login-input" type="password" name="password" placeholder="Parol" required autocomplete="current-password" style="padding-right:48px;" />
                    <button type="button" class="pwd-toggle" onclick="togglePwd('sp')">
                        <svg id="sp-eye" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                        <svg id="sp-eyeoff" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="display:none;"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12c1.292 4.338 5.31 7.5 10.066 7.5.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" /></svg>
                    </button>
                </div>
                @if($errors->has('password'))<div class="form-error">{{ $errors->first('password') }}</div>@endif

                <div class="remember-row">
                    <input id="rem_s" type="checkbox" name="remember">
                    <label for="rem_s">Eslab qolish</label>
                </div>

                <button type="submit" class="btn-login">Kirish</button>
            </form>

            <div class="divider"><span>yoki</span></div>

            <div style="display:flex; gap:10px;">
                <a href="{{ route('student.face-id.login') }}" class="btn-social">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="#6366f1"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 3.75H6A2.25 2.25 0 0 0 3.75 6v1.5M16.5 3.75H18A2.25 2.25 0 0 1 20.25 6v1.5m0 9V18A2.25 2.25 0 0 1 18 20.25h-1.5m-9 0H6A2.25 2.25 0 0 1 3.75 18v-1.5M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                    Face ID
                </a>
                <a href="{{ route('auth.hemis.redirect') }}" class="btn-social">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="#059669"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" /></svg>
                    HEMIS
                </a>
            </div>
        </div>

        {{-- O'NG: Xodim --}}
        <div class="split-right">
            <div style="text-align:center; margin-bottom:1.5rem; position:relative; z-index:2;">
                <div style="width:52px; height:52px; background:rgba(255,255,255,0.15); border-radius:16px; display:inline-flex; align-items:center; justify-content:center; margin-bottom:1rem; backdrop-filter:blur(8px);">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="#fff" style="width:26px;height:26px;"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 0 0 .75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 0 0-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0 1 12 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 0 1-.673-.38m0 0A2.18 2.18 0 0 1 3 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 0 1 3.413-.387m7.5 0V5.25A2.25 2.25 0 0 0 13.5 3h-3a2.25 2.25 0 0 0-2.25 2.25v.894m7.5 0a48.667 48.667 0 0 0-7.5 0" /></svg>
                </div>
                <h2 style="font-size:24px; font-weight:800; color:#fff; margin:0; letter-spacing:-0.5px;">Xodim kirishi</h2>
                <p style="font-size:13px; color:rgba(255,255,255,0.6); margin-top:6px;">O'qituvchi yoki xodim sifatida kiring</p>
            </div>

            <div class="right-form-card">
                <form method="POST" action="{{ route('teacher.login.post') }}">
                    @csrf
                    <div class="input-wrap">
                        <svg class="icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0" /></svg>
                        <input class="login-input" type="text" name="login" value="{{ old('login') }}" placeholder="Login" required autocomplete="login" />
                    </div>

                    <div class="input-wrap">
                        <svg class="icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" /></svg>
                        <input id="tp" class="login-input" type="password" name="password" placeholder="Parol" required autocomplete="current-password" style="padding-right:48px;" />
                        <button type="button" class="pwd-toggle" onclick="togglePwd('tp')">
                            <svg id="tp-eye" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                            <svg id="tp-eyeoff" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="display:none;"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12c1.292 4.338 5.31 7.5 10.066 7.5.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" /></svg>
                        </button>
                    </div>

                    <div class="remember-row">
                        <input id="rem_t" type="checkbox" name="remember">
                        <label for="rem_t">Eslab qolish</label>
                    </div>

                    <button type="submit" class="btn-login-outline">Kirish</button>
                </form>
            </div>
        </div>
    </div>

    {{-- ===== MOBILE ===== --}}
    <div class="mobile-card">
        <div class="m-tab-bar">
            <button class="active" onclick="mTab('s',this)">Talaba</button>
            <button onclick="mTab('t',this)">Xodim</button>
        </div>

        <div id="mp-s" class="m-panel active">
            <h2 class="form-heading" style="font-size:20px;">Talaba kirishi</h2>
            <p class="form-subtitle">Login va parol bilan kiring</p>

            <form method="POST" action="{{ route('student.login.post') }}">
                @csrf
                <div class="input-wrap">
                    <svg class="icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0" /></svg>
                    <input class="login-input" type="text" name="login" placeholder="Login" required autocomplete="login" />
                </div>
                <div class="input-wrap">
                    <svg class="icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" /></svg>
                    <input id="msp" class="login-input" type="password" name="password" placeholder="Parol" required autocomplete="current-password" style="padding-right:48px;" />
                    <button type="button" class="pwd-toggle" onclick="togglePwd('msp')">
                        <svg id="msp-eye" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                        <svg id="msp-eyeoff" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="display:none;"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12c1.292 4.338 5.31 7.5 10.066 7.5.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" /></svg>
                    </button>
                </div>
                <div class="remember-row"><input id="mrem_s" type="checkbox" name="remember"><label for="mrem_s">Eslab qolish</label></div>
                <button type="submit" class="btn-login">Kirish</button>
            </form>
            <div class="divider"><span>yoki</span></div>
            <div style="display:flex; gap:10px;">
                <a href="{{ route('student.face-id.login') }}" class="btn-social"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="#6366f1"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 3.75H6A2.25 2.25 0 0 0 3.75 6v1.5M16.5 3.75H18A2.25 2.25 0 0 1 20.25 6v1.5m0 9V18A2.25 2.25 0 0 1 18 20.25h-1.5m-9 0H6A2.25 2.25 0 0 1 3.75 18v-1.5M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>Face ID</a>
                <a href="{{ route('auth.hemis.redirect') }}" class="btn-social"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="#059669"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" /></svg>HEMIS</a>
            </div>
        </div>

        <div id="mp-t" class="m-panel">
            <h2 class="form-heading" style="font-size:20px;">Xodim kirishi</h2>
            <p class="form-subtitle">Login va parol bilan kiring</p>
            <form method="POST" action="{{ route('teacher.login.post') }}">
                @csrf
                <div class="input-wrap">
                    <svg class="icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0" /></svg>
                    <input class="login-input" type="text" name="login" placeholder="Login" required autocomplete="login" />
                </div>
                <div class="input-wrap">
                    <svg class="icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" /></svg>
                    <input id="mtp" class="login-input" type="password" name="password" placeholder="Parol" required autocomplete="current-password" style="padding-right:48px;" />
                    <button type="button" class="pwd-toggle" onclick="togglePwd('mtp')">
                        <svg id="mtp-eye" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                        <svg id="mtp-eyeoff" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="display:none;"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12c1.292 4.338 5.31 7.5 10.066 7.5.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" /></svg>
                    </button>
                </div>
                <div class="remember-row"><input id="mrem_t" type="checkbox" name="remember"><label for="mrem_t">Eslab qolish</label></div>
                <button type="submit" class="btn-login">Kirish</button>
            </form>
        </div>
    </div>

    <div class="copyright">&copy; {{ date('Y') }} TDTU Termiz filiali. Barcha huquqlar himoyalangan.</div>

    <script>
        function mTab(t, btn) {
            document.querySelectorAll('.m-tab-bar button').forEach(function(b) { b.classList.remove('active'); });
            document.querySelectorAll('.m-panel').forEach(function(p) { p.classList.remove('active'); });
            btn.classList.add('active');
            document.getElementById('mp-' + t).classList.add('active');
        }
    </script>
</x-guest-layout>
