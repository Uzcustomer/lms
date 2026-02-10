<x-student-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Talaba boshqaruv paneli') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-2xl font-bold">Xush kelibsiz, {{ Auth::guard('student')->user()->full_name }}
                            !</h3>
                        <span class="px-3 py-1 text-sm font-semibold text-white bg-green-500 rounded-full">Aktiv</span>
                    </div>


                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                        <div class="bg-blue-100 p-4 rounded-lg text-center">
                            <h4 class="text-lg font-semibold mb-2">Talaba GPA si</h4>
                            <p class="text-3xl font-bold text-blue-600">{{$avgGpa }}</p>
                        </div>
                        <div class="bg-green-100 p-4 rounded-lg text-center">
                            <h4 class="text-lg font-semibold mb-2">Qoldirgan darslar soni</h4>
                            <p class="text-3xl font-bold text-green-600">{{ $totalAbsent }} ta
                                </p>
                        </div>
                        <div class="bg-yellow-100 p-4 rounded-lg text-center">
                            <h4 class="text-lg font-semibold mb-2">Qayta topshirishlar soni</h4>
                            <p class="text-3xl font-bold text-yellow-600">{{ $debtSubjectsCount }} ta</p>
                        </div>
                    </div>

                    <div class="bg-white shadow rounded-lg p-6 mb-6">
                        <h4 class="text-lg font-semibold mb-4">Tezkor havolalar</h4>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <a href="{{ route('student.schedule') }}"
                               class="flex items-center justify-center p-4 bg-indigo-100 rounded-lg hover:bg-indigo-200 transition">
                                <svg class="w-6 h-6 mr-2 text-indigo-600" fill="none" stroke="currentColor"
                                     viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                </svg>
                                Dars jadvali
                            </a>
                            <a href="{{ route('student.attendance') }}"
                               class="flex items-center justify-center p-4 bg-green-100 rounded-lg hover:bg-green-200 transition">
                                <svg class="w-6 h-6 mr-2 text-green-600" fill="none" stroke="currentColor"
                                     viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                                </svg>
                                Davomat
                            </a>
                            <a href="{{ route('student.subjects') }}"
                               class="flex items-center justify-center p-4 bg-yellow-100 rounded-lg hover:bg-yellow-200 transition">
                                <svg class="w-6 h-6 mr-2 text-yellow-600" fill="none" stroke="currentColor"
                                     viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                                </svg>
                                Joriy fanlar
                            </a>
                            <a href="{{ route('student.pending-lessons') }}"
                               class="flex items-center justify-center p-4 bg-red-100 rounded-lg hover:bg-red-200 transition">
                                <svg class="w-6 h-6 mr-2 text-red-600" fill="none" stroke="currentColor"
                                     viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                                </svg>
                                Qayta topshirish
                            </a>
                        </div>
                    </div>

                    <div class="bg-white shadow rounded-lg p-6">
                        <h4 class="text-lg font-semibold mb-4">So'nggi Baholar</h4>
                        <ul class="space-y-3">
                            @foreach($recentGrades as $grade)
                                <li class="flex items-center text-sm">
                                    <svg class="w-4 h-4 mr-2 text-green-500" fill="none" stroke="currentColor"
                                         viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    <span>{{ $grade->subject_name }} fanidan {{ $grade->grade }} ball oldingiz - ({{ $grade->lesson_pair_name }}-para)  <time
                                            datetime="{{ $grade->created_at }}">{{ format_date($grade->created_at) }}</time> </span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-student-app-layout>
