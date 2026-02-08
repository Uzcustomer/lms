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
                        @forelse($grades as $grade)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">{{ $grade->subject_name }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">{{ $grade->training_type_name }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">{{ $grade->employee_name }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">{{ $grade->grade }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">{{ \Carbon\Carbon::parse($grade->lesson_date)->format('d-m-Y') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">No low grades found.</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>

                    <div class="mt-4">
                        {{ $grades->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
