<x-guest-layout>
    <x-auth-session-status class="mb-4" :status="session('status')" style="color:#fff; text-align:center;" />

    {{-- ===== DESKTOP: Swapping card (starts swapped — Xodim active) ===== --}}
    <div class="swap-container swapped" id="swapContainer">
        <div class="swap-box"></div>

        <div class="swap-info">
            <div class="swap-info-item">
                <div class="info-inner" style="padding-right:20px;">
                    <div class="swap-info-icon" style="width:56px;height:56px;background:rgba(255,255,255,0.12);border-radius:16px;display:flex;align-items:center;justify-content:center;margin-bottom:1.25rem;">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="#fff" style="width:28px;height:28px;"><path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.438 60.438 0 0 0-.491 6.347A48.62 48.62 0 0 1 12 20.904a48.62 48.62 0 0 1 8.232-4.41 60.46 60.46 0 0 0-.491-6.347m-15.482 0a50.636 50.636 0 0 0-2.658-.813A59.906 59.906 0 0 1 12 3.493a59.903 59.903 0 0 1 10.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0 1 12 13.489a50.702 50.702 0 0 1 7.74-3.342" /></svg>
                    </div>
                    <h3>Talabamisiz?</h3>
                    <p>Talaba sifatida tizimga kiring</p>
                    <div class="swap-info-btn" onclick="document.getElementById('swapContainer').classList.remove('swapped')">Talaba kirishi</div>
                </div>
            </div>
            <div class="swap-info-item">
                <div class="info-inner" style="padding-left:40px;">
                    <!-- empty: xodim card covers this -->
                </div>
            </div>
        </div>

        <div class="swap-card">
            {{-- Talaba form --}}
            <div class="swap-form form-talaba">
                <h2>Talaba kirishi</h2>
                <div class="form-subtitle">Login va parol bilan tizimga kiring</div>

                <form method="POST" action="{{ route('student.login.post') }}" style="width:100%;">
                    @csrf
                    <div class="s-input-group">
                        <svg class="input-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0" /></svg>
                        <input class="s-input" type="text" name="login" value="{{ old('login') }}" placeholder="Login" required autocomplete="login" />
                    </div>
                    <div class="s-input-group">
                        <svg class="input-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" /></svg>
                        <input id="s-pwd" class="s-input" type="password" name="password" placeholder="Parol" required autocomplete="current-password" style="padding-right:44px;" />
                        <button type="button" class="s-pwd-toggle" onclick="togglePwd('s-pwd')">
                            <svg id="s-pwd-eye" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                            <svg id="s-pwd-eye-off" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="display:none;"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12c1.292 4.338 5.31 7.5 10.066 7.5.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" /></svg>
                        </button>
                    </div>
                    <div class="s-remember">
                        <input id="rem_s" type="checkbox" name="remember">
                        <label for="rem_s">Eslab qolish</label>
                    </div>
                    <button type="submit" class="s-btn">Kirish</button>
                </form>

                <div class="s-divider"><span>yoki</span></div>
                <div class="s-social-row">
                    <a href="{{ route('student.face-id.login') }}" class="s-social-btn">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="#6366f1" style="width:18px;height:18px;"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 3.75H6A2.25 2.25 0 0 0 3.75 6v1.5M16.5 3.75H18A2.25 2.25 0 0 1 20.25 6v1.5m0 9V18A2.25 2.25 0 0 1 18 20.25h-1.5m-9 0H6A2.25 2.25 0 0 1 3.75 18v-1.5M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                        Face ID
                    </a>
                    <a href="{{ route('auth.hemis.redirect') }}" class="s-social-btn">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="#059669" style="width:18px;height:18px;"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" /></svg>
                        HEMIS
                    </a>
                </div>
            </div>

            {{-- Xodim form --}}
            <div class="swap-form form-xodim">
                <h2>Xodim kirishi</h2>
                <div class="form-subtitle">O'qituvchi yoki xodim sifatida kiring</div>

                <form method="POST" action="{{ route('teacher.login.post') }}" style="width:100%;">
                    @csrf
                    <div class="s-input-group">
                        <svg class="input-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0" /></svg>
                        <input class="s-input" type="text" name="login" value="{{ old('login') }}" placeholder="Login" required autofocus autocomplete="login" />
                    </div>
                    <div class="s-input-group">
                        <svg class="input-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" /></svg>
                        <input id="t-pwd" class="s-input" type="password" name="password" placeholder="Parol" required autocomplete="current-password" style="padding-right:44px;" />
                        <button type="button" class="s-pwd-toggle" onclick="togglePwd('t-pwd')">
                            <svg id="t-pwd-eye" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                            <svg id="t-pwd-eye-off" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="display:none;"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12c1.292 4.338 5.31 7.5 10.066 7.5.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" /></svg>
                        </button>
                    </div>
                    <div class="s-remember">
                        <input id="rem_t" type="checkbox" name="remember">
                        <label for="rem_t">Eslab qolish</label>
                    </div>
                    <button type="submit" class="s-btn">Kirish</button>
                </form>
            </div>
        </div>
    </div>

    {{-- ===== MOBILE ===== --}}
    <div class="mobile-login">
        <div class="mobile-tab-bar">
            <button onclick="mTab('student', this)">Talaba</button>
            <button class="active" onclick="mTab('teacher', this)">Xodim</button>
        </div>

        <div id="m-student" class="mobile-panel">
            <h2 style="font-size:20px; font-weight:700; color:#0f172a; margin-bottom:4px;">Talaba kirishi</h2>
            <p style="font-size:13px; color:#64748b; margin-bottom:1.5rem;">Login va parol bilan kiring</p>
            <form method="POST" action="{{ route('student.login.post') }}">
                @csrf
                <div class="s-input-group">
                    <svg class="input-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0" /></svg>
                    <input class="s-input" type="text" name="login" placeholder="Login" required autocomplete="login" />
                </div>
                <div class="s-input-group">
                    <svg class="input-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" /></svg>
                    <input id="ms-pwd" class="s-input" type="password" name="password" placeholder="Parol" required autocomplete="current-password" style="padding-right:44px;" />
                    <button type="button" class="s-pwd-toggle" onclick="togglePwd('ms-pwd')">
                        <svg id="ms-pwd-eye" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                        <svg id="ms-pwd-eye-off" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="display:none;"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12c1.292 4.338 5.31 7.5 10.066 7.5.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" /></svg>
                    </button>
                </div>
                <div class="s-remember">
                    <input id="mrem_s2" type="checkbox" name="remember">
                    <label for="mrem_s2">Eslab qolish</label>
                </div>
                <button type="submit" class="s-btn">Kirish</button>
            </form>
            <div class="s-divider"><span>yoki</span></div>
            <div class="s-social-row">
                <a href="{{ route('student.face-id.login') }}" class="s-social-btn"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="#6366f1" style="width:18px;height:18px;"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 3.75H6A2.25 2.25 0 0 0 3.75 6v1.5M16.5 3.75H18A2.25 2.25 0 0 1 20.25 6v1.5m0 9V18A2.25 2.25 0 0 1 18 20.25h-1.5m-9 0H6A2.25 2.25 0 0 1 3.75 18v-1.5M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>Face ID</a>
                <a href="{{ route('auth.hemis.redirect') }}" class="s-social-btn"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="#059669" style="width:18px;height:18px;"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" /></svg>HEMIS</a>
            </div>
        </div>

        <div id="m-teacher" class="mobile-panel active">
            <h2 style="font-size:20px; font-weight:700; color:#0f172a; margin-bottom:4px;">Xodim kirishi</h2>
            <p style="font-size:13px; color:#64748b; margin-bottom:1.5rem;">Login va parol bilan kiring</p>
            <form method="POST" action="{{ route('teacher.login.post') }}">
                @csrf
                <div class="s-input-group">
                    <svg class="input-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0" /></svg>
                    <input class="s-input" type="text" name="login" placeholder="Login" required autofocus autocomplete="login" />
                </div>
                <div class="s-input-group">
                    <svg class="input-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" /></svg>
                    <input id="mt-pwd" class="s-input" type="password" name="password" placeholder="Parol" required autocomplete="current-password" style="padding-right:44px;" />
                    <button type="button" class="s-pwd-toggle" onclick="togglePwd('mt-pwd')">
                        <svg id="mt-pwd-eye" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                        <svg id="mt-pwd-eye-off" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="display:none;"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12c1.292 4.338 5.31 7.5 10.066 7.5.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" /></svg>
                    </button>
                </div>
                <div class="s-remember">
                    <input id="mrem_t2" type="checkbox" name="remember">
                    <label for="mrem_t2">Eslab qolish</label>
                </div>
                <button type="submit" class="s-btn">Kirish</button>
            </form>
        </div>
    </div>

    <div class="footer-text">&copy; {{ date('Y') }} TDTU Termiz filiali. Barcha huquqlar himoyalangan.</div>

    <script>
        function mTab(tab, btn) {
            document.querySelectorAll('.mobile-tab-bar button').forEach(function(b) { b.classList.remove('active'); });
            document.querySelectorAll('.mobile-panel').forEach(function(p) { p.classList.remove('active'); });
            btn.classList.add('active');
            document.getElementById('m-' + tab).classList.add('active');
        }
    </script>
</x-guest-layout>
