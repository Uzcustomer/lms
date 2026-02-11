<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Talabalar') }}
            </h2>
            <form action="{{route('admin.student-import-grades')}}" method="POST" enctype="multipart/form-data" class="flex items-center">
                @csrf
                <div class="relative mr-4">
                    <input type="file" name="file" accept=".xlsx, .xls, .csv" class="sr-only" id="file-upload">
                    <label for="file-upload" class="cursor-pointer bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm leading-4 font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Faylni tanlang
                    </label>
                    <span id="file-name" class="ml-2 text-sm text-gray-600"></span>
                </div>
                <button type="submit"
                        class="inline-flex justify-center items-center px-4 py-2 bg-blue-500 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-600 active:bg-blue-700 focus:outline-none focus:border-blue-700 focus:ring focus:ring-blue-200 disabled:opacity-25 transition shadow-md hover:shadow-lg">
                    Baholarni excel orqali yuklash
                </button>
            </form>
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

                <form id="search-form" method="GET" action="{{ route('admin.students.index') }}" class="p-6 bg-gray-50">
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
                                    <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">
                                        Amallar
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
                                            <a href="{{ route('admin.student-performances.index', $student->hemis_id) }}"
                                               class="text-blue-600 hover:text-blue-900">
                                               {{ $student->full_name}}
                                            </a>
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
                                        <td class="px-2 py-2 whitespace-nowrap">
                                            <div class="flex items-center gap-1">
                                                <button type="button"
                                                        class="px-2 py-1 text-xs rounded"
                                                        style="background-color: #f59e0b; color: white;"
                                                        onclick="openResetModal('{{ $student->id }}', '{{ addslashes($student->full_name) }}', '{{ $student->student_id_number }}')">
                                                    Parolni tiklash
                                                </button>
                                                @if(auth()->user() && auth()->user()->hasRole('superadmin'))
                                                    <form action="{{ route('admin.impersonate.student', $student->id) }}" method="POST" onsubmit="return confirm('{{ addslashes($student->full_name) }} sifatida tizimga kirasizmi?')">
                                                        @csrf
                                                        <button type="submit"
                                                                class="px-2 py-1 text-xs rounded"
                                                                style="background-color: #3b82f6; color: white;">
                                                            Login as
                                                        </button>
                                                    </form>
                                                @endif
                                            </div>
                                        </td>
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



    <!-- Parolni tiklash modal -->
    <div id="resetModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; justify-content:center; align-items:center;">
        <div style="background:white; border-radius:12px; padding:24px; max-width:440px; width:90%; box-shadow:0 20px 60px rgba(0,0,0,0.3);">
            <h3 style="font-size:16px; font-weight:600; color:#1f2937; margin-bottom:4px;">Parolni tiklash</h3>
            <p id="modalStudentName" style="font-size:13px; color:#6b7280; margin-bottom:16px;"></p>

            <form id="resetForm" method="POST">
                @csrf
                <div style="margin-bottom:12px;">
                    <label style="display:flex; align-items:center; padding:10px 12px; border:2px solid #2563eb; border-radius:8px; cursor:pointer; background:#eff6ff; margin-bottom:8px;">
                        <input type="radio" name="password_type" value="auto" checked
                               onchange="toggleManualInput()" style="margin-right:10px;">
                        <div>
                            <div style="font-size:13px; font-weight:600; color:#1e40af;">Avtomatik (Talaba ID)</div>
                            <div id="autoPasswordDisplay" style="font-size:12px; color:#3b82f6; margin-top:2px;"></div>
                        </div>
                    </label>

                    <label style="display:flex; align-items:center; padding:10px 12px; border:2px solid #d1d5db; border-radius:8px; cursor:pointer;">
                        <input type="radio" name="password_type" value="manual"
                               onchange="toggleManualInput()" style="margin-right:10px;">
                        <div style="font-size:13px; font-weight:600; color:#374151;">Boshqa parol kiritish</div>
                    </label>
                </div>

                <div id="manualPasswordField" style="display:none; margin-bottom:12px;">
                    <label style="font-size:12px; font-weight:500; color:#374151; display:block; margin-bottom:4px;">Parolni kiriting (kamida 4 belgi)</label>
                    <input type="text" name="custom_password" id="customPasswordInput"
                           style="width:100%; padding:8px 12px; border:1px solid #d1d5db; border-radius:6px; font-size:14px; box-sizing:border-box;"
                           placeholder="Yangi parol..." minlength="4">
                </div>

                <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:16px;">
                    <button type="button" onclick="closeResetModal()"
                            style="padding:8px 16px; border:1px solid #d1d5db; border-radius:6px; background:white; color:#374151; font-size:13px; cursor:pointer;">
                        Bekor qilish
                    </button>
                    <button type="submit"
                            style="padding:8px 16px; border:none; border-radius:6px; background:#f59e0b; color:white; font-size:13px; font-weight:600; cursor:pointer;">
                        Tasdiqlash
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openResetModal(studentId, studentName, studentIdNumber) {
            document.getElementById('modalStudentName').textContent = studentName;
            document.getElementById('autoPasswordDisplay').textContent = 'Parol: ' + studentIdNumber;
            document.getElementById('resetForm').action = '{{ url("admin/students") }}/' + studentId + '/reset-local-password';
            document.getElementById('customPasswordInput').value = '';
            document.querySelector('input[name="password_type"][value="auto"]').checked = true;
            toggleManualInput();
            document.getElementById('resetModal').style.display = 'flex';
        }

        function closeResetModal() {
            document.getElementById('resetModal').style.display = 'none';
        }

        function toggleManualInput() {
            var isManual = document.querySelector('input[name="password_type"]:checked').value === 'manual';
            document.getElementById('manualPasswordField').style.display = isManual ? 'block' : 'none';
            document.getElementById('customPasswordInput').required = isManual;

            var labels = document.getElementById('resetForm').querySelectorAll('label');
            if (isManual) {
                labels[0].style.borderColor = '#d1d5db';
                labels[0].style.background = 'white';
                labels[1].style.borderColor = '#2563eb';
                labels[1].style.background = '#eff6ff';
            } else {
                labels[0].style.borderColor = '#2563eb';
                labels[0].style.background = '#eff6ff';
                labels[1].style.borderColor = '#d1d5db';
                labels[1].style.background = 'white';
            }
        }

        document.getElementById('resetModal').addEventListener('click', function(e) {
            if (e.target === this) closeResetModal();
        });
    </script>

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
        document.getElementById('file-upload').addEventListener('change', function(e) {
            var fileName = e.target.files[0].name;
            document.getElementById('file-name').textContent = fileName;
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
