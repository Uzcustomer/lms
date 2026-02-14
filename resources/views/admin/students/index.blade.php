<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            {{ __('Talabalar') }}
        </h2>
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
            <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <form id="search-form" method="GET" action="{{ route('admin.students.index') }}" class="mb-4">
                        <div class="form-row" style="display:flex; flex-wrap:wrap; gap:12px; margin-bottom:12px;">
                            <div style="flex:1; min-width:200px;">
                                <label for="full_name" class="block text-sm font-medium text-gray-700 mb-1">F.I.Sh</label>
                                <input type="text" name="full_name" id="full_name" value="{{ request('full_name') }}"
                                       placeholder="Obidov Zohid"
                                       class="form-control w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50" style="height:38px;">
                            </div>
                            <div style="flex:1; min-width:160px;">
                                <label for="student_id_number" class="block text-sm font-medium text-gray-700 mb-1">Talaba ID</label>
                                <input type="text" name="student_id_number" id="student_id_number" value="{{ request('student_id_number') }}"
                                       placeholder="1234"
                                       class="form-control w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50" style="height:38px;">
                            </div>
                            <div style="flex:1; min-width:200px;">
                                <label for="department" class="block text-sm font-medium text-gray-700 mb-1">Fakultet</label>
                                <select name="department" id="department" class="select2 form-control w-full">
                                    <option value="">Fakultetni tanlang</option>
                                    @foreach($departments as $department)
                                        <option value="{{ $department['id'] }}" {{ request('department') == $department['id'] ? 'selected' : '' }}>
                                            {{ $department['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div style="flex:1; min-width:200px;">
                                <label for="specialty" class="block text-sm font-medium text-gray-700 mb-1">Yo'nalish</label>
                                <select name="specialty" id="specialty" class="select2 form-control w-full">
                                    <option value="">Yo'nalishni tanlang</option>
                                    @foreach($specialties as $specialty)
                                        <option value="{{ $specialty['id'] }}" {{ request('specialty') == $specialty['id'] ? 'selected' : '' }}>
                                            {{ $specialty['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="form-row" style="display:flex; flex-wrap:wrap; gap:12px; align-items:flex-end;">
                            <div style="flex:1; min-width:150px;">
                                <label for="level_code" class="block text-sm font-medium text-gray-700 mb-1">Kurs</label>
                                <select name="level_code" id="level_code" class="select2 form-control w-full">
                                    <option value="">Kursni tanlang</option>
                                    @foreach(['11' => '1-kurs', '12' => '2-kurs', '13' => '3-kurs', '14' => '4-kurs', '15' => '5-kurs', '16' => '6-kurs'] as $value => $label)
                                        <option value="{{ $value }}" {{ request('level_code') == $value ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div style="flex:1; min-width:150px;">
                                <label for="semester_code" class="block text-sm font-medium text-gray-700 mb-1">Semestr</label>
                                <select name="semester_code" id="semester_code" class="select2 form-control w-full">
                                    <option value="">Semesterni tanlang</option>
                                    @foreach($semesters as $semester)
                                        <option value="{{ $semester['id'] }}" {{ request('semester_code') == $semester['id'] ? 'selected' : '' }}>
                                            {{ $semester['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div style="flex:1; min-width:150px;">
                                <label for="group" class="block text-sm font-medium text-gray-700 mb-1">Guruh</label>
                                <select name="group" id="group" class="select2 form-control w-full">
                                    <option value="">Guruhni tanlang</option>
                                    @foreach($groups as $group)
                                        <option value="{{ $group['id'] }}" {{ request('group') == $group['id'] ? 'selected' : '' }}>
                                            {{ $group['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div style="flex:1; min-width:150px;">
                                <label for="education_type" class="block text-sm font-medium text-gray-700 mb-1">Ta'lim turi</label>
                                <select name="education_type" id="education_type" class="select2 form-control w-full">
                                    <option value="">Ta'lim turini tanlang</option>
                                    @foreach($educationTypes as $educationType)
                                        <option value="{{ $educationType['id'] }}" {{ request('education_type') == $educationType['id'] ? 'selected' : '' }}>
                                            {{ $educationType['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div style="flex:0 0 auto; min-width:100px;">
                                <label for="per_page" class="block text-sm font-medium text-gray-700 mb-1">Sahifada</label>
                                <select id="per_page" name="per_page" class="form-control w-full rounded-md border-gray-300 shadow-sm" style="height:38px;">
                                    @foreach([10, 25, 50, 100] as $pageSize)
                                        <option value="{{ $pageSize }}" {{ request('per_page', 50) == $pageSize ? 'selected' : '' }}>
                                            {{ $pageSize }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div style="flex:0 0 auto;">
                                <button type="submit" class="btn btn-primary px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-sm font-medium" style="height:38px;">
                                    Qidirish
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                @php
                    $userRoles = auth()->user()?->getRoleNames()->toArray() ?? [];
                    $activeRole = session('active_role', $userRoles[0] ?? '');
                    if (!in_array($activeRole, $userRoles) && count($userRoles) > 0) {
                        $activeRole = $userRoles[0];
                    }
                    $canBulkReset = in_array($activeRole, ['registrator_ofisi', 'superadmin', 'admin', 'kichik_admin']);
                @endphp

                @if($canBulkReset)
                <div id="bulkResetBar" style="display:none;" class="bg-yellow-50 border-b border-yellow-200 px-4 py-3 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <span class="text-sm font-medium text-yellow-800">
                            <span id="selectedCount">0</span> ta talaba tanlandi
                        </span>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" onclick="clearSelection()" class="px-3 py-1.5 text-xs rounded border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">
                            Bekor qilish
                        </button>
                        <button type="button" onclick="openBulkResetModal()" class="px-3 py-1.5 text-xs rounded font-semibold text-white" style="background-color: #f59e0b;">
                            Tanlanganlarga parol tiklash
                        </button>
                    </div>
                </div>
                @endif

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                        <tr>
                            @if($canBulkReset)
                            <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <input type="checkbox" id="selectAll" onchange="toggleSelectAll()" class="rounded border-gray-300 text-yellow-500 focus:ring-yellow-400">
                            </th>
                            @endif
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">F.I.Sh</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">HEMIS ID</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Talaba ID</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ta'lim turi</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fakultet</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Yo'nalish</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kurs</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Semestr</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Guruh</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amallar</th>
                        </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($students as $student)
                            <tr class="hover:bg-gray-50">
                                @if($canBulkReset)
                                <td class="px-3 py-2 whitespace-nowrap text-center">
                                    <input type="checkbox" class="student-checkbox rounded border-gray-300 text-yellow-500 focus:ring-yellow-400"
                                           value="{{ $student->id }}" onchange="updateBulkBar()">
                                </td>
                                @endif
                                <td class="px-3 py-2 whitespace-nowrap">
                                    <a href="{{ route('admin.students.show', $student->id) }}" class="text-blue-600 hover:text-blue-900 font-medium">
                                        {{ $student->full_name }}
                                    </a>
                                </td>
                                <td class="px-3 py-2 whitespace-nowrap text-gray-500">{{ $student->hemis_id }}</td>
                                <td class="px-3 py-2 whitespace-nowrap text-gray-500">{{ $student->student_id_number }}</td>
                                <td class="px-3 py-2 whitespace-nowrap">
                                    <div class="text-gray-900">{{ $student->education_type_name }}</div>
                                    <div class="text-xs text-gray-400">{{ $student->education_form_name }}</div>
                                </td>
                                <td class="px-3 py-2 whitespace-nowrap text-gray-500">{{ $student->department_name }}</td>
                                <td class="px-3 py-2 whitespace-nowrap">
                                    <div class="text-gray-900" title="{{ $student->specialty_name }}">{{ Str::limit($student->specialty_name, 25) }}</div>
                                </td>
                                <td class="px-3 py-2 whitespace-nowrap text-gray-500">{{ $student->level_name }}</td>
                                <td class="px-3 py-2 whitespace-nowrap text-gray-500">{{ $student->semester_name }}</td>
                                <td class="px-3 py-2 whitespace-nowrap text-gray-500">{{ $student->group_name }}</td>
                                <td class="px-3 py-2 whitespace-nowrap">
                                    <div class="flex items-center gap-1">
                                        <button type="button"
                                                class="px-2 py-1 text-xs rounded"
                                                style="background-color: #f59e0b; color: white;"
                                                onclick="openResetModal('{{ $student->id }}', '{{ addslashes($student->full_name) }}', '{{ $student->student_id_number }}')">
                                            Parolni tiklash
                                        </button>
                                        @if(auth()->user()?->hasRole('superadmin'))
                                            <form action="{{ route('admin.impersonate.student', $student->id) }}" method="POST" onsubmit="return confirm('{{ addslashes($student->full_name) }} sifatida tizimga kirasizmi?')">
                                                @csrf
                                                <button type="submit" class="px-2 py-1 text-xs rounded" style="background-color: #3b82f6; color: white;">
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

    @if($canBulkReset)
    <!-- Bulk parolni tiklash modal -->
    <div id="bulkResetModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; justify-content:center; align-items:center;">
        <div style="background:white; border-radius:12px; padding:24px; max-width:440px; width:90%; box-shadow:0 20px 60px rgba(0,0,0,0.3);">
            <h3 style="font-size:16px; font-weight:600; color:#1f2937; margin-bottom:4px;">Bulk parolni tiklash</h3>
            <p id="bulkModalInfo" style="font-size:13px; color:#6b7280; margin-bottom:16px;"></p>

            <div style="font-size:13px; color:#374151; margin-bottom:12px; padding:10px 12px; border:2px solid #2563eb; border-radius:8px; background:#eff6ff;">
                <div style="font-weight:600; color:#1e40af;">Avtomatik (Talaba ID)</div>
                <div style="font-size:12px; color:#3b82f6; margin-top:2px;">Har bir talabaning ID raqami parol sifatida o'rnatiladi</div>
            </div>

            <form id="bulkResetForm" method="POST" action="{{ route('admin.students.bulk-reset-password') }}">
                @csrf
                <div id="bulkStudentInputs"></div>

                <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:16px;">
                    <button type="button" onclick="closeBulkResetModal()"
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
    @endif

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

        // Bulk reset functions
        function getSelectedIds() {
            return Array.from(document.querySelectorAll('.student-checkbox:checked')).map(cb => cb.value);
        }

        function updateBulkBar() {
            var selected = getSelectedIds();
            var bar = document.getElementById('bulkResetBar');
            if (bar) {
                bar.style.display = selected.length > 0 ? 'flex' : 'none';
                document.getElementById('selectedCount').textContent = selected.length;
            }
            var selectAll = document.getElementById('selectAll');
            if (selectAll) {
                var allCheckboxes = document.querySelectorAll('.student-checkbox');
                selectAll.checked = allCheckboxes.length > 0 && selected.length === allCheckboxes.length;
                selectAll.indeterminate = selected.length > 0 && selected.length < allCheckboxes.length;
            }
        }

        function toggleSelectAll() {
            var checked = document.getElementById('selectAll').checked;
            document.querySelectorAll('.student-checkbox').forEach(function(cb) {
                cb.checked = checked;
            });
            updateBulkBar();
        }

        function clearSelection() {
            document.querySelectorAll('.student-checkbox').forEach(function(cb) {
                cb.checked = false;
            });
            var selectAll = document.getElementById('selectAll');
            if (selectAll) selectAll.checked = false;
            updateBulkBar();
        }

        function openBulkResetModal() {
            var ids = getSelectedIds();
            if (ids.length === 0) return;

            document.getElementById('bulkModalInfo').textContent = ids.length + ' ta talaba uchun parol tiklanadi. Davom etasizmi?';

            var container = document.getElementById('bulkStudentInputs');
            container.innerHTML = '';
            ids.forEach(function(id) {
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'student_ids[]';
                input.value = id;
                container.appendChild(input);
            });

            document.getElementById('bulkResetModal').style.display = 'flex';
        }

        function closeBulkResetModal() {
            document.getElementById('bulkResetModal').style.display = 'none';
        }

        var bulkModal = document.getElementById('bulkResetModal');
        if (bulkModal) {
            bulkModal.addEventListener('click', function(e) {
                if (e.target === this) closeBulkResetModal();
            });
        }
    </script>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
        $(document).ready(function() {
            $('#department, #specialty, #level_code, #semester_code, #group, #education_type').select2({
                width: '100%',
                allowClear: true,
                placeholder: function() {
                    return $(this).find('option:first').text();
                }
            });

            $('select.select2').on('change', function() {
                $('#search-form').submit();
            });

            $('#per_page').on('change', function() {
                $('#search-form').submit();
            });

            var searchTimeout;
            $('input[type="text"]').on('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(function() {
                    $('#search-form').submit();
                }, 2000);
            });
        });
    </script>

    <style>
        .select2-container { min-width: 100% !important; }
        .select2-container .select2-selection--single {
            height: 38px !important;
            border: 1px solid #D1D5DB !important;
            border-radius: 0.375rem !important;
        }
        .select2-container .select2-selection--single .select2-selection__rendered {
            line-height: 36px !important;
            padding-left: 12px !important;
            color: #374151 !important;
        }
        .select2-container .select2-selection--single .select2-selection__arrow {
            height: 36px !important;
        }
    </style>
</x-app-layout>
