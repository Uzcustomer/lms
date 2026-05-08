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

                            <div style="display: flex; align-items: flex-end; padding-bottom: 6px; gap: 8px;">
                                <button type="submit" class="ktr-search-btn">
                                    <svg style="width: 16px; height: 16px; margin-right: 4px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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

                    <div style="padding: 10px 16px; background: #f8fafc; border-top: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span style="font-size: 13px; color: #475569;">Sahifadagi barchasiga qo'llash:</span>
                            <select id="bulk-apply" style="padding: 4px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px;">
                                <option value="">— Tanlang —</option>
                                <option value="oski">Faqat OSKI</option>
                                <option value="test">Faqat Test</option>
                                <option value="oski_test">OSKI + Test</option>
                                <option value="none">Yo'q</option>
                            </select>
                            <button type="button" onclick="applyBulk()" style="background: #3b82f6; color: white; padding: 5px 12px; border: none; border-radius: 6px; font-size: 12px; cursor: pointer;">Qo'llash</button>
                        </div>
                        <button type="submit" style="background: #16a34a; color: white; padding: 7px 18px; border: none; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer;">
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
</x-app-layout>
