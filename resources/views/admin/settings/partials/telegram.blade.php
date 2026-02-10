<div class="bg-white rounded-lg shadow-sm border border-gray-200">
    <div class="px-6 py-4 border-b border-gray-200">
        <h3 class="text-lg font-semibold text-gray-900">Telegram sozlamalari</h3>
        <p class="mt-1 text-sm text-gray-500">O'qituvchilar uchun Telegram tasdiqlash muddati</p>
    </div>

    <div class="p-6">
        <div class="p-4 mb-6 text-sm text-blue-700 bg-blue-50 rounded-lg border border-blue-200">
            <p>O'qituvchilar tizimga kirgandan keyin belgilangan muddat ichida Telegram username ni tasdiqlashlari kerak. Muddat o'tgandan keyin akkaunt bloklanadi.</p>
        </div>

        <form method="POST" action="{{ route('admin.settings.update.telegram') }}">
            @csrf

            <div class="max-w-md">
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

            <div class="flex justify-end mt-6 pt-4 border-t border-gray-200">
                <button type="submit"
                        class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Saqlash
                </button>
            </div>
        </form>
    </div>
</div>
