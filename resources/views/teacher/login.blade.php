<x-guest-layout>
    <!-- Tabs -->
    <div class="flex mb-6 border-b border-gray-200 dark:border-gray-700">
        <a href="{{ route('student.login') }}"
           class="flex-1 text-center py-2.5 text-sm font-medium border-b-2 border-transparent text-blue-400 dark:text-blue-500 hover:text-blue-500 dark:hover:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/10 rounded-t-lg transition">
            Talaba
        </a>
        <a href="{{ route('teacher.login') }}"
           class="flex-1 text-center py-2.5 text-sm font-semibold border-b-2 border-blue-600 text-blue-700 dark:text-blue-300 bg-blue-100 dark:bg-blue-900/30 rounded-t-lg">
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
