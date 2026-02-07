<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Xodimlar') }}
        </h2>
    </x-slot>

    @if (session('error'))
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 mt-4">
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                <span class="block sm:inline">{{ session('error') }}</span>
            </div>
        </div>
    @endif

    @if (session('success'))
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 mt-4">
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                <span class="block sm:inline">{{ session('success') }}</span>
            </div>
        </div>
    @endif

    <div class="py-4">
        <div class="w-full px-4 sm:px-6">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-4 bg-white border-b border-gray-200">

                    {{-- Qidirish va filtrlar --}}
                    <form action="{{ route('admin.teachers.index') }}" method="GET" class="mb-3">
                        <div class="flex flex-wrap items-end gap-2">
                            <div class="flex-1" style="min-width: 200px;">
                                <label class="block text-xs text-gray-500 mb-1">Qidirish</label>
                                <input type="text" name="search" value="{{ request('search') }}" placeholder="Ism, ID..."
                                       class="w-full text-sm rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                            </div>
                            <div style="min-width: 180px;">
                                <label class="block text-xs text-gray-500 mb-1">Kafedra</label>
                                <select name="department" class="w-full text-sm rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                    <option value="">Barchasi</option>
                                    @foreach($departments as $dept)
                                        <option value="{{ $dept }}" {{ request('department') == $dept ? 'selected' : '' }}>{{ $dept }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div style="min-width: 140px;">
                                <label class="block text-xs text-gray-500 mb-1">Lavozim</label>
                                <select name="staff_position" class="w-full text-sm rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                    <option value="">Barchasi</option>
                                    @foreach($positions as $pos)
                                        <option value="{{ $pos }}" {{ request('staff_position') == $pos ? 'selected' : '' }}>{{ $pos }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div style="min-width: 140px;">
                                <label class="block text-xs text-gray-500 mb-1">Rol</label>
                                <select name="role" class="w-full text-sm rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                    <option value="">Barchasi</option>
                                    @foreach($activeRoles as $roleName)
                                        <option value="{{ $roleName }}" {{ request('role') == $roleName ? 'selected' : '' }}>
                                            {{ \App\Enums\ProjectRole::tryFrom($roleName)?->label() ?? $roleName }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div style="min-width: 100px;">
                                <label class="block text-xs text-gray-500 mb-1">Status</label>
                                <select name="status" class="w-full text-sm rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                    <option value="">Barchasi</option>
                                    <option value="1" {{ request('status') === '1' ? 'selected' : '' }}>Faol</option>
                                    <option value="0" {{ request('status') === '0' ? 'selected' : '' }}>Nofaol</option>
                                </select>
                            </div>
                            <div style="min-width: 100px;">
                                <label class="block text-xs text-gray-500 mb-1">Holati</label>
                                <select name="is_active" class="w-full text-sm rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                    <option value="1" {{ request('is_active', '1') === '1' ? 'selected' : '' }}>Aktiv</option>
                                    <option value="0" {{ request('is_active') === '0' ? 'selected' : '' }}>Noaktiv</option>
                                    <option value="" {{ request()->has('is_active') && request('is_active') === '' ? 'selected' : '' }}>Barchasi</option>
                                </select>
                            </div>
                            <div class="flex gap-1">
                                <button type="submit" class="px-3 py-2 text-sm bg-indigo-500 text-white rounded-md hover:bg-indigo-600 transition">
                                    <i class="fas fa-search mr-1"></i>Filtr
                                </button>
                                @if(request()->hasAny(['search', 'department', 'staff_position', 'role', 'status', 'is_active']))
                                    <a href="{{ route('admin.teachers.index') }}" class="px-3 py-2 text-sm bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 transition">
                                        Tozalash
                                    </a>
                                @endif
                            </div>
                        </div>
                    </form>

                    <div class="overflow-x-auto">
                        <table class="w-full divide-y divide-gray-200 table-fixed">
                            <thead class="bg-gray-50">
                            <tr>
                                <th class="w-1/4 px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Xodim</th>
                                <th class="w-1/4 px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Kafedra</th>
                                <th class="w-1/8 px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Lavozim</th>
                                <th class="w-1/6 px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Rollar</th>
                                <th class="w-16 px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="w-20 px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Amallar</th>
                            </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($teachers as $teacher)
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="px-3 py-2">
                                        <div class="flex items-center min-w-0">
                                            <div class="flex-shrink-0 h-8 w-8">
                                                @if($teacher->image)
                                                    <img class="h-8 w-8 rounded-full object-cover" src="{{ $teacher->image }}" alt="">
                                                @else
                                                    <div class="h-8 w-8 rounded-full bg-indigo-100 flex items-center justify-center">
                                                        <span class="text-indigo-700 font-semibold text-xs">{{ mb_substr($teacher->first_name ?? '', 0, 1) }}{{ mb_substr($teacher->second_name ?? '', 0, 1) }}</span>
                                                    </div>
                                                @endif
                                            </div>
                                            <div class="ml-2 min-w-0">
                                                <div class="text-xs font-medium text-gray-900 truncate">{{ $teacher->full_name }}</div>
                                                <div class="text-xs text-gray-400">{{ $teacher->employee_id_number }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-3 py-2 text-xs text-gray-600 truncate">{{ $teacher->department }}</td>
                                    <td class="px-3 py-2 text-xs text-gray-600 truncate">{{ $teacher->staff_position }}</td>
                                    <td class="px-3 py-2">
                                        <div class="flex flex-wrap gap-0.5">
                                            @forelse($teacher->roles as $role)
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-indigo-100 text-indigo-800">
                                                    {{ \App\Enums\ProjectRole::tryFrom($role->name)?->label() ?? $role->name }}
                                                </span>
                                            @empty
                                                <span class="text-xs text-gray-400">-</span>
                                            @endforelse
                                        </div>
                                    </td>
                                    <td class="px-3 py-2">
                                        <span class="px-1.5 py-0.5 inline-flex text-xs font-semibold rounded {{ $teacher->status ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                            {{ $teacher->status ? 'Faol' : 'Nofaol' }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2">
                                        <a href="{{ route('admin.teachers.show', $teacher) }}"
                                           class="inline-flex items-center px-2 py-1 text-xs bg-indigo-50 text-indigo-700 rounded hover:bg-indigo-100 transition">
                                            <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                            </svg>
                                            Profil
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">
                        {{ $teachers->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
