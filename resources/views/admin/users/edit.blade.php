<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Tahrirlash') }} — {{ $user->name }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">

                    @if($errors->any())
                        <div class="mb-4 p-4 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm">
                            <ul class="list-disc list-inside">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form action="{{ route('admin.users.update', $user) }}" method="POST">
                        @csrf
                        @method('PUT')
                        <div class="mb-4">
                            <label for="name" class="block text-gray-700 text-sm font-bold mb-2">Ism:</label>
                            <input type="text" name="name" id="name" value="{{ old('name', $user->name) }}"
                                   class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                        </div>
                        <div class="mb-4">
                            <label for="email" class="block text-gray-700 text-sm font-bold mb-2">Email:</label>
                            <input type="email" name="email" id="email" value="{{ old('email', $user->email) }}"
                                   class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                        </div>
                        <div class="mb-4">
                            <label for="password" class="block text-gray-700 text-sm font-bold mb-2">Parol (Yangilanmasa eski qiyatida qoladi):</label>
                            <input type="password" name="password" id="password"
                                   class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                        </div>

                        {{-- Rollar — checkboxlar --}}
                        <div class="mb-4">
                            <label class="block text-gray-700 text-sm font-bold mb-2">Rollar:</label>
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-2 p-3 border rounded bg-gray-50">
                                @foreach($roles as $role)
                                    <label class="flex items-center gap-2 cursor-pointer text-sm py-1 px-2 rounded hover:bg-white transition">
                                        <input type="checkbox" name="roles[]" value="{{ $role->value }}"
                                               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                               onchange="toggleFirmSelect()"
                                               {{ $user->hasRole($role->value) ? 'checked' : '' }}>
                                        <span>{{ $role->label() }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        {{-- Firma bo'limi --}}
                        <div id="firm-section" class="mb-4 p-4 bg-blue-50 border border-blue-200 rounded-lg" style="display: none;">
                            <div class="flex items-center gap-2 mb-3">
                                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3H21m-3.75 3H21"/></svg>
                                <span class="text-sm font-bold text-blue-800">Javobgar firma sozlamalari</span>
                            </div>
                            <div class="mb-3">
                                <label for="assigned_firm" class="block text-gray-700 text-sm font-bold mb-2">Firma: <span class="text-red-500">*</span></label>
                                <select name="assigned_firm" id="assigned_firm"
                                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                    <option value="">Tanlang</option>
                                    @foreach($firmOptions as $key => $label)
                                        <option value="{{ $key }}" {{ old('assigned_firm', $user->assigned_firm) === $key ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                    <option value="other" {{ old('assigned_firm', $user->assigned_firm) === 'other' ? 'selected' : '' }}>Boshqa</option>
                                </select>
                            </div>
                            <div>
                                <label for="telegram_chat_id" class="block text-gray-700 text-sm font-bold mb-2">Telegram Chat ID:</label>
                                <input type="text" name="telegram_chat_id" id="telegram_chat_id" value="{{ old('telegram_chat_id', $user->telegram_chat_id) }}"
                                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                       placeholder="Masalan: 123456789">
                                <p class="text-gray-500 text-xs mt-1">Telegram orqali ogohlantirish yuborish uchun</p>
                            </div>
                        </div>

                        <div class="flex items-center justify-between">
                            <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                                <i class="fas fa-save mr-2"></i>Yangilash
                            </button>
                            <a href="{{ route('admin.users.index') }}" class="inline-block align-baseline font-bold text-sm text-blue-500 hover:text-blue-800">
                                <i class="fas fa-arrow-left mr-2"></i>Foydalanuvchilar sahifasiga qaytish
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
    function toggleFirmSelect() {
        var checkboxes = document.querySelectorAll('input[name="roles[]"]:checked');
        var selected = Array.from(checkboxes).map(function(cb) { return cb.value; });
        document.getElementById('firm-section').style.display = selected.includes('javobgar_firma') ? 'block' : 'none';
    }
    toggleFirmSelect();
    </script>
</x-app-layout>
