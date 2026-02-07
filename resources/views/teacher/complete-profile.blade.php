<x-guest-layout>
    <div class="mb-5">
        <div class="flex items-center mb-3">
            <svg class="w-5 h-5 text-blue-600 mr-2" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
            </svg>
            <h2 class="text-lg font-bold text-gray-900">Profilni to'ldiring</h2>
        </div>
        <p class="text-sm text-gray-500">
            @if(!$teacher->phone)
                Davom etish uchun telefon raqamingizni kiriting.
            @else
                Telegram hisobingizni tasdiqlang.
                @if(!$teacher->isTelegramVerified())
                    <span class="font-medium text-orange-600">({{ $teacher->telegramDaysLeft() }} kun muhlat)</span>
                @endif
            @endif
        </p>
    </div>

    @if (session('success'))
        <div class="mb-4 p-3 bg-green-50 border border-green-200 rounded-lg text-sm text-green-700">
            {{ session('success') }}
        </div>
    @endif

    @if (session('error'))
        <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
            {{ session('error') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    {{-- Progress Steps --}}
    <div class="flex items-center mb-6">
        {{-- Step 1: Telefon --}}
        <div class="flex items-center">
            <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold
                {{ $teacher->phone ? 'bg-green-500 text-white' : 'bg-blue-600 text-white' }}">
                @if($teacher->phone)
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                @else
                    1
                @endif
            </div>
            <span class="ml-2 text-xs font-medium {{ $teacher->phone ? 'text-green-600' : 'text-blue-600' }}">Telefon</span>
        </div>

        <div class="flex-1 h-0.5 mx-3 {{ $teacher->phone ? 'bg-green-300' : 'bg-gray-200' }}"></div>

        {{-- Step 2: Telegram --}}
        <div class="flex items-center">
            <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold
                {{ $teacher->telegram_verified_at ? 'bg-green-500 text-white' : ($teacher->phone ? 'bg-blue-600 text-white' : 'bg-gray-300 text-gray-500') }}">
                @if($teacher->telegram_verified_at)
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                @else
                    2
                @endif
            </div>
            <span class="ml-2 text-xs font-medium {{ $teacher->telegram_verified_at ? 'text-green-600' : ($teacher->phone ? 'text-blue-600' : 'text-gray-400') }}">Telegram</span>
        </div>
    </div>

    {{-- Step 1: Telefon raqami --}}
    <div class="mb-5 p-4 rounded-lg border {{ $teacher->phone ? 'border-green-200 bg-green-50' : 'border-blue-200 bg-blue-50' }}">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-semibold {{ $teacher->phone ? 'text-green-800' : 'text-blue-800' }}">
                <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                </svg>
                Telefon raqami
            </h3>
            @if($teacher->phone)
                <span class="text-xs text-green-600 font-medium">{{ $teacher->phone }}</span>
            @endif
        </div>

        @if(!$teacher->phone)
            <form method="POST" action="{{ route('teacher.complete-profile.phone') }}">
                @csrf
                <div class="mb-3">
                    <label for="phone" class="block text-xs font-medium text-gray-600 mb-1">O'zbekiston telefon raqami</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500 text-sm font-medium pointer-events-none">
                            <img src="data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIyNCIgaGVpZ2h0PSIxNiIgdmlld0JveD0iMCAwIDMwIDIwIj48cmVjdCB3aWR0aD0iMzAiIGhlaWdodD0iNi42NyIgZmlsbD0iIzFhYjNlOCIvPjxyZWN0IHk9IjYuNjciIHdpZHRoPSIzMCIgaGVpZ2h0PSI2LjY3IiBmaWxsPSIjZmZmIi8+PHJlY3QgeT0iMTMuMzMiIHdpZHRoPSIzMCIgaGVpZ2h0PSI2LjY3IiBmaWxsPSIjMGE5YjQ2Ii8+PHJlY3QgeT0iNi4xNyIgd2lkdGg9IjMwIiBoZWlnaHQ9IjEiIGZpbGw9IiNjZTExMjYiLz48cmVjdCB5PSIxMi44MyIgd2lkdGg9IjMwIiBoZWlnaHQ9IjEiIGZpbGw9IiNjZTExMjYiLz48L3N2Zz4=" alt="UZ" class="w-5 h-3.5 mr-1.5 rounded-sm">
                        </span>
                        <input type="tel" name="phone" id="phone"
                               value="{{ old('phone', '+998') }}"
                               placeholder="+998901234567"
                               maxlength="13"
                               class="w-full pl-14 text-sm rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                               oninput="formatPhone(this)">
                    </div>
                    <p class="mt-1 text-xs text-gray-400">Format: +998XXXXXXXXX</p>
                </div>
                <button type="submit"
                        class="w-full inline-flex justify-center items-center px-3 py-2.5 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition">
                    Saqlash va davom etish
                </button>
            </form>
        @else
            <p class="text-xs text-green-600">Telefon raqami saqlangan.</p>
        @endif
    </div>

    {{-- Step 2: Telegram --}}
    <div class="mb-3 p-4 rounded-lg border {{ $teacher->telegram_verified_at ? 'border-green-200 bg-green-50' : ($teacher->phone ? 'border-blue-200 bg-blue-50' : 'border-gray-200 bg-gray-50') }}">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-semibold {{ $teacher->telegram_verified_at ? 'text-green-800' : ($teacher->phone ? 'text-blue-800' : 'text-gray-400') }}">
                <svg class="w-4 h-4 inline mr-1" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/>
                </svg>
                Telegram tasdiqlash
            </h3>
            @if($teacher->telegram_verified_at)
                <span class="text-xs text-green-600 font-medium">{{ $teacher->telegram_username }}</span>
            @endif
        </div>

        @if(!$teacher->phone)
            <p class="text-xs text-gray-400">Avval telefon raqamini kiriting.</p>
        @elseif($teacher->telegram_verified_at)
            <p class="text-xs text-green-600">Telegram hisobingiz tasdiqlangan.</p>
        @else
            {{-- Telegram username formasi --}}
            @if(!$teacher->telegram_verification_code)
                <form method="POST" action="{{ route('teacher.complete-profile.telegram') }}">
                    @csrf
                    <div class="mb-3">
                        <label for="telegram_username" class="block text-xs font-medium text-gray-600 mb-1">Telegram username</label>
                        <input type="text" name="telegram_username" id="telegram_username"
                               value="{{ old('telegram_username', $teacher->telegram_username ?? '@') }}"
                               placeholder="@username"
                               class="w-full text-sm rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <button type="submit"
                            class="w-full inline-flex justify-center items-center px-3 py-2.5 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition">
                        Davom etish
                    </button>
                </form>
            @else
                {{-- Tasdiqlash kodi ko'rsatish --}}
                <div class="space-y-3">
                    <div class="p-3 bg-white rounded-lg border border-blue-100">
                        <p class="text-xs text-gray-500 mb-2">Telegram username: <strong>{{ $teacher->telegram_username }}</strong></p>
                        <p class="text-xs text-gray-600 mb-1">Tasdiqlash kodingiz:</p>
                        <div class="flex items-center justify-center py-2 px-4 bg-gray-50 rounded-md border border-dashed border-gray-300">
                            <span class="text-2xl font-mono font-bold tracking-widest text-blue-700" id="verification-code">{{ $verificationCode }}</span>
                        </div>
                    </div>

                    <div class="text-xs text-gray-600">
                        <p class="font-medium mb-1">Tasdiqlash uchun:</p>
                        <ol class="list-decimal list-inside space-y-1 text-gray-500">
                            <li>Quyidagi tugmani bosing yoki botga o'ting</li>
                            <li>Botga tasdiqlash kodini yuboring: <strong>{{ $verificationCode }}</strong></li>
                            <li>Sahifa avtomatik yangilanadi</li>
                        </ol>
                    </div>

                    @if($botUsername)
                        <a href="https://t.me/{{ $botUsername }}?start={{ $verificationCode }}"
                           target="_blank"
                           class="w-full inline-flex justify-center items-center px-3 py-2.5 bg-[#0088cc] text-white text-sm font-medium rounded-lg hover:bg-[#007ab8] transition">
                            <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/>
                            </svg>
                            Telegram botni ochish
                        </a>
                    @endif

                    {{-- Username o'zgartirish --}}
                    <form method="POST" action="{{ route('teacher.complete-profile.telegram') }}">
                        @csrf
                        <div class="flex items-center gap-2">
                            <input type="text" name="telegram_username" value="{{ $teacher->telegram_username }}"
                                   placeholder="@username"
                                   class="flex-1 text-xs rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <button type="submit"
                                    class="px-3 py-2 text-xs font-medium text-gray-600 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                                O'zgartirish
                            </button>
                        </div>
                    </form>
                </div>

                {{-- Auto-refresh polling --}}
                <div id="verification-status" class="hidden mt-3 p-3 bg-green-50 border border-green-200 rounded-lg text-sm text-green-700 font-medium text-center">
                    Telegram tasdiqlandi! Sahifa yangilanmoqda...
                </div>
            @endif
        @endif
    </div>

    {{-- Keyinroq tugmasi — telegram muhlat o'tmagan bo'lsa --}}
    @if($teacher->phone && !$teacher->isTelegramVerified() && !$teacher->isTelegramDeadlinePassed())
        <div class="mt-4 text-center">
            <a href="{{ route('teacher.dashboard') }}"
               class="text-sm text-gray-500 hover:text-gray-700 underline">
                Keyinroq tasdiqlash ({{ $teacher->telegramDaysLeft() }} kun qoldi)
            </a>
        </div>
    @endif

    <script>
        function formatPhone(input) {
            let val = input.value.replace(/[^\d+]/g, '');
            if (!val.startsWith('+998')) {
                val = '+998';
            }
            if (val.length > 13) {
                val = val.substring(0, 13);
            }
            input.value = val;
        }

        @if($teacher->phone && $teacher->telegram_verification_code && !$teacher->telegram_verified_at)
        // Poll for Telegram verification
        (function checkVerification() {
            setInterval(function() {
                fetch('{{ route("teacher.verify-telegram.check") }}', {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(r => r.json())
                .then(data => {
                    if (data.verified) {
                        document.getElementById('verification-status').classList.remove('hidden');
                        setTimeout(() => window.location.reload(), 1500);
                    }
                })
                .catch(() => {});
            }, 3000);
        })();
        @endif
    </script>
</x-guest-layout>
