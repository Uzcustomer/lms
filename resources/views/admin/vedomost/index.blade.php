<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                {{ __('Vedomost') }}
            </h2>
            @if(auth()->user()->hasRole(['admin']) || auth()->user()->hasRole(['dekan']))
                <a href="{{ route('admin.vedomost.create') }}" class="btn btn-primary">
                    {{ __('Vedemost yaratish') }}
                </a>
            @endif

        </div>
    </x-slot>

    @if(session('error'))
        <div class="relative px-4 py-3 mb-4 text-red-700 bg-red-100 border border-red-400 rounded" role="alert">
            <strong class="font-bold">Xato!</strong>
            <span class="block sm:inline">{{ session('error') }}</span>
        </div>
    @endif

    @if(session('success'))
        <div class="relative px-4 py-3 mb-4 text-green-700 bg-green-100 border border-green-400 rounded" role="alert">
            <strong class="font-bold">Muvaffaqiyatli!</strong>
            <span class="block sm:inline">{{ session('success') }}</span>
        </div>
    @endif

    @if ($errors->any())
        <div class="relative px-4 py-3 mb-4 text-red-700 bg-red-100 border border-red-400 rounded" role="alert">
            <strong class="font-bold">Xatoliklar mavjud:</strong>
            <ul class="mt-3 list-disc list-inside">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    <div class="py-12">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">
            <div class="overflow-hidden bg-white shadow-xl sm:rounded-lg">
                <!-- Search Form -->

                <form id="search-form" method="GET" action="{{ route('admin.vedomost.index') }}" class="p-6 bg-gray-50">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-3">

                        <div>
                            <label class="block mb-2">Bo'lim</label>
                            <select name="department" id="department" class="w-full rounded-md select2">
                                <option value="">Tanlang</option>
                                @foreach($departments as $departmentItem)
                                    <option value="{{ $departmentItem->id }}" {{ request('department') == $departmentItem->id ? 'selected' : '' }}>
                                        {{ $departmentItem->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block mb-2 ">Guruh</label>
                            <select name="group" id="group" class="w-full rounded-md select2">
                                <option value="">Avval bo'limni tanlang</option>
                            </select>
                        </div>
                        <div>
                            <label class="block mb-2">Semestr</label>
                            <select name="semester_code" id="semester" class="w-full rounded-md select2">
                                <option value="">Avval guruhni tanlang</option>
                            </select>
                        </div>

                        <div>
                            <label class="block mb-2">Fan</label>
                            <select name="subject" id="subject" class="w-full rounded-md select2">
                                <option value="">Avval semestrni tanlang</option>
                            </select>
                        </div>

                    </div>
                    <div class="flex flex-col items-start justify-between gap-4 mt-6 sm:flex-row sm:items-center">
                        <div class="w-full sm:w-auto">
                            <label for="per_page" class="block mr-2 text-sm font-medium text-gray-700 sm:inline">Har bir
                                sahifada:</label>
                            <select id="per_page" name="per_page"
                                class="block w-full mt-1 border-gray-300 rounded-md shadow-sm select2 sm:mt-0 sm:w-auto focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                @foreach([10, 25, 50, 100] as $pageSize)
                                    <option value="{{ $pageSize }}" {{ request('per_page', 50) == $pageSize ? 'selected' : '' }}>
                                        {{ $pageSize }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit"
                            class="inline-flex items-center justify-center w-full px-4 py-2 text-xs font-semibold tracking-widest text-white uppercase transition bg-blue-500 border border-transparent rounded-md shadow-md sm:w-auto hover:bg-blue-600 active:bg-blue-700 focus:outline-none focus:border-blue-700 focus:ring focus:ring-blue-200 disabled:opacity-25 hover:shadow-lg">
                            Qidirish
                        </button>
                    </div>
                </form>

                <div>
                    <div class="overflow-x-auto">
                        <div class="inline-block min-w-full">
                            @if($vedomosts->isEmpty())
                                <div class="text-center text-gray-500">
                                    <p>Hozircha dars tarixi mavjud emas.</p>
                                </div>
                            @else
                                                    <table class="min-w-full divide-y divide-gray-200">
                                                        <thead class="bg-gray-50">
                                                            <tr>
                                                                <th
                                                                    class="px-6 py-3 text-xs font-medium tracking-wider text-left text-gray-500 uppercase">
                                                                    ID
                                                                </th>
                                                                <th
                                                                    class="px-6 py-3 text-xs font-medium tracking-wider text-left text-gray-500 uppercase">
                                                                    Fakultet
                                                                </th>
                                                                <th
                                                                    class="px-6 py-3 text-xs font-medium tracking-wider text-left text-gray-500 uppercase">
                                                                    Guruh
                                                                </th>
                                                                <th
                                                                    class="px-6 py-3 text-xs font-medium tracking-wider text-left text-gray-500 uppercase">
                                                                    Fan
                                                                </th>
                                                                <th
                                                                    class="px-6 py-3 text-xs font-medium tracking-wider text-left text-gray-500 uppercase">
                                                                    Semester
                                                                </th>
                                                                <th
                                                                    class="px-6 py-3 text-xs font-medium tracking-wider text-left text-gray-500 uppercase">
                                                                    Shakli
                                                                </th>

                                                                <th
                                                                    class="px-6 py-3 text-xs font-medium tracking-wider text-left text-gray-500 uppercase">
                                                                    Yaratgan Admin
                                                                </th>
                                                                <th
                                                                    class="px-6 py-3 text-xs font-medium tracking-wider text-left text-gray-500 uppercase">
                                                                    Yaratilgan sana va vaqt
                                                                </th>
                                                                <th
                                                                    class="px-6 py-3 text-xs font-medium tracking-wider text-left text-gray-500 uppercase">
                                                                    Amallar
                                                                </th>

                                                            </tr>
                                                        </thead>
                                                        <tbody class="bg-white divide-y divide-gray-200">
                                                            <?php
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                          $shakllar =
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
                                ];?>
                                                            @foreach ($vedomosts as $vedomost)
                                                                <tr>
                                                                    <td class="px-6 py-4 text-sm font-medium text-gray-900 whitespace-nowrap">
                                                                        {{$vedomost->id}}
                                                                    </td>
                                                                    <td class="px-6 py-4 text-sm font-medium text-gray-900 whitespace-nowrap">
                                                                        {{$vedomost->deportment_name}}
                                                                    </td>
                                                                    <td class="px-6 py-4 text-sm font-medium text-gray-900 whitespace-nowrap">
                                                                        {{$vedomost->group_name}}
                                                                    </td>
                                                                    <td class="px-6 py-4 text-sm font-medium text-gray-900 whitespace-nowrap">
                                                                        {{$vedomost->subject_name}}
                                                                    </td>
                                                                    <td class="px-6 py-4 text-sm font-medium text-gray-900 whitespace-nowrap">
                                                                        {{$vedomost->semester_name}}
                                                                    </td>
                                                                    <td class="px-6 py-4 text-sm font-medium text-gray-900 whitespace-nowrap">
                                                                        {{($shakllar[($vedomost->shakl ?? 1) - 1]['name']) }}
                                                                    </td>


                                                                    <td class="px-6 py-4 text-sm font-medium text-gray-900 whitespace-nowrap">
                                                                        {{$vedomost->user?->name ?? $vedomost->dekan->full_name}}
                                                                    </td>

                                                                    <td class="px-6 py-4 text-sm font-medium text-gray-900 whitespace-nowrap">
                                                                        {{ format_datetime($vedomost->created_at, true) }}
                                                                    </td>
                                                                    <td class="px-6 py-4 text-sm text-gray-500 whitespace-nowrap">
                                                                        @if(auth()->user()->hasRole(['admin']))

                                                                            <form action="{{ route('admin.vedomost.delete', $vedomost->id) }}"
                                                                                method="POST" class="inline"
                                                                                onsubmit="return confirm('Haqiqatan ham bu darsni o\'chirmoqchimisiz?');">
                                                                                @csrf
                                                                                @method('DELETE')
                                                                                <button type="submit" class="text-red-600 hover:text-red-900">
                                                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                                                                        viewBox="0 0 24 24">
                                                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                                                            stroke-width="2"
                                                                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                                                    </svg>
                                                                                </button>
                                                                            </form>

                                                                        @endif
                                                                        <a type="button" href="/{{$vedomost->file_path}}"
                                                                            class="text-blue-600 hover:text-blue-900">
                                                                            <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                                                                viewBox="0 0 24 24">
                                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                                    stroke-width="2" d="M12 4v12m0 0l-4-4m4 4l4-4m-8 8h8" />

                                                                            </svg>
                                                                        </a>

                                                                    </td>

                                                                </tr>
                                                            @endforeach
                                                        </tbody>
                                                    </table>

                                                    <div class="mt-4">
                                                        {{ $vedomosts->links() }}
                                                    </div>
                            @endif
                        </div>
                    </div>


                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
        $(document).ready(function () {
            $('.select2').each(function () {
                $(this).select2({
                    theme: 'classic',
                    width: '100%',
                    allowClear: true,
                    placeholder: $(this).find('option:first').text()
                }).on('select2:open', function () {
                    setTimeout(() => {
                        document.querySelector(
                            '.select2-container--open .select2-search__field')?.focus();
                    }, 0);
                });
            });
            // Select2 konfiguratsiyasi


            const selectedGroup = @json(request('group'));
            const selectedSemester = @json(request('semester'));
            const selectedSubject = @json(request('subject'));


            // Dynamic elementlarni tozalash va asosiy qiymat qo'shish
            function resetDropdown(element, placeholder) {
                $(element).empty().append(`<option value="">${placeholder}</option>`);
            }

            // Ajax orqali dropdownni to'ldirish
            function populateDropdown(url, params, element, callback) {
                $.ajax({
                    url: url,
                    type: 'GET',
                    data: params,
                    success: function (data) {
                        $.each(data, function (key, value) {
                            $(element).append(`<option value="${key}">${value}</option>`);
                        });
                        if (callback) callback(data);
                    }
                });
            }

            // Bo'lim o'zgarishi
            $('#department').change(function () {
                const departmentId = $(this).val();
                resetDropdown('#group', 'Guruhni tanlang');
                resetDropdown('#semester', 'Semestrni tanlang');
                resetDropdown('#subject', 'Fanni tanlang');

                if (departmentId) {
                    populateDropdown(
                        '{{ route("admin.get-groups-by-department") }}', {
                        department_id: departmentId
                    },
                        '#group',
                        () => {
                            if (selectedGroup) {
                                $('#group').val(selectedGroup).trigger('change');
                            } else {
                                $('#group').trigger('change.select2');
                            }
                        }
                    );
                }
            });

            // Guruh o'zgarishi
            $('#group').change(function () {
                const groupId = $(this).val();
                resetDropdown('#semester', 'Semestrni tanlang');
                resetDropdown('#subject', 'Fanni tanlang');

                if (groupId) {

                    $.ajax({
                        url: '{{ route("admin.get-semesters-new") }}',
                        type: 'GET',
                        data: {
                            group_id: groupId
                        },
                        success: function (data) {
                            $.each(data, function (index, semester) {
                                const selected = (selectedSemester && semester.id ==
                                    selectedSemester) || (!selectedSemester &&
                                        semester
                                            .current) ? 'selected' : '';
                                $('#semester').append(
                                    `<option value="${semester.id}" ${selected}>${semester.name}</option>`
                                );
                            });
                            $('#semester').trigger('change.select2');
                            $('#semester').trigger('change');
                        }
                    });


                }
            });

            // Semestr o'zgarishi
            $('#semester').change(function () {
                console.log("seme");

                const semesterId = $(this).val();
                const groupId = $('#group').val();
                resetDropdown('#subject', 'Fanni tanlang');

                if (semesterId && groupId) {
                    populateDropdown(
                        '{{ route("admin.get-subjects-new") }}', {
                        semester_id: semesterId,
                        group_id: groupId
                    },
                        '#subject',
                        () => {
                            if (selectedSubject) {
                                $('#subject').val(selectedSubject).trigger('change.select2');
                            } else {
                                $('#subject').trigger('change.select2');
                            }
                        }
                    );
                }
            });
            console.log("{{request('department')}}");

            @if(request('department'))

                $('#department').val("{{ request('department') }}")
                    .trigger('change');
                $('#department').trigger('change.select2');

            @endif
        });
    </script>


    <style>
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
