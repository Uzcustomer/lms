<div class="bg-white rounded-lg shadow-sm border border-gray-200">
    <div class="px-6 py-4 border-b border-gray-200">
        <h3 class="text-lg font-semibold text-gray-900">Muddatlar sozlamalari</h3>
        <p class="mt-1 text-sm text-gray-500">Har bir kurs darajasi uchun muddatlar va boshqa muddat sozlamalari</p>
    </div>

    <div class="p-6">
        <form method="POST" action="{{ route('admin.settings.update.deadlines') }}">
            @csrf

            {{-- Spravka --}}
            <div class="mb-6 p-4 bg-amber-50 border border-amber-200 rounded-lg">
                <label for="spravka_deadline_days" class="block text-sm font-medium text-gray-700 mb-1">
                    Spravka topshirish muddati (kunlarda)
                </label>
                <div class="flex items-center gap-3">
                    <input type="number" name="spravka_deadline_days" id="spravka_deadline_days"
                        class="block w-32 border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                        value="{{ old('spravka_deadline_days', $spravkaDays ?? 10) }}" min="1">
                    <span class="text-sm text-gray-500">kun</span>
                </div>
            </div>

            {{-- MT sozlamalari --}}
            <div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                <h4 class="text-sm font-semibold text-gray-800 mb-3">Mustaqil ta'lim topshiriq muddati sozlamalari</h4>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Topshiriq muddati qachon tugaydi?
                    </label>
                    <div class="space-y-2">
                        <label class="flex items-center">
                            <input type="radio" name="mt_deadline_type" value="before_last"
                                {{ old('mt_deadline_type', $mtDeadlineType) == 'before_last' ? 'checked' : '' }}
                                class="text-blue-600 focus:ring-blue-500">
                            <span class="ml-2 text-sm text-gray-700">Oxirgi darsdan bitta oldingi darsda</span>
                        </label>
                        <label class="flex items-center">
                            <input type="radio" name="mt_deadline_type" value="last"
                                {{ old('mt_deadline_type', $mtDeadlineType) == 'last' ? 'checked' : '' }}
                                class="text-blue-600 focus:ring-blue-500">
                            <span class="ml-2 text-sm text-gray-700">Oxirgi darsda</span>
                        </label>
                        <label class="flex items-center">
                            <input type="radio" name="mt_deadline_type" value="fixed_days"
                                {{ old('mt_deadline_type', $mtDeadlineType) == 'fixed_days' ? 'checked' : '' }}
                                class="text-blue-600 focus:ring-blue-500">
                            <span class="ml-2 text-sm text-gray-700">Dars sanasidan + N kun (yuqoridagi muddat kunlari asosida)</span>
                        </label>
                    </div>
                </div>

                <div>
                    <label for="mt_deadline_time" class="block text-sm font-medium text-gray-700 mb-1">
                        Topshiriq muddati vaqti
                    </label>
                    <div class="flex items-center gap-3">
                        <input type="time" name="mt_deadline_time" id="mt_deadline_time"
                            class="block w-40 border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                            value="{{ old('mt_deadline_time', $mtDeadlineTime ?? '17:00') }}">
                        <span class="text-sm text-gray-500">gacha (default: 17:00)</span>
                    </div>
                </div>

                <div class="mt-4">
                    <label for="mt_max_resubmissions" class="block text-sm font-medium text-gray-700 mb-1">
                        MT topshirig'ini qayta yuklash imkoniyati (necha marta)
                    </label>
                    <div class="flex items-center gap-3">
                        <input type="number" name="mt_max_resubmissions" id="mt_max_resubmissions"
                            class="block w-32 border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                            value="{{ old('mt_max_resubmissions', $mtMaxResubmissions ?? 3) }}" min="0" max="10">
                        <span class="text-sm text-gray-500">marta (baho 60 dan past bo'lsa)</span>
                    </div>
                    <p class="text-xs text-gray-400 mt-1">0 = qayta yuklash mumkin emas. Baho 60 va undan yuqori bo'lsa, baho qulflanadi.</p>
                </div>
            </div>

            {{-- Per-level deadlines --}}
            <h4 class="text-sm font-semibold text-gray-800 mb-3">Kurs darajalari bo'yicha muddatlar</h4>
            <div class="grid grid-cols-1 gap-6 sm:grid-cols-3">
                @foreach ($deadlines as $deadline)
                    <div>
                        <label class="block text-sm font-medium text-gray-700">
                            {{ $deadline->level->level_name ?? $deadline->level_code }} — Muddati
                        </label>
                        <input type="number" name="deadlines[{{ $deadline->level_code }}][days]"
                            class="block w-full mt-1 border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                            value="{{ old('deadlines.' . $deadline->level_code . '.days', $deadline->deadline_days ?? '') }}">
                        @error('deadlines.' . $deadline->level_code . '.days')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">
                            {{ $deadline->level->level_name ?? $deadline->level_code }} — Joriy nazorat o'tish bali
                        </label>
                        <input type="number" name="deadlines[{{ $deadline->level_code }}][joriy]"
                            class="block w-full mt-1 border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                            value="{{ old('deadlines.' . $deadline->level_code . '.joriy', $deadline->joriy ?? '') }}">
                        @error('deadlines.' . $deadline->level_code . '.joriy')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">
                            {{ $deadline->level->level_name ?? $deadline->level_code }} — Mustaqil ta'lim o'tish bali
                        </label>
                        <input type="number" name="deadlines[{{ $deadline->level_code }}][mustaqil_talim]"
                            class="block w-full mt-1 border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                            value="{{ old('deadlines.' . $deadline->level_code . '.mustaqil_talim', $deadline->mustaqil_talim ?? '') }}">
                        @error('deadlines.' . $deadline->level_code . '.mustaqil_talim')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                @endforeach
            </div>

            <div class="flex justify-end mt-6 pt-4 border-t border-gray-200">
                <button type="submit"
                    class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Muddatlarni saqlash
                </button>
            </div>
        </form>
    </div>
</div>
