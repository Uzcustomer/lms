<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Xodim profili
        </h2>
    </x-slot>

    @if (session('success'))
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 mt-4">
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                <span class="block sm:inline">{{ session('success') }}</span>
            </div>
        </div>
    @endif

    @if (session('error'))
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 mt-4">
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                <span class="block sm:inline">{{ session('error') }}</span>
            </div>
        </div>
    @endif

    @if ($errors->any())
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 mt-4">
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                @foreach ($errors->all() as $error)
                    <span class="block sm:inline">{{ $error }}</span>
                @endforeach
            </div>
        </div>
    @endif

    <div class="py-4">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            <div class="mb-4">
                <a href="{{ route('admin.teachers.index') }}" class="inline-flex items-center text-sm text-gray-500 hover:text-gray-800 transition">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    Xodimlar ro'yxati
                </a>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

                {{-- Chap ustun: Profil kartasi --}}
                <div class="lg:col-span-1">
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                        {{-- Profil header --}}
                        <div class="px-4 py-4 flex items-center border-b border-gray-100">
                            @if($teacher->image)
                                <img src="{{ $teacher->image }}" alt=""
                                     class="w-14 h-14 rounded-full object-cover flex-shrink-0">
                            @else
                                <div class="w-14 h-14 rounded-full bg-indigo-100 flex items-center justify-center flex-shrink-0">
                                    <span class="text-xl font-bold text-indigo-700">{{ mb_substr($teacher->first_name ?? '', 0, 1) }}{{ mb_substr($teacher->second_name ?? '', 0, 1) }}</span>
                                </div>
                            @endif
                            <div class="ml-3 min-w-0">
                                <h3 class="text-sm font-bold text-gray-900 truncate">{{ $teacher->full_name }}</h3>
                                <p class="text-xs text-gray-500">{{ $teacher->staff_position ?? '-' }}</p>
                                <span class="inline-flex items-center mt-1 px-1.5 py-0.5 rounded text-xs font-semibold {{ $teacher->status ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                    {{ $teacher->status ? 'Faol' : 'Nofaol' }}
                                </span>
                            </div>
                        </div>

                        {{-- Ma'lumotlar --}}
                        <div class="px-4 py-3 space-y-2.5 text-xs">
                            <div class="flex items-start">
                                <span class="text-gray-400 w-24 flex-shrink-0">ID raqami</span>
                                <span class="font-medium text-gray-900">{{ $teacher->employee_id_number }}</span>
                            </div>
                            <div class="flex items-start">
                                <span class="text-gray-400 w-24 flex-shrink-0">Kafedra</span>
                                <span class="font-medium text-gray-900">{{ $teacher->department ?? '-' }}</span>
                            </div>
                            <div class="flex items-start">
                                <span class="text-gray-400 w-24 flex-shrink-0">Jinsi</span>
                                <span class="font-medium text-gray-900">{{ $teacher->gender ?? '-' }}</span>
                            </div>
                            @if($teacher->birth_date)
                            <div class="flex items-start">
                                <span class="text-gray-400 w-24 flex-shrink-0">Tug'ilgan sana</span>
                                <span class="font-medium text-gray-900">{{ $teacher->birth_date }}</span>
                            </div>
                            @endif
                            <div class="flex items-start">
                                <span class="text-gray-400 w-24 flex-shrink-0">Ish turi</span>
                                <span class="font-medium text-gray-900">{{ $teacher->employment_form ?? '-' }}</span>
                            </div>
                            @if($teacher->employee_type)
                            <div class="flex items-start">
                                <span class="text-gray-400 w-24 flex-shrink-0">Xodim turi</span>
                                <span class="font-medium text-gray-900">{{ $teacher->employee_type }}</span>
                            </div>
                            @endif
                        </div>
                    </div>

                    {{-- Parolni tiklash --}}
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden mt-4">
                        <div class="px-4 py-3 border-b border-gray-100">
                            <h4 class="text-sm font-semibold text-gray-900">Parolni tiklash</h4>
                        </div>
                        <div class="px-4 py-3">
                            @if($teacher->must_change_password)
                                <div class="mb-2 p-2 bg-yellow-50 border border-yellow-200 rounded text-xs text-yellow-800">
                                    <svg class="w-3.5 h-3.5 inline mr-1" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                    </svg>
                                    Parol tiklangan. Xodim keyingi kirishda parolni o'zgartirishi kerak.
                                </div>
                            @endif
                            <p class="text-xs text-gray-500 mb-2">
                                Parol tug'ilgan sana formatida tiklanadi: <strong>ddmmyyyy</strong>
                                @if($teacher->birth_date)
                                    ({{ \Carbon\Carbon::parse($teacher->birth_date)->format('d.m.Y') }})
                                @endif
                            </p>
                            <form action="{{ route('admin.teachers.reset-password', $teacher) }}" method="POST"
                                  onsubmit="return confirm('Parolni tiklashni tasdiqlaysizmi? Yangi parol: tug\'ilgan sana (ddmmyyyy)')">
                                @csrf
                                <button type="submit"
                                        class="w-full inline-flex justify-center items-center px-3 py-2 text-xs font-medium rounded-md transition
                                        {{ $teacher->birth_date ? 'bg-orange-500 text-white hover:bg-orange-600' : 'bg-gray-200 text-gray-400 cursor-not-allowed' }}"
                                        {{ !$teacher->birth_date ? 'disabled' : '' }}>
                                    <svg class="w-3.5 h-3.5 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                    </svg>
                                    {{ $teacher->birth_date ? 'Parolni tiklash' : 'Tug\'ilgan sana mavjud emas' }}
                                </button>
                            </form>
                        </div>
                    </div>

                    {{-- Hisob sozlamalari --}}
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden mt-4">
                        <div class="px-4 py-3 border-b border-gray-100">
                            <h4 class="text-sm font-semibold text-gray-900">Hisob sozlamalari</h4>
                        </div>
                        <form action="{{ route('admin.teachers.update', $teacher) }}" method="POST">
                            @csrf
                            @method('PUT')
                            <div class="px-4 py-3 space-y-3">
                                <div>
                                    <label for="login" class="block text-xs font-medium text-gray-600 mb-1">Login</label>
                                    <input type="text" name="login" id="login" value="{{ old('login', $teacher->login) }}"
                                           class="w-full text-sm rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                </div>
                                <div>
                                    <label for="password" class="block text-xs font-medium text-gray-600 mb-1">Yangi parol</label>
                                    <input type="password" name="password" id="password" placeholder="Bo'sh qoldirilsa o'zgarmaydi"
                                           class="w-full text-sm rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                </div>
                                <div>
                                    <label for="status" class="block text-xs font-medium text-gray-600 mb-1">Status</label>
                                    <select name="status" id="status"
                                            class="w-full text-sm rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <option value="1" {{ $teacher->status ? 'selected' : '' }}>Faol</option>
                                        <option value="0" {{ !$teacher->status ? 'selected' : '' }}>Nofaol</option>
                                    </select>
                                </div>
                            </div>
                            <div class="px-4 py-3 bg-gray-50 border-t border-gray-100">
                                <button type="submit"
                                        class="w-full inline-flex justify-center items-center px-3 py-2 bg-gray-800 text-white text-xs font-medium rounded-md hover:bg-gray-900 transition">
                                    Saqlash
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                {{-- O'ng ustun: Rollar --}}
                <div class="lg:col-span-2">
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                        <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                            <div>
                                <h4 class="text-sm font-semibold text-gray-900">Rollarni boshqarish</h4>
                                <p class="text-xs text-gray-500 mt-0.5">Bir nechta rol tanlash mumkin</p>
                            </div>
                        </div>
                        <form action="{{ route('admin.teachers.update-roles', $teacher) }}" method="POST">
                            @csrf
                            @method('PUT')
                            <div class="p-4">
                                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                                    @foreach($roles as $role)
                                        <label class="role-card relative flex items-center p-2.5 rounded-lg border-2 cursor-pointer transition-all duration-150
                                            {{ $teacher->hasRole($role->value) ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200 hover:border-gray-300 hover:bg-gray-50' }}">
                                            <input type="checkbox" name="roles[]" value="{{ $role->value }}"
                                                   class="sr-only"
                                                   {{ $teacher->hasRole($role->value) ? 'checked' : '' }}
                                                   onchange="toggleRole(this)">
                                            <div class="w-7 h-7 rounded flex items-center justify-center flex-shrink-0
                                                {{ $teacher->hasRole($role->value) ? 'bg-indigo-500 text-white' : 'bg-gray-100 text-gray-400' }}"
                                                data-icon>
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                                </svg>
                                            </div>
                                            <span class="ml-2 text-xs font-semibold text-gray-800 truncate">{{ $role->label() }}</span>
                                            <div class="check-indicator ml-auto flex-shrink-0 {{ $teacher->hasRole($role->value) ? '' : 'hidden' }}">
                                                <svg class="w-4 h-4 text-indigo-600" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                                </svg>
                                            </div>
                                        </label>
                                    @endforeach
                                </div>
                            </div>

                            {{-- Dekan uchun fakultet --}}
                            <div id="department-section" class="px-4 pb-3" style="display: none;">
                                <div class="p-3 bg-blue-50 rounded-md border border-blue-100">
                                    <label for="department_hemis_id" class="block text-xs font-medium text-blue-800 mb-1">
                                        Dekan roli uchun fakultet:
                                    </label>
                                    <select name="department_hemis_id" id="department_hemis_id"
                                            class="w-full text-sm rounded-md border-blue-200 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                        <option value="">-- Tanlang --</option>
                                        @foreach($departments as $department)
                                            <option value="{{ $department->department_hemis_id }}"
                                                {{ $teacher->department_hemis_id == $department->department_hemis_id ? 'selected' : '' }}>
                                                {{ $department->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="px-4 py-3 bg-gray-50 border-t border-gray-100 flex justify-end">
                                <button type="submit"
                                        class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-xs font-medium rounded-md hover:bg-indigo-700 transition">
                                    <svg class="w-3.5 h-3.5 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    Rollarni saqlash
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleRole(checkbox) {
            var card = checkbox.closest('.role-card');
            var icon = card.querySelector('[data-icon]');
            var check = card.querySelector('.check-indicator');

            card.classList.toggle('border-indigo-500', checkbox.checked);
            card.classList.toggle('bg-indigo-50', checkbox.checked);
            card.classList.toggle('border-gray-200', !checkbox.checked);
            icon.classList.toggle('bg-indigo-500', checkbox.checked);
            icon.classList.toggle('text-white', checkbox.checked);
            icon.classList.toggle('bg-gray-100', !checkbox.checked);
            icon.classList.toggle('text-gray-400', !checkbox.checked);
            check.classList.toggle('hidden', !checkbox.checked);

            var dekanCheckbox = document.querySelector('input[value="dekan"]');
            var dept = document.getElementById('department-section');
            if (dept && dekanCheckbox) {
                dept.style.display = dekanCheckbox.checked ? 'block' : 'none';
            }
        }

        document.addEventListener('DOMContentLoaded', function () {
            var dekanCheckbox = document.querySelector('input[value="dekan"]');
            var dept = document.getElementById('department-section');
            if (dept && dekanCheckbox) {
                dept.style.display = dekanCheckbox.checked ? 'block' : 'none';
            }
        });
    </script>
</x-app-layout>
