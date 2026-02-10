<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            Parol sozlamalari
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="overflow-hidden bg-white shadow-xl sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <h2 class="mb-4 text-lg font-semibold">Parol va muddat sozlamalari</h2>

                    @if (session('success'))
                        <div class="p-4 mb-4 text-sm text-green-700 bg-green-100 rounded-lg">
                            {{ session('success') }}
                        </div>
                    @endif

                    @if (session('error'))
                        <div class="p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-lg">
                            {{ session('error') }}
                        </div>
                    @endif

                    <div class="p-4 mb-6 text-sm text-blue-700 bg-blue-50 rounded-lg">
                        <p class="font-semibold mb-2">Parolni tiklash jarayoni:</p>
                        <ol class="list-decimal list-inside space-y-1">
                            <li>Admin talabaning parolini tiklaydi (vaqtinchalik parol = talaba ID raqami)</li>
                            <li>Talaba vaqtinchalik parol bilan tizimga kiradi</li>
                            <li>Tizim talabani yangi parol o'rnatishga majbur qiladi</li>
                            <li>Yangi parol ham muddatli bo'ladi — talaba HEMIS parolini tiklab olguncha foydalanadi</li>
                        </ol>
                    </div>

                    <form method="POST" action="{{ route('admin.password-settings.update') }}">
                        @csrf

                        <div class="grid grid-cols-1 gap-6 mt-4 sm:grid-cols-2">
                            <div>
                                <label for="temp_password_days" class="block text-sm font-medium text-gray-700">
                                    Vaqtinchalik parol amal qilish muddati (kun)
                                </label>
                                <p class="text-xs text-gray-500 mt-1 mb-2">Admin tomonidan tiklanganidan keyin necha kun amal qiladi</p>
                                <input type="number"
                                       name="temp_password_days"
                                       id="temp_password_days"
                                       min="1"
                                       max="365"
                                       class="block w-full mt-1 border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                                       value="{{ old('temp_password_days', $tempPasswordDays) }}">
                                @error('temp_password_days')
                                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="changed_password_days" class="block text-sm font-medium text-gray-700">
                                    O'zgartirilgan parol amal qilish muddati (kun)
                                </label>
                                <p class="text-xs text-gray-500 mt-1 mb-2">Talaba o'zi yangi parol o'rnatganidan keyin necha kun amal qiladi</p>
                                <input type="number"
                                       name="changed_password_days"
                                       id="changed_password_days"
                                       min="1"
                                       max="365"
                                       class="block w-full mt-1 border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                                       value="{{ old('changed_password_days', $changedPasswordDays) }}">
                                @error('changed_password_days')
                                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <h3 class="mt-8 mb-4 text-lg font-semibold text-gray-800 border-t pt-6">Telegram tasdiqlash muddati</h3>

                        <div class="p-4 mb-6 text-sm text-blue-700 bg-blue-50 rounded-lg">
                            <p>O'qituvchilar tizimga kirgandan keyin belgilangan muddat ichida Telegram username ni tasdiqlashtlari kerak. Muddat o'tgandan keyin akkaunt bloklanadi.</p>
                        </div>

                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                            <div>
                                <label for="telegram_deadline_days" class="block text-sm font-medium text-gray-700">
                                    Telegram tasdiqlash muddati (kun)
                                </label>
                                <p class="text-xs text-gray-500 mt-1 mb-2">Telefon raqami kiritilganidan keyin necha kun ichida Telegram tasdiqlanishi kerak</p>
                                <input type="number"
                                       name="telegram_deadline_days"
                                       id="telegram_deadline_days"
                                       min="1"
                                       max="365"
                                       class="block w-full mt-1 border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                                       value="{{ old('telegram_deadline_days', $telegramDeadlineDays) }}">
                                @error('telegram_deadline_days')
                                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="flex justify-end mt-6">
                            <button type="submit"
                                    class="inline-flex items-center px-4 py-2 text-sm font-medium text-white border border-transparent rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                                    style="background-color: #2563eb;">
                                Sozlamalarni saqlash
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
