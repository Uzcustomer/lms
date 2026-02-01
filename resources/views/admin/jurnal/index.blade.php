<x-sidebar-layout title="Jurnal" breadcrumb="Jurnal" pageTitle="Jurnal">
    @push('styles')
    <style>
        .filter-select {
            min-width: 140px;
        }
        .journal-info {
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .journal-info-item {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
        }
        .journal-info-item:last-child {
            margin-bottom: 0;
        }
        .journal-info-item strong {
            min-width: 120px;
            color: #0369a1;
        }
        .date-col {
            min-width: 40px;
            max-width: 40px;
        }
        .rotate-header {
            writing-mode: vertical-rl;
            text-orientation: mixed;
            transform: rotate(180deg);
            white-space: nowrap;
            font-size: 11px;
            padding: 5px 2px;
            height: 80px;
        }
        .student-row:nth-child(even) {
            background-color: #f9fafb;
        }
        .student-row:nth-child(even) .student-name {
            background-color: #f9fafb;
        }
        .nb-cell {
            background-color: #fef2f2 !important;
            color: #dc2626;
            font-weight: bold;
        }
        .low-grade {
            background-color: #fef3c7 !important;
            color: #d97706;
        }
        .good-grade {
            background-color: #d1fae5 !important;
            color: #059669;
        }
    </style>
    @endpush

    <!-- Filtrlar -->
    <div class="filter-card">
        <form id="filterForm" method="GET" action="{{ route('admin.jurnal.index') }}">
            <div class="filter-row">
                <!-- Bo'lim -->
                <div class="filter-group">
                    <label>Bo'lim (Fakultet)</label>
                    <select name="department_id" id="department_id" class="filter-select">
                        <option value="">Tanlang...</option>
                        @foreach($departments as $dept)
                            <option value="{{ $dept->id }}" {{ request('department_id') == $dept->id ? 'selected' : '' }}>
                                {{ $dept->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Kurs -->
                <div class="filter-group">
                    <label>Kurs</label>
                    <select name="level_code" id="level_code" class="filter-select">
                        <option value="">Tanlang...</option>
                        @foreach($levelCodes as $code => $name)
                            <option value="{{ $code }}" {{ request('level_code') == $code ? 'selected' : '' }}>
                                {{ $name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Guruh -->
                <div class="filter-group">
                    <label>Guruh</label>
                    <select name="group_id" id="group_id" class="filter-select">
                        <option value="">Tanlang...</option>
                        @foreach($groups as $group)
                            <option value="{{ $group->id }}" {{ request('group_id') == $group->id ? 'selected' : '' }}>
                                {{ $group->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Semestr -->
                <div class="filter-group">
                    <label>Semestr</label>
                    <select name="semester_id" id="semester_id" class="filter-select">
                        <option value="">Tanlang...</option>
                        @foreach($semesters as $sem)
                            <option value="{{ $sem->id }}" {{ request('semester_id') == $sem->id ? 'selected' : '' }}>
                                {{ $sem->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Fan -->
                <div class="filter-group">
                    <label>Fan</label>
                    <select name="subject_id" id="subject_id" class="filter-select">
                        <option value="">Tanlang...</option>
                        @foreach($subjects as $subj)
                            <option value="{{ $subj->id }}" {{ request('subject_id') == $subj->id ? 'selected' : '' }}>
                                {{ $subj->subject_name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Ko'rsatish button -->
                <div class="filter-group" style="flex: 0;">
                    <label>&nbsp;</label>
                    <button type="submit" class="btn-filter">
                        <i class="fas fa-search mr-1"></i> Ko'rsatish
                    </button>
                </div>
            </div>
        </form>
    </div>

    @if($selectedSubject)
    <!-- Jurnal ma'lumotlari -->
    <div class="journal-info">
        <div style="display: flex; flex-wrap: wrap; gap: 30px;">
            <div>
                <div class="journal-info-item">
                    <strong>Fan:</strong> {{ $selectedSubject->subject_name }}
                </div>
                <div class="journal-info-item">
                    <strong>Guruh:</strong> {{ $selectedGroup->name }}
                </div>
            </div>
            <div>
                <div class="journal-info-item">
                    <strong>O'qituvchi:</strong> {{ $teacherName ?? 'Noma\'lum' }}
                </div>
                <div class="journal-info-item">
                    <strong>Semestr:</strong> {{ $selectedSemester->name }}
                </div>
            </div>
        </div>
    </div>

    <!-- Jurnal jadvali -->
    <div class="journal-table-wrapper">
        <table class="journal-table">
            <thead>
                <tr>
                    <th rowspan="2" style="min-width: 50px;">N<br>T/R</th>
                    <th rowspan="2" class="student-name">Talabaning F.I.SH.</th>

                    @if($dates->count() > 0)
                    <th colspan="{{ $dates->count() }}">Davomat va joriy yil natijalari (baholash 100% hisobidan)</th>
                    @endif

                    <th rowspan="2" class="summary-cell">JN o'rtacha (%)</th>
                    <th rowspan="2" class="summary-cell">TMI o'rtacha (%)</th>
                    <th rowspan="2" class="summary-cell">Oraliq nazorat (%)</th>
                    <th colspan="2">Yakuniy nazorat (%)</th>
                </tr>
                <tr>
                    @foreach($dates as $date)
                    <th class="date-col">
                        <div class="rotate-header">{{ $date->format('d.m') }}</div>
                    </th>
                    @endforeach
                    <th class="summary-cell">OSKI</th>
                    <th class="summary-cell">Test</th>
                </tr>
            </thead>
            <tbody>
                @forelse($students as $index => $student)
                    @php
                        $studentData = $gradesData[$student->hemis_id] ?? null;
                    @endphp
                    <tr class="student-row">
                        <td>{{ $index + 1 }}</td>
                        <td class="student-name">{{ $student->full_name }}</td>

                        @foreach($dates as $date)
                            @php
                                $dateKey = $date->format('Y-m-d');
                                $grade = $studentData['daily'][$dateKey] ?? null;
                                $isAbsent = $studentData['daily'][$dateKey . '_absent'] ?? false;
                                $cellClass = '';
                                if ($isAbsent) {
                                    $cellClass = 'nb-cell';
                                } elseif ($grade !== null && $grade < 60) {
                                    $cellClass = 'low-grade';
                                } elseif ($grade !== null && $grade >= 80) {
                                    $cellClass = 'good-grade';
                                }
                            @endphp
                            <td class="grade-cell {{ $cellClass }}">
                                @if($isAbsent)
                                    Nb
                                @else
                                    {{ $grade ?? '' }}
                                @endif
                            </td>
                        @endforeach

                        <!-- JN o'rtacha -->
                        <td class="summary-cell {{ ($studentData['jn'] ?? 0) < 60 ? 'low-grade' : (($studentData['jn'] ?? 0) >= 80 ? 'good-grade' : '') }}">
                            {{ $studentData['jn'] ?? 0 }}
                        </td>

                        <!-- MT o'rtacha -->
                        <td class="summary-cell {{ ($studentData['mt'] ?? 0) < 60 ? 'low-grade' : (($studentData['mt'] ?? 0) >= 80 ? 'good-grade' : '') }}">
                            {{ $studentData['mt'] ?? 0 }}
                        </td>

                        <!-- Oraliq nazorat -->
                        <td class="summary-cell {{ ($studentData['oraliq'] ?? 0) < 60 ? 'low-grade' : (($studentData['oraliq'] ?? 0) >= 80 ? 'good-grade' : '') }}">
                            {{ $studentData['oraliq'] ?? 0 }}
                        </td>

                        <!-- OSKI -->
                        <td class="summary-cell {{ ($studentData['oski'] ?? 0) < 60 ? 'low-grade' : (($studentData['oski'] ?? 0) >= 80 ? 'good-grade' : '') }}">
                            {{ $studentData['oski'] ?? 0 }}
                        </td>

                        <!-- Test -->
                        <td class="summary-cell {{ ($studentData['test'] ?? 0) < 60 ? 'low-grade' : (($studentData['test'] ?? 0) >= 80 ? 'good-grade' : '') }}">
                            {{ $studentData['test'] ?? 0 }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ 8 + $dates->count() }}" class="text-center py-4" style="color: #6b7280;">
                            Talabalar topilmadi
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @else
    <!-- Bo'sh holat -->
    <div style="text-align: center; padding: 60px 20px;">
        <i class="fas fa-book-open" style="font-size: 64px; color: #d1d5db; margin-bottom: 20px; display: block;"></i>
        <h3 style="font-size: 20px; color: #6b7280; margin-bottom: 10px;">Jurnalni ko'rish uchun filterlarni tanlang</h3>
        <p style="color: #9ca3af;">Bo'lim, kurs, guruh, semestr va fanni tanlang</p>
    </div>
    @endif

    @push('scripts')
    <script>
    $(document).ready(function() {
        // Cascade filters
        $('#department_id, #level_code').change(function() {
            loadGroups();
        });

        $('#group_id').change(function() {
            loadSemesters();
            $('#subject_id').html('<option value="">Tanlang...</option>');
        });

        $('#semester_id').change(function() {
            loadSubjects();
        });

        function loadGroups() {
            var departmentId = $('#department_id').val();
            var levelCode = $('#level_code').val();

            if (!departmentId && !levelCode) {
                $('#group_id').html('<option value="">Tanlang...</option>');
                return;
            }

            $.ajax({
                url: '{{ route("admin.jurnal.get-groups") }}',
                method: 'GET',
                data: {
                    department_id: departmentId,
                    level_code: levelCode
                },
                success: function(data) {
                    var options = '<option value="">Tanlang...</option>';
                    data.forEach(function(group) {
                        options += '<option value="' + group.id + '">' + group.name + '</option>';
                    });
                    $('#group_id').html(options);
                    $('#semester_id').html('<option value="">Tanlang...</option>');
                    $('#subject_id').html('<option value="">Tanlang...</option>');
                }
            });
        }

        function loadSemesters() {
            var groupId = $('#group_id').val();

            if (!groupId) {
                $('#semester_id').html('<option value="">Tanlang...</option>');
                return;
            }

            $.ajax({
                url: '{{ route("admin.jurnal.get-semesters") }}',
                method: 'GET',
                data: { group_id: groupId },
                success: function(data) {
                    var options = '<option value="">Tanlang...</option>';
                    data.forEach(function(sem) {
                        var selected = sem.current ? ' selected' : '';
                        options += '<option value="' + sem.id + '"' + selected + '>' + sem.name + '</option>';
                    });
                    $('#semester_id').html(options);
                }
            });
        }

        function loadSubjects() {
            var groupId = $('#group_id').val();
            var semesterId = $('#semester_id').val();

            if (!groupId || !semesterId) {
                $('#subject_id').html('<option value="">Tanlang...</option>');
                return;
            }

            $.ajax({
                url: '{{ route("admin.jurnal.get-subjects") }}',
                method: 'GET',
                data: {
                    group_id: groupId,
                    semester_id: semesterId
                },
                success: function(data) {
                    var options = '<option value="">Tanlang...</option>';
                    data.forEach(function(subj) {
                        options += '<option value="' + subj.id + '">' + subj.name + '</option>';
                    });
                    $('#subject_id').html(options);
                }
            });
        }
    });
    </script>
    @endpush
</x-sidebar-layout>
