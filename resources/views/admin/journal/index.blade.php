<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                {{ __('Jurnal') }}
            </h2>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">
            <div class="overflow-hidden bg-white shadow-xl sm:rounded-lg">
                <!-- Filters Form -->
                <form id="filter-form" method="GET" action="{{ route('admin.journal.index') }}" class="p-6 bg-gray-50">
                    <h3 class="mb-4 text-lg font-medium text-gray-900">Filtrlar</h3>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-4 lg:grid-cols-4">
                        <!-- Ta'lim turi -->
                        <div>
                            <label class="block mb-2 text-sm font-medium text-gray-700">Ta'lim turi</label>
                            <select name="education_type" id="education_type" class="w-full border-gray-300 rounded-md shadow-sm">
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
                            <select name="education_year" id="education_year" class="w-full border-gray-300 rounded-md shadow-sm">
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
                            <select name="faculty" id="faculty" class="w-full border-gray-300 rounded-md shadow-sm">
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
                            <select name="specialty" id="specialty" class="w-full border-gray-300 rounded-md shadow-sm">
                                <option value="">Barchasi</option>
                            </select>
                        </div>

                        <!-- Kurs -->
                        <div>
                            <label class="block mb-2 text-sm font-medium text-gray-700">Kurs</label>
                            <select name="level_code" id="level_code" class="w-full border-gray-300 rounded-md shadow-sm">
                                <option value="">Barchasi</option>
                                @foreach($levelCodes as $level)
                                    <option value="{{ $level->level_code }}" {{ request('level_code') == $level->level_code ? 'selected' : '' }}>
                                        {{ $level->level_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Semestr -->
                        <div>
                            <label class="block mb-2 text-sm font-medium text-gray-700">Semestr</label>
                            <select name="semester_code" id="semester_code" class="w-full border-gray-300 rounded-md shadow-sm">
                                <option value="">Barchasi</option>
                            </select>
                        </div>

                        <!-- Fan -->
                        <div>
                            <label class="block mb-2 text-sm font-medium text-gray-700">Fan</label>
                            <select name="subject" id="subject" class="w-full border-gray-300 rounded-md shadow-sm">
                                <option value="">Barchasi</option>
                            </select>
                        </div>

                        <!-- Guruh -->
                        <div>
                            <label class="block mb-2 text-sm font-medium text-gray-700">Guruh</label>
                            <select name="group" id="group" class="w-full border-gray-300 rounded-md shadow-sm">
                                <option value="">Barchasi</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex flex-col items-start justify-between gap-4 mt-6 sm:flex-row sm:items-center">
                        <div class="w-full sm:w-auto">
                            <label for="per_page" class="block mr-2 text-sm font-medium text-gray-700 sm:inline">Har bir sahifada:</label>
                            <select id="per_page" name="per_page" class="border-gray-300 rounded-md shadow-sm">
                                @foreach([10, 25, 50, 100] as $pageSize)
                                    <option value="{{ $pageSize }}" {{ request('per_page', 50) == $pageSize ? 'selected' : '' }}>
                                        {{ $pageSize }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex gap-2">
                            <a href="{{ route('admin.journal.index') }}" class="inline-flex items-center px-4 py-2 text-xs font-semibold tracking-widest text-gray-700 uppercase bg-gray-200 rounded-md hover:bg-gray-300">
                                Tozalash
                            </a>
                            <button type="submit" class="inline-flex items-center px-4 py-2 text-xs font-semibold tracking-widest text-white uppercase bg-blue-500 rounded-md hover:bg-blue-600">
                                Qidirish
                            </button>
                        </div>
                    </div>
                </form>

                <!-- Table -->
                <div class="overflow-x-auto">
                    @if($journals->isEmpty())
                        <div class="p-6 text-center text-gray-500">
                            <p>Ma'lumot topilmadi.</p>
                        </div>
                    @else
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-xs font-medium tracking-wider text-left text-gray-500 uppercase">#</th>
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
                                        <td class="px-4 py-3 text-sm text-gray-900">{{ $journals->firstItem() + $index }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-900">{{ $journal->education_type_name ?? '-' }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-900">{{ $journal->education_year_name ?? '-' }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-900">{{ $journal->department_name ?? '-' }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-900">{{ Str::limit($journal->specialty_name ?? '-', 25) }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-900">{{ $journal->level_name ?? '-' }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-900">{{ $journal->semester_name ?? '-' }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-900">{{ Str::limit($journal->subject_name ?? '-', 30) }}</td>
                                        <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $journal->group_name ?? '-' }}</td>
                                        <td class="px-4 py-3 text-sm text-center">
                                            <div class="flex items-center justify-center space-x-2">
                                                <a href="{{ route('admin.journal.show', ['groupId' => $journal->group_id, 'subjectId' => $journal->subject_id, 'semesterCode' => $journal->semester_code]) }}"
                                                   class="inline-flex items-center px-2 py-1 text-xs font-medium text-blue-700 bg-blue-100 rounded hover:bg-blue-200">
                                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                    </svg>
                                                    Ko'rish
                                                </a>
                                                <a href="{{ route('admin.student-grades-week', ['department' => $journal->department_id, 'group' => $journal->group_id, 'semester' => $journal->semester_code, 'subject' => $journal->subject_id]) }}"
                                                   class="inline-flex items-center px-2 py-1 text-xs font-medium text-green-700 bg-green-100 rounded hover:bg-green-200">
                                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                    </svg>
                                                    Tahrirlash
                                                </a>
                                            </div>
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
    <script>
        $(document).ready(function () {
            const selectedSpecialty = @json(request('specialty'));
            const selectedSemesterCode = @json(request('semester_code'));
            const selectedSubject = @json(request('subject'));
            const selectedGroup = @json(request('group'));

            function resetDropdown(element, placeholder) {
                $(element).empty().append(`<option value="">${placeholder}</option>`);
            }

            function populateDropdown(url, params, element, selectedValue) {
                $.ajax({
                    url: url,
                    type: 'GET',
                    data: params,
                    success: function (data) {
                        $.each(data, function (key, value) {
                            const selected = selectedValue == key ? 'selected' : '';
                            $(element).append(`<option value="${key}" ${selected}>${value}</option>`);
                        });
                    }
                });
            }

            // Faculty change - load specialties and groups
            $('#faculty').change(function () {
                const facultyId = $(this).val();
                resetDropdown('#specialty', 'Barchasi');
                resetDropdown('#group', 'Barchasi');

                if (facultyId) {
                    populateDropdown('{{ route("admin.journal.get-specialties") }}', { faculty_id: facultyId }, '#specialty', selectedSpecialty);
                    populateDropdown('{{ route("admin.journal.get-groups") }}', { faculty_id: facultyId }, '#group', selectedGroup);
                }
            });

            // Specialty change - update groups
            $('#specialty').change(function () {
                const specialtyId = $(this).val();
                const facultyId = $('#faculty').val();
                resetDropdown('#group', 'Barchasi');

                if (specialtyId || facultyId) {
                    populateDropdown('{{ route("admin.journal.get-groups") }}', { faculty_id: facultyId, specialty_id: specialtyId }, '#group', selectedGroup);
                }
            });

            // Level code change - load semesters
            $('#level_code').change(function () {
                const levelCode = $(this).val();
                resetDropdown('#semester_code', 'Barchasi');
                resetDropdown('#subject', 'Barchasi');

                if (levelCode) {
                    populateDropdown('{{ route("admin.journal.get-semesters") }}', { level_code: levelCode }, '#semester_code', selectedSemesterCode);
                }
            });

            // Semester change - load subjects
            $('#semester_code').change(function () {
                const semesterCode = $(this).val();
                resetDropdown('#subject', 'Barchasi');

                if (semesterCode) {
                    populateDropdown('{{ route("admin.journal.get-subjects") }}', { semester_code: semesterCode }, '#subject', selectedSubject);
                }
            });

            // Load initial values
            @if(request('faculty'))
                $('#faculty').trigger('change');
            @endif

            @if(request('level_code'))
                $('#level_code').trigger('change');
            @endif
        });
    </script>
</x-app-layout>
