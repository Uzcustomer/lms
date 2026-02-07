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

    @if ($errors->any())
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 mt-4">
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                @foreach ($errors->all() as $error)
                    <span class="block sm:inline">{{ $error }}</span>
                @endforeach
            </div>
        </div>
    @endif

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            {{-- Orqaga qaytish --}}
            <div class="mb-6">
                <a href="{{ route('admin.teachers.index') }}" class="inline-flex items-center text-sm text-gray-600 hover:text-gray-900 transition">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    Xodimlar ro'yxatiga qaytish
                </a>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                {{-- Chap ustun: Profil kartasi --}}
                <div class="lg:col-span-1">
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                        {{-- Profil boshi --}}
                        <div class="bg-gradient-to-br from-indigo-500 to-blue-600 px-6 py-8 text-center">
                            @if($teacher->image)
                                <img src="{{ $teacher->image }}" alt="{{ $teacher->full_name }}"
                                     class="w-24 h-24 rounded-full mx-auto border-4 border-white/30 object-cover shadow-lg">
                            @else
                                <div class="w-24 h-24 rounded-full mx-auto border-4 border-white/30 bg-white/20 flex items-center justify-center shadow-lg">
                                    <span class="text-3xl font-bold text-white">{{ mb_substr($teacher->first_name ?? '', 0, 1) }}{{ mb_substr($teacher->second_name ?? '', 0, 1) }}</span>
                                </div>
                            @endif
                            <h3 class="mt-4 text-lg font-bold text-white">{{ $teacher->full_name }}</h3>
                            <p class="text-indigo-100 text-sm mt-1">{{ $teacher->staff_position ?? 'Lavozim ko\'rsatilmagan' }}</p>
                        </div>

                        {{-- Ma'lumotlar --}}
                        <div class="p-6 space-y-4">
                            <div class="flex items-center text-sm">
                                <svg class="w-5 h-5 text-gray-400 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"/>
                                </svg>
                                <div>
                                    <span class="text-gray-500">ID raqami</span>
                                    <p class="font-medium text-gray-900">{{ $teacher->employee_id_number }}</p>
                                </div>
                            </div>

                            <div class="flex items-center text-sm">
                                <svg class="w-5 h-5 text-gray-400 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                </svg>
                                <div>
                                    <span class="text-gray-500">Kafedra</span>
                                    <p class="font-medium text-gray-900">{{ $teacher->department ?? '-' }}</p>
                                </div>
                            </div>

                            <div class="flex items-center text-sm">
                                <svg class="w-5 h-5 text-gray-400 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                                <div>
                                    <span class="text-gray-500">Jinsi</span>
                                    <p class="font-medium text-gray-900">{{ $teacher->gender ?? '-' }}</p>
                                </div>
                            </div>

                            @if($teacher->birth_date)
                            <div class="flex items-center text-sm">
                                <svg class="w-5 h-5 text-gray-400 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                                <div>
                                    <span class="text-gray-500">Tug'ilgan sana</span>
                                    <p class="font-medium text-gray-900">{{ $teacher->birth_date }}</p>
                                </div>
                            </div>
                            @endif

                            <div class="flex items-center text-sm">
                                <svg class="w-5 h-5 text-gray-400 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                </svg>
                                <div>
                                    <span class="text-gray-500">Ish turi</span>
                                    <p class="font-medium text-gray-900">{{ $teacher->employment_form ?? '-' }}</p>
                                </div>
                            </div>

                            <div class="flex items-center text-sm">
                                <svg class="w-5 h-5 text-gray-400 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                </svg>
                                <div>
                                    <span class="text-gray-500">Holati</span>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold {{ $teacher->status ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                        {{ $teacher->status ? 'Faol' : 'Nofaol' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- O'ng ustun: Rollar va sozlamalar --}}
                <div class="lg:col-span-2 space-y-6">

                    {{-- Rollar bo'limi --}}
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-100">
                            <h4 class="text-lg font-semibold text-gray-900 flex items-center">
                                <svg class="w-5 h-5 text-indigo-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                </svg>
                                Rollarni boshqarish
                            </h4>
                            <p class="text-sm text-gray-500 mt-1">Xodimga tegishli rollarni belgilang. Bir nechta rol tanlash mumkin.</p>
                        </div>
                        <form action="{{ route('admin.teachers.update-roles', $teacher) }}" method="POST">
                            @csrf
                            @method('PUT')
                            <div class="p-6">
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    @foreach($roles as $role)
                                        <label class="role-card relative flex items-center p-4 rounded-lg border-2 cursor-pointer transition-all duration-200
                                            {{ $teacher->hasRole($role->value) ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200 hover:border-gray-300 hover:bg-gray-50' }}">
                                            <input type="checkbox" name="roles[]" value="{{ $role->value }}"
                                                   class="sr-only peer"
                                                   {{ $teacher->hasRole($role->value) ? 'checked' : '' }}
                                                   onchange="this.closest('.role-card').classList.toggle('border-indigo-500', this.checked);
                                                             this.closest('.role-card').classList.toggle('bg-indigo-50', this.checked);
                                                             this.closest('.role-card').classList.toggle('border-gray-200', !this.checked);">
                                            <div class="flex items-center flex-1">
                                                <div class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0
                                                    {{ $teacher->hasRole($role->value) ? 'bg-indigo-500 text-white' : 'bg-gray-100 text-gray-500' }}"
                                                    id="icon-{{ $role->value }}">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        @if($role === \App\Enums\ProjectRole::DEAN)
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                                        @elseif($role === \App\Enums\ProjectRole::TEACHER)
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                                                        @elseif($role === \App\Enums\ProjectRole::DEPARTMENT_HEAD)
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                                                        @elseif($role === \App\Enums\ProjectRole::TUTOR)
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                                                        @else
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                                        @endif
                                                    </svg>
                                                </div>
                                                <div class="ml-3">
                                                    <span class="text-sm font-semibold text-gray-900">{{ $role->label() }}</span>
                                                    <p class="text-xs text-gray-500">{{ $role->value }}</p>
                                                </div>
                                            </div>
                                            <div class="check-indicator ml-2 flex-shrink-0 {{ $teacher->hasRole($role->value) ? '' : 'hidden' }}">
                                                <svg class="w-5 h-5 text-indigo-600" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                                </svg>
                                            </div>
                                        </label>
                                    @endforeach
                                </div>
                            </div>

                            {{-- Dekan uchun fakultet tanlash --}}
                            <div id="department-section" class="px-6 pb-4" style="display: none;">
                                <div class="p-4 bg-blue-50 rounded-lg border border-blue-100">
                                    <label for="department_hemis_id" class="block text-sm font-medium text-blue-800 mb-2">
                                        Dekan roli uchun fakultetni tanlang:
                                    </label>
                                    <select name="department_hemis_id" id="department_hemis_id"
                                            class="w-full rounded-md border-blue-200 shadow-sm focus:border-blue-500 focus:ring-blue-500">
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

                            <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex justify-end">
                                <button type="submit"
                                        class="inline-flex items-center px-5 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition shadow-sm">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    Rollarni saqlash
                                </button>
                            </div>
                        </form>
                    </div>

                    {{-- Hisob sozlamalari --}}
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-100">
                            <h4 class="text-lg font-semibold text-gray-900 flex items-center">
                                <svg class="w-5 h-5 text-gray-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                Hisob sozlamalari
                            </h4>
                        </div>
                        <form action="{{ route('admin.teachers.update', $teacher) }}" method="POST">
                            @csrf
                            @method('PUT')
                            <div class="p-6 space-y-4">
                                <div>
                                    <label for="login" class="block text-sm font-medium text-gray-700 mb-1">Login</label>
                                    <input type="text" name="login" id="login" value="{{ old('login', $teacher->login) }}"
                                           class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                </div>
                                <div>
                                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Yangi parol</label>
                                    <input type="password" name="password" id="password" placeholder="Bo'sh qoldirilsa o'zgarmaydi"
                                           class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <p class="text-xs text-gray-500 mt-1">Kamida 6 ta belgi</p>
                                </div>
                                <div>
                                    <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                                    <select name="status" id="status"
                                            class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <option value="1" {{ $teacher->status ? 'selected' : '' }}>Faol</option>
                                        <option value="0" {{ !$teacher->status ? 'selected' : '' }}>Nofaol</option>
                                    </select>
                                </div>
                            </div>
                            <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex justify-end">
                                <button type="submit"
                                        class="inline-flex items-center px-5 py-2.5 bg-gray-800 text-white text-sm font-medium rounded-lg hover:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition shadow-sm">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/>
                                    </svg>
                                    Saqlash
                                </button>
                            </div>
                        </form>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const roleCards = document.querySelectorAll('.role-card input[type="checkbox"]');
            const departmentSection = document.getElementById('department-section');

            function updateUI() {
                // Dekan bo'limini ko'rsatish/yashirish
                const dekanChecked = document.querySelector('input[value="dekan"]');
                if (departmentSection && dekanChecked) {
                    departmentSection.style.display = dekanChecked.checked ? 'block' : 'none';
                }
            }

            roleCards.forEach(function (checkbox) {
                checkbox.addEventListener('change', function () {
                    const card = this.closest('.role-card');
                    const icon = card.querySelector('[id^="icon-"]');
                    const check = card.querySelector('.check-indicator');

                    if (this.checked) {
                        icon.classList.remove('bg-gray-100', 'text-gray-500');
                        icon.classList.add('bg-indigo-500', 'text-white');
                        check.classList.remove('hidden');
                    } else {
                        icon.classList.add('bg-gray-100', 'text-gray-500');
                        icon.classList.remove('bg-indigo-500', 'text-white');
                        check.classList.add('hidden');
                    }

                    updateUI();
                });
            });

            updateUI();
        });
    </script>
</x-app-layout>
