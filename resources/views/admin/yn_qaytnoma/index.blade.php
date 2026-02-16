<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            YN qaytnomasi (Test markazi)
        </h2>
    </x-slot>

    <div class="py-4">
        <div class="max-w-full mx-auto sm:px-4 lg:px-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">

                <!-- Filters -->
                <div class="filter-container">
                    <div class="filter-row">
                        <div class="filter-item" style="min-width: 160px;">
                            <label class="filter-label">
                                <span class="fl-dot" style="background:#3b82f6;"></span> Ta'lim turi
                            </label>
                            <select id="education_type" class="select2" style="width: 100%;">
                                <option value="">Barchasi</option>
                                @foreach($educationTypes as $type)
                                    <option value="{{ $type->education_type_code }}">{{ $type->education_type_name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="filter-item" style="flex: 1; min-width: 200px;">
                            <label class="filter-label">
                                <span class="fl-dot" style="background:#10b981;"></span> Fakultet
                            </label>
                            <select id="faculty" class="select2" style="width: 100%;">
                                <option value="">Barchasi</option>
                                @foreach($faculties as $faculty)
                                    <option value="{{ $faculty->id }}">{{ $faculty->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="filter-item" style="flex: 1; min-width: 240px;">
                            <label class="filter-label">
                                <span class="fl-dot" style="background:#06b6d4;"></span> Yo'nalish
                            </label>
                            <select id="specialty" class="select2" style="width: 100%;">
                                <option value="">Barchasi</option>
                            </select>
                        </div>
                    </div>

                    <div class="filter-row">
                        <div class="filter-item" style="min-width: 140px;">
                            <label class="filter-label">
                                <span class="fl-dot" style="background:#8b5cf6;"></span> Kurs
                            </label>
                            <select id="level_code" class="select2" style="width: 100%;">
                                <option value="">Barchasi</option>
                            </select>
                        </div>

                        <div class="filter-item" style="min-width: 150px;">
                            <label class="filter-label">
                                <span class="fl-dot" style="background:#14b8a6;"></span> Semestr
                            </label>
                            <select id="semester_code" class="select2" style="width: 100%;">
                                <option value="">Barchasi</option>
                            </select>
                        </div>

                        <div class="filter-item" style="flex: 1; min-width: 240px;">
                            <label class="filter-label">
                                <span class="fl-dot" style="background:#f59e0b;"></span> Fan
                            </label>
                            <select id="subject" class="select2" style="width: 100%;">
                                <option value="">Barchasi</option>
                            </select>
                        </div>
                    </div>

                    <div class="filter-row">
                        <div class="filter-item" style="min-width: 170px;">
                            <label class="filter-label">
                                <span class="fl-dot" style="background:#1a3268;"></span> Guruh
                            </label>
                            <select id="group" class="select2" style="width: 100%;">
                                <option value="">Barchasi</option>
                            </select>
                        </div>

                        <div class="filter-item" style="display: flex; align-items: flex-end;">
                            <label class="filter-label">&nbsp;</label>
                            <button type="button" id="btn-show" class="btn-show" onclick="loadData()">
                                <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                </svg>
                                Ko'rsatish
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Action Bar -->
                <div class="action-bar">
                    <div class="action-left">
                        <label class="select-all-label">
                            <input type="checkbox" id="select-all" onchange="toggleSelectAll(this)">
                            <span>Barchasini tanlash</span>
                        </label>
                        <span id="selection-info" class="sel-info">
                            <span id="selected-count">0</span> ta tanlangan
                        </span>
                    </div>
                    <div class="action-right">
                        <button type="button" id="btn-ruxsatnoma" class="btn-ruxsatnoma" onclick="generateRuxsatnoma()" disabled>
                            <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            Ruxsatnoma chiqarish
                        </button>
                    </div>
                </div>

                <!-- Loading -->
                <div id="loading" style="display:none; padding: 40px; text-align: center;">
                    <svg class="animate-spin" style="height: 32px; width: 32px; margin: 0 auto 12px; color: #2b5ea7;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle style="opacity: 0.25;" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path style="opacity: 0.75;" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <p style="color: #94a3b8; font-size: 14px;">Yuklanmoqda...</p>
                </div>

                <!-- Table -->
                <div id="table-container" style="max-height: calc(100vh - 340px); overflow-y: auto; overflow-x: auto;">
                    <div id="empty-state" style="padding: 60px 20px; text-align: center;">
                        <svg style="width: 48px; height: 48px; margin: 0 auto 12px; color: #cbd5e1;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                        </svg>
                        <p style="color: #94a3b8; font-size: 14px;">YN yuborilgan guruhlarni ko'rish uchun filtrlarni tanlang va "Ko'rsatish" tugmasini bosing.</p>
                    </div>

                    <table id="groups-table" class="yn-table" style="display: none;">
                        <thead>
                            <tr>
                                <th style="width: 40px; text-align: center;">
                                    <input type="checkbox" id="select-all-header" onchange="toggleSelectAll(this)">
                                </th>
                                <th class="th-num">#</th>
                                <th>Ta'lim turi</th>
                                <th>Fakultet</th>
                                <th>Yo'nalish</th>
                                <th>Kurs</th>
                                <th>Semestr</th>
                                <th>Guruh</th>
                                <th>Fanlar soni</th>
                                <th>Yuborilgan sana</th>
                            </tr>
                        </thead>
                        <tbody id="groups-tbody">
                        </tbody>
                    </table>

                    <div id="no-data" style="display: none; padding: 60px 20px; text-align: center;">
                        <svg style="width: 48px; height: 48px; margin: 0 auto 12px; color: #cbd5e1;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                        </svg>
                        <p style="color: #94a3b8; font-size: 14px;">Tanlangan filtrlar bo'yicha YN yuborilgan guruhlar topilmadi.</p>
                    </div>
                </div>

                <!-- Total info -->
                <div id="total-bar" style="display: none; padding: 12px 20px; border-top: 1px solid #e2e8f0; background: #f8fafc;">
                    <span style="font-size: 13px; color: #64748b;">Jami: <strong id="total-count">0</strong> ta guruh</span>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
        const routePrefix = @json($routePrefix);

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

        let allGroupsData = [];

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

            initializeFilters();

            // Cascading filter events
            $('#education_type').change(function () { refreshSpecialties(); refreshLevelCodes(); refreshSemesters(); refreshSubjects(); refreshGroups(); });
            $('#faculty').change(function () { refreshSpecialties(); refreshLevelCodes(); refreshSemesters(); refreshSubjects(); refreshGroups(); });
            $('#specialty').change(function () { refreshLevelCodes(); refreshSemesters(); refreshSubjects(); refreshGroups(); });
            $('#level_code').change(function () { refreshSemesters(); refreshSubjects(); refreshGroups(); });
            $('#semester_code').change(function () { refreshSubjects(); refreshGroups(); });
            $('#subject').change(function () { refreshGroups(); });

            // Load data by default
            loadData();
        });

        function resetDropdown(el, ph) {
            $(el).empty().append('<option value="">' + ph + '</option>').trigger('change.select2');
        }

        function populateDropdown(url, params, element, callback) {
            $.ajax({ url: url, type: 'GET', data: params, success: function (data) {
                $.each(data, function (k, v) { $(element).append('<option value="' + k + '">' + v + '</option>'); });
                if (callback) callback(data);
            }});
        }

        function getFilterParams() {
            return {
                education_type: $('#education_type').val() || '',
                faculty_id: $('#faculty').val() || '',
                specialty_id: $('#specialty').val() || '',
                level_code: $('#level_code').val() || '',
                semester_code: $('#semester_code').val() || '',
                subject_id: $('#subject').val() || '',
                group_id: $('#group').val() || '',
            };
        }

        function refreshSpecialties() {
            resetDropdown('#specialty', 'Barchasi');
            populateDropdown('{{ route($routePrefix . ".yn-qaytnoma.get-specialties") }}', getFilterParams(), '#specialty');
        }
        function refreshLevelCodes() {
            resetDropdown('#level_code', 'Barchasi');
            populateDropdown('{{ route($routePrefix . ".yn-qaytnoma.get-level-codes") }}', getFilterParams(), '#level_code');
        }
        function refreshSemesters() {
            resetDropdown('#semester_code', 'Barchasi');
            populateDropdown('{{ route($routePrefix . ".yn-qaytnoma.get-semesters") }}', getFilterParams(), '#semester_code');
        }
        function refreshSubjects() {
            resetDropdown('#subject', 'Barchasi');
            populateDropdown('{{ route($routePrefix . ".yn-qaytnoma.get-subjects") }}', getFilterParams(), '#subject');
        }
        function refreshGroups() {
            resetDropdown('#group', 'Barchasi');
            populateDropdown('{{ route($routePrefix . ".yn-qaytnoma.get-groups") }}', getFilterParams(), '#group');
        }

        function initializeFilters() {
            var p = getFilterParams();
            populateDropdown('{{ route($routePrefix . ".yn-qaytnoma.get-specialties") }}', p, '#specialty');
            populateDropdown('{{ route($routePrefix . ".yn-qaytnoma.get-level-codes") }}', p, '#level_code');
            populateDropdown('{{ route($routePrefix . ".yn-qaytnoma.get-semesters") }}', p, '#semester_code');
            populateDropdown('{{ route($routePrefix . ".yn-qaytnoma.get-subjects") }}', p, '#subject');
            populateDropdown('{{ route($routePrefix . ".yn-qaytnoma.get-groups") }}', p, '#group');
        }

        function loadData() {
            var params = getFilterParams();

            $('#loading').show();
            $('#empty-state').hide();
            $('#groups-table').hide();
            $('#no-data').hide();
            $('#total-bar').hide();

            $.ajax({
                url: '{{ route($routePrefix . ".yn-qaytnoma.data") }}',
                type: 'GET',
                data: params,
                success: function (data) {
                    $('#loading').hide();
                    allGroupsData = data;

                    if (data.length === 0) {
                        $('#no-data').show();
                        return;
                    }

                    renderTable(data);
                    $('#groups-table').show();
                    $('#total-bar').show();
                    $('#total-count').text(data.length);
                },
                error: function () {
                    $('#loading').hide();
                    $('#no-data').show();
                }
            });
        }

        function renderTable(data) {
            var tbody = $('#groups-tbody');
            tbody.empty();

            data.forEach(function (row, index) {
                var submittedDate = row.last_submitted_at ? new Date(row.last_submitted_at).toLocaleDateString('uz-UZ') : '-';
                var tr = '<tr>' +
                    '<td style="text-align:center;">' +
                        '<input type="checkbox" class="group-checkbox" ' +
                            'data-group-hemis-id="' + row.group_hemis_id + '" ' +
                            'data-semester-code="' + row.semester_code + '" ' +
                            'data-group-name="' + (row.group_name || '') + '" ' +
                            'onchange="updateSelection()">' +
                    '</td>' +
                    '<td class="td-num">' + (index + 1) + '</td>' +
                    '<td><span class="badge badge-blue">' + (row.education_type_name || '-') + '</span></td>' +
                    '<td><span class="text-cell text-emerald">' + (row.faculty_name || '-') + '</span></td>' +
                    '<td><span class="text-cell text-cyan">' + (row.specialty_name || '-') + '</span></td>' +
                    '<td><span class="badge badge-violet">' + (row.level_name || '-') + '</span></td>' +
                    '<td><span class="badge badge-teal">' + (row.semester_name || '-') + '</span></td>' +
                    '<td><span class="badge badge-indigo">' + (row.group_name || '-') + '</span></td>' +
                    '<td><span class="badge badge-subject">' + (row.subject_count || 0) + ' ta fan</span></td>' +
                    '<td><span class="text-cell text-date">' + submittedDate + '</span></td>' +
                    '</tr>';
                tbody.append(tr);
            });
        }

        function toggleSelectAll(el) {
            var checked = el.checked;
            $('.group-checkbox').prop('checked', checked);
            $('#select-all').prop('checked', checked);
            $('#select-all-header').prop('checked', checked);
            updateSelection();
        }

        function updateSelection() {
            var count = $('.group-checkbox:checked').length;
            var total = $('.group-checkbox').length;

            $('#selected-count').text(count);
            $('#btn-ruxsatnoma').prop('disabled', count === 0);
            $('#select-all').prop('checked', count === total && total > 0);
            $('#select-all-header').prop('checked', count === total && total > 0);
        }

        function generateRuxsatnoma() {
            var selected = [];
            $('.group-checkbox:checked').each(function () {
                selected.push({
                    group_hemis_id: $(this).data('group-hemis-id'),
                    semester_code: String($(this).data('semester-code')),
                });
            });

            if (selected.length === 0) {
                alert('Kamida bitta guruhni tanlang');
                return;
            }

            var btn = $('#btn-ruxsatnoma');
            var originalText = btn.html();
            btn.prop('disabled', true).html(
                '<svg class="animate-spin" style="height:14px;width:14px;display:inline-block;margin-right:4px;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">' +
                '<circle style="opacity:0.25;" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>' +
                '<path style="opacity:0.75;" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>' +
                '</svg> Yuklanmoqda...'
            );

            fetch('{{ route($routePrefix . ".yn-qaytnoma.generate-ruxsatnoma") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/octet-stream',
                },
                body: JSON.stringify({ groups: selected })
            })
            .then(function (response) {
                if (!response.ok) throw new Error('Server xatosi');

                var contentDisposition = response.headers.get('Content-Disposition');
                var fileName = 'ruxsatnoma.docx';
                if (contentDisposition) {
                    var match = contentDisposition.match(/filename="?([^";\n]+)"?/);
                    if (match && match[1]) fileName = match[1];
                }

                return response.blob().then(function (blob) {
                    return { blob: blob, fileName: fileName };
                });
            })
            .then(function (result) {
                var url = window.URL.createObjectURL(result.blob);
                var a = document.createElement('a');
                a.href = url;
                a.download = result.fileName;
                document.body.appendChild(a);
                a.click();
                a.remove();
                window.URL.revokeObjectURL(url);
            })
            .catch(function (err) {
                alert('Xatolik yuz berdi: ' + err.message);
            })
            .finally(function () {
                btn.prop('disabled', false).html(originalText);
                updateSelection();
            });
        }
    </script>

    <style>
        /* ===== Filter Container ===== */
        .filter-container {
            padding: 16px 20px 12px;
            background: linear-gradient(135deg, #f0f4f8 0%, #e8edf5 100%);
            border-bottom: 2px solid #dbe4ef;
        }
        .filter-row {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 10px;
            align-items: flex-end;
        }
        .filter-row:last-child { margin-bottom: 0; }

        /* ===== Filter Labels ===== */
        .filter-label {
            display: flex;
            align-items: center;
            gap: 5px;
            margin-bottom: 4px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #475569;
        }
        .fl-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            display: inline-block;
            flex-shrink: 0;
        }

        /* ===== Select2 ===== */
        .select2-container--classic .select2-selection--single {
            height: 36px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            background: #ffffff;
            transition: all 0.2s;
            box-shadow: 0 1px 2px rgba(0,0,0,0.04);
        }
        .select2-container--classic .select2-selection--single:hover {
            border-color: #2b5ea7;
            box-shadow: 0 0 0 2px rgba(43,94,167,0.1);
        }
        .select2-container--classic .select2-selection--single .select2-selection__rendered {
            line-height: 34px;
            padding-left: 10px;
            padding-right: 52px;
            color: #1e293b;
            font-size: 0.8rem;
            font-weight: 500;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .select2-container--classic .select2-selection--single .select2-selection__arrow {
            height: 34px;
            width: 22px;
            background: transparent;
            border-left: none;
            right: 0;
        }
        .select2-container--classic .select2-selection--single .select2-selection__clear {
            position: absolute;
            right: 22px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 16px;
            font-weight: bold;
            color: #94a3b8;
            cursor: pointer;
            padding: 2px 6px;
            z-index: 2;
            background: #ffffff;
            border-radius: 50%;
            line-height: 1;
            transition: all 0.15s;
        }
        .select2-container--classic .select2-selection--single .select2-selection__clear:hover {
            color: #ffffff;
            background: #ef4444;
        }
        .select2-dropdown {
            font-size: 0.8rem;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
        }
        .select2-container--classic .select2-results__option--highlighted {
            background-color: #2b5ea7;
        }

        /* ===== Ko'rsatish Button ===== */
        .btn-show {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 20px;
            background: linear-gradient(135deg, #2b5ea7, #3b7ddb);
            color: #ffffff;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 2px 6px rgba(43,94,167,0.3);
            white-space: nowrap;
            height: 36px;
        }
        .btn-show:hover {
            background: linear-gradient(135deg, #1e4d8c, #2b5ea7);
            box-shadow: 0 4px 12px rgba(43,94,167,0.4);
            transform: translateY(-1px);
        }

        /* ===== Action Bar ===== */
        .action-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 20px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
        }
        .action-left {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .action-right {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .select-all-label {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            color: #475569;
            cursor: pointer;
            user-select: none;
        }
        .select-all-label input[type="checkbox"] {
            width: 16px;
            height: 16px;
            accent-color: #2b5ea7;
        }
        .sel-info {
            font-size: 13px;
            color: #64748b;
            font-weight: 500;
        }

        /* ===== Ruxsatnoma Button ===== */
        .btn-ruxsatnoma {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 18px;
            background: linear-gradient(135deg, #059669, #10b981);
            color: #ffffff;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 2px 6px rgba(5,150,105,0.3);
            white-space: nowrap;
        }
        .btn-ruxsatnoma:hover:not(:disabled) {
            background: linear-gradient(135deg, #047857, #059669);
            box-shadow: 0 4px 12px rgba(5,150,105,0.4);
            transform: translateY(-1px);
        }
        .btn-ruxsatnoma:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        /* ===== Table ===== */
        .yn-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 13px;
        }
        .yn-table thead {
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .yn-table thead tr {
            background: linear-gradient(135deg, #e8edf5 0%, #dbe4ef 50%, #d1d9e6 100%);
        }
        .yn-table th {
            padding: 14px 12px;
            text-align: left;
            font-weight: 600;
            font-size: 11.5px;
            color: #334155;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            white-space: nowrap;
            border-bottom: 2px solid #cbd5e1;
        }
        .yn-table th.th-num {
            padding: 14px 12px 14px 8px;
            width: 44px;
        }
        .yn-table th input[type="checkbox"] {
            width: 16px;
            height: 16px;
            accent-color: #2b5ea7;
        }
        .yn-table tbody tr {
            transition: all 0.15s ease;
            border-bottom: 1px solid #f1f5f9;
        }
        .yn-table tbody tr:nth-child(even) { background-color: #f8fafc; }
        .yn-table tbody tr:nth-child(odd) { background-color: #ffffff; }
        .yn-table tbody tr:hover {
            background-color: #eff6ff !important;
            box-shadow: inset 4px 0 0 #2b5ea7;
        }
        .yn-table td {
            padding: 10px 12px;
            vertical-align: middle;
            line-height: 1.4;
        }
        .yn-table td input[type="checkbox"] {
            width: 16px;
            height: 16px;
            accent-color: #2b5ea7;
            cursor: pointer;
        }
        .td-num {
            padding-left: 8px !important;
            font-weight: 700;
            color: #2b5ea7;
            font-size: 13px;
        }

        /* ===== Badges ===== */
        .badge {
            display: inline-block;
            padding: 3px 9px;
            border-radius: 6px;
            font-size: 11.5px;
            font-weight: 600;
            line-height: 1.4;
        }
        .badge-blue { background: #dbeafe; color: #1e40af; border: 1px solid #bfdbfe; }
        .badge-violet { background: #ede9fe; color: #5b21b6; border: 1px solid #ddd6fe; white-space: nowrap; }
        .badge-teal { background: #ccfbf1; color: #0f766e; border: 1px solid #99f6e4; white-space: nowrap; }
        .badge-indigo {
            background: linear-gradient(135deg, #1a3268, #2b5ea7);
            color: #ffffff;
            border: none;
            white-space: nowrap;
        }
        .badge-subject {
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #fde68a;
        }

        /* ===== Text Cells ===== */
        .text-cell {
            font-size: 12.5px;
            font-weight: 500;
            line-height: 1.35;
            display: block;
        }
        .text-emerald { color: #047857; }
        .text-cyan { color: #0e7490; max-width: 220px; white-space: normal; word-break: break-word; }
        .text-date { color: #64748b; font-size: 12px; }

        /* ===== Animations ===== */
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .animate-spin { animation: spin 1s linear infinite; }
    </style>
</x-app-layout>
