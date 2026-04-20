<x-student-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-sm text-gray-800 leading-tight">
            {{ __('Talaba profili') }}
        </h2>
    </x-slot>

    <div class="pb-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <div class="flex flex-col md:flex-row">
                        <div class="md:w-1/4 pr-4 flex flex-col items-center">
                            <img src="{{ $profileData['image'] }}" alt="{{ $profileData['full_name'] }}" class="w-48 h-48 object-cover rounded-full shadow-md mb-4">
                            <h3 class="text-xl font-bold text-center mb-2">{{ $profileData['full_name'] }}</h3>
                            <p class="text-gray-600 text-center">{{ $profileData['student_id_number'] }}</p>
                        </div>
                        <div class="md:w-3/4 mt-4 md:mt-0">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <h4 class="font-semibold text-lg mb-3 text-gray-700">{{ __("Shaxsiy ma'lumotlar") }}</h4>
                                    <ul class="space-y-2">
                                        <li><span class="font-medium">{{ __("Tug'ilgan sana:") }}</span> {{ date('d.m.Y', $profileData['birth_date']) }}</li>
                                        <li><span class="font-medium">{{ __('Email:') }}</span> {{ $profileData['email'] }}</li>
                                        <li><span class="font-medium">{{ __('Jinsi:') }}</span> {{ $profileData['gender']['name'] }}</li>
                                    </ul>

                                    {{-- Telefon va Telegram tahrirlash --}}
                                    <div class="mt-4 pt-3 border-t border-gray-200">
                                        @if(session('success'))
                                            <div class="mb-3 px-3 py-2 bg-green-100 border border-green-300 text-green-700 rounded-lg text-sm font-medium">{{ session('success') }}</div>
                                        @endif
                                        @if($errors->any())
                                            <div class="mb-3 px-3 py-2 bg-red-100 border border-red-300 text-red-700 rounded-lg text-sm font-medium">
                                                @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
                                            </div>
                                        @endif

                                        <form method="POST" action="{{ route('student.profile.update-contact') }}">
                                            @csrf
                                            <div class="space-y-3">
                                                <div>
                                                    <label class="text-sm font-medium text-gray-600">{{ __('Telefon raqam') }}</label>
                                                    <input type="text" name="phone" value="{{ old('phone', $profileData['phone']) }}"
                                                           placeholder="+998901234567"
                                                           class="mt-1 w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                                                </div>
                                                <div>
                                                    <label class="text-sm font-medium text-gray-600">{{ __('Telegram username') }}</label>
                                                    <input type="text" name="telegram_username" value="{{ old('telegram_username', $profileData['telegram_username']) }}"
                                                           placeholder="@username"
                                                           class="mt-1 w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                                                </div>
                                                <button type="submit"
                                                        class="w-full px-4 py-2.5 bg-blue-500 hover:bg-blue-600 text-white text-sm font-semibold rounded-lg transition">
                                                    {{ __('Saqlash') }}
                                                </button>
                                            </div>
                                        </form>

                                        {{-- Telegram tasdiqlash bo'limi --}}
                                        @if($student->telegram_username && $student->telegram_verification_code && !$student->telegram_verified_at)
                                            <div class="mt-3 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                                                <p class="text-xs font-semibold text-blue-800 mb-2">{{ __('Telegram tasdiqlash') }}</p>
                                                <p class="text-xs text-gray-500 mb-1">{{ __('Username:') }} <strong>{{ $student->telegram_username }}</strong></p>
                                                <p class="text-xs text-gray-600 mb-1">{{ __('Tasdiqlash kodingiz:') }}</p>
                                                <div class="flex items-center justify-center py-2 px-4 bg-white rounded-md border border-dashed border-gray-300 mb-2">
                                                    <span class="text-2xl font-mono font-bold tracking-widest text-blue-700">{{ $student->telegram_verification_code }}</span>
                                                </div>
                                                <div class="text-xs text-gray-600 mb-2">
                                                    <ol class="list-decimal list-inside space-y-1 text-gray-500">
                                                        <li>{{ __('Quyidagi tugmani bosing') }}</li>
                                                        <li>{{ __('Botga tasdiqlash kodini yuboring:') }} <strong>{{ $student->telegram_verification_code }}</strong></li>
                                                    </ol>
                                                </div>
                                                @if($botUsername)
                                                    <a href="https://t.me/{{ $botUsername }}?start={{ $student->telegram_verification_code }}"
                                                       target="_blank"
                                                       class="w-full inline-flex justify-center items-center px-3 py-2.5 bg-[#0088cc] text-white text-sm font-medium rounded-lg hover:bg-[#007ab8] transition">
                                                        <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 24 24">
                                                            <path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/>
                                                        </svg>
                                                        {{ __('Telegram botni ochish') }}
                                                    </a>
                                                @endif
                                                <div id="profile-tg-status" class="hidden mt-2 p-2 bg-green-50 border border-green-200 rounded-lg text-sm text-green-700 font-medium text-center">
                                                    {{ __('Telegram tasdiqlandi!') }}
                                                </div>
                                            </div>
                                        @elseif($student->telegram_verified_at)
                                            <div class="mt-3 p-3 bg-green-50 border border-green-200 rounded-lg flex items-center gap-2">
                                                <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                                <span class="text-sm font-medium text-green-700">{{ __('Telegram tasdiqlangan') }}: {{ $student->telegram_username }}</span>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <h4 class="font-semibold text-lg mb-3 text-gray-700">{{ __("Ta'lim ma'lumotlari") }}</h4>
                                    <ul class="space-y-2">
                                        <li><span class="font-medium">{{ __('Fakultet:') }}</span> {{ $profileData['faculty']['name'] }}</li>
                                        <li><span class="font-medium">{{ __("Yo'nalish:") }}</span> {{ $profileData['specialty']['name'] }}</li>
                                        <li><span class="font-medium">{{ __('Guruh:') }}</span> {{ $profileData['group']['name'] }}</li>
                                        <li><span class="font-medium">{{ __('Kurs:') }}</span> {{ $profileData['level']['name'] }}</li>
                                        <li><span class="font-medium">{{ __("Ta'lim turi:") }}</span> {{ $profileData['educationType']['name'] }}</li>
                                        <li>
                                            <span class="font-medium">{{ __('Talaba holati:') }}</span>
                                            @if($profileData['is_graduate'])
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">{{ __('Bitiruvchi') }}</span>
                                            @else
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">{{ __('Talaba') }}</span>
                                            @endif
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            <div class="mt-6 bg-gray-50 p-4 rounded-lg">
                                <h4 class="font-semibold text-lg mb-3 text-gray-700">{{ __('Manzil') }}</h4>
                                <ul class="space-y-2">
                                    <li><span class="font-medium">{{ __("To'liq manzil:") }}</span> {{ $profileData['address'] }}</li>
                                    <li><span class="font-medium">{{ __('Viloyat:') }}</span> {{ $profileData['province']['name'] }}</li>
                                    <li><span class="font-medium">{{ __('Tuman:') }}</span> {{ $profileData['district']['name'] }}</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    {{-- Chiqish (Log out) button --}}
                    <div class="mt-6 pt-4 border-t border-gray-200">
                        <form method="POST" action="{{ route('student.logout') }}">
                            @csrf
                            <button type="submit"
                                    class="w-full flex items-center justify-center gap-2 px-4 py-3 bg-red-50 hover:bg-red-100 text-red-600 font-semibold rounded-lg transition">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" />
                                </svg>
                                {{ __('Tizimdan chiqish') }}
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if($student->telegram_username && $student->telegram_verification_code && !$student->telegram_verified_at)
    <script>
        setInterval(function() {
            fetch('{{ route("student.verify-telegram.check") }}', {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(r => r.json())
            .then(data => {
                if (data.verified) {
                    document.getElementById('profile-tg-status')?.classList.remove('hidden');
                    setTimeout(() => location.reload(), 1500);
                }
            }).catch(() => {});
        }, 3000);
    </script>
    @endif
</x-student-app-layout>
