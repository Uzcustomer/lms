<x-guest-layout>
    <div class="mb-4 text-sm text-gray-600">
        Xavfsizlik uchun vaqtinchalik parol bilan kirgansiz. Davom etishdan oldin yangi parol o‘rnating.
    </div>

    <form method="POST" action="{{ route('student.password.update') }}">
        @csrf
        @method('PUT')

        <div>
            <x-input-label for="current_password" :value="__('Vaqtinchalik parol')" />
            <x-text-input id="current_password" class="block mt-1 w-full" type="password" name="current_password" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('current_password')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="password" :value="__('Yangi parol')" />
            <x-text-input id="password" class="block mt-1 w-full" type="password" name="password" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="password_confirmation" :value="__('Yangi parolni tasdiqlang')" />
            <x-text-input id="password_confirmation" class="block mt-1 w-full" type="password" name="password_confirmation" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <x-primary-button>
                {{ __('Parolni yangilash') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
