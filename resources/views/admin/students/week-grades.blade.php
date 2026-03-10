<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            {{ __('Talabalar baholari') }}
        </h2>
    </x-slot>

    @push('styles')
    @endpush

    <div class="py-12">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">
            <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">

                    <!-- Journal uslubidagi filtrlar -->
                    <form id="gradeForm" action="{{ route('admin.student-grades-week') }}" method="GET" class="mb-4">
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
                                            <option value="{{ $type->education_type_code }}" {{ request('education_type') == $type->education_type_code ? 'selected' : '' }}>
                                                {{ $type->education_type_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="filter-item" style="flex: 1; min-width: 200px;">
                                    <label class="filter-label fl-emerald">
                                        <span class="fl-dot" style="background:#10b981;"></span> Fakultet
                                    </label>
                                    <select name="faculty" id="faculty" class="select2" style="width: 100%;">
                                        <option value="">Barchasi</option>
                                        @foreach($faculties as $faculty)
                                            <option value="{{ $faculty->id }}" {{ request('faculty') == $faculty->id ? 'selected' : '' }}>
                                                {{ $faculty->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="filter-item" style="flex: 1; min-width: 240px;">
                                    <label class="filter-label fl-cyan">
                                        <span class="fl-dot" style="background:#06b6d4;"></span> Yo'nalish
                                    </label>
                                    <select name="specialty" id="specialty" class="select2" style="width: 100%;">
                                        <option value="">Barchasi</option>
                                    </select>
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
                                        <option value="">Guruhni tanlang</option>
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
                                        <option value="">Fanni tanlang</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Row 3: Ko'rinish va Tur -->
                            <div class="filter-row">
                                <div class="filter-item" style="min-width: 140px;">
                                    <label class="filter-label">
                                        <span class="fl-dot" style="background:#6366f1;"></span> Ko'rinish
                                    </label>
                                    <select name="viewType" id="viewType" class="select2" style="width: 100%;">
                                        <option value="day" {{ request('viewType') == 'day' ? 'selected' : '' }}>Kun bo'yicha</option>
                                        <option value="week" {{ request('viewType', 'week') == 'week' ? 'selected' : '' }}>Hafta bo'yicha</option>
                                    </select>
                                </div>
                                <div class="filter-item" style="min-width: 160px;">
                                    <label class="filter-label">
                                        <span class="fl-dot" style="background:#ec4899;"></span> Turini tanlang
                                    </label>
                                    <select name="traning_type" id="traning_type" class="select2" style="width: 100%;">
                                        <option value="joriy" {{ request('traning_type', 'joriy') == 'joriy' ? 'selected' : '' }}>Joriy</option>
                                        <option value="mustaqil" {{ request('traning_type') == 'mustaqil' ? 'selected' : '' }}>Mustaqil ta'lim</option>
                                        <option value="oraliq" {{ request('traning_type') == 'oraliq' ? 'selected' : '' }}>Oraliq nazorat</option>
                                        <option value="oski" {{ request('traning_type') == 'oski' ? 'selected' : '' }}>OSKI</option>
                                        <option value="examtest" {{ request('traning_type') == 'examtest' ? 'selected' : '' }}>Test</option>
                                    </select>
                                </div>
                                <div style="display: flex; align-items: flex-end; gap: 8px; padding-bottom: 6px;">
                                    <button type="submit" class="btn btn-primary">Ko'rsatish</button>
                                    <button type="button" id="exportButton" class="btn btn-success">Excelga export qilish</button>
                                    <button type="button" id="exportButtonBox" class="btn btn-success">Excelga export qilish(Test)</button>
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

                    @if (isset($teacherName) && $teacherName !== "Yo'q")
                        <h3>O'qituvchi: {{ $teacherName }}</h3>
                    @endif

                    @if (isset($students) && count($students) > 0)
                        <div class="mt-6 overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead>
                                    <tr>
                                        <th
                                            class="px-4 py-2 text-xs font-medium tracking-wider text-left text-gray-500 uppercase bg-gray-50">
                                            Talaba ID
                                        </th>
                                        <th
                                            class="px-4 py-2 text-xs font-medium tracking-wider text-left text-gray-500 uppercase bg-gray-50">
                                            F.I.Sh
                                        </th>
                                        @if ($viewType == 'day')
                                            <th
                                                class="px-4 py-2 text-xs font-medium tracking-wider text-left text-gray-500 uppercase bg-gray-50">
                                                O'rtacha to'plangan ball
                                            </th>
                                        @endif
                                        @if ($viewType == 'week')
                                            @foreach ($weeks as $index => $week)
                                                <th
                                                    class="px-4 py-2 text-xs font-medium tracking-wider text-left text-gray-500 uppercase bg-gray-50">
                                                    {{ $index + 1 }}-Hafta
                                                    <br>{{ format_date($week->start_date) }}
                                                    - {{ format_date($week->end_date) }}
                                                </th>
                                            @endforeach
                                        @else
                                            @foreach ($dates as $date)
                                                <th
                                                    class="px-4 py-2 text-xs font-medium tracking-wider text-left text-gray-500 uppercase bg-gray-50">
                                                    {{ format_date($date) }}
                                                </th>
                                            @endforeach
                                        @endif
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach ($students as $student)
                                        <tr>
                                            <td class="px-4 py-2 whitespace-nowrap">{{ $student->student_id_number }}
                                            </td>
                                            <td class="px-4 py-2 whitespace-nowrap">{{ $student->full_name }}</td>
                                            @if ($viewType == 'day')
                                                <td class="px-4 py-2 whitespace-nowrap">
                                                    @php
                                                        $gradeInfo = $averageGradesForSubject[$student->hemis_id] ?? [
                                                            'average' => null,
                                                            'days' => 0,
                                                        ];
                                                    @endphp
                                                    @if ($gradeInfo['average'] !== null)
                                                        {{ number_format(round($gradeInfo['average']), 2) }}
                                                        ({{ $gradeInfo['days'] }})
                                                    @endif
                                                </td>
                                                @foreach ($dates as $date)
                                                    <td class="px-4 py-2 whitespace-nowrap">
                                                        @php
                                                            $dateKey = $date->format('Y-m-d');
                                                            $grade =
                                                                $averageGradesPerStudentPerPeriod[$student->hemis_id][
                                                                    $dateKey
                                                                ] ?? null;
                                                        @endphp
                                                        @if ($grade === 'Nb')
                                                            Nb
                                                        @elseif($grade !== null)
                                                            {{ number_format(round($grade), 2) }}
                                                        @endif
                                                    </td>
                                                @endforeach
                                            @else
                                                @foreach ($weeks as $index => $week)
                                                    <td class="px-4 py-2 whitespace-nowrap">
                                                        @php
                                                            $averageGrade =
                                                                $averageGradesPerStudentPerPeriod[$student->hemis_id][
                                                                    $index
                                                                ] ?? null;
                                                        @endphp
                                                        @if ($averageGrade === 'Nb')
                                                            Nb
                                                        @elseif($averageGrade !== null)
                                                            {{ number_format(round($averageGrade), 2) }}
                                                        @endif
                                                    </td>
                                                @endforeach
                                            @endif
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @elseif(request()->has('group') && request()->has('semester') && request()->has('subject'))
                        <p class="mt-4 text-gray-500">Ma'lumotlar topilmadi.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js"></script>

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
            if (data.text.toLowerCase().indexOf(params.term.toLowerCase()) > -1) {
                return $.extend({}, data, true);
            }
            return null;
        }

        $(document).ready(function() {
            let isInitialLoad = true;

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
            const selectedDepartment = @json(request('department'));

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
                    faculty_id: $('#faculty').val() || '',
                    specialty_id: $('#specialty').val() || '',
                    department_id: $('#department').val() || '',
                    level_code: $('#level_code').val() || '',
                    semester_code: $('#semester_code').val() || '',
                    subject_id: $('#subject').val() || '',
                    current_semester: '0',
                };
            }

            function refreshGroups() {
                resetDropdown('#group', 'Guruhni tanlang');
                populateDropdown('{{ route("admin.journal.get-groups") }}', getFilterParams(), '#group');
            }
            function refreshSubjects() {
                resetDropdown('#subject', 'Fanni tanlang');
                populateDropdownUnique('{{ route("admin.journal.get-subjects") }}', getFilterParams(), '#subject');
            }
            function refreshSpecialties() {
                resetDropdown('#specialty', 'Barchasi');
                populateDropdownUnique('{{ route("admin.journal.get-specialties") }}', getFilterParams(), '#specialty');
            }

            $('#education_type').change(function () {
                if (!isInitialLoad) { refreshSpecialties(); refreshSubjects(); refreshGroups(); }
            });
            $('#faculty').change(function () {
                if (!isInitialLoad) { refreshSpecialties(); refreshSubjects(); refreshGroups(); }
            });
            $('#department').change(function () {
                if (!isInitialLoad) { refreshSubjects(); refreshGroups(); }
            });
            $('#specialty').change(function () {
                if (!isInitialLoad) { refreshGroups(); }
            });
            $('#level_code').change(function () {
                var lc = $(this).val();
                resetDropdown('#semester_code', 'Barchasi');
                if (lc) {
                    populateDropdown('{{ route("admin.journal.get-semesters") }}', { level_code: lc }, '#semester_code');
                }
                if (!isInitialLoad) { refreshSubjects(); refreshGroups(); }
            });
            $('#semester_code').change(function () {
                if (!isInitialLoad) { refreshSubjects(); refreshGroups(); }
            });
            $('#subject').change(function () {
                if (!isInitialLoad) { refreshGroups(); }
            });

            // Sahifa yuklanganda filtrlarni initsializatsiya qilish
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

            // Excel export
            $('#exportButton').click(function() {
                var exportUrl = '{{ route('admin.student-grades-week.export') }}' +
                    '?' + $('#gradeForm').serialize();
                window.location.href = exportUrl;
            });
            $('#exportButtonBox').click(function() {
                var exportUrl = '{{ route('admin.student-grades-week.export-box') }}' +
                    '?' + $('#gradeForm').serialize();
                window.location.href = exportUrl;
            });
        });
    </script>

    <style>
        /* ===== Filter Container ===== */
        .filter-container {
            padding: 16px 20px 12px;
            background: linear-gradient(135deg, #f0f4f8 0%, #e8edf5 100%);
            border-bottom: 2px solid #dbe4ef;
            border-radius: 8px;
            margin-bottom: 16px;
        }
        .filter-row {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 10px;
            align-items: flex-end;
        }
        .filter-row:last-child { margin-bottom: 0; }

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
    </style>

</x-app-layout>
