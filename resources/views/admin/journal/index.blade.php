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
                <form id="filter-form" method="GET" action="{{ route('admin.journal.index') }}" class="p-4 bg-gray-50 border-b">
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3">
                        <!-- Ta'lim turi -->
                        <div>
                            <label class="block mb-1 text-xs font-medium text-gray-600">Ta'lim turi</label>
                            <select name="education_type" id="education_type" class="w-full text-sm rounded select2">
                                <option value="">Barchasi</option>
                                @foreach($educationTypes as $type)
                                    <option value="{{ $type->education_type_code }}" {{ request('education_type') == $type->education_type_code ? 'selected' : '' }}>
                                        {{ $type->education_type_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <!-- O'quv yili -->
                        <div>
                            <label class="block mb-1 text-xs font-medium text-gray-600">O'quv yili</label>
                            <select name="education_year" id="education_year" class="w-full text-sm rounded select2">
                                <option value="">Barchasi</option>
                                @foreach($educationYears as $year)
                                    <option value="{{ $year->education_year_code }}" {{ request('education_year') == $year->education_year_code ? 'selected' : '' }}>
                                        {{ $year->education_year_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Fakultet -->
                        <div>
                            <label class="block mb-1 text-xs font-medium text-gray-600">Fakultet</label>
                            <select name="faculty" id="faculty" class="w-full text-sm rounded select2">
                                <option value="">Barchasi</option>
                                @foreach($faculties as $faculty)
                                    <option value="{{ $faculty->id }}" {{ request('faculty') == $faculty->id ? 'selected' : '' }}>
                                        {{ $faculty->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Yo'nalish -->
                        <div>
                            <label class="block mb-1 text-xs font-medium text-gray-600">Yo'nalish</label>
                            <select name="specialty" id="specialty" class="w-full text-sm rounded select2">
                                <option value="">Tanlang</option>
                            </select>
                        </div>

                        <!-- Kurs -->
                        <div>
                            <label class="block mb-1 text-xs font-medium text-gray-600">Kurs</label>
                            <select name="level_code" id="level_code" class="w-full text-sm rounded select2">
                                <option value="">Barchasi</option>
                            </select>
                        </div>

                        <!-- Semestr -->
                        <div>
                            <label class="block mb-1 text-xs font-medium text-gray-600">Semestr</label>
                            <select name="semester_code" id="semester_code" class="w-full text-sm rounded select2">
                                <option value="">Tanlang</option>
                            </select>
                        </div>

                        <!-- Fan -->
                        <div>
                            <label class="block mb-1 text-xs font-medium text-gray-600">Fan</label>
                            <select name="subject" id="subject" class="w-full text-sm rounded select2">
                                <option value="">Tanlang</option>
                            </select>
                        </div>

                        <!-- Guruh -->
                        <div>
                            <label class="block mb-1 text-xs font-medium text-gray-600">Guruh</label>
                            <select name="group" id="group" class="w-full text-sm rounded select2">
                                <option value="">Tanlang</option>
                            </select>
                        </div>

                        <!-- Har bir sahifada -->
                        <div>
                            <label class="block mb-1 text-xs font-medium text-gray-600">Sahifada</label>
                            <select id="per_page" name="per_page" class="w-full text-sm rounded select2">
                                @foreach([10, 25, 50, 100] as $pageSize)
                                    <option value="{{ $pageSize }}" {{ request('per_page', 50) == $pageSize ? 'selected' : '' }}>
                                        {{ $pageSize }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Loading indicator -->
                        <div class="flex items-end pb-1">
                            <div id="filter-loading" class="hidden items-center text-blue-600">
                                <svg class="animate-spin h-4 w-4 mr-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span class="text-xs">Yuklanmoqda...</span>
                            </div>
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
                        @php
                            $sortColumn = $sortColumn ?? 'group_name';
                            $sortDirection = $sortDirection ?? 'asc';
                        @endphp
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-2 text-xs font-medium tracking-wider text-left text-blue-600 uppercase">T/R</th>
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
                                        <th class="px-3 py-2 text-xs font-medium tracking-wider text-left uppercase">
                                            <a href="{{ $sortUrl }}" class="flex items-center gap-1 text-blue-600 hover:text-blue-800">
                                                {{ $label }}
                                                @if($isActive)
                                                    <svg class="w-3 h-3 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                                                        @if($sortDirection === 'asc')
                                                            <path fill-rule="evenodd" d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z" clip-rule="evenodd" />
                                                        @else
                                                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                                        @endif
                                                    </svg>
                                                @else
                                                    <svg class="w-3 h-3 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M10 3a1 1 0 01.707.293l3 3a1 1 0 01-1.414 1.414L10 5.414 7.707 7.707a1 1 0 01-1.414-1.414l3-3A1 1 0 0110 3zm-3.707 9.293a1 1 0 011.414 0L10 14.586l2.293-2.293a1 1 0 011.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd" />
                                                    </svg>
                                                @endif
                                            </a>
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach ($journals as $index => $journal)
                                    <tr class="cursor-pointer transition-colors hover:bg-blue-50"
                                        onclick="window.location='{{ route('admin.journal.show', ['groupId' => $journal->group_id, 'subjectId' => $journal->subject_id, 'semesterCode' => $journal->semester_code]) }}'">
                                        <td class="px-3 py-2 text-sm text-gray-900 whitespace-nowrap">
                                            {{ $journals->firstItem() + $index }}
                                        </td>
                                        <td class="px-3 py-2 text-sm text-gray-900 whitespace-nowrap">
                                            {{ $journal->education_type_name ?? '-' }}
                                        </td>
                                        <td class="px-3 py-2 text-sm text-gray-900 whitespace-nowrap">
                                            {{ $journal->education_year_name ?? '-' }}
                                        </td>
                                        <td class="px-3 py-2 text-sm text-gray-900 whitespace-nowrap">
                                            {{ $journal->department_name ?? '-' }}
                                        </td>
                                        <td class="px-3 py-2 text-sm text-gray-900 whitespace-nowrap" title="{{ $journal->specialty_name ?? '-' }}">
                                            {{ Str::limit($journal->specialty_name ?? '-', 25) }}
                                        </td>
                                        <td class="px-3 py-2 text-sm text-gray-900 whitespace-nowrap">
                                            {{ $journal->level_name ?? '-' }}
                                        </td>
                                        <td class="px-3 py-2 text-sm text-gray-900 whitespace-nowrap">
                                            {{ $journal->semester_name ?? '-' }}
                                        </td>
                                        <td class="px-3 py-2 text-sm text-gray-900" title="{{ $journal->subject_name ?? '-' }}">
                                            {{ Str::limit($journal->subject_name ?? '-', 30) }}
                                        </td>
                                        <td class="px-3 py-2 text-sm text-gray-900 whitespace-nowrap">
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

            // Debounced auto-submit function
            function autoSubmitForm() {
                if (isInitialLoad) return;

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
                    // Focus on search input when opened
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
                $(element).empty().append(`<option value="">${placeholder}</option>`);
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

            // Auto-submit for simple filters
            $('#education_type, #per_page').on('change', function() {
                autoSubmitForm();
            });

            // Auto-submit for subject and group (final selections)
            $('#subject, #group').on('change', function() {
                autoSubmitForm();
            });

            // Fakultet tanlash -> yo'nalishlar va guruhlarni yuklash
            $('#faculty').change(function () {
                const facultyId = $(this).val();
                resetDropdown('#specialty', "Yo'nalishni tanlang");
                resetDropdown('#group', 'Guruhni tanlang');

                if (facultyId) {
                    populateDropdown('{{ route("admin.journal.get-specialties") }}', { faculty_id: facultyId }, '#specialty', () => {
                        if (selectedSpecialty) $('#specialty').val(selectedSpecialty).trigger('change.select2');
                    });
                    populateDropdown('{{ route("admin.journal.get-groups") }}', { faculty_id: facultyId }, '#group', () => {
                        if (selectedGroup) $('#group').val(selectedGroup).trigger('change.select2');
                    });
                } else {
                    autoSubmitForm();
                }
            });

            // Yo'nalish tanlash -> fakultetni va guruhlarni yangilash
            $('#specialty').change(function () {
                const specialtyId = $(this).val();
                const facultyId = $('#faculty').val();
                resetDropdown('#group', 'Guruhni tanlang');

                if (specialtyId) {
                    // Ikki tomonlama: yo'nalishga mos fakultetlarni ko'rsatish
                    if (!facultyId) {
                        $.ajax({
                            url: '{{ route("admin.journal.get-faculties-by-specialty") }}',
                            type: 'GET',
                            data: { specialty_id: specialtyId },
                            success: function (data) {
                                resetDropdown('#faculty', 'Barchasi');
                                $.each(data, function (key, value) {
                                    $('#faculty').append(`<option value="${key}">${value}</option>`);
                                });
                            }
                        });
                    }
                    populateDropdown('{{ route("admin.journal.get-groups") }}', { faculty_id: facultyId, specialty_id: specialtyId }, '#group', () => {
                        if (selectedGroup) $('#group').val(selectedGroup).trigger('change.select2');
                        else autoSubmitForm();
                    });
                } else if (facultyId) {
                    populateDropdown('{{ route("admin.journal.get-groups") }}', { faculty_id: facultyId }, '#group', () => {
                        if (selectedGroup) $('#group').val(selectedGroup).trigger('change.select2');
                        else autoSubmitForm();
                    });
                } else {
                    autoSubmitForm();
                }
            });

            // O'quv yili tanlash -> kurslarni yuklash
            $('#education_year').change(function () {
                const educationYear = $(this).val();
                resetDropdown('#level_code', 'Kursni tanlang');
                resetDropdown('#semester_code', 'Semestrni tanlang');
                resetDropdown('#subject', 'Fanni tanlang');

                if (educationYear) {
                    populateDropdown('{{ route("admin.journal.get-level-codes") }}', { education_year: educationYear }, '#level_code', () => {
                        if (selectedLevelCode) $('#level_code').val(selectedLevelCode).trigger('change');
                    });
                } else {
                    autoSubmitForm();
                }
            });

            // Kurs tanlash -> semestrlarni yuklash
            $('#level_code').change(function () {
                const levelCode = $(this).val();
                resetDropdown('#semester_code', 'Semestrni tanlang');
                resetDropdown('#subject', 'Fanni tanlang');

                if (levelCode) {
                    // Ikki tomonlama: kursga mos o'quv yillarini ko'rsatish
                    if (!$('#education_year').val()) {
                        $.ajax({
                            url: '{{ route("admin.journal.get-education-years-by-level") }}',
                            type: 'GET',
                            data: { level_code: levelCode },
                            success: function (data) {
                                resetDropdown('#education_year', 'Barchasi');
                                $.each(data, function (key, value) {
                                    $('#education_year').append(`<option value="${key}">${value}</option>`);
                                });
                            }
                        });
                    }
                    populateDropdown('{{ route("admin.journal.get-semesters") }}', { level_code: levelCode }, '#semester_code', () => {
                        if (selectedSemesterCode) $('#semester_code').val(selectedSemesterCode).trigger('change');
                    });
                } else {
                    autoSubmitForm();
                }
            });

            // Semestr tanlash -> fanlarni yuklash va kursni yangilash
            $('#semester_code').change(function () {
                const semesterCode = $(this).val();
                resetDropdown('#subject', 'Fanni tanlang');

                if (semesterCode) {
                    // Ikki tomonlama: semestrga mos kurslarni ko'rsatish
                    if (!$('#level_code').val()) {
                        $.ajax({
                            url: '{{ route("admin.journal.get-level-codes-by-semester") }}',
                            type: 'GET',
                            data: { semester_code: semesterCode },
                            success: function (data) {
                                resetDropdown('#level_code', 'Kursni tanlang');
                                $.each(data, function (key, value) {
                                    $('#level_code').append(`<option value="${key}">${value}</option>`);
                                });
                            }
                        });
                    }
                    populateDropdown('{{ route("admin.journal.get-subjects") }}', { semester_code: semesterCode }, '#subject', () => {
                        if (selectedSubject) $('#subject').val(selectedSubject).trigger('change.select2');
                        else autoSubmitForm();
                    });
                } else {
                    autoSubmitForm();
                }
            });

            // Initial load - trigger changes to populate dependent dropdowns
            @if(request('faculty'))
                $('#faculty').trigger('change');
            @endif

            @if(request('education_year'))
                $('#education_year').trigger('change');
            @endif

            // Load all levels and semesters initially
            populateDropdown('{{ route("admin.journal.get-level-codes") }}', {}, '#level_code', () => {
                if (selectedLevelCode) $('#level_code').val(selectedLevelCode).trigger('change.select2');
            });

            // Mark initial load as complete after a short delay
            setTimeout(function() {
                isInitialLoad = false;
            }, 1000);
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
