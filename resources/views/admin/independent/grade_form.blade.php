<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Mustaqil ta'lim Baholash
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 pt-2 bg-white border-b border-gary-200">
                    @if (session('success'))
                    <div class="mb-4 px-4 py-2 bg-green-100 border border-green-400 text-green-700 rounded">
                        {{ session('success') }}
                    </div>
                    @endif

                    @if (session('error'))
                    <div class="mb-4 px-4 py-2 bg-red-100 border border-red-400 text-red-700 rounded">
                        {{ session('error') }}
                    </div>
                    @endif
                    <div class="flex w-full">
                        <div class="w-3/4">
                            <form method="POST" class="inline" action="{{ route('admin.independent.grade.save') }}">
                                @csrf
                                <input type="hidden" value="{{$independent->id}}" name="independent">
                                <dl class="divide-y divide-gray-100">
                                    <div class="px-2 py-2 sm:grid sm:grid-cols-4 sm:gap-4 sm:px-0">
                                        <dt class="mt-1 text-sm font-medium text-gray-900">Talaba</dt>
                                        <dd class="mt-1 text-sm font-medium text-gray-900">Yuklangan fayl</dd>
                                        @if ($independent->status == 0)
                                        <dd class="mt-1 text-sm font-medium text-gray-900">Yangi baho</dd>
                                        @else
                                        <dd class="mt-1 text-sm font-medium text-gray-900">Baho</dd>
                                        @endif
                                    </div>
                                    @foreach ($students as $student)
                                    @php
                                        $gradeValue = is_numeric($student->grade) ? (float) $student->grade : null;
                                        $isLocked = $gradeValue !== null && $gradeValue >= ($minimumLimit ?? 60);
                                        $needsGrading = $gradeValue === null || $gradeValue < ($minimumLimit ?? 60);
                                    @endphp
                                    <div class="px-2 py-2 sm:grid sm:grid-cols-4 sm:gap-4 sm:px-0">
                                        <dt class="mt-1 text-sm font-medium text-gray-900">
                                            {{ $student->full_name }}
                                        </dt>
                                        <dd class="mt-1 text-sm text-gray-700">
                                            @if(isset($submissions[$student->id]))
                                                @php $sub = $submissions[$student->id]; @endphp
                                                <a href="{{ asset('storage/' . $sub->file_path) }}" target="_blank"
                                                   class="inline-flex items-center text-blue-600 hover:text-blue-800 underline text-xs">
                                                    <svg class="h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                                    </svg>
                                                    {{ $sub->file_original_name }}
                                                </a>
                                                <div class="text-xs text-gray-400">{{ $sub->submitted_at->format('d.m.Y H:i') }}</div>
                                            @else
                                                <span class="text-xs text-gray-400">Yuklanmagan</span>
                                            @endif
                                        </dd>
                                        @if ($isLocked)
                                        <dt class="mt-1 text-sm font-medium text-green-700">
                                            {{ $student->grade }}
                                            <span class="text-xs text-green-500 ml-1" title="Baho qulflangan ({{ $minimumLimit ?? 60 }}+)">&#128274;</span>
                                            @if(isset($gradeHistory[$student->id]) && $gradeHistory[$student->id]->count() > 0)
                                                <div class="text-xs text-gray-400 font-normal mt-1">
                                                    Oldingi: @foreach($gradeHistory[$student->id] as $h){{ $h->grade }}@if(!$loop->last), @endif @endforeach
                                                </div>
                                            @endif
                                        </dt>
                                        @elseif ($needsGrading)
                                        <dd class="mt-1 text-sm font-medium text-gray-900">
                                            <input type="number" name="baho[{{ $student->id }}]" placeholder="0-100"
                                                min="0" max="100" required onkeydown="focusNext(event)"
                                                value="{{$student->grade}}"
                                                class="border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                                style="width:100px">
                                            @if(isset($gradeHistory[$student->id]) && $gradeHistory[$student->id]->count() > 0)
                                                <div class="text-xs text-gray-400 mt-1">
                                                    Oldingi: @foreach($gradeHistory[$student->id] as $h)<span class="text-red-400">{{ $h->grade }}</span>@if(!$loop->last), @endif @endforeach
                                                </div>
                                            @endif
                                        </dd>
                                        @else
                                        <dt class="mt-1 text-sm font-medium text-gray-900">
                                            {{ $student->grade }}
                                        </dt>
                                        @endif
                                    </div>
                                    @endforeach
                                </dl>
                                @php
                                    $hasStudentsNeedingGrading = $students->contains(function ($s) {
                                        $g = is_numeric($s->grade) ? (float) $s->grade : null;
                                        return $g === null || $g < ($minimumLimit ?? 60);
                                    });
                                @endphp
                                @if ($hasStudentsNeedingGrading)
                                <div class="mt-6">
                                    <button type="submit"
                                        class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                                        Saqlash
                                    </button>
                                </div>
                                @endif
                            </form>

                        </div>
                        <div class="flex-1 ">
                            <div class="ml-1" style="border-left:2px black solid">
                                <dl class="divide-y divide-gray-100">
                                    <div class="px-2 py-2 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-0">
                                        <dt class="mt-1 text-sm/6 font-medium text-gray-900">Fakultet</dt>
                                        <dd class="mt-1 text-sm/6 text-gray-700 sm:col-span-2 sm:mt-0">
                                            {{$independent->deportment_name}}
                                        </dd>
                                    </div>
                                    <div class="px-2 py-2 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-0">
                                        <dt class="mt-1 text-sm/6 font-medium text-gray-900">Group</dt>
                                        <dd class="mt-1 text-sm/6 text-gray-700 sm:col-span-2 sm:mt-0">
                                            {{$independent->group_name}}
                                        </dd>
                                    </div>
                                    <div class="px-2 py-2 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-0">
                                        <dt class="mt-1 text-sm/6 font-medium text-gray-900">O'qituvchi</dt>
                                        <dd class="mt-1 text-sm/6 text-gray-700 sm:col-span-2 sm:mt-0">
                                            {{$independent->teacher_short_name}}
                                        </dd>
                                    </div>
                                    <div class="px-2 py-2 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-0">
                                        <dt class="mt-1 text-sm/6 font-medium text-gray-900">Semester</dt>
                                        <dd class="mt-1 text-sm/6 text-gray-700 sm:col-span-2 sm:mt-0">
                                            {{$independent->semester_name}}
                                        </dd>
                                    </div>
                                    <div class="px-2 py-2 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-0">
                                        <dt class="mt-1 text-sm/6 font-medium text-gray-900">Fan</dt>
                                        <dd class="mt-1 text-sm/6 text-gray-700 sm:col-span-2 sm:mt-0">
                                            {{$independent->subject_name}}
                                        </dd>
                                    </div>
                                    <div class="px-2 py-2 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-0">
                                        <dt class="mt-1 text-sm/6 font-medium text-gray-900">Sana</dt>
                                        <dd class="mt-1 text-sm/6 text-gray-700 sm:col-span-2 sm:mt-0">
                                            {{$independent->start_date}}
                                        </dd>
                                    </div>

                                </dl>
                            </div>
                        </div>
                    </div>

                </div>

            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />



    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script>
        // const numberInput = document.getElementById('numberInput');
        const errorMsg = document.getElementById('errorMsg');
        // const form = document.getElementById('numberForm');

        // form.addEventListener('submit', (e) => {
        //     const value = parseInt(numberInput.value, 10);
        //     if (isNaN(value) || value < 0 || value > 100) {
        //         e.preventDefault(); // Formani yuborishni to'xtatadi
        //         errorMsg.style.display = 'block';
        //     } else {
        //         errorMsg.style.display = 'none';
        //         alert('Son qabul qilindi: ' + value);
        //     }
        // });

        // numberInput.addEventListener('input', () => {
        //     const value = parseInt(numberInput.value, 10);
        //     if (value >= 0 && value <= 100) {
        //         errorMsg.style.display = 'none';
        //     }
        // });

        function focusNext(event) {
            if (event.key === "Enter") {
                event.preventDefault(); // Enter bosilganda formani submit qilishni oldini oladi
                const inputs = Array.from(document.querySelectorAll("input[type='number']"));
                const currentIndex = inputs.indexOf(event.target);
                if (currentIndex !== -1 && currentIndex < inputs.length - 1) {
                    inputs[currentIndex + 1].focus();
                }
            }
        }
    </script>
    <style>
        .error {
            color: red;
            margin-top: 5px;
            display: none;
        }

        .select2-container--classic .select2-selection--single {
            height: 38px;
            border: 1px solid #D1D5DB;
            border-radius: 0.375rem;
            background: white;
        }

        .select2-container--classic .select2-selection--single .select2-selection__rendered {
            line-height: 36px;
            padding-left: 12px;
            padding-right: 45px;
            color: #374151;
        }

        .select2-container--classic .select2-selection--single .select2-selection__clear {
            position: absolute;
            right: 30px;
            font-size: 22px;
            font-weight: 500;
            color: #4B5563;
            margin: 0;
            height: 36px;
            line-height: 36px;
            padding: 0 5px;
        }

        .select2-container--classic .select2-selection--single .select2-selection__clear:hover {
            color: #1F2937;
        }

        .select2-container--classic .select2-selection--single .select2-selection__arrow {
            height: 36px;
            width: 25px;
            border-left: none;
            border-radius: 0 0.375rem 0.375rem 0;
            background: transparent;
            position: absolute;
            right: 0;
            top: 0;
        }

        .select2-container--classic .select2-selection--single .select2-selection__arrow b {
            border-color: #6B7280 transparent transparent transparent;
        }

        .select2-container--classic.select2-container--open .select2-selection--single .select2-selection__arrow b {
            border-color: transparent transparent #6B7280 transparent;
        }

        .select2-container--classic .select2-selection--single.select2-selection--clearable .select2-selection__arrow {
            right: 0;
        }
    </style>
</x-app-layout>