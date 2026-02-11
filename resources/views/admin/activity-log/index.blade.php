<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Faoliyat jurnali
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">

                    {{-- Filtrlar --}}
                    <form method="GET" action="{{ route('admin.activity-log.index') }}" class="mb-6">
                        <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Qidirish</label>
                                <input type="text" name="search" value="{{ request('search') }}"
                                       placeholder="Ism, tavsif, modul..."
                                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Amal turi</label>
                                <select name="action" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                    <option value="">Barchasi</option>
                                    @foreach($actions as $action)
                                        <option value="{{ $action }}" {{ request('action') == $action ? 'selected' : '' }}>
                                            {{ match($action) {
                                                'create' => 'Yaratish',
                                                'update' => 'Tahrirlash',
                                                'delete' => "O'chirish",
                                                'login' => 'Kirish',
                                                'logout' => 'Chiqish',
                                                'export' => 'Eksport',
                                                'import' => 'Import',
                                                'upload' => 'Yuklash',
                                                'grade' => 'Baholash',
                                                default => $action,
                                            } }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Modul</label>
                                <select name="module" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                    <option value="">Barchasi</option>
                                    @foreach($modules as $module)
                                        <option value="{{ $module }}" {{ request('module') == $module ? 'selected' : '' }}>{{ $module }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Guard</label>
                                <select name="guard" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                    <option value="">Barchasi</option>
                                    <option value="web" {{ request('guard') == 'web' ? 'selected' : '' }}>Admin (web)</option>
                                    <option value="teacher" {{ request('guard') == 'teacher' ? 'selected' : '' }}>O'qituvchi</option>
                                    <option value="student" {{ request('guard') == 'student' ? 'selected' : '' }}>Talaba</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Rol</label>
                                <select name="role" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                    <option value="">Barchasi</option>
                                    @foreach($roles as $role)
                                        <option value="{{ $role }}" {{ request('role') == $role ? 'selected' : '' }}>{{ $role }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Boshlanish sanasi</label>
                                <input type="date" name="date_from" value="{{ request('date_from') }}"
                                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Tugash sanasi</label>
                                <input type="date" name="date_to" value="{{ request('date_to') }}"
                                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                            </div>
                            <div class="flex items-end gap-2">
                                <button type="submit"
                                        class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 transition text-sm">
                                    Filtrlash
                                </button>
                                <a href="{{ route('admin.activity-log.index') }}"
                                   class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 transition text-sm">
                                    Tozalash
                                </a>
                            </div>
                        </div>
                    </form>

                    {{-- Natijalar soni --}}
                    <div class="mb-4 text-sm text-gray-600">
                        Jami: <strong>{{ $logs->total() }}</strong> ta yozuv
                    </div>

                    {{-- Jadval --}}
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Vaqt</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Foydalanuvchi</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rol</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amal</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Modul</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tavsif</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">IP</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase"></th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse($logs as $log)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3 whitespace-nowrap text-gray-500">
                                            {{ $log->created_at->format('d.m.Y H:i:s') }}
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <div class="font-medium text-gray-900">{{ $log->user_name ?? '-' }}</div>
                                            <div class="text-xs text-gray-400">{{ $log->guard }}</div>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            @if($log->role)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800">
                                                    {{ $log->role }}
                                                </span>
                                            @else
                                                <span class="text-gray-400">-</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            @php
                                                $colors = [
                                                    'create' => 'bg-green-100 text-green-800',
                                                    'update' => 'bg-blue-100 text-blue-800',
                                                    'delete' => 'bg-red-100 text-red-800',
                                                    'login' => 'bg-indigo-100 text-indigo-800',
                                                    'logout' => 'bg-gray-100 text-gray-800',
                                                    'export' => 'bg-purple-100 text-purple-800',
                                                    'import' => 'bg-yellow-100 text-yellow-800',
                                                    'upload' => 'bg-cyan-100 text-cyan-800',
                                                    'grade' => 'bg-orange-100 text-orange-800',
                                                ];
                                                $colorClass = $colors[$log->action] ?? 'bg-gray-100 text-gray-800';
                                            @endphp
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $colorClass }}">
                                                {{ $log->action_label }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-gray-600">
                                            {{ $log->module }}
                                        </td>
                                        <td class="px-4 py-3 text-gray-600 max-w-xs truncate" title="{{ $log->description }}">
                                            {{ $log->description ?? '-' }}
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-gray-400 text-xs">
                                            {{ $log->ip_address }}
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            @if($log->old_values || $log->new_values)
                                                <a href="{{ route('admin.activity-log.show', $log) }}"
                                                   class="text-indigo-600 hover:text-indigo-900 text-xs">
                                                    Batafsil
                                                </a>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="px-4 py-8 text-center text-gray-500">
                                            Hozircha yozuvlar yo'q
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{-- Pagination --}}
                    <div class="mt-4">
                        {{ $logs->links() }}
                    </div>

                </div>
            </div>
        </div>
    </div>
</x-app-layout>
