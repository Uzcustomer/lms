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
                    <div class="filter-container">

                        <!-- Row 1 -->
                        <div class="filter-row">
                            <div class="filter-item" style="min-width: 160px;">
                                <label class="filter-label fl-blue">
                                    <span class="fl-dot" style="background:#3b82f6;"></span> Ta'lim turi
                                </label>
                                <select name="education_type" id="education_type" class="select2" style="width: 100%;">
                                    <option value="">Barchasi</option>
                                    @foreach($educationTypes as $type)
                                        <option value="{{ $type->education_type_code }}" {{ ($selectedEducationType ?? request('education_type')) == $type->education_type_code ? 'selected' : '' }}>
                                            {{ $type->education_type_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="filter-item" style="flex: 1; min-width: 200px;">
                                <label class="filter-label fl-emerald">
                                    <span class="fl-dot" style="background:#10b981;"></span> Fakultet
                                </label>
                                <select name="faculty" id="faculty" class="select2" style="width: 100%;" {{ isset($dekanFacultyId) && $dekanFacultyId ? 'disabled' : '' }}>
                                    @if(isset($dekanFacultyId) && $dekanFacultyId)
                                        @foreach($faculties as $faculty)
                                            <option value="{{ $faculty->id }}" selected>{{ $faculty->name }}</option>
                                        @endforeach
                                    @else
                                        <option value="">Barchasi</option>
                                        @foreach($faculties as $faculty)
                                            <option value="{{ $faculty->id }}" {{ request('faculty') == $faculty->id ? 'selected' : '' }}>
                                                {{ $faculty->name }}
                                            </option>
                                        @endforeach
                                    @endif
                                </select>
                                @if(isset($dekanFacultyId) && $dekanFacultyId)
                                    <input type="hidden" name="faculty" value="{{ $dekanFacultyId }}">
                                @endif
                            </div>

                            <div class="filter-item" style="flex: 1; min-width: 240px;">
                                <label class="filter-label fl-cyan">
                                    <span class="fl-dot" style="background:#06b6d4;"></span> Yo'nalish
                                </label>
                                <select name="specialty" id="specialty" class="select2" style="width: 100%;">
                                    <option value="">Barchasi</option>
                                </select>
                            </div>

                            <div class="filter-item" style="min-width: 90px;">
                                <label class="filter-label fl-slate">
                                    <span class="fl-dot" style="background:#94a3b8;"></span> Sahifada
                                </label>
                                <select id="per_page" name="per_page" class="select2" style="width: 100%;">
                                    @foreach([10, 25, 50, 100] as $pageSize)
                                        <option value="{{ $pageSize }}" {{ request('per_page', 50) == $pageSize ? 'selected' : '' }}>
                                            {{ $pageSize }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="filter-item" style="min-width: 160px;">
                                <label class="filter-label">&nbsp;</label>
                                <input type="hidden" name="current_semester" id="current_semester_input" value="{{ request('current_semester', '1') }}">
                                <input type="hidden" name="education_year" id="education_year" value="{{ request('education_year') }}">
                                <div class="toggle-switch {{ request('current_semester', '1') == '1' ? 'active' : '' }}" id="current-semester-toggle" onclick="toggleCurrentSemester()">
                                    <div class="toggle-track">
                                        <div class="toggle-thumb"></div>
                                    </div>
                                    <span class="toggle-label">Joriy semestr</span>
                                </div>
                            </div>
                        </div>

                        <!-- Row 2 -->
                        <div class="filter-row">
                            <div class="filter-item" style="min-width: 140px;">
                                <label class="filter-label fl-violet">
                                    <span class="fl-dot" style="background:#8b5cf6;"></span> Kurs
                                </label>
                                <select name="level_code" id="level_code" class="select2" style="width: 100%;">
                                    <option value="">Barchasi</option>
                                </select>
                            </div>

                            <div class="filter-item" style="min-width: 150px;">
                                <label class="filter-label fl-teal">
                                    <span class="fl-dot" style="background:#14b8a6;"></span> Semestr
                                </label>
                                <select name="semester_code" id="semester_code" class="select2" style="width: 100%;">
                                    <option value="">Barchasi</option>
                                </select>
                            </div>

                            <div class="filter-item" style="min-width: 170px;">
                                <label class="filter-label fl-indigo">
                                    <span class="fl-dot" style="background:#1a3268;"></span> Guruh
                                </label>
                                <select name="group" id="group" class="select2" style="width: 100%;">
                                    <option value="">Barchasi</option>
                                </select>
                            </div>

                            <div class="filter-item" style="flex: 1; min-width: 220px;">
                                <label class="filter-label fl-amber">
                                    <span class="fl-dot" style="background:#f59e0b;"></span> Kafedra
                                </label>
                                <select name="department" id="department" class="select2" style="width: 100%;">
                                    <option value="">Barchasi</option>
                                    @foreach($kafedras as $kafedra)
                                        <option value="{{ $kafedra->department_id }}" {{ request('department') == $kafedra->department_id ? 'selected' : '' }}>
                                            {{ $kafedra->department_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="filter-item" style="flex: 1; min-width: 280px;">
                                <label class="filter-label fl-subject">
                                    <span class="fl-dot" style="background:#0f172a;"></span> Fan
                                </label>
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
                                        <td class="td-num">{{ $journals->firstItem() + $index }}</td>
                                        <td><span class="badge badge-blue">{{ $journal->education_type_name ?? '-' }}</span></td>
                                        <td><span class="text-cell text-emerald">{{ $journal->faculty_name ?? '-' }}</span></td>
                                        <td><span class="badge badge-amber">{{ $journal->kafedra_name ?? '-' }}</span></td>
                                        <td><span class="text-cell text-cyan">{{ $journal->specialty_name ?? '-' }}</span></td>
                                        <td><span class="badge badge-violet">{{ $journal->level_name ?? '-' }}</span></td>
                                        <td><span class="badge badge-teal">{{ $journal->semester_name ?? '-' }}</span></td>
                                        <td><span class="text-cell text-subject">{{ $journal->subject_name ?? '-' }}</span></td>
                                        <td><span class="badge badge-indigo">{{ $journal->group_name ?? '-' }}</span></td>
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
        // Maxsus belgilarni olib tashlab fuzzy qidiruv
        function stripSpecialChars(str) {
            return str.replace(/[\/\(\),\-\.\s]/g, '').toLowerCase();
        }

        function fuzzyMatcher(params, data) {
            if ($.trim(params.term) === '') return data;
            if (typeof data.text === 'undefined') return null;

            var searchClean = stripSpecialChars(params.term);
            var optionClean = stripSpecialChars(data.text);

            if (optionClean.indexOf(searchClean) > -1) {
                return $.extend({}, data, true);
            }
            // Oddiy qidiruv ham (asl matn bo'yicha)
            if (data.text.toLowerCase().indexOf(params.term.toLowerCase()) > -1) {
                return $.extend({}, data, true);
            }
            return null;
        }

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
                    placeholder: $(this).find('option:first').text(),
                    matcher: fuzzyMatcher
                }).on('select2:open', function() {
                    setTimeout(function() {
                        var sf = document.querySelector('.select2-container--open .select2-search__field');
                        if (sf) sf.focus();
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

            function resetDropdown(el, ph) {
                $(el).empty().append(`<option value="">${ph}</option>`);
            }

            function populateDropdownUnique(url, params, element, callback) {
                $.ajax({ url: url, type: 'GET', data: params, success: function (data) {
                    var unique = {};
                    $.each(data, function (k, v) { if (!unique[v]) unique[v] = k; });
                    $.each(unique, function (n, k) { $(element).append(`<option value="${k}">${n}</option>`); });
                    if (callback) callback(data);
                }});
            }

            function populateDropdown(url, params, element, callback) {
                $.ajax({ url: url, type: 'GET', data: params, success: function (data) {
                    $.each(data, function (k, v) { $(element).append(`<option value="${k}">${v}</option>`); });
                    if (callback) callback(data);
                }});
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

            $('#education_type').change(function () { refreshSpecialties(); refreshSubjects(); refreshGroups(); autoSubmitForm(); });
            $('#per_page').on('change', function() { autoSubmitForm(); });
            $('#faculty').change(function () { refreshSpecialties(); refreshSubjects(); refreshGroups(); autoSubmitForm(); });
            $('#department').change(function () { refreshSubjects(); refreshGroups(); autoSubmitForm(); });
            $('#specialty').change(function () { refreshGroups(); autoSubmitForm(); });
            $('#level_code').change(function () {
                var lc = $(this).val();
                resetDropdown('#semester_code', 'Barchasi');
                if (lc) populateDropdown('{{ route("admin.journal.get-semesters") }}', { level_code: lc }, '#semester_code');
                refreshSubjects(); refreshGroups(); autoSubmitForm();
            });
            $('#semester_code').change(function () { refreshSubjects(); refreshGroups(); autoSubmitForm(); });
            $('#group').change(function () { autoSubmitForm(); });
            $('#subject').change(function () { refreshGroups(); autoSubmitForm(); });

            function initializeFilters() {
                var p = getFilterParams();
                populateDropdownUnique('{{ route("admin.journal.get-specialties") }}', p, '#specialty', () => {
                    if (selectedSpecialty) $('#specialty').val(selectedSpecialty).trigger('change.select2');
                });
                populateDropdown('{{ route("admin.journal.get-level-codes") }}', {}, '#level_code', () => {
                    if (selectedLevelCode) $('#level_code').val(selectedLevelCode).trigger('change.select2');
                });
                populateDropdown('{{ route("admin.journal.get-semesters") }}', {}, '#semester_code', () => {
                    if (selectedSemesterCode) $('#semester_code').val(selectedSemesterCode).trigger('change.select2');
                });
                populateDropdownUnique('{{ route("admin.journal.get-subjects") }}', p, '#subject', () => {
                    if (selectedSubject) $('#subject').val(selectedSubject).trigger('change.select2');
                });
                populateDropdown('{{ route("admin.journal.get-groups") }}', p, '#group', () => {
                    if (selectedGroup) $('#group').val(selectedGroup).trigger('change.select2');
                });
                if (selectedDepartment) $('#department').val(selectedDepartment).trigger('change.select2');
            }

            initializeFilters();
            setTimeout(function() { isInitialLoad = false; }, 1500);
        });
    </script>

    <style>
        /* ===== Filter Container ===== */
        .filter-container {
            padding: 16px 20px 12px;
            background: linear-gradient(135deg, #f0f4f8 0%, #e8edf5 100%);
            border-bottom: 2px solid #dbe4ef;
        }
        .filter-row {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 10px;
            align-items: flex-end;
        }
        .filter-row:last-child { margin-bottom: 0; }
        .filter-item { }

        /* ===== Filter Labels ===== */
        .filter-label {
            display: flex;
            align-items: center;
            gap: 5px;
            margin-bottom: 4px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #475569;
        }
        .fl-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            display: inline-block;
            flex-shrink: 0;
        }

        /* ===== Select2 ===== */
        .select2-container--classic .select2-selection--single {
            height: 36px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            background: #ffffff;
            transition: all 0.2s;
            box-shadow: 0 1px 2px rgba(0,0,0,0.04);
        }
        .select2-container--classic .select2-selection--single:hover {
            border-color: #2b5ea7;
            box-shadow: 0 0 0 2px rgba(43,94,167,0.1);
        }
        .select2-container--classic .select2-selection--single .select2-selection__rendered {
            line-height: 34px;
            padding-left: 10px;
            padding-right: 52px;
            color: #1e293b;
            font-size: 0.8rem;
            font-weight: 500;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .select2-container--classic .select2-selection--single .select2-selection__arrow {
            height: 34px;
            width: 22px;
            background: transparent;
            border-left: none;
            right: 0;
        }
        .select2-container--classic .select2-selection--single .select2-selection__clear {
            position: absolute;
            right: 22px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 16px;
            font-weight: bold;
            color: #94a3b8;
            cursor: pointer;
            padding: 2px 6px;
            z-index: 2;
            background: #ffffff;
            border-radius: 50%;
            line-height: 1;
            transition: all 0.15s;
        }
        .select2-container--classic .select2-selection--single .select2-selection__clear:hover {
            color: #ffffff;
            background: #ef4444;
        }
        .select2-dropdown {
            font-size: 0.8rem;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
        }
        .select2-container--classic .select2-results__option--highlighted {
            background-color: #2b5ea7;
        }

        /* ===== Toggle Switch ===== */
        .toggle-switch {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            padding: 6px 0;
            height: 36px;
            user-select: none;
        }
        .toggle-track {
            width: 40px;
            height: 22px;
            background: #cbd5e1;
            border-radius: 11px;
            position: relative;
            transition: background 0.25s;
            flex-shrink: 0;
        }
        .toggle-switch.active .toggle-track {
            background: linear-gradient(135deg, #2b5ea7, #3b7ddb);
        }
        .toggle-thumb {
            width: 18px;
            height: 18px;
            background: #ffffff;
            border-radius: 50%;
            position: absolute;
            top: 2px;
            left: 2px;
            transition: transform 0.25s;
            box-shadow: 0 1px 4px rgba(0,0,0,0.2);
        }
        .toggle-switch.active .toggle-thumb {
            transform: translateX(18px);
        }
        .toggle-label {
            font-size: 12px;
            font-weight: 600;
            color: #64748b;
            white-space: nowrap;
        }
        .toggle-switch.active .toggle-label {
            color: #1e3a5f;
        }

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
            background: linear-gradient(135deg, #e8edf5 0%, #dbe4ef 50%, #d1d9e6 100%);
        }
        .journal-table th {
            padding: 14px 12px;
            text-align: left;
            font-weight: 600;
            font-size: 11.5px;
            color: #334155;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            white-space: nowrap;
            border-bottom: 2px solid #cbd5e1;
        }
        .journal-table th.th-num {
            padding: 14px 12px 14px 16px;
            width: 44px;
        }
        .sort-link {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            color: #334155;
            text-decoration: none;
            transition: opacity 0.15s;
        }
        .sort-link:hover { opacity: 0.75; }
        .sort-icon { font-size: 8px; opacity: 0.4; }
        .sort-icon.active { font-size: 11px; opacity: 1; color: #ef4444; }

        /* ===== Table Body ===== */
        .journal-table tbody tr {
            cursor: pointer;
            transition: all 0.15s ease;
            border-bottom: 1px solid #f1f5f9;
        }
        .journal-table tbody tr:nth-child(even) { background-color: #f8fafc; }
        .journal-table tbody tr:nth-child(odd) { background-color: #ffffff; }
        .journal-table tbody tr:hover {
            background-color: #eff6ff !important;
            box-shadow: inset 4px 0 0 #2b5ea7;
        }
        .journal-table td {
            padding: 10px 12px;
            vertical-align: middle;
            line-height: 1.4;
        }
        .td-num {
            padding-left: 16px !important;
            font-weight: 700;
            color: #2b5ea7;
            font-size: 13px;
        }

        /* ===== Badges ===== */
        .badge {
            display: inline-block;
            padding: 3px 9px;
            border-radius: 6px;
            font-size: 11.5px;
            font-weight: 600;
            line-height: 1.4;
        }
        .badge-blue { background: #dbeafe; color: #1e40af; border: 1px solid #bfdbfe; }
        .badge-amber { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; white-space: normal; word-break: break-word; max-width: 260px; display: inline-block; }
        .badge-violet { background: #ede9fe; color: #5b21b6; border: 1px solid #ddd6fe; white-space: nowrap; }
        .badge-teal { background: #ccfbf1; color: #0f766e; border: 1px solid #99f6e4; white-space: nowrap; }
        .badge-indigo {
            background: linear-gradient(135deg, #1a3268, #2b5ea7);
            color: #ffffff;
            border: none;
            white-space: nowrap;
        }

        /* ===== Text Cells ===== */
        .text-cell {
            font-size: 12.5px;
            font-weight: 500;
            line-height: 1.35;
            display: block;
        }
        .text-emerald { color: #047857; }
        .text-cyan { color: #0e7490; max-width: 220px; white-space: normal; word-break: break-word; }
        .text-subject {
            color: #0f172a;
            font-weight: 700;
            font-size: 12.5px;
            max-width: 260px;
            white-space: normal;
            word-break: break-word;
        }
    </style>
</x-app-layout>
