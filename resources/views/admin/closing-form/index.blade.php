<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            Fanning yopilish shakli
        </h2>
    </x-slot>

    <div class="py-4">
        <div class="max-w-full mx-auto sm:px-4 lg:px-6">
            @if(session('success'))
                <div style="background:#dcfce7;color:#166534;padding:10px 16px;border-radius:8px;margin-bottom:12px;border:1px solid #bbf7d0;">
                    {{ session('success') }}
                </div>
            @endif

            <div class="bg-white rounded-xl shadow-sm border border-gray-100" style="overflow: visible;">
                <!-- Filters -->
                <form id="filter-form" method="GET" action="{{ route('admin.closing-form.index') }}">
                    <div class="filter-container">
                        <div class="filter-row">
                            <div class="filter-item" style="min-width: 160px;">
                                <label class="filter-label fl-blue">
                                    <span class="fl-dot" style="background:#3b82f6;"></span> Ta'lim turi
                                </label>
                                <select name="education_type" id="education_type" class="select2" style="width: 100%;">
                                    <option value="">Barchasi</option>
                                    @foreach($educationTypes as $type)
                                        <option value="{{ $type->education_type_code }}" {{ ($selectedEducationType ?? request('education_type')) == $type->education_type_code ? 'selected' : '' }}>
                                            {{ $type->education_type_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="filter-item" style="flex: 1; min-width: 200px;">
                                <label class="filter-label fl-emerald">
                                    <span class="fl-dot" style="background:#10b981;"></span> Fakultet
                                </label>
                                <select name="faculty" id="faculty" class="select2" style="width: 100%;">
                                    <option value="">Barchasi</option>
                                    @foreach($faculties as $faculty)
                                        <option value="{{ $faculty->id }}" {{ request('faculty') == $faculty->id ? 'selected' : '' }}>
                                            {{ $faculty->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="filter-item" style="flex: 1; min-width: 240px;">
                                <label class="filter-label fl-cyan">
                                    <span class="fl-dot" style="background:#06b6d4;"></span> Yo'nalish
                                </label>
                                <select name="specialty" id="specialty" class="select2" style="width: 100%;">
                                    <option value="">Barchasi</option>
                                </select>
                            </div>

                            <div class="filter-item" style="min-width: 160px;">
                                <label class="filter-label">&nbsp;</label>
                                @php $csDefault = '1'; @endphp
                                <input type="hidden" name="current_semester" id="current_semester_input" value="{{ request('current_semester', $csDefault) }}">
                                <div class="toggle-switch {{ request('current_semester', $csDefault) == '1' ? 'active' : '' }}" id="current-semester-toggle" onclick="toggleCurrentSemester()">
                                    <div class="toggle-track"><div class="toggle-thumb"></div></div>
                                    <span class="toggle-label">Joriy semestr</span>
                                </div>
                            </div>
                        </div>

                        <div class="filter-row">
                            <div class="filter-item" style="min-width: 140px;">
                                <label class="filter-label fl-violet">
                                    <span class="fl-dot" style="background:#8b5cf6;"></span> Kurs
                                </label>
                                <select name="level_code" id="level_code" class="select2" style="width: 100%;">
                                    <option value="">Barchasi</option>
                                </select>
                            </div>

                            <div class="filter-item" style="min-width: 150px;">
                                <label class="filter-label fl-teal">
                                    <span class="fl-dot" style="background:#14b8a6;"></span> Semestr
                                </label>
                                <select name="semester_code" id="semester_code" class="select2" style="width: 100%;">
                                    <option value="">Barchasi</option>
                                </select>
                            </div>

                            <div class="filter-item" style="flex: 1; min-width: 200px;">
                                <label class="filter-label" style="color: #ea580c;">
                                    <span class="fl-dot" style="background:#ea580c;"></span> Fan
                                </label>
                                <input type="text" name="subject_name" id="subject_name" value="{{ request('subject_name') }}" placeholder="Fan nomini kiriting..." class="filter-input">
                            </div>

                            <div class="filter-item" style="min-width: 170px;">
                                <label class="filter-label" style="color: #059669;">
                                    <span class="fl-dot" style="background:#059669;"></span> Yopilish shakli
                                </label>
                                <select name="closing_form_filter" id="closing_form_filter" class="select2" style="width: 100%;">
                                    <option value="">Barchasi</option>
                                    <option value="unset" {{ request('closing_form_filter') == 'unset' ? 'selected' : '' }}>Belgilanmagan</option>
                                    <option value="oski" {{ request('closing_form_filter') == 'oski' ? 'selected' : '' }}>Faqat OSKI</option>
                                    <option value="test" {{ request('closing_form_filter') == 'test' ? 'selected' : '' }}>Faqat Test</option>
                                    <option value="oski_test" {{ request('closing_form_filter') == 'oski_test' ? 'selected' : '' }}>OSKI + Test</option>
                                    <option value="normativ" {{ request('closing_form_filter') == 'normativ' ? 'selected' : '' }}>Normativ</option>
                                    <option value="sinov" {{ request('closing_form_filter') == 'sinov' ? 'selected' : '' }}>Sinov (test)</option>
                                    <option value="none" {{ request('closing_form_filter') == 'none' ? 'selected' : '' }}>Yo'q</option>
                                </select>
                            </div>

                            <div class="filter-item" style="min-width: 90px;">
                                <label class="filter-label fl-slate">
                                    <span class="fl-dot" style="background:#94a3b8;"></span> Sahifada
                                </label>
                                <select id="per_page" name="per_page" class="select2" style="width: 100%;">
                                    @foreach([25, 50, 100, 200] as $pageSize)
                                        <option value="{{ $pageSize }}" {{ request('per_page', 50) == $pageSize ? 'selected' : '' }}>{{ $pageSize }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="filter-item" style="min-width: 140px;">
                                <label class="filter-label">&nbsp;</label>
                                <button type="submit" class="btn-search">
                                    <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                    </svg>
                                    Qidirish
                                </button>
                            </div>
                        </div>
                    </div>
                </form>

                <!-- Save form (separate) -->
                <form id="save-form" method="POST" action="{{ route('admin.closing-form.bulk-update', request()->query()) }}">
                    @csrf

                    <div class="action-bar">
                        <div class="action-bar-left">
                            <span class="action-bar-label">Sahifadagi barchasiga qo'llash:</span>
                            <select id="bulk-apply" class="bulk-select">
                                <option value="">— Tanlang —</option>
                                <option value="oski">Faqat OSKI</option>
                                <option value="test">Faqat Test</option>
                                <option value="oski_test">OSKI + Test</option>
                                <option value="normativ">Normativ</option>
                                <option value="sinov">Sinov (test)</option>
                                <option value="none">Yo'q</option>
                            </select>
                            <button type="button" onclick="applyBulk()" class="btn-apply">
                                <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                                Qo'llash
                            </button>
                        </div>
                        <button type="submit" class="btn-save">
                            <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            Saqlash
                        </button>
                    </div>

                    <div style="max-height: calc(100vh - 360px); overflow-y: auto; overflow-x: auto;">
                        @if($subjects->isEmpty())
                            <div style="padding: 60px 20px; text-align: center;">
                                <p style="color: #94a3b8; font-size: 14px;">Filtrlarni tanlang va "Qidirish" tugmasini bosing.</p>
                            </div>
                        @else
                            <table class="journal-table">
                                <thead>
                                    <tr>
                                        <th class="th-num">#</th>
                                        <th>Fakultet</th>
                                        <th>Yo'nalish</th>
                                        <th>Kurs</th>
                                        <th>Semestr</th>
                                        <th>Fan</th>
                                        <th style="text-align: center; min-width: 380px;">Yopilish shakli</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($subjects as $index => $item)
                                        @php $cf = $item->closing_form ?? null; @endphp
                                        <tr class="journal-row">
                                            <td class="td-num">{{ $subjects->firstItem() + $index }}</td>
                                            <td><span class="text-cell text-emerald">{{ $item->faculty_name ?? '-' }}</span></td>
                                            <td><span class="text-cell text-cyan">{{ $item->specialty_name ?? '-' }}</span></td>
                                            <td><span class="badge badge-violet">{{ $item->level_name ?? '-' }}</span></td>
                                            <td><span class="badge badge-teal">{{ $item->semester_name ?? '-' }}</span></td>
                                            <td><span class="text-cell text-subject">{{ $item->subject_name ?? '-' }}</span></td>
                                            <td>
                                                <div style="display: flex; gap: 4px; flex-wrap: wrap; justify-content: center;">
                                                    @foreach([
                                                        'oski' => ['Faqat OSKI', '#dbeafe', '#1d4ed8'],
                                                        'test' => ['Faqat Test', '#dcfce7', '#15803d'],
                                                        'oski_test' => ['OSKI + Test', '#ede9fe', '#6d28d9'],
                                                        'normativ' => ['Normativ', '#fef3c7', '#a16207'],
                                                        'sinov' => ['Sinov (test)', '#ffedd5', '#c2410c'],
                                                        'none' => ["Yo'q", '#f1f5f9', '#475569'],
                                                    ] as $val => $meta)
                                                        @php
                                                            $checked = $cf === $val;
                                                            $bg = $checked ? $meta[1] : '#ffffff';
                                                            $fg = $checked ? $meta[2] : '#64748b';
                                                            $border = $checked ? $meta[2] : '#cbd5e1';
                                                        @endphp
                                                        <label class="cf-radio" style="background:{{ $bg }};color:{{ $fg }};border:1px solid {{ $border }};padding:4px 10px;border-radius:6px;font-size:12px;cursor:pointer;display:inline-flex;align-items:center;gap:4px;font-weight:{{ $checked ? '600' : '400' }};">
                                                            <input type="radio" name="closing_forms[{{ $item->id }}]" value="{{ $val }}" {{ $checked ? 'checked' : '' }} class="cf-input" data-row="{{ $item->id }}" style="margin:0;">
                                                            {{ $meta[0] }}
                                                        </label>
                                                    @endforeach
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>

                            <div style="padding: 12px 20px; border-top: 1px solid #e2e8f0; background: #f8fafc;">
                                {{ $subjects->links() }}
                            </div>
                        @endif
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
        function toggleCurrentSemester() {
            const btn = document.getElementById('current-semester-toggle');
            const input = document.getElementById('current_semester_input');
            if (btn.classList.contains('active')) {
                btn.classList.remove('active'); input.value = '0';
            } else {
                btn.classList.add('active'); input.value = '1';
            }
        }

        function applyBulk() {
            const val = document.getElementById('bulk-apply').value;
            if (!val) { alert('Avval qiymatni tanlang.'); return; }
            document.querySelectorAll('.cf-input').forEach(inp => {
                if (inp.value === val) {
                    inp.checked = true;
                    inp.dispatchEvent(new Event('change', { bubbles: true }));
                }
            });
            updateRadioStyles();
        }

        function updateRadioStyles() {
            const palette = {
                'oski': { bg: '#dbeafe', fg: '#1d4ed8' },
                'test': { bg: '#dcfce7', fg: '#15803d' },
                'oski_test': { bg: '#ede9fe', fg: '#6d28d9' },
                'normativ': { bg: '#fef3c7', fg: '#a16207' },
                'sinov': { bg: '#ffedd5', fg: '#c2410c' },
                'none': { bg: '#f1f5f9', fg: '#475569' },
            };
            document.querySelectorAll('.cf-input').forEach(inp => {
                const lbl = inp.closest('label');
                const c = palette[inp.value];
                if (inp.checked) {
                    lbl.style.background = c.bg;
                    lbl.style.color = c.fg;
                    lbl.style.border = '1px solid ' + c.fg;
                    lbl.style.fontWeight = '600';
                } else {
                    lbl.style.background = '#ffffff';
                    lbl.style.color = '#64748b';
                    lbl.style.border = '1px solid #cbd5e1';
                    lbl.style.fontWeight = '400';
                }
            });
        }

        document.addEventListener('change', function (e) {
            if (e.target.classList && e.target.classList.contains('cf-input')) {
                updateRadioStyles();
            }
        });

        $(document).ready(function () {
            $('.select2').each(function () {
                $(this).select2({ theme: 'classic', width: '100%', allowClear: true, placeholder: $(this).find('option:first').text() });
            });

            const selSpec = @json(request('specialty'));
            const selLevel = @json(request('level_code'));
            const selSem = @json(request('semester_code'));

            function reset(el, ph) { $(el).empty().append('<option value="">' + ph + '</option>'); }
            function populate(url, params, el, cb) {
                $.ajax({ url: url, type: 'GET', data: params, success: function (data) {
                    $.each(data, function (k, v) { $(el).append('<option value="' + k + '">' + v + '</option>'); });
                    if (cb) cb();
                }});
            }

            function loadSpecialties(preselect) {
                reset('#specialty', 'Barchasi');
                populate('{{ route('admin.closing-form.get-specialties') }}', {
                    education_type: $('#education_type').val(),
                    faculty_id: $('#faculty').val(),
                    current_semester: $('#current_semester_input').val()
                }, '#specialty', function () {
                    if (preselect) $('#specialty').val(preselect);
                    $('#specialty').trigger('change.select2');
                });
            }

            function loadLevels(preselect) {
                reset('#level_code', 'Barchasi');
                populate('{{ route('admin.closing-form.get-level-codes') }}', {}, '#level_code', function () {
                    if (preselect) $('#level_code').val(preselect);
                    $('#level_code').trigger('change.select2');
                });
            }

            function loadSemesters(preselect) {
                reset('#semester_code', 'Barchasi');
                populate('{{ route('admin.closing-form.get-semesters') }}', {
                    level_code: $('#level_code').val()
                }, '#semester_code', function () {
                    if (preselect) $('#semester_code').val(preselect);
                    $('#semester_code').trigger('change.select2');
                });
            }

            loadSpecialties(selSpec);
            loadLevels(selLevel);
            loadSemesters(selSem);

            $('#education_type, #faculty').on('change', function () { loadSpecialties(); });
            $('#level_code').on('change', function () { loadSemesters(); });
        });
    </script>

    <style>
        /* Filter container */
        .filter-container { padding: 16px 20px 12px; background: linear-gradient(135deg, #f0f4f8, #e8edf5); border-bottom: 2px solid #dbe4ef; }
        .filter-row { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 10px; align-items: flex-end; }
        .filter-row:last-child { margin-bottom: 0; }
        .filter-label { display: flex; align-items: center; gap: 5px; margin-bottom: 4px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: #475569; }
        .fl-dot { width: 7px; height: 7px; border-radius: 50%; display: inline-block; flex-shrink: 0; }

        /* Filter input (Fan nomi) */
        .filter-input { height: 36px; border: 1px solid #cbd5e1; border-radius: 8px; padding: 0 12px; font-size: 0.8rem; font-weight: 500; color: #1e293b; background: #fff; width: 100%; box-shadow: 0 1px 2px rgba(0,0,0,0.04); transition: all 0.2s; outline: none; }
        .filter-input:hover { border-color: #2b5ea7; box-shadow: 0 0 0 2px rgba(43,94,167,0.1); }
        .filter-input:focus { border-color: #2b5ea7; box-shadow: 0 0 0 3px rgba(43,94,167,0.15); }
        .filter-input::placeholder { color: #94a3b8; font-weight: 400; }

        /* Filter buttons */
        .btn-search { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 8px 20px; background: linear-gradient(135deg, #2b5ea7, #3b7ddb); color: #fff; border: none; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 8px rgba(43,94,167,0.3); height: 36px; width: 100%; }
        .btn-search:hover { background: linear-gradient(135deg, #1e4b8a, #2b5ea7); box-shadow: 0 4px 12px rgba(43,94,167,0.4); transform: translateY(-1px); }
        .btn-search:active { transform: translateY(0); box-shadow: 0 2px 6px rgba(43,94,167,0.3); }

        .btn-save { display: inline-flex; align-items: center; gap: 8px; padding: 8px 20px; background: linear-gradient(135deg, #16a34a, #22c55e); color: #fff; border: none; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 8px rgba(22,163,74,0.3); height: 36px; }
        .btn-save:hover { background: linear-gradient(135deg, #15803d, #16a34a); box-shadow: 0 4px 12px rgba(22,163,74,0.4); transform: translateY(-1px); }
        .btn-save:active { transform: translateY(0); box-shadow: 0 2px 6px rgba(22,163,74,0.3); }

        .btn-apply { display: inline-flex; align-items: center; gap: 6px; padding: 7px 14px; background: linear-gradient(135deg, #3b82f6, #2563eb); color: #fff; border: none; border-radius: 8px; font-size: 12px; font-weight: 700; cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 6px rgba(37,99,235,0.3); height: 32px; }
        .btn-apply:hover { background: linear-gradient(135deg, #2563eb, #1d4ed8); box-shadow: 0 4px 10px rgba(37,99,235,0.4); transform: translateY(-1px); }
        .btn-apply:active { transform: translateY(0); box-shadow: 0 2px 6px rgba(37,99,235,0.3); }

        /* Action bar (bulk apply / save) */
        .action-bar { padding: 10px 16px; background: linear-gradient(135deg, #f8fafc, #f1f5f9); border-top: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; }
        .action-bar-left { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .action-bar-label { font-size: 13px; color: #475569; font-weight: 600; }
        .bulk-select { height: 32px; padding: 0 28px 0 10px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 12.5px; font-weight: 500; color: #1e293b; background: #fff url("data:image/svg+xml;charset=utf-8,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' d='M19 9l-7 7-7-7'/%3E%3C/svg%3E") no-repeat right 8px center / 14px; -webkit-appearance: none; appearance: none; outline: none; box-shadow: 0 1px 2px rgba(0,0,0,0.04); transition: all 0.2s; cursor: pointer; }
        .bulk-select:hover { border-color: #2b5ea7; box-shadow: 0 0 0 2px rgba(43,94,167,0.1); }
        .bulk-select:focus { border-color: #2b5ea7; box-shadow: 0 0 0 3px rgba(43,94,167,0.15); }

        /* Toggle switch */
        .toggle-switch { display: inline-flex; align-items: center; gap: 10px; cursor: pointer; padding: 6px 0; height: 36px; user-select: none; }
        .toggle-track { width: 40px; height: 22px; background: #cbd5e1; border-radius: 11px; position: relative; transition: background 0.25s; flex-shrink: 0; }
        .toggle-switch.active .toggle-track { background: linear-gradient(135deg, #2b5ea7, #3b7ddb); }
        .toggle-thumb { width: 18px; height: 18px; background: #fff; border-radius: 50%; position: absolute; top: 2px; left: 2px; transition: transform 0.25s; box-shadow: 0 1px 4px rgba(0,0,0,0.2); }
        .toggle-switch.active .toggle-thumb { transform: translateX(18px); }
        .toggle-label { font-size: 12px; font-weight: 600; color: #64748b; white-space: nowrap; }
        .toggle-switch.active .toggle-label { color: #1e3a5f; }

        /* Select2 */
        .select2-container--classic .select2-selection--single { height: 36px; border: 1px solid #cbd5e1; border-radius: 8px; background: #fff; transition: all 0.2s; box-shadow: 0 1px 2px rgba(0,0,0,0.04); }
        .select2-container--classic .select2-selection--single:hover { border-color: #2b5ea7; box-shadow: 0 0 0 2px rgba(43,94,167,0.1); }
        .select2-container--classic .select2-selection--single .select2-selection__rendered { line-height: 34px; padding-left: 10px; padding-right: 52px; color: #1e293b; font-size: 0.8rem; font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .select2-container--classic .select2-selection--single .select2-selection__arrow { height: 34px; width: 22px; background: transparent; border-left: none; right: 0; }
        .select2-container--classic .select2-selection--single .select2-selection__clear { position: absolute; right: 22px; top: 50%; transform: translateY(-50%); font-size: 16px; font-weight: bold; color: #94a3b8; cursor: pointer; padding: 2px 6px; z-index: 2; background: #fff; border-radius: 50%; line-height: 1; transition: all 0.15s; }
        .select2-container--classic .select2-selection--single .select2-selection__clear:hover { color: #fff; background: #ef4444; }
        .select2-dropdown { font-size: 0.8rem; border-radius: 8px; border: 1px solid #cbd5e1; box-shadow: 0 8px 24px rgba(0,0,0,0.12); }
        .select2-container--classic .select2-results__option--highlighted { background-color: #2b5ea7; }

        /* Journal table */
        .journal-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 13px; }
        .journal-table thead { position: sticky; top: 0; z-index: 10; }
        .journal-table thead tr { background: linear-gradient(135deg, #e8edf5, #dbe4ef, #d1d9e6); }
        .journal-table th { padding: 14px 12px; text-align: left; font-weight: 600; font-size: 11.5px; color: #334155; text-transform: uppercase; letter-spacing: 0.05em; white-space: nowrap; border-bottom: 2px solid #cbd5e1; }
        .journal-table th.th-num { padding: 14px 12px 14px 16px; width: 44px; }
        .journal-table tbody tr { transition: all 0.15s; border-bottom: 1px solid #f1f5f9; }
        .journal-table tbody tr:nth-child(even) { background: #f8fafc; }
        .journal-table tbody tr:nth-child(odd) { background: #fff; }
        .journal-table tbody tr:hover { background: #eff6ff !important; box-shadow: inset 4px 0 0 #2b5ea7; }
        .journal-table td { padding: 10px 12px; vertical-align: middle; line-height: 1.4; }
        .td-num { padding-left: 16px !important; font-weight: 700; color: #2b5ea7; font-size: 13px; }

        /* Badges & cells */
        .badge { display: inline-block; padding: 3px 9px; border-radius: 6px; font-size: 11.5px; font-weight: 600; line-height: 1.4; }
        .badge-violet { background: #ede9fe; color: #5b21b6; border: 1px solid #ddd6fe; white-space: nowrap; }
        .badge-teal { background: #ccfbf1; color: #0f766e; border: 1px solid #99f6e4; white-space: nowrap; }
        .text-cell { font-size: 12.5px; font-weight: 500; line-height: 1.35; display: block; }
        .text-emerald { color: #047857; }
        .text-cyan { color: #0e7490; max-width: 220px; white-space: normal; word-break: break-word; }
        .text-subject { color: #0f172a; font-weight: 700; font-size: 12.5px; max-width: 320px; white-space: normal; word-break: break-word; }

        /* Closing-form radio chips */
        .cf-radio { transition: all 0.15s; }
        .cf-radio:hover { transform: translateY(-1px); box-shadow: 0 2px 6px rgba(0,0,0,0.08); }
        .cf-radio input[type="radio"] { accent-color: #2b5ea7; }
    </style>
</x-app-layout>
