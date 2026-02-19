<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                YN kunini belgilash
            </h2>
        </div>
    </x-slot>

    <div class="py-4">
        <div class="max-w-full mx-auto sm:px-4 lg:px-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100">

                @if(session('success'))
                    <div class="px-5 py-3 text-green-700 bg-green-50 border-b border-green-200" role="alert">
                        <strong class="font-bold">Muvaffaqiyat!</strong>
                        <span>{{ session('success') }}</span>
                    </div>
                @endif

                @if(session('error'))
                    <div class="px-5 py-3 text-red-700 bg-red-50 border-b border-red-200" role="alert">
                        <strong class="font-bold">Xato!</strong>
                        <span>{{ session('error') }}</span>
                    </div>
                @endif

                <!-- Filters -->
                <div class="filter-container">
                    <!-- Row 1 -->
                    <div class="filter-row">
                        <div class="filter-item" style="min-width: 140px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#3b82f6;"></span> Ta'lim turi</label>
                            <select id="education_type" class="select2" style="width: 100%;">
                                <option value="">Barchasi</option>
                                @foreach($educationTypes as $type)
                                    <option value="{{ $type->education_type_code }}" {{ ($selectedEducationType ?? '') == $type->education_type_code ? 'selected' : '' }}>{{ $type->education_type_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="filter-item" style="flex: 1; min-width: 170px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#10b981;"></span> Fakultet</label>
                            <select id="department_id" class="select2" style="width: 100%;">
                                <option value="">Barchasi</option>
                                @foreach($departments as $dept)
                                    <option value="{{ $dept->department_hemis_id }}" {{ ($selectedDepartment ?? '') == $dept->department_hemis_id ? 'selected' : '' }}>{{ $dept->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="filter-item" style="flex: 1; min-width: 180px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#06b6d4;"></span> Yo'nalish</label>
                            <select id="specialty_id" class="select2" style="width: 100%;">
                                <option value="">Barchasi</option>
                                @foreach($specialties as $sp)
                                    <option value="{{ $sp->specialty_hemis_id }}" {{ ($selectedSpecialty ?? '') == $sp->specialty_hemis_id ? 'selected' : '' }}>{{ $sp->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="filter-item" style="min-width: 150px;">
                            <label class="filter-label">&nbsp;</label>
                            <div class="toggle-switch {{ ($currentSemesterToggle ?? '1') === '1' ? 'active' : '' }}" id="current-semester-toggle" onclick="toggleSemester()">
                                <div class="toggle-track"><div class="toggle-thumb"></div></div>
                                <span class="toggle-label">Joriy semestr</span>
                            </div>
                        </div>
                    </div>
                    <!-- Row 2 -->
                    <div class="filter-row">
                        <div class="filter-item" style="min-width: 110px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#8b5cf6;"></span> Kurs</label>
                            <select id="level_code" class="select2" style="width: 100%;"><option value="">Barchasi</option></select>
                        </div>
                        <div class="filter-item" style="min-width: 120px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#14b8a6;"></span> Semestr</label>
                            <select id="semester_code" class="select2" style="width: 100%;">
                                <option value="">Barchasi</option>
                                @foreach($semesters as $sem)
                                    <option value="{{ $sem->code }}" {{ ($selectedSemester ?? '') == $sem->code ? 'selected' : '' }}>{{ $sem->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="filter-item" style="min-width: 140px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#1a3268;"></span> Guruh</label>
                            <select id="group_id" class="select2" style="width: 100%;">
                                <option value="">Barchasi</option>
                                @foreach($groups as $gr)
                                    <option value="{{ $gr->group_hemis_id }}" {{ ($selectedGroup ?? '') == $gr->group_hemis_id ? 'selected' : '' }}>{{ $gr->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="filter-item" style="flex: 1; min-width: 200px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#0f172a;"></span> Fan</label>
                            <select id="subject_id" class="select2" style="width: 100%;"><option value="">Barchasi</option></select>
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
                <form method="POST" action="{{ route($routePrefix . '.academic-schedule.store') }}">
                    @csrf
                    <div style="padding:10px 20px;background:#f0fdf4;border-bottom:1px solid #bbf7d0;display:flex;align-items:center;justify-content:space-between;">
                        <div style="display:flex;align-items:center;gap:12px;">
                            <span style="background:#16a34a;color:#fff;padding:6px 14px;font-size:13px;border-radius:8px;font-weight:700;">
                                Imtihon sanalari
                                @if($currentEducationYear)
                                    ({{ $currentEducationYear }})
                                @endif
                            </span>
                        </div>
                        <button type="submit" class="btn-save">
                            <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            Saqlash
                        </button>
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
                                    <th style="width:170px;text-align:center;">OSKI sanasi</th>
                                    <th style="width:170px;text-align:center;">Test sanasi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php $rowIndex = 0; @endphp
                                @foreach($scheduleData as $groupHemisId => $items)
                                    <tr class="group-header-row">
                                        <td colspan="7">
                                            {{ $items->first()['group']->name }}
                                            <span style="margin-left:8px;font-size:11px;font-weight:400;color:#3b82f6;">
                                                ({{ $items->first()['specialty_name'] }})
                                            </span>
                                        </td>
                                    </tr>
                                    @foreach($items as $item)
                                        <tr class="data-row">
                                            <td style="color:#94a3b8;font-weight:500;padding-left:16px;">{{ ++$rowIndex }}</td>
                                            <td style="font-weight:600;color:#0f172a;">{{ $item['group']->name }}</td>
                                            <td style="color:#64748b;font-size:12px;">{{ $item['specialty_name'] }}</td>
                                            <td style="font-weight:500;color:#1e293b;">{{ $item['subject']->subject_name }}</td>
                                            <td style="text-align:center;color:#64748b;">{{ $item['subject']->credit }}</td>
                                            <td style="text-align:center;padding:4px 8px;">
                                                <input type="text" id="oski_{{ $rowIndex }}"
                                                       name="schedules[{{ $rowIndex }}][oski_date]"
                                                       data-initial="{{ $item['oski_date'] }}"
                                                       class="date-input sc-date" autocomplete="off" />
                                            </td>
                                            <td style="text-align:center;padding:4px 8px;">
                                                <input type="text" id="test_{{ $rowIndex }}"
                                                       name="schedules[{{ $rowIndex }}][test_date]"
                                                       data-initial="{{ $item['test_date'] }}"
                                                       class="date-input sc-date" autocomplete="off" />
                                            </td>
                                            <input type="hidden" name="schedules[{{ $rowIndex }}][group_hemis_id]" value="{{ $item['group']->group_hemis_id }}">
                                            <input type="hidden" name="schedules[{{ $rowIndex }}][subject_id]" value="{{ $item['subject']->subject_id }}">
                                            <input type="hidden" name="schedules[{{ $rowIndex }}][subject_name]" value="{{ $item['subject']->subject_name }}">
                                            <input type="hidden" name="schedules[{{ $rowIndex }}][department_hemis_id]" value="{{ $item['group']->department_hemis_id }}">
                                            <input type="hidden" name="schedules[{{ $rowIndex }}][specialty_hemis_id]" value="{{ $item['group']->specialty_hemis_id }}">
                                            <input type="hidden" name="schedules[{{ $rowIndex }}][curriculum_hemis_id]" value="{{ $item['group']->curriculum_hemis_id }}">
                                            <input type="hidden" name="schedules[{{ $rowIndex }}][semester_code]" value="{{ $item['subject']->semester_code }}">
                                        </tr>
                                    @endforeach
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div style="padding:12px 20px;border-top:1px solid #e2e8f0;background:#f8fafc;display:flex;justify-content:flex-end;">
                        <button type="submit" class="btn-save">
                            <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            Saqlash
                        </button>
                    </div>
                </form>
                @elseif($isSearched)
                    <div style="padding:60px 20px;text-align:center;">
                        <svg style="width:56px;height:56px;margin:0 auto 12px;color:#cbd5e1;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        <p style="color:#64748b;font-size:15px;font-weight:600;">Ma'lumot topilmadi</p>
                        <p style="color:#94a3b8;font-size:13px;margin-top:4px;">Tanlangan filtrlar bo'yicha fanlar topilmadi.</p>
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
        function stripSpecialChars(s) { return s.replace(/[\/\(\),\-\.\s]/g, '').toLowerCase(); }
        function fuzzyMatcher(params, data) {
            if ($.trim(params.term) === '') return data;
            if (typeof data.text === 'undefined') return null;
            if (stripSpecialChars(data.text).indexOf(stripSpecialChars(params.term)) > -1) return $.extend({}, data, true);
            if (data.text.toLowerCase().indexOf(params.term.toLowerCase()) > -1) return $.extend({}, data, true);
            return null;
        }

        function rd(el, ph) { $(el).empty().append('<option value="">' + (ph || 'Barchasi') + '</option>').trigger('change'); }

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
            if (et) url.searchParams.set('education_type', et);
            if (dept) url.searchParams.set('department_id', dept);
            if (spec) url.searchParams.set('specialty_id', spec);
            if (lc) url.searchParams.set('level_code', lc);
            if (sem) url.searchParams.set('semester_code', sem);
            if (grp) url.searchParams.set('group_id', grp);
            if (subj) url.searchParams.set('subject_id', subj);
            if (status) url.searchParams.set('status', status);
            url.searchParams.set('current_semester', cs);
            window.location.href = url.toString();
        }

        function loadLevels(cb) {
            rd('#level_code');
            $.get('{{ route($routePrefix . ".academic-schedule.get-level-codes") }}', fp(), function(d) {
                $.each(d, function(k, v) { $('#level_code').append('<option value="' + k + '">' + v + '</option>'); });
                @if($selectedLevelCode)
                    $('#level_code').val('{{ $selectedLevelCode }}').trigger('change');
                @endif
                if (cb) cb();
            });
        }

        function loadSemesters() {
            rd('#semester_code');
            var params = fp();
            if (!params.department_id && !params.level_code) return;
            $.get('{{ route($routePrefix . ".academic-schedule.get-semesters") }}', params, function(d) {
                $.each(d, function(i, item) { $('#semester_code').append('<option value="' + item.code + '">' + item.name + '</option>'); });
                @if($selectedSemester)
                    $('#semester_code').val('{{ $selectedSemester }}').trigger('change');
                @endif
            });
        }

        function loadSubjects() {
            rd('#subject_id');
            $.get('{{ route($routePrefix . ".academic-schedule.get-subjects") }}', fp(), function(d) {
                $.each(d, function(k, v) { $('#subject_id').append('<option value="' + k + '">' + v + '</option>'); });
                @if($selectedSubject)
                    $('#subject_id').val('{{ $selectedSubject }}').trigger('change');
                @endif
            });
        }

        function loadGroups() {
            rd('#group_id');
            var params = fp();
            if (!params.department_id) return;
            $.get('{{ route($routePrefix . ".academic-schedule.get-groups") }}', params, function(d) {
                $.each(d, function(i, item) { $('#group_id').append('<option value="' + item.group_hemis_id + '">' + item.name + '</option>'); });
                @if($selectedGroup)
                    $('#group_id').val('{{ $selectedGroup }}').trigger('change');
                @endif
            });
        }

        function loadSpecialties() {
            rd('#specialty_id');
            var deptId = $('#department_id').val();
            if (!deptId) return;
            $.get('{{ route($routePrefix . ".academic-schedule.get-specialties") }}', {department_id: deptId}, function(d) {
                $.each(d, function(i, item) { $('#specialty_id').append('<option value="' + item.specialty_hemis_id + '">' + item.name + '</option>'); });
                @if($selectedSpecialty)
                    $('#specialty_id').val('{{ $selectedSpecialty }}').trigger('change');
                @endif
            });
        }

        $(document).ready(function() {
            // Select2 init
            $('.select2').each(function() {
                $(this).select2({ theme: 'classic', width: '100%', allowClear: true, placeholder: $(this).find('option:first').text(), matcher: fuzzyMatcher })
                .on('select2:open', function() { setTimeout(function() { var s = document.querySelector('.select2-container--open .select2-search__field'); if(s) s.focus(); }, 10); });
            });

            // Cascading
            $('#education_type').on('change', function() { loadLevels(); loadSemesters(); loadSubjects(); loadGroups(); });
            $('#department_id').on('change', function() { loadSpecialties(); loadSemesters(); loadSubjects(); loadGroups(); });
            $('#specialty_id').on('change', function() { loadSemesters(); loadSubjects(); loadGroups(); });
            $('#level_code').on('change', function() { loadSemesters(); loadSubjects(); loadGroups(); });
            $('#semester_code').on('change', function() { loadSubjects(); loadGroups(); });

            // Init level codes
            loadLevels(function() { loadSubjects(); });

            // Init scroll calendars
            $('[id^="oski_"], [id^="test_"]').each(function() {
                var cal = new ScrollCalendar(this.id);
                var val = $(this).attr('data-initial');
                if (val) cal.setValue(val);
            });
        });
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

        .btn-calc { display: inline-flex; align-items: center; gap: 8px; padding: 8px 20px; background: linear-gradient(135deg, #2b5ea7, #3b7ddb); color: #fff; border: none; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 8px rgba(43,94,167,0.3); height: 36px; }
        .btn-calc:hover { background: linear-gradient(135deg, #1e4b8a, #2b5ea7); box-shadow: 0 4px 12px rgba(43,94,167,0.4); transform: translateY(-1px); }
        .btn-save { display: inline-flex; align-items: center; gap: 8px; padding: 8px 20px; background: linear-gradient(135deg, #16a34a, #22c55e); color: #fff; border: none; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 8px rgba(22,163,74,0.3); height: 36px; }
        .btn-save:hover { background: linear-gradient(135deg, #15803d, #16a34a); box-shadow: 0 4px 12px rgba(22,163,74,0.4); transform: translateY(-1px); }

        .schedule-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 13px; }
        .schedule-table thead { position: sticky; top: 0; z-index: 10; }
        .schedule-table thead tr { background: linear-gradient(135deg, #e8edf5, #dbe4ef, #d1d9e6); }
        .schedule-table th { padding: 14px 12px; text-align: left; font-weight: 600; font-size: 11.5px; color: #334155; text-transform: uppercase; letter-spacing: 0.05em; white-space: nowrap; border-bottom: 2px solid #cbd5e1; }
        .group-header-row td { padding: 8px 16px; font-size: 13px; font-weight: 700; color: #1e3a5f; background: linear-gradient(135deg, #eff6ff, #dbeafe); border-bottom: 1px solid #bfdbfe; }
        .data-row td { padding: 8px 12px; border-bottom: 1px solid #f1f5f9; font-size: 13px; }
        .data-row:hover td { background: #f8fafc; }
        .data-row .sc-wrap { min-width: 140px; }
        .data-row .sc-dropdown { z-index: 9999; }
    </style>
</x-app-layout>
