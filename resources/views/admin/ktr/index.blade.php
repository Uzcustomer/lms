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
                                        <td>
                                            <a href="javascript:void(0)" class="ktr-subject-link" onclick="openKtrPlan({{ $item->id }})">
                                                {{ $item->subject_name ?? '-' }}
                                            </a>
                                        </td>
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

    <!-- KTR Plan Modal -->
    <div id="ktr-modal-overlay" class="ktr-modal-overlay" style="display:none;" onclick="closeKtrModal(event)">
        <div class="ktr-modal" onclick="event.stopPropagation()">
            <div class="ktr-modal-header">
                <div>
                    <h3 class="ktr-modal-title" id="ktr-modal-title">Fan nomi</h3>
                    <div class="ktr-modal-subtitle">
                        Jami yuklama: <span class="badge badge-amber" id="ktr-total-load">0</span> soat
                    </div>
                </div>
                <button class="ktr-modal-close" onclick="closeKtrModal()">&times;</button>
            </div>

            <div class="ktr-modal-body">
                <!-- Hafta tanlash -->
                <div class="ktr-week-selector" id="ktr-week-selector">
                    <label class="filter-label fl-blue" style="margin-bottom: 8px;">
                        <span class="fl-dot" style="background:#3b82f6;"></span> Fan davomiyligi (hafta)
                    </label>
                    <div class="ktr-week-buttons" id="ktr-week-buttons">
                        @for($w = 1; $w <= 15; $w++)
                            <button type="button" class="ktr-week-btn" data-week="{{ $w }}" onclick="selectWeekCount({{ $w }})">{{ $w }}</button>
                        @endfor
                    </div>
                </div>

                <!-- Soatlar jadvali -->
                <div id="ktr-plan-table-wrap" style="display:none;">
                    <div style="max-height: 55vh; overflow: auto;">
                        <table class="ktr-plan-table" id="ktr-plan-table">
                            <thead id="ktr-plan-thead"></thead>
                            <tbody id="ktr-plan-tbody"></tbody>
                            <tfoot id="ktr-plan-tfoot"></tfoot>
                        </table>
                    </div>

                    <!-- Xabarnoma -->
                    <div id="ktr-validation-msg" class="ktr-validation-msg" style="display:none;"></div>

                    <!-- Saqlash tugmasi -->
                    <div style="display: flex; justify-content: flex-end; margin-top: 16px; gap: 10px;">
                        <button type="button" class="ktr-btn ktr-btn-secondary" onclick="closeKtrModal()">Bekor qilish</button>
                        <button type="button" class="ktr-btn ktr-btn-primary" id="ktr-save-btn" onclick="saveKtrPlan()">Saqlash</button>
                    </div>
                </div>
            </div>

            <!-- Loading -->
            <div id="ktr-modal-loading" class="ktr-modal-loading" style="display:none;">
                <svg class="animate-spin" style="height: 32px; width: 32px; color: #3b82f6;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle style="opacity: 0.25;" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path style="opacity: 0.75;" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
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

        // ===== KTR Plan Modal =====
        var ktrState = {
            csId: null,
            totalLoad: 0,
            trainingTypes: {},
            weekCount: 0,
            planData: {}
        };

        function openKtrPlan(csId) {
            ktrState.csId = csId;
            $('#ktr-modal-overlay').fadeIn(200);
            $('#ktr-modal-loading').show();
            $('#ktr-week-selector').hide();
            $('#ktr-plan-table-wrap').hide();
            $('#ktr-validation-msg').hide();

            $.ajax({
                url: '/admin/ktr/plan/' + csId,
                type: 'GET',
                success: function(data) {
                    $('#ktr-modal-loading').hide();
                    $('#ktr-modal-title').text(data.subject_name);
                    $('#ktr-total-load').text(data.total_acload);
                    ktrState.totalLoad = data.total_acload;
                    ktrState.trainingTypes = data.training_types;
                    ktrState.planData = {};

                    // Hafta tanlash ko'rsatish
                    $('#ktr-week-selector').show();
                    $('.ktr-week-btn').removeClass('active');

                    if (data.plan && data.plan.week_count) {
                        ktrState.planData = data.plan.plan_data || {};
                        selectWeekCount(data.plan.week_count, true);
                    }
                },
                error: function(xhr) {
                    $('#ktr-modal-loading').hide();
                    var msg = "Ma'lumotlarni yuklashda xatolik yuz berdi";
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        msg += ': ' + xhr.responseJSON.message;
                    }
                    alert(msg);
                }
            });
        }

        function closeKtrModal(event) {
            if (event && event.target !== document.getElementById('ktr-modal-overlay')) return;
            $('#ktr-modal-overlay').fadeOut(200);
        }

        function selectWeekCount(count, fromLoad) {
            ktrState.weekCount = count;
            $('.ktr-week-btn').removeClass('active');
            $('.ktr-week-btn[data-week="' + count + '"]').addClass('active');

            buildPlanTable(count, fromLoad);
            $('#ktr-plan-table-wrap').slideDown(200);
        }

        function buildPlanTable(weekCount, fromLoad) {
            var types = ktrState.trainingTypes;
            var typeCodes = Object.keys(types);

            // Thead
            var thead = '<tr><th class="ktr-th-week">Hafta</th>';
            typeCodes.forEach(function(code) {
                thead += '<th class="ktr-th-type">' + types[code].name + '<div class="ktr-th-hours">' + types[code].hours + ' soat</div></th>';
            });
            thead += '</tr>';
            $('#ktr-plan-thead').html(thead);

            // Tbody
            var tbody = '';
            for (var w = 1; w <= weekCount; w++) {
                tbody += '<tr>';
                tbody += '<td class="ktr-td-week">' + w + '-hafta</td>';
                typeCodes.forEach(function(code) {
                    var val = '';
                    if (fromLoad && ktrState.planData[w] && ktrState.planData[w][code] !== undefined) {
                        val = ktrState.planData[w][code];
                    }
                    tbody += '<td class="ktr-td-input"><input type="number" min="0" class="ktr-input" data-week="' + w + '" data-code="' + code + '" value="' + val + '" oninput="recalcKtr()"></td>';
                });
                tbody += '</tr>';
            }
            $('#ktr-plan-tbody').html(tbody);

            // Tfoot - Har bir ustun yig'indisi + jami yuklama
            var tfoot = '<tr class="ktr-tfoot-sum"><td class="ktr-td-week" style="font-weight:700;">Jami</td>';
            typeCodes.forEach(function(code) {
                tfoot += '<td class="ktr-td-sum" id="ktr-col-sum-' + code + '">0</td>';
            });
            tfoot += '</tr>';
            tfoot += '<tr class="ktr-tfoot-load"><td class="ktr-td-week" style="font-weight:700;">Yuklama</td>';
            typeCodes.forEach(function(code) {
                tfoot += '<td class="ktr-td-load">' + types[code].hours + '</td>';
            });
            tfoot += '</tr>';
            $('#ktr-plan-tfoot').html(tfoot);

            recalcKtr();
        }

        function recalcKtr() {
            var types = ktrState.trainingTypes;
            var typeCodes = Object.keys(types);
            var grandTotal = 0;
            var allMatch = true;

            typeCodes.forEach(function(code) {
                var colSum = 0;
                $('.ktr-input[data-code="' + code + '"]').each(function() {
                    colSum += parseInt($(this).val()) || 0;
                });
                $('#ktr-col-sum-' + code).text(colSum);
                grandTotal += colSum;

                var expected = types[code].hours;
                if (colSum !== expected) {
                    $('#ktr-col-sum-' + code).css('color', '#dc2626');
                    allMatch = false;
                } else {
                    $('#ktr-col-sum-' + code).css('color', '#059669');
                }
            });

            // Jami tekshirish
            var $msg = $('#ktr-validation-msg');
            if (grandTotal > 0 && grandTotal !== ktrState.totalLoad) {
                $msg.html('Jami soatlar mos kelmadi! Kiritilgan: <b>' + grandTotal + '</b>, Jami yuklama: <b>' + ktrState.totalLoad + '</b>')
                    .removeClass('ktr-msg-success').addClass('ktr-msg-error').show();
            } else if (grandTotal > 0 && grandTotal === ktrState.totalLoad && allMatch) {
                $msg.html('Barcha soatlar to\'g\'ri taqsimlangan!')
                    .removeClass('ktr-msg-error').addClass('ktr-msg-success').show();
            } else {
                $msg.hide();
            }
        }

        function saveKtrPlan() {
            var typeCodes = Object.keys(ktrState.trainingTypes);
            var planData = {};

            for (var w = 1; w <= ktrState.weekCount; w++) {
                planData[w] = {};
                typeCodes.forEach(function(code) {
                    var val = parseInt($('.ktr-input[data-week="' + w + '"][data-code="' + code + '"]').val()) || 0;
                    planData[w][code] = val;
                });
            }

            $('#ktr-save-btn').prop('disabled', true).text('Saqlanmoqda...');

            $.ajax({
                url: '/admin/ktr/plan/' + ktrState.csId,
                type: 'POST',
                data: JSON.stringify({
                    week_count: ktrState.weekCount,
                    plan_data: planData
                }),
                contentType: 'application/json',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                success: function(resp) {
                    $('#ktr-save-btn').prop('disabled', false).text('Saqlash');
                    if (resp.success) {
                        $('#ktr-validation-msg')
                            .html(resp.message)
                            .removeClass('ktr-msg-error').addClass('ktr-msg-success').show();
                        setTimeout(function() { $('#ktr-modal-overlay').fadeOut(200); }, 1000);
                    }
                },
                error: function(xhr) {
                    $('#ktr-save-btn').prop('disabled', false).text('Saqlash');
                    var msg = 'Xatolik yuz berdi';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        msg = xhr.responseJSON.message;
                    }
                    $('#ktr-validation-msg')
                        .html(msg)
                        .removeClass('ktr-msg-success').addClass('ktr-msg-error').show();
                }
            });
        }
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

        /* Subject link in table */
        .ktr-subject-link {
            color: #1d4ed8;
            font-weight: 500;
            font-size: 13px;
            text-decoration: none;
            cursor: pointer;
            transition: color 0.15s;
        }
        .ktr-subject-link:hover {
            color: #2563eb;
            text-decoration: underline;
        }

        /* Modal overlay */
        .ktr-modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.5);
            backdrop-filter: blur(4px);
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .ktr-modal {
            background: #fff;
            border-radius: 16px;
            width: 100%;
            max-width: 900px;
            max-height: 90vh;
            box-shadow: 0 25px 60px rgba(0,0,0,0.2);
            position: relative;
            display: flex;
            flex-direction: column;
        }
        .ktr-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 20px 24px 16px;
            border-bottom: 1px solid #e2e8f0;
        }
        .ktr-modal-title {
            font-size: 16px;
            font-weight: 700;
            color: #0f172a;
            margin: 0;
        }
        .ktr-modal-subtitle {
            font-size: 13px;
            color: #64748b;
            margin-top: 4px;
        }
        .ktr-modal-close {
            background: none;
            border: none;
            font-size: 24px;
            color: #94a3b8;
            cursor: pointer;
            padding: 0 4px;
            line-height: 1;
            transition: color 0.15s;
        }
        .ktr-modal-close:hover { color: #0f172a; }
        .ktr-modal-body {
            padding: 20px 24px;
            overflow-y: auto;
            flex: 1;
        }
        .ktr-modal-loading {
            position: absolute;
            inset: 0;
            background: rgba(255,255,255,0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 16px;
            z-index: 10;
        }

        /* Week selector */
        .ktr-week-buttons {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }
        .ktr-week-btn {
            width: 40px;
            height: 36px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            background: #fff;
            font-size: 14px;
            font-weight: 600;
            color: #475569;
            cursor: pointer;
            transition: all 0.15s;
        }
        .ktr-week-btn:hover {
            border-color: #3b82f6;
            color: #3b82f6;
            background: #eff6ff;
        }
        .ktr-week-btn.active {
            border-color: #3b82f6;
            background: #3b82f6;
            color: #fff;
        }

        /* Plan table */
        .ktr-plan-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
            font-size: 13px;
        }
        .ktr-plan-table thead th {
            background: #f8fafc;
            padding: 10px 8px;
            text-align: center;
            font-weight: 600;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            color: #475569;
            border: 1px solid #e2e8f0;
            position: sticky;
            top: 0;
            z-index: 5;
        }
        .ktr-th-hours {
            font-size: 10px;
            font-weight: 400;
            color: #94a3b8;
            margin-top: 2px;
        }
        .ktr-th-week { min-width: 90px; text-align: left; padding-left: 12px !important; }
        .ktr-td-week {
            padding: 6px 12px;
            font-weight: 500;
            color: #475569;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            white-space: nowrap;
        }
        .ktr-td-input {
            padding: 4px;
            border: 1px solid #e2e8f0;
            text-align: center;
        }
        .ktr-input {
            width: 100%;
            max-width: 70px;
            margin: 0 auto;
            display: block;
            padding: 6px 4px;
            border: 1.5px solid #e2e8f0;
            border-radius: 6px;
            text-align: center;
            font-size: 13px;
            font-weight: 500;
            color: #0f172a;
            outline: none;
            transition: border-color 0.15s;
            -moz-appearance: textfield;
        }
        .ktr-input::-webkit-outer-spin-button,
        .ktr-input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
        .ktr-input:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,0.1); }
        .ktr-td-sum {
            padding: 8px;
            text-align: center;
            font-weight: 700;
            font-size: 14px;
            border: 1px solid #e2e8f0;
            background: #f8fafc;
        }
        .ktr-td-load {
            padding: 8px;
            text-align: center;
            font-weight: 600;
            font-size: 13px;
            color: #64748b;
            border: 1px solid #e2e8f0;
            background: #fffbeb;
        }

        /* Buttons */
        .ktr-btn {
            padding: 8px 20px;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        .ktr-btn-primary {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: #fff;
            box-shadow: 0 2px 6px rgba(37, 99, 235, 0.3);
        }
        .ktr-btn-primary:hover { background: linear-gradient(135deg, #2563eb, #1d4ed8); transform: translateY(-1px); }
        .ktr-btn-primary:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }
        .ktr-btn-secondary {
            background: #f1f5f9;
            color: #475569;
        }
        .ktr-btn-secondary:hover { background: #e2e8f0; }

        /* Validation message */
        .ktr-validation-msg {
            margin-top: 12px;
            padding: 10px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
        }
        .ktr-msg-error {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }
        .ktr-msg-success {
            background: #f0fdf4;
            color: #16a34a;
            border: 1px solid #bbf7d0;
        }
    </style>
</x-app-layout>
