<x-teacher-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Talabalar') }}
            </h2>
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

    <div class="py-12">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                <!-- Search Form -->

                <form id="search-form" method="GET" action="{{ route('teacher.students') }}" class="p-6 bg-gray-50">
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                        <div>
                            <label for="student_id_number" class="block text-sm font-medium text-gray-700">Talaba ID</label>
                            <input type="text" name="student_id_number" id="student_id_number" placeholder="1234"
                                   value="{{ request('student_id_number') }}"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                        </div>
                        <div>
                            <label for="full_name" class="block text-sm font-medium text-gray-700">F.I.Sh</label>
                            <input type="text" name="full_name" id="full_name" value="{{ request('full_name') }}"
                                   placeholder="Obidov Zohid"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                        </div>
                        <div>
                            <label for="level_code" class="block text-sm font-medium text-gray-700">Kurs</label>
                            <select name="level_code" id="level_code"
                                    class="select2 mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                <option value="">Kursni tanlang</option>
                                @foreach(['11' => '1-kurs', '12' => '2-kurs', '13' => '3-kurs', '14' => '4-kurs', '15' => '5-kurs', '16' => '6-kurs'] as $value => $label)
                                    <option value="{{ $value }}" {{ request('level_code') == $value ? 'selected' : '' }}>
                                        {{ $label }} (Level {{ $value }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="curriculum" class="block text-sm font-medium text-gray-700">O'quv reja</label>
                            <select name="curriculum" id="curriculum"
                                    class="select2 mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                <option value="">O'quv rejani tanlang</option>
                                @foreach($curriculums as $curriculum)
                                    <option value="{{ $curriculum->curricula_hemis_id }}" {{ request('curriculum') == $curriculum->curricula_hemis_id ? 'selected' : '' }}>
                                        {{ $curriculum->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="department" class="block text-sm font-medium text-gray-700">Fakultet</label>
                            <select name="department" id="department"
                                    class="select2 mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                <option value="">Fakultetni tanlang</option>
                                @foreach($departments as $department)
                                    <option value="{{ $department['id'] }}" {{ request('department') == $department['id'] ? 'selected' : '' }}>
                                        {{ $department['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="semester_code" class="block text-sm font-medium text-gray-700">Semester</label>
                            <select name="semester_code" id="semester_code"
                                    class="select2 mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                <option value="">Semesterni tanlang</option>
                                @foreach($semesters as $semester)
                                    <option value="{{ $semester['id'] }}" {{ request('semester_code') == $semester['id'] ? 'selected' : '' }}>
                                        {{ $semester['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="specialty" class="block text-sm font-medium text-gray-700">Mutaxassislik</label>
                            <select name="specialty" id="specialty"
                                    class="select2 mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                <option value="">Mutaxassislikni tanlang</option>
                                @foreach($specialties as $specialty)
                                    <option value="{{ $specialty['id'] }}" {{ request('specialty') == $specialty['id'] ? 'selected' : '' }}>
                                        {{ $specialty['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="group" class="block text-sm font-medium text-gray-700">Guruh</label>
                            <select name="group" id="group"
                                    class="select2 mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                <option value="">Guruhni tanlang</option>
                                @foreach($groups as $group)
                                    <option value="{{ $group['id'] }}" {{ request('group') == $group['id'] ? 'selected' : '' }}>
                                        {{ $group['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="mt-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                        <div class="w-full sm:w-auto">
                            <label for="per_page" class="block sm:inline text-sm font-medium text-gray-700 mr-2">Har bir sahifada:</label>
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
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">
                                        Foto
                                    </th>
                                    <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">
                                        Talaba
                                    </th>
                                    <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">
                                        Yo'nalish
                                    </th>
                                    <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">
                                        O'quv yili
                                    </th>
                                    <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">
                                        Ta'lim turi
                                    </th>
                                    <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">
                                        Semester
                                    </th>
                                    <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">
                                        O'quv rejasi
                                    </th>
                                    <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">
                                        Fakultet
                                    </th>
                                    <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">
                                        Guruh
                                    </th>
                                    <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">
                                        O'rtacha GPA
                                    </th>
                                    <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">
                                        Yangilangan vaqt
                                    </th>
                                </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($students as $student)
                                    <tr>
                                        <td class="px-2 py-2 whitespace-nowrap">
                                            <div class="flex-shrink-0 h-6 w-6">
                                                @if($student->image)
                                                    <img class="h-6 w-6 rounded-full object-cover"
                                                         src="{{ $student->image }}" alt="{{ $student->name }}'s avatar"
                                                         onerror="this.onerror=null; this.src='{{ asset('path/to/default-avatar.png') }}'; this.alt='Default avatar'">
                                                @else
                                                    <div
                                                        class="h-6 w-6 rounded-full bg-gray-300 flex items-center justify-center">
                                                        <span
                                                            class="text-xs font-medium text-gray-600">{{ strtoupper(substr($student->name, 0, 2)) }}</span>
                                                    </div>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-2 py-2 whitespace-nowrap">
                                            <a href="{{ route('admin.students.show', $student->id) }}" class="text-blue-600 hover:text-blue-800 hover:underline font-medium">{{ $student->full_name }}</a>
                                            <div class="text-xs text-gray-500">{{ $student->student_id_number }}</div>
                                        </td>
                                        <td class="px-2 py-2 whitespace-nowrap">
                                            <div
                                                class="text-gray-900">{{ Str::limit($student->specialty_name, 20) }}</div>
                                            <div class="text-xs text-gray-500">{{$student->specialty_code}}</div>
                                        </td>
                                        <td class="px-2 py-2 whitespace-nowrap text-gray-500">{{ $student->education_year_name }}</td>
                                        <td class="px-2 py-2 whitespace-nowrap">
                                            <div class="text-gray-900">{{ $student->education_type_name }}</div>
                                            <div class="text-xs text-gray-500">{{$student->education_form_name}}</div>
                                        </td>
                                        <td class="px-2 py-2 whitespace-nowrap">
                                            <div class="text-gray-900">{{ $student->semester_name }}</div>
                                            <div class="text-xs text-gray-500">{{ $student->level_name }}</div>
                                        </td>
                                        <td class="px-2 py-2 text-xs whitespace-nowrap text-gray-500">{{ $student->semester->curriculum->name ?? "Noma'lum"}}</td>
                                        <td class="px-2 py-2 whitespace-nowrap text-gray-500">{{$student->department_name }}</td>
                                        <td class="px-2 py-2 whitespace-nowrap">
                                            <div class="text-gray-900">{{ $student->group_name }}</div>
                                            <div
                                                class="text-xs text-gray-500">{{ $student->group->education_lang_name }}</div>
                                        </td>
                                        <td class="px-2 py-2 whitespace-nowrap text-gray-500">{{ $student->avg_gpa }}</td>
                                        <td class="px-2 py-2 whitespace-nowrap text-gray-500">{{ format_datetime($student->updated_at) }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="mt-4">
                        <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
                            <div class="flex-1 flex justify-between sm:hidden">
                                {{ $students->appends(request()->query())->links('pagination::simple-tailwind') }}
                            </div>
                            <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                                <div>
                                    <p class="text-sm text-gray-700 leading-5">
                                        {!! __('Showing') !!}
                                        <span class="font-medium">{{ $students->firstItem() }}</span>
                                        {!! __('to') !!}
                                        <span class="font-medium">{{ $students->lastItem() }}</span>
                                        {!! __('of') !!}
                                        <span class="font-medium">{{ $students->total() }}</span>
                                        {!! __('results') !!}
                                    </p>
                                </div>
                                <div>
                                    {{ $students->appends(request()->query())->links('pagination::tailwind') }}
                                </div>
                            </div>
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
        $(document).ready(function() {
            $('.select2').each(function() {
                $(this).select2({
                    theme: 'classic',
                    width: '100%',
                    allowClear: true,
                    placeholder: $(this).find('option:first').text()
                }).on('select2:open', function() {
                    setTimeout(() => {
                        document.querySelector('.select2-container--open .select2-search__field')?.focus();
                    }, 0);
                });
            });

            function delayedSubmit() {
                clearTimeout(window.searchTimeout);
                window.searchTimeout = setTimeout(function() {
                    $('#search-form').submit();
                }, 2000);
            }

            $('select').on('change', function() {
                $('#search-form').submit();
            });

            $('input[type="text"]').on('input', delayedSubmit);

            $('#per_page').on('change', function() {
                $('#search-form').submit();
            });

            $('.select2-clear-btn').on('click', function(e) {
                e.preventDefault();
                var targetSelect = $(this).data('target');
                $('#' + targetSelect).val(null).trigger('change');
                $('#search-form').submit();
            });
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
</x-teacher-app-layout>
