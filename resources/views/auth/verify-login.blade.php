<x-guest-layout>
    <div class="login-card" style="width:100%; max-width:420px; padding:2.5rem;">
        <div style="text-align:center; margin-bottom:1.5rem;">
            <div style="width:48px; height:48px; background:linear-gradient(135deg, #2563eb, #3b82f6); border-radius:14px; display:flex; align-items:center; justify-content:center; margin:0 auto 1rem;">
                <svg style="width:24px;height:24px;" fill="#fff" viewBox="0 0 24 24">
                    <path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/>
                </svg>
            </div>
            <h2 style="font-size:20px; font-weight:700; color:#0f172a; margin:0;">Telegram tasdiqlash</h2>
            <p style="font-size:13px; color:#64748b; margin-top:6px;">
                Telegramga ({{ $maskedContact }}) yuborilgan 6 xonali kodni kiriting
            </p>
        </div>

        @if (session('success'))
            <div style="margin-bottom:1rem; padding:12px 16px; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:12px; font-size:13px; color:#15803d;">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div style="margin-bottom:1rem; padding:12px 16px; background:#fef2f2; border:1px solid #fecaca; border-radius:12px; font-size:13px; color:#dc2626;">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route($guard . '.verify-login.post') }}">
            @csrf
            <div class="login-input-group">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" /></svg>
                <input class="login-input" type="text" name="code" maxlength="6" placeholder="000000" required autofocus
                       autocomplete="one-time-code" inputmode="numeric"
                       oninput="this.value = this.value.replace(/[^\d]/g, '')"
                       style="text-align:center; font-size:24px; letter-spacing:8px; font-family:monospace; font-weight:700;" />
            </div>

            <button type="submit" class="btn-primary-login" style="margin-top:0.5rem;">Tasdiqlash</button>
        </form>

        <div style="display:flex; align-items:center; justify-content:space-between; margin-top:1.25rem;">
            <form method="POST" action="{{ route($guard . '.verify-login.resend') }}">
                @csrf
                <button type="submit" style="background:none; border:none; cursor:pointer; font-size:13px; color:#0d9488; font-weight:500; text-decoration:underline;">
                    Kodni qayta yuborish
                </button>
            </form>
            <a href="{{ route($guard . '.login') }}" style="font-size:13px; color:#64748b; text-decoration:underline;">
                Orqaga
            </a>
        </div>

        <div style="margin-top:1.25rem; padding:12px 16px; background:#f0f9ff; border:1px solid #bae6fd; border-radius:12px;">
            <p style="font-size:11px; color:#0369a1; line-height:1.5;">
                Tasdiqlash kodi 5 daqiqa amal qiladi. Agar kod kelmasa, "Kodni qayta yuborish" tugmasini bosing.
            </p>
        </div>
    </div>
</x-guest-layout>
