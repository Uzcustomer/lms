<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    {{ __('Jurnal') }} - {{ $group->name }}
                </h2>
                <p class="mt-1 text-sm text-gray-500">{{ $subject->subject_name }}</p>
            </div>
            <a href="{{ route('admin.journal.index') }}" class="inline-flex items-center px-4 py-2 text-xs font-semibold tracking-widest text-gray-700 uppercase transition bg-gray-200 border border-transparent rounded-md hover:bg-gray-300">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                Orqaga
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">
            <!-- Info Cards -->
            <div class="grid grid-cols-1 gap-4 mb-6 md:grid-cols-2 lg:grid-cols-4">
                <div class="p-4 bg-white rounded-lg shadow">
                    <div class="text-sm font-medium text-gray-500">Guruh</div>
                    <div class="mt-1 text-lg font-semibold text-gray-900">{{ $group->name }}</div>
                </div>
                <div class="p-4 bg-white rounded-lg shadow">
                    <div class="text-sm font-medium text-gray-500">Fan</div>
                    <div class="mt-1 text-lg font-semibold text-gray-900">{{ $subject->subject_name }}</div>
                </div>
                <div class="p-4 bg-white rounded-lg shadow">
                    <div class="text-sm font-medium text-gray-500">Semestr</div>
                    <div class="mt-1 text-lg font-semibold text-gray-900">{{ $semester->name ?? $subject->semester_name }}</div>
                </div>
                <div class="p-4 bg-white rounded-lg shadow">
                    <div class="text-sm font-medium text-gray-500">O'quv yili</div>
                    <div class="mt-1 text-lg font-semibold text-gray-900">{{ $curriculum->education_year_name ?? '-' }}</div>
                </div>
            </div>

            <!-- Students Table -->
            <div class="overflow-hidden bg-white shadow-xl sm:rounded-lg">
                <div class="p-4 border-b border-gray-200 bg-gray-50">
                    <h3 class="text-lg font-medium text-gray-900">Talabalar ro'yxati</h3>
                </div>

                <div class="overflow-x-auto">
                    @if($students->isEmpty())
                        <div class="p-6 text-center text-gray-500">
                            <p>Bu guruhda talabalar topilmadi.</p>
                        </div>
                    @else
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-xs font-medium tracking-wider text-left text-gray-500 uppercase">
                                        #
                                    </th>
                                    <th class="px-4 py-3 text-xs font-medium tracking-wider text-left text-gray-500 uppercase">
                                        F.I.O
                                    </th>
                                    <th class="px-4 py-3 text-xs font-medium tracking-wider text-left text-gray-500 uppercase">
                                        Talaba ID
                                    </th>
                                    <th class="px-4 py-3 text-xs font-medium tracking-wider text-center text-gray-500 uppercase">
                                        JB (Joriy)
                                    </th>
                                    <th class="px-4 py-3 text-xs font-medium tracking-wider text-center text-gray-500 uppercase">
                                        MT (Mustaqil)
                                    </th>
                                    <th class="px-4 py-3 text-xs font-medium tracking-wider text-center text-gray-500 uppercase">
                                        ON (Oraliq)
                                    </th>
                                    <th class="px-4 py-3 text-xs font-medium tracking-wider text-center text-gray-500 uppercase">
                                        OSKI
                                    </th>
                                    <th class="px-4 py-3 text-xs font-medium tracking-wider text-center text-gray-500 uppercase">
                                        Test
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach ($students as $index => $student)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-4 text-sm text-gray-900 whitespace-nowrap">
                                            {{ $index + 1 }}
                                        </td>
                                        <td class="px-4 py-4 text-sm font-medium text-gray-900 whitespace-nowrap">
                                            {{ $student->full_name }}
                                        </td>
                                        <td class="px-4 py-4 text-sm text-gray-500 whitespace-nowrap">
                                            {{ $student->student_id_number }}
                                        </td>
                                        <td class="px-4 py-4 text-sm text-center whitespace-nowrap">
                                            @if($student->jb_average !== null)
                                                <span class="px-2 py-1 text-xs font-semibold rounded {{ $student->jb_average >= 60 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                                    {{ round($student->jb_average, 1) }}
                                                </span>
                                            @else
                                                <span class="text-gray-400">-</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-4 text-sm text-center whitespace-nowrap">
                                            @if($student->mt_average !== null)
                                                <span class="px-2 py-1 text-xs font-semibold rounded {{ $student->mt_average >= 60 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                                    {{ round($student->mt_average, 1) }}
                                                </span>
                                            @else
                                                <span class="text-gray-400">-</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-4 text-sm text-center whitespace-nowrap">
                                            @if($student->on_average !== null)
                                                <span class="px-2 py-1 text-xs font-semibold rounded {{ $student->on_average >= 60 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                                    {{ round($student->on_average, 1) }}
                                                </span>
                                            @else
                                                <span class="text-gray-400">-</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-4 text-sm text-center whitespace-nowrap">
                                            @if($student->oski_average !== null)
                                                <span class="px-2 py-1 text-xs font-semibold rounded {{ $student->oski_average >= 60 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                                    {{ round($student->oski_average, 1) }}
                                                </span>
                                            @else
                                                <span class="text-gray-400">-</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-4 text-sm text-center whitespace-nowrap">
                                            @if($student->test_average !== null)
                                                <span class="px-2 py-1 text-xs font-semibold rounded {{ $student->test_average >= 60 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                                    {{ round($student->test_average, 1) }}
                                                </span>
                                            @else
                                                <span class="text-gray-400">-</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
