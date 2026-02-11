<x-guest-layout>
    {{-- Logout tugmasi --}}
    <div class="flex items-center justify-between mb-5">
        <div>
            <div class="flex items-center mb-1">
                <svg class="w-5 h-5 text-blue-600 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                </svg>
                <h2 class="text-lg font-bold text-gray-900">Profilni to'ldiring</h2>
            </div>
            <p class="text-sm text-gray-500">
                @if(!$student->phone)
                    Davom etish uchun telefon raqamingizni kiriting.
                @else
                    Telegram hisobingizni tasdiqlang.
                    @if(!$student->isTelegramVerified())
                        <span class="font-medium text-orange-600">({{ $student->telegramDaysLeft() }} kun muhlat)</span>
                    @endif
                @endif
            </p>
        </div>
        <form method="POST" action="{{ route('student.logout') }}">
            @csrf
            <button type="submit" class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-gray-500 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 hover:text-red-600 transition" title="Chiqish">
                <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                </svg>
                Chiqish
            </button>
        </form>
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
                {{ $student->phone ? 'bg-green-500 text-white' : 'bg-blue-600 text-white' }}">
                @if($student->phone)
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                @else
                    1
                @endif
            </div>
            <span class="ml-2 text-xs font-medium {{ $student->phone ? 'text-green-600' : 'text-blue-600' }}">Telefon</span>
        </div>

        <div class="flex-1 h-0.5 mx-3 {{ $student->phone ? 'bg-green-300' : 'bg-gray-200' }}"></div>

        {{-- Step 2: Telegram --}}
        <div class="flex items-center">
            <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold
                {{ $student->telegram_verified_at ? 'bg-green-500 text-white' : ($student->phone ? 'bg-blue-600 text-white' : 'bg-gray-300 text-gray-500') }}">
                @if($student->telegram_verified_at)
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                @else
                    2
                @endif
            </div>
            <span class="ml-2 text-xs font-medium {{ $student->telegram_verified_at ? 'text-green-600' : ($student->phone ? 'text-blue-600' : 'text-gray-400') }}">Telegram</span>
        </div>
    </div>

    {{-- Step 1: Telefon raqami --}}
    <div class="mb-5 p-4 rounded-lg border {{ $student->phone ? 'border-green-200 bg-green-50' : 'border-blue-200 bg-blue-50' }}">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-semibold {{ $student->phone ? 'text-green-800' : 'text-blue-800' }}">
                <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                </svg>
                Telefon raqami
            </h3>
            @if($student->phone)
                <span class="text-xs text-green-600 font-medium">{{ $student->phone }}</span>
            @endif
        </div>

        @if(!$student->phone)
            <form method="POST" action="{{ route('student.complete-profile.phone') }}"
                  x-data="phoneInput()" x-init="init()">
                @csrf
                <div class="mb-3">
                    <label class="block text-xs font-medium text-gray-600 mb-1.5">Telefon raqami</label>
                    <div class="relative flex items-stretch rounded-lg border border-gray-300 bg-white shadow-sm focus-within:border-blue-500 focus-within:ring-1 focus-within:ring-blue-500 overflow-visible">
                        {{-- Country selector button --}}
                        <button type="button" @click="open = !open"
                                class="flex items-center gap-1.5 px-2.5 bg-gray-50 border-r border-gray-300 hover:bg-gray-100 transition rounded-l-lg shrink-0"
                                @click.away="open = false">
                            <span class="text-base" x-text="selectedFlag"></span>
                            <span class="text-xs font-semibold text-gray-700" x-text="'+' + selectedCode"></span>
                            <svg class="w-3 h-3 text-gray-400 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>

                        {{-- Phone input --}}
                        <input type="tel" x-model="phoneNumber" x-ref="phoneInput"
                               placeholder="90 123 45 67"
                               maxlength="15"
                               class="flex-1 min-w-0 text-sm border-0 focus:ring-0 py-2.5 px-3"
                               oninput="this.value = this.value.replace(/[^\d]/g, '')">

                        {{-- Country dropdown --}}
                        <div x-show="open" x-transition:enter="transition ease-out duration-150"
                             x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                             x-transition:leave="transition ease-in duration-100"
                             x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
                             @click.away="open = false"
                             class="absolute left-0 top-full z-50 mt-1.5 w-full bg-white border border-gray-200 rounded-lg shadow-xl overflow-hidden"
                             style="display: none;">
                            {{-- Search --}}
                            <div class="p-2 border-b border-gray-100 bg-gray-50">
                                <div class="relative">
                                    <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                    </svg>
                                    <input type="text" x-model="search" x-ref="searchInput"
                                           @keydown.escape="open = false"
                                           placeholder="Mamlakat qidirish..."
                                           class="w-full text-sm rounded-md border-gray-300 pl-8 py-1.5 focus:border-blue-500 focus:ring-blue-500">
                                </div>
                            </div>
                            {{-- List --}}
                            <div class="overflow-y-auto max-h-48 overscroll-contain">
                                <template x-for="c in filteredCountries" :key="c.name + c.code">
                                    <button type="button"
                                            @click="selectCountry(c)"
                                            class="w-full flex items-center justify-between px-3 py-2 text-sm hover:bg-blue-50 transition-colors"
                                            :class="selectedCode === c.code && selectedName === c.name ? 'bg-blue-50 text-blue-700' : 'text-gray-700'">
                                        <span class="flex items-center gap-2">
                                            <span class="text-base" x-text="c.flag"></span>
                                            <span x-text="c.name" class="truncate"></span>
                                        </span>
                                        <span class="text-xs text-gray-400 font-medium ml-2 shrink-0" x-text="'+' + c.code"></span>
                                    </button>
                                </template>
                                <div x-show="filteredCountries.length === 0" class="px-3 py-4 text-xs text-gray-400 text-center">
                                    Hech narsa topilmadi
                                </div>
                            </div>
                        </div>
                    </div>
                    <input type="hidden" name="phone" :value="'+' + selectedCode + phoneNumber">
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
    <div class="mb-3 p-4 rounded-lg border {{ $student->telegram_verified_at ? 'border-green-200 bg-green-50' : ($student->phone ? 'border-blue-200 bg-blue-50' : 'border-gray-200 bg-gray-50') }}">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-semibold {{ $student->telegram_verified_at ? 'text-green-800' : ($student->phone ? 'text-blue-800' : 'text-gray-400') }}">
                <svg class="w-4 h-4 inline mr-1" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/>
                </svg>
                Telegram tasdiqlash
            </h3>
            @if($student->telegram_verified_at)
                <span class="text-xs text-green-600 font-medium">{{ $student->telegram_username }}</span>
            @endif
        </div>

        @if(!$student->phone)
            <p class="text-xs text-gray-400">Avval telefon raqamini kiriting.</p>
        @elseif($student->telegram_verified_at)
            <p class="text-xs text-green-600">Telegram hisobingiz tasdiqlangan.</p>
        @else
            {{-- Telegram username formasi --}}
            @if(!$student->telegram_verification_code)
                <form method="POST" action="{{ route('student.complete-profile.telegram') }}">
                    @csrf
                    <div class="mb-3">
                        <label for="telegram_username" class="block text-xs font-medium text-gray-600 mb-1">Telegram username</label>
                        <input type="text" name="telegram_username" id="telegram_username"
                               value="{{ old('telegram_username', $student->telegram_username ?? '@') }}"
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
                        <p class="text-xs text-gray-500 mb-2">Telegram username: <strong>{{ $student->telegram_username }}</strong></p>
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
                    <form method="POST" action="{{ route('student.complete-profile.telegram') }}">
                        @csrf
                        <div class="flex items-center gap-2">
                            <input type="text" name="telegram_username" value="{{ $student->telegram_username }}"
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
    @if($student->phone && !$student->isTelegramVerified() && !$student->isTelegramDeadlinePassed())
        <div class="mt-4 text-center">
            <a href="{{ route('student.dashboard') }}"
               class="text-sm text-gray-500 hover:text-gray-700 underline">
                Keyinroq tasdiqlash ({{ $student->telegramDaysLeft() }} kun qoldi)
            </a>
        </div>
    @endif

    <script>
        function phoneInput() {
            return {
                open: false,
                search: '',
                phoneNumber: '',
                selectedCode: '998',
                selectedName: 'Uzbekistan',
                selectedFlag: '\uD83C\uDDFA\uD83C\uDDFF',
                countries: [
                    {name:'Afghanistan',code:'93',flag:'\uD83C\uDDE6\uD83C\uDDEB'},
                    {name:'Albania',code:'355',flag:'\uD83C\uDDE6\uD83C\uDDF1'},
                    {name:'Algeria',code:'213',flag:'\uD83C\uDDE9\uD83C\uDDFF'},
                    {name:'Argentina',code:'54',flag:'\uD83C\uDDE6\uD83C\uDDF7'},
                    {name:'Armenia',code:'374',flag:'\uD83C\uDDE6\uD83C\uDDF2'},
                    {name:'Australia',code:'61',flag:'\uD83C\uDDE6\uD83C\uDDFA'},
                    {name:'Austria',code:'43',flag:'\uD83C\uDDE6\uD83C\uDDF9'},
                    {name:'Azerbaijan',code:'994',flag:'\uD83C\uDDE6\uD83C\uDDFF'},
                    {name:'Bahrain',code:'973',flag:'\uD83C\uDDE7\uD83C\uDDED'},
                    {name:'Bangladesh',code:'880',flag:'\uD83C\uDDE7\uD83C\uDDE9'},
                    {name:'Belarus',code:'375',flag:'\uD83C\uDDE7\uD83C\uDDFE'},
                    {name:'Belgium',code:'32',flag:'\uD83C\uDDE7\uD83C\uDDEA'},
                    {name:'Brazil',code:'55',flag:'\uD83C\uDDE7\uD83C\uDDF7'},
                    {name:'Bulgaria',code:'359',flag:'\uD83C\uDDE7\uD83C\uDDEC'},
                    {name:'Canada',code:'1',flag:'\uD83C\uDDE8\uD83C\uDDE6'},
                    {name:'Chile',code:'56',flag:'\uD83C\uDDE8\uD83C\uDDF1'},
                    {name:'China',code:'86',flag:'\uD83C\uDDE8\uD83C\uDDF3'},
                    {name:'Colombia',code:'57',flag:'\uD83C\uDDE8\uD83C\uDDF4'},
                    {name:'Croatia',code:'385',flag:'\uD83C\uDDED\uD83C\uDDF7'},
                    {name:'Cuba',code:'53',flag:'\uD83C\uDDE8\uD83C\uDDFA'},
                    {name:'Czech Republic',code:'420',flag:'\uD83C\uDDE8\uD83C\uDDFF'},
                    {name:'Denmark',code:'45',flag:'\uD83C\uDDE9\uD83C\uDDF0'},
                    {name:'Egypt',code:'20',flag:'\uD83C\uDDEA\uD83C\uDDEC'},
                    {name:'Estonia',code:'372',flag:'\uD83C\uDDEA\uD83C\uDDEA'},
                    {name:'Finland',code:'358',flag:'\uD83C\uDDEB\uD83C\uDDEE'},
                    {name:'France',code:'33',flag:'\uD83C\uDDEB\uD83C\uDDF7'},
                    {name:'Georgia',code:'995',flag:'\uD83C\uDDEC\uD83C\uDDEA'},
                    {name:'Germany',code:'49',flag:'\uD83C\uDDE9\uD83C\uDDEA'},
                    {name:'Greece',code:'30',flag:'\uD83C\uDDEC\uD83C\uDDF7'},
                    {name:'Hungary',code:'36',flag:'\uD83C\uDDED\uD83C\uDDFA'},
                    {name:'India',code:'91',flag:'\uD83C\uDDEE\uD83C\uDDF3'},
                    {name:'Indonesia',code:'62',flag:'\uD83C\uDDEE\uD83C\uDDE9'},
                    {name:'Iran',code:'98',flag:'\uD83C\uDDEE\uD83C\uDDF7'},
                    {name:'Iraq',code:'964',flag:'\uD83C\uDDEE\uD83C\uDDF6'},
                    {name:'Ireland',code:'353',flag:'\uD83C\uDDEE\uD83C\uDDEA'},
                    {name:'Israel',code:'972',flag:'\uD83C\uDDEE\uD83C\uDDF1'},
                    {name:'Italy',code:'39',flag:'\uD83C\uDDEE\uD83C\uDDF9'},
                    {name:'Japan',code:'81',flag:'\uD83C\uDDEF\uD83C\uDDF5'},
                    {name:'Jordan',code:'962',flag:'\uD83C\uDDEF\uD83C\uDDF4'},
                    {name:'Kazakhstan',code:'7',flag:'\uD83C\uDDF0\uD83C\uDDFF'},
                    {name:'Kuwait',code:'965',flag:'\uD83C\uDDF0\uD83C\uDDFC'},
                    {name:'Kyrgyzstan',code:'996',flag:'\uD83C\uDDF0\uD83C\uDDEC'},
                    {name:'Latvia',code:'371',flag:'\uD83C\uDDF1\uD83C\uDDFB'},
                    {name:'Lebanon',code:'961',flag:'\uD83C\uDDF1\uD83C\uDDE7'},
                    {name:'Lithuania',code:'370',flag:'\uD83C\uDDF1\uD83C\uDDF9'},
                    {name:'Malaysia',code:'60',flag:'\uD83C\uDDF2\uD83C\uDDFE'},
                    {name:'Mexico',code:'52',flag:'\uD83C\uDDF2\uD83C\uDDFD'},
                    {name:'Moldova',code:'373',flag:'\uD83C\uDDF2\uD83C\uDDE9'},
                    {name:'Mongolia',code:'976',flag:'\uD83C\uDDF2\uD83C\uDDF3'},
                    {name:'Morocco',code:'212',flag:'\uD83C\uDDF2\uD83C\uDDE6'},
                    {name:'Netherlands',code:'31',flag:'\uD83C\uDDF3\uD83C\uDDF1'},
                    {name:'New Zealand',code:'64',flag:'\uD83C\uDDF3\uD83C\uDDFF'},
                    {name:'Nigeria',code:'234',flag:'\uD83C\uDDF3\uD83C\uDDEC'},
                    {name:'Norway',code:'47',flag:'\uD83C\uDDF3\uD83C\uDDF4'},
                    {name:'Oman',code:'968',flag:'\uD83C\uDDF4\uD83C\uDDF2'},
                    {name:'Pakistan',code:'92',flag:'\uD83C\uDDF5\uD83C\uDDF0'},
                    {name:'Philippines',code:'63',flag:'\uD83C\uDDF5\uD83C\uDDED'},
                    {name:'Poland',code:'48',flag:'\uD83C\uDDF5\uD83C\uDDF1'},
                    {name:'Portugal',code:'351',flag:'\uD83C\uDDF5\uD83C\uDDF9'},
                    {name:'Qatar',code:'974',flag:'\uD83C\uDDF6\uD83C\uDDE6'},
                    {name:'Romania',code:'40',flag:'\uD83C\uDDF7\uD83C\uDDF4'},
                    {name:'Russia',code:'7',flag:'\uD83C\uDDF7\uD83C\uDDFA'},
                    {name:'Saudi Arabia',code:'966',flag:'\uD83C\uDDF8\uD83C\uDDE6'},
                    {name:'Serbia',code:'381',flag:'\uD83C\uDDF7\uD83C\uDDF8'},
                    {name:'Singapore',code:'65',flag:'\uD83C\uDDF8\uD83C\uDDEC'},
                    {name:'Slovakia',code:'421',flag:'\uD83C\uDDF8\uD83C\uDDF0'},
                    {name:'South Korea',code:'82',flag:'\uD83C\uDDF0\uD83C\uDDF7'},
                    {name:'Spain',code:'34',flag:'\uD83C\uDDEA\uD83C\uDDF8'},
                    {name:'Sweden',code:'46',flag:'\uD83C\uDDF8\uD83C\uDDEA'},
                    {name:'Switzerland',code:'41',flag:'\uD83C\uDDE8\uD83C\uDDED'},
                    {name:'Syria',code:'963',flag:'\uD83C\uDDF8\uD83C\uDDFE'},
                    {name:'Tajikistan',code:'992',flag:'\uD83C\uDDF9\uD83C\uDDEF'},
                    {name:'Thailand',code:'66',flag:'\uD83C\uDDF9\uD83C\uDDED'},
                    {name:'Tunisia',code:'216',flag:'\uD83C\uDDF9\uD83C\uDDF3'},
                    {name:'Turkey',code:'90',flag:'\uD83C\uDDF9\uD83C\uDDF7'},
                    {name:'Turkmenistan',code:'993',flag:'\uD83C\uDDF9\uD83C\uDDF2'},
                    {name:'UAE',code:'971',flag:'\uD83C\uDDE6\uD83C\uDDEA'},
                    {name:'Ukraine',code:'380',flag:'\uD83C\uDDFA\uD83C\uDDE6'},
                    {name:'United Kingdom',code:'44',flag:'\uD83C\uDDEC\uD83C\uDDE7'},
                    {name:'United States',code:'1',flag:'\uD83C\uDDFA\uD83C\uDDF8'},
                    {name:'Uzbekistan',code:'998',flag:'\uD83C\uDDFA\uD83C\uDDFF'},
                    {name:'Vietnam',code:'84',flag:'\uD83C\uDDFB\uD83C\uDDF3'},
                ],
                get filteredCountries() {
                    if (!this.search) return this.countries;
                    let s = this.search.toLowerCase().replace(/^\+/, '');
                    return this.countries.filter(c =>
                        c.name.toLowerCase().includes(s) || c.code.includes(s)
                    );
                },
                init() {
                    this.$watch('open', v => {
                        if (v) {
                            this.$nextTick(() => {
                                if (this.$refs.searchInput) {
                                    this.$refs.searchInput.focus();
                                }
                            });
                        } else {
                            this.search = '';
                        }
                    });
                },
                selectCountry(c) {
                    this.selectedCode = c.code;
                    this.selectedName = c.name;
                    this.selectedFlag = c.flag;
                    this.open = false;
                    this.$nextTick(() => {
                        if (this.$refs.phoneInput) this.$refs.phoneInput.focus();
                    });
                }
            };
        }

        @if($student->phone && $student->telegram_verification_code && !$student->telegram_verified_at)
        // Poll for Telegram verification
        (function checkVerification() {
            setInterval(function() {
                fetch('{{ route("student.verify-telegram.check") }}', {
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
