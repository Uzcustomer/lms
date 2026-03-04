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

            @if(session('error'))
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    {{ session('error') }}
                </div>
            @endif

            {{-- Statistika card --}}
            <div class="mb-4">
                <button onclick="document.getElementById('reviewerStatsModal').classList.remove('hidden')"
                        class="w-full block bg-blue-50 border-2 border-blue-200 rounded-lg p-4 transition-all duration-200 hover:shadow-md hover:scale-[1.01] hover:border-blue-400 text-left">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <svg class="w-8 h-8 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-blue-600">Statistika</p>
                                <p class="text-xs text-blue-500">Ko'rib chiquvchilar bo'yicha apellyatsiyalar statistikasi</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="text-xs bg-green-100 text-green-700 px-2 py-1 rounded-full font-medium">{{ $stats['approved'] }} qabul qilingan</span>
                            <span class="text-xs bg-red-100 text-red-700 px-2 py-1 rounded-full font-medium">{{ $stats['rejected'] }} rad etilgan</span>
                            <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                            </svg>
                        </div>
                    </div>
                </button>
            </div>

            {{-- Reviewer Statistika Modal --}}
            <div id="reviewerStatsModal" class="hidden fixed inset-0 z-50 overflow-y-auto" x-data="{ openReviewer: null, openName: '', filterStatus: null }">
                <div class="flex items-center justify-center min-h-screen px-4 py-8">
                    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="document.getElementById('reviewerStatsModal').classList.add('hidden')"></div>
                    <div class="relative bg-white rounded-lg shadow-xl w-full z-10 max-h-[90vh] flex flex-col mx-4" @click.stop>
                        <div class="flex items-center justify-between p-4 border-b bg-blue-50 rounded-t-lg flex-shrink-0">
                            <h3 class="text-lg font-semibold text-blue-800">Ko'rib chiquvchilar statistikasi</h3>
                            <button onclick="document.getElementById('reviewerStatsModal').classList.add('hidden')"
                                    class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
                        </div>
                        <div class="flex-1 overflow-y-auto">
                            @if($reviewerStats->isEmpty())
                                <p class="text-gray-500 text-center py-6">Hali hech qanday apellyatsiya ko'rib chiqilmagan.</p>
                            @else
                                <div class="p-4">
                                    <div class="overflow-x-auto">
                                        <table class="min-w-full divide-y divide-gray-200">
                                            <thead class="bg-gray-50">
                                                <tr>
                                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">#</th>
                                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Xodim</th>
                                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Qabul qilgan</th>
                                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Rad etgan</th>
                                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Jami</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-200">
                                                @foreach($reviewerStats as $i => $reviewer)
                                                    <tr class="cursor-pointer transition hover:bg-gray-50"
                                                        @click.stop="openReviewer = {{ $reviewer->reviewed_by }}; openName = '{{ addslashes($reviewer->reviewed_by_name) }}'; filterStatus = null">
                                                        <td class="px-4 py-3 text-sm text-gray-500">{{ $i + 1 }}</td>
                                                        <td class="px-4 py-3 text-sm font-semibold text-gray-900">{{ $reviewer->reviewed_by_name }}</td>
                                                        <td class="px-4 py-3 text-center">
                                                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800">{{ $reviewer->approved_count }}</span>
                                                        </td>
                                                        <td class="px-4 py-3 text-center">
                                                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-800">{{ $reviewer->rejected_count }}</span>
                                                        </td>
                                                        <td class="px-4 py-3 text-center">
                                                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-800">{{ $reviewer->total_count }}</span>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Ikkinchi modal: Reviewer apellyatsiyalari --}}
                <div x-show="openReviewer !== null"
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0"
                     x-transition:enter-end="opacity-100"
                     x-transition:leave="transition ease-in duration-150"
                     x-transition:leave-start="opacity-100"
                     x-transition:leave-end="opacity-0"
                     class="fixed inset-0 overflow-y-auto" style="z-index: 60;"
                     @keydown.escape.window="openReviewer = null">
                    <div class="flex items-center justify-center min-h-screen px-4 py-4">
                        <div class="fixed inset-0 bg-gray-900 bg-opacity-50 transition-opacity" @click="openReviewer = null; filterStatus = null"></div>
                        <div class="relative bg-white rounded-lg shadow-2xl w-full z-10 flex flex-col mx-4" style="height: 600px;">
                            <div class="flex items-center justify-between p-4 border-b bg-indigo-50 rounded-t-lg flex-shrink-0">
                                <h3 class="text-lg font-semibold text-indigo-800" x-text="openName + ' — apellyatsiyalari'"></h3>
                                <div class="flex items-center gap-3">
                                    <div class="flex gap-2">
                                        <button @click="filterStatus = null"
                                                :class="filterStatus === null ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-100'"
                                                class="px-3 py-1.5 text-xs rounded-full font-medium transition border">
                                            Barchasi
                                        </button>
                                        <button @click="filterStatus = 'approved'"
                                                :class="filterStatus === 'approved' ? 'bg-green-600 text-white border-green-600' : 'bg-white text-green-700 hover:bg-green-50 border-green-200'"
                                                class="px-3 py-1.5 text-xs rounded-full font-medium transition border">
                                            Qabul qilingan
                                        </button>
                                        <button @click="filterStatus = 'rejected'"
                                                :class="filterStatus === 'rejected' ? 'bg-red-600 text-white border-red-600' : 'bg-white text-red-700 hover:bg-red-50 border-red-200'"
                                                class="px-3 py-1.5 text-xs rounded-full font-medium transition border">
                                            Rad etilgan
                                        </button>
                                    </div>
                                    <button @click="openReviewer = null; filterStatus = null"
                                            class="text-gray-400 hover:text-gray-600 text-2xl leading-none ml-2">&times;</button>
                                </div>
                            </div>
                            <div class="flex-1 overflow-y-auto">
                                <table class="min-w-full divide-y divide-gray-200 text-sm">
                                    <thead class="bg-gray-50 sticky top-0">
                                        <tr>
                                            <th class="px-4 py-3 text-left font-medium text-gray-500 text-xs">ID</th>
                                            <th class="px-4 py-3 text-left font-medium text-gray-500 text-xs">Talaba</th>
                                            <th class="px-4 py-3 text-left font-medium text-gray-500 text-xs">Fan</th>
                                            <th class="px-4 py-3 text-left font-medium text-gray-500 text-xs">Turi</th>
                                            <th class="px-4 py-3 text-center font-medium text-gray-500 text-xs">Baho</th>
                                            <th class="px-4 py-3 text-center font-medium text-gray-500 text-xs">Holat</th>
                                            <th class="px-4 py-3 text-left font-medium text-gray-500 text-xs">Ko'rib chiqilgan</th>
                                            <th class="px-4 py-3 text-center font-medium text-gray-500 text-xs"></th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        @foreach($reviewerStats as $reviewer)
                                            @foreach(($reviewerAppeals[$reviewer->reviewed_by] ?? collect()) as $ra)
                                                <tr x-show="openReviewer === {{ $reviewer->reviewed_by }} && (filterStatus === null || filterStatus === '{{ $ra->status }}')"
                                                    class="hover:bg-blue-50 transition">
                                                    <td class="px-4 py-3 text-gray-500">{{ $ra->id }}</td>
                                                    <td class="px-4 py-3 font-medium text-gray-900">{{ $ra->student->full_name ?? '-' }}</td>
                                                    <td class="px-4 py-3 text-gray-700">{{ $ra->subject_name }}</td>
                                                    <td class="px-4 py-3 text-gray-500">{{ $ra->training_type_name }}</td>
                                                    <td class="px-4 py-3 text-center">
                                                        <span class="font-bold text-gray-800">{{ $ra->current_grade }}</span>
                                                        @if($ra->new_grade !== null)
                                                            <span class="text-green-600 font-bold">&rarr; {{ $ra->new_grade }}</span>
                                                        @endif
                                                    </td>
                                                    <td class="px-4 py-3 text-center">
                                                        @if($ra->status === 'approved')
                                                            <span class="px-2 py-0.5 rounded-full bg-green-100 text-green-700 font-semibold text-xs">Qabul qilingan</span>
                                                        @else
                                                            <span class="px-2 py-0.5 rounded-full bg-red-100 text-red-700 font-semibold text-xs">Rad etilgan</span>
                                                        @endif
                                                    </td>
                                                    <td class="px-4 py-3 text-gray-500">{{ $ra->reviewed_at ? \Carbon\Carbon::parse($ra->reviewed_at)->format('d.m.Y H:i') : '-' }}</td>
                                                    <td class="px-4 py-3 text-center">
                                                        <a href="{{ route('admin.exam-appeals.show', $ra->id) }}"
                                                           class="text-indigo-600 hover:text-indigo-800 font-medium">Ko'rish</a>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Status cardlar --}}
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                <a href="{{ route('admin.exam-appeals.index', ['status' => 'pending']) }}"
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
                <a href="{{ route('admin.exam-appeals.index', ['status' => 'reviewing']) }}"
                   class="block bg-blue-50 border-2 rounded-lg p-4 transition-all duration-200 hover:shadow-md hover:scale-[1.02] {{ request('status') == 'reviewing' ? 'border-blue-500 shadow-md ring-2 ring-blue-200' : 'border-blue-200 hover:border-blue-400' }}">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="w-8 h-8 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-blue-600">Ko'rib chiqilmoqda</p>
                            <p class="text-2xl font-bold text-blue-800">{{ $stats['reviewing'] }}</p>
                        </div>
                    </div>
                </a>
                <a href="{{ route('admin.exam-appeals.index', ['status' => 'approved']) }}"
                   class="block bg-green-50 border-2 rounded-lg p-4 transition-all duration-200 hover:shadow-md hover:scale-[1.02] {{ request('status') == 'approved' ? 'border-green-500 shadow-md ring-2 ring-green-200' : 'border-green-200 hover:border-green-400' }}">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="w-8 h-8 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-green-600">Qabul qilingan</p>
                            <p class="text-2xl font-bold text-green-800">{{ $stats['approved'] }}</p>
                        </div>
                    </div>
                </a>
                <a href="{{ route('admin.exam-appeals.index', ['status' => 'rejected']) }}"
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
                <form method="GET" action="{{ route('admin.exam-appeals.index') }}" class="flex flex-wrap items-center gap-2">
                    <input type="text" name="search" value="{{ request('search') }}"
                           placeholder="Talaba, fan, o'qituvchi..."
                           class="w-40 lg:w-48 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-xs py-1.5 px-2">
                    <select name="status" class="w-36 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-xs py-1.5 px-2">
                        <option value="">Barcha holatlar</option>
                        <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Kutilmoqda</option>
                        <option value="reviewing" {{ request('status') == 'reviewing' ? 'selected' : '' }}>Ko'rib chiqilmoqda</option>
                        <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>Qabul qilingan</option>
                        <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>Rad etilgan</option>
                    </select>
                    <div class="flex gap-1.5">
                        <button type="submit" class="px-3 py-1.5 bg-indigo-600 text-white text-xs rounded-md hover:bg-indigo-700 transition whitespace-nowrap">
                            Qidirish
                        </button>
                        @if(request()->hasAny(['search', 'status']))
                            <a href="{{ route('admin.exam-appeals.index') }}" class="px-3 py-1.5 bg-gray-200 text-gray-700 text-xs rounded-md hover:bg-gray-300 transition whitespace-nowrap">
                                Tozalash
                            </a>
                        @endif
                    </div>
                </form>
            </div>

            {{-- Jadval --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm overflow-hidden">
                @if($appeals->isEmpty())
                    <div class="text-center py-12">
                        <p class="text-gray-500">Apellyatsiya topilmadi.</p>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Talaba FISH</th>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Fan</th>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Turi</th>
                                    <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Baho</th>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Holat</th>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Ko'rib chiqgan</th>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Amallar</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($appeals as $appeal)
                                    <tr class="{{ $appeal->status === 'pending' ? 'bg-yellow-50 dark:bg-yellow-900/10' : '' }}">
                                        <td class="px-3 py-3 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $appeal->student->full_name ?? '-' }}</div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ $appeal->student->student_id_number ?? '' }}</div>
                                        </td>
                                        <td class="px-3 py-3 whitespace-nowrap">
                                            <div class="text-sm text-gray-900 dark:text-white">{{ $appeal->subject_name }}</div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ $appeal->employee_name }}</div>
                                        </td>
                                        <td class="px-3 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $appeal->training_type_name }}</td>
                                        <td class="px-3 py-3 whitespace-nowrap text-center">
                                            <span class="text-sm font-bold text-gray-800">{{ $appeal->current_grade }}</span>
                                            @if($appeal->new_grade !== null)
                                                <span class="text-green-600 font-bold">&rarr; {{ $appeal->new_grade }}</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-3 whitespace-nowrap">
                                            @if($appeal->status === 'approved' && session('active_role') === 'test_markazi')
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                    Test ga ruxsat etildi
                                                </span>
                                            @else
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                                    bg-{{ $appeal->getStatusColor() }}-100 text-{{ $appeal->getStatusColor() }}-800">
                                                    {{ $appeal->getStatusLabel() }}
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                            @if($appeal->reviewed_by_name)
                                                <div class="font-medium text-gray-900 dark:text-white">{{ $appeal->reviewed_by_name }}</div>
                                                <div class="text-xs text-gray-400">{{ $appeal->reviewed_at ? \Carbon\Carbon::parse($appeal->reviewed_at)->format('d.m.Y H:i') : '' }}</div>
                                            @else
                                                <span class="text-gray-400">-</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-3 whitespace-nowrap text-sm">
                                            <a href="{{ route('admin.exam-appeals.show', $appeal->id) }}"
                                               class="text-indigo-600 hover:text-indigo-900 font-medium">Ko'rish</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700">
                        {{ $appeals->links() }}
                    </div>
                @endif
            </div>

        </div>
    </div>
</x-app-layout>
