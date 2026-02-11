<x-teacher-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{Auth::guard('teacher')->user()->short_name}} ga tegishli {{ __('Talabalar ro\'yxati') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <div class="mb-4">
                        <form action="{{ route('teacher.students') }}" method="GET" class="flex items-center">
                            <input type="text" name="search" placeholder="Qidirish..." value="{{ request('search') }}" class="form-input rounded-md shadow-sm mt-1 block w-full" />
                            <button type="submit" class="ml-3 inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 active:bg-gray-900 focus:outline-none focus:border-gray-900 focus:shadow-outline-gray disabled:opacity-25 transition ease-in-out duration-150">
                                Qidirish
                            </button>
                        </form>
                    </div>

                    @foreach($groupedStudents as $subjectName => $students)
                        <h3 class="text-lg font-semibold mt-6 mb-3">{{ $subjectName }}</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">#</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">FISH</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kurs</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Semester</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fan</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Guruh</th>
                                </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($students as $index => $studentGrade)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $index + 1 }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <a href="{{ route('teacher.student.details', ['studentId' => $studentGrade->student_id, 'subjectId' => $studentGrade->subject_id]) }}" class="text-indigo-600 hover:text-indigo-900">{{ $studentGrade->student->full_name }}</a></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $studentGrade->student->level_name }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $studentGrade->semester_name }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $studentGrade->student->group_name }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $studentGrade->curriculumSubject()->subject_name }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            @if(session('impersonating'))
                                                <form action="{{ route('impersonate.switch-to-student', $studentGrade->student_id) }}" method="POST" onsubmit="return confirm('{{ addslashes($studentGrade->student->full_name) }} sifatida tizimga kirasizmi?')">
                                                    @csrf
                                                    <button type="submit"
                                                            class="px-2 py-1 text-xs rounded"
                                                            style="background-color: #3b82f6; color: white;">
                                                        Login as
                                                    </button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endforeach
                    <div class="mt-4">
                        {{ $studentGrades->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-teacher-app-layout>
