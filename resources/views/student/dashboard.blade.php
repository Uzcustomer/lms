<x-student-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-sm text-gray-800 leading-tight">
            {{ __('Talaba boshqaruv paneli') }}
        </h2>
    </x-slot>

    <div class="pb-6">
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
                            <a href="{{ route('student.exam-schedule') }}"
                               class="flex items-center justify-center p-4 bg-purple-100 rounded-lg hover:bg-purple-200 transition">
                                <svg class="w-6 h-6 mr-2 text-purple-600" fill="none" stroke="currentColor"
                                     viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"></path>
                                </svg>
                                Imtihon jadvali
                            </a>
                        </div>
                    </div>

                    @if($debtBySemester->isNotEmpty())
                    <div class="bg-white shadow rounded-lg p-6 mb-6">
                        <h4 class="text-lg font-semibold mb-4 text-red-700">Qarzdor fanlar ({{ $debtSubjectsCount }} ta)</h4>
                        <div class="space-y-4">
                            @foreach($debtBySemester as $semesterName => $records)
                                <div class="border border-red-200 rounded-lg overflow-hidden">
                                    <div class="bg-red-50 px-4 py-3 flex items-center justify-between">
                                        <h5 class="text-sm font-semibold text-red-800">{{ $semesterName }}</h5>
                                        <span class="text-xs text-red-500">{{ $records->count() }} ta fan</span>
                                    </div>
                                    <div class="overflow-x-auto">
                                        <table class="min-w-full divide-y divide-gray-200">
                                            <thead class="bg-gray-50/50">
                                                <tr>
                                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Fan nomi</th>
                                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Kredit</th>
                                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Soat</th>
                                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Ball</th>
                                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Baho</th>
                                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Holat</th>
                                                </tr>
                                            </thead>
                                            <tbody class="bg-white divide-y divide-gray-100">
                                                @foreach($records as $record)
                                                    <tr>
                                                        <td class="px-4 py-2 text-sm text-gray-900">{{ $record->subject_name }}</td>
                                                        <td class="px-4 py-2 text-sm text-gray-600">{{ $record->credit ?? '-' }}</td>
                                                        <td class="px-4 py-2 text-sm text-gray-600">{{ $record->total_acload ?? '-' }}</td>
                                                        <td class="px-4 py-2 text-sm text-gray-600">{{ $record->total_point ?? '-' }}</td>
                                                        <td class="px-4 py-2 text-sm font-medium text-gray-900">{{ $record->grade ?? '-' }}</td>
                                                        <td class="px-4 py-2 text-sm">
                                                            @if(!empty($record->retraining_status))
                                                                <span class="px-2 inline-flex items-center text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                                                    <svg class="mr-1 h-2 w-2 text-red-400" fill="currentColor" viewBox="0 0 8 8"><circle cx="4" cy="4" r="3"/></svg>
                                                                    Qayta o'qish
                                                                </span>
                                                            @elseif(!empty($record->grade) && in_array($record->grade, ['2', '0']))
                                                                <span class="px-2 inline-flex items-center text-xs leading-5 font-semibold rounded-full bg-orange-100 text-orange-800">
                                                                    <svg class="mr-1 h-2 w-2 text-orange-400" fill="currentColor" viewBox="0 0 8 8"><circle cx="4" cy="4" r="3"/></svg>
                                                                    Baho: {{ $record->grade }}
                                                                </span>
                                                            @else
                                                                <span class="px-2 inline-flex items-center text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                                                    <svg class="mr-1 h-2 w-2 text-yellow-400" fill="currentColor" viewBox="0 0 8 8"><circle cx="4" cy="4" r="3"/></svg>
                                                                    Qarzdor
                                                                </span>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    <div class="bg-white shadow rounded-lg p-6">
                        <h4 class="text-lg font-semibold mb-4">Fanlar bo'yicha baholar</h4>
                        @if($gradesBySubject->isEmpty())
                            <p class="text-sm text-gray-500">Hozircha baholar yo'q.</p>
                        @else
                            <div class="space-y-4">
                                @foreach($gradesBySubject as $subjectName => $grades)
                                    <div class="border border-gray-200 rounded-lg overflow-hidden">
                                        <div class="bg-gray-50 px-4 py-3 flex items-center justify-between">
                                            <h5 class="text-sm font-semibold text-gray-800">{{ $subjectName }}</h5>
                                            <span class="text-xs text-gray-500">{{ $grades->count() }} ta baho</span>
                                        </div>
                                        <div class="overflow-x-auto">
                                            <table class="min-w-full divide-y divide-gray-200">
                                                <thead class="bg-gray-50/50">
                                                    <tr>
                                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Turi</th>
                                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Baho</th>
                                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Holat</th>
                                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Sana</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="bg-white divide-y divide-gray-100">
                                                    @foreach($grades as $grade)
                                                        @php
                                                            $statusMap = [
                                                                'pending' => ['color' => 'yellow', 'text' => 'Kutilmoqda'],
                                                                'recorded' => ['color' => 'green', 'text' => 'Baholangan'],
                                                                'retake' => ['color' => 'blue', 'text' => 'Qayta topshirilgan'],
                                                                'closed' => ['color' => 'red', 'text' => 'Yopilgan'],
                                                            ];
                                                            $badge = $statusMap[$grade->status] ?? ['color' => 'gray', 'text' => 'Noma\'lum'];
                                                        @endphp
                                                        <tr>
                                                            <td class="px-4 py-2 text-sm text-gray-500">{{ $grade->training_type_name }}</td>
                                                            <td class="px-4 py-2 text-sm font-medium text-gray-900">
                                                                @if($grade->status == 'pending')
                                                                    {{ $grade->reason == 'absent' ? '0 (NB)' : $grade->grade }}
                                                                @elseif($grade->status == 'retake')
                                                                    {{ $grade->grade ?? '0 (NB)' }}/{{ $grade->retake_grade }}
                                                                @else
                                                                    {{ $grade->grade }}
                                                                @endif
                                                            </td>
                                                            <td class="px-4 py-2 text-sm">
                                                                <span class="px-2 inline-flex items-center text-xs leading-5 font-semibold rounded-full bg-{{ $badge['color'] }}-100 text-{{ $badge['color'] }}-800">
                                                                    <svg class="mr-1 h-2 w-2 text-{{ $badge['color'] }}-400" fill="currentColor" viewBox="0 0 8 8"><circle cx="4" cy="4" r="3"/></svg>
                                                                    {{ $badge['text'] }}
                                                                </span>
                                                            </td>
                                                            <td class="px-4 py-2 text-sm text-gray-500">{{ format_date($grade->lesson_date) }}</td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-student-app-layout>
