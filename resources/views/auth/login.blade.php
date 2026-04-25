<x-guest-layout>
    <x-auth-session-status class="mb-4" :status="session('status')" style="color:#fff;" />

    <div class="login-card" style="width:100%; max-width:420px; padding:2.5rem;">
        <div style="text-align:center; margin-bottom:2rem;">
            <div style="width:48px; height:48px; background:linear-gradient(135deg, #0f766e, #0d9488); border-radius:14px; display:flex; align-items:center; justify-content:center; margin:0 auto 1rem;">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="#fff" style="width:24px;height:24px;"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" /></svg>
            </div>
            <h2 style="font-size:20px; font-weight:700; color:#0f172a; margin:0;">Administrator kirishi</h2>
            <p style="font-size:13px; color:#64748b; margin-top:6px;">Examiner va admin paneli uchun</p>
        </div>

        <form method="POST" action="{{ route('admin.login.post') }}">
            @csrf
            <div class="login-input-group">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0" /></svg>
                <input class="login-input" type="text" name="email" value="{{ old('email') }}" placeholder="Login" required autofocus autocomplete="username" />
            </div>
            @if($errors->has('email'))
                <div class="form-error" style="margin-top:-0.75rem; margin-bottom:0.75rem;">{{ $errors->first('email') }}</div>
            @endif

            <div class="login-input-group">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" /></svg>
                <input id="admin-password" class="login-input" type="password" name="password" placeholder="Parol" required autocomplete="current-password" style="padding-right:44px;" />
                <button type="button" class="password-toggle" onclick="togglePassword('admin-password')">
                    <svg id="admin-password-eye" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                    <svg id="admin-password-eye-off" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="display:none;"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12c1.292 4.338 5.31 7.5 10.066 7.5.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" /></svg>
                </button>
            </div>
            @if($errors->has('password'))
                <div class="form-error" style="margin-top:-0.75rem; margin-bottom:0.75rem;">{{ $errors->first('password') }}</div>
            @endif

            <div class="remember-check">
                <input id="remember_admin" type="checkbox" name="remember">
                <label for="remember_admin">Eslab qolish</label>
            </div>

            <button type="submit" class="btn-primary-login">Kirish</button>
        </form>
    </div>

    <p style="text-align:center; margin-top:1.5rem; font-size:11px; color:rgba(255,255,255,0.4);">
        &copy; {{ date('Y') }} TDTU Termiz filiali. Barcha huquqlar himoyalangan.
    </p>
</x-guest-layout>
