<x-guest-layout>
    <!-- Tabs -->
    <div style="display: flex; margin-bottom: 1.2rem; border-bottom: 2px solid #e5e7eb;">
        <a href="{{ route('student.login') }}"
           onclick="localStorage.setItem('lastLoginTab', 'student')"
           style="flex: 1; text-align: center; padding: 10px 0; font-size: 14px; font-weight: 500; text-decoration: none; border-bottom: 3px solid transparent; color: #1e40af; background-color: #dbeafe; border-radius: 8px 8px 0 0; margin-bottom: -2px;">
            Talaba
        </a>
        <a href="{{ route('teacher.login') }}"
           onclick="localStorage.setItem('lastLoginTab', 'teacher')"
           style="flex: 1; text-align: center; padding: 10px 0; font-size: 14px; font-weight: 600; text-decoration: none; border-bottom: 3px solid #1d4ed8; color: #ffffff; background-color: #1e40af; border-radius: 8px 8px 0 0; margin-bottom: -2px;">
            Xodim
        </a>
    </div>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('teacher.login.post') }}">
        @csrf

        <div>
            <x-input-label for="login" :value="__('Login')" />
            <x-text-input id="login" class="block mt-1 w-full" type="text" name="login" :value="old('login')" required autofocus autocomplete="login" />
            <x-input-error :messages="$errors->get('login')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="password" :value="__('Parol')" />

            <div style="position: relative;">
                <x-text-input id="password" class="block mt-1 w-full"
                              type="password"
                              name="password"
                              required autocomplete="current-password"
                              style="padding-right: 2.5rem;" />

                <button type="button" onclick="togglePassword()" style="position: absolute; right: 0.5rem; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; padding: 0.25rem; color: #6b7280;">
                    <svg id="eye-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1.25rem; height: 1.25rem;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                    </svg>
                    <svg id="eye-off-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1.25rem; height: 1.25rem; display: none;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12c1.292 4.338 5.31 7.5 10.066 7.5.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                    </svg>
                </button>
            </div>

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="block mt-4">
            <label for="remember_me" class="inline-flex items-center">
                <input id="remember_me" type="checkbox" class="rounded dark:bg-gray-900 border-gray-300 dark:border-gray-700 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:focus:ring-indigo-600 dark:focus:ring-offset-gray-800" name="remember">
                <span class="ms-2 text-sm text-gray-600 dark:text-gray-400">{{ __('Eslab qolish') }}</span>
            </label>
        </div>

        <div class="flex items-center justify-end mt-4">
            <x-primary-button class="ms-3">
                {{ __('Kirish') }}
            </x-primary-button>
        </div>
    </form>

    <script>
        localStorage.setItem('lastLoginTab', 'teacher');

        function togglePassword() {
            const input = document.getElementById('password');
            const eyeIcon = document.getElementById('eye-icon');
            const eyeOffIcon = document.getElementById('eye-off-icon');
            if (input.type === 'password') {
                input.type = 'text';
                eyeIcon.style.display = 'none';
                eyeOffIcon.style.display = 'block';
            } else {
                input.type = 'password';
                eyeIcon.style.display = 'block';
                eyeOffIcon.style.display = 'none';
            }
        }
    </script>
</x-guest-layout>
