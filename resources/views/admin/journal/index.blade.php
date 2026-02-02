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

    <div class="py-12">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">
            <div class="overflow-hidden bg-white shadow-xl sm:rounded-lg">
                <!-- Filters -->
                <form id="filter-form" method="GET" action="{{ route('admin.journal.index') }}" class="p-6 bg-gray-50">
                    <h3 class="mb-4 text-lg font-medium text-gray-900">Filtrlar</h3>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-4 lg:grid-cols-4">
                        <!-- Ta'lim turi -->
                        <div>
                            <label class="block mb-2 text-sm font-medium text-gray-700">Ta'lim turi</label>
                            <select name="education_type" id="education_type" class="w-full rounded-md select2">
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
                            <label class="block mb-2 text-sm font-medium text-gray-700">O'quv yili</label>
                            <select name="education_year" id="education_year" class="w-full rounded-md select2">
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
                            <label class="block mb-2 text-sm font-medium text-gray-700">Fakultet</label>
                            <select name="faculty" id="faculty" class="w-full rounded-md select2">
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
                            <label class="block mb-2 text-sm font-medium text-gray-700">Yo'nalish</label>
                            <select name="specialty" id="specialty" class="w-full rounded-md select2">
                                <option value="">Avval fakultetni tanlang</option>
                            </select>
                        </div>

                        <!-- Kurs -->
                        <div>
                            <label class="block mb-2 text-sm font-medium text-gray-700">Kurs</label>
                            <select name="level_code" id="level_code" class="w-full rounded-md select2">
                                <option value="">Barchasi</option>
                            </select>
                        </div>

                        <!-- Semestr -->
                        <div>
                            <label class="block mb-2 text-sm font-medium text-gray-700">Semestr</label>
                            <select name="semester_code" id="semester_code" class="w-full rounded-md select2">
                                <option value="">Avval kursni tanlang</option>
                            </select>
                        </div>

                        <!-- Fan -->
                        <div>
                            <label class="block mb-2 text-sm font-medium text-gray-700">Fan</label>
                            <select name="subject" id="subject" class="w-full rounded-md select2">
                                <option value="">Avval semestrni tanlang</option>
                            </select>
                        </div>

                        <!-- Guruh -->
                        <div>
                            <label class="block mb-2 text-sm font-medium text-gray-700">Guruh</label>
                            <select name="group" id="group" class="w-full rounded-md select2">
                                <option value="">Avval fakultetni tanlang</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex flex-col items-start justify-between gap-4 mt-6 sm:flex-row sm:items-center">
                        <div class="w-full sm:w-auto">
                            <label for="per_page" class="block mr-2 text-sm font-medium text-gray-700 sm:inline">Har bir sahifada:</label>
                            <select id="per_page" name="per_page" class="block w-full mt-1 border-gray-300 rounded-md shadow-sm select2 sm:mt-0 sm:w-auto">
                                @foreach([10, 25, 50, 100] as $pageSize)
                                    <option value="{{ $pageSize }}" {{ request('per_page', 50) == $pageSize ? 'selected' : '' }}>
                                        {{ $pageSize }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <!-- Loading indicator -->
                        <div id="filter-loading" class="hidden items-center text-blue-600">
                            <svg class="animate-spin h-5 w-5 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span class="text-sm">Yuklanmoqda...</span>
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
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-xs font-medium tracking-wider text-left text-gray-500 uppercase">T/R</th>
                                    <th class="px-4 py-3 text-xs font-medium tracking-wider text-left text-gray-500 uppercase">Ta'lim turi</th>
                                    <th class="px-4 py-3 text-xs font-medium tracking-wider text-left text-gray-500 uppercase">O'quv yili</th>
                                    <th class="px-4 py-3 text-xs font-medium tracking-wider text-left text-gray-500 uppercase">Fakultet</th>
                                    <th class="px-4 py-3 text-xs font-medium tracking-wider text-left text-gray-500 uppercase">Yo'nalish</th>
                                    <th class="px-4 py-3 text-xs font-medium tracking-wider text-left text-gray-500 uppercase">Kurs</th>
                                    <th class="px-4 py-3 text-xs font-medium tracking-wider text-left text-gray-500 uppercase">Semestr</th>
                                    <th class="px-4 py-3 text-xs font-medium tracking-wider text-left text-gray-500 uppercase">Fan</th>
                                    <th class="px-4 py-3 text-xs font-medium tracking-wider text-left text-gray-500 uppercase">Guruh</th>
                                    <th class="px-4 py-3 text-xs font-medium tracking-wider text-center text-gray-500 uppercase">Amallar</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach ($journals as $index => $journal)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-4 text-sm text-gray-900 whitespace-nowrap">
                                            {{ $journals->firstItem() + $index }}
                                        </td>
                                        <td class="px-4 py-4 text-sm text-gray-900 whitespace-nowrap">
                                            {{ $journal->education_type_name ?? '-' }}
                                        </td>
                                        <td class="px-4 py-4 text-sm text-gray-900 whitespace-nowrap">
                                            {{ $journal->education_year_name ?? '-' }}
                                        </td>
                                        <td class="px-4 py-4 text-sm text-gray-900 whitespace-nowrap">
                                            {{ $journal->department_name ?? '-' }}
                                        </td>
                                        <td class="px-4 py-4 text-sm text-gray-900 whitespace-nowrap">
                                            {{ Str::limit($journal->specialty_name ?? '-', 30) }}
                                        </td>
                                        <td class="px-4 py-4 text-sm text-gray-900 whitespace-nowrap">
                                            {{ $journal->level_name ?? '-' }}
                                        </td>
                                        <td class="px-4 py-4 text-sm text-gray-900 whitespace-nowrap">
                                            {{ $journal->semester_name ?? '-' }}
                                        </td>
                                        <td class="px-4 py-4 text-sm text-gray-900">
                                            {{ Str::limit($journal->subject_name ?? '-', 40) }}
                                        </td>
                                        <td class="px-4 py-4 text-sm text-gray-900 whitespace-nowrap">
                                            {{ $journal->group_name ?? '-' }}
                                        </td>
                                        <td class="px-4 py-4 text-sm text-center whitespace-nowrap">
                                            <a href="{{ route('admin.journal.show', ['groupId' => $journal->group_id, 'subjectId' => $journal->subject_id, 'semesterCode' => $journal->semester_code]) }}"
                                               class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-blue-700 bg-blue-100 rounded hover:bg-blue-200">
                                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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

                        <div class="p-4">
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

            $('.select2').each(function () {
                $(this).select2({
                    theme: 'classic',
                    width: '100%',
                    allowClear: true,
                    placeholder: $(this).find('option:first').text()
                });
            });

            const selectedSpecialty = @json(request('specialty'));
            const selectedLevelCode = @json(request('level_code'));
            const selectedSemesterCode = @json(request('semester_code'));
            const selectedSubject = @json(request('subject'));
            const selectedGroup = @json(request('group'));

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

            // Auto-submit for simple filters (education_type, per_page)
            $('#education_type, #per_page').on('change', function() {
                autoSubmitForm();
            });

            // Auto-submit for subject and group (final selections)
            $('#subject, #group').on('change', function() {
                autoSubmitForm();
            });

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

            $('#specialty').change(function () {
                const specialtyId = $(this).val();
                const facultyId = $('#faculty').val();
                resetDropdown('#group', 'Guruhni tanlang');

                if (specialtyId || facultyId) {
                    populateDropdown('{{ route("admin.journal.get-groups") }}', { faculty_id: facultyId, specialty_id: specialtyId }, '#group', () => {
                        if (selectedGroup) $('#group').val(selectedGroup).trigger('change.select2');
                        else autoSubmitForm();
                    });
                } else {
                    autoSubmitForm();
                }
            });

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

            $('#level_code').change(function () {
                const levelCode = $(this).val();
                resetDropdown('#semester_code', 'Semestrni tanlang');
                resetDropdown('#subject', 'Fanni tanlang');

                if (levelCode) {
                    populateDropdown('{{ route("admin.journal.get-semesters") }}', { level_code: levelCode }, '#semester_code', () => {
                        if (selectedSemesterCode) $('#semester_code').val(selectedSemesterCode).trigger('change');
                    });
                } else {
                    autoSubmitForm();
                }
            });

            $('#semester_code').change(function () {
                const semesterCode = $(this).val();
                resetDropdown('#subject', 'Fanni tanlang');

                if (semesterCode) {
                    populateDropdown('{{ route("admin.journal.get-subjects") }}', { semester_code: semesterCode }, '#subject', () => {
                        if (selectedSubject) $('#subject').val(selectedSubject).trigger('change.select2');
                        else autoSubmitForm();
                    });
                } else {
                    autoSubmitForm();
                }
            });

            @if(request('faculty'))
                $('#faculty').trigger('change');
            @endif

            @if(request('education_year'))
                $('#education_year').trigger('change');
            @endif

            // Mark initial load as complete after a short delay
            setTimeout(function() {
                isInitialLoad = false;
            }, 1000);
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
            color: #374151;
        }
        .select2-container--classic .select2-selection--single .select2-selection__arrow {
            height: 36px;
            background: transparent;
        }
    </style>
</x-app-layout>
