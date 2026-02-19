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
                    <div class="filter-row">
                        <div class="filter-item" style="flex: 1; min-width: 170px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#10b981;"></span> Fakultet</label>
                            <select id="department_id" class="select2" style="width: 100%;">
                                <option value="">Barchasi</option>
                                @foreach($departments as $dept)
                                    <option value="{{ $dept->department_hemis_id }}" {{ $selectedDepartment == $dept->department_hemis_id ? 'selected' : '' }}>{{ $dept->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="filter-item" style="flex: 1; min-width: 180px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#06b6d4;"></span> Yo'nalish</label>
                            <select id="specialty_id" class="select2" style="width: 100%;">
                                <option value="">Barchasi</option>
                                @foreach($specialties as $sp)
                                    <option value="{{ $sp->specialty_hemis_id }}" {{ $selectedSpecialty == $sp->specialty_hemis_id ? 'selected' : '' }}>{{ $sp->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="filter-item" style="min-width: 120px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#8b5cf6;"></span> Semestr</label>
                            <select id="semester_code" class="select2" style="width: 100%;">
                                <option value="">Tanlang</option>
                                @foreach($semesters as $sem)
                                    <option value="{{ $sem->code }}" {{ $selectedSemester == $sem->code ? 'selected' : '' }}>{{ $sem->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="filter-item" style="min-width: 140px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#1a3268;"></span> Guruh</label>
                            <select id="group_id" class="select2" style="width: 100%;">
                                <option value="">Barchasi</option>
                                @foreach($groups as $gr)
                                    <option value="{{ $gr->group_hemis_id }}" {{ $selectedGroup == $gr->group_hemis_id ? 'selected' : '' }}>{{ $gr->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="filter-item" style="min-width: 155px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#ef4444;"></span> Holat</label>
                            <select id="status" class="select2" style="width: 100%;">
                                <option value="">Barchasi</option>
                                <option value="belgilangan" {{ ($selectedStatus ?? '') == 'belgilangan' ? 'selected' : '' }}>Belgilangan</option>
                                <option value="belgilanmagan" {{ ($selectedStatus ?? '') == 'belgilanmagan' ? 'selected' : '' }}>Belgilanmagan</option>
                            </select>
                        </div>
                        <div class="filter-item" style="min-width: 120px;">
                            <label class="filter-label">&nbsp;</label>
                            <button type="button" class="btn-calc" onclick="applyFilter()">
                                <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                                Qidirish
                            </button>
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
                            O'quv bo'limi tomonidan belgilangan OSKI va Test imtihon sanalari
                        </span>
                    </div>

                    <div style="overflow-x:auto;">
                        <table class="schedule-table">
                            <thead>
                                <tr>
                                    <th style="width:44px;padding-left:16px;">#</th>
                                    <th>Guruh</th>
                                    <th>Yo'nalish</th>
                                    <th>Fan nomi</th>
                                    <th style="width:70px;text-align:center;">Kredit</th>
                                    <th style="width:130px;text-align:center;">OSKI sanasi</th>
                                    <th style="width:130px;text-align:center;">Test sanasi</th>
                                    <th style="width:110px;text-align:center;">Holat</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php $rowIndex = 0; @endphp
                                @foreach($scheduleData as $groupHemisId => $items)
                                    <tr class="group-header-row">
                                        <td colspan="8">
                                            {{ $items->first()['group']->name }}
                                            <span style="margin-left:8px;font-size:11px;font-weight:400;color:#3b82f6;">
                                                ({{ $items->first()['specialty_name'] }})
                                            </span>
                                        </td>
                                    </tr>
                                    @foreach($items as $item)
                                        @php
                                            $now = now();
                                            $oskiDate = $item['oski_date_carbon'] ?? null;
                                            $testDate = $item['test_date_carbon'] ?? null;

                                            $oskiPassed = $oskiDate && $oskiDate->lt($now);
                                            $testPassed = $testDate && $testDate->lt($now);
                                            $oskiToday = $oskiDate && $oskiDate->isToday();
                                            $testToday = $testDate && $testDate->isToday();
                                            $oskiSoon = $oskiDate && !$oskiPassed && $oskiDate->diffInDays($now) <= 3;
                                            $testSoon = $testDate && !$testPassed && $testDate->diffInDays($now) <= 3;

                                            $hasDate = $item['oski_date'] || $item['test_date'];
                                        @endphp
                                        <tr class="data-row">
                                            <td style="color:#94a3b8;font-weight:500;padding-left:16px;">{{ ++$rowIndex }}</td>
                                            <td style="font-weight:600;color:#0f172a;">{{ $item['group']->name }}</td>
                                            <td style="color:#64748b;font-size:12px;">{{ $item['specialty_name'] }}</td>
                                            <td style="font-weight:500;color:#1e293b;">{{ $item['subject']->subject_name }}</td>
                                            <td style="text-align:center;color:#64748b;">{{ $item['subject']->credit }}</td>
                                            <td style="text-align:center;">
                                                @if($item['oski_date'])
                                                    <span class="date-badge {{ $oskiToday ? 'badge-today' : ($oskiPassed ? 'badge-passed' : ($oskiSoon ? 'badge-soon' : 'badge-pending')) }}">
                                                        {{ \Carbon\Carbon::parse($item['oski_date'])->format('d.m.Y') }}
                                                    </span>
                                                @else
                                                    <span style="color:#cbd5e1;">—</span>
                                                @endif
                                            </td>
                                            <td style="text-align:center;">
                                                @if($item['test_date'])
                                                    <span class="date-badge {{ $testToday ? 'badge-today' : ($testPassed ? 'badge-passed' : ($testSoon ? 'badge-soon' : 'badge-pending-blue')) }}">
                                                        {{ \Carbon\Carbon::parse($item['test_date'])->format('d.m.Y') }}
                                                    </span>
                                                @else
                                                    <span style="color:#cbd5e1;">—</span>
                                                @endif
                                            </td>
                                            <td style="text-align:center;">
                                                @if(!$hasDate)
                                                    <span class="status-badge status-empty">Belgilanmagan</span>
                                                @elseif(($oskiDate && $oskiToday) || ($testDate && $testToday))
                                                    <span class="status-badge status-today">Bugun</span>
                                                @elseif(($oskiDate && $oskiSoon) || ($testDate && $testSoon))
                                                    <span class="status-badge status-soon">Yaqinda</span>
                                                @elseif(($oskiDate && !$oskiPassed) || ($testDate && !$testPassed))
                                                    <span class="status-badge status-pending">Kutilmoqda</span>
                                                @else
                                                    <span class="status-badge status-passed">O'tgan</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                @elseif($selectedDepartment && $selectedSemester)
                    <div style="padding:60px 20px;text-align:center;">
                        <svg style="width:56px;height:56px;margin:0 auto 12px;color:#cbd5e1;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        <p style="color:#64748b;font-size:15px;font-weight:600;">Jadval topilmadi</p>
                        <p style="color:#94a3b8;font-size:13px;margin-top:4px;">Tanlangan filtrlar bo'yicha imtihon sanalari topilmadi.</p>
                    </div>
                @else
                    <div style="padding:60px 20px;text-align:center;">
                        <svg style="width:56px;height:56px;margin:0 auto 12px;color:#cbd5e1;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path></svg>
                        <p style="color:#64748b;font-size:15px;font-weight:600;">Filtrlang</p>
                        <p style="color:#94a3b8;font-size:13px;margin-top:4px;">Fakultet va semestrni tanlab, yakuniy nazoratlar jadvalini ko'ring.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
        function stripSpecialChars(s) { return s.replace(/[\/\(\),\-\.\s]/g, '').toLowerCase(); }
        function fuzzyMatcher(params, data) {
            if ($.trim(params.term) === '') return data;
            if (typeof data.text === 'undefined') return null;
            if (stripSpecialChars(data.text).indexOf(stripSpecialChars(params.term)) > -1) return $.extend({}, data, true);
            if (data.text.toLowerCase().indexOf(params.term.toLowerCase()) > -1) return $.extend({}, data, true);
            return null;
        }

        function rd(el) { $(el).empty().append('<option value="">Barchasi</option>').trigger('change'); }

        function applyFilter() {
            var url = new URL(window.location.href.split('?')[0]);
            var dept = $('#department_id').val();
            var spec = $('#specialty_id').val();
            var sem = $('#semester_code').val();
            var grp = $('#group_id').val();
            var status = $('#status').val();
            if (dept) url.searchParams.set('department_id', dept);
            if (spec) url.searchParams.set('specialty_id', spec);
            if (sem) url.searchParams.set('semester_code', sem);
            if (grp) url.searchParams.set('group_id', grp);
            if (status) url.searchParams.set('status', status);
            window.location.href = url.toString();
        }

        $(document).ready(function() {
            // Select2 init
            $('.select2').each(function() {
                $(this).select2({ theme: 'classic', width: '100%', allowClear: true, placeholder: $(this).find('option:first').text(), matcher: fuzzyMatcher })
                .on('select2:open', function() { setTimeout(function() { var s = document.querySelector('.select2-container--open .select2-search__field'); if(s) s.focus(); }, 10); });
            });

            // Cascading dropdowns
            $('#department_id').on('change', function() {
                var deptId = $(this).val();
                rd('#specialty_id');
                $('#semester_code').empty().append('<option value="">Tanlang</option>').trigger('change');
                rd('#group_id');
                if (!deptId) return;

                $.get('{{ route($routePrefix . ".academic-schedule.get-specialties") }}', {department_id: deptId}, function(data) {
                    $.each(data, function(i, item) {
                        $('#specialty_id').append('<option value="' + item.specialty_hemis_id + '">' + item.name + '</option>');
                    });
                });

                $.get('{{ route($routePrefix . ".academic-schedule.get-semesters") }}', {department_id: deptId}, function(data) {
                    $.each(data, function(i, item) {
                        $('#semester_code').append('<option value="' + item.code + '">' + item.name + '</option>');
                    });
                });

                $.get('{{ route($routePrefix . ".academic-schedule.get-groups") }}', {department_id: deptId}, function(data) {
                    $.each(data, function(i, item) {
                        $('#group_id').append('<option value="' + item.group_hemis_id + '">' + item.name + '</option>');
                    });
                });
            });

            $('#specialty_id').on('change', function() {
                var deptId = $('#department_id').val();
                var specId = $(this).val();
                $('#semester_code').empty().append('<option value="">Tanlang</option>').trigger('change');
                rd('#group_id');
                if (!deptId) return;

                var params = {department_id: deptId};
                if (specId) params.specialty_id = specId;

                $.get('{{ route($routePrefix . ".academic-schedule.get-semesters") }}', params, function(data) {
                    $.each(data, function(i, item) {
                        $('#semester_code').append('<option value="' + item.code + '">' + item.name + '</option>');
                    });
                });

                $.get('{{ route($routePrefix . ".academic-schedule.get-groups") }}', params, function(data) {
                    $.each(data, function(i, item) {
                        $('#group_id').append('<option value="' + item.group_hemis_id + '">' + item.name + '</option>');
                    });
                });
            });
        });
    </script>

    <style>
        /* Filter styles (dars belgilash hisoboti uslubida) */
        .filter-container { padding: 16px 20px 12px; background: linear-gradient(135deg, #f0f4f8, #e8edf5); border-bottom: 2px solid #dbe4ef; overflow: visible; position: relative; z-index: 20; }
        .filter-row { display: flex; gap: 10px; flex-wrap: nowrap; margin-bottom: 10px; align-items: flex-end; overflow: visible; }
        .filter-row:last-child { margin-bottom: 0; }
        .filter-label { display: flex; align-items: center; gap: 5px; margin-bottom: 4px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: #475569; }
        .fl-dot { width: 7px; height: 7px; border-radius: 50%; display: inline-block; flex-shrink: 0; }
        .filter-item { flex: 0 0 auto; }

        /* Select2 theme */
        .select2-container--classic .select2-selection--single { height: 36px; border: 1px solid #cbd5e1; border-radius: 8px; background: #fff; transition: all 0.2s; box-shadow: 0 1px 2px rgba(0,0,0,0.04); }
        .select2-container--classic .select2-selection--single:hover { border-color: #2b5ea7; box-shadow: 0 0 0 2px rgba(43,94,167,0.1); }
        .select2-container--classic .select2-selection--single .select2-selection__rendered { line-height: 34px; padding-left: 10px; padding-right: 52px; color: #1e293b; font-size: 0.8rem; font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .select2-container--classic .select2-selection--single .select2-selection__arrow { height: 34px; width: 22px; background: transparent; border-left: none; right: 0; }
        .select2-container--classic .select2-selection--single .select2-selection__clear { position: absolute; right: 22px; top: 50%; transform: translateY(-50%); font-size: 16px; font-weight: bold; color: #94a3b8; cursor: pointer; padding: 2px 6px; z-index: 2; background: #fff; border-radius: 50%; line-height: 1; transition: all 0.15s; }
        .select2-container--classic .select2-selection--single .select2-selection__clear:hover { color: #fff; background: #ef4444; }
        .select2-dropdown { font-size: 0.8rem; border-radius: 8px; border: 1px solid #cbd5e1; box-shadow: 0 8px 24px rgba(0,0,0,0.12); }
        .select2-container--classic .select2-results__option--highlighted { background-color: #2b5ea7; }

        /* Buttons */
        .btn-calc { display: inline-flex; align-items: center; gap: 8px; padding: 8px 20px; background: linear-gradient(135deg, #2b5ea7, #3b7ddb); color: #fff; border: none; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 8px rgba(43,94,167,0.3); height: 36px; }
        .btn-calc:hover { background: linear-gradient(135deg, #1e4b8a, #2b5ea7); box-shadow: 0 4px 12px rgba(43,94,167,0.4); transform: translateY(-1px); }

        /* Table */
        .schedule-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 13px; }
        .schedule-table thead { position: sticky; top: 0; z-index: 10; }
        .schedule-table thead tr { background: linear-gradient(135deg, #e8edf5, #dbe4ef, #d1d9e6); }
        .schedule-table th { padding: 14px 12px; text-align: left; font-weight: 600; font-size: 11.5px; color: #334155; text-transform: uppercase; letter-spacing: 0.05em; white-space: nowrap; border-bottom: 2px solid #cbd5e1; }
        .group-header-row td { padding: 8px 16px; font-size: 13px; font-weight: 700; color: #1e3a5f; background: linear-gradient(135deg, #eff6ff, #dbeafe); border-bottom: 1px solid #bfdbfe; }
        .data-row td { padding: 8px 12px; border-bottom: 1px solid #f1f5f9; font-size: 13px; }
        .data-row:hover td { background: #f8fafc; }

        /* Date badges */
        .date-badge { display: inline-flex; padding: 4px 10px; font-size: 12px; font-weight: 600; border-radius: 6px; line-height: 1.3; }
        .badge-pending { background: #dcfce7; color: #166534; }
        .badge-pending-blue { background: #dbeafe; color: #1e40af; }
        .badge-today { background: #fef9c3; color: #854d0e; }
        .badge-soon { background: #ffedd5; color: #9a3412; }
        .badge-passed { background: #f1f5f9; color: #64748b; }

        /* Status badges */
        .status-badge { display: inline-flex; padding: 4px 10px; font-size: 11px; font-weight: 700; border-radius: 6px; line-height: 1.3; text-transform: uppercase; letter-spacing: 0.03em; }
        .status-pending { background: #dcfce7; color: #166534; }
        .status-today { background: #fef9c3; color: #854d0e; }
        .status-soon { background: #ffedd5; color: #9a3412; }
        .status-passed { background: #f1f5f9; color: #64748b; }
        .status-empty { background: #fef2f2; color: #dc2626; }
    </style>
</x-app-layout>
