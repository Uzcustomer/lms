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
        </div>
    </div>
</x-app-layout>
