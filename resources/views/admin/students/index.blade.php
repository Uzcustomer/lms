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

    <div class="py-4">
        <div class="max-w-full mx-auto sm:px-4 lg:px-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">

                <!-- Filters -->
                <form id="search-form" method="GET" action="{{ route('admin.students.index') }}">
                    <div class="filter-container">
                        <!-- Row 1 -->
                        <div class="filter-row">
                            <div class="filter-item" style="min-width: 160px;">
                                <label class="filter-label"><span class="fl-dot" style="background:#3b82f6;"></span> Ta'lim turi</label>
                                <select id="education_type" name="education_type" class="select2" style="width: 100%;">
                                    <option value="">Barchasi</option>
                                    @foreach($educationTypes as $type)
                                        <option value="{{ $type->education_type_code }}" {{ request('education_type') == $type->education_type_code ? 'selected' : '' }}>
                                            {{ $type->education_type_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="filter-item" style="flex: 1; min-width: 200px;">
                                <label class="filter-label"><span class="fl-dot" style="background:#10b981;"></span> Fakultet</label>
                                <select id="department" name="department" class="select2" style="width: 100%;">
                                    <option value="">Barchasi</option>
                                </select>
                            </div>
                            <div class="filter-item" style="flex: 1; min-width: 240px;">
                                <label class="filter-label"><span class="fl-dot" style="background:#06b6d4;"></span> Yo'nalish</label>
                                <select id="specialty" name="specialty" class="select2" style="width: 100%;">
                                    <option value="">Barchasi</option>
                                </select>
                            </div>
                            <div class="filter-item" style="min-width: 90px;">
                                <label class="filter-label"><span class="fl-dot" style="background:#94a3b8;"></span> Sahifada</label>
                                <select id="per_page" name="per_page" class="select2" style="width: 100%;">
                                    @foreach([10, 25, 50, 100] as $ps)
                                        <option value="{{ $ps }}" {{ request('per_page', 50) == $ps ? 'selected' : '' }}>{{ $ps }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <!-- Row 2 -->
                        <div class="filter-row">
                            <div class="filter-item" style="min-width: 140px;">
                                <label class="filter-label"><span class="fl-dot" style="background:#8b5cf6;"></span> Kurs</label>
                                <select id="level_code" name="level_code" class="select2" style="width: 100%;">
                                    <option value="">Barchasi</option>
                                </select>
                            </div>
                            <div class="filter-item" style="min-width: 150px;">
                                <label class="filter-label"><span class="fl-dot" style="background:#14b8a6;"></span> Semestr</label>
                                <select id="semester_code" name="semester_code" class="select2" style="width: 100%;">
                                    <option value="">Barchasi</option>
                                </select>
                            </div>
                            <div class="filter-item" style="min-width: 170px;">
                                <label class="filter-label"><span class="fl-dot" style="background:#1a3268;"></span> Guruh</label>
                                <select id="group" name="group" class="select2" style="width: 100%;">
                                    <option value="">Barchasi</option>
                                </select>
                            </div>
                            <div class="filter-item" style="flex: 1; min-width: 200px;">
                                <label class="filter-label"><span class="fl-dot" style="background:#f59e0b;"></span> F.I.Sh</label>
                                <input type="text" name="full_name" id="full_name" value="{{ request('full_name') }}"
                                       placeholder="Obidov Zohid" class="filter-input">
                            </div>
                            <div class="filter-item" style="min-width: 140px;">
                                <label class="filter-label"><span class="fl-dot" style="background:#ef4444;"></span> Talaba ID</label>
                                <input type="text" name="student_id_number" id="student_id_number" value="{{ request('student_id_number') }}"
                                       placeholder="1234" class="filter-input">
                            </div>
                            <div class="filter-item" style="min-width: 120px;">
                                <label class="filter-label">&nbsp;</label>
                                <button type="submit" class="btn-calc">
                                    <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                    Qidirish
                                </button>
                            </div>
                        </div>
                    </div>
                </form>

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

                <div style="padding:10px 20px;background:#f8fafc;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;gap:12px;">
                    <span class="badge" style="background:linear-gradient(135deg,#2b5ea7,#3b7ddb);color:#fff;padding:6px 14px;font-size:13px;border-radius:8px;">Jami: {{ $students->total() }} ta talaba</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="student-table">
                        <thead>
                        <tr>
                            @if($canBulkReset)
                            <th style="width:40px;text-align:center;">
                                <input type="checkbox" id="selectAll" onchange="toggleSelectAll()" class="rounded border-gray-300 text-yellow-500 focus:ring-yellow-400">
                            </th>
                            @endif
                            <th>F.I.Sh</th>
                            <th>HEMIS ID</th>
                            <th>Talaba ID</th>
                            <th>Ta'lim turi</th>
                            <th>Fakultet</th>
                            <th>Yo'nalish</th>
                            <th>Kurs</th>
                            <th>Semestr</th>
                            <th>Guruh</th>
                            <th style="text-align:center;">Amallar</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($students as $student)
                            <tr>
                                @if($canBulkReset)
                                <td style="text-align:center;">
                                    <input type="checkbox" class="student-checkbox rounded border-gray-300 text-yellow-500 focus:ring-yellow-400"
                                           value="{{ $student->id }}" onchange="updateBulkBar()">
                                </td>
                                @endif
                                <td>
                                    <a href="{{ route('admin.students.show', $student->id) }}" class="student-name-link">
                                        {{ $student->full_name }}
                                    </a>
                                </td>
                                <td style="color:#64748b;">{{ $student->hemis_id }}</td>
                                <td style="color:#64748b;">{{ $student->student_id_number }}</td>
                                <td>
                                    <span class="text-cell">{{ $student->education_type_name }}</span>
                                    <span style="font-size:11px;color:#94a3b8;">{{ $student->education_form_name }}</span>
                                </td>
                                <td><span class="text-cell text-emerald">{{ $student->department_name }}</span></td>
                                <td><span class="text-cell text-cyan" title="{{ $student->specialty_name }}">{{ Str::limit($student->specialty_name, 30) }}</span></td>
                                <td><span class="badge badge-violet">{{ $student->level_name }}</span></td>
                                <td><span class="badge badge-teal">{{ $student->semester_name }}</span></td>
                                <td><span class="badge badge-indigo">{{ $student->group_name }}</span></td>
                                <td style="text-align:center;">
                                    <div style="display:flex;align-items:center;gap:4px;justify-content:center;">
                                        <button type="button"
                                                class="btn-action btn-action-yellow"
                                                onclick="openResetModal('{{ $student->id }}', '{{ addslashes($student->full_name) }}', '{{ $student->student_id_number }}')">
                                            Parol
                                        </button>
                                        @if(auth()->user()?->hasRole('superadmin'))
                                            <form action="{{ route('admin.impersonate.student', $student->id) }}" method="POST" onsubmit="return confirm('{{ addslashes($student->full_name) }} sifatida tizimga kirasizmi?')">
                                                @csrf
                                                <button type="submit" class="btn-action btn-action-blue">
                                                    Login
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

                <div style="padding:12px 20px;border-top:1px solid #e2e8f0;background:#f8fafc;display:flex;align-items:center;justify-content:between;">
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

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
        // Modal functions
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

    <script>
        // Cascading filter logic
        var initDone = false;
        var sv = {
            education_type: @json(request('education_type', '')),
            department: @json(request('department', '')),
            specialty: @json(request('specialty', '')),
            level_code: @json(request('level_code', '')),
            semester_code: @json(request('semester_code', '')),
            group: @json(request('group', ''))
        };

        function stripSpecialChars(s) { return s.replace(/[\/\(\),\-\.\s]/g, '').toLowerCase(); }
        function fuzzyMatcher(params, data) {
            if ($.trim(params.term) === '') return data;
            if (typeof data.text === 'undefined') return null;
            if (stripSpecialChars(data.text).indexOf(stripSpecialChars(params.term)) > -1) return $.extend({}, data, true);
            if (data.text.toLowerCase().indexOf(params.term.toLowerCase()) > -1) return $.extend({}, data, true);
            return null;
        }

        function fp() {
            return {
                education_type: $('#education_type').val() || '',
                department: $('#department').val() || '',
                specialty: $('#specialty').val() || '',
                level_code: $('#level_code').val() || '',
                semester_code: $('#semester_code').val() || ''
            };
        }

        function rd(el) { $(el).empty().append('<option value="">Barchasi</option>'); }

        function pd(url, params, el, selVal, cb) {
            $.get(url, params, function(d) {
                $.each(d, function(k, v) {
                    $(el).append('<option value="' + k + '">' + v + '</option>');
                });
                if (selVal) {
                    $(el).val(selVal);
                }
                $(el).trigger('change');
                if (cb) cb();
            });
        }

        // Cascade refresh functions
        function rDept() { rd('#department'); pd('{{ route("admin.students.filter.departments") }}', fp(), '#department'); }
        function rSpec() { rd('#specialty'); pd('{{ route("admin.students.filter.specialties") }}', fp(), '#specialty'); }
        function rLvl() { rd('#level_code'); pd('{{ route("admin.students.filter.levels") }}', fp(), '#level_code'); }
        function rSem() { rd('#semester_code'); pd('{{ route("admin.students.filter.semesters") }}', fp(), '#semester_code'); }
        function rGrp() { rd('#group'); pd('{{ route("admin.students.filter.groups") }}', fp(), '#group'); }

        $(document).ready(function() {
            // Select2 init
            $('.select2').each(function() {
                $(this).select2({
                    theme: 'classic',
                    width: '100%',
                    allowClear: true,
                    placeholder: $(this).find('option:first').text(),
                    matcher: fuzzyMatcher
                }).on('select2:open', function() {
                    setTimeout(function() {
                        var s = document.querySelector('.select2-container--open .select2-search__field');
                        if (s) s.focus();
                    }, 10);
                });
            });

            // Cascade handlers (only fire after init is done)
            $('#education_type').on('change', function() { if (!initDone) return; rDept(); rSpec(); rLvl(); rSem(); rGrp(); });
            $('#department').on('change', function() { if (!initDone) return; rSpec(); rGrp(); });
            $('#specialty').on('change', function() { if (!initDone) return; rGrp(); });
            $('#level_code').on('change', function() { if (!initDone) return; rSem(); rGrp(); });
            $('#semester_code').on('change', function() { if (!initDone) return; rGrp(); });

            // Initial load with saved values
            var initLoadCount = 0;
            function checkInit() { initLoadCount++; if (initLoadCount >= 5) initDone = true; }

            pd('{{ route("admin.students.filter.departments") }}',
                {education_type: sv.education_type},
                '#department', sv.department, checkInit);

            pd('{{ route("admin.students.filter.specialties") }}',
                {education_type: sv.education_type, department: sv.department},
                '#specialty', sv.specialty, checkInit);

            pd('{{ route("admin.students.filter.levels") }}',
                {education_type: sv.education_type, department: sv.department, specialty: sv.specialty},
                '#level_code', sv.level_code, checkInit);

            pd('{{ route("admin.students.filter.semesters") }}',
                {education_type: sv.education_type, level_code: sv.level_code},
                '#semester_code', sv.semester_code, checkInit);

            pd('{{ route("admin.students.filter.groups") }}',
                {education_type: sv.education_type, department: sv.department, specialty: sv.specialty, level_code: sv.level_code, semester_code: sv.semester_code},
                '#group', sv.group, checkInit);
        });
    </script>

    <style>
        .filter-container { padding: 16px 20px 12px; background: linear-gradient(135deg, #f0f4f8, #e8edf5); border-bottom: 2px solid #dbe4ef; }
        .filter-row { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 10px; align-items: flex-end; }
        .filter-row:last-child { margin-bottom: 0; }
        .filter-label { display: flex; align-items: center; gap: 5px; margin-bottom: 4px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: #475569; }
        .fl-dot { width: 7px; height: 7px; border-radius: 50%; display: inline-block; flex-shrink: 0; }

        .filter-input { width: 100%; height: 36px; padding: 0 10px; border: 1px solid #cbd5e1; border-radius: 8px; background: #fff; font-size: 0.8rem; font-weight: 500; color: #1e293b; box-shadow: 0 1px 2px rgba(0,0,0,0.04); transition: all 0.2s; box-sizing: border-box; }
        .filter-input:hover { border-color: #2b5ea7; box-shadow: 0 0 0 2px rgba(43,94,167,0.1); }
        .filter-input:focus { outline: none; border-color: #2b5ea7; box-shadow: 0 0 0 2px rgba(43,94,167,0.2); }
        .filter-input::placeholder { color: #94a3b8; }

        .btn-calc { display: inline-flex; align-items: center; gap: 8px; padding: 8px 20px; background: linear-gradient(135deg, #2b5ea7, #3b7ddb); color: #fff; border: none; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 8px rgba(43,94,167,0.3); height: 36px; white-space: nowrap; }
        .btn-calc:hover { background: linear-gradient(135deg, #1e4b8a, #2b5ea7); box-shadow: 0 4px 12px rgba(43,94,167,0.4); transform: translateY(-1px); }

        .select2-container--classic .select2-selection--single { height: 36px; border: 1px solid #cbd5e1; border-radius: 8px; background: #fff; transition: all 0.2s; box-shadow: 0 1px 2px rgba(0,0,0,0.04); }
        .select2-container--classic .select2-selection--single:hover { border-color: #2b5ea7; box-shadow: 0 0 0 2px rgba(43,94,167,0.1); }
        .select2-container--classic .select2-selection--single .select2-selection__rendered { line-height: 34px; padding-left: 10px; padding-right: 52px; color: #1e293b; font-size: 0.8rem; font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .select2-container--classic .select2-selection--single .select2-selection__arrow { height: 34px; width: 22px; background: transparent; border-left: none; right: 0; }
        .select2-container--classic .select2-selection--single .select2-selection__clear { position: absolute; right: 22px; top: 50%; transform: translateY(-50%); font-size: 16px; font-weight: bold; color: #94a3b8; cursor: pointer; padding: 2px 6px; z-index: 2; background: #fff; border-radius: 50%; line-height: 1; transition: all 0.15s; }
        .select2-container--classic .select2-selection--single .select2-selection__clear:hover { color: #fff; background: #ef4444; }
        .select2-dropdown { font-size: 0.8rem; border-radius: 8px; border: 1px solid #cbd5e1; box-shadow: 0 8px 24px rgba(0,0,0,0.12); }
        .select2-container--classic .select2-results__option--highlighted { background-color: #2b5ea7; }

        .student-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 13px; }
        .student-table thead { position: sticky; top: 0; z-index: 10; }
        .student-table thead tr { background: linear-gradient(135deg, #e8edf5, #dbe4ef, #d1d9e6); }
        .student-table th { padding: 12px 10px; text-align: left; font-weight: 600; font-size: 11px; color: #334155; text-transform: uppercase; letter-spacing: 0.05em; white-space: nowrap; border-bottom: 2px solid #cbd5e1; }
        .student-table tbody tr { transition: all 0.15s; border-bottom: 1px solid #f1f5f9; }
        .student-table tbody tr:nth-child(even) { background: #f8fafc; }
        .student-table tbody tr:nth-child(odd) { background: #fff; }
        .student-table tbody tr:hover { background: #eff6ff !important; box-shadow: inset 4px 0 0 #2b5ea7; }
        .student-table td { padding: 10px 10px; vertical-align: middle; line-height: 1.4; }

        .student-name-link { color: #1e40af; font-weight: 700; text-decoration: none; transition: all 0.15s; }
        .student-name-link:hover { color: #2b5ea7; text-decoration: underline; }

        .text-cell { font-size: 12.5px; font-weight: 500; line-height: 1.35; display: block; }
        .text-emerald { color: #047857; }
        .text-cyan { color: #0e7490; max-width: 220px; white-space: normal; word-break: break-word; }

        .badge { display: inline-block; padding: 3px 9px; border-radius: 6px; font-size: 11.5px; font-weight: 600; line-height: 1.4; }
        .badge-violet { background: #ede9fe; color: #5b21b6; border: 1px solid #ddd6fe; white-space: nowrap; }
        .badge-teal { background: #ccfbf1; color: #0f766e; border: 1px solid #99f6e4; white-space: nowrap; }
        .badge-indigo { background: linear-gradient(135deg, #1a3268, #2b5ea7); color: #fff; border: none; white-space: nowrap; }

        .btn-action { padding: 4px 10px; font-size: 11px; font-weight: 600; border: none; border-radius: 6px; cursor: pointer; transition: all 0.15s; white-space: nowrap; }
        .btn-action:hover { transform: translateY(-1px); }
        .btn-action-yellow { background: linear-gradient(135deg, #f59e0b, #fbbf24); color: #fff; }
        .btn-action-yellow:hover { box-shadow: 0 2px 8px rgba(245,158,11,0.4); }
        .btn-action-blue { background: linear-gradient(135deg, #2b5ea7, #3b82f6); color: #fff; }
        .btn-action-blue:hover { box-shadow: 0 2px 8px rgba(59,130,246,0.4); }
    </style>
</x-app-layout>
