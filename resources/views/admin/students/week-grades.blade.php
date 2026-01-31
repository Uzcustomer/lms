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
                    <form id="gradeForm" action="{{ route('admin.student-grades-week') }}" method="GET" class="mb-4">
                        <div class="form-row">
                            <div class="form-group col-md-3">
                                <label for="department">Bo'lim</label>
                                <select name="department" id="department" class="form-control">
                                    <option value="">Bo'limni tanlang</option>
                                    @foreach ($departments as $departmentItem)
                                        <option value="{{ $departmentItem->id }}"
                                            {{ request('department') == $departmentItem->id ? 'selected' : '' }}>
                                            {{ $departmentItem->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group col-md-3">
                                <label for="level_code">Kurs</label>
                                <select name="level_code" id="level_code" class="select2 form-control">
                                    <option value="">Kursni tanlang</option>
                                    @foreach (['11' => '1-kurs', '12' => '2-kurs', '13' => '3-kurs', '14' => '4-kurs', '15' => '5-kurs', '16' => '6-kurs'] as $value => $label)
                                        <option value="{{ $value }}"
                                            {{ request('level_code') == $value ? 'selected' : '' }}>
                                            {{ $label }} (Level {{ $value }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group col-md-3">
                                <label for="group">Guruh</label>
                                <select name="group" id="group" class="form-control">
                                    <option value="">Guruhni tanlang</option>
                                </select>
                            </div>
                            <div class="form-group col-md-3">
                                <label for="semester">Semestr</label>
                                <select name="semester" id="semester" class="form-control">
                                    <option value="">Semestrni tanlang</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-3">
                                <label for="subject">Fan</label>
                                <select name="subject" id="subject" class="form-control">
                                    <option value="">Fanni tanlang</option>
                                </select>
                            </div>
                            <div class="form-group col-md-3">
                                <label for="viewType">Ko'rinish</label>
                                <select name="viewType" id="viewType" class="form-control">
                                    <option value="day" {{ request('viewType') == 'day' ? 'selected' : '' }}>Kun
                                        bo'yicha
                                    </option>
                                    <option value="week" {{ request('viewType') == 'week' ? 'selected' : '' }}>Hafta
                                        bo'yicha
                                    </option>
                                </select>
                            </div>
                            <div class="form-group col-md-3">
                                <label for="viewType">Turini tanlang</label>
                                <select name="traning_type" id="traning_type" class="form-control">
                                    <option value="joriy" {{ request('traning_type') == 'joriy' ? 'selected' : '' }}>
                                        Joriy
                                    </option>
                                    <option value="mustaqil"
                                        {{ request('traning_type') == 'mustaqil' ? 'selected' : '' }}>
                                        Mustaqil ta'lim
                                    </option>
                                    <option value="oraliq" {{ request('traning_type') == 'oraliq' ? 'selected' : '' }}>
                                        Oraliq nazorat
                                    </option>
                                    <option value="oski" {{ request('traning_type') == 'oski' ? 'selected' : '' }}>
                                        OSKI
                                    </option>
                                    <option value="examtest"
                                        {{ request('traning_type') == 'examtest' ? 'selected' : '' }}>
                                        Test
                                    </option>

                                </select>
                            </div>
                            <div class="form-group col-md-1 align-self-end">
                                <button type="submit" class="btn btn-primary">Ko'rsatish</button>
                            </div>
                            <div class="form-group col-md-1 align-self-end">
                                <button type="button" id="exportButton" class="btn btn-success">Excelga export
                                    qilish</button>
                            </div>
                            <div class="form-group col-md-1 align-self-end">
                                <button type="button" id="exportButtonBox" class="btn btn-success">Excelga export
                                    qilish(Test)</button>
                            </div>
                        </div>
                    </form>

                    @if (isset($teacherName))
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
                                                    <br>{{ $week->start_date->format('d-m-Y') }}
                                                    - {{ $week->end_date->format('d-m-Y') }}
                                                </th>
                                            @endforeach
                                        @else
                                            @foreach ($dates as $date)
                                                <th
                                                    class="px-4 py-2 text-xs font-medium tracking-wider text-left text-gray-500 uppercase bg-gray-50">
                                                    {{ $date->format('d.m.Y') }}
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
                    @elseif(request()->has(['department', 'group', 'semester', 'subject']))
                        <p class="mt-4 text-gray-500">Ma'lumotlar topilmadi.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#department, #level_code, #group, #semester, #subject').select2({
                width: '100%',
                placeholder: function() {
                    $(this).data('placeholder');
                }
            });
            var selectedLevelCode = @json(request('level_code'));
            var selectedGroup = @json(request('group'));
            var selectedSemester = @json(request('semester'));
            var selectedSubject = @json(request('subject'));
            $('#department').change(function() {
                var departmentId = $(this).val();
                if (departmentId) {
                    $('#level_code').val('').trigger('change');
                    $('#group').empty().append('<option value="">Guruhni tanlang</option>');
                    $('#semester').empty().append('<option value="">Semestrni tanlang</option>');
                    $('#subject').empty().append('<option value="">Fanni tanlang</option>');
                    $.ajax({
                        url: '{{ route('admin.get-groups-by-department') }}',
                        type: 'GET',
                        data: {
                            department_id: departmentId
                        },
                        success: function(data) {
                            if (selectedLevelCode) {
                                $('#level_code').val(selectedLevelCode).trigger('change');
                            } else {
                                $('#level_code').trigger('change.select2');
                            }
                            $.each(data, function(key, value) {
                                $('#group').append('<option value="' + key + '">' +
                                    value + '</option>');
                            });
                            if (selectedGroup) {
                                $('#group').val(selectedGroup).trigger('change');
                            } else {
                                $('#group').trigger('change.select2');
                            }
                        }
                    });
                }
            });
            $('#level_code').change(function() {
                var level_code = $(this).val();
                var departmentId = $('#department').val();
                if (level_code) {
                    $('#group').empty().append('<option value="">Guruhni tanlang</option>');
                    $('#semester').empty().append('<option value="">Semestrni tanlang</option>');
                    $('#subject').empty().append('<option value="">Fanni tanlang</option>');
                    $.ajax({
                        url: '{{ route('admin.get-level-codes') }}',
                        type: 'GET',
                        data: {
                            level_code: level_code,
                            department_id: departmentId
                        },
                        success: function(data) {
                            $.each(data.groups, function(key, value) {
                                $('#group').append('<option value="' + key + '">' +
                                    value + '</option>');
                            });
                            if (selectedGroup) {
                                $('#group').val(selectedGroup).trigger('change');
                            } else {
                                $('#group').trigger('change.select2');
                            }
                        }
                    });
                }
            });
            $('#group').change(function() {
                var groupId = $(this).val();
                if (groupId) {
                    $('#semester').empty().append('<option value="">Semestrni tanlang</option>');
                    $('#subject').empty().append('<option value="">Fanni tanlang</option>');
                    $.ajax({
                        url: '{{ route('admin.get-semesters-new') }}',
                        type: 'GET',
                        data: {
                            group_id: groupId
                        },
                        success: function(data) {
                            $.each(data, function(index, semester) {
                                var selected = '';
                                if (selectedSemester && semester.id ==
                                    selectedSemester) {
                                    selected = 'selected';
                                } else if (!selectedSemester && semester.current) {
                                    selected = 'selected';
                                }
                                $('#semester').append('<option value="' + semester.id +
                                    '" ' + selected + '>' + semester.name +
                                    '</option>');
                            });
                            if ($('#semester').val()) {
                                $('#semester').trigger('change');
                            } else {
                                $('#semester').trigger('change.select2');
                            }
                        }
                    });
                }
            });

            $('#semester').change(function() {
                var semesterId = $(this).val();
                var groupId = $('#group').val();
                if (semesterId && groupId) {
                    $('#subject').empty().append('<option value="">Fanni tanlang</option>');
                    $.ajax({
                        url: '{{ route('admin.get-subjects-new') }}',
                        type: 'GET',
                        data: {
                            semester_id: semesterId,
                            group_id: groupId
                        },
                        success: function(data) {
                            $.each(data, function(key, value) {
                                $('#subject').append('<option value="' + key + '">' +
                                    value + '</option>');
                            });
                            if (selectedSubject) {
                                $('#subject').val(selectedSubject).trigger('change.select2');
                            } else {
                                $('#subject').trigger('change.select2');
                            }
                        }
                    });
                }
            });

            @if (request('department'))
                $('#department').val(@json(request('department'))).trigger('change');
            @endif

            $('#exportButton').click(function() {
                var department = $('#department').val();
                var level_code = $('#level_code').val();
                var group = $('#group').val();
                var semester = $('#semester').val();
                var subject = $('#subject').val();
                var viewType = $('#viewType').val();

                var exportUrl = '{{ route('admin.student-grades-week.export') }}' +
                    '?department=' + department +
                    '&level_code=' + level_code +
                    '&group=' + group +
                    '&semester=' + semester +
                    '&subject=' + subject +
                    '&viewType=' + viewType;

                window.location.href = exportUrl;
            });
            $('#exportButtonBox').click(function() {
                var department = $('#department').val();
                var level_code = $('#level_code').val();
                var group = $('#group').val();
                var semester = $('#semester').val();
                var subject = $('#subject').val();
                var viewType = $('#viewType').val();

                var exportUrl = '{{ route('admin.student-grades-week.export-box') }}' +
                    '?department=' + department +
                    '&level_code=' + level_code +
                    '&group=' + group +
                    '&semester=' + semester +
                    '&subject=' + subject +
                    '&viewType=' + viewType;

                window.location.href = exportUrl;
            });
        });
    </script>

</x-app-layout>
