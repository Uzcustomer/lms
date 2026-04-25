<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<link href="/css/scroll-calendar.css" rel="stylesheet" />
<script src="/js/scroll-calendar.js"></script>

<script>
    let currentSort = 'avg_grade';
    let currentDirection = 'desc';
    let currentPage = 1;

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

    function getFilters() {
        return {
            education_type: $('#education_type').val() || '',
            faculty: $('#faculty').val() || '',
            specialty: $('#specialty').val() || '',
            date_from: $('#date_from').val() || '',
            date_to: $('#date_to').val() || '',
            level_code: $('#level_code').val() || '',
            semester_code: $('#semester_code').val() || '',
            group: $('#group').val() || '',
            department: $('#department').val() || '',
            subject: $('#subject').val() || '',
            current_semester: document.getElementById('current-semester-toggle').classList.contains('active') ? '1' : '0',
            per_page: $('#per_page').val() || 50,
            sort: currentSort,
            direction: currentDirection,
        };
    }

    function loadReport(page) {
        currentPage = page || 1;
        var params = getFilters();
        params.page = currentPage;

        $('#empty-state').hide();
        $('#table-area').hide();
        $('#loading-state').show();
        $('#btn-calculate').prop('disabled', true).css('opacity', '0.6');

        var startTime = performance.now();

        $.ajax({
            url: '{{ route("teacher.reports.jn.data") }}',
            type: 'GET',
            data: params,
            timeout: 120000,
            success: function(res) {
                var elapsed = ((performance.now() - startTime) / 1000).toFixed(1);
                $('#loading-state').hide();
                $('#btn-calculate').prop('disabled', false).css('opacity', '1');

                if (!res.data || res.data.length === 0) {
                    $('#empty-state').show().find('p:first').text("Ma'lumot topilmadi");
                    $('#table-area').hide();
                    $('#btn-excel').prop('disabled', true).css('opacity', '0.5');
                    return;
                }

                $('#total-badge').text('Jami: ' + res.total);
                $('#time-badge').text(elapsed + ' soniyada hisoblandi');
                renderTable(res.data);
                renderPagination(res);
                $('#table-area').show();
                $('#btn-excel').prop('disabled', false).css('opacity', '1');
            },
            error: function(xhr) {
                $('#loading-state').hide();
                $('#btn-calculate').prop('disabled', false).css('opacity', '1');
                var errMsg = 'Xatolik yuz berdi.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errMsg += ' ' + xhr.responseJSON.message;
                } else if (xhr.status === 500) {
                    errMsg += ' Server xatosi (500). Filtrlarni o\'zgartiring.';
                } else if (xhr.status === 0) {
                    errMsg += ' Server javob bermadi (timeout).';
                }
                $('#empty-state').show().find('p:first').text(errMsg);
            }
        });
    }

    function gradeClass(val, minLimit) {
        minLimit = minLimit || 60;
        if (val < minLimit) return 'badge-grade-red';
        if (val < 75) return 'badge-grade-yellow';
        return 'badge-grade-green';
    }

    function esc(s) { return $('<span>').text(s || '-').html(); }

    function renderTable(data) {
        var html = '';
        var journalBase = '{{ url("/admin/journal/show") }}';
        for (var i = 0; i < data.length; i++) {
            var r = data[i];
            var journalUrl = journalBase + '/' + encodeURIComponent(r.group_id) + '/' + encodeURIComponent(r.subject_id) + '/' + encodeURIComponent(r.semester_code);
            html += '<tr class="journal-row">';
            html += '<td class="td-num">' + r.row_num + '</td>';
            html += '<td><span class="text-cell" style="font-weight:700;color:#0f172a;">' + esc(r.full_name) + '</span></td>';
            html += '<td><span class="text-cell text-emerald">' + esc(r.department_name) + '</span></td>';
            html += '<td><span class="text-cell text-cyan">' + esc(r.specialty_name) + '</span></td>';
            html += '<td><span class="badge badge-violet">' + esc(r.level_name) + '</span></td>';
            html += '<td><span class="badge badge-teal">' + esc(r.semester_name) + '</span></td>';
            html += '<td><span class="badge badge-indigo">' + esc(r.group_name) + '</span></td>';
            html += '<td><span class="text-cell text-subject">' + esc(r.subject_name) + '</span></td>';
            html += '<td><span class="badge ' + gradeClass(r.avg_grade, r.minimum_limit) + '">' + r.avg_grade + '</span></td>';
            html += '<td style="text-align:center;font-weight:600;color:#475569;">' + r.grades_count + '</td>';
            html += '<td style="text-align:center;"><a href="' + journalUrl + '" target="_blank" class="journal-link">Ko\'rish</a></td>';
            html += '</tr>';
        }
        $('#table-body').html(html);
    }

    function downloadExcel() {
        var params = getFilters();
        params.export = 'excel';
        var query = $.param(params);
        window.location.href = '{{ route("teacher.reports.jn.data") }}?' + query;
    }

    function renderPagination(res) {
        if (res.last_page <= 1) { $('#pagination-area').html(''); return; }
        var html = '';
        if (res.current_page > 1)
            html += '<button class="pg-btn" onclick="loadReport(' + (res.current_page - 1) + ')">&laquo; Oldingi</button>';
        for (var p = 1; p <= res.last_page; p++) {
            if (p === 1 || p === res.last_page || (p >= res.current_page - 2 && p <= res.current_page + 2)) {
                html += '<button class="pg-btn' + (p === res.current_page ? ' pg-active' : '') + '" onclick="loadReport(' + p + ')">' + p + '</button>';
            } else if (p === res.current_page - 3 || p === res.current_page + 3) {
                html += '<span style="color:#94a3b8;padding:0 4px;">...</span>';
            }
        }
        if (res.current_page < res.last_page)
            html += '<button class="pg-btn" onclick="loadReport(' + (res.current_page + 1) + ')">Keyingi &raquo;</button>';
        $('#pagination-area').html(html);
    }

    $(document).ready(function() {
        $(document).on('click', '.sort-link', function(e) {
            e.preventDefault();
            var col = $(this).data('sort');
            if (currentSort === col) {
                currentDirection = currentDirection === 'asc' ? 'desc' : 'asc';
            } else {
                currentSort = col;
                currentDirection = 'asc';
            }
            $('.sort-link .sort-icon').removeClass('active').html('&#9650;&#9660;');
            $(this).find('.sort-icon').addClass('active').html(currentDirection === 'asc' ? '&#9650;' : '&#9660;');
            loadReport(1);
        });

        new ScrollCalendar('date_from');
        new ScrollCalendar('date_to');

        $('.select2').each(function() {
            $(this).select2({ theme: 'classic', width: '100%', allowClear: true, placeholder: $(this).find('option:first').text(), matcher: fuzzyMatcher })
            .on('select2:open', function() { setTimeout(function() { var s = document.querySelector('.select2-container--open .select2-search__field'); if(s) s.focus(); }, 10); });
        });

        function fp() {
            return {
                education_type: $('#education_type').val() || '',
                faculty_id: $('#faculty').val() || '',
                specialty_id: $('#specialty').val() || '',
                department_id: $('#department').val() || '',
                level_code: $('#level_code').val() || '',
                semester_code: $('#semester_code').val() || '',
                subject_id: $('#subject').val() || '',
                current_semester: document.getElementById('current-semester-toggle').classList.contains('active') ? '1' : '0'
            };
        }
        function rd(el) { $(el).empty().append('<option value="">Barchasi</option>'); }
        function pd(url, p, el, cb) { $.get(url, p, function(d) { $.each(d, function(k,v){ $(el).append('<option value="'+k+'">'+v+'</option>'); }); if(cb) cb(); }); }
        function pdu(url, p, el, cb) { $.get(url, p, function(d) { var u={}; $.each(d, function(k,v){ if(!u[v]) u[v]=k; }); $.each(u, function(n,k){ $(el).append('<option value="'+k+'">'+n+'</option>'); }); if(cb) cb(); }); }

        function rSpec() { rd('#specialty'); pdu('{{ route("teacher.reports.jn.specialties") }}', fp(), '#specialty'); }
        function rSubj() { rd('#subject'); pdu('{{ route("teacher.reports.jn.subjects") }}', fp(), '#subject'); }
        function rGrp() { rd('#group'); pd('{{ route("teacher.reports.jn.groups") }}', fp(), '#group'); }

        $('#education_type').change(function() { rSpec(); rSubj(); rGrp(); });
        $('#faculty').change(function() { rSpec(); rSubj(); rGrp(); });
        $('#department').change(function() { rSubj(); rGrp(); });
        $('#specialty').change(function() { rGrp(); });
        $('#level_code').change(function() { var lc=$(this).val(); rd('#semester_code'); if(lc) pd('{{ route("teacher.reports.jn.semesters") }}', {level_code:lc}, '#semester_code'); rSubj(); rGrp(); });
        $('#semester_code').change(function() { rSubj(); rGrp(); });
        $('#subject').change(function() { rGrp(); });

        pdu('{{ route("teacher.reports.jn.specialties") }}', fp(), '#specialty');
        pd('{{ route("teacher.reports.jn.level-codes") }}', {}, '#level_code');
        pd('{{ route("teacher.reports.jn.semesters") }}', {}, '#semester_code');
        pdu('{{ route("teacher.reports.jn.subjects") }}', fp(), '#subject');
        pd('{{ route("teacher.reports.jn.groups") }}', fp(), '#group');
    });
</script>
