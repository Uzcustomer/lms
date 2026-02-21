<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Yakuniy nazoratlar jadvali
            </h2>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="max-w-full mx-auto sm:px-4 lg:px-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100">

                <!-- Filters -->
                <div class="filter-container">
                    <!-- Row 1: Ta'lim turi, Fakultet, Yo'nalish, Sanadan, Sanagacha, Joriy semestr -->
                    <div class="filter-row">
                        <div class="filter-item" style="min-width: 140px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#3b82f6;"></span> Ta'lim turi</label>
                            <select id="education_type" class="select2" style="width: 100%;">
                                <option value="">Barchasi</option>
                            </select>
                        </div>
                        <div class="filter-item" style="flex: 1; min-width: 170px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#10b981;"></span> Fakultet</label>
                            <select id="department_id" class="select2" style="width: 100%;">
                                <option value="">Barchasi</option>
                            </select>
                        </div>
                        <div class="filter-item" style="flex: 1; min-width: 170px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#06b6d4;"></span> Yo'nalish</label>
                            <select id="specialty_id" class="select2" style="width: 100%;">
                                <option value="">Barchasi</option>
                            </select>
                        </div>
                        <div class="filter-item" style="min-width: 145px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#f59e0b;"></span> YN sanasi (dan)</label>
                            <input type="text" id="date_from" class="date-input sc-date" autocomplete="off" placeholder="dd.mm.yyyy" />
                        </div>
                        <div class="filter-item" style="min-width: 145px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#f59e0b;"></span> YN sanasi (gacha)</label>
                            <input type="text" id="date_to" class="date-input sc-date" autocomplete="off" placeholder="dd.mm.yyyy" />
                        </div>
                        <div class="filter-item" style="min-width: 150px;">
                            <label class="filter-label">&nbsp;</label>
                            <div class="toggle-switch {{ ($currentSemesterToggle ?? '1') === '1' ? 'active' : '' }}" id="current-semester-toggle" onclick="toggleSemester()">
                                <div class="toggle-track"><div class="toggle-thumb"></div></div>
                                <span class="toggle-label">Joriy semestr</span>
                            </div>
                        </div>
                    </div>
                    <!-- Row 2: Kurs, Semestr, Guruh, Fan, Holat, Qidirish -->
                    <div class="filter-row">
                        <div class="filter-item" style="min-width: 110px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#8b5cf6;"></span> Kurs</label>
                            <select id="level_code" class="select2" style="width: 100%;"><option value="">Barchasi</option></select>
                        </div>
                        <div class="filter-item" style="min-width: 130px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#14b8a6;"></span> Semestr</label>
                            <select id="semester_code" class="select2" style="width: 100%;">
                                <option value="">Barchasi</option>
                            </select>
                        </div>
                        <div class="filter-item" style="min-width: 140px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#1a3268;"></span> Guruh</label>
                            <select id="group_id" class="select2" style="width: 100%;">
                                <option value="">Barchasi</option>
                            </select>
                        </div>
                        <div class="filter-item" style="flex: 1; min-width: 200px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#0f172a;"></span> Fan</label>
                            <select id="subject_id" class="select2" style="width: 100%;"><option value="">Barchasi</option></select>
                        </div>
                        <div class="filter-item" style="min-width: 150px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#ef4444;"></span> Holat</label>
                            <select id="status" class="select2" style="width: 100%;">
                                <option value="">Barchasi</option>
                                <option value="belgilangan" {{ ($selectedStatus ?? '') == 'belgilangan' ? 'selected' : '' }}>Belgilangan</option>
                                <option value="belgilanmagan" {{ ($selectedStatus ?? '') == 'belgilanmagan' ? 'selected' : '' }}>Belgilanmagan</option>
                            </select>
                        </div>
                        <div class="filter-item" style="min-width: 120px;">
                            <label class="filter-label">&nbsp;</label>
                            <div style="display:flex;gap:6px;align-items:center;">
                                <button type="button" class="btn-refresh" id="btn-refresh-quiz" onclick="refreshQuizCounts()">
                                    <svg class="refresh-icon" style="width:15px;height:15px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                    <span id="refresh-label">Yangilash</span>
                                </button>
                                <button type="button" class="btn-calc" onclick="applyFilter()">
                                    <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                                    Qidirish
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Results -->
                @if($scheduleData->count() > 0)
                <div>
                    <div style="padding:10px 20px;background:#f0f4f8;border-bottom:1px solid #dbe4ef;display:flex;align-items:center;gap:12px;">
                        <span style="background:#2b5ea7;color:#fff;padding:6px 14px;font-size:13px;border-radius:8px;font-weight:700;">
                            Imtihon jadvali
                            @if($currentEducationYear)
                                ({{ $currentEducationYear }})
                            @endif
                        </span>
                        <span style="font-size:12px;color:#64748b;">
                            O'quv bo'limi tomonidan belgilangan yakuniy nazorat sanalari
                        </span>
                    </div>

                    <div style="overflow-x:auto;">
                        <table class="schedule-table">
                            <thead>
                                <tr class="header-row">
                                    <th style="width:44px;padding-left:16px;">#</th>
                                    <th class="sortable" data-col="1">Guruh <span class="sort-icon"></span></th>
                                    <th class="sortable" data-col="2">Yo'nalish <span class="sort-icon"></span></th>
                                    <th class="sortable" data-col="3" style="width:100px;">Fan kodi <span class="sort-icon"></span></th>
                                    <th class="sortable" data-col="4">Fan nomi <span class="sort-icon"></span></th>
                                    <th class="sortable" data-col="5" style="width:70px;text-align:center;">Kurs <span class="sort-icon"></span></th>
                                    <th class="sortable" data-col="6" style="width:90px;text-align:center;">Semestr <span class="sort-icon"></span></th>
                                    <th style="width:100px;text-align:center;">Urinish</th>
                                    <th class="sortable" data-col="8" style="width:100px;text-align:center;">YN turi <span class="sort-icon"></span></th>
                                    <th class="sortable" data-col="9" style="width:140px;text-align:center;">Sana <span class="sort-icon"></span></th>
                                    <th class="sortable" data-col="10" style="width:100px;text-align:center;">Topshirgan <span class="sort-icon"></span></th>
                                </tr>
                                <tr class="filter-header-row">
                                    <th></th>
                                    <th><select class="col-filter" data-col="1"><option value="">Barchasi</option></select></th>
                                    <th><select class="col-filter" data-col="2"><option value="">Barchasi</option></select></th>
                                    <th><select class="col-filter" data-col="3"><option value="">Barchasi</option></select></th>
                                    <th><select class="col-filter" data-col="4"><option value="">Barchasi</option></select></th>
                                    <th><select class="col-filter" data-col="5"><option value="">Barchasi</option></select></th>
                                    <th><select class="col-filter" data-col="6"><option value="">Barchasi</option></select></th>
                                    <th></th>
                                    <th><select class="col-filter" data-col="8"><option value="">Barchasi</option></select></th>
                                    <th><select class="col-filter" data-col="9"><option value="">Barchasi</option></select></th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="schedule-tbody">
                                @php
                                    $rowIndex = 0;
                                    $today = now()->format('Y-m-d');
                                @endphp
                                @foreach($scheduleData as $groupHemisId => $items)
                                    @foreach($items as $item)
                                        <tr class="data-row" data-group-id="{{ $item['group']->group_hemis_id }}" data-subject-id="{{ $item['subject']->subject_id ?? '' }}" data-yn-type="{{ $item['yn_type'] ?? '' }}">
                                            <td class="row-num" style="color:#94a3b8;font-weight:500;padding-left:16px;">{{ ++$rowIndex }}</td>
                                            <td data-sort-value="{{ $item['group']->name }}" style="font-weight:600;color:#0f172a;">{{ $item['group']->name }}</td>
                                            <td data-sort-value="{{ $item['specialty_name'] }}" style="color:#64748b;font-size:12px;">{{ $item['specialty_name'] }}</td>
                                            <td data-sort-value="{{ $item['subject_code'] }}" style="color:#64748b;font-size:12px;">{{ $item['subject_code'] }}</td>
                                            <td data-sort-value="{{ $item['subject']->subject_name }}" style="font-weight:500;color:#1e293b;">{{ $item['subject']->subject_name }}</td>
                                            <td data-sort-value="{{ $item['level_name'] }}" style="text-align:center;color:#1e293b;font-weight:500;">{{ $item['level_name'] }}</td>
                                            <td data-sort-value="{{ $item['semester_name'] }}" style="text-align:center;color:#64748b;font-size:12px;">{{ $item['semester_name'] }}</td>
                                            <td style="text-align:center;padding:4px 8px;"><span class="attempt-badge">1-urinish</span></td>
                                            <td data-sort-value="{{ $item['yn_type'] ?? '' }}" style="text-align:center;padding:4px 8px;">
                                                @if($item['yn_type'] ?? null)
                                                    <span class="yn-type-badge yn-type-{{ strtolower($item['yn_type']) }}">{{ $item['yn_type'] }}</span>
                                                @else
                                                    <span style="color:#cbd5e1;">—</span>
                                                @endif
                                            </td>
                                            <td data-sort-value="{{ ($item['yn_na'] ?? false) ? '' : (($item['yn_date'] ?? null) ? \Carbon\Carbon::parse($item['yn_date'])->format('d.m.Y') : '') }}" style="text-align:center;padding:4px 8px;">
                                                @if($item['yn_na'] ?? false)
                                                    <span class="na-badge">N/A</span>
                                                @elseif($item['yn_date'] ?? null)
                                                    @php
                                                        $ynDate = $item['yn_date_carbon'] ?? null;
                                                        $badgeClass = (($item['yn_type'] ?? '') === 'Test') ? 'badge-pending-blue' : 'badge-pending';
                                                        if ($ynDate && $ynDate->format('Y-m-d') === $today) $badgeClass = 'badge-today';
                                                        elseif ($ynDate && $ynDate->isPast()) $badgeClass = 'badge-passed';
                                                        elseif ($ynDate && $ynDate->diffInDays(now()) <= 3) $badgeClass = 'badge-soon';
                                                    @endphp
                                                    <span class="date-badge {{ $badgeClass }}">{{ $ynDate?->format('d.m.Y') }}</span>
                                                @else
                                                    <span style="color:#cbd5e1;">—</span>
                                                @endif
                                            </td>
                                            <td class="td-quiz-count" data-sort-value="{{ $item['quiz_count'] ?? 0 }}" style="text-align:center;">
                                                @php
                                                    $sc = $item['student_count'] ?? 0;
                                                    $qc = $item['quiz_count'] ?? 0;
                                                    $qcClass = $qc == 0 ? 'quiz-count-zero' : ($qc >= $sc ? 'quiz-count-full' : 'quiz-count-partial');
                                                @endphp
                                                <span class="{{ $qcClass }}">{{ $qc }}/{{ $sc }}</span>
                                            </td>
                                        </tr>
                                    @endforeach
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                @elseif($isSearched)
                    <div style="padding:60px 20px;text-align:center;">
                        <svg style="width:56px;height:56px;margin:0 auto 12px;color:#cbd5e1;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        <p style="color:#64748b;font-size:15px;font-weight:600;">Jadval topilmadi</p>
                        <p style="color:#94a3b8;font-size:13px;margin-top:4px;">Tanlangan filtrlar bo'yicha imtihon sanalari topilmadi.</p>
                    </div>
                @else
                    <div style="padding:60px 20px;text-align:center;">
                        <svg style="width:56px;height:56px;margin:0 auto 12px;color:#cbd5e1;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path></svg>
                        <p style="color:#64748b;font-size:15px;font-weight:600;">Filtrlarni tanlang va "Qidirish" tugmasini bosing</p>
                        <p style="color:#94a3b8;font-size:13px;margin-top:4px;">Natijalar shu yerda ko'rsatiladi</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <link href="/css/scroll-calendar.css" rel="stylesheet" />
    <script src="/js/scroll-calendar.js"></script>

    <script>
        var isUpdatingFilters = false;
        var filterUrl = '{{ route($routePrefix . ".academic-schedule.get-filter-options") }}';

        var initialValues = {
            education_type: '{{ $selectedEducationType ?? '' }}',
            department_id: '{{ $selectedDepartment ?? '' }}',
            specialty_id: '{{ $selectedSpecialty ?? '' }}',
            level_code: '{{ $selectedLevelCode ?? '' }}',
            semester_code: '{{ $selectedSemester ?? '' }}',
            group_id: '{{ $selectedGroup ?? '' }}',
            subject_id: '{{ $selectedSubject ?? '' }}'
        };

        function stripSpecialChars(s) { return s.replace(/[\/\(\),\-\.\s]/g, '').toLowerCase(); }
        function fuzzyMatcher(params, data) {
            if ($.trim(params.term) === '') return data;
            if (typeof data.text === 'undefined') return null;
            if (stripSpecialChars(data.text).indexOf(stripSpecialChars(params.term)) > -1) return $.extend({}, data, true);
            if (data.text.toLowerCase().indexOf(params.term.toLowerCase()) > -1) return $.extend({}, data, true);
            return null;
        }

        function toggleSemester() {
            var btn = document.getElementById('current-semester-toggle');
            btn.classList.toggle('active');
        }

        function fp() {
            return {
                education_type: $('#education_type').val() || '',
                department_id: $('#department_id').val() || '',
                specialty_id: $('#specialty_id').val() || '',
                level_code: $('#level_code').val() || '',
                semester_code: $('#semester_code').val() || '',
                current_semester: document.getElementById('current-semester-toggle').classList.contains('active') ? '1' : '0'
            };
        }

        function updateSelect(selector, items, valueKey, textKey) {
            var $el = $(selector);
            var currentVal = $el.val();
            $el.empty().append('<option value="">Barchasi</option>');
            $.each(items, function(i, item) {
                $el.append('<option value="' + item[valueKey] + '">' + item[textKey] + '</option>');
            });
            if (currentVal && $el.find('option[value="' + currentVal + '"]').length) {
                $el.val(currentVal);
            }
            $el.trigger('change.select2');
        }

        function loadAllFilters(callback) {
            if (isUpdatingFilters) return;
            isUpdatingFilters = true;

            $.get(filterUrl, fp(), function(data) {
                updateSelect('#education_type', data.educationTypes, 'education_type_code', 'education_type_name');
                updateSelect('#department_id', data.departments, 'department_hemis_id', 'name');
                updateSelect('#specialty_id', data.specialties, 'specialty_hemis_id', 'name');
                updateSelect('#level_code', data.levels, 'level_code', 'level_name');
                updateSelect('#semester_code', data.semesters, 'code', 'name');
                updateSelect('#group_id', data.groups, 'group_hemis_id', 'name');
                updateSelect('#subject_id', data.subjects, 'subject_id', 'subject_name');

                isUpdatingFilters = false;
                if (callback) callback();
            }).fail(function() {
                isUpdatingFilters = false;
            });
        }

        function initFilters() {
            isUpdatingFilters = true;
            $.get(filterUrl, initialValues, function(data) {
                updateSelect('#education_type', data.educationTypes, 'education_type_code', 'education_type_name');
                updateSelect('#department_id', data.departments, 'department_hemis_id', 'name');
                updateSelect('#specialty_id', data.specialties, 'specialty_hemis_id', 'name');
                updateSelect('#level_code', data.levels, 'level_code', 'level_name');
                updateSelect('#semester_code', data.semesters, 'code', 'name');
                updateSelect('#group_id', data.groups, 'group_hemis_id', 'name');
                updateSelect('#subject_id', data.subjects, 'subject_id', 'subject_name');

                if (initialValues.education_type) $('#education_type').val(initialValues.education_type).trigger('change.select2');
                if (initialValues.department_id) $('#department_id').val(initialValues.department_id).trigger('change.select2');
                if (initialValues.specialty_id) $('#specialty_id').val(initialValues.specialty_id).trigger('change.select2');
                if (initialValues.level_code) $('#level_code').val(initialValues.level_code).trigger('change.select2');
                if (initialValues.semester_code) $('#semester_code').val(initialValues.semester_code).trigger('change.select2');
                if (initialValues.group_id) $('#group_id').val(initialValues.group_id).trigger('change.select2');
                if (initialValues.subject_id) $('#subject_id').val(initialValues.subject_id).trigger('change.select2');

                isUpdatingFilters = false;
            }).fail(function() {
                isUpdatingFilters = false;
            });
        }

        var refreshQuizUrl = '{{ route($routePrefix . ".academic-schedule.test-center.refresh-quiz-counts") }}';

        function refreshQuizCounts() {
            var rows = document.querySelectorAll('#schedule-tbody tr.data-row');
            if (!rows.length) return;

            var btn = document.getElementById('btn-refresh-quiz');
            var icon = btn.querySelector('.refresh-icon');
            var label = document.getElementById('refresh-label');
            btn.disabled = true;
            icon.classList.add('spinning');
            label.textContent = 'Yangilanmoqda...';

            // Collect unique group+subject+yn_type combinations
            var seen = {};
            var items = [];
            rows.forEach(function(row) {
                var gid = row.getAttribute('data-group-id');
                var sid = row.getAttribute('data-subject-id');
                var yn = row.getAttribute('data-yn-type');
                var key = gid + '|' + sid + '|' + yn;
                if (!seen[key]) {
                    seen[key] = true;
                    items.push({ group_id: gid, subject_id: sid, yn_type: yn });
                }
            });

            $.ajax({
                url: refreshQuizUrl,
                method: 'POST',
                data: JSON.stringify({ items: items }),
                contentType: 'application/json',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content') },
                success: function(data) {
                    // Build lookup
                    var lookup = {};
                    (data.counts || []).forEach(function(c) {
                        lookup[c.group_id + '|' + c.subject_id + '|' + c.yn_type] = c;
                    });

                    // Update each row
                    rows.forEach(function(row) {
                        var key = row.getAttribute('data-group-id') + '|' + row.getAttribute('data-subject-id') + '|' + row.getAttribute('data-yn-type');
                        var info = lookup[key];
                        if (!info) return;

                        var qcCell = row.querySelector('.td-quiz-count');
                        if (qcCell) {
                            var cls = info.quiz_count == 0 ? 'quiz-count-zero' : (info.quiz_count >= info.student_count ? 'quiz-count-full' : 'quiz-count-partial');
                            qcCell.innerHTML = '<span class="' + cls + '">' + info.quiz_count + '/' + info.student_count + '</span>';
                            qcCell.setAttribute('data-sort-value', info.quiz_count);
                        }
                    });

                    label.textContent = 'Yangilash';
                    icon.classList.remove('spinning');
                    btn.disabled = false;
                },
                error: function() {
                    label.textContent = 'Yangilash';
                    icon.classList.remove('spinning');
                    btn.disabled = false;
                    alert('Xatolik yuz berdi. Qaytadan urinib ko\'ring.');
                }
            });
        }

        function applyFilter() {
            var url = new URL(window.location.href.split('?')[0]);
            url.searchParams.set('searched', '1');
            var et = $('#education_type').val();
            var dept = $('#department_id').val();
            var spec = $('#specialty_id').val();
            var lc = $('#level_code').val();
            var sem = $('#semester_code').val();
            var grp = $('#group_id').val();
            var subj = $('#subject_id').val();
            var status = $('#status').val();
            var cs = document.getElementById('current-semester-toggle').classList.contains('active') ? '1' : '0';
            var dateFrom = $('#date_from').val();
            var dateTo = $('#date_to').val();
            if (et) url.searchParams.set('education_type', et);
            if (dept) url.searchParams.set('department_id', dept);
            if (spec) url.searchParams.set('specialty_id', spec);
            if (lc) url.searchParams.set('level_code', lc);
            if (sem) url.searchParams.set('semester_code', sem);
            if (grp) url.searchParams.set('group_id', grp);
            if (subj) url.searchParams.set('subject_id', subj);
            if (status) url.searchParams.set('status', status);
            if (dateFrom) url.searchParams.set('date_from', dateFrom);
            if (dateTo) url.searchParams.set('date_to', dateTo);
            url.searchParams.set('current_semester', cs);
            window.location.href = url.toString();
        }

        $(document).ready(function() {
            $('.select2').each(function() {
                $(this).select2({ theme: 'classic', width: '100%', allowClear: true, placeholder: $(this).find('option:first').text(), matcher: fuzzyMatcher })
                .on('select2:open', function() { setTimeout(function() { var s = document.querySelector('.select2-container--open .select2-search__field'); if(s) s.focus(); }, 10); });
            });

            $('#education_type, #department_id, #specialty_id, #level_code, #semester_code').on('change', function() {
                if (!isUpdatingFilters) loadAllFilters();
            });

            initFilters();

            // Scroll calendar for date filters
            var calFrom = new ScrollCalendar('date_from');
            var calTo = new ScrollCalendar('date_to');
            @if($dateFrom)
                calFrom.setValue('{{ $dateFrom }}');
            @endif
            @if($dateTo)
                calTo.setValue('{{ $dateTo }}');
            @endif

            // Sort funksiyasi
            initTableSort();

            // Ustun filtrlari
            populateColumnFilters();
            document.querySelectorAll('.col-filter').forEach(function(sel) {
                sel.addEventListener('change', function() { applyColumnFilters(); });
            });
        });

        // Ustun filtrlarini to'ldirish
        function populateColumnFilters() {
            document.querySelectorAll('.col-filter').forEach(function(sel) {
                var col = parseInt(sel.getAttribute('data-col'));
                var values = {};
                document.querySelectorAll('#schedule-tbody tr.data-row').forEach(function(row) {
                    var cell = row.cells[col];
                    if (!cell) return;
                    var val = (cell.getAttribute('data-sort-value') || cell.textContent || '').trim();
                    if (val && val !== '\u2014') values[val] = true;
                });
                var sorted = Object.keys(values).sort(function(a, b) { return a.localeCompare(b, 'uz'); });
                sel.innerHTML = '<option value="">Barchasi</option>';
                sorted.forEach(function(v) {
                    var opt = document.createElement('option');
                    opt.value = v;
                    opt.textContent = v;
                    sel.appendChild(opt);
                });
            });
        }

        function applyColumnFilters() {
            var filters = {};
            document.querySelectorAll('.col-filter').forEach(function(sel) {
                var col = parseInt(sel.getAttribute('data-col'));
                var val = sel.value;
                if (val) filters[col] = val;
            });
            var idx = 0;
            document.querySelectorAll('#schedule-tbody tr.data-row').forEach(function(row) {
                var show = true;
                for (var col in filters) {
                    var cell = row.cells[parseInt(col)];
                    if (!cell) { show = false; break; }
                    var cellVal = (cell.getAttribute('data-sort-value') || cell.textContent || '').trim();
                    if (cellVal !== filters[col]) { show = false; break; }
                }
                row.style.display = show ? '' : 'none';
                if (show) {
                    idx++;
                    var numCell = row.querySelector('.row-num');
                    if (numCell) numCell.textContent = idx;
                }
            });
        }

        // Jadval ustunlarini bosganda sort
        var currentSortCol = -1;
        var currentSortDir = 'asc';

        function initTableSort() {
            document.querySelectorAll('.sortable').forEach(function(th) {
                th.addEventListener('click', function() {
                    var col = parseInt(this.getAttribute('data-col'));
                    if (currentSortCol === col) {
                        currentSortDir = currentSortDir === 'asc' ? 'desc' : 'asc';
                    } else {
                        currentSortCol = col;
                        currentSortDir = 'asc';
                    }
                    sortTable(col, currentSortDir);
                    document.querySelectorAll('.sortable .sort-icon').forEach(function(s) { s.textContent = ''; });
                    this.querySelector('.sort-icon').textContent = currentSortDir === 'asc' ? ' \u25B2' : ' \u25BC';
                });
            });
        }

        function sortTable(colIndex, dir) {
            var tbody = document.getElementById('schedule-tbody');
            if (!tbody) return;
            var rows = Array.from(tbody.querySelectorAll('tr.data-row'));
            rows.sort(function(a, b) {
                var aCell = a.cells[colIndex];
                var bCell = b.cells[colIndex];
                var aVal = (aCell && aCell.getAttribute('data-sort-value')) || '';
                var bVal = (bCell && bCell.getAttribute('data-sort-value')) || '';
                if (/^\d+(\.\d+)?$/.test(aVal) && /^\d+(\.\d+)?$/.test(bVal)) {
                    return dir === 'asc' ? parseFloat(aVal) - parseFloat(bVal) : parseFloat(bVal) - parseFloat(aVal);
                }
                var dateRe = /^(\d{2})\.(\d{2})\.(\d{4})$/;
                var aM = aVal.match(dateRe);
                var bM = bVal.match(dateRe);
                if (aM && bM) {
                    var aD = aM[3] + aM[2] + aM[1];
                    var bD = bM[3] + bM[2] + bM[1];
                    return dir === 'asc' ? aD.localeCompare(bD) : bD.localeCompare(aD);
                }
                if (aM && !bM) return dir === 'asc' ? -1 : 1;
                if (!aM && bM) return dir === 'asc' ? 1 : -1;
                var cmp = aVal.localeCompare(bVal, 'uz');
                return dir === 'asc' ? cmp : -cmp;
            });
            rows.forEach(function(row, i) {
                tbody.appendChild(row);
                var numCell = row.querySelector('.row-num');
                if (numCell) numCell.textContent = i + 1;
            });
        }
    </script>

    <style>
        .filter-container { padding: 16px 20px 12px; background: linear-gradient(135deg, #f0f4f8, #e8edf5); border-bottom: 2px solid #dbe4ef; overflow: visible; position: relative; z-index: 20; }
        .filter-row { display: flex; gap: 10px; flex-wrap: nowrap; margin-bottom: 10px; align-items: flex-end; overflow: visible; }
        .filter-row:last-child { margin-bottom: 0; }
        .filter-label { display: flex; align-items: center; gap: 5px; margin-bottom: 4px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: #475569; }
        .fl-dot { width: 7px; height: 7px; border-radius: 50%; display: inline-block; flex-shrink: 0; }
        .filter-item { flex: 0 0 auto; }

        .select2-container--classic .select2-selection--single { height: 36px; border: 1px solid #cbd5e1; border-radius: 8px; background: #fff; transition: all 0.2s; box-shadow: 0 1px 2px rgba(0,0,0,0.04); }
        .select2-container--classic .select2-selection--single:hover { border-color: #2b5ea7; box-shadow: 0 0 0 2px rgba(43,94,167,0.1); }
        .select2-container--classic .select2-selection--single .select2-selection__rendered { line-height: 34px; padding-left: 10px; padding-right: 52px; color: #1e293b; font-size: 0.8rem; font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .select2-container--classic .select2-selection--single .select2-selection__arrow { height: 34px; width: 22px; background: transparent; border-left: none; right: 0; }
        .select2-container--classic .select2-selection--single .select2-selection__clear { position: absolute; right: 22px; top: 50%; transform: translateY(-50%); font-size: 16px; font-weight: bold; color: #94a3b8; cursor: pointer; padding: 2px 6px; z-index: 2; background: #fff; border-radius: 50%; line-height: 1; transition: all 0.15s; }
        .select2-container--classic .select2-selection--single .select2-selection__clear:hover { color: #fff; background: #ef4444; }
        .select2-dropdown { font-size: 0.8rem; border-radius: 8px; border: 1px solid #cbd5e1; box-shadow: 0 8px 24px rgba(0,0,0,0.12); }
        .select2-container--classic .select2-results__option--highlighted { background-color: #2b5ea7; }

        .date-input { height: 36px; border: 1px solid #cbd5e1; border-radius: 8px; padding: 0 30px 0 10px; font-size: 0.8rem; font-weight: 500; color: #1e293b; background: #fff; width: 100%; box-shadow: 0 1px 2px rgba(0,0,0,0.04); transition: all 0.2s; outline: none; }
        .date-input:hover { border-color: #2b5ea7; box-shadow: 0 0 0 2px rgba(43,94,167,0.1); }
        .date-input:focus { border-color: #2b5ea7; box-shadow: 0 0 0 3px rgba(43,94,167,0.15); }
        .date-input::placeholder { color: #94a3b8; font-weight: 400; }

        .toggle-switch { display: inline-flex; align-items: center; gap: 10px; cursor: pointer; padding: 6px 0; height: 36px; user-select: none; }
        .toggle-track { width: 40px; height: 22px; background: #cbd5e1; border-radius: 11px; position: relative; transition: background 0.25s; flex-shrink: 0; }
        .toggle-switch.active .toggle-track { background: linear-gradient(135deg, #2b5ea7, #3b7ddb); }
        .toggle-thumb { width: 18px; height: 18px; background: #fff; border-radius: 50%; position: absolute; top: 2px; left: 2px; transition: transform 0.25s; box-shadow: 0 1px 4px rgba(0,0,0,0.2); }
        .toggle-switch.active .toggle-thumb { transform: translateX(18px); }
        .toggle-label { font-size: 12px; font-weight: 600; color: #64748b; white-space: nowrap; }
        .toggle-switch.active .toggle-label { color: #1e3a5f; }

        .btn-refresh { display: inline-flex; align-items: center; gap: 7px; padding: 8px 16px; background: linear-gradient(135deg, #0891b2, #06b6d4); color: #fff; border: none; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 8px rgba(8,145,178,0.3); height: 36px; white-space: nowrap; }
        .btn-refresh:hover { background: linear-gradient(135deg, #0e7490, #0891b2); box-shadow: 0 4px 12px rgba(8,145,178,0.4); transform: translateY(-1px); }
        .btn-refresh:disabled { cursor: not-allowed; opacity: 0.5; }
        .btn-refresh .refresh-icon.spinning { animation: spin 0.8s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }

        .btn-calc { display: inline-flex; align-items: center; gap: 8px; padding: 8px 20px; background: linear-gradient(135deg, #2b5ea7, #3b7ddb); color: #fff; border: none; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 8px rgba(43,94,167,0.3); height: 36px; }
        .btn-calc:hover { background: linear-gradient(135deg, #1e4b8a, #2b5ea7); box-shadow: 0 4px 12px rgba(43,94,167,0.4); transform: translateY(-1px); }

        .schedule-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 13px; }
        .schedule-table thead { position: sticky; top: 0; z-index: 10; }
        .schedule-table thead tr.header-row { background: linear-gradient(135deg, #e8edf5, #dbe4ef, #d1d9e6); }
        .schedule-table thead tr.filter-header-row { background: #f0f4f8; }
        .schedule-table thead tr.filter-header-row th { padding: 4px 6px; border-bottom: 2px solid #93c5fd; }
        .col-filter { width: 100%; height: 26px; border: 1px solid #cbd5e1; border-radius: 5px; font-size: 11px; color: #334155; background: #fff; padding: 0 4px; cursor: pointer; outline: none; transition: all 0.2s; }
        .col-filter:hover { border-color: #2b5ea7; box-shadow: 0 0 0 2px rgba(43,94,167,0.1); }
        .col-filter:focus { border-color: #2b5ea7; box-shadow: 0 0 0 2px rgba(43,94,167,0.15); }
        .attempt-badge { display: inline-flex; padding: 4px 10px; font-size: 11px; font-weight: 700; border-radius: 6px; line-height: 1.3; background: #f0f4f8; color: #475569; letter-spacing: 0.02em; }
        .schedule-table th { padding: 14px 12px; text-align: left; font-weight: 600; font-size: 11.5px; color: #334155; text-transform: uppercase; letter-spacing: 0.05em; white-space: nowrap; border-bottom: 2px solid #cbd5e1; }
        .schedule-table th.sortable { cursor: pointer; user-select: none; transition: background 0.15s; }
        .schedule-table th.sortable:hover { background: rgba(43,94,167,0.1); }
        .sort-icon { font-size: 10px; color: #2b5ea7; }
        .data-row td { padding: 8px 12px; border-bottom: 1px solid #f1f5f9; font-size: 13px; }
        .data-row:hover td { background: #f8fafc; }

        .date-badge { display: inline-flex; padding: 4px 10px; font-size: 12px; font-weight: 600; border-radius: 6px; line-height: 1.3; }
        .badge-pending { background: #dcfce7; color: #166534; }
        .badge-pending-blue { background: #dbeafe; color: #1e40af; }
        .badge-today { background: #fef9c3; color: #854d0e; }
        .badge-soon { background: #ffedd5; color: #9a3412; }
        .badge-passed { background: #f1f5f9; color: #64748b; }

        .status-badge { display: inline-flex; padding: 4px 10px; font-size: 11px; font-weight: 700; border-radius: 6px; line-height: 1.3; text-transform: uppercase; letter-spacing: 0.03em; }
        .status-pending { background: #dcfce7; color: #166534; }
        .status-today { background: #fef9c3; color: #854d0e; }
        .status-soon { background: #ffedd5; color: #9a3412; }
        .status-passed { background: #f1f5f9; color: #64748b; }
        .status-empty { background: #fef2f2; color: #dc2626; }
        .na-badge { display: inline-flex; padding: 4px 10px; font-size: 11px; font-weight: 700; border-radius: 6px; line-height: 1.3; background: #fef2f2; color: #dc2626; text-transform: uppercase; letter-spacing: 0.03em; }

        .yn-type-badge { display: inline-flex; padding: 4px 12px; font-size: 11px; font-weight: 700; border-radius: 6px; line-height: 1.3; text-transform: uppercase; letter-spacing: 0.03em; }
        .yn-type-oski { background: #dcfce7; color: #166534; }
        .yn-type-test { background: #dbeafe; color: #1e40af; }

        .quiz-count-zero { display: inline-block; padding: 3px 9px; border-radius: 6px; font-size: 11.5px; font-weight: 700; background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
        .quiz-count-partial { display: inline-block; padding: 3px 9px; border-radius: 6px; font-size: 11.5px; font-weight: 700; background: #fffbeb; color: #d97706; border: 1px solid #fde68a; }
        .quiz-count-full { display: inline-block; padding: 3px 9px; border-radius: 6px; font-size: 11.5px; font-weight: 700; background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; }
    </style>
</x-app-layout>
