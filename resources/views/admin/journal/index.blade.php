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
            <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                <!-- Filters -->
                <form id="filter-form" method="GET" action="{{ route('admin.journal.index') }}" class="p-3 bg-gray-50 border-b">
                    <!-- Row 1: Ta'lim turi, O'quv yili, Fakultet, Yo'nalish, Sahifada, Joriy semestr toggle -->
                    <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 10px; align-items: flex-end;">
                        <!-- Ta'lim turi -->
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

                        <!-- O'quv yili -->
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

                        <!-- Fakultet -->
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

                        <!-- Yo'nalish -->
                        <div style="flex: 1; min-width: 240px;">
                            <label class="filter-label">Yo'nalish</label>
                            <select name="specialty" id="specialty" class="select2" style="width: 100%;">
                                <option value="">Barchasi</option>
                            </select>
                        </div>

                        <!-- Sahifada -->
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

                        <!-- Joriy semestr toggle -->
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

                    <!-- Row 2: Semestr, Guruh, Kurs, Kafedra, Fan -->
                    <div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: flex-end;">
                        <!-- Semestr -->
                        <div style="min-width: 160px;">
                            <label class="filter-label">Semestr</label>
                            <select name="semester_code" id="semester_code" class="select2" style="width: 100%;">
                                <option value="">Barchasi</option>
                            </select>
                        </div>

                        <!-- Guruh -->
                        <div style="min-width: 170px;">
                            <label class="filter-label">Guruh</label>
                            <select name="group" id="group" class="select2" style="width: 100%;">
                                <option value="">Barchasi</option>
                            </select>
                        </div>

                        <!-- Kurs -->
                        <div style="min-width: 140px;">
                            <label class="filter-label">Kurs</label>
                            <select name="level_code" id="level_code" class="select2" style="width: 100%;">
                                <option value="">Barchasi</option>
                            </select>
                        </div>

                        <!-- Kafedra -->
                        <div style="flex: 1; min-width: 220px;">
                            <label class="filter-label">Kafedra</label>
                            <select name="department" id="department" class="select2" style="width: 100%;">
                                <option value="">Barchasi</option>
                                @foreach($kafedras as $kafedra)
                                    <option value="{{ $kafedra->id }}" {{ request('department') == $kafedra->id ? 'selected' : '' }}>
                                        {{ $kafedra->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Fan -->
                        <div style="flex: 1; min-width: 280px;">
                            <label class="filter-label">Fan</label>
                            <select name="subject" id="subject" class="select2" style="width: 100%;">
                                <option value="">Barchasi</option>
                            </select>
                        </div>

                        <!-- Loading indicator -->
                        <div style="display: flex; align-items: flex-end; padding-bottom: 6px;">
                            <div id="filter-loading" class="hidden" style="display: none; align-items: center; color: #2563eb;">
                                <svg class="animate-spin" style="height: 16px; width: 16px; margin-right: 4px;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle style="opacity: 0.25;" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path style="opacity: 0.75;" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span style="font-size: 11px;">Yuklanmoqda...</span>
                            </div>
                        </div>
                    </div>
                </form>

                <!-- Table -->
                <div style="max-height: calc(100vh - 300px); overflow-y: auto; overflow-x: auto;">
                    @if($journals->isEmpty())
                        <div class="p-6 text-center text-gray-500">
                            <p>Hozircha ma'lumot mavjud emas.</p>
                        </div>
                    @else
                        @php
                            $sortColumn = $sortColumn ?? 'group_name';
                            $sortDirection = $sortDirection ?? 'asc';
                        @endphp
                        <table class="min-w-full divide-y divide-gray-200" style="font-size: 13px;">
                            <thead style="background-color: #f8fafc; position: sticky; top: 0; z-index: 10;">
                                <tr>
                                    <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #475569; border-bottom: 2px solid #e2e8f0;">#</th>
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
                                        <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #475569; border-bottom: 2px solid #e2e8f0; white-space: nowrap;">
                                            <a href="{{ $sortUrl }}" style="display: inline-flex; align-items: center; gap: 4px; color: #1e40af; text-decoration: none;">
                                                {{ $label }}
                                                @if($isActive)
                                                    <span style="color: #dc2626; font-size: 10px;">
                                                        @if($sortDirection === 'asc')
                                                            &#9650;
                                                        @else
                                                            &#9660;
                                                        @endif
                                                    </span>
                                                @else
                                                    <span style="color: #94a3b8; font-size: 8px;">&#9650;&#9660;</span>
                                                @endif
                                            </a>
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody style="background-color: #ffffff;">
                                @foreach ($journals as $index => $journal)
                                    <tr style="border-bottom: 1px solid #e2e8f0; cursor: pointer; transition: background-color 0.15s;"
                                        onmouseover="this.style.backgroundColor='#f0f9ff'"
                                        onmouseout="this.style.backgroundColor='#ffffff'"
                                        onclick="window.location='{{ route('admin.journal.show', ['groupId' => $journal->group_id, 'subjectId' => $journal->subject_id, 'semesterCode' => $journal->semester_code]) }}'">
                                        <td style="padding: 10px 16px; color: #1e40af; font-weight: 500;">
                                            {{ $journals->firstItem() + $index }}
                                        </td>
                                        <td style="padding: 10px 16px; color: #334155;">
                                            {{ $journal->education_type_name ?? '-' }}
                                        </td>
                                        <td style="padding: 10px 16px; color: #334155;">
                                            {{ $journal->education_year_name ?? '-' }}
                                        </td>
                                        <td style="padding: 10px 16px; color: #334155;">
                                            {{ $journal->faculty_name ?? '-' }}
                                        </td>
                                        <td style="padding: 10px 16px; color: #334155;">
                                            {{ $journal->department_name ?? '-' }}
                                        </td>
                                        <td style="padding: 10px 16px; color: #0891b2;" title="{{ $journal->specialty_name ?? '-' }}">
                                            {{ Str::limit($journal->specialty_name ?? '-', 25) }}
                                        </td>
                                        <td style="padding: 10px 16px; color: #334155;">
                                            {{ $journal->level_name ?? '-' }}
                                        </td>
                                        <td style="padding: 10px 16px; color: #334155;">
                                            {{ $journal->semester_name ?? '-' }}
                                        </td>
                                        <td style="padding: 10px 16px; color: #0891b2;" title="{{ $journal->subject_name ?? '-' }}">
                                            {{ Str::limit($journal->subject_name ?? '-', 30) }}
                                        </td>
                                        <td style="padding: 10px 16px; color: #334155;">
                                            {{ $journal->group_name ?? '-' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>

                        <div class="p-3 border-t">
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

            // Trigger form submit
            setTimeout(function() {
                document.getElementById('filter-loading').classList.remove('hidden');
                document.getElementById('filter-loading').style.display = 'flex';
                document.getElementById('filter-form').submit();
            }, 100);
        }

        $(document).ready(function () {
            let isInitialLoad = true;
            let autoSubmitTimeout = null;

            // Debounced auto-submit function
            function autoSubmitForm() {
                if (isInitialLoad) return;

                clearTimeout(autoSubmitTimeout);
                autoSubmitTimeout = setTimeout(function() {
                    $('#filter-loading').removeClass('hidden').css('display', 'flex');
                    $('#filter-form').submit();
                }, 400);
            }

            // Initialize Select2 with focus on search
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
                        // Bir xil nomlarni birlashtiramiz
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

            // Hozirgi barcha filtr qiymatlarini olish
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

            // Bog'liq dropdown'larni yangilash
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

            // Ta'lim turi tanlash - barcha bog'liq filtrlarni yangilash
            $('#education_type').change(function () {
                refreshSpecialties();
                refreshSubjects();
                refreshGroups();
                autoSubmitForm();
            });

            // O'quv yili tanlash
            $('#education_year').change(function () {
                refreshSubjects();
                refreshGroups();
                autoSubmitForm();
            });

            // Sahifada
            $('#per_page').on('change', function() {
                autoSubmitForm();
            });

            // Fakultet tanlash
            $('#faculty').change(function () {
                refreshSpecialties();
                refreshSubjects();
                refreshGroups();
                autoSubmitForm();
            });

            // Kafedra tanlash
            $('#department').change(function () {
                refreshSubjects();
                refreshGroups();
                autoSubmitForm();
            });

            // Yo'nalish tanlash
            $('#specialty').change(function () {
                refreshGroups();
                autoSubmitForm();
            });

            // Kurs tanlash
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

            // Semestr tanlash
            $('#semester_code').change(function () {
                refreshSubjects();
                refreshGroups();
                autoSubmitForm();
            });

            // Guruh tanlash
            $('#group').change(function () {
                autoSubmitForm();
            });

            // Fan tanlash
            $('#subject').change(function () {
                refreshGroups();
                autoSubmitForm();
            });

            // Initial load - populate dropdowns
            function initializeFilters() {
                const initParams = getFilterParams();

                // Load specialties
                populateDropdownUnique('{{ route("admin.journal.get-specialties") }}', initParams, '#specialty', () => {
                    if (selectedSpecialty) $('#specialty').val(selectedSpecialty).trigger('change.select2');
                });

                // Load levels
                populateDropdown('{{ route("admin.journal.get-level-codes") }}', {}, '#level_code', () => {
                    if (selectedLevelCode) $('#level_code').val(selectedLevelCode).trigger('change.select2');
                });

                // Load semesters
                populateDropdown('{{ route("admin.journal.get-semesters") }}', {}, '#semester_code', () => {
                    if (selectedSemesterCode) $('#semester_code').val(selectedSemesterCode).trigger('change.select2');
                });

                // Load subjects
                populateDropdownUnique('{{ route("admin.journal.get-subjects") }}', initParams, '#subject', () => {
                    if (selectedSubject) $('#subject').val(selectedSubject).trigger('change.select2');
                });

                // Load groups
                populateDropdown('{{ route("admin.journal.get-groups") }}', initParams, '#group', () => {
                    if (selectedGroup) $('#group').val(selectedGroup).trigger('change.select2');
                });

                if (selectedDepartment) {
                    $('#department').val(selectedDepartment).trigger('change.select2');
                }
            }

            initializeFilters();

            // Mark initial load as complete after delay
            setTimeout(function() {
                isInitialLoad = false;
            }, 1500);
        });
    </script>

    <style>
        .filter-label {
            display: block;
            margin-bottom: 3px;
            font-size: 11px;
            font-weight: 500;
            color: #6b7280;
        }

        /* Select2 styling */
        .select2-container--classic .select2-selection--single {
            height: 36px;
            border: 1px solid #D1D5DB;
            border-radius: 0.375rem;
            background: white;
        }
        .select2-container--classic .select2-selection--single .select2-selection__rendered {
            line-height: 34px;
            padding-left: 10px;
            padding-right: 45px;
            color: #374151;
            font-size: 0.875rem;
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
            font-size: 0.875rem;
            border-radius: 0.375rem;
        }
        .select2-container--classic .select2-results__option--highlighted {
            background-color: #3b82f6;
        }

        /* Toggle button styling */
        .toggle-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border: 1px solid #D1D5DB;
            border-radius: 0.375rem;
            background: white;
            cursor: pointer;
            font-size: 12px;
            color: #6b7280;
            transition: all 0.2s;
            height: 36px;
        }
        .toggle-btn:hover {
            border-color: #9ca3af;
        }
        .toggle-btn.active {
            background: #dbeafe;
            border-color: #3b82f6;
            color: #1d4ed8;
        }
        .toggle-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #D1D5DB;
            transition: all 0.2s;
        }
        .toggle-btn.active .toggle-indicator {
            background: #3b82f6;
        }
        .toggle-text {
            white-space: nowrap;
        }

        tbody tr:hover {
            background-color: #EBF5FF !important;
        }
    </style>
</x-app-layout>
