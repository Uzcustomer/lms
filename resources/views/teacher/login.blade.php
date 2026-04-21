<x-guest-layout>
    <x-auth-session-status class="mb-4" :status="session('status')" style="color:#fff;" />

    {{-- ===== DESKTOP: Split Screen ===== --}}
    <div class="login-card desktop-split" style="display:grid; grid-template-columns:1fr 1fr; width:100%; max-width:880px;">

        {{-- Chap tomon: Talaba login --}}
        <div class="split-left">
            <div style="margin-bottom:1.75rem;">
                <h2 style="font-size:22px; font-weight:700; color:#0f172a; margin:0;">Talaba kirishi</h2>
                <p style="font-size:13px; color:#64748b; margin-top:6px;">Login va parol bilan tizimga kiring</p>
            </div>

            <form method="POST" action="{{ route('student.login.post') }}">
                @csrf
                <div class="login-input-group">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0" /></svg>
                    <input class="login-input" type="text" name="login" value="{{ old('login') }}" placeholder="Login" required autocomplete="login" />
                </div>

                <div class="login-input-group">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" /></svg>
                    <input id="student-password" class="login-input" type="password" name="password" placeholder="Parol" required autocomplete="current-password" style="padding-right:44px;" />
                    <button type="button" class="password-toggle" onclick="togglePassword('student-password')">
                        <svg id="student-password-eye" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                        <svg id="student-password-eye-off" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="display:none;"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12c1.292 4.338 5.31 7.5 10.066 7.5.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" /></svg>
                    </button>
                </div>

                <div class="remember-check">
                    <input id="remember_student" type="checkbox" name="remember">
                    <label for="remember_student">Eslab qolish</label>
                </div>

                <button type="submit" class="btn-primary-login">Kirish</button>
            </form>

            <div class="divider"><span>yoki</span></div>

            <div style="display:flex; gap:10px;">
                <a href="{{ route('student.face-id.login') }}" class="btn-social" style="flex:1;">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:18px;height:18px;color:#6366f1;"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 3.75H6A2.25 2.25 0 0 0 3.75 6v1.5M16.5 3.75H18A2.25 2.25 0 0 1 20.25 6v1.5m0 9V18A2.25 2.25 0 0 1 18 20.25h-1.5m-9 0H6A2.25 2.25 0 0 1 3.75 18v-1.5M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                    Face ID
                </a>
                <a href="{{ route('auth.hemis.redirect') }}" class="btn-social" style="flex:1;">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:18px;height:18px;color:#059669;"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" /></svg>
                    HEMIS
                </a>
            </div>
        </div>

        {{-- O'ng tomon: Xodim login --}}
        <div class="split-right">
            <div style="text-align:center; margin-bottom:2rem; position:relative; z-index:2;">
                <div style="width:56px; height:56px; background:rgba(255,255,255,0.15); border-radius:16px; display:flex; align-items:center; justify-content:center; margin:0 auto 1rem;">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="#fff" style="width:28px;height:28px;"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 0 0 .75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 0 0-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0 1 12 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 0 1-.673-.38m0 0A2.18 2.18 0 0 1 3 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 0 1 3.413-.387m7.5 0V5.25A2.25 2.25 0 0 0 13.5 3h-3a2.25 2.25 0 0 0-2.25 2.25v.894m7.5 0a48.667 48.667 0 0 0-7.5 0" /></svg>
                </div>
                <h2 style="font-size:22px; font-weight:700; color:#fff; margin:0;">Xodim kirishi</h2>
                <p style="font-size:13px; color:rgba(255,255,255,0.7); margin-top:6px;">O'qituvchi yoki xodim sifatida kiring</p>
            </div>

            <form method="POST" action="{{ route('teacher.login.post') }}" style="width:100%; max-width:320px; position:relative; z-index:2;">
                @csrf
                <div class="login-input-group">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0" /></svg>
                    <input class="login-input" type="text" name="login" value="{{ old('login') }}" placeholder="Login" required autofocus autocomplete="login" />
                </div>

                <div class="login-input-group">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" /></svg>
                    <input id="teacher-password" class="login-input" type="password" name="password" placeholder="Parol" required autocomplete="current-password" style="padding-right:44px;" />
                    <button type="button" class="password-toggle" onclick="togglePassword('teacher-password')">
                        <svg id="teacher-password-eye" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                        <svg id="teacher-password-eye-off" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="display:none;"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12c1.292 4.338 5.31 7.5 10.066 7.5.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" /></svg>
                    </button>
                </div>

                <div class="remember-check">
                    <input id="remember_teacher" type="checkbox" name="remember" style="accent-color:#fff;">
                    <label for="remember_teacher" style="color:rgba(255,255,255,0.8);">Eslab qolish</label>
                </div>

                <button type="submit" class="btn-outline-login" style="padding:14px; font-size:15px; font-weight:600; border-width:2px;">Kirish</button>
            </form>
        </div>
    </div>

    {{-- ===== MOBILE: Tab ko'rinishi ===== --}}
    <div class="login-card mobile-tabs" style="width:100%; max-width:420px;">
        <div style="display:flex; border-bottom:2px solid #e2e8f0;">
            <button onclick="switchMobileTab('student')" id="tab-student" style="flex:1; padding:14px 0; font-size:14px; font-weight:500; background:none; border:none; cursor:pointer; border-bottom:3px solid transparent; color:#64748b; margin-bottom:-2px; transition:all 0.2s;">
                Talaba
            </button>
            <button onclick="switchMobileTab('teacher')" id="tab-teacher" style="flex:1; padding:14px 0; font-size:14px; font-weight:600; background:none; border:none; cursor:pointer; border-bottom:3px solid #0d9488; color:#0d9488; margin-bottom:-2px; transition:all 0.2s;">
                Xodim
            </button>
        </div>

        {{-- Talaba tab --}}
        <div id="mobile-student" class="mobile-tab-content" style="display:none;">
            <div style="margin-bottom:1.5rem;">
                <h2 style="font-size:20px; font-weight:700; color:#0f172a; margin:0;">Talaba kirishi</h2>
                <p style="font-size:13px; color:#64748b; margin-top:4px;">Login va parol bilan kiring</p>
            </div>

            <form method="POST" action="{{ route('student.login.post') }}">
                @csrf
                <div class="login-input-group">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0" /></svg>
                    <input class="login-input" type="text" name="login" value="{{ old('login') }}" placeholder="Login" required autocomplete="login" />
                </div>

                <div class="login-input-group">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" /></svg>
                    <input id="m-student-password" class="login-input" type="password" name="password" placeholder="Parol" required autocomplete="current-password" style="padding-right:44px;" />
                    <button type="button" class="password-toggle" onclick="togglePassword('m-student-password')">
                        <svg id="m-student-password-eye" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                        <svg id="m-student-password-eye-off" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="display:none;"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12c1.292 4.338 5.31 7.5 10.066 7.5.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" /></svg>
                    </button>
                </div>

                <div class="remember-check">
                    <input id="m_remember_student2" type="checkbox" name="remember">
                    <label for="m_remember_student2">Eslab qolish</label>
                </div>

                <button type="submit" class="btn-primary-login">Kirish</button>
            </form>

            <div class="divider"><span>yoki</span></div>

            <div style="display:flex; gap:10px;">
                <a href="{{ route('student.face-id.login') }}" class="btn-social" style="flex:1;">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:18px;height:18px;color:#6366f1;"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 3.75H6A2.25 2.25 0 0 0 3.75 6v1.5M16.5 3.75H18A2.25 2.25 0 0 1 20.25 6v1.5m0 9V18A2.25 2.25 0 0 1 18 20.25h-1.5m-9 0H6A2.25 2.25 0 0 1 3.75 18v-1.5M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                    Face ID
                </a>
                <a href="{{ route('auth.hemis.redirect') }}" class="btn-social" style="flex:1;">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:18px;height:18px;color:#059669;"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" /></svg>
                    HEMIS
                </a>
            </div>
        </div>

        {{-- Xodim tab --}}
        <div id="mobile-teacher" class="mobile-tab-content">
            <div style="margin-bottom:1.5rem;">
                <h2 style="font-size:20px; font-weight:700; color:#0f172a; margin:0;">Xodim kirishi</h2>
                <p style="font-size:13px; color:#64748b; margin-top:4px;">Login va parol bilan kiring</p>
            </div>

            <form method="POST" action="{{ route('teacher.login.post') }}">
                @csrf
                <div class="login-input-group">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0" /></svg>
                    <input class="login-input" type="text" name="login" value="{{ old('login') }}" placeholder="Login" required autofocus autocomplete="login" />
                </div>

                <div class="login-input-group">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" /></svg>
                    <input id="m-teacher-password" class="login-input" type="password" name="password" placeholder="Parol" required autocomplete="current-password" style="padding-right:44px;" />
                    <button type="button" class="password-toggle" onclick="togglePassword('m-teacher-password')">
                        <svg id="m-teacher-password-eye" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                        <svg id="m-teacher-password-eye-off" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="display:none;"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12c1.292 4.338 5.31 7.5 10.066 7.5.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" /></svg>
                    </button>
                </div>

                <div class="remember-check">
                    <input id="m_remember_teacher2" type="checkbox" name="remember">
                    <label for="m_remember_teacher2">Eslab qolish</label>
                </div>

                <button type="submit" class="btn-primary-login">Kirish</button>
            </form>
        </div>
    </div>

    <p style="text-align:center; margin-top:1.5rem; font-size:11px; color:rgba(255,255,255,0.4);">
        &copy; {{ date('Y') }} TDTU Termiz filiali. Barcha huquqlar himoyalangan.
    </p>

    <script>
        function switchMobileTab(tab) {
            var studentPanel = document.getElementById('mobile-student');
            var teacherPanel = document.getElementById('mobile-teacher');
            var tabStudent = document.getElementById('tab-student');
            var tabTeacher = document.getElementById('tab-teacher');

            if (tab === 'student') {
                studentPanel.style.display = 'block';
                teacherPanel.style.display = 'none';
                tabStudent.style.borderBottomColor = '#0d9488';
                tabStudent.style.color = '#0d9488';
                tabStudent.style.fontWeight = '600';
                tabTeacher.style.borderBottomColor = 'transparent';
                tabTeacher.style.color = '#64748b';
                tabTeacher.style.fontWeight = '500';
            } else {
                studentPanel.style.display = 'none';
                teacherPanel.style.display = 'block';
                tabTeacher.style.borderBottomColor = '#0d9488';
                tabTeacher.style.color = '#0d9488';
                tabTeacher.style.fontWeight = '600';
                tabStudent.style.borderBottomColor = 'transparent';
                tabStudent.style.color = '#64748b';
                tabStudent.style.fontWeight = '500';
            }
        }
    </script>
</x-guest-layout>
