<x-guest-layout>
    <div class="mb-4">
        <div class="flex items-center mb-3">
            <svg class="w-6 h-6 text-blue-600 mr-2" fill="currentColor" viewBox="0 0 24 24">
                <path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/>
            </svg>
            <h2 class="text-lg font-bold text-gray-900">Telegram tasdiqlash</h2>
        </div>
        <p class="text-sm text-gray-600">
            Hisobingizga kirish uchun Telegramga ({{ $maskedContact }}) yuborilgan 6 xonali kodni kiriting.
        </p>
    </div>

    @if (session('success'))
        <div class="mb-4 p-3 bg-green-50 border border-green-200 rounded-lg text-sm text-green-700">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route($guard . '.verify-login.post') }}">
        @csrf

        <div class="mb-4">
            <x-input-label for="code" :value="__('Tasdiqlash kodi')" />
            <x-text-input id="code" class="block mt-1 w-full text-center text-2xl tracking-widest font-mono"
                          type="text"
                          name="code"
                          maxlength="6"
                          placeholder="000000"
                          required autofocus
                          autocomplete="one-time-code"
                          inputmode="numeric"
                          oninput="this.value = this.value.replace(/[^\d]/g, '')" />
        </div>

        <div class="flex items-center justify-between mt-4">
            <x-primary-button class="w-full justify-center py-3">
                {{ __('Tasdiqlash') }}
            </x-primary-button>
        </div>
    </form>

    <div class="mt-4 flex items-center justify-between">
        <form method="POST" action="{{ route($guard . '.verify-login.resend') }}">
            @csrf
            <button type="submit" class="text-sm text-blue-600 hover:text-blue-800 underline">
                Kodni qayta yuborish
            </button>
        </form>

        <a href="{{ route($guard . '.login') }}" class="text-sm text-gray-500 hover:text-gray-700 underline">
            Orqaga
        </a>
    </div>

    <div class="mt-4 p-3 bg-blue-50 border border-blue-100 rounded-lg">
        <p class="text-xs text-blue-700">
            <svg class="w-3.5 h-3.5 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            Tasdiqlash kodi 5 daqiqa amal qiladi. Agar kod kelmasa, "Kodni qayta yuborish" tugmasini bosing.
        </p>
    </div>
</x-guest-layout>
