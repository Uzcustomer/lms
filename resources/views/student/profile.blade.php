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
                                    <h4 class="font-semibold text-lg mb-3 text-gray-700">Shaxsiy ma'lumotlar</h4>
                                    <ul class="space-y-2">
                                        <li><span class="font-medium">Tug'ilgan sana:</span> {{ date('d.m.Y', $profileData['birth_date']) }}</li>
                                        <li><span class="font-medium">Telefon:</span> {{ $profileData['phone'] }}</li>
                                        <li><span class="font-medium">Email:</span> {{ $profileData['email'] }}</li>
                                        <li><span class="font-medium">Jinsi:</span> {{ $profileData['gender']['name'] }}</li>
                                    </ul>
                                </div>
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <h4 class="font-semibold text-lg mb-3 text-gray-700">Ta'lim ma'lumotlari</h4>
                                    <ul class="space-y-2">
                                        <li><span class="font-medium">Fakultet:</span> {{ $profileData['faculty']['name'] }}</li>
                                        <li><span class="font-medium">Yo'nalish:</span> {{ $profileData['specialty']['name'] }}</li>
                                        <li><span class="font-medium">Guruh:</span> {{ $profileData['group']['name'] }}</li>
                                        <li><span class="font-medium">Kurs:</span> {{ $profileData['level']['name'] }}</li>
                                        <li><span class="font-medium">Ta'lim turi:</span> {{ $profileData['educationType']['name'] }}</li>
                                    </ul>
                                </div>
                            </div>
                            <div class="mt-6 bg-gray-50 p-4 rounded-lg">
                                <h4 class="font-semibold text-lg mb-3 text-gray-700">Manzil</h4>
                                <ul class="space-y-2">
                                    <li><span class="font-medium">To'liq manzil:</span> {{ $profileData['address'] }}</li>
                                    <li><span class="font-medium">Viloyat:</span> {{ $profileData['province']['name'] }}</li>
                                    <li><span class="font-medium">Tuman:</span> {{ $profileData['district']['name'] }}</li>
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
                                Tizimdan chiqish
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-student-app-layout>
