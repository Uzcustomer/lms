<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            {{ __('Sinxronizatsiya') }}
        </h2>
    </x-slot>

    <div class="py-8 ">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="p-6">
                    <h2 class="text-lg font-semibold mb-6 text-gray-800 border-b pb-2">Dars jadvalini sinxronlash</h2>

                    <div class="mb-4 rounded-md bg-blue-50 p-4 border-l-4 border-blue-500">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-blue-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-blue-800">Sinxronizatsiya haqida muhim ma'lumot</h3>
                                <div class="mt-2 text-sm text-blue-700">
                                    <p>Siz tanlagan vaqt oralig'i  uchun sinxronizatsiya jarayoni boshlandi.</p>
                                    <ul class="list-disc pl-5 mt-1 space-y-1">
                                        <li>Bu jarayon fon rejimida ishlaydi va tanlangan vaqt oralig'iga qarab bir necha daqiqadan bir necha soatgacha davom etishi mumkin.</li>
                                        <li>Sinxronizatsiya davomida tizimning boshqa qismlariga ta'sir qilishi mumkin, shu sababli tizim yuklamasi kam bo'lgan vaqtda (masalan, kechqurun yoki erta tongda) ishlatish tavsiya etiladi.</li>
                                        <li>Jarayon yakunlanganda, natijalar haqida xabar olasiz.</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    @if (session('error'))
                        <div class="mb-4 rounded-md bg-red-50 p-4 border-l-4 border-red-500">
                            <div class="flex items-center">
                                <svg class="h-5 w-5 text-red-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                </svg>
                                <span class="text-red-700">{{ session('error') }}</span>
                            </div>
                        </div>
                    @endif

                    @if (session('success'))
                        <div class="mb-4 rounded-md bg-green-50 p-4 border-l-4 border-green-500">
                            <div class="flex items-center">
                                <svg class="h-5 w-5 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <span class="text-green-700">{{ session('success') }}</span>
                            </div>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('admin.synchronize') }}" class="space-y-5">
                        @csrf

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                            <div>
                                <label for="start_date" class="block text-sm font-medium text-gray-700 mb-1">
                                    Muddatidan boshlab
                                </label>
                                <input type="date" name="start_date" id="start_date"
                                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" />
                            </div>

                            <div>
                                <label for="end_date" class="block text-sm font-medium text-gray-700 mb-1">
                                    Muddatigacha
                                </label>
                                <input type="date" name="end_date" id="end_date"
                                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" />
                            </div>
                        </div>

                        <div class="pt-3">
                            <button type="submit"
                                    class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                                <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                </svg>
                                Jadvallarni yangilash
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- HEMIS ma'lumotlarini sinxronlash -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden mt-6">
                <div class="p-6">
                    <h2 class="text-lg font-semibold mb-6 text-gray-800 border-b pb-2">HEMIS ma'lumotlarini sinxronlash</h2>

                    <div class="mb-4 rounded-md bg-yellow-50 p-4 border-l-4 border-yellow-500">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-yellow-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                </svg>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-yellow-800">Diqqat!</h3>
                                <div class="mt-2 text-sm text-yellow-700">
                                    <p>Quyidagi sinxronizatsiyalar fon rejimida ishlaydi. Har bir jarayon HEMIS API'dan ma'lumotlarni yuklab, mahalliy bazaga saqlaydi.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        <!-- O'quv rejalar -->
                        <form method="POST" action="{{ route('admin.synchronize.curricula') }}">
                            @csrf
                            <button type="submit"
                                    class="w-full inline-flex items-center justify-center px-4 py-3 bg-indigo-600 border border-transparent rounded-md font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors">
                                <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                O'quv rejalar
                            </button>
                        </form>

                        <!-- O'quv reja fanlari -->
                        <form method="POST" action="{{ route('admin.synchronize.curriculum-subjects') }}">
                            @csrf
                            <button style="background: purple" type="submit"
                                    class="w-full inline-flex items-center justify-center px-4 py-3 bg-purple-600 border border-transparent rounded-md font-medium text-white hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500 transition-colors">
                                <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                                </svg>
                                O'quv reja fanlari
                            </button>
                        </form>

                        <!-- Guruhlar -->
                        <form method="POST" action="{{ route('admin.synchronize.groups') }}">
                            @csrf
                            <button type="submit"
                                    class="w-full inline-flex items-center justify-center px-4 py-3 bg-green-600 border border-transparent rounded-md font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-colors">
                                <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                </svg>
                                Guruhlar
                            </button>
                        </form>

                        <!-- Semestrlar -->
                        <form method="POST" action="{{ route('admin.synchronize.semesters') }}">
                            @csrf
                            <button type="submit"
                                    class="w-full inline-flex items-center justify-center px-4 py-3 bg-teal-600 border border-transparent rounded-md font-medium text-white hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-teal-500 transition-colors">
                                <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                </svg>
                                Semestrlar
                            </button>
                        </form>

                        <!-- Mutaxassislik/Kafedralar -->
                        <form method="POST" action="{{ route('admin.synchronize.specialties-departments') }}">
                            @csrf
                            <button type="submit"
                                    class="w-full inline-flex items-center justify-center px-4 py-3 bg-orange-600 border border-transparent rounded-md font-medium text-white hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500 transition-colors">
                                <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                </svg>
                                Mutaxassislik/Kafedralar
                            </button>
                        </form>

                        <!-- Talabalar -->
                        <form method="POST" action="{{ route('admin.synchronize.students') }}">
                            @csrf
                            <button type="submit"
                                    class="w-full inline-flex items-center justify-center px-4 py-3 bg-cyan-600 border border-transparent rounded-md font-medium text-white hover:bg-cyan-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-cyan-500 transition-colors">
                                <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                                </svg>
                                Talabalar
                            </button>
                        </form>

                        <!-- O'qituvchilar -->
                        <form method="POST" action="{{ route('admin.synchronize.teachers') }}">
                            @csrf
                            <button type="submit"
                                    class="w-full inline-flex items-center justify-center px-4 py-3 bg-rose-600 border border-transparent rounded-md font-medium text-white hover:bg-rose-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-rose-500 transition-colors">
                                <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                </svg>
                                O'qituvchilar
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
