<div class="bg-white rounded-lg shadow-sm border border-gray-200">
    <div class="px-6 py-4 border-b border-gray-200">
        <h3 class="text-lg font-semibold text-gray-900">Parol sozlamalari</h3>
        <p class="mt-1 text-sm text-gray-500">Talaba parollarining amal qilish muddatlari</p>
    </div>

    <div class="p-6">
        <div class="p-4 mb-6 text-sm text-blue-700 bg-blue-50 rounded-lg border border-blue-200">
            <p class="font-semibold mb-2">Parolni tiklash jarayoni:</p>
            <ol class="list-decimal list-inside space-y-1">
                <li>Admin talabaning parolini tiklaydi (vaqtinchalik parol = talaba ID raqami)</li>
                <li>Talaba vaqtinchalik parol bilan tizimga kiradi</li>
                <li>Tizim talabani yangi parol o'rnatishga majbur qiladi</li>
                <li>Yangi parol ham muddatli bo'ladi — talaba HEMIS parolini tiklab olguncha foydalanadi</li>
            </ol>
        </div>

        <form method="POST" action="{{ route('admin.settings.update.password') }}">
            @csrf

            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
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

            <div class="flex justify-end mt-6 pt-4 border-t border-gray-200">
                <button type="submit"
                        class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Saqlash
                </button>
            </div>
        </form>
    </div>
</div>
