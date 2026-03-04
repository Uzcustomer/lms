<x-app-layout>
    <div class="p-4 sm:ml-64">
        <div class="mt-14">

            {{-- Sarlavha --}}
            <div class="flex items-center justify-between mb-6">
                <h1 class="text-2xl font-bold text-gray-800 dark:text-white">Apellyatsiyalar</h1>
            </div>

            @if(session('success'))
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    {{ session('success') }}
                </div>
            @endif

            {{-- Statistika --}}
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-5">
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 text-center">
                    <p class="text-2xl font-bold text-yellow-600">{{ $stats['pending'] }}</p>
                    <p class="text-xs text-yellow-700 font-medium">Kutilmoqda</p>
                </div>
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 text-center">
                    <p class="text-2xl font-bold text-blue-600">{{ $stats['reviewing'] }}</p>
                    <p class="text-xs text-blue-700 font-medium">Ko'rib chiqilmoqda</p>
                </div>
                <div class="bg-green-50 border border-green-200 rounded-lg p-3 text-center">
                    <p class="text-2xl font-bold text-green-600">{{ $stats['approved'] }}</p>
                    <p class="text-xs text-green-700 font-medium">Qabul qilingan</p>
                </div>
                <div class="bg-red-50 border border-red-200 rounded-lg p-3 text-center">
                    <p class="text-2xl font-bold text-red-600">{{ $stats['rejected'] }}</p>
                    <p class="text-xs text-red-700 font-medium">Rad etilgan</p>
                </div>
            </div>

            {{-- Filtrlar --}}
            <div class="bg-white rounded-lg shadow-sm border p-4 mb-5">
                <form method="GET" action="{{ route('admin.exam-appeals.index') }}" class="flex flex-wrap items-end gap-3">
                    <div class="flex-1 min-w-[200px]">
                        <label class="block text-xs font-medium text-gray-600 mb-1">Qidiruv</label>
                        <input type="text" name="search" value="{{ request('search') }}"
                               placeholder="Talaba, fan yoki o'qituvchi..."
                               class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div class="min-w-[150px]">
                        <label class="block text-xs font-medium text-gray-600 mb-1">Holati</label>
                        <select name="status" class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Barchasi</option>
                            <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Kutilmoqda</option>
                            <option value="reviewing" {{ request('status') == 'reviewing' ? 'selected' : '' }}>Ko'rib chiqilmoqda</option>
                            <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>Qabul qilingan</option>
                            <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>Rad etilgan</option>
                        </select>
                    </div>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                        Filtr
                    </button>
                    @if(request()->hasAny(['search', 'status']))
                        <a href="{{ route('admin.exam-appeals.index') }}" class="px-4 py-2 bg-gray-200 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-300 transition">
                            Tozalash
                        </a>
                    @endif
                </form>
            </div>

            {{-- Jadval --}}
            <div class="bg-white rounded-lg shadow-sm border overflow-hidden">
                @if($appeals->isEmpty())
                    <div class="p-8 text-center text-gray-500">
                        <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0-10.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285zm0 13.036h.008v.008H12v-.008z" />
                        </svg>
                        <p class="text-sm">Hozircha apellyatsiya arizalari yo'q</p>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">#</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Talaba</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Fan</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Turi</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Baho</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Holat</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Sana</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Amal</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($appeals as $appeal)
                                    <tr class="hover:bg-gray-50 transition {{ $appeal->status === 'pending' ? 'bg-yellow-50/40' : '' }}">
                                        <td class="px-4 py-3 text-sm text-gray-500">{{ $appeal->id }}</td>
                                        <td class="px-4 py-3">
                                            <div class="text-sm font-medium text-gray-800">{{ $appeal->student->full_name ?? '-' }}</div>
                                            <div class="text-xs text-gray-500">{{ $appeal->student->student_id_number ?? '' }}</div>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="text-sm text-gray-800">{{ $appeal->subject_name }}</div>
                                            <div class="text-xs text-gray-500">{{ $appeal->employee_name }}</div>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-600">{{ $appeal->training_type_name }}</td>
                                        <td class="px-4 py-3 text-center">
                                            <span class="text-sm font-bold text-gray-800">{{ $appeal->current_grade }}</span>
                                            @if($appeal->new_grade !== null)
                                                <span class="text-green-600 font-bold">&rarr; {{ $appeal->new_grade }}</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold
                                                bg-{{ $appeal->getStatusColor() }}-100 text-{{ $appeal->getStatusColor() }}-700">
                                                <svg class="mr-1 h-1.5 w-1.5 fill-current" viewBox="0 0 8 8"><circle cx="4" cy="4" r="3"/></svg>
                                                {{ $appeal->getStatusLabel() }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-center text-xs text-gray-500">
                                            {{ $appeal->created_at->format('d.m.Y') }}
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <a href="{{ route('admin.exam-appeals.show', $appeal->id) }}"
                                               class="inline-flex items-center px-3 py-1.5 bg-indigo-50 text-indigo-600 text-xs font-medium rounded-lg hover:bg-indigo-100 transition">
                                                Ko'rish
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="px-4 py-3 border-t">
                        {{ $appeals->links() }}
                    </div>
                @endif
            </div>

        </div>
    </div>
</x-app-layout>
