<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Tyutorlar ro'yxati</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-4 gap-3">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800">Guruhga biriktirilgan tyutorlar</h3>
                        <p class="text-sm text-gray-500 mt-1">Har bir tyutor bo'yicha biriktirilgan guruhlar ro'yxatini Excel formatida yuklab olish mumkin.</p>
                    </div>
                    <form method="GET" action="{{ route('admin.tutors.index') }}" class="flex gap-2">
                        <input type="text" name="search" value="{{ request('search') }}"
                               placeholder="F.I.O yoki kafedra..."
                               class="w-full sm:w-72 border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"/>
                        <button type="submit"
                                class="px-4 py-2 bg-blue-600 text-white text-sm font-semibold rounded-lg hover:bg-blue-700 transition">
                            Qidirish
                        </button>
                        @if(request('search'))
                            <a href="{{ route('admin.tutors.index') }}"
                               class="px-3 py-2 bg-gray-200 text-gray-700 text-sm rounded-lg hover:bg-gray-300 transition">
                                Tozalash
                            </a>
                        @endif
                    </form>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">№</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">F.I.O</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Kafedra</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Guruhlar</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Soni</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Excel</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($tutors as $tutor)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 text-sm text-gray-500">
                                        {{ ($tutors->currentPage() - 1) * $tutors->perPage() + $loop->iteration }}
                                    </td>
                                    <td class="px-4 py-3 text-sm font-medium text-gray-900">
                                        {{ $tutor->full_name }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600">
                                        {{ $tutor->department ?? '-' }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600">
                                        @if($tutor->groups->isEmpty())
                                            <span class="text-gray-400">-</span>
                                        @else
                                            <div class="flex flex-wrap gap-1">
                                                @foreach($tutor->groups as $group)
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium bg-green-50 text-green-700 border border-green-200">
                                                        {{ $group->name }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-sm text-center">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-blue-50 text-blue-700">
                                            {{ $tutor->groups->count() }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <a href="{{ route('admin.tutors.export', $tutor->id) }}"
                                           class="inline-flex items-center gap-1 px-3 py-1.5 bg-green-600 text-white text-xs font-semibold rounded-md hover:bg-green-700 transition">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/>
                                            </svg>
                                            Yuklab olish
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500">
                                        Tyutorlar topilmadi.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $tutors->withQueryString()->links() }}
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
