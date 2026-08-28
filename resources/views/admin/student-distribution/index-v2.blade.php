<x-app-layout>
    <style>
        .sd-page{max-width:1540px;margin:0 auto;padding:18px 14px 34px;color:#0f172a}
        .sd-hero{position:relative;overflow:hidden;border-radius:20px;padding:22px 24px;color:#fff;background:linear-gradient(120deg,#123766,#1d4ed8 60%,#0ea5e9);box-shadow:0 14px 30px rgba(30,64,175,.18)}
        .sd-hero:after{content:"";position:absolute;right:-70px;top:-100px;width:260px;height:260px;border-radius:50%;background:rgba(255,255,255,.1)}
        .sd-hero-content{position:relative;z-index:1;display:flex;align-items:center;justify-content:space-between;gap:18px}
        .sd-title{display:flex;align-items:center;gap:14px}.sd-icon{display:grid;place-items:center;width:52px;height:52px;border:1px solid rgba(255,255,255,.3);border-radius:16px;background:rgba(255,255,255,.13)}
        .sd-kicker{font-size:10px;font-weight:800;letter-spacing:.16em;text-transform:uppercase;color:#dbeafe}.sd-title h1{margin:3px 0 4px;font-size:26px;font-weight:800}.sd-title p{margin:0;font-size:12px;color:#e0f2fe}
        .sd-hero-stat{position:relative;z-index:1;min-width:145px;padding:11px 14px;border:1px solid rgba(255,255,255,.3);border-radius:14px;background:rgba(255,255,255,.1)}.sd-hero-stat b{display:block;font-size:20px}.sd-hero-stat span{font-size:10px;color:#dbeafe}
        .sd-card{margin-top:15px;border:1px solid #dbe4ef;border-radius:16px;background:#fff;box-shadow:0 4px 14px rgba(15,23,42,.05)}
        .sd-card-head{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:15px 18px;border-bottom:1px solid #edf2f7}.sd-card-head h2{margin:0;font-size:14px;font-weight:800}.sd-card-head p{margin:3px 0 0;color:#64748b;font-size:11px}
        .sd-upload{display:flex;align-items:end;gap:12px;padding:16px 18px}.sd-field{flex:1}.sd-label{display:block;margin-bottom:6px;color:#475569;font-size:11px;font-weight:700}.sd-file,.sd-select,.sd-input{width:100%;height:38px;border:1px solid #cbd5e1;border-radius:9px;padding:0 10px;background:#fff;color:#334155;font-size:12px;outline:none}.sd-file{height:auto;padding:8px}.sd-file:focus,.sd-select:focus{border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,.1)}
        .sd-btn{display:inline-flex;align-items:center;justify-content:center;gap:7px;height:38px;padding:0 16px;border:0;border-radius:9px;background:#2563eb;color:#fff;font-size:12px;font-weight:800;cursor:pointer;transition:.15s}.sd-btn:hover{background:#1d4ed8}.sd-btn-green{background:#059669}.sd-btn-green:hover{background:#047857}.sd-btn-light{border:1px solid #cbd5e1;background:#f8fafc;color:#334155}.sd-btn-light:hover{background:#eff6ff}.sd-btn-danger{background:#dc2626}.sd-btn-danger:hover{background:#b91c1c}.sd-help{padding:0 18px 15px;color:#64748b;font-size:11px}
        .sd-alert{margin:15px 0 0;padding:11px 14px;border-radius:10px;font-size:12px}.sd-alert-ok{border:1px solid #a7f3d0;background:#ecfdf5;color:#047857}.sd-alert-error{border:1px solid #fecaca;background:#fef2f2;color:#b91c1c}
        .sd-filters{display:grid;grid-template-columns:1.4fr 1.4fr .65fr auto;align-items:end;gap:10px;padding:14px 18px;background:#f8fafc;border-bottom:1px solid #edf2f7}.sd-filter-actions{display:flex;gap:8px}.sd-filter-actions .sd-btn{white-space:nowrap}
        .sd-summary{display:flex;align-items:center;gap:8px;padding:0 18px 14px;color:#64748b;font-size:11px}.sd-pill{display:inline-flex;align-items:center;padding:5px 9px;border-radius:999px;background:#eff6ff;color:#1d4ed8;font-weight:800}.sd-pill-green{background:#ecfdf5;color:#047857}
        .sd-groups{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:10px;padding:16px 18px 20px}.sd-group{padding:14px;border:1px solid #dbe5f0;border-left:4px solid #2563eb;border-radius:12px;background:linear-gradient(145deg,#fff,#f8fbff);transition:.16s}.sd-group:hover{transform:translateY(-2px);box-shadow:0 8px 18px rgba(37,99,235,.1)}.sd-group h3{margin:0;color:#123766;font-size:13px;font-weight:800}.sd-group-meta{margin:5px 0 11px;color:#64748b;font-size:10px;line-height:1.45}.sd-group-foot{display:flex;align-items:center;justify-content:space-between;gap:7px}.sd-free{color:#047857;font-size:11px;font-weight:800}.sd-full{color:#b91c1c;font-size:11px;font-weight:800}.sd-empty{padding:34px 20px;text-align:center;color:#64748b;font-size:12px}.sd-empty strong{display:block;margin-bottom:4px;color:#334155;font-size:14px}
        .sd-modal-backdrop{position:fixed;inset:0;z-index:80;display:none;align-items:center;justify-content:center;padding:18px;background:rgba(15,23,42,.58)}.sd-modal-backdrop.is-open{display:flex}.sd-modal{width:min(1080px,100%);max-height:calc(100vh - 36px);overflow:hidden;border-radius:18px;background:#fff;box-shadow:0 25px 70px rgba(15,23,42,.28)}.sd-modal-head{display:flex;align-items:flex-start;justify-content:space-between;gap:15px;padding:17px 20px;color:#fff;background:linear-gradient(120deg,#123766,#2563eb)}.sd-modal-head h2{margin:0;font-size:17px;font-weight:800}.sd-modal-head p{margin:4px 0 0;color:#dbeafe;font-size:11px}.sd-close{display:grid;place-items:center;width:30px;height:30px;border:1px solid rgba(255,255,255,.35);border-radius:50%;background:transparent;color:#fff;font-size:20px;cursor:pointer}.sd-tabs{display:flex;gap:4px;padding:10px 18px 0;border-bottom:1px solid #e2e8f0}.sd-tab{padding:10px 13px;border:0;border-bottom:3px solid transparent;background:transparent;color:#64748b;font-size:12px;font-weight:800;cursor:pointer}.sd-tab.active{border-color:#2563eb;color:#1d4ed8}.sd-panel{display:none;padding:15px 18px 18px}.sd-panel.active{display:block}.sd-modal-filters{display:grid;grid-template-columns:1.5fr 1.5fr .7fr auto;gap:9px;align-items:end;padding:12px;border:1px solid #e2e8f0;border-radius:12px;background:#f8fafc}.sd-scroll{max-height:390px;overflow:auto;margin-top:12px;border:1px solid #e2e8f0;border-radius:11px}.sd-accordion{border-bottom:1px solid #e2e8f0}.sd-accordion:last-child{border-bottom:0}.sd-accordion summary{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:11px 13px;cursor:pointer;list-style:none;color:#1e3a5f;font-size:12px;font-weight:800;background:#fff}.sd-accordion summary::-webkit-details-marker{display:none}.sd-accordion summary:hover{background:#f8fbff}.sd-accordion-body{padding:0 12px 10px;background:#fbfdff}.sd-student-row{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:9px 2px;border-top:1px solid #edf2f7}.sd-student-info{min-width:0}.sd-student-info b{display:block;overflow:hidden;color:#334155;font-size:11px;text-overflow:ellipsis;white-space:nowrap}.sd-student-info span{display:block;margin-top:2px;color:#94a3b8;font-size:10px}.sd-muted{padding:14px;color:#94a3b8;font-size:11px;text-align:center}.sd-permission{display:inline-flex;align-items:center;gap:5px;padding:5px 8px;border-radius:7px;background:#ecfdf5;color:#047857;font-size:10px;font-weight:800}
        .sd-choice-list{display:grid;gap:8px;max-height:390px;overflow:auto;margin-top:12px}.sd-choice{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:11px 13px;border:1px solid #dbe5f0;border-radius:10px;background:#fff}.sd-choice b{display:block;color:#1e3a5f;font-size:12px}.sd-choice span{display:block;margin-top:3px;color:#64748b;font-size:10px}.sd-move-copy{padding:12px;border-radius:10px;background:#eff6ff;color:#1e40af;font-size:11px}
        @media(max-width:760px){.sd-hero-content{align-items:flex-start;flex-direction:column}.sd-hero-stat{width:100%}.sd-upload,.sd-filters,.sd-modal-filters{display:block}.sd-upload .sd-btn,.sd-filter-actions{width:100%;margin-top:9px}.sd-filter-actions .sd-btn{flex:1}.sd-groups{grid-template-columns:1fr}.sd-student-row{align-items:flex-start;flex-direction:column}.sd-student-row .sd-btn{width:100%}}
    </style>

    <div class="sd-page">
        <section class="sd-hero">
            <div class="sd-hero-content">
                <div class="sd-title">
                    <span class="sd-icon"><svg width="25" height="25" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 4h14v16H5zM8 8h8M8 12h8M8 16h5"/></svg></span>
                    <div><div class="sd-kicker">Registrator ofisi</div><h1>Talabalarni taqsimlash</h1><p>Guruh sig'imi, bo'sh joy va talabalarni bir joydan boshqaring.</p></div>
                </div>
                <div class="sd-hero-stat"><span>Faol guruhlar</span><b id="heroGroupCount">{{ $groups->count() }}</b></div>
            </div>
        </section>

        @if(session('success'))<div class="sd-alert sd-alert-ok">{{ session('success') }}</div>@endif
        @if($errors->any())<div class="sd-alert sd-alert-error">{{ $errors->first() }}</div>@endif

        <section class="sd-card">
            <div class="sd-card-head"><div><h2>1. Excel sig'im jadvalini yuklash</h2><p>Excelning birinchi qatorida: Fakultet, Yo'nalish, Kurs, Guruh, Sig'im yoki Bo'sh joy ustunlari bo'lsin.</p></div></div>
            <form class="sd-upload" method="POST" action="{{ route('admin.student-distribution.upload') }}" enctype="multipart/form-data">
                @csrf
                <div class="sd-field"><label class="sd-label" for="student_file">Excel fayl</label><input class="sd-file" id="student_file" name="student_file" type="file" accept=".xlsx,.xls,.csv,.txt" required></div>
                <button class="sd-btn" type="submit"><svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 16V4m0 0-4 4m4-4 4 4M5 20h14"/></svg>Excelni saqlash</button>
            </form>
            <div class="sd-help">Yangi fayl eski importni o'chirmaydi: yangi import faol bo'ladi, eski import tarixda saqlanadi.</div>
        </section>

        <section class="sd-card">
            <div class="sd-card-head"><div><h2>2. Fakultet bo'yicha guruhlar</h2><p>Bo'sh joyi bor guruhni tanlang va talabalarni to'ldiring.</p></div><span class="sd-pill" id="groupCount">{{ $groups->count() }} ta guruh</span></div>
            <div class="sd-filters">
                <div class="sd-field"><label class="sd-label" for="facultyFilter">Fakultet</label><select class="sd-select" id="facultyFilter"><option value="">Barcha fakultetlar</option>@foreach($faculties as $faculty)<option value="{{ $faculty }}">{{ $faculty }}</option>@endforeach</select></div>
                <div class="sd-field"><label class="sd-label" for="specialtyFilter">Yo'nalish</label><select class="sd-select" id="specialtyFilter"><option value="">Barcha yo'nalishlar</option>@foreach($specialties as $specialty)<option value="{{ $specialty }}">{{ $specialty }}</option>@endforeach</select></div>
                <div class="sd-field"><label class="sd-label" for="courseFilter">Kurs</label><select class="sd-select" id="courseFilter"><option value="">Barchasi</option>@foreach($courses as $course)<option value="{{ $course }}">{{ $course }}-kurs</option>@endforeach</select></div>
                <div class="sd-filter-actions"><button type="button" class="sd-btn sd-btn-light" id="resetFilters">Tozalash</button><button type="button" class="sd-btn" id="refreshGroups">Yangilash</button></div>
            </div>
            <div class="sd-summary"><span class="sd-pill sd-pill-green" id="availableCount">0 ta bo'sh joyli</span><span id="capacitySummary"></span></div>
            <div class="sd-groups" id="groupsGrid"></div>
        </section>
    </div>

    <div class="sd-modal-backdrop" id="fillModal">
        <div class="sd-modal" role="dialog" aria-modal="true">
            <div class="sd-modal-head"><div><h2 id="fillTitle">Guruhni to'ldirish</h2><p id="fillSubtitle"></p></div><button class="sd-close" type="button" data-close="fillModal">&times;</button></div>
            <div class="sd-tabs"><button class="sd-tab active" type="button" data-tab="manualPanel">1. Qo'lda taqsimlash</button><button class="sd-tab" type="button" data-tab="permissionPanel">2. Arizaga ruxsat</button></div>
            <div class="sd-panel active" id="manualPanel">
                <div class="sd-modal-filters">
                    <div class="sd-field"><label class="sd-label" for="modalFaculty">Fakultet</label><select class="sd-select" id="modalFaculty"></select></div>
                    <div class="sd-field"><label class="sd-label" for="modalSpecialty">Yo'nalish</label><select class="sd-select" id="modalSpecialty"></select></div>
                    <div class="sd-field"><label class="sd-label" for="modalCourse">Kurs</label><select class="sd-select" id="modalCourse"></select></div>
                    <button class="sd-btn sd-btn-light" type="button" id="reloadStudents">Ko'rsatish</button>
                </div>
                <div class="sd-scroll" id="accordionList"></div>
            </div>
            <div class="sd-panel" id="permissionPanel">
                <div class="sd-move-copy">Bu tab guruhga hali taqsimlanmagan talabalar uchun student profilidagi xizmatni ochadi. Talaba keyin xizmatlar bo'limidan ariza yuborishi mumkin.</div>
                <div class="sd-scroll" id="permissionList"></div>
            </div>
        </div>
    </div>

    <div class="sd-modal-backdrop" id="moveModal">
        <div class="sd-modal" style="width:min(650px,100%)" role="dialog" aria-modal="true">
            <div class="sd-modal-head"><div><h2>Talabani guruhga o'tkazish</h2><p id="moveStudentName"></p></div><button class="sd-close" type="button" data-close="moveModal">&times;</button></div>
            <div style="padding:16px 18px 18px"><div class="sd-move-copy">Talabaga mos fakultet, yo'nalish va kursdagi bo'sh joyli guruhlardan birini tanlang.</div><div class="sd-choice-list" id="moveChoices"></div></div>
        </div>
    </div>

    <script>
        (() => {
            const initialGroups = @json($groups->map(fn($g) => ['id'=>$g->id,'faculty_name'=>$g->faculty_name,'specialty_name'=>$g->specialty_name,'course'=>(int)$g->course,'group_name'=>$g->group_name,'capacity'=>(int)$g->capacity,'occupied_count'=>(int)$g->occupied_count,'free_places'=>(int)$g->free_places])->values());
            const urls = {
                groups: @json(route('admin.student-distribution.groups')),
                students: @json(route('admin.student-distribution.students')),
                assign: @json(route('admin.student-distribution.assign-student')),
                permission: @json(route('admin.student-distribution.group-change-permission'))
            };
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content || @json(csrf_token());
            let groups = initialGroups, selectedGroup = null, selectedStudent = null;

            const $ = id => document.getElementById(id);
            const esc = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
            const query = params => Object.entries(params).filter(([, value]) => value !== '' && value !== null && value !== undefined).map(([key,value]) => encodeURIComponent(key)+'='+encodeURIComponent(value)).join('&');
            const filterValues = (prefix) => ({faculty: $(prefix+'Faculty').value, specialty: $(prefix+'Specialty').value, course: $(prefix+'Course').value});

            function renderGroups() {
                const faculty = $('facultyFilter').value, specialty = $('specialtyFilter').value, course = $('courseFilter').value;
                const filtered = groups.filter(group => (!faculty || group.faculty_name === faculty) && (!specialty || group.specialty_name === specialty) && (!course || String(group.course) === course));
                $('groupCount').textContent = filtered.length + ' ta guruh';
                $('heroGroupCount').textContent = filtered.length;
                $('availableCount').textContent = filtered.filter(group => group.free_places > 0).length + ' ta bo\'sh joyli';
                $('capacitySummary').textContent = filtered.reduce((sum, group) => sum + group.free_places, 0) + ' ta bo\'sh o\'rin';
                $('groupsGrid').innerHTML = filtered.length ? filtered.map(group => '<article class="sd-group"><h3>'+esc(group.group_name)+'</h3><div class="sd-group-meta">'+esc(group.faculty_name)+'<br>'+esc(group.specialty_name)+' ? '+group.course+'-kurs</div><div class="sd-group-foot"><span class="'+(group.free_places > 0 ? 'sd-free' : 'sd-full')+'">'+(group.free_places > 0 ? group.free_places+' ta bo\'sh joy' : 'Joy qolmagan')+'</span>'+(group.free_places > 0 ? '<button class="sd-btn sd-btn-green fill-trigger" type="button" data-group="'+group.id+'">To\'ldirish</button>' : '')+'</div></article>').join('') : '<div class="sd-empty" style="grid-column:1/-1"><strong>Guruh topilmadi</strong>Filtrni o\'zgartirib ko\'ring yoki avval Excel yuklang.</div>';
                document.querySelectorAll('.fill-trigger').forEach(button => button.addEventListener('click', () => openFill(Number(button.dataset.group))));
            }

            function setOptions(select, values, empty) {
                const current = select.value; select.innerHTML = '<option value="">'+empty+'</option>'+[...new Set(values.filter(Boolean))].sort().map(value => '<option value="'+esc(value)+'">'+esc(value)+'</option>').join('');
                if ([...select.options].some(option => option.value === current)) select.value = current;
            }
            function refreshFilterOptions() {
                const faculty = $('facultyFilter').value;
                setOptions($('specialtyFilter'), groups.filter(group => !faculty || group.faculty_name === faculty).map(group => group.specialty_name), 'Barcha yo\'nalishlar');
                setOptions($('courseFilter'), groups.filter(group => (!faculty || group.faculty_name === faculty) && (!$('specialtyFilter').value || group.specialty_name === $('specialtyFilter').value)).map(group => group.course), 'Barchasi');
            }
            async function reloadGroups() {
                const response = await fetch(urls.groups);
                const data = await response.json(); groups = data.groups; renderGroups();
            }

            function openFill(id) {
                selectedGroup = groups.find(group => group.id === id); if (!selectedGroup) return;
                $('fillTitle').textContent = selectedGroup.group_name + ' guruhini to\'ldirish';
                $('fillSubtitle').textContent = selectedGroup.faculty_name+' ? '+selectedGroup.specialty_name+' ? '+selectedGroup.course+'-kurs ? '+selectedGroup.free_places+' ta bo\'sh joy';
                $('modalFaculty').value = selectedGroup.faculty_name; $('modalSpecialty').value = selectedGroup.specialty_name; $('modalCourse').value = String(selectedGroup.course);
                fillModalOptions(); $('fillModal').classList.add('is-open'); loadManual(); loadPermissions();
            }
            function fillModalOptions() {
                setOptions($('modalFaculty'), groups.map(group => group.faculty_name), 'Fakultet tanlang');
                $('modalFaculty').value = selectedGroup?.faculty_name || '';
                setOptions($('modalSpecialty'), groups.filter(group => !selectedGroup || group.faculty_name === $('modalFaculty').value).map(group => group.specialty_name), 'Yo\'nalish tanlang');
                $('modalSpecialty').value = selectedGroup?.specialty_name || '';
                setOptions($('modalCourse'), groups.filter(group => (!selectedGroup || group.faculty_name === $('modalFaculty').value) && (!$('modalSpecialty').value || group.specialty_name === $('modalSpecialty').value)).map(group => group.course), 'Kurs tanlang');
                $('modalCourse').value = String(selectedGroup?.course || '');
            }
            function closeModal(id) { $(id).classList.remove('is-open'); }
            async function loadManual() {
                const f = filterValues('modal');
                const base = query(f);
                const filtered = groups.filter(group => (!f.faculty || group.faculty_name === f.faculty) && (!f.specialty || group.specialty_name === f.specialty) && (!f.course || String(group.course) === f.course));
                $('accordionList').innerHTML = '<details class="sd-accordion" open><summary>Taqsimlanmagan talabalar <span class="sd-pill sd-pill-green">yuklanmoqda...</span></summary><div class="sd-accordion-body" id="unassignedRows"></div></details>'+filtered.map(group => '<details class="sd-accordion"><summary>'+esc(group.group_name)+' <span class="'+(group.free_places > 0 ? 'sd-free' : 'sd-full')+'">'+group.free_places+' bo\'sh joy</span></summary><div class="sd-accordion-body" id="groupRows'+group.id+'"><div class="sd-muted">Talabalar yuklanmoqda...</div></div></details>').join('');
                const unassigned = await fetch(urls.students+'?'+base+'&unassigned=1').then(response => response.json());
                $('unassignedRows').innerHTML = studentRows(unassigned.students, true);
                filtered.forEach(async group => {
                    const data = await fetch(urls.students+'?'+base+'&group_id='+group.id).then(response => response.json());
                    const target = $('groupRows'+group.id); if (target) target.innerHTML = studentRows(data.students, false);
                });
            }
            function studentRows(students, canMove) {
                if (!students.length) return '<div class="sd-muted">Talabalar topilmadi.</div>';
                return students.map(student => '<div class="sd-student-row"><div class="sd-student-info"><b>'+esc(student.name)+'</b><span>'+esc(student.student_id_number)+' ? '+esc(student.course ? student.course+'-kurs' : '-')+' ? '+esc(student.group_name || 'Taqsimlanmagan')+'</span></div>'+(canMove ? '<button class="sd-btn sd-btn-green move-trigger" type="button" data-student="'+student.id+'">Guruhga o\'tkazish</button>' : '<span class="sd-permission">Biriktirilgan</span>')+'</div>').join('');
            }
            async function loadPermissions() {
                const f = filterValues('modal');
                $('permissionList').innerHTML = '<div class="sd-muted">Talabalar yuklanmoqda...</div>';
                const data = await fetch(urls.students+'?'+query({...f, unassigned:1})).then(response => response.json());
                $('permissionList').innerHTML = data.students.length ? data.students.map(student => '<div class="sd-student-row"><div class="sd-student-info"><b>'+esc(student.name)+'</b><span>'+esc(student.student_id_number)+' ? '+esc(student.course ? student.course+'-kurs' : '-')+'</span></div>'+(student.permission_enabled ? '<button class="sd-btn sd-btn-light permission-trigger" data-student="'+student.id+'" data-enabled="0" type="button">Xizmatni yopish</button>' : '<button class="sd-btn sd-btn-green permission-trigger" data-student="'+student.id+'" data-enabled="1" type="button">Arizaga ruxsat</button>')+'</div>').join('') : '<div class="sd-muted">Taqsimlanmagan talabalar topilmadi.</div>';
            }
            async function openMove(id) {
                selectedStudent = id; const f = filterValues('modal');
                const students = await fetch(urls.students+'?'+query({...f,unassigned:1})).then(response => response.json());
                const student = students.students.find(item => item.id === id); $('moveStudentName').textContent = student ? student.name+' ? '+student.student_id_number : '';
                const available = groups.filter(group => group.free_places > 0 && (!f.faculty || group.faculty_name === f.faculty) && (!f.specialty || group.specialty_name === f.specialty) && (!f.course || String(group.course) === f.course));
                $('moveChoices').innerHTML = available.length ? available.map(group => '<div class="sd-choice"><div><b>'+esc(group.group_name)+'</b><span>'+esc(group.specialty_name)+' ? '+group.course+'-kurs ? '+group.free_places+' ta bo\'sh joy</span></div><button class="sd-btn sd-btn-green assign-trigger" type="button" data-group="'+group.id+'">Tanlash</button></div>').join('') : '<div class="sd-muted">Mos bo\'sh joyli guruh topilmadi.</div>';
                $('moveModal').classList.add('is-open');
            }
            async function assign(groupId) {
                const response = await fetch(urls.assign, {method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrf,'Accept':'application/json'},body:JSON.stringify({student_id:selectedStudent,distribution_group_id:groupId})});
                const data = await response.json(); if (!response.ok) return alert(data.message || 'Amalni bajarib bo\'lmadi.');
                closeModal('moveModal'); await reloadGroups(); if ($('fillModal').classList.contains('is-open')) { loadManual(); loadPermissions(); }
            }
            async function setPermission(studentId, enabled) {
                const response = await fetch(urls.permission, {method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrf,'Accept':'application/json'},body:JSON.stringify({student_id:studentId,enabled:Boolean(Number(enabled))})});
                const data = await response.json(); if (!response.ok) return alert(data.message || 'Amalni bajarib bo\'lmadi.'); loadPermissions();
            }

            $('facultyFilter').addEventListener('change', () => { refreshFilterOptions(); renderGroups(); });
            $('specialtyFilter').addEventListener('change', () => { refreshFilterOptions(); renderGroups(); });
            $('courseFilter').addEventListener('change', renderGroups);
            $('resetFilters').addEventListener('click', () => { $('facultyFilter').value=''; $('specialtyFilter').value=''; $('courseFilter').value=''; refreshFilterOptions(); renderGroups(); });
            $('refreshGroups').addEventListener('click', reloadGroups);
            $('modalFaculty').addEventListener('change', fillModalOptions); $('modalSpecialty').addEventListener('change', fillModalOptions);
            $('reloadStudents').addEventListener('click', () => { loadManual(); loadPermissions(); });
            document.addEventListener('click', event => {
                const moveButton = event.target.closest('.move-trigger'); if (moveButton) openMove(Number(moveButton.dataset.student));
                const assignButton = event.target.closest('.assign-trigger'); if (assignButton) assign(Number(assignButton.dataset.group));
                const permissionButton = event.target.closest('.permission-trigger'); if (permissionButton) setPermission(Number(permissionButton.dataset.student), permissionButton.dataset.enabled);
                const closeButton = event.target.closest('[data-close]'); if (closeButton) closeModal(closeButton.dataset.close);
                const tab = event.target.closest('.sd-tab'); if (tab) { document.querySelectorAll('.sd-tab').forEach(item=>item.classList.remove('active')); document.querySelectorAll('.sd-panel').forEach(item=>item.classList.remove('active')); tab.classList.add('active'); $(tab.dataset.tab).classList.add('active'); }
            });
            document.querySelectorAll('.sd-modal-backdrop').forEach(backdrop => backdrop.addEventListener('click', event => { if (event.target === backdrop) backdrop.classList.remove('is-open'); }));
            refreshFilterOptions(); renderGroups();
        })();
    </script>
</x-app-layout>
