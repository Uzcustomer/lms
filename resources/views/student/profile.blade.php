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
                                                    <label class="text-sm font-medium text-gray-600">{{ __('Telegram ID') }}</label>
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
</x-student-app-layout>
