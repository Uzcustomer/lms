<x-guest-layout>
    <div class="mb-4 text-sm text-gray-600">
        <div class="flex items-center mb-2">
            <svg class="w-5 h-5 text-orange-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
            </svg>
            <strong>Parolni o'zgartirish talab qilinadi</strong>
        </div>
        <p>Xavfsizlik maqsadida yangi parol o'rnating. Davom etish uchun yangi parolni kiriting.</p>
    </div>

    @if ($errors->any())
        <div class="mb-4 p-3 bg-red-100 border border-red-300 text-red-700 rounded text-sm">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('teacher.force-change-password.post') }}">
        @csrf

        <div>
            <x-input-label for="password" :value="__('Yangi parol')" />
            <x-text-input id="password" class="block mt-1 w-full" type="password" name="password" required autofocus autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="password_confirmation" :value="__('Yangi parolni tasdiqlang')" />
            <x-text-input id="password_confirmation" class="block mt-1 w-full" type="password" name="password_confirmation" required autocomplete="new-password" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <x-primary-button>
                {{ __('Parolni o\'zgartirish') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
