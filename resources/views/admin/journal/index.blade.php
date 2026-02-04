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
                    <!-- Row 1: Ta'lim turi, O'quv yili, Fakultet, Yo'nalish, Kurs -->
                    <div style="display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 8px;">
                        <!-- Ta'lim turi -->
                        <div style="width: 110px;">
                            <label style="display: block; margin-bottom: 2px; font-size: 11px; font-weight: 500; color: #6b7280;">Ta'lim turi</label>
                            <select name="education_type" id="education_type" class="select2" style="width: 100%;">
                                <option value="">Barchasi</option>
                                @foreach($educationTypes as $type)
                                    <option value="{{ $type->education_type_code }}" {{ request('education_type') == $type->education_type_code ? 'selected' : '' }}>
                                        {{ $type->education_type_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <!-- O'quv yili -->
                        <div style="width: 110px;">
                            <label style="display: block; margin-bottom: 2px; font-size: 11px; font-weight: 500; color: #6b7280;">O'quv yili</label>
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
                        <div style="flex: 1; min-width: 180px;">
                            <label style="display: block; margin-bottom: 2px; font-size: 11px; font-weight: 500; color: #6b7280;">Fakultet</label>
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
                        <div style="flex: 1; min-width: 180px;">
                            <label style="display: block; margin-bottom: 2px; font-size: 11px; font-weight: 500; color: #6b7280;">Yo'nalish</label>
                            <select name="specialty" id="specialty" class="select2" style="width: 100%;">
                                <option value="">Barchasi</option>
                            </select>
                        </div>

                        <!-- Kurs -->
                        <div style="width: 80px;">
                            <label style="display: block; margin-bottom: 2px; font-size: 11px; font-weight: 500; color: #6b7280;">Kurs</label>
                            <select name="level_code" id="level_code" class="select2" style="width: 100%;">
                                <option value="">Barchasi</option>
                            </select>
                        </div>
                    </div>

                    <!-- Row 2: Semestr, Guruh, Fan, Sahifada -->
                    <div style="display: flex; gap: 8px; flex-wrap: wrap; align-items: flex-end;">
                        <!-- Semestr -->
                        <div style="width: 100px;">
                            <label style="display: block; margin-bottom: 2px; font-size: 11px; font-weight: 500; color: #6b7280;">Semestr</label>
                            <select name="semester_code" id="semester_code" class="select2" style="width: 100%;">
                                <option value="">Barchasi</option>
                            </select>
                        </div>

                        <!-- Guruh -->
                        <div style="width: 120px;">
                            <label style="display: block; margin-bottom: 2px; font-size: 11px; font-weight: 500; color: #6b7280;">Guruh</label>
                            <select name="group" id="group" class="select2" style="width: 100%;">
                                <option value="">Barchasi</option>
                            </select>
                        </div>

                        <!-- Fan -->
                        <div style="flex: 1; min-width: 200px;">
                            <label style="display: block; margin-bottom: 2px; font-size: 11px; font-weight: 500; color: #6b7280;">Fan</label>
                            <select name="subject" id="subject" class="select2" style="width: 100%;">
                                <option value="">Barchasi</option>
                            </select>
                        </div>

                        <!-- Sahifada -->
                        <div style="width: 70px;">
                            <label style="display: block; margin-bottom: 2px; font-size: 11px; font-weight: 500; color: #6b7280;">Sahifada</label>
                            <select id="per_page" name="per_page" class="select2" style="width: 100%;">
                                @foreach([10, 25, 50, 100] as $pageSize)
                                    <option value="{{ $pageSize }}" {{ request('per_page', 50) == $pageSize ? 'selected' : '' }}>
                                        {{ $pageSize }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Loading indicator -->
                        <div style="display: flex; align-items: flex-end; padding-bottom: 4px;">
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
                <div style="max-height: calc(100vh - 280px); overflow-y: auto; overflow-x: auto;">
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
        $(document).ready(function () {
            let isInitialLoad = true;
            let autoSubmitTimeout = null;
            let isUpdatingFilters = false;

            // Debounced auto-submit function
            function autoSubmitForm() {
                if (isInitialLoad || isUpdatingFilters) return;

                clearTimeout(autoSubmitTimeout);
                autoSubmitTimeout = setTimeout(function() {
                    $('#filter-loading').removeClass('hidden').addClass('flex');
                    $('#filter-form').submit();
                }, 300);
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
                        document.querySelector('.select2-container--open .select2-search__field').focus();
                    }, 10);
                });
            });

            const selectedSpecialty = @json(request('specialty'));
            const selectedLevelCode = @json(request('level_code'));
            const selectedSemesterCode = @json(request('semester_code'));
            const selectedSubject = @json(request('subject'));
            const selectedGroup = @json(request('group'));
            const selectedFaculty = @json(request('faculty'));
            const selectedEducationYear = @json(request('education_year'));

            function resetDropdown(element, placeholder) {
                const currentVal = $(element).val();
                $(element).empty().append(`<option value="">${placeholder}</option>`);
                return currentVal;
            }

            function populateDropdown(url, params, element, callback, preserveValue = null) {
                $.ajax({
                    url: url,
                    type: 'GET',
                    data: params,
                    success: function (data) {
                        $.each(data, function (key, value) {
                            $(element).append(`<option value="${key}">${value}</option>`);
                        });
                        if (preserveValue) {
                            $(element).val(preserveValue).trigger('change.select2');
                        }
                        if (callback) callback(data);
                    }
                });
            }

            // Auto-submit for simple filters
            $('#education_type, #per_page').on('change', function() {
                autoSubmitForm();
            });

            // Fakultet tanlash -> yo'nalishlar va guruhlarni yuklash
            $('#faculty').change(function () {
                if (isUpdatingFilters) return;
                const facultyId = $(this).val();
                resetDropdown('#specialty', 'Barchasi');
                resetDropdown('#group', 'Barchasi');

                if (facultyId) {
                    populateDropdown('{{ route("admin.journal.get-specialties") }}', { faculty_id: facultyId }, '#specialty');
                    populateDropdown('{{ route("admin.journal.get-groups") }}', { faculty_id: facultyId }, '#group');
                }
                autoSubmitForm();
            });

            // Yo'nalish tanlash -> fakultetni (ikki tomonlama) va guruhlarni yangilash
            $('#specialty').change(function () {
                if (isUpdatingFilters) return;
                const specialtyId = $(this).val();
                const facultyId = $('#faculty').val();
                resetDropdown('#group', 'Barchasi');

                if (specialtyId) {
                    // Ikki tomonlama: yo'nalishga mos fakultetlarni ko'rsatish
                    if (!facultyId) {
                        isUpdatingFilters = true;
                        $.ajax({
                            url: '{{ route("admin.journal.get-faculties-by-specialty") }}',
                            type: 'GET',
                            data: { specialty_id: specialtyId },
                            success: function (data) {
                                resetDropdown('#faculty', 'Barchasi');
                                $.each(data, function (key, value) {
                                    $('#faculty').append(`<option value="${key}">${value}</option>`);
                                });
                                isUpdatingFilters = false;
                            }
                        });
                    }
                    populateDropdown('{{ route("admin.journal.get-groups") }}', { faculty_id: facultyId, specialty_id: specialtyId }, '#group');
                } else if (facultyId) {
                    populateDropdown('{{ route("admin.journal.get-groups") }}', { faculty_id: facultyId }, '#group');
                }
                autoSubmitForm();
            });

            // Kurs tanlash -> semestrlarni yuklash va o'quv yilini (ikki tomonlama)
            $('#level_code').change(function () {
                if (isUpdatingFilters) return;
                const levelCode = $(this).val();
                resetDropdown('#semester_code', 'Barchasi');

                if (levelCode) {
                    // Ikki tomonlama: kursga mos o'quv yillarini ko'rsatish
                    if (!$('#education_year').val()) {
                        isUpdatingFilters = true;
                        $.ajax({
                            url: '{{ route("admin.journal.get-education-years-by-level") }}',
                            type: 'GET',
                            data: { level_code: levelCode },
                            success: function (data) {
                                resetDropdown('#education_year', 'Barchasi');
                                $.each(data, function (key, value) {
                                    $('#education_year').append(`<option value="${key}">${value}</option>`);
                                });
                                isUpdatingFilters = false;
                            }
                        });
                    }
                    populateDropdown('{{ route("admin.journal.get-semesters") }}', { level_code: levelCode }, '#semester_code');
                }
                autoSubmitForm();
            });

            // Semestr tanlash -> fanlarni yuklash va kursni (ikki tomonlama)
            $('#semester_code').change(function () {
                if (isUpdatingFilters) return;
                const semesterCode = $(this).val();

                if (semesterCode) {
                    // Ikki tomonlama: semestr bo'yicha kurs va fanlarni olish
                    $.ajax({
                        url: '{{ route("admin.journal.get-filters-by-semester") }}',
                        type: 'GET',
                        data: { semester_code: semesterCode },
                        success: function (data) {
                            // Kurs (agar tanlanmagan bo'lsa)
                            if (!$('#level_code').val() && data.level_codes) {
                                isUpdatingFilters = true;
                                resetDropdown('#level_code', 'Barchasi');
                                $.each(data.level_codes, function (key, value) {
                                    $('#level_code').append(`<option value="${key}">${value}</option>`);
                                });
                                isUpdatingFilters = false;
                            }
                            // Fanlar
                            if (data.subjects) {
                                resetDropdown('#subject', 'Barchasi');
                                $.each(data.subjects, function (key, value) {
                                    $('#subject').append(`<option value="${key}">${value}</option>`);
                                });
                            }
                        }
                    });
                } else {
                    resetDropdown('#subject', 'Barchasi');
                }
                autoSubmitForm();
            });

            // Guruh tanlash -> fakultet, yo'nalish, fan, semestr (ikki tomonlama)
            $('#group').change(function () {
                if (isUpdatingFilters) return;
                const groupId = $(this).val();

                if (groupId) {
                    isUpdatingFilters = true;
                    $.ajax({
                        url: '{{ route("admin.journal.get-filters-by-group") }}',
                        type: 'GET',
                        data: { group_id: groupId },
                        success: function (data) {
                            // Fakultet (agar tanlanmagan bo'lsa)
                            if (!$('#faculty').val() && data.faculties && Object.keys(data.faculties).length > 0) {
                                resetDropdown('#faculty', 'Barchasi');
                                $.each(data.faculties, function (key, value) {
                                    $('#faculty').append(`<option value="${key}">${value}</option>`);
                                });
                            }
                            // Yo'nalish (agar tanlanmagan bo'lsa)
                            if (!$('#specialty').val() && data.specialties && Object.keys(data.specialties).length > 0) {
                                resetDropdown('#specialty', 'Barchasi');
                                $.each(data.specialties, function (key, value) {
                                    $('#specialty').append(`<option value="${key}">${value}</option>`);
                                });
                            }
                            // Semestrlar (agar tanlanmagan bo'lsa)
                            if (!$('#semester_code').val() && data.semesters && Object.keys(data.semesters).length > 0) {
                                resetDropdown('#semester_code', 'Barchasi');
                                $.each(data.semesters, function (key, value) {
                                    $('#semester_code').append(`<option value="${key}">${value}</option>`);
                                });
                            }
                            // Fanlar (agar tanlanmagan bo'lsa)
                            if (!$('#subject').val() && data.subjects && Object.keys(data.subjects).length > 0) {
                                resetDropdown('#subject', 'Barchasi');
                                $.each(data.subjects, function (key, value) {
                                    $('#subject').append(`<option value="${key}">${value}</option>`);
                                });
                            }
                            isUpdatingFilters = false;
                        }
                    });
                }
                autoSubmitForm();
            });

            // Fan tanlash -> fakultet, yo'nalish, guruh, semestr (ikki tomonlama)
            $('#subject').change(function () {
                if (isUpdatingFilters) return;
                const subjectId = $(this).val();

                if (subjectId) {
                    isUpdatingFilters = true;
                    $.ajax({
                        url: '{{ route("admin.journal.get-filters-by-subject") }}',
                        type: 'GET',
                        data: { subject_id: subjectId },
                        success: function (data) {
                            // Fakultet (agar tanlanmagan bo'lsa)
                            if (!$('#faculty').val() && data.faculties && Object.keys(data.faculties).length > 0) {
                                resetDropdown('#faculty', 'Barchasi');
                                $.each(data.faculties, function (key, value) {
                                    $('#faculty').append(`<option value="${key}">${value}</option>`);
                                });
                            }
                            // Yo'nalish (agar tanlanmagan bo'lsa)
                            if (!$('#specialty').val() && data.specialties && Object.keys(data.specialties).length > 0) {
                                resetDropdown('#specialty', 'Barchasi');
                                $.each(data.specialties, function (key, value) {
                                    $('#specialty').append(`<option value="${key}">${value}</option>`);
                                });
                            }
                            // Guruhlar (agar tanlanmagan bo'lsa)
                            if (!$('#group').val() && data.groups && Object.keys(data.groups).length > 0) {
                                resetDropdown('#group', 'Barchasi');
                                $.each(data.groups, function (key, value) {
                                    $('#group').append(`<option value="${key}">${value}</option>`);
                                });
                            }
                            // Semestrlar (agar tanlanmagan bo'lsa)
                            if (!$('#semester_code').val() && data.semesters && Object.keys(data.semesters).length > 0) {
                                resetDropdown('#semester_code', 'Barchasi');
                                $.each(data.semesters, function (key, value) {
                                    $('#semester_code').append(`<option value="${key}">${value}</option>`);
                                });
                            }
                            isUpdatingFilters = false;
                        }
                    });
                }
                autoSubmitForm();
            });

            // O'quv yili tanlash -> kurslarni yuklash
            $('#education_year').change(function () {
                if (isUpdatingFilters) return;
                const educationYear = $(this).val();
                resetDropdown('#level_code', 'Barchasi');
                resetDropdown('#semester_code', 'Barchasi');

                if (educationYear) {
                    populateDropdown('{{ route("admin.journal.get-level-codes") }}', { education_year: educationYear }, '#level_code');
                }
                autoSubmitForm();
            });

            // Initial load - populate dropdowns
            function initializeFilters() {
                // Load all specialties
                populateDropdown('{{ route("admin.journal.get-specialties") }}', {}, '#specialty', () => {
                    if (selectedSpecialty) $('#specialty').val(selectedSpecialty).trigger('change.select2');
                });

                // Load all levels
                populateDropdown('{{ route("admin.journal.get-level-codes") }}', {}, '#level_code', () => {
                    if (selectedLevelCode) $('#level_code').val(selectedLevelCode).trigger('change.select2');
                });

                // Load all semesters
                populateDropdown('{{ route("admin.journal.get-semesters") }}', {}, '#semester_code', () => {
                    if (selectedSemesterCode) $('#semester_code').val(selectedSemesterCode).trigger('change.select2');
                });

                // Load all subjects
                populateDropdown('{{ route("admin.journal.get-subjects") }}', {}, '#subject', () => {
                    if (selectedSubject) $('#subject').val(selectedSubject).trigger('change.select2');
                });

                // Load all groups
                populateDropdown('{{ route("admin.journal.get-groups") }}', {}, '#group', () => {
                    if (selectedGroup) $('#group').val(selectedGroup).trigger('change.select2');
                });
            }

            initializeFilters();

            // Mark initial load as complete after a short delay
            setTimeout(function() {
                isInitialLoad = false;
            }, 1500);
        });
    </script>

    <style>
        .select2-container--classic .select2-selection--single {
            height: 32px;
            border: 1px solid #D1D5DB;
            border-radius: 0.25rem;
            background: white;
        }
        .select2-container--classic .select2-selection--single .select2-selection__rendered {
            line-height: 30px;
            padding-left: 8px;
            color: #374151;
            font-size: 0.875rem;
        }
        .select2-container--classic .select2-selection--single .select2-selection__arrow {
            height: 30px;
            background: transparent;
        }
        .select2-container--classic .select2-selection--single .select2-selection__clear {
            margin-right: 20px;
            font-size: 1rem;
        }
        .select2-dropdown {
            font-size: 0.875rem;
        }
        tbody tr:hover {
            background-color: #EBF5FF !important;
        }
    </style>
</x-app-layout>
