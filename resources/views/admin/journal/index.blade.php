<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">Jurnal</h2>
    </x-slot>

    @if(session('error'))
        <div class="px-4 py-2 mb-3 text-sm text-red-700 bg-red-100 border border-red-400 rounded" role="alert">
            {{ session('error') }}
        </div>
    @endif

    @if(session('success'))
        <div class="px-4 py-2 mb-3 text-sm text-green-700 bg-green-100 border border-green-400 rounded" role="alert">
            {{ session('success') }}
        </div>
    @endif

    <div class="py-4">
        <div class="max-w-full mx-auto sm:px-4 lg:px-6">
            <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                <!-- Compact Filters -->
                <form id="filter-form" method="GET" action="{{ route('admin.journal.index') }}" class="px-4 py-3 bg-gray-50 border-b">
                    <div class="flex flex-wrap items-end gap-2">
                        <!-- Ta'lim turi -->
                        <div class="w-28">
                            <label class="block text-xs text-gray-500 mb-1">Ta'lim turi</label>
                            <select name="education_type" id="education_type" class="filter-select">
                                <option value="">Barchasi</option>
                                @foreach($educationTypes as $type)
                                    <option value="{{ $type->education_type_code }}" {{ request('education_type') == $type->education_type_code ? 'selected' : '' }}>
                                        {{ $type->education_type_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <!-- O'quv yili -->
                        <div class="w-28">
                            <label class="block text-xs text-gray-500 mb-1">O'quv yili</label>
                            <select name="education_year" id="education_year" class="filter-select">
                                <option value="">Barchasi</option>
                                @foreach($educationYears as $year)
                                    <option value="{{ $year->education_year_code }}" {{ request('education_year') == $year->education_year_code ? 'selected' : '' }}>
                                        {{ $year->education_year_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Fakultet -->
                        <div class="w-40">
                            <label class="block text-xs text-gray-500 mb-1">Fakultet</label>
                            <select name="faculty" id="faculty" class="filter-select">
                                <option value="">Barchasi</option>
                                @foreach($faculties as $faculty)
                                    <option value="{{ $faculty->id }}" {{ request('faculty') == $faculty->id ? 'selected' : '' }}>
                                        {{ $faculty->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Yo'nalish -->
                        <div class="w-44">
                            <label class="block text-xs text-gray-500 mb-1">Yo'nalish</label>
                            <select name="specialty" id="specialty" class="filter-select">
                                <option value="">Barchasi</option>
                            </select>
                        </div>

                        <!-- Kurs -->
                        <div class="w-24">
                            <label class="block text-xs text-gray-500 mb-1">Kurs</label>
                            <select name="level_code" id="level_code" class="filter-select">
                                <option value="">Barchasi</option>
                            </select>
                        </div>

                        <!-- Semestr -->
                        <div class="w-28">
                            <label class="block text-xs text-gray-500 mb-1">Semestr</label>
                            <select name="semester_code" id="semester_code" class="filter-select">
                                <option value="">Barchasi</option>
                            </select>
                        </div>

                        <!-- Fan -->
                        <div class="w-48">
                            <label class="block text-xs text-gray-500 mb-1">Fan</label>
                            <select name="subject" id="subject" class="filter-select">
                                <option value="">Barchasi</option>
                            </select>
                        </div>

                        <!-- Guruh -->
                        <div class="w-28">
                            <label class="block text-xs text-gray-500 mb-1">Guruh</label>
                            <select name="group" id="group" class="filter-select">
                                <option value="">Barchasi</option>
                            </select>
                        </div>

                        <!-- Per page -->
                        <div class="w-16">
                            <label class="block text-xs text-gray-500 mb-1">Soni</label>
                            <select id="per_page" name="per_page" class="filter-select">
                                @foreach([10, 25, 50, 100] as $pageSize)
                                    <option value="{{ $pageSize }}" {{ request('per_page', 50) == $pageSize ? 'selected' : '' }}>
                                        {{ $pageSize }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Loading indicator -->
                        <div id="filter-loading" class="hidden items-center text-blue-500 ml-2">
                            <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </div>
                    </div>
                </form>

                <!-- Table -->
                <div class="overflow-x-auto">
                    @if($journals->isEmpty())
                        <div class="p-6 text-center text-gray-500">
                            <p>Hozircha ma'lumot mavjud emas.</p>
                        </div>
                    @else
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-100">
                                <tr>
                                    <th class="px-3 py-2 text-xs font-medium text-left text-gray-600">#</th>
                                    <th class="px-3 py-2 text-xs font-medium text-left text-gray-600">Ta'lim turi</th>
                                    <th class="px-3 py-2 text-xs font-medium text-left text-gray-600">O'quv yili</th>
                                    <th class="px-3 py-2 text-xs font-medium text-left text-gray-600">Fakultet</th>
                                    <th class="px-3 py-2 text-xs font-medium text-left text-gray-600">Yo'nalish</th>
                                    <th class="px-3 py-2 text-xs font-medium text-left text-gray-600">Kurs</th>
                                    <th class="px-3 py-2 text-xs font-medium text-left text-gray-600">Semestr</th>
                                    <th class="px-3 py-2 text-xs font-medium text-left text-gray-600">Fan</th>
                                    <th class="px-3 py-2 text-xs font-medium text-left text-gray-600">Guruh</th>
                                    <th class="px-3 py-2 text-xs font-medium text-center text-gray-600">Amal</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($journals as $index => $journal)
                                    <tr class="hover:bg-blue-50 transition-colors">
                                        <td class="px-3 py-2 text-gray-700">{{ $journals->firstItem() + $index }}</td>
                                        <td class="px-3 py-2 text-gray-700">{{ $journal->education_type_name ?? '-' }}</td>
                                        <td class="px-3 py-2 text-gray-700">{{ $journal->education_year_name ?? '-' }}</td>
                                        <td class="px-3 py-2 text-gray-700">{{ $journal->department_name ?? '-' }}</td>
                                        <td class="px-3 py-2 text-gray-700" title="{{ $journal->specialty_name }}">{{ Str::limit($journal->specialty_name ?? '-', 25) }}</td>
                                        <td class="px-3 py-2 text-gray-700">{{ $journal->level_name ?? '-' }}</td>
                                        <td class="px-3 py-2 text-gray-700">{{ $journal->semester_name ?? '-' }}</td>
                                        <td class="px-3 py-2 text-gray-700" title="{{ $journal->subject_name }}">{{ Str::limit($journal->subject_name ?? '-', 30) }}</td>
                                        <td class="px-3 py-2 text-gray-700 font-medium">{{ $journal->group_name ?? '-' }}</td>
                                        <td class="px-3 py-2 text-center">
                                            <a href="{{ route('admin.journal.show', ['groupId' => $journal->group_id, 'subjectId' => $journal->subject_id, 'semesterCode' => $journal->semester_code]) }}"
                                               class="inline-flex items-center px-2 py-1 text-xs text-blue-600 bg-blue-50 rounded hover:bg-blue-100 transition-colors">
                                                <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                </svg>
                                                Ko'rish
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>

                        <div class="px-4 py-2 border-t bg-gray-50">
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

            // Auto-submit function
            function autoSubmitForm() {
                if (isInitialLoad) return;
                $('#filter-loading').removeClass('hidden').addClass('flex');
                $('#filter-form').submit();
            }

            // Initialize Select2 with compact styling
            $('.filter-select').each(function () {
                $(this).select2({
                    theme: 'classic',
                    width: '100%',
                    allowClear: true,
                    placeholder: 'Tanlang',
                    dropdownAutoWidth: true,
                    minimumResultsForSearch: 10
                });
            });

            const selectedSpecialty = @json(request('specialty'));
            const selectedLevelCode = @json(request('level_code'));
            const selectedSemesterCode = @json(request('semester_code'));
            const selectedSubject = @json(request('subject'));
            const selectedGroup = @json(request('group'));

            function updateDropdown(element, data, selectedValue, placeholder = 'Barchasi') {
                const $el = $(element);
                const currentVal = $el.val();
                $el.empty().append(`<option value="">${placeholder}</option>`);
                $.each(data, function (key, value) {
                    $el.append(`<option value="${key}">${value}</option>`);
                });
                if (selectedValue && data[selectedValue]) {
                    $el.val(selectedValue).trigger('change.select2');
                } else if (currentVal && data[currentVal]) {
                    $el.val(currentVal).trigger('change.select2');
                }
            }

            // Load all dependent dropdowns based on current selections
            function loadFilterOptions() {
                const params = {
                    faculty_id: $('#faculty').val(),
                    specialty_id: $('#specialty').val(),
                    education_year: $('#education_year').val(),
                    level_code: $('#level_code').val(),
                    semester_code: $('#semester_code').val()
                };

                $.ajax({
                    url: '{{ route("admin.journal.get-filter-options") }}',
                    type: 'GET',
                    data: params,
                    success: function (data) {
                        if (!$('#faculty').val() && !$('#specialty').val()) {
                            // Only update if nothing is selected in related fields
                        }
                        updateDropdown('#specialty', data.specialties, selectedSpecialty);
                        updateDropdown('#group', data.groups, selectedGroup);
                        updateDropdown('#level_code', data.levels, selectedLevelCode);
                        updateDropdown('#semester_code', data.semesters, selectedSemesterCode);
                        updateDropdown('#subject', data.subjects, selectedSubject);
                    }
                });
            }

            // Faculty change - updates specialty and group
            $('#faculty').on('change', function () {
                const facultyId = $(this).val();
                $.ajax({
                    url: '{{ route("admin.journal.get-specialties") }}',
                    data: { faculty_id: facultyId },
                    success: function (data) {
                        updateDropdown('#specialty', data, selectedSpecialty);
                    }
                });
                $.ajax({
                    url: '{{ route("admin.journal.get-groups") }}',
                    data: { faculty_id: facultyId },
                    success: function (data) {
                        updateDropdown('#group', data, selectedGroup);
                    }
                });
                autoSubmitForm();
            });

            // Specialty change - updates group, can filter faculty
            $('#specialty').on('change', function () {
                const specialtyId = $(this).val();
                const facultyId = $('#faculty').val();
                $.ajax({
                    url: '{{ route("admin.journal.get-groups") }}',
                    data: { faculty_id: facultyId, specialty_id: specialtyId },
                    success: function (data) {
                        updateDropdown('#group', data, selectedGroup);
                    }
                });
                autoSubmitForm();
            });

            // Education year change - updates level codes
            $('#education_year').on('change', function () {
                const educationYear = $(this).val();
                $.ajax({
                    url: '{{ route("admin.journal.get-level-codes") }}',
                    data: { education_year: educationYear },
                    success: function (data) {
                        updateDropdown('#level_code', data, selectedLevelCode);
                    }
                });
                autoSubmitForm();
            });

            // Level code change - updates semesters
            $('#level_code').on('change', function () {
                const levelCode = $(this).val();
                $.ajax({
                    url: '{{ route("admin.journal.get-semesters") }}',
                    data: { level_code: levelCode },
                    success: function (data) {
                        updateDropdown('#semester_code', data, selectedSemesterCode);
                    }
                });
                autoSubmitForm();
            });

            // Semester change - updates subjects
            $('#semester_code').on('change', function () {
                const semesterCode = $(this).val();
                $.ajax({
                    url: '{{ route("admin.journal.get-subjects") }}',
                    data: { semester_code: semesterCode },
                    success: function (data) {
                        updateDropdown('#subject', data, selectedSubject);
                    }
                });
                autoSubmitForm();
            });

            // Simple filters - just submit
            $('#education_type, #per_page, #subject, #group').on('change', function() {
                autoSubmitForm();
            });

            // Initial load of dropdowns
            loadFilterOptions();

            // Mark initial load as complete
            setTimeout(function() {
                isInitialLoad = false;
            }, 1500);
        });
    </script>

    <style>
        .filter-select + .select2-container {
            min-width: 100% !important;
        }
        .select2-container--classic .select2-selection--single {
            height: 30px !important;
            border: 1px solid #D1D5DB !important;
            border-radius: 4px !important;
            background: white !important;
        }
        .select2-container--classic .select2-selection--single .select2-selection__rendered {
            line-height: 28px !important;
            padding-left: 8px !important;
            padding-right: 20px !important;
            color: #374151 !important;
            font-size: 12px !important;
        }
        .select2-container--classic .select2-selection--single .select2-selection__arrow {
            height: 28px !important;
            width: 20px !important;
            background: transparent !important;
            border: none !important;
        }
        .select2-container--classic .select2-selection--single .select2-selection__arrow b {
            border-color: #6B7280 transparent transparent transparent !important;
        }
        .select2-dropdown {
            font-size: 12px !important;
        }
        .select2-results__option {
            padding: 4px 8px !important;
        }
        .select2-container--classic .select2-selection--single .select2-selection__clear {
            margin-right: 4px !important;
            font-size: 14px !important;
        }
    </style>
</x-app-layout>
