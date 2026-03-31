<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Yangi foydalanuvchi yaratish') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <form action="{{ route('admin.users.store') }}" method="POST">
                        @csrf
                        <div class="mb-4">
                            <label for="name" class="block text-gray-700 text-sm font-bold mb-2">Ism:</label>
                            <input type="text" name="name" id="name"
                                   class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                   required>
                        </div>
                        <div class="mb-4">
                            <label for="email" class="block text-gray-700 text-sm font-bold mb-2">Email:</label>
                            <input type="email" name="email" id="email"
                                   class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                   required>
                        </div>
                        <div class="mb-4">
                            <label for="password" class="block text-gray-700 text-sm font-bold mb-2">Password:</label>
                            <input type="password" name="password" id="password"
                                   class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                   required>
                        </div>
                        <div class="mb-4">
                            <label for="roles" class="block text-gray-700 text-sm font-bold mb-2">Rollar (bir nechta tanlash mumkin):</label>
                            <select name="roles[]" id="roles" multiple
                                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                    style="min-height: 150px;"
                                    required
                                    onchange="toggleFirmSelect()">
                                @foreach($roles as $role)
                                    <option value="{{ $role->value }}">{{ $role->label() }}</option>
                                @endforeach
                            </select>
                            <p class="text-gray-500 text-xs mt-1">Ctrl (Cmd) tugmasini bosib bir nechta rol tanlang</p>
                        </div>

                        <div id="firm-section" class="mb-4 p-4 bg-blue-50 border border-blue-200 rounded-lg" style="display: none;">
                            <label for="assigned_firm" class="block text-gray-700 text-sm font-bold mb-2">Javobgar firma: <span class="text-red-500">*</span></label>
                            <select name="assigned_firm" id="assigned_firm"
                                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                <option value="">Tanlang</option>
                                @foreach($firmOptions as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                                <option value="other">Boshqa</option>
                            </select>
                            <p class="text-gray-500 text-xs mt-1">Bu xodim qaysi firma talabalari uchun javobgar</p>
                        </div>

                        <div class="flex items-center justify-between">
                            <button type="submit"
                                    class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                                <i class="fas fa-save mr-2"></i>Yaratish
                            </button>
                            <a href="{{ route('admin.users.index') }}"
                               class="inline-block align-baseline font-bold text-sm text-blue-500 hover:text-blue-800">
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
        var select = document.getElementById('roles');
        var firmSection = document.getElementById('firm-section');
        var selected = Array.from(select.selectedOptions).map(function(o) { return o.value; });
        firmSection.style.display = selected.includes('javobgar_firma') ? 'block' : 'none';
    }
    </script>
</x-app-layout>
