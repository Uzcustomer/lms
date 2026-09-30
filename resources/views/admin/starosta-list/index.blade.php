<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            {{ __("Starostalar ro'yxati") }}
        </h2>
    </x-slot>

    <div class="py-4">
        <div class="max-w-full mx-auto sm:px-4 lg:px-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">

                <!-- Filters -->
                <form id="search-form" method="GET" action="{{ route('admin.starostalar.index') }}">
                    <div class="filter-container">
                        <!-- Row 1 -->
                        <div class="filter-row">
                            <div class="filter-item" style="min-width: 170px;">
                                <label class="filter-label"><span class="fl-dot" style="background:#3b82f6;"></span> Ta'lim turi</label>
                                <select id="education_type" name="education_type" class="select2" style="width: 100%;">
                                    <option value="">Barchasi</option>
                                    @foreach($educationTypes as $type)
                                        <option value="{{ $type->education_type_code }}" {{ (string)$selectedEducationType === (string)$type->education_type_code ? 'selected' : '' }}>
                                            {{ $type->education_type_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="filter-item" style="flex: 1; min-width: 200px;">
                                <label class="filter-label"><span class="fl-dot" style="background:#10b981;"></span> Fakultet</label>
                                <select id="department" name="department" class="select2" style="width: 100%;">
                                    <option value="">Barchasi</option>
                                </select>
                            </div>
                            <div class="filter-item" style="flex: 1; min-width: 240px;">
                                <label class="filter-label"><span class="fl-dot" style="background:#06b6d4;"></span> Yo'nalish</label>
                                <select id="specialty" name="specialty" class="select2" style="width: 100%;">
                                    <option value="">Barchasi</option>
                                </select>
                            </div>
                        </div>
                        <!-- Row 2 -->
                        <div class="filter-row">
                            <div class="filter-item" style="min-width: 150px;">
                                <label class="filter-label"><span class="fl-dot" style="background:#8b5cf6;"></span> Kurs</label>
                                <select id="level_code" name="level_code" class="select2" style="width: 100%;">
                                    <option value="">Barchasi</option>
                                </select>
                            </div>
                            <div class="filter-item" style="min-width: 200px;">
                                <label class="filter-label"><span class="fl-dot" style="background:#1a3268;"></span> Guruh</label>
                                <select id="group" name="group" class="select2" style="width: 100%;">
                                    <option value="">Barchasi</option>
                                </select>
                            </div>
                            <div class="filter-item" style="min-width: 120px;">
                                <label class="filter-label">&nbsp;</label>
                                <button type="submit" class="btn-calc">
                                    <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                    Qidirish
                                </button>
                            </div>
                        </div>
                    </div>
                </form>

                <div style="padding:10px 20px;background:#f8fafc;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;justify-content:space-between;gap:12px;">
                    <span class="badge" style="background:linear-gradient(135deg,#2b5ea7,#3b7ddb);color:#fff;padding:6px 14px;font-size:13px;border-radius:8px;">Jami: {{ $students->total() }} ta starosta</span>
                    <a href="{{ route('admin.starostalar.export') }}?{{ http_build_query(request()->query()) }}"
                       style="display:inline-flex;align-items:center;gap:6px;padding:6px 14px;font-size:13px;font-weight:600;color:#fff;background:linear-gradient(135deg,#16a34a,#22c55e);border-radius:8px;text-decoration:none;transition:opacity 0.2s;"
                       onmouseover="this.style.opacity='0.85'" onmouseout="this.style.opacity='1'">
                        <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                        </svg>
                        Excel yuklab olish
                    </a>
                </div>

                <div class="overflow-x-auto">
                    <table class="student-table">
                        <thead>
                        <tr>
                            <th>F.I.Sh</th>
                            <th>HEMIS ID</th>
                            <th>Talaba ID</th>
                            <th>Ta'lim turi</th>
                            <th>Fakultet</th>
                            <th>Yo'nalish</th>
                            <th>Kurs</th>
                            <th>Semestr</th>
                            <th>Guruh</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($students as $student)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.students.show', $student->id) }}" class="student-name-link">
                                        {{ $student->full_name }}
                                    </a>
                                </td>
                                <td style="color:#64748b;">{{ $student->hemis_id }}</td>
                                <td style="color:#64748b;">{{ $student->student_id_number }}</td>
                                <td>
                                    <span class="text-cell">{{ $student->education_type_name }}</span>
                                    <span style="font-size:11px;color:#94a3b8;">{{ $student->education_form_name }}</span>
                                </td>
                                <td><span class="text-cell text-emerald">{{ $student->department_name }}</span></td>
                                <td><span class="text-cell text-cyan" title="{{ $student->specialty_name }}">{{ Str::limit($student->specialty_name, 30) }}</span></td>
                                <td><span class="badge badge-violet">{{ $student->level_name }}</span></td>
                                <td><span class="badge badge-teal">{{ $student->semester_name }}</span></td>
                                <td><span class="badge badge-indigo">{{ $student->group_name }}</span></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" style="text-align:center;padding:36px;color:#94a3b8;">Starosta topilmadi</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                <div style="padding:12px 20px;border-top:1px solid #e2e8f0;background:#f8fafc;">
                    <div class="hidden sm:flex sm:items-center sm:justify-between">
                        <p class="text-sm text-gray-700 leading-5">
                            <span class="font-medium">{{ $students->firstItem() ?? 0 }}</span> —
                            <span class="font-medium">{{ $students->lastItem() ?? 0 }}</span> /
                            <span class="font-medium">{{ $students->total() }}</span>
                        </p>
                        <div>{{ $students->appends(request()->query())->links('pagination::tailwind') }}</div>
                    </div>
                    <div class="sm:hidden">{{ $students->appends(request()->query())->links('pagination::simple-tailwind') }}</div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
        // Cascading filter (talabalar tabidagi bilan bir xil endpointlar)
        var initDone = false;
        var sv = {
            education_type: @json($selectedEducationType),
            department: @json(request('department', '')),
            specialty: @json(request('specialty', '')),
            level_code: @json(request('level_code', '')),
            group: @json(request('group', ''))
        };

        function stripSpecialChars(s) { return s.replace(/[\/\(\),\-\.\s]/g, '').toLowerCase(); }
        function fuzzyMatcher(params, data) {
            if ($.trim(params.term) === '') return data;
            if (typeof data.text === 'undefined') return null;
            if (stripSpecialChars(data.text).indexOf(stripSpecialChars(params.term)) > -1) return $.extend({}, data, true);
            if (data.text.toLowerCase().indexOf(params.term.toLowerCase()) > -1) return $.extend({}, data, true);
            return null;
        }

        function fp() {
            return {
                education_type: $('#education_type').val() || '',
                department: $('#department').val() || '',
                specialty: $('#specialty').val() || '',
                level_code: $('#level_code').val() || ''
            };
        }

        function rd(el) { $(el).empty().append('<option value="">Barchasi</option>'); }
        function pd(url, params, el, selVal, cb) {
            $.get(url, params, function(d) {
                $.each(d, function(k, v) { $(el).append('<option value="' + k + '">' + v + '</option>'); });
                if (selVal) { $(el).val(selVal); }
                $(el).trigger('change');
                if (cb) cb();
            });
        }

        function rDept() { rd('#department'); pd('{{ route("admin.students.filter.departments") }}', fp(), '#department'); }
        function rSpec() { rd('#specialty'); pd('{{ route("admin.students.filter.specialties") }}', fp(), '#specialty'); }
        function rLvl() { rd('#level_code'); pd('{{ route("admin.students.filter.levels") }}', fp(), '#level_code'); }
        function rGrp() { rd('#group'); pd('{{ route("admin.students.filter.groups") }}', fp(), '#group'); }

        $(document).ready(function() {
            $('.select2').each(function() {
                $(this).select2({
                    theme: 'classic', width: '100%', allowClear: true,
                    placeholder: $(this).find('option:first').text(),
                    matcher: fuzzyMatcher
                }).on('select2:open', function() {
                    setTimeout(function() {
                        var s = document.querySelector('.select2-container--open .select2-search__field');
                        if (s) s.focus();
                    }, 10);
                });
            });

            $('#education_type').on('change', function() { if (!initDone) return; rDept(); rSpec(); rLvl(); rGrp(); });
            $('#department').on('change', function() { if (!initDone) return; rSpec(); rGrp(); });
            $('#specialty').on('change', function() { if (!initDone) return; rGrp(); });
            $('#level_code').on('change', function() { if (!initDone) return; rGrp(); });

            var initLoadCount = 0;
            function checkInit() { initLoadCount++; if (initLoadCount >= 4) initDone = true; }

            pd('{{ route("admin.students.filter.departments") }}', {education_type: sv.education_type}, '#department', sv.department, checkInit);
            pd('{{ route("admin.students.filter.specialties") }}', {education_type: sv.education_type, department: sv.department}, '#specialty', sv.specialty, checkInit);
            pd('{{ route("admin.students.filter.levels") }}', {education_type: sv.education_type, department: sv.department, specialty: sv.specialty}, '#level_code', sv.level_code, checkInit);
            pd('{{ route("admin.students.filter.groups") }}', {education_type: sv.education_type, department: sv.department, specialty: sv.specialty, level_code: sv.level_code}, '#group', sv.group, checkInit);
        });
    </script>

    <style>
        .filter-container { padding: 16px 20px 12px; background: linear-gradient(135deg, #f0f4f8, #e8edf5); border-bottom: 2px solid #dbe4ef; }
        .filter-row { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 10px; align-items: flex-end; }
        .filter-row:last-child { margin-bottom: 0; }
        .filter-label { display: flex; align-items: center; gap: 5px; margin-bottom: 4px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: #475569; }
        .fl-dot { width: 7px; height: 7px; border-radius: 50%; display: inline-block; flex-shrink: 0; }

        .btn-calc { display: inline-flex; align-items: center; gap: 8px; padding: 8px 20px; background: linear-gradient(135deg, #2b5ea7, #3b7ddb); color: #fff; border: none; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 8px rgba(43,94,167,0.3); height: 36px; white-space: nowrap; }
        .btn-calc:hover { background: linear-gradient(135deg, #1e4b8a, #2b5ea7); box-shadow: 0 4px 12px rgba(43,94,167,0.4); transform: translateY(-1px); }

        .select2-container--classic .select2-selection--single { height: 36px; border: 1px solid #cbd5e1; border-radius: 8px; background: #fff; transition: all 0.2s; box-shadow: 0 1px 2px rgba(0,0,0,0.04); }
        .select2-container--classic .select2-selection--single:hover { border-color: #2b5ea7; box-shadow: 0 0 0 2px rgba(43,94,167,0.1); }
        .select2-container--classic .select2-selection--single .select2-selection__rendered { line-height: 34px; padding-left: 10px; padding-right: 52px; color: #1e293b; font-size: 0.8rem; font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .select2-container--classic .select2-selection--single .select2-selection__arrow { height: 34px; width: 22px; background: transparent; border-left: none; right: 0; }
        .select2-container--classic .select2-selection--single .select2-selection__clear { position: absolute; right: 22px; top: 50%; transform: translateY(-50%); font-size: 16px; font-weight: bold; color: #94a3b8; cursor: pointer; padding: 2px 6px; z-index: 2; background: #fff; border-radius: 50%; line-height: 1; transition: all 0.15s; }
        .select2-container--classic .select2-selection--single .select2-selection__clear:hover { color: #fff; background: #ef4444; }
        .select2-dropdown { font-size: 0.8rem; border-radius: 8px; border: 1px solid #cbd5e1; box-shadow: 0 8px 24px rgba(0,0,0,0.12); }
        .select2-container--classic .select2-results__option--highlighted { background-color: #2b5ea7; }

        .student-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 13px; }
        .student-table thead { position: sticky; top: 0; z-index: 10; }
        .student-table thead tr { background: linear-gradient(135deg, #e8edf5, #dbe4ef, #d1d9e6); }
        .student-table th { padding: 12px 10px; text-align: left; font-weight: 600; font-size: 11px; color: #334155; text-transform: uppercase; letter-spacing: 0.05em; white-space: nowrap; border-bottom: 2px solid #cbd5e1; }
        .student-table tbody tr { transition: all 0.15s; border-bottom: 1px solid #f1f5f9; }
        .student-table tbody tr:nth-child(even) { background: #f8fafc; }
        .student-table tbody tr:nth-child(odd) { background: #fff; }
        .student-table tbody tr:hover { background: #eff6ff !important; box-shadow: inset 4px 0 0 #2b5ea7; }
        .student-table td { padding: 10px 10px; vertical-align: middle; line-height: 1.4; }

        .student-name-link { color: #1e40af; font-weight: 700; text-decoration: none; transition: all 0.15s; }
        .student-name-link:hover { color: #2b5ea7; text-decoration: underline; }

        .text-cell { font-size: 12.5px; font-weight: 500; line-height: 1.35; display: block; }
        .text-emerald { color: #047857; }
        .text-cyan { color: #0e7490; max-width: 220px; white-space: normal; word-break: break-word; }

        .badge { display: inline-block; padding: 3px 9px; border-radius: 6px; font-size: 11.5px; font-weight: 600; line-height: 1.4; }
        .badge-violet { background: #ede9fe; color: #5b21b6; border: 1px solid #ddd6fe; white-space: nowrap; }
        .badge-teal { background: #ccfbf1; color: #0f766e; border: 1px solid #99f6e4; white-space: nowrap; }
        .badge-indigo { background: linear-gradient(135deg, #1a3268, #2b5ea7); color: #fff; border: none; white-space: nowrap; }
    </style>
</x-app-layout>
