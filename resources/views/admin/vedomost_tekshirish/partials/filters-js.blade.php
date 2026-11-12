{{-- Filtrlar skriptlari: jQuery, select2, kalendar, bog'langan ro'yxatlar, collectParams() — sahifa o'zi doSearch() ni beradi --}}
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <link href="/css/scroll-calendar.css" rel="stylesheet" />
    <script src="/js/scroll-calendar.js"></script>

    <script>
        // cbData[id] = [{value, label}] — full list for each checkbox dropdown
        var cbData = { groups: [], subjects: [] };

        /* ===== Checkbox dropdown helpers ===== */
        function toggleCb(id) {
            var panel = document.getElementById(id+'-panel');
            var isOpen = panel.style.display !== 'none';
            // close all
            ['groups','subjects'].forEach(function(x){ document.getElementById(x+'-panel').style.display='none'; });
            if (!isOpen) {
                // Position using fixed coords to escape overflow:hidden
                var btn = document.getElementById(id+'-btn');
                var rect = btn.getBoundingClientRect();
                panel.style.top = (rect.bottom + window.scrollY + 4) + 'px';
                panel.style.left = rect.left + 'px';
                panel.style.width = Math.max(rect.width, 260) + 'px';
                panel.style.display = 'block';
                var srch = document.getElementById(id+'-search');
                if (srch) { srch.value=''; filterCb(id); srch.focus(); }
            }
        }
        function filterCb(id) {
            var q = (document.getElementById(id+'-search').value||'').toLowerCase().replace(/\s+/g,'');
            var list = document.getElementById(id+'-list');
            var items = list.querySelectorAll('.cb-item');
            items.forEach(function(el){
                var label = (el.getAttribute('data-label')||'').toLowerCase().replace(/\s+/g,'');
                el.style.display = label.indexOf(q) > -1 ? '' : 'none';
            });
            updateCbAllState(id);
        }
        function toggleAllCb(id, cb) {
            var list = document.getElementById(id+'-list');
            list.querySelectorAll('.cb-item-cb:not([style*="display: none"]) input, .cb-item:not([style*="display: none"]) input').forEach(function(inp){
                inp.checked = cb.checked;
            });
            updateCbLabel(id);
        }
        function updateCbAllState(id) {
            var list = document.getElementById(id+'-list');
            var visible = list.querySelectorAll('.cb-item:not([style*="display: none"]) input');
            var checked = 0;
            visible.forEach(function(inp){ if(inp.checked) checked++; });
            var allCb = document.getElementById(id+'-all');
            if (allCb) {
                allCb.checked = visible.length > 0 && checked === visible.length;
                allCb.indeterminate = checked > 0 && checked < visible.length;
            }
        }
        function onCbItemChange(id) { updateCbAllState(id); updateCbLabel(id); }
        function updateCbLabel(id) {
            var vals = getCbValues(id);
            var label = document.getElementById(id+'-label');
            var count = document.getElementById(id+'-count');
            if (vals.length === 0) {
                label.textContent = 'Tanlang...';
                label.style.color = '#94a3b8';
                if (count) count.textContent = '';
            } else {
                label.textContent = vals.length + ' ta tanlangan';
                label.style.color = '#1e40af';
                if (count) count.textContent = '(' + vals.length + ' ta)';
            }
        }
        function getCbValues(id) {
            var list = document.getElementById(id+'-list');
            var vals = [];
            list.querySelectorAll('input[type=checkbox]:checked').forEach(function(inp){ vals.push(inp.value); });
            return vals;
        }
        function loadCbItems(id, items, keepVals) {
            keepVals = keepVals || [];
            cbData[id] = items;
            var list = document.getElementById(id+'-list');
            var html = '';
            items.forEach(function(it) {
                var checked = keepVals.indexOf(it.value) > -1 ? ' checked' : '';
                html += '<label class="cb-item" data-label="'+escAttr(it.label)+'">'
                      + '<input type="checkbox" value="'+escAttr(it.value)+'"'+checked+' onchange="onCbItemChange(\''+id+'\')">'
                      + '<span>'+escHtml(it.label)+'</span></label>';
            });
            list.innerHTML = html;
            updateCbAllState(id);
            updateCbLabel(id);
        }
        function escAttr(s){ return (s||'').replace(/"/g,'&quot;').replace(/</g,'&lt;'); }
        function escHtml(s){ return (s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

        // Close panels when clicking outside
        document.addEventListener('click', function(e){
            ['groups','subjects'].forEach(function(id){
                var wrap = document.getElementById(id+'-wrap');
                var panel = document.getElementById(id+'-panel');
                if (wrap && panel && !wrap.contains(e.target) && !panel.contains(e.target)) {
                    panel.style.display='none';
                }
            });
        });

        function stripSpecialChars(s) { return s.replace(/[\/\(\),\-\.\s]/g,'').toLowerCase(); }
        function fuzzyMatcher(params, data) {
            if ($.trim(params.term) === '') return data;
            if (typeof data.text === 'undefined') return null;
            if (stripSpecialChars(data.text).indexOf(stripSpecialChars(params.term)) > -1) return $.extend({}, data, true);
            if (data.text.toLowerCase().indexOf(params.term.toLowerCase()) > -1) return $.extend({}, data, true);
            return null;
        }
        function toggleSemester() {
            document.getElementById('current-semester-toggle').classList.toggle('active');
            if (window.reloadVedomostDependentFilters) {
                window.reloadVedomostDependentFilters({
                    keepSpecialty: false,
                    keepLevel: false,
                    keepSemester: false
                });
            }
        }

        $(document).ready(function () {
            // Single selects
            $('.select2').each(function () {
                $(this).select2({ theme:'classic', width:'100%', allowClear:true, placeholder:$(this).find('option:first').text(), matcher:fuzzyMatcher })
                .on('select2:open', function () { setTimeout(function(){ var s=document.querySelector('.select2-container--open .select2-search__field'); if(s)s.focus(); },10); });
            });

            // Ta'lim turi — by default Bakalavr
            @if($defaultEducationType)
            $('#education_type').val('{{ $defaultEducationType }}').trigger('change');
            @endif

            // Calendar
            new ScrollCalendar('yn_date_from');
            new ScrollCalendar('yn_date_to');

            // Cascading filters
            function fp() {
                var df=document.getElementById('dekan_faculty_id');
                return {
                    education_type: $('#education_type').val()||'',
                    faculty_id: df ? df.value : ($('#faculty').val()||''),
                    specialty_id: $('#specialty').val()||'',
                    department_id: $('#department').val()||'',
                    level_code: $('#level_code').val()||'',
                    semester_code: $('#semester_code').val()||'',
                    current_semester: document.getElementById('current-semester-toggle').classList.contains('active') ? '1':'0',
                };
            }
            function rd(el){
                $(el).empty().append('<option value="">Barchasi</option>').val('').trigger('change.select2');
            }
            function pd(url,p,el,selectedValue,cb){
                var $el=$(el);
                $.get(url,p,function(d){
                    rd(el);
                    $.each(d,function(k,v){ $el.append('<option value="'+k+'">'+v+'</option>'); });
                    if(selectedValue && $el.find('option[value="'+selectedValue+'"]').length){
                        $el.val(selectedValue);
                    }
                    $el.trigger('change.select2');
                    if(cb)cb();
                });
            }
            function pdu(url,p,el,selectedValue,cb){
                var $el=$(el);
                $.get(url,p,function(d){
                    rd(el);
                    var u={};
                    $.each(d,function(k,v){ if(!u[v])u[v]=k; });
                    $.each(u,function(n,k){ $el.append('<option value="'+k+'">'+n+'</option>'); });
                    if(selectedValue && $el.find('option[value="'+selectedValue+'"]').length){
                        $el.val(selectedValue);
                    }
                    $el.trigger('change.select2');
                    if(cb)cb();
                });
            }

            function rSpec(keepSpecialty){
                pdu('{{ route("admin.journal.get-specialties") }}', fp(), '#specialty', keepSpecialty || '');
            }
            function rGrp(){
                var prev=getCbValues('groups');
                $.get('{{ route("admin.journal.get-groups") }}',fp(),function(d){
                    var items=[];
                    $.each(d,function(k,v){ items.push({value:k,label:v}); });
                    loadCbItems('groups',items,prev);
                });
            }
            function rSubj(){
                var prev=getCbValues('subjects');
                $.get('{{ route("admin.journal.get-subjects") }}',fp(),function(d){
                    var seen={},items=[];
                    $.each(d,function(k,v){ if(!seen[v]){seen[v]=true;items.push({value:k,label:v});} });
                    loadCbItems('subjects',items,prev);
                });
            }

            function reloadSemesters(keepSemester, cb) {
                pd('{{ route("admin.journal.get-semesters") }}', fp(), '#semester_code', keepSemester || '', cb);
            }

            function reloadLevelsAndSemesters(options) {
                options = options || {};
                pd('{{ route("admin.journal.get-level-codes") }}', fp(), '#level_code', options.keepLevel || '', function(){
                    reloadSemesters(options.keepSemester || '', function(){
                        rSubj();
                        rGrp();
                        if (options.after) options.after();
                    });
                });
            }

            function reloadAllDependentFilters(options) {
                options = options || {};
                rSpec(options.keepSpecialty || '');
                reloadLevelsAndSemesters(options);
            }

            window.reloadVedomostDependentFilters = reloadAllDependentFilters;

            $('#education_type').change(function(){
                reloadAllDependentFilters({ keepSpecialty:false, keepLevel:false, keepSemester:false });
            });
            $('#faculty').change(function(){
                reloadAllDependentFilters({ keepSpecialty:false, keepLevel:false, keepSemester:false });
            });
            $('#specialty').change(function(){
                reloadLevelsAndSemesters({ keepLevel:false, keepSemester:false });
            });
            $('#department').change(function(){ rSubj(); rGrp(); });
            $('#level_code').change(function(){
                reloadSemesters('', function(){
                    rSubj();
                    rGrp();
                });
            });
            $('#semester_code').change(function(){ rSubj(); rGrp(); });

            // Initial load
            reloadAllDependentFilters({ keepSpecialty:false, keepLevel:false, keepSemester:false });
        });

        function collectParams() {
            var df=document.getElementById('dekan_faculty_id');
            var params = {
                education_type:  $('#education_type').val()||'',
                faculty_id:      df ? df.value : ($('#faculty').val()||''),
                specialty_id:    $('#specialty').val()||'',
                department_id:   $('#department').val()||'',
                level_code:      $('#level_code').val()||'',
                semester_code:   $('#semester_code').val()||'',
                yn_date_from:    $('#yn_date_from').val()||'',
                yn_date_to:      $('#yn_date_to').val()||'',
                current_semester: document.getElementById('current-semester-toggle').classList.contains('active')?'1':'0',
            };
            var gv=getCbValues('groups'); if(gv.length) params.group_ids=gv;
            var sv=getCbValues('subjects'); if(sv.length) params.subject_ids=sv;
            return params;
        }
    </script>
