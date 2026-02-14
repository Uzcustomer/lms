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
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ format_date($grade->lesson_date) }}</td>
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

                    {{-- YN rozilik bo'limi --}}
                    @if(isset($subjectId))
                    <div class="mt-6 border-t pt-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Yakuniy nazorat (YN) ga rozilik</h3>

                        @if(isset($ynSubmission) && $ynSubmission)
                            {{-- YN ga allaqachon yuborilgan --}}
                            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                <div class="flex items-center">
                                    <svg class="h-5 w-5 text-blue-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                                    </svg>
                                    <span class="text-blue-800 font-medium">
                                        YN ga yuborilgan ({{ $ynSubmission->submitted_at->format('d.m.Y H:i') }}). Baholar qulflangan.
                                    </span>
                                </div>
                            </div>
                        @elseif(isset($ynConsent) && $ynConsent)
                            {{-- Rozilik allaqachon yuborilgan --}}
                            <div class="flex items-center space-x-4">
                                @if($ynConsent->status === 'approved')
                                    <div class="bg-green-50 border border-green-200 rounded-lg p-4 flex-1">
                                        <div class="flex items-center">
                                            <svg class="h-5 w-5 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                            </svg>
                                            <span class="text-green-800 font-medium">YN topshirishga rozilik yuborildi</span>
                                            <span class="text-green-600 text-sm ml-2">({{ $ynConsent->submitted_at->format('d.m.Y H:i') }})</span>
                                        </div>
                                    </div>
                                @else
                                    <div class="bg-red-50 border border-red-200 rounded-lg p-4 flex-1">
                                        <div class="flex items-center">
                                            <svg class="h-5 w-5 text-red-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                                            </svg>
                                            <span class="text-red-800 font-medium">YN topshirishdan rad etildi</span>
                                            <span class="text-red-600 text-sm ml-2">({{ $ynConsent->submitted_at->format('d.m.Y H:i') }})</span>
                                        </div>
                                    </div>
                                @endif
                                {{-- Fikrini o'zgartirish imkoniyati --}}
                                <form method="POST" action="{{ route('student.yn-consent') }}" class="flex-shrink-0">
                                    @csrf
                                    <input type="hidden" name="subject_id" value="{{ $subjectId }}">
                                    @if($ynConsent->status === 'approved')
                                        <button type="submit" name="status" value="rejected"
                                            class="px-4 py-2 bg-red-600 text-white text-sm rounded-lg hover:bg-red-700 transition"
                                            onclick="return confirm('Rozilikni bekor qilmoqchimisiz?')">
                                            Bekor qilish
                                        </button>
                                    @else
                                        <button type="submit" name="status" value="approved"
                                            class="px-4 py-2 bg-green-600 text-white text-sm rounded-lg hover:bg-green-700 transition"
                                            onclick="return confirm('YN topshirishga rozilik berasizmi?')">
                                            Rozilik berish
                                        </button>
                                    @endif
                                </form>
                            </div>
                        @else
                            {{-- Hali rozilik berilmagan --}}
                            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
                                <p class="text-yellow-800 text-sm">
                                    Darslar tugagandan so'ng, yakuniy nazorat (YN) ga kirishga ruxsat berish uchun roziligingizni bildiring.
                                </p>
                            </div>
                            <div class="flex space-x-4">
                                <form method="POST" action="{{ route('student.yn-consent') }}">
                                    @csrf
                                    <input type="hidden" name="subject_id" value="{{ $subjectId }}">
                                    <button type="submit" name="status" value="approved"
                                        class="px-6 py-3 bg-green-600 text-white font-medium rounded-lg hover:bg-green-700 transition shadow-sm"
                                        onclick="return confirm('YN topshirishga tayyorman — rozilik berasizmi?')">
                                        YN topshirishga tayyorman
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('student.yn-consent') }}">
                                    @csrf
                                    <input type="hidden" name="subject_id" value="{{ $subjectId }}">
                                    <button type="submit" name="status" value="rejected"
                                        class="px-6 py-3 bg-red-600 text-white font-medium rounded-lg hover:bg-red-700 transition shadow-sm"
                                        onclick="return confirm('YN topshirishga rozi emasligingizni bildirasizmi?')">
                                        Rozi emasman
                                    </button>
                                </form>
                            </div>
                        @endif
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-student-app-layout>
