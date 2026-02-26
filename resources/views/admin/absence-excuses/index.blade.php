<x-app-layout>
    <div class="p-4 sm:ml-64">
        <div class="mt-14">

            {{-- Sarlavha --}}
            <div class="flex items-center justify-between mb-6">
                <h1 class="text-2xl font-bold text-gray-800 dark:text-white">Sababli dars qoldirish arizalari</h1>
            </div>

            @if(session('success'))
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    {{ session('error') }}
                </div>
            @endif

            {{-- Statistika --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <a href="{{ route('admin.absence-excuses.index', ['status' => 'pending']) }}"
                   class="block bg-yellow-50 border-2 rounded-lg p-4 transition-all duration-200 hover:shadow-md hover:scale-[1.02] {{ request('status') == 'pending' ? 'border-yellow-500 shadow-md ring-2 ring-yellow-200' : 'border-yellow-200 hover:border-yellow-400' }}">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="w-8 h-8 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-yellow-600">Kutilmoqda</p>
                            <p class="text-2xl font-bold text-yellow-800">{{ $stats['pending'] }}</p>
                        </div>
                    </div>
                </a>
                <a href="{{ route('admin.absence-excuses.index', ['status' => 'approved']) }}"
                   class="block bg-green-50 border-2 rounded-lg p-4 transition-all duration-200 hover:shadow-md hover:scale-[1.02] {{ request('status') == 'approved' ? 'border-green-500 shadow-md ring-2 ring-green-200' : 'border-green-200 hover:border-green-400' }}">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="w-8 h-8 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-green-600">Tasdiqlangan</p>
                            <p class="text-2xl font-bold text-green-800">{{ $stats['approved'] }}</p>
                        </div>
                    </div>
                </a>
                <a href="{{ route('admin.absence-excuses.index', ['status' => 'rejected']) }}"
                   class="block bg-red-50 border-2 rounded-lg p-4 transition-all duration-200 hover:shadow-md hover:scale-[1.02] {{ request('status') == 'rejected' ? 'border-red-500 shadow-md ring-2 ring-red-200' : 'border-red-200 hover:border-red-400' }}">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="w-8 h-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-red-600">Rad etilgan</p>
                            <p class="text-2xl font-bold text-red-800">{{ $stats['rejected'] }}</p>
                        </div>
                    </div>
                </a>
            </div>

            {{-- Filterlar --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-3 mb-6">
                <form method="GET" action="{{ route('admin.absence-excuses.index') }}" class="flex flex-wrap items-center gap-2">
                    <input type="number" name="student_id" value="{{ request('student_id') }}"
                           placeholder="Student ID: 368"
                           class="w-36 lg:w-40 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-xs py-1.5 px-2">
                    <input type="text" name="search" value="{{ request('search') }}"
                           placeholder="Ism, HEMIS ID, guruh..."
                           class="w-40 lg:w-48 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-xs py-1.5 px-2">
                    <select name="status" class="w-32 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-xs py-1.5 px-2">
                        <option value="">Barcha holatlar</option>
                        <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Kutilmoqda</option>
                        <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>Tasdiqlangan</option>
                        <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>Rad etilgan</option>
                    </select>
                    <select name="reason" class="w-36 lg:w-44 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-xs py-1.5 px-2">
                        <option value="">Barcha sabablar</option>
                        @foreach($reasons as $key => $label)
                            <option value="{{ $key }}" {{ request('reason') == $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    <input type="date" name="date_from" value="{{ request('date_from') }}"
                           class="w-32 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-xs py-1.5 px-2">
                    <input type="date" name="date_to" value="{{ request('date_to') }}"
                           class="w-32 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-xs py-1.5 px-2">
                    <div class="flex gap-1.5">
                        <button type="submit" class="px-3 py-1.5 bg-indigo-600 text-white text-xs rounded-md hover:bg-indigo-700 transition whitespace-nowrap">
                            Qidirish
                        </button>
                        <a href="{{ route('admin.absence-excuses.index') }}" class="px-3 py-1.5 bg-gray-200 text-gray-700 text-xs rounded-md hover:bg-gray-300 transition whitespace-nowrap">
                            Tozalash
                        </a>
                    </div>
                </form>
            </div>

            {{-- Jadval --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm overflow-hidden">
                @if($excuses->isEmpty())
                    <div class="text-center py-12">
                        <p class="text-gray-500">Ariza topilmadi.</p>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">#</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Talaba</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Guruh</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Sabab</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Sanalar</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Holat</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Yuborilgan</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Amallar</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($excuses as $excuse)
                                    <tr class="{{ $excuse->isPending() ? 'bg-yellow-50 dark:bg-yellow-900/10' : '' }}">
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $excuse->id }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $excuse->student_full_name }}</div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ $excuse->student_hemis_id }}</div>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $excuse->group_name }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-white">{{ $excuse->reason_label }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                            {{ $excuse->start_date->format('d.m.Y') }} - {{ $excuse->end_date->format('d.m.Y') }}
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                                bg-{{ $excuse->status_color }}-100 text-{{ $excuse->status_color }}-800">
                                                {{ $excuse->status_label }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                            {{ $excuse->created_at->format('d.m.Y H:i') }}
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm">
                                            <a href="{{ route('admin.absence-excuses.show', $excuse->id) }}"
                                               class="text-indigo-600 hover:text-indigo-900 font-medium">Ko'rish</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700">
                        {{ $excuses->links() }}
                    </div>
                @endif
            </div>

        </div>
    </div>
</x-app-layout>
