@php
    function getStatusBadge($status) {
        $statuses = [
             'pending' => ['color' => 'yellow', 'text' => 'Kutilmoqda'],
            'recorded' => ['color' => 'green', 'text' => 'Baholangan'],
            'retake' => ['color' => 'blue', 'text' => 'Qayta topshirilgan'],
            'closed' => ['color' => 'red', 'text' => 'Yopilgan'],
        ];

        return $statuses[$status] ?? ['color' => 'gray', 'text' => 'Noma\'lum'];
    }
@endphp
<x-student-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-sm text-gray-800 leading-tight">
            {{ __('Qayta topshirish fanlari') }}
        </h2>
    </x-slot>

    <div class="pb-6">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    @if($pendingLessons->count() > 0)
                        <div class="overflow-x-auto">
                            <div class="inline-block min-w-full align-middle">
                                <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Dars Idsi
                                            </th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Fan
                                            </th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                O'qituvchi
                                            </th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Mashg'ulot turi
                                            </th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Sana
                                            </th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Sabab
                                            </th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Status
                                            </th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Muddat
                                            </th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Ball
                                            </th>
                                        </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                        @foreach($pendingLessons as $lesson)
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $lesson->id }}</td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $lesson->subject_name }}</td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $lesson->employee_name }}</td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $lesson->training_type_name }}</td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ format_date($lesson->lesson_date) }}</td>
{{--                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $lesson->reason == 'absent' ? 'NB' : 'Past baho' }}</td>--}}
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">  @if($lesson->reason == 'teacher_victim')
                                                        Kechiktirilgan baho
                                                    @elseif($lesson->status == 'low_grade')
                                                        Past baho
                                                    @else
                                                        NB
                                                    @endif</td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <div class="flex items-center">
                                                        @php
                                                            $badge = getStatusBadge($lesson->status);
                                                        @endphp
                                                        <span
                                                            class="px-2 inline-flex items-center text-xs leading-5 font-semibold rounded-full bg-{{ $badge['color'] }}-100 text-{{ $badge['color'] }}-800">
            <svg class="mr-1.5 h-2 w-2 text-{{ $badge['color'] }}-400 flex-shrink-0" fill="currentColor"
                 viewBox="0 0 8 8">
                <circle cx="4" cy="4" r="3"/>
            </svg>
            <span class="flex-grow">{{ __($badge['text']) }}</span>
        </span>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $lesson->deadline }}</td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    @if($lesson->status == 'pending')
                                                        {{ $lesson->reason == "absent" ? "0 (NB)" : $lesson->grade }}
                                                    @elseif($lesson->status == 'retake')
                                                        {{ $lesson->grade ?? "0 (NB)" }}/{{ $lesson->retake_grade }}
                                                    @else
                                                        {{ $lesson->grade }}
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    @else
                        <p class="text-gray-500">Hozircha qayta topshirish uchun fanlar yo'q.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-student-app-layout>
