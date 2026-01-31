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
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Fanga tegishli baholar') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">#</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Dars sanasi</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fan</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Mashg'ulot turi</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Juftlik</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Xodim</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Baholar</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Holat</th>
                            </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($grades as $index => $grade)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $index + 1 }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ \Carbon\Carbon::parse($grade->lesson_date)->format('d-m-Y')  }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $grade->subject_name }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $grade->training_type_name }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $grade->lesson_pair_name }} ({{ $grade->lesson_pair_start_time }} - {{ $grade->lesson_pair_end_time }})</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $grade->employee_name }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">  @if($grade->status == 'pending')
                                            {{ $grade->reason == "absent" ? "0 (NB)" : $grade->grade }}
                                        @elseif($grade->status == 'retake')
                                            {{ $grade->grade ?? "0 (NB)" }}/{{ $grade->retake_grade }}
                                        @else
                                            {{ $grade->grade }}
                                        @endif</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <div class="flex items-center">
                                            @php
                                                $badge = getStatusBadge($grade->status);
                                            @endphp
                                            <span class="px-2 inline-flex items-center text-xs leading-5 font-semibold rounded-full bg-{{ $badge['color'] }}-100 text-{{ $badge['color'] }}-800">
            <svg class="mr-1.5 h-2 w-2 text-{{ $badge['color'] }}-400 flex-shrink-0" fill="currentColor" viewBox="0 0 8 8">
                <circle cx="4" cy="4" r="3"/>
            </svg>
            <span class="flex-grow">{{ __($badge['text']) }}</span>
        </span>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-student-app-layout>
