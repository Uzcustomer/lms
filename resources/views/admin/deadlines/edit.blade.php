<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            {{ __('Muddatlarni Tahrirlash') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="overflow-hidden bg-white shadow-xl sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <h2 class="mb-4 text-lg font-semibold">Har bir kurs darajasi uchun muddatlarni tahrirlash (kunlarda)
                    </h2>

                    @if (session('success'))
                        <div class="p-4 mb-4 text-sm text-green-700 bg-green-100 rounded-lg">
                            {{ session('success') }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('admin.deadlines.update') }}">
                        @csrf
                        <div class="grid grid-cols-1 gap-6 mt-4 sm:grid-cols-3">
                            @foreach ($deadlines as $deadline)
                                <div>
                                    <label for="deadlines[{{ $deadline->level_code }}][days]"
                                        class="block text-sm font-medium text-gray-700">
                                        {{ $deadline->level->level_name }} ({{ $deadline->level_code }}) Muddati
                                    </label>
                                    <input type="number" name="deadlines[{{ $deadline->level_code }}][days]"
                                        id="deadlines[{{ $deadline->level_code }}][days]"
                                        class="block w-full mt-1 border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                                        value="{{ old('deadlines.' . $deadline->level_code . '.days', $deadline->deadline_days ?? '') }}">
                                    @error('deadlines.' . $deadline->level_code . '.days')
                                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div>
                                    <label for="deadlines[{{ $deadline->level_code }}][joriy]"
                                        class="block text-sm font-medium text-gray-700">
                                        {{ $deadline->level->level_name }} ({{ $deadline->level_code }}) Joriy nazorat
                                        o'tish bali
                                    </label>
                                    <input type="number" name="deadlines[{{ $deadline->level_code }}][joriy]"
                                        id="deadlines[{{ $deadline->level_code }}][joriy]"
                                        class="block w-full mt-1 border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                                        value="{{ old('deadlines.' . $deadline->level_code . '.joriy', $deadline->joriy ?? '') }}">
                                    @error('deadlines.' . $deadline->level_code . '.joriy')
                                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div>
                                    <label for="deadlines[{{ $deadline->level_code }}][mustaqil_talim]"
                                        class="block text-sm font-medium text-gray-700">
                                        {{ $deadline->level->level_name }} ({{ $deadline->level_code }}) Mustaqil ta'lim
                                        o'tish
                                        bali
                                    </label>
                                    <input type="number" name="deadlines[{{ $deadline->level_code }}][mustaqil_talim]"
                                        id="deadlines[{{ $deadline->level_code }}][mustaqil_talim]"
                                        class="block w-full mt-1 border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                                        value="{{ old('deadlines.' . $deadline->level_code . '.mustaqil_talim', $deadline->mustaqil_talim ?? '') }}">
                                    @error('deadlines.' . $deadline->level_code . '.mustaqil_talim')
                                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            @endforeach
                        </div>

                        <div class="flex justify-end mt-6">
                            <button type="submit"
                                class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                Muddatlarni yangilash
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>