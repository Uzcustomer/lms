<x-teacher-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Test') }}
            </h2>

            <a href="{{ route('teacher.examtest.create') }}" class="btn btn-primary">
                {{ __('Test qo\'shish') }}
            </a>

        </div>
    </x-slot>

    @if(session('error'))
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
            <strong class="font-bold">Xato!</strong>
            <span class="block sm:inline">{{ session('error') }}</span>
        </div>
    @endif


    @if(session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
            <strong class="font-bold">Muvaffaqiyatli!</strong>
            <span class="block sm:inline">{{ session('success') }}</span>
        </div>
    @endif


    @if ($errors->any())
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
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
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                <!-- Search Form -->

                <form id="search-form" method="GET" action="{{ route('teacher.examtest.index') }}"
                    class="p-6 bg-gray-50">
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                        <div>
                            <label for="full_name" class="block text-sm font-medium text-gray-700">F.I.Sh</label>
                            <input type="text" name="full_name" id="full_name" value="{{ request('full_name') }}"
                                placeholder="Obidov Zohid"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                        </div>
                        <div>
                            <label class=" block mb-2">Guruh</label>
                            <select name="group" id="group" class="w-full rounded-md select2">
                                <option value="">Avval bo'limni tanlang</option>
                                @foreach($groups as $groupItem)
                                    <option value="{{ $groupItem->id }}" {{ request('group') == $groupItem->id ? 'selected' : '' }}>
                                        {{ $groupItem->name }}
                                    </option>
                                @endforeach
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
                        <div>
                            <label class="block mb-2">Holati</label>
                            <select name="status" id="status" class="w-full rounded-md select2">
                                <option value="2" {{ (request('status') ?? 2) == 2 ? 'selected' : '' }}>Hammasi</option>
                                <option value="0" {{ (request('status') ?? 2) == 0 ? 'selected' : '' }}>Baholanmagan
                                </option>
                                <option value="1" {{ request('status') == 1 ? 'selected' : '' }}>Baholangan</option>
                            </select>
                        </div>
                        <div>
                            <label class="block mb-2">Sana</label>
                            <input type="date" name="start_date" value="{{ request('start_date')}}"
                                class="border-2 border-gray-300 rounded-lg p-2 focus:outline-none focus:ring-2 focus:ring-blue-500" />
                        </div>

                    </div>
                    <div class="mt-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                        <div class="w-full sm:w-auto">
                            <label for="per_page" class="block sm:inline text-sm font-medium text-gray-700 mr-2">Har bir
                                sahifada:</label>
                            <select id="per_page" name="per_page"
                                class="select2 mt-1 sm:mt-0 block w-full sm:w-auto rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                @foreach([10, 25, 50, 100] as $pageSize)
                                    <option value="{{ $pageSize }}" {{ request('per_page', 50) == $pageSize ? 'selected' : '' }}>
                                        {{ $pageSize }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit"
                            class="w-full sm:w-auto inline-flex justify-center items-center px-4 py-2 bg-blue-500 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-600 active:bg-blue-700 focus:outline-none focus:border-blue-700 focus:ring focus:ring-blue-200 disabled:opacity-25 transition shadow-md hover:shadow-lg">
                            Qidirish
                        </button>
                    </div>
                </form>

                <div>
                    <div class="overflow-x-auto">
                        <div class="inline-block min-w-full">
                            @if($examtests->isEmpty())
                                <div class="text-gray-500 text-center">
                                    <p>Hozircha test mavjud emas.</p>
                                </div>
                            @else
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th
                                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                ID
                                            </th>
                                            <th
                                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Fakultet
                                            </th>
                                            <th
                                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Guruh
                                            </th>
                                            <th
                                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Fan
                                            </th>
                                            <th
                                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Fan id
                                            </th>
                                            <th
                                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Semester
                                            </th>

                                            <th
                                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Sana
                                            </th>
                                            <th
                                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Muddat
                                            </th>
                                            <th
                                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Yaratgan Admin
                                            </th>
                                            <th
                                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Baholagan shaxs
                                            </th>
                                            <th
                                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Yaratilgan sana va vaqt
                                            </th>

                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        @foreach ($examtests as $examtest)
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                    {{$examtest->id}}
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                    {{$examtest->deportment_name}}
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                    {{$examtest->group_name}}
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                    {{$examtest->subject_name}}
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                    {{$examtest->subject->subject_id}}
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                    {{$examtest->semester_name}}
                                                </td>

                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                    {{$examtest->start_date}}
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                    {{$examtest->deadline}}
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                    {{$examtest->user?->name ?? $examtest->dekan->full_name}}
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                    {{ $examtest->grade_teacher ?? "???" }}
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                    {{ $examtest->created_at->format('d-m-Y H:i:s') }}
                                                </td>

                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>

                                <div class="mt-4">
                                    {{ $examtests->links() }}
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
                        '{{ route("teacher.get-groups-by-department") }}', {
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
                        url: '{{ route("teacher.get-semesters-new") }}',
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
                        '{{ route("teacher.get-subjects-new") }}', {
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