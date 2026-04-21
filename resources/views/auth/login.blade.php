<x-guest-layout>
    <!-- Session Status -->

    <x-auth-session-status class="mb-4" :status="session('status')" />
    <div class="mb-4 text-sm text-gray-600 dark:text-gray-400">
        {{ __("Examinerlar uchun Login") }}
    </div>
    <form method="POST" action="{{ route('admin.login.post') }}">
        @csrf

        <div>
            <x-input-label for="email" :value="__('Login')" />
            <x-text-input id="email" class="block mt-1 w-full" type="text" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
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
{{--            @if (Route::has('password.request'))--}}
{{--                <a class="underline text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:focus:ring-offset-gray-800" href="{{ route('password.request') }}">--}}
{{--                    {{ __('Forgot your password?') }}--}}
{{--                </a>--}}
{{--            @endif--}}

            <x-primary-button class="ms-3">
                {{ __('Kirish') }}
            </x-primary-button>
        </div>
    </form>

    <script>
        // CSRF tokenni avtomatik yangilash
        document.addEventListener('visibilitychange', function() {
            if (!document.hidden) {
                fetch('/refresh-csrf')
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        var tokenInput = document.querySelector('input[name="_token"]');
                        if (tokenInput && data.token) tokenInput.value = data.token;
                    })
                    .catch(function() {});
            }
        });

        (function() {
            var form = document.querySelector('form');
            var submitting = false;
            form.addEventListener('submit', function(e) {
                if (submitting) return;
                e.preventDefault();
                submitting = true;
                fetch('/refresh-csrf')
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        var tokenInput = form.querySelector('input[name="_token"]');
                        if (tokenInput && data.token) tokenInput.value = data.token;
                    })
                    .catch(function() {})
                    .finally(function() {
                        form.submit();
                    });
            });
        })();
    </script>
</x-guest-layout>
