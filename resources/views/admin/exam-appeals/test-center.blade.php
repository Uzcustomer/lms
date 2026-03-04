<x-app-layout>
    <div class="p-4 sm:ml-64">
        <div class="mt-14">

            <div class="flex items-center justify-between mb-6">
                <h1 class="text-2xl font-bold text-gray-800">Apellyatsiyalar</h1>
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                    Jami: {{ $appeals->total() }} ta tasdiqlangan
                </span>
            </div>

            {{-- Qidirish --}}
            <div class="bg-white rounded-lg shadow-sm p-3 mb-6">
                <form method="GET" action="{{ route('admin.exam-appeals.index') }}" class="flex flex-wrap items-center gap-2">
                    <input type="text" name="search" value="{{ request('search') }}"
                           placeholder="Talaba ismi, ID, fan nomi..."
                           class="w-64 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm py-2 px-3">
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm rounded-md hover:bg-indigo-700 transition">
                        Qidirish
                    </button>
                    @if(request('search'))
                        <a href="{{ route('admin.exam-appeals.index') }}" class="px-4 py-2 bg-gray-200 text-gray-700 text-sm rounded-md hover:bg-gray-300 transition">
                            Tozalash
                        </a>
                    @endif
                </form>
            </div>

            {{-- Jadval --}}
            <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                @if($appeals->isEmpty())
                    <div class="text-center py-12">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <p class="mt-2 text-gray-500">Tasdiqlangan apellyatsiya topilmadi.</p>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">#</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Talaba</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fan</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Turi</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Baho</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Holat</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tasdiqlangan sana</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($appeals as $index => $appeal)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                            {{ ($appeals->currentPage() - 1) * $appeals->perPage() + $index + 1 }}
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900">{{ $appeal->student->full_name ?? '-' }}</div>
                                            <div class="text-xs text-gray-500">{{ $appeal->student->student_id_number ?? '' }}</div>
                                            <div class="text-xs text-gray-400">{{ $appeal->student->group_name ?? '' }}</div>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <div class="text-sm text-gray-900">{{ $appeal->subject_name }}</div>
                                            <div class="text-xs text-gray-500">{{ $appeal->employee_name }}</div>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">{{ $appeal->training_type_name }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap text-center">
                                            <span class="text-sm font-bold text-gray-800">{{ $appeal->current_grade }}</span>
                                            @if($appeal->new_grade !== null)
                                                <span class="text-green-600 font-bold">&rarr; {{ $appeal->new_grade }}</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-center">
                                            <span class="px-2.5 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                Test ga ruxsat etildi
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                            {{ $appeal->reviewed_at ? \Carbon\Carbon::parse($appeal->reviewed_at)->format('d.m.Y H:i') : '-' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="px-4 py-3 border-t border-gray-200">
                        {{ $appeals->links() }}
                    </div>
                @endif
            </div>

        </div>
    </div>
</x-app-layout>
