<x-guest-layout>
    <!-- Tabs -->
    <div style="display: flex; margin-bottom: 1.2rem; border-bottom: 2px solid #e5e7eb;">
        <a href="{{ route('student.login') }}"
           style="flex: 1; text-align: center; padding: 10px 0; font-size: 14px; font-weight: 500; text-decoration: none; border-bottom: 3px solid transparent; color: #93c5fd; border-radius: 8px 8px 0 0; margin-bottom: -2px;">
            Talaba
        </a>
        <a href="{{ route('teacher.login') }}"
           style="flex: 1; text-align: center; padding: 10px 0; font-size: 14px; font-weight: 600; text-decoration: none; border-bottom: 3px solid #1d4ed8; color: #1e40af; background-color: #dbeafe; border-radius: 8px 8px 0 0; margin-bottom: -2px;">
            Xodim
        </a>
    </div>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('teacher.login.post') }}">
        @csrf

        <div>
            <x-input-label for="login" :value="__('Login')" />
            <x-text-input id="login" class="block mt-1 w-full" type="text" name="login" :value="old('login')" required autofocus autocomplete="login" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="password" :value="__('Parol')" />

            <x-text-input id="password" class="block mt-1 w-full"
                          type="password"
                          name="password"
                          required autocomplete="current-password" />

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
</x-guest-layout>
