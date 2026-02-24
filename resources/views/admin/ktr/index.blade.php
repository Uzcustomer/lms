<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            KTR (Kalendar tematik reja)
        </h2>
    </x-slot>

    <div class="py-4">
        <div class="max-w-full mx-auto sm:px-4 lg:px-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100" style="overflow: visible;">

                <!-- Filters -->
                <form id="filter-form" method="GET" action="{{ route('admin.ktr.index') }}">
                    <div class="filter-container">

                        <!-- Row 1 -->
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
                                <input type="hidden" name="current_semester" id="current_semester_input" value="{{ request('current_semester', '1') }}">
                                <div class="toggle-switch {{ request('current_semester', '1') == '1' ? 'active' : '' }}" id="current-semester-toggle" onclick="toggleCurrentSemester()">
                                    <div class="toggle-track">
                                        <div class="toggle-thumb"></div>
                                    </div>
                                    <span class="toggle-label">Joriy semestr</span>
                                </div>
                            </div>
                        </div>

                        <!-- Row 2 -->
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

                            <div class="filter-item" style="min-width: 90px;">
                                <label class="filter-label fl-slate">
                                    <span class="fl-dot" style="background:#94a3b8;"></span> Sahifada
                                </label>
                                <select id="per_page" name="per_page" class="select2" style="width: 100%;">
                                    @foreach([10, 25, 50, 100] as $pageSize)
                                        <option value="{{ $pageSize }}" {{ request('per_page', 50) == $pageSize ? 'selected' : '' }}>
                                            {{ $pageSize }}
                                        </option>
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
                                <div id="filter-loading" class="hidden" style="display: none; align-items: center; color: #2b5ea7;">
                                    <svg class="animate-spin" style="height: 16px; width: 16px; margin-right: 4px;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle style="opacity: 0.25;" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path style="opacity: 0.75;" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>

                <!-- Table -->
                <div style="max-height: calc(100vh - 300px); overflow-y: auto; overflow-x: auto; -webkit-overflow-scrolling: touch;">
                    @if($subjects->isEmpty())
                        <div style="padding: 60px 20px; text-align: center;">
                            <svg style="width: 48px; height: 48px; margin: 0 auto 12px; color: #cbd5e1;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            <p style="color: #94a3b8; font-size: 14px;">Filtrlarni tanlang va "Qidirish" tugmasini bosing.</p>
                        </div>
                    @else
                        @php
                            $sortColumn = $sortColumn ?? 'faculty_name';
                            $sortDirection = $sortDirection ?? 'asc';
                        @endphp
                        <table class="journal-table">
                            <thead>
                                <tr>
                                    <th class="th-num">#</th>
                                    @php
                                        $columns = [
                                            'faculty_name' => 'Fakultet',
                                            'specialty_name' => "Yo'nalish",
                                            'level_name' => 'Kurs',
                                            'semester_name' => 'Semestr',
                                            'subject_name' => 'Fan',
                                            'credit' => 'Kredit',
                                            'total_acload' => 'Jami yuklama',
                                        ];
                                    @endphp
                                    @foreach($columns as $column => $label)
                                        @php
                                            $isActive = $sortColumn === $column;
                                            $newDirection = ($isActive && $sortDirection === 'asc') ? 'desc' : 'asc';
                                            $sortUrl = request()->fullUrlWithQuery(['sort' => $column, 'direction' => $newDirection]);
                                        @endphp
                                        <th>
                                            <a href="{{ $sortUrl }}" class="sort-link">
                                                {{ $label }}
                                                @if($isActive)
                                                    <span class="sort-icon active">
                                                        @if($sortDirection === 'asc') &#9650; @else &#9660; @endif
                                                    </span>
                                                @else
                                                    <span class="sort-icon">&#9650;&#9660;</span>
                                                @endif
                                            </a>
                                        </th>
                                    @endforeach
                                    @foreach($trainingTypes as $code => $name)
                                        <th style="text-align: center; min-width: 80px;">{{ $name }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($subjects as $index => $item)
                                    @php
                                        $details = $item->subject_details;
                                        if (is_string($details)) {
                                            $details = json_decode($details, true);
                                        }
                                        $loadMap = [];
                                        if (is_array($details)) {
                                            foreach ($details as $detail) {
                                                $tCode = (string) ($detail['trainingType']['code'] ?? '');
                                                $hours = (int) ($detail['academic_load'] ?? 0);
                                                if ($tCode !== '') {
                                                    $loadMap[$tCode] = $hours;
                                                }
                                            }
                                        }
                                    @endphp
                                    <tr class="journal-row">
                                        <td class="td-num">{{ $subjects->firstItem() + $index }}</td>
                                        <td><span class="text-cell text-emerald">{{ $item->faculty_name ?? '-' }}</span></td>
                                        <td><span class="text-cell text-cyan">{{ $item->specialty_name ?? '-' }}</span></td>
                                        <td><span class="badge badge-violet">{{ $item->level_name ?? '-' }}</span></td>
                                        <td><span class="badge badge-teal">{{ $item->semester_name ?? '-' }}</span></td>
                                        <td><span class="text-cell text-subject">{{ $item->subject_name ?? '-' }}</span></td>
                                        <td style="text-align: center;"><span class="badge badge-blue">{{ $item->credit ?? '-' }}</span></td>
                                        <td style="text-align: center;"><span class="badge badge-amber">{{ $item->total_acload ?? '-' }}</span></td>
                                        @foreach($trainingTypes as $code => $name)
                                            <td style="text-align: center;">
                                                @if(isset($loadMap[$code]) && $loadMap[$code] > 0)
                                                    <span class="badge badge-indigo">{{ $loadMap[$code] }}</span>
                                                @else
                                                    <span style="color: #cbd5e1;">-</span>
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>

                        <div style="padding: 12px 20px; border-top: 1px solid #e2e8f0; background: #f8fafc;">
                            {{ $subjects->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
        function stripSpecialChars(str) {
            return str.replace(/[\/\(\),\-\.\s]/g, '').toLowerCase();
        }

        function fuzzyMatcher(params, data) {
            if ($.trim(params.term) === '') return data;
            if (typeof data.text === 'undefined') return null;
            var searchClean = stripSpecialChars(params.term);
            var optionClean = stripSpecialChars(data.text);
            if (optionClean.indexOf(searchClean) > -1) return $.extend({}, data, true);
            if (data.text.toLowerCase().indexOf(params.term.toLowerCase()) > -1) return $.extend({}, data, true);
            return null;
        }

        function toggleCurrentSemester() {
            const btn = document.getElementById('current-semester-toggle');
            const input = document.getElementById('current_semester_input');
            const isActive = btn.classList.contains('active');
            if (isActive) {
                btn.classList.remove('active');
                input.value = '0';
            } else {
                btn.classList.add('active');
                input.value = '1';
            }
        }

        $(document).ready(function () {
            $('.select2').each(function () {
                $(this).select2({
                    theme: 'classic',
                    width: '100%',
                    allowClear: true,
                    placeholder: $(this).find('option:first').text(),
                    matcher: fuzzyMatcher
                }).on('select2:open', function() {
                    setTimeout(function() {
                        var sf = document.querySelector('.select2-container--open .select2-search__field');
                        if (sf) sf.focus();
                    }, 10);
                });
            });

            const selectedSpecialty = @json(request('specialty'));
            const selectedLevelCode = @json(request('level_code'));
            const selectedSemesterCode = @json(request('semester_code'));

            function resetDropdown(el, ph) {
                $(el).empty().append('<option value="">' + ph + '</option>');
            }

            function populateDropdown(url, params, element, callback) {
                $.ajax({ url: url, type: 'GET', data: params, success: function (data) {
                    $.each(data, function (k, v) { $(element).append('<option value="' + k + '">' + v + '</option>'); });
                    if (callback) callback(data);
                }});
            }

            function populateDropdownUnique(url, params, element, callback) {
                $.ajax({ url: url, type: 'GET', data: params, success: function (data) {
                    var unique = {};
                    $.each(data, function (k, v) { if (!unique[v]) unique[v] = k; });
                    $.each(unique, function (n, k) { $(element).append('<option value="' + k + '">' + n + '</option>'); });
                    if (callback) callback(data);
                }});
            }

            function getFilterParams() {
                return {
                    education_type: $('#education_type').val() || '',
                    faculty_id: $('#faculty').val() || '',
                    current_semester: $('#current_semester_input').val() || '1',
                };
            }

            function refreshSpecialties() {
                resetDropdown('#specialty', 'Barchasi');
                populateDropdownUnique('{{ route("admin.ktr.get-specialties") }}', getFilterParams(), '#specialty');
            }

            $('#education_type').change(function () { refreshSpecialties(); });
            $('#faculty').change(function () { refreshSpecialties(); });
            $('#per_page').on('change', function() {
                $('#filter-loading').removeClass('hidden').css('display', 'flex');
                $('#filter-form').submit();
            });

            $('#level_code').change(function () {
                var lc = $(this).val();
                resetDropdown('#semester_code', 'Barchasi');
                if (lc) {
                    populateDropdown('{{ route("admin.ktr.get-semesters") }}', { level_code: lc }, '#semester_code');
                }
            });

            // Sahifa yuklanganda filtrlarni to'ldirish
            var p = getFilterParams();
            populateDropdownUnique('{{ route("admin.ktr.get-specialties") }}', p, '#specialty', function() {
                if (selectedSpecialty) $('#specialty').val(selectedSpecialty).trigger('change.select2');
            });
            populateDropdown('{{ route("admin.ktr.get-level-codes") }}', {}, '#level_code', function() {
                if (selectedLevelCode) {
                    $('#level_code').val(selectedLevelCode).trigger('change.select2');
                    // Semestr ham yuklash
                    if (selectedLevelCode) {
                        populateDropdown('{{ route("admin.ktr.get-semesters") }}', { level_code: selectedLevelCode }, '#semester_code', function() {
                            if (selectedSemesterCode) $('#semester_code').val(selectedSemesterCode).trigger('change.select2');
                        });
                    }
                }
            });
        });
    </script>

    <style>
        .ktr-search-btn {
            display: inline-flex;
            align-items: center;
            padding: 8px 20px;
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 2px 6px rgba(37, 99, 235, 0.3);
        }
        .ktr-search-btn:hover {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.4);
            transform: translateY(-1px);
        }

        /* Filter container - Journal bilan bir xil */
        .filter-container {
            padding: 16px 20px 12px;
            border-bottom: 1px solid #e2e8f0;
        }
        .filter-row {
            display: flex;
            gap: 12px;
            margin-bottom: 12px;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        .filter-item {
            display: flex;
            flex-direction: column;
        }
        .filter-label {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 5px;
            color: #64748b;
        }
        .fl-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            display: inline-block;
        }

        /* Toggle switch */
        .toggle-switch {
            display: flex;
            align-items: center;
            cursor: pointer;
            user-select: none;
            padding: 6px 0;
        }
        .toggle-track {
            width: 36px;
            height: 20px;
            background: #cbd5e1;
            border-radius: 10px;
            position: relative;
            transition: background 0.2s;
            margin-right: 8px;
        }
        .toggle-switch.active .toggle-track {
            background: #3b82f6;
        }
        .toggle-thumb {
            width: 16px;
            height: 16px;
            background: #fff;
            border-radius: 50%;
            position: absolute;
            top: 2px;
            left: 2px;
            transition: transform 0.2s;
            box-shadow: 0 1px 3px rgba(0,0,0,0.2);
        }
        .toggle-switch.active .toggle-thumb {
            transform: translateX(16px);
        }
        .toggle-label {
            font-size: 12px;
            font-weight: 500;
            color: #64748b;
        }
        .toggle-switch.active .toggle-label {
            color: #3b82f6;
        }

        /* Journal table styles */
        .journal-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        .journal-table thead th {
            background: #f8fafc;
            padding: 10px 12px;
            text-align: left;
            font-weight: 600;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #64748b;
            border-bottom: 2px solid #e2e8f0;
            white-space: nowrap;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .journal-table tbody td {
            padding: 10px 12px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }
        .journal-row:hover {
            background: #f8fafc;
        }
        .th-num, .td-num {
            width: 40px;
            text-align: center;
            color: #94a3b8;
            font-size: 12px;
        }

        /* Badges */
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
            white-space: nowrap;
        }
        .badge-blue { background: #eff6ff; color: #1d4ed8; }
        .badge-violet { background: #f5f3ff; color: #7c3aed; }
        .badge-teal { background: #f0fdfa; color: #0f766e; }
        .badge-amber { background: #fffbeb; color: #b45309; }
        .badge-indigo { background: #eef2ff; color: #4338ca; }

        .text-cell { font-size: 13px; }
        .text-emerald { color: #059669; }
        .text-cyan { color: #0891b2; }
        .text-subject { color: #0f172a; font-weight: 500; }

        .sort-link {
            color: inherit;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .sort-link:hover { color: #3b82f6; }
        .sort-icon { font-size: 10px; color: #cbd5e1; }
        .sort-icon.active { color: #3b82f6; }

        /* Select2 customization */
        .select2-container--classic .select2-selection--single {
            border-radius: 8px !important;
            border-color: #e2e8f0 !important;
            height: 36px !important;
        }
        .select2-container--classic .select2-selection--single .select2-selection__rendered {
            line-height: 34px !important;
            font-size: 13px !important;
        }
        .select2-container--classic .select2-selection--single .select2-selection__arrow {
            height: 34px !important;
        }
    </style>
</x-app-layout>
