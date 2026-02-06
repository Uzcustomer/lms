<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            Jurnal
        </h2>
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

    <div class="py-4">
        <div class="max-w-full mx-auto sm:px-4 lg:px-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">

                <!-- Filters -->
                <form id="filter-form" method="GET" action="{{ route('admin.journal.index') }}">
                    <div class="px-5 pt-4 pb-3" style="background: linear-gradient(135deg, #f8fafc 0%, #eef2f7 100%); border-bottom: 1px solid #e2e8f0;">

                        <!-- Row 1 -->
                        <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 10px; align-items: flex-end;">
                            <div style="min-width: 160px;">
                                <label class="filter-label">Ta'lim turi</label>
                                <select name="education_type" id="education_type" class="select2" style="width: 100%;">
                                    <option value="">Barchasi</option>
                                    @foreach($educationTypes as $type)
                                        <option value="{{ $type->education_type_code }}" {{ ($selectedEducationType ?? request('education_type')) == $type->education_type_code ? 'selected' : '' }}>
                                            {{ $type->education_type_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div style="min-width: 160px;">
                                <label class="filter-label">O'quv yili</label>
                                <select name="education_year" id="education_year" class="select2" style="width: 100%;">
                                    <option value="">Barchasi</option>
                                    @foreach($educationYears as $year)
                                        <option value="{{ $year->education_year_code }}" {{ request('education_year') == $year->education_year_code ? 'selected' : '' }}>
                                            {{ $year->education_year_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div style="flex: 1; min-width: 200px;">
                                <label class="filter-label">Fakultet</label>
                                <select name="faculty" id="faculty" class="select2" style="width: 100%;">
                                    <option value="">Barchasi</option>
                                    @foreach($faculties as $faculty)
                                        <option value="{{ $faculty->id }}" {{ request('faculty') == $faculty->id ? 'selected' : '' }}>
                                            {{ $faculty->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div style="flex: 1; min-width: 240px;">
                                <label class="filter-label">Yo'nalish</label>
                                <select name="specialty" id="specialty" class="select2" style="width: 100%;">
                                    <option value="">Barchasi</option>
                                </select>
                            </div>

                            <div style="min-width: 90px;">
                                <label class="filter-label">Sahifada</label>
                                <select id="per_page" name="per_page" class="select2" style="width: 100%;">
                                    @foreach([10, 25, 50, 100] as $pageSize)
                                        <option value="{{ $pageSize }}" {{ request('per_page', 50) == $pageSize ? 'selected' : '' }}>
                                            {{ $pageSize }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div style="min-width: 130px;">
                                <label class="filter-label">&nbsp;</label>
                                <input type="hidden" name="current_semester" id="current_semester_input" value="{{ request('current_semester', '1') }}">
                                <button type="button" id="current-semester-toggle"
                                    class="toggle-btn {{ request('current_semester', '1') == '1' ? 'active' : '' }}"
                                    onclick="toggleCurrentSemester()">
                                    <span class="toggle-indicator"></span>
                                    <span class="toggle-text">Joriy semestr</span>
                                </button>
                            </div>
                        </div>

                        <!-- Row 2 -->
                        <div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: flex-end;">
                            <div style="min-width: 160px;">
                                <label class="filter-label">Semestr</label>
                                <select name="semester_code" id="semester_code" class="select2" style="width: 100%;">
                                    <option value="">Barchasi</option>
                                </select>
                            </div>

                            <div style="min-width: 170px;">
                                <label class="filter-label">Guruh</label>
                                <select name="group" id="group" class="select2" style="width: 100%;">
                                    <option value="">Barchasi</option>
                                </select>
                            </div>

                            <div style="min-width: 140px;">
                                <label class="filter-label">Kurs</label>
                                <select name="level_code" id="level_code" class="select2" style="width: 100%;">
                                    <option value="">Barchasi</option>
                                </select>
                            </div>

                            <div style="flex: 1; min-width: 220px;">
                                <label class="filter-label">Kafedra</label>
                                <select name="department" id="department" class="select2" style="width: 100%;">
                                    <option value="">Barchasi</option>
                                    @foreach($kafedras as $kafedra)
                                        <option value="{{ $kafedra->department_id }}" {{ request('department') == $kafedra->department_id ? 'selected' : '' }}>
                                            {{ $kafedra->department_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div style="flex: 1; min-width: 280px;">
                                <label class="filter-label">Fan</label>
                                <select name="subject" id="subject" class="select2" style="width: 100%;">
                                    <option value="">Barchasi</option>
                                </select>
                            </div>

                            <div style="display: flex; align-items: flex-end; padding-bottom: 6px;">
                                <div id="filter-loading" class="hidden" style="display: none; align-items: center; color: #2b5ea7;">
                                    <svg class="animate-spin" style="height: 16px; width: 16px; margin-right: 4px;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle style="opacity: 0.25;" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path style="opacity: 0.75;" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <span style="font-size: 11px;">Yuklanmoqda...</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>

                <!-- Table -->
                <div style="max-height: calc(100vh - 300px); overflow-y: auto; overflow-x: auto;">
                    @if($journals->isEmpty())
                        <div style="padding: 60px 20px; text-align: center;">
                            <svg style="width: 48px; height: 48px; margin: 0 auto 12px; color: #cbd5e1;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            <p style="color: #94a3b8; font-size: 14px;">Hozircha ma'lumot mavjud emas.</p>
                        </div>
                    @else
                        @php
                            $sortColumn = $sortColumn ?? 'group_name';
                            $sortDirection = $sortDirection ?? 'asc';
                        @endphp
                        <table class="journal-table">
                            <thead>
                                <tr>
                                    <th class="th-num">#</th>
                                    @php
                                        $columns = [
                                            'education_type' => "Ta'lim turi",
                                            'education_year' => "O'quv yili",
                                            'faculty' => 'Fakultet',
                                            'department' => 'Kafedra',
                                            'specialty' => "Yo'nalish",
                                            'level' => 'Kurs',
                                            'semester' => 'Semestr',
                                            'subject' => 'Fan',
                                            'group_name' => 'Guruh',
                                        ];
                                    @endphp
                                    @foreach($columns as $column => $label)
                                        @php
                                            $isActive = $sortColumn === $column;
                                            $newDirection = ($isActive && $sortDirection === 'asc') ? 'desc' : 'asc';
                                            $sortUrl = request()->fullUrlWithQuery(['sort' => $column, 'direction' => $newDirection]);
                                        @endphp
                                        <th>
                                            <a href="{{ $sortUrl }}" class="sort-link">
                                                {{ $label }}
                                                @if($isActive)
                                                    <span class="sort-icon active">
                                                        @if($sortDirection === 'asc') &#9650; @else &#9660; @endif
                                                    </span>
                                                @else
                                                    <span class="sort-icon">&#9650;&#9660;</span>
                                                @endif
                                            </a>
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($journals as $index => $journal)
                                    <tr class="journal-row"
                                        onclick="window.location='{{ route('admin.journal.show', ['groupId' => $journal->group_id, 'subjectId' => $journal->subject_id, 'semesterCode' => $journal->semester_code]) }}'">
                                        <td class="td-num">
                                            {{ $journals->firstItem() + $index }}
                                        </td>
                                        <td>
                                            <span class="badge badge-blue">{{ $journal->education_type_name ?? '-' }}</span>
                                        </td>
                                        <td>
                                            <span class="badge badge-slate">{{ $journal->education_year_name ?? '-' }}</span>
                                        </td>
                                        <td>
                                            <span class="text-cell text-emerald" title="{{ $journal->faculty_name ?? '-' }}">
                                                {{ Str::limit($journal->faculty_name ?? '-', 30) }}
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge badge-amber" title="{{ $journal->kafedra_name ?? '-' }}">
                                                {{ Str::limit($journal->kafedra_name ?? '-', 25) }}
                                            </span>
                                        </td>
                                        <td>
                                            <span class="text-cell text-cyan" title="{{ $journal->specialty_name ?? '-' }}">
                                                {{ Str::limit($journal->specialty_name ?? '-', 25) }}
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge badge-violet">{{ $journal->level_name ?? '-' }}</span>
                                        </td>
                                        <td>
                                            <span class="badge badge-teal">{{ $journal->semester_name ?? '-' }}</span>
                                        </td>
                                        <td>
                                            <span class="text-cell text-subject" title="{{ $journal->subject_name ?? '-' }}">
                                                {{ Str::limit($journal->subject_name ?? '-', 30) }}
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge badge-indigo">{{ $journal->group_name ?? '-' }}</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>

                        <div style="padding: 12px 20px; border-top: 1px solid #e2e8f0; background: #f8fafc;">
                            {{ $journals->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
        function toggleCurrentSemester() {
            const btn = document.getElementById('current-semester-toggle');
            const input = document.getElementById('current_semester_input');
            const isActive = btn.classList.contains('active');

            if (isActive) {
                btn.classList.remove('active');
                input.value = '0';
            } else {
                btn.classList.add('active');
                input.value = '1';
            }

            setTimeout(function() {
                document.getElementById('filter-loading').classList.remove('hidden');
                document.getElementById('filter-loading').style.display = 'flex';
                document.getElementById('filter-form').submit();
            }, 100);
        }

        $(document).ready(function () {
            let isInitialLoad = true;
            let autoSubmitTimeout = null;

            function autoSubmitForm() {
                if (isInitialLoad) return;

                clearTimeout(autoSubmitTimeout);
                autoSubmitTimeout = setTimeout(function() {
                    $('#filter-loading').removeClass('hidden').css('display', 'flex');
                    $('#filter-form').submit();
                }, 400);
            }

            $('.select2').each(function () {
                $(this).select2({
                    theme: 'classic',
                    width: '100%',
                    allowClear: true,
                    placeholder: $(this).find('option:first').text()
                }).on('select2:open', function() {
                    setTimeout(function() {
                        const searchField = document.querySelector('.select2-container--open .select2-search__field');
                        if (searchField) searchField.focus();
                    }, 10);
                });
            });

            const selectedSpecialty = @json(request('specialty'));
            const selectedLevelCode = @json(request('level_code'));
            const selectedSemesterCode = @json(request('semester_code'));
            const selectedSubject = @json(request('subject'));
            const selectedGroup = @json(request('group'));
            const selectedFaculty = @json(request('faculty'));
            const selectedDepartment = @json(request('department'));
            const selectedEducationYear = @json(request('education_year'));
            const currentSemester = @json(request('current_semester', '1'));

            function resetDropdown(element, placeholder) {
                $(element).empty().append(`<option value="">${placeholder}</option>`);
            }

            function populateDropdownUnique(url, params, element, callback) {
                $.ajax({
                    url: url,
                    type: 'GET',
                    data: params,
                    success: function (data) {
                        const uniqueNames = {};
                        $.each(data, function (key, value) {
                            if (!uniqueNames[value]) {
                                uniqueNames[value] = key;
                            }
                        });
                        $.each(uniqueNames, function (name, key) {
                            $(element).append(`<option value="${key}">${name}</option>`);
                        });
                        if (callback) callback(data);
                    }
                });
            }

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

            function getFilterParams() {
                return {
                    education_type: $('#education_type').val() || '',
                    education_year: $('#education_year').val() || '',
                    faculty_id: $('#faculty').val() || '',
                    specialty_id: $('#specialty').val() || '',
                    department_id: $('#department').val() || '',
                    level_code: $('#level_code').val() || '',
                    semester_code: $('#semester_code').val() || '',
                    subject_id: $('#subject').val() || '',
                    current_semester: $('#current_semester_input').val() || '1',
                };
            }

            function refreshGroups() {
                resetDropdown('#group', 'Barchasi');
                populateDropdown('{{ route("admin.journal.get-groups") }}', getFilterParams(), '#group');
            }

            function refreshSubjects() {
                resetDropdown('#subject', 'Barchasi');
                populateDropdownUnique('{{ route("admin.journal.get-subjects") }}', getFilterParams(), '#subject');
            }

            function refreshSpecialties() {
                resetDropdown('#specialty', 'Barchasi');
                populateDropdownUnique('{{ route("admin.journal.get-specialties") }}', getFilterParams(), '#specialty');
            }

            $('#education_type').change(function () {
                refreshSpecialties();
                refreshSubjects();
                refreshGroups();
                autoSubmitForm();
            });

            $('#education_year').change(function () {
                refreshSubjects();
                refreshGroups();
                autoSubmitForm();
            });

            $('#per_page').on('change', function() {
                autoSubmitForm();
            });

            $('#faculty').change(function () {
                refreshSpecialties();
                refreshSubjects();
                refreshGroups();
                autoSubmitForm();
            });

            $('#department').change(function () {
                refreshSubjects();
                refreshGroups();
                autoSubmitForm();
            });

            $('#specialty').change(function () {
                refreshGroups();
                autoSubmitForm();
            });

            $('#level_code').change(function () {
                const levelCode = $(this).val();
                resetDropdown('#semester_code', 'Barchasi');

                if (levelCode) {
                    populateDropdown('{{ route("admin.journal.get-semesters") }}', { level_code: levelCode }, '#semester_code');
                }
                refreshSubjects();
                refreshGroups();
                autoSubmitForm();
            });

            $('#semester_code').change(function () {
                refreshSubjects();
                refreshGroups();
                autoSubmitForm();
            });

            $('#group').change(function () {
                autoSubmitForm();
            });

            $('#subject').change(function () {
                refreshGroups();
                autoSubmitForm();
            });

            function initializeFilters() {
                const initParams = getFilterParams();

                populateDropdownUnique('{{ route("admin.journal.get-specialties") }}', initParams, '#specialty', () => {
                    if (selectedSpecialty) $('#specialty').val(selectedSpecialty).trigger('change.select2');
                });

                populateDropdown('{{ route("admin.journal.get-level-codes") }}', {}, '#level_code', () => {
                    if (selectedLevelCode) $('#level_code').val(selectedLevelCode).trigger('change.select2');
                });

                populateDropdown('{{ route("admin.journal.get-semesters") }}', {}, '#semester_code', () => {
                    if (selectedSemesterCode) $('#semester_code').val(selectedSemesterCode).trigger('change.select2');
                });

                populateDropdownUnique('{{ route("admin.journal.get-subjects") }}', initParams, '#subject', () => {
                    if (selectedSubject) $('#subject').val(selectedSubject).trigger('change.select2');
                });

                populateDropdown('{{ route("admin.journal.get-groups") }}', initParams, '#group', () => {
                    if (selectedGroup) $('#group').val(selectedGroup).trigger('change.select2');
                });

                if (selectedDepartment) {
                    $('#department').val(selectedDepartment).trigger('change.select2');
                }
            }

            initializeFilters();

            setTimeout(function() {
                isInitialLoad = false;
            }, 1500);
        });
    </script>

    <style>
        /* ===== Filter Section ===== */
        .filter-label {
            display: block;
            margin-bottom: 4px;
            font-size: 11px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        /* ===== Select2 ===== */
        .select2-container--classic .select2-selection--single {
            height: 36px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            background: white;
            transition: border-color 0.2s;
        }
        .select2-container--classic .select2-selection--single:hover {
            border-color: #94a3b8;
        }
        .select2-container--classic .select2-selection--single .select2-selection__rendered {
            line-height: 34px;
            padding-left: 10px;
            padding-right: 45px;
            color: #334155;
            font-size: 0.8rem;
        }
        .select2-container--classic .select2-selection--single .select2-selection__arrow {
            height: 34px;
            width: 24px;
            background: transparent;
            border-left: none;
        }
        .select2-container--classic .select2-selection--single .select2-selection__clear {
            position: absolute;
            right: 25px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 18px;
            font-weight: bold;
            color: #9ca3af;
            cursor: pointer;
            padding: 0 5px;
            z-index: 1;
        }
        .select2-container--classic .select2-selection--single .select2-selection__clear:hover {
            color: #ef4444;
        }
        .select2-dropdown {
            font-size: 0.8rem;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .select2-container--classic .select2-results__option--highlighted {
            background-color: #2b5ea7;
        }

        /* ===== Toggle Button ===== */
        .toggle-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 14px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            background: white;
            cursor: pointer;
            font-size: 12px;
            color: #64748b;
            transition: all 0.2s;
            height: 36px;
        }
        .toggle-btn:hover {
            border-color: #94a3b8;
        }
        .toggle-btn.active {
            background: linear-gradient(135deg, #dbeafe 0%, #e0e7ff 100%);
            border-color: #2b5ea7;
            color: #1e3a5f;
            font-weight: 600;
        }
        .toggle-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #D1D5DB;
            transition: all 0.2s;
        }
        .toggle-btn.active .toggle-indicator {
            background: #2b5ea7;
            box-shadow: 0 0 0 3px rgba(43,94,167,0.2);
        }
        .toggle-text { white-space: nowrap; }

        /* ===== Table ===== */
        .journal-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 13px;
        }

        .journal-table thead {
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .journal-table thead tr {
            background: linear-gradient(135deg, #1a3268 0%, #2b5ea7 100%);
        }

        .journal-table th {
            padding: 14px 12px;
            text-align: left;
            font-weight: 600;
            font-size: 12px;
            color: #ffffff;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            white-space: nowrap;
            border-bottom: 3px solid #f59e0b;
        }

        .journal-table th.th-num {
            padding: 14px 12px 14px 20px;
            width: 50px;
        }

        .sort-link {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            color: #ffffff;
            text-decoration: none;
            transition: opacity 0.15s;
        }
        .sort-link:hover {
            opacity: 0.85;
        }

        .sort-icon {
            font-size: 8px;
            opacity: 0.5;
        }
        .sort-icon.active {
            font-size: 10px;
            opacity: 1;
            color: #fbbf24;
        }

        /* ===== Table Body ===== */
        .journal-table tbody tr {
            cursor: pointer;
            transition: all 0.15s ease;
            border-bottom: 1px solid #f1f5f9;
        }

        .journal-table tbody tr:nth-child(even) {
            background-color: #f8fafc;
        }
        .journal-table tbody tr:nth-child(odd) {
            background-color: #ffffff;
        }

        .journal-table tbody tr:hover {
            background-color: #eff6ff !important;
            box-shadow: inset 4px 0 0 #2b5ea7;
        }

        .journal-table td {
            padding: 14px 12px;
            vertical-align: middle;
        }

        .td-num {
            padding-left: 20px !important;
            font-weight: 700;
            color: #2b5ea7;
            font-size: 14px;
        }

        /* ===== Badge Styles ===== */
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 11.5px;
            font-weight: 600;
            white-space: nowrap;
            line-height: 1.4;
        }

        .badge-blue {
            background: #dbeafe;
            color: #1e40af;
            border: 1px solid #bfdbfe;
        }
        .badge-slate {
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #e2e8f0;
        }
        .badge-amber {
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #fde68a;
        }
        .badge-violet {
            background: #ede9fe;
            color: #5b21b6;
            border: 1px solid #ddd6fe;
        }
        .badge-teal {
            background: #ccfbf1;
            color: #0f766e;
            border: 1px solid #99f6e4;
        }
        .badge-indigo {
            background: linear-gradient(135deg, #1a3268, #2b5ea7);
            color: #ffffff;
            border: none;
        }

        /* ===== Text Cell Styles ===== */
        .text-cell {
            font-size: 12.5px;
            font-weight: 500;
            line-height: 1.4;
        }

        .text-emerald {
            color: #047857;
        }
        .text-cyan {
            color: #0e7490;
        }
        .text-subject {
            color: #1e293b;
            font-weight: 700;
            font-size: 13px;
        }
    </style>
</x-app-layout>
