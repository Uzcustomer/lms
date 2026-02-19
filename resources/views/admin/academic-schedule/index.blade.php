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
                            <label class="filter-label"><span class="fl-dot" style="background:#f59e0b;"></span> Dars tugash (dan)</label>
                            <input type="text" id="date_from" class="date-input sc-date" autocomplete="off" placeholder="dd.mm.yyyy" />
                        </div>
                        <div class="filter-item" style="min-width: 145px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#f59e0b;"></span> Dars tugash (gacha)</label>
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
                                    <th class="sortable" data-col="1">Guruh <span class="sort-icon"></span></th>
                                    <th class="sortable" data-col="2">Yo'nalish <span class="sort-icon"></span></th>
                                    <th class="sortable" data-col="3">Fan nomi <span class="sort-icon"></span></th>
                                    <th class="sortable" data-col="4" style="width:70px;text-align:center;">Kredit <span class="sort-icon"></span></th>
                                    <th class="sortable" data-col="5" style="width:160px;text-align:center;">Dars boshlanish <span class="sort-icon"></span></th>
                                    <th class="sortable" data-col="6" style="width:160px;text-align:center;">Dars tugash <span class="sort-icon"></span></th>
                                    <th style="width:190px;text-align:center;">OSKI sanasi</th>
                                    <th style="width:190px;text-align:center;">Test sanasi</th>
                                </tr>
                            </thead>
                            <tbody id="schedule-tbody">
                                @php $rowIndex = 0; @endphp
                                @foreach($scheduleData as $groupHemisId => $items)
                                    @foreach($items as $item)
                                        <tr class="data-row">
                                            <td class="row-num" style="color:#94a3b8;font-weight:500;padding-left:16px;">{{ ++$rowIndex }}</td>
                                            <td data-sort-value="{{ $item['group']->name }}" style="font-weight:600;color:#0f172a;">{{ $item['group']->name }}</td>
                                            <td data-sort-value="{{ $item['specialty_name'] }}" style="color:#64748b;font-size:12px;">{{ $item['specialty_name'] }}</td>
                                            <td data-sort-value="{{ $item['subject']->subject_name }}" style="font-weight:500;color:#1e293b;">{{ $item['subject']->subject_name }}</td>
                                            <td data-sort-value="{{ $item['subject']->credit }}" style="text-align:center;color:#64748b;">{{ $item['subject']->credit }}</td>
                                            <td data-sort-value="{{ $item['lesson_start_date'] ?? '' }}" style="text-align:center;padding:4px 8px;">
                                                @if($item['lesson_start_date'])
                                                    <span class="lesson-date-badge">{{ \Carbon\Carbon::parse($item['lesson_start_date'])->format('d.m.Y') }}</span>
                                                @else
                                                    <span style="color:#cbd5e1;">—</span>
                                                @endif
                                            </td>
                                            <td data-sort-value="{{ $item['lesson_end_date'] ?? '' }}" style="text-align:center;padding:4px 8px;">
                                                @if($item['lesson_end_date'])
                                                    <span class="lesson-date-badge">{{ \Carbon\Carbon::parse($item['lesson_end_date'])->format('d.m.Y') }}</span>
                                                @else
                                                    <span style="color:#cbd5e1;">—</span>
                                                @endif
                                            </td>
                                            <td style="text-align:center;padding:4px 8px;">
                                                <div class="exam-cell">
                                                    <div class="exam-date-wrap" id="oski_wrap_{{ $rowIndex }}" style="{{ $item['oski_na'] ? 'display:none;' : '' }}">
                                                        <input type="text" class="date-input-masked" placeholder="kk.oo.yyyy"
                                                               value="{{ $item['oski_date'] ? \Carbon\Carbon::parse($item['oski_date'])->format('d.m.Y') : '' }}"
                                                               data-hidden="oski_h_{{ $rowIndex }}"
                                                               maxlength="10" autocomplete="off" />
                                                        <input type="hidden" name="schedules[{{ $rowIndex }}][oski_date]" id="oski_h_{{ $rowIndex }}" value="{{ $item['oski_date'] }}" />
                                                    </div>
                                                    <label class="na-toggle" title="Bu fan uchun OSKI yo'q">
                                                        <input type="checkbox" name="schedules[{{ $rowIndex }}][oski_na]" value="1"
                                                               {{ $item['oski_na'] ? 'checked' : '' }}
                                                               onchange="toggleNa(this, 'oski_wrap_{{ $rowIndex }}')">
                                                        <span class="na-label">N/A</span>
                                                    </label>
                                                </div>
                                            </td>
                                            <td style="text-align:center;padding:4px 8px;">
                                                <div class="exam-cell">
                                                    <div class="exam-date-wrap" id="test_wrap_{{ $rowIndex }}" style="{{ $item['test_na'] ? 'display:none;' : '' }}">
                                                        <input type="text" class="date-input-masked" placeholder="kk.oo.yyyy"
                                                               value="{{ $item['test_date'] ? \Carbon\Carbon::parse($item['test_date'])->format('d.m.Y') : '' }}"
                                                               data-hidden="test_h_{{ $rowIndex }}"
                                                               maxlength="10" autocomplete="off" />
                                                        <input type="hidden" name="schedules[{{ $rowIndex }}][test_date]" id="test_h_{{ $rowIndex }}" value="{{ $item['test_date'] }}" />
                                                    </div>
                                                    <label class="na-toggle" title="Bu fan uchun Test yo'q">
                                                        <input type="checkbox" name="schedules[{{ $rowIndex }}][test_na]" value="1"
                                                               {{ $item['test_na'] ? 'checked' : '' }}
                                                               onchange="toggleNa(this, 'test_wrap_{{ $rowIndex }}')">
                                                        <span class="na-label">N/A</span>
                                                    </label>
                                                </div>
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
                            <tfoot>
                                <tr><td colspan="9" style="padding:8px 16px;font-size:12px;color:#94a3b8;text-align:right;">Jami: {{ $scheduleData->flatten(1)->count() }} ta fan</td></tr>
                            </tfoot>
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
        var isUpdatingFilters = false;
        var filterUrl = '{{ route($routePrefix . ".academic-schedule.get-filter-options") }}';

        // Initial values from server (sahifa qayta yuklanganda)
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

        function toggleNa(checkbox, wrapId) {
            var wrap = document.getElementById(wrapId);
            if (checkbox.checked) {
                wrap.style.display = 'none';
                var txt = wrap.querySelector('.date-input-masked');
                var hid = wrap.querySelector('input[type="hidden"]');
                if (txt) { txt.value = ''; txt.classList.remove('date-error'); }
                if (hid) hid.value = '';
            } else {
                wrap.style.display = '';
            }
        }

        // dd.mm.yyyy mask va validatsiya
        function initDateMask() {
            document.querySelectorAll('.date-input-masked').forEach(function(inp) {
                // Faqat raqam va nuqta qo'yish
                inp.addEventListener('input', function(e) {
                    var v = this.value.replace(/[^\d]/g, '');
                    if (v.length > 8) v = v.substring(0, 8);
                    var parts = [];
                    if (v.length > 0) parts.push(v.substring(0, Math.min(2, v.length)));
                    if (v.length > 2) parts.push(v.substring(2, Math.min(4, v.length)));
                    if (v.length > 4) parts.push(v.substring(4, 8));
                    this.value = parts.join('.');
                    syncHidden(this);
                });

                // Blur da tekshirish
                inp.addEventListener('blur', function() {
                    validateDateInput(this);
                });
            });
        }

        function validateDateInput(inp) {
            var v = inp.value.trim();
            if (!v) {
                inp.classList.remove('date-error');
                inp.title = '';
                syncHidden(inp);
                return true;
            }
            var m = v.match(/^(\d{2})\.(\d{2})\.(\d{4})$/);
            if (!m) {
                inp.classList.add('date-error');
                inp.title = 'Format: kk.oo.yyyy';
                clearHidden(inp);
                return false;
            }
            var day = parseInt(m[1], 10);
            var month = parseInt(m[2], 10);
            var year = parseInt(m[3], 10);
            var err = '';
            if (month < 1 || month > 12) err = 'Oy 01-12 orasida bo\'lishi kerak';
            else if (day < 1 || day > 31) err = 'Kun 01-31 orasida bo\'lishi kerak';
            else {
                // Oydagi kunlar sonini tekshirish
                var maxDay = new Date(year, month, 0).getDate();
                if (day > maxDay) err = month + '-oyda ' + maxDay + ' kun bor';
            }
            if (year < 2020 || year > 2040) err = 'Yil 2020-2040 orasida bo\'lishi kerak';
            if (err) {
                inp.classList.add('date-error');
                inp.title = err;
                clearHidden(inp);
                return false;
            }
            inp.classList.remove('date-error');
            inp.title = '';
            syncHidden(inp);
            return true;
        }

        function syncHidden(inp) {
            var hidId = inp.getAttribute('data-hidden');
            if (!hidId) return;
            var hid = document.getElementById(hidId);
            if (!hid) return;
            var v = inp.value.trim();
            var m = v.match(/^(\d{2})\.(\d{2})\.(\d{4})$/);
            hid.value = m ? (m[3] + '-' + m[2] + '-' + m[1]) : '';
        }

        function clearHidden(inp) {
            var hidId = inp.getAttribute('data-hidden');
            if (!hidId) return;
            var hid = document.getElementById(hidId);
            if (hid) hid.value = '';
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
                    // Icon yangilash
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
                // Raqam tekshirish (kredit)
                var aNum = parseFloat(aVal);
                var bNum = parseFloat(bVal);
                if (!isNaN(aNum) && !isNaN(bNum)) {
                    return dir === 'asc' ? aNum - bNum : bNum - aNum;
                }
                // Matn tartiblash
                var cmp = aVal.localeCompare(bVal, 'uz');
                return dir === 'asc' ? cmp : -cmp;
            });
            // Qayta joylashtirish va raqamlarni yangilash
            rows.forEach(function(row, i) {
                tbody.appendChild(row);
                var numCell = row.querySelector('.row-num');
                if (numCell) numCell.textContent = i + 1;
            });
        }

        // Dropdown parametrlarini yig'ish
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

        // Dropdown ni yangilash (tanlangan qiymatni saqlab)
        function updateSelect(selector, items, valueKey, textKey) {
            var $el = $(selector);
            var currentVal = $el.val();
            $el.empty().append('<option value="">Barchasi</option>');
            $.each(items, function(i, item) {
                $el.append('<option value="' + item[valueKey] + '">' + item[textKey] + '</option>');
            });
            // Agar avvalgi qiymat hali ham mavjud bo'lsa - tiklash
            if (currentVal && $el.find('option[value="' + currentVal + '"]').length) {
                $el.val(currentVal);
            }
            $el.trigger('change.select2');
        }

        // Bidirectional filter: barcha dropdownlarni yangilash
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

        // Boshlang'ich yuklash (query params bor bo'lsa qiymatlarni tiklash)
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

                // Initial qiymatlarni tiklash
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
            // Select2 init
            $('.select2').each(function() {
                $(this).select2({ theme: 'classic', width: '100%', allowClear: true, placeholder: $(this).find('option:first').text(), matcher: fuzzyMatcher })
                .on('select2:open', function() { setTimeout(function() { var s = document.querySelector('.select2-container--open .select2-search__field'); if(s) s.focus(); }, 10); });
            });

            // Bidirectional: har qanday asosiy filtr o'zgarganda barcha filtrlarni yangilash
            $('#education_type, #department_id, #specialty_id, #level_code, #semester_code').on('change', function() {
                if (!isUpdatingFilters) loadAllFilters();
            });

            // Init
            initFilters();

            // Scroll calendar for date filters
            var calFrom = new ScrollCalendar('date_from');
            var calTo = new ScrollCalendar('date_to');
            @if(request()->get('date_from'))
                calFrom.setValue('{{ request()->get("date_from") }}');
            @endif
            @if(request()->get('date_to'))
                calTo.setValue('{{ request()->get("date_to") }}');
            @endif

            // dd.mm.yyyy mask ishga tushirish
            initDateMask();

            // Sort funksiyasi
            initTableSort();

            // Form submit da validatsiya
            $('form').on('submit', function(e) {
                var hasError = false;
                document.querySelectorAll('.date-input-masked').forEach(function(inp) {
                    if (!validateDateInput(inp)) hasError = true;
                });
                if (hasError) {
                    e.preventDefault();
                    alert('Sana formatida xatolik bor. Iltimos, tekshiring (kk.oo.yyyy).');
                }
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
        .schedule-table th.sortable { cursor: pointer; user-select: none; transition: background 0.15s; }
        .schedule-table th.sortable:hover { background: rgba(43,94,167,0.1); }
        .sort-icon { font-size: 10px; color: #2b5ea7; }
        .data-row td { padding: 8px 12px; border-bottom: 1px solid #f1f5f9; font-size: 13px; }
        .data-row:hover td { background: #f8fafc; }
        .data-row .sc-wrap { min-width: 140px; }
        .data-row .sc-dropdown { z-index: 9999; }

        .lesson-date-badge { display: inline-flex; padding: 4px 8px; font-size: 12px; font-weight: 600; border-radius: 6px; line-height: 1.3; background: #f0f9ff; color: #0369a1; }
        .date-input-masked { height: 32px; border: 1px solid #cbd5e1; border-radius: 6px; padding: 0 8px; font-size: 13px; font-weight: 500; color: #1e293b; background: #fff; width: 100%; min-width: 110px; outline: none; transition: border-color 0.2s; text-align: center; letter-spacing: 0.5px; }
        .date-input-masked:hover { border-color: #2b5ea7; }
        .date-input-masked:focus { border-color: #2b5ea7; box-shadow: 0 0 0 2px rgba(43,94,167,0.15); }
        .date-input-masked::placeholder { color: #94a3b8; font-weight: 400; letter-spacing: 0; }
        .date-input-masked.date-error { border-color: #ef4444; background: #fef2f2; }
        .date-input-masked.date-error:focus { box-shadow: 0 0 0 2px rgba(239,68,68,0.2); }
        .exam-cell { display: flex; align-items: center; gap: 6px; justify-content: center; }
        .exam-date-wrap { flex: 1; min-width: 0; }
        .na-toggle { display: inline-flex; align-items: center; gap: 3px; cursor: pointer; white-space: nowrap; flex-shrink: 0; }
        .na-toggle input[type="checkbox"] { width: 14px; height: 14px; accent-color: #ef4444; cursor: pointer; margin: 0; }
        .na-label { font-size: 10px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.03em; }
        .na-toggle input:checked + .na-label { color: #ef4444; }
    </style>
</x-app-layout>
