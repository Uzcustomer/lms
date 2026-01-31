<x-student-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Dars jadvali') }} - {{ $selectedSemester['name'] ?? '' }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <form action="{{ route('student.schedule') }}" method="GET" class="mb-6 space-y-4 md:space-y-0 md:flex md:space-x-4">
                        <div class="flex-1">
                            <x-input-label for="semester-select" :value="__('Semestr')" />
                            <x-select-input
                                id="semester-select"
                                name="semester_id"
                                :options="$semesters->map(function($semester) use ($selectedSemester) {
                                    return [
                                        'value' => $semester['id'],
                                        'label' => $semester['name'] . ' (' . $semester['education_year']['name'] . ')',
                                        'selected' => $semester['id'] == ($selectedSemester['id'] ?? null)
                                    ];
                                })->toArray()"
                                class="block mt-1 w-full"
                                onchange="this.form.submit()"
                            />
                        </div>

                        <div class="flex-1">
                            <x-input-label for="week-select" :value="__('Hafta')" />
                            <x-select-input
                                id="week-select"
                                name="week_id"
                                :options="$weeks->map(function($week) use ($selectedWeekId) {
                                    $startDate = \Carbon\Carbon::createFromTimestamp($week['start_date'])->format('d.m.Y');
                                    $endDate = \Carbon\Carbon::createFromTimestamp($week['end_date'])->format('d.m.Y');
                                    return [
                                        'value' => $week['id'],
                                        'label' => $startDate . ' - ' . $endDate,
                                        'selected' => $week['id'] == $selectedWeekId
                                    ];
                                })->toArray()"
                                class="block mt-1 w-full"
                                onchange="this.form.submit()"
                            />
                        </div>
                    </form>

                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                        @php
                            $days = [
                                'Monday' => 'Dushanba',
                                'Tuesday' => 'Seshanba',
                                'Wednesday' => 'Chorshanba',
                                'Thursday' => 'Payshanba',
                                'Friday' => 'Juma',
                                'Saturday' => 'Shanba'
                            ];
                        @endphp

                        @foreach($days as $dayEn => $dayUz)
                            <div class="bg-white dark:bg-gray-700 overflow-hidden shadow-sm sm:rounded-lg">
                                <div class="px-4 py-5 sm:px-6 bg-gray-50 dark:bg-gray-600">
                                    <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-gray-100">{{ $dayUz }}</h3>
                                </div>
                                <div class="border-t border-gray-200 dark:border-gray-600">
                                    @if(isset($groupedSchedule[$dayEn]) && count($groupedSchedule[$dayEn]) > 0)
                                        <dl>
                                            @foreach($groupedSchedule[$dayEn] as $lesson)
                                                <div class="px-4 py-4 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6 hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors duration-150">
                                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-300">{{ $lesson['subject']['name'] }}</dt>
                                                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100 sm:mt-0 sm:col-span-2">
                                                        <p class="mb-1"><span class="font-semibold">O'qituvchi:</span> {{ $lesson['employee']['name'] }}</p>
                                                        <p class="mb-1"><span class="font-semibold">Xona:</span> {{ $lesson['auditorium']['name'] }}</p>
                                                        <p><span class="font-semibold">Vaqt:</span> {{ $lesson['lessonPair']['start_time'] }} - {{ $lesson['lessonPair']['end_time'] }}</p>
                                                    </dd>
                                                </div>
                                            @endforeach
                                        </dl>
                                    @else
                                        <div class="flex items-center justify-center h-32">
                                            <p class="text-xl font-bold text-gray-400 dark:text-gray-500">Dars yo'q</p>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-student-app-layout>
