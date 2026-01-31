<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            OSKI Baholash
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
                            <form method="POST" class="inline" action="{{ route('admin.oski.sababli.save') }}"
                                enctype="multipart/form-data">

                                @csrf
                                <input type="hidden" value="{{$oski->id}}" name="oski">
                                <dl class="divide-y divide-gray-100">
                                    <div class="px-2 py-2 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-0">
                                        <dt class="mt-1 text-sm font-medium text-gray-900">Talaba</dt>
                                        @if ($oski->status == 0)

                                            <dd class="mt-1 text-sm font-medium text-gray-900">Yangi baho</dd>
                                        @else
                                            <dd class="mt-1 text-sm font-medium text-gray-900">Baho</dd>

                                        @endif

                                    </div>
                                    @foreach ($students as $student)
                                        <div class="px-2 py-2 row sm:px-0">
                                            <dt class="mt-1 text-sm font-medium text-gray-900 col-4">
                                                {{ $student->full_name }}
                                            </dt>

                                            <dt class="mt-1 text-sm font-medium text-gray-900 col-2">
                                                {{ $student->grade }}
                                            </dt>
                                            @if (empty($student->grade) and $student->grade != 0)
                                                @if($oski->status == 1 and auth()->user()->hasRole(['admin']))
                                                    <?php 
                                                        $check = in_array($student->hemis_id, json_decode($oski->sababli_studets, true) ?? []);
                                                    ?>
                                                    @if(!$check)
                                                        <dd class="mt-1 text-sm font-medium text-gray-900 col-2">
                                                            <input type="checkbox" id="checkbox_{{ $student->id }}" name="sababli[]"
                                                                value="{{ $student->hemis_id }}"
                                                                class="border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                                                style="width: 20px; height:20px">
                                                            Sababli
                                                        </dd>
                                                        <dd class="mt-1 text-sm font-medium text-gray-900 col-4">
                                                            <input type="file" name="sababli_file[{{ $student->hemis_id }}]">
                                                        </dd>
                                                    @else

                                                        <dd class="mt-1 text-sm font-medium text-gray-900 col-1">
                                                            <input type="checkbox" id="checkbox_{{ $student->id }}" checked disabled
                                                                class="border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                                                style="width: 20px; height:20px">
                                                            Sababli
                                                        </dd>
                                                        <input type="hidden" name="check[{{ $student->hemis_id }}]" value="1">
                                                        <input type="hidden" name="sababli[]" value="{{ $student->hemis_id }}">
                                                        <dd class="mt-1 text-sm font-medium text-gray-900 col-3">
                                                            <a
                                                                href="/storage/{{$student->file_path}} ">{{$student->file_original_name}}</a>
                                                        </dd>
                                                        <dd class="mt-1 text-sm  font-medium text-red-500 col-2">
                                                            <a href="{{ route('admin.oski.file.delete', $student->exam_file_id) }}"
                                                                class="text-danger bg-white">O'chirish
                                                            </a>
                                                        </dd>
                                                    @endif
                                                @endif
                                            @endif
                                        </div>
                                    @endforeach
                                </dl>
                                @if ($oski->status == 1 && auth()->user()->hasRole(['admin']))

                                    <div class="mt-6">
                                        <button type="submit"
                                            class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                                            Sabalilarni saqlash
                                        </button>
                                    </div>
                                @endif
                                @if ($oski->status == 0 && auth()->user()->hasRole(['examiner']))
                                    <div class="mt-6">
                                        <a type="submit" href="{{ route('admin.oski.grade_form', $oski->id) }}"
                                            class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                                            Baholash
                                        </a>
                                    </div>
                                @endif

                                @if (auth()->user()->hasRole(['admin']))
                                    <div class="mt-6">
                                        <a type="submit" href="{{ route('admin.oski.grade_form', $oski->id) }}"
                                            class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                                            Baholash
                                        </a>
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
                                            {{$oski->deportment_name}}
                                        </dd>
                                    </div>
                                    <div class="px-2 py-2 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-0">
                                        <dt class="mt-1 text-sm/6 font-medium text-gray-900">Group</dt>
                                        <dd class="mt-1 text-sm/6 text-gray-700 sm:col-span-2 sm:mt-0">
                                            {{$oski->group_name}}
                                        </dd>
                                    </div>

                                    <div class="px-2 py-2 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-0">
                                        <dt class="mt-1 text-sm/6 font-medium text-gray-900">Semester</dt>
                                        <dd class="mt-1 text-sm/6 text-gray-700 sm:col-span-2 sm:mt-0">
                                            {{$oski->semester_name}}
                                        </dd>
                                    </div>
                                    <div class="px-2 py-2 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-0">
                                        <dt class="mt-1 text-sm/6 font-medium text-gray-900">Fan</dt>
                                        <dd class="mt-1 text-sm/6 text-gray-700 sm:col-span-2 sm:mt-0">
                                            {{$oski->subject_name}}
                                        </dd>
                                    </div>
                                    <div class="px-2 py-2 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-0">
                                        <dt class="mt-1 text-sm/6 font-medium text-gray-900">Sana</dt>
                                        <dd class="mt-1 text-sm/6 text-gray-700 sm:col-span-2 sm:mt-0">
                                            {{$oski->start_date}}
                                        </dd>
                                    </div>
                                    <?php 
                                      $shakllar = [
    [
        'id' => 1,
        "name" => "12-shakl"
    ],
    [
        'id' => 2,
        "name" => "12-shakl(qo‘shimcha)"
    ],
    [
        'id' => 3,
        "name" => "12a-shakl"
    ],
    [
        'id' => 4,
        "name" => "12a-shakl(qo‘shimcha)"
    ],
    [
        'id' => 5,
        "name" => "12b-shakl"
    ],
    [
        'id' => 6,
        "name" => "12b-shakl(qo‘shimcha)"
    ],
    [
        'id' => 7,
        "name" => "12-shakl (yozgi)"
    ]
];
                                    ?>
                                    <div class="px-2 py-2 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-0">
                                        <dt class="mt-1 text-sm/6 font-medium text-gray-900">Shakl</dt>
                                        <dd class="mt-1 text-sm/6 text-gray-700 sm:col-span-2 sm:mt-0">
                                            {{($shakllar[($oski->shakl ?? 1) - 1]['name'] ?? "") }}

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