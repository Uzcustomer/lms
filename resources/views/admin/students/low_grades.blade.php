<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Student Grades (Below 60)') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">

                    <!-- Table for Grades Below 60 -->
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                        <tr>
                            <th class="px-6 py-3 bg-gray-50">Subject</th>
                            <th class="px-6 py-3 bg-gray-50">Training Type</th>
                            <th class="px-6 py-3 bg-gray-50">Teacher</th>
                            <th class="px-6 py-3 bg-gray-50">Grade</th>
                            <th class="px-6 py-3 bg-gray-50">Lesson Date</th>
                        </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($grades as $grade)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">{{ $grade['subject']['name'] }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">{{ $grade['trainingType']['name'] }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">{{ $grade['employee']['name'] }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">{{ $grade['grade'] }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">{{ date('d-m-Y', $grade['lesson_date']) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>

                    <!-- Pagination -->
                    <div class="mt-4">
                        @if ($pagination['pageCount'] > 1)
                            <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                                <a href="{{ request()->fullUrlWithQuery(['page' => 1]) }}"
                                   class="px-4 py-2 bg-white border border-gray-300 text-sm font-medium text-gray-500 hover:bg-gray-50">
                                    First
                                </a>
                                @if($pagination['page'] > 1)
                                    <a href="{{ request()->fullUrlWithQuery(['page' => $pagination['page'] - 1]) }}"
                                       class="px-4 py-2 bg-white border border-gray-300 text-sm font-medium text-gray-500 hover:bg-gray-50">
                                        Previous
                                    </a>
                                @endif
                                <span class="px-4 py-2 bg-white border border-gray-300 text-sm font-medium text-gray-500">
                                    Page {{ $pagination['page'] }} of {{ $pagination['pageCount'] }}
                                </span>
                                @if($pagination['page'] < $pagination['pageCount'])
                                    <a href="{{ request()->fullUrlWithQuery(['page' => $pagination['page'] + 1]) }}"
                                       class="px-4 py-2 bg-white border border-gray-300 text-sm font-medium text-gray-500 hover:bg-gray-50">
                                        Next
                                    </a>
                                @endif
                                <a href="{{ request()->fullUrlWithQuery(['page' => $pagination['pageCount']]) }}"
                                   class="px-4 py-2 bg-white border border-gray-300 text-sm font-medium text-gray-500 hover:bg-gray-50">
                                    Last
                                </a>
                            </nav>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
