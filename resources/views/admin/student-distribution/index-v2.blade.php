<x-app-layout>
    <style>
        .sd-page{width:100%;max-width:none;margin:0;padding:18px 14px 34px;color:#0f172a}
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
        .sd-group-head{display:flex;align-items:flex-start;justify-content:space-between;gap:10px}.sd-group-delete{display:grid;place-items:center;flex:0 0 30px;width:30px;height:30px;border:1px solid #fecaca;border-radius:8px;background:#fff1f2;color:#dc2626;cursor:pointer;transition:.15s}.sd-group-delete:hover{border-color:#f87171;background:#dc2626;color:#fff}.sd-group-delete svg{width:14px;height:14px}
        .sd-modal-backdrop{position:fixed;inset:0;z-index:80;display:none;align-items:center;justify-content:center;padding:18px;background:rgba(15,23,42,.58)}.sd-modal-backdrop.is-open{display:flex}.sd-modal{width:min(1080px,100%);max-height:calc(100vh - 36px);overflow:hidden;border-radius:18px;background:#fff;box-shadow:0 25px 70px rgba(15,23,42,.28)}.sd-modal-head{display:flex;align-items:flex-start;justify-content:space-between;gap:15px;padding:17px 20px;color:#fff;background:linear-gradient(120deg,#123766,#2563eb)}.sd-modal-head h2{margin:0;font-size:17px;font-weight:800}.sd-modal-head p{margin:4px 0 0;color:#dbeafe;font-size:11px}.sd-close{display:grid;place-items:center;width:30px;height:30px;border:1px solid rgba(255,255,255,.35);border-radius:50%;background:transparent;color:#fff;font-size:20px;cursor:pointer}.sd-tabs{display:flex;gap:4px;padding:10px 18px 0;border-bottom:1px solid #e2e8f0}.sd-tab{padding:10px 13px;border:0;border-bottom:3px solid transparent;background:transparent;color:#64748b;font-size:12px;font-weight:800;cursor:pointer}.sd-tab.active{border-color:#2563eb;color:#1d4ed8}.sd-panel{display:none;padding:15px 18px 18px}.sd-panel.active{display:block}.sd-modal-filters{display:grid;grid-template-columns:1.5fr 1.5fr .7fr auto;gap:9px;align-items:end;padding:12px;border:1px solid #e2e8f0;border-radius:12px;background:#f8fafc}.sd-scroll{max-height:390px;overflow:auto;margin-top:12px;border:1px solid #e2e8f0;border-radius:11px}.sd-accordion{border-bottom:1px solid #e2e8f0}.sd-accordion:last-child{border-bottom:0}.sd-accordion summary{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:11px 13px;cursor:pointer;list-style:none;color:#1e3a5f;font-size:12px;font-weight:800;background:#fff}.sd-accordion summary::-webkit-details-marker{display:none}.sd-accordion summary:hover{background:#f8fbff}.sd-accordion-body{padding:0 12px 10px;background:#fbfdff}.sd-student-row{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:9px 2px;border-top:1px solid #edf2f7}.sd-student-info{min-width:0}.sd-student-info b{display:block;overflow:hidden;color:#334155;font-size:11px;text-overflow:ellipsis;white-space:nowrap}.sd-student-info span{display:block;margin-top:2px;color:#94a3b8;font-size:10px}.sd-muted{padding:14px;color:#94a3b8;font-size:11px;text-align:center}.sd-permission{display:inline-flex;align-items:center;gap:5px;padding:5px 8px;border-radius:7px;background:#ecfdf5;color:#047857;font-size:10px;font-weight:800}
        .sd-choice-list{display:grid;gap:8px;max-height:390px;overflow:auto;margin-top:12px}.sd-choice{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:11px 13px;border:1px solid #dbe5f0;border-radius:10px;background:#fff}.sd-choice b{display:block;color:#1e3a5f;font-size:12px}.sd-choice span{display:block;margin-top:3px;color:#64748b;font-size:10px}.sd-move-copy{padding:12px;border-radius:10px;background:#eff6ff;color:#1e40af;font-size:11px}
        .sd-setup{display:grid;grid-template-columns:1fr 1fr;gap:12px;padding:16px 18px}.sd-setup-box{display:flex;align-items:center;justify-content:space-between;gap:14px;padding:15px;border:1px solid #dbeafe;border-radius:13px;background:linear-gradient(135deg,#f8fbff,#eff6ff)}.sd-setup-box h3{margin:0;font-size:13px;font-weight:800}.sd-setup-box p{margin:4px 0 0;color:#64748b;font-size:10px}
        .sd-config-body{padding:15px 18px}.sd-config-filters{display:grid;grid-template-columns:1.3fr 1.3fr .65fr 1fr;gap:9px;padding:12px;border:1px solid #e2e8f0;border-radius:12px;background:#f8fafc}.sd-config-scroll{max-height:430px;overflow:auto;margin-top:12px;border:1px solid #e2e8f0;border-radius:11px}.sd-config-table{width:100%;border-collapse:collapse;font-size:11px}.sd-config-table th{position:sticky;top:0;z-index:2;padding:9px;background:#eff6ff;color:#1e3a5f;text-align:left}.sd-config-table td{padding:8px 9px;border-top:1px solid #edf2f7}.sd-config-table tr:hover td{background:#f8fbff}.sd-config-foot{display:flex;align-items:center;justify-content:space-between;padding:12px 18px;border-top:1px solid #e2e8f0;background:#f8fafc}.sd-check{width:16px;height:16px;accent-color:#2563eb}.sd-number{width:82px;height:33px;border:1px solid #cbd5e1;border-radius:8px;padding:0 8px;font-size:11px}
        .sd-group.is-source{border-color:#fecdd3;border-left-color:#e11d48;background:#fff1f2}.sd-pill-red{background:#fff1f2;color:#be123c}
        .sd-row-title b{display:block;color:#1e3a5f}.sd-row-title span{display:block;margin-top:2px;color:#94a3b8;font-size:9px}
        .sd-paste-box{display:grid;grid-template-columns:1fr auto;gap:10px;align-items:end;margin-bottom:12px;padding:12px;border:1px dashed #93c5fd;border-radius:12px;background:#eff6ff}.sd-paste{width:100%;min-height:72px;resize:vertical;border:1px solid #bfdbfe;border-radius:9px;padding:9px 10px;background:#fff;font:11px/1.45 monospace;color:#334155;outline:none}.sd-paste:focus{border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.1)}.sd-paste-help{display:block;margin-bottom:6px;color:#1e40af;font-size:10px;font-weight:700}@media(max-width:760px){.sd-paste-box{grid-template-columns:1fr}}
        .sd-paste{min-height:150px}.sd-config-scroll{max-height:300px}
        @media(max-width:760px){.sd-setup{grid-template-columns:1fr}.sd-setup-box{align-items:flex-start;flex-direction:column}.sd-config-filters{grid-template-columns:1fr}}
        @media(max-width:760px){.sd-hero-content{align-items:flex-start;flex-direction:column}.sd-hero-stat{width:100%}.sd-upload,.sd-filters,.sd-modal-filters{display:block}.sd-upload .sd-btn,.sd-filter-actions{width:100%;margin-top:9px}.sd-filter-actions .sd-btn{flex:1}.sd-groups{grid-template-columns:1fr}.sd-student-row{align-items:flex-start;flex-direction:column}.sd-student-row .sd-btn{width:100%}}
        .sd-group-tabs{display:flex;gap:8px;padding:13px 18px 0;border-bottom:1px solid #edf2f7;background:#fff}.sd-group-tab{display:inline-flex;align-items:center;gap:8px;padding:10px 14px;border:1px solid transparent;border-bottom:3px solid transparent;border-radius:10px 10px 0 0;background:transparent;color:#64748b;font-size:12px;font-weight:800;cursor:pointer}.sd-group-tab:hover{background:#f8fafc;color:#1e40af}.sd-group-tab.active{border-color:#dbeafe;border-bottom-color:#2563eb;background:#eff6ff;color:#1d4ed8}.sd-group-tab[data-view="source"].active{border-color:#fecdd3;border-bottom-color:#e11d48;background:#fff1f2;color:#be123c}.sd-group-tab-count{display:inline-grid;place-items:center;min-width:23px;height:20px;padding:0 6px;border-radius:999px;background:#fff;font-size:10px}@media(max-width:600px){.sd-group-tabs{display:grid;grid-template-columns:1fr 1fr}.sd-group-tab{justify-content:center;padding:9px 6px}}
        .sd-group-tab[data-view="applications"].active{border-color:#fde68a;border-bottom-color:#d97706;background:#fffbeb;color:#92400e}.sd-application{grid-column:span 2;padding:15px;border:1px solid #dbe5ef;border-left:4px solid #d97706;border-radius:13px;background:linear-gradient(145deg,#fff,#fffbeb)}.sd-application-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px}.sd-application h3{margin:0;color:#123766;font-size:13px;font-weight:900}.sd-application-meta{margin-top:4px;color:#64748b;font-size:10px}.sd-application-route{display:inline-flex;margin-top:10px;padding:6px 9px;border-radius:8px;background:#eff6ff;color:#1d4ed8;font-size:11px;font-weight:900}.sd-application-reason{margin:10px 0 0;color:#475569;font-size:11px;line-height:1.55}.sd-permission-toolbar{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:12px;padding:10px 12px;border:1px solid #dbe5f0;border-radius:11px;background:#f8fafc}.sd-permission-toolbar label{display:flex;align-items:center;gap:8px;color:#334155;font-size:11px;font-weight:800}.sd-permission-buttons{display:flex;gap:8px}.sd-permission-group{display:flex;align-items:center;gap:11px;padding:12px 13px;border-bottom:1px solid #edf2f7;cursor:pointer}.sd-permission-group:last-child{border-bottom:0}.sd-permission-group:hover{background:#f8fbff}.sd-permission-group-info{flex:1;min-width:0}.sd-permission-group-info b{display:block;color:#1e3a5f;font-size:12px}.sd-permission-group-info span{display:block;margin-top:3px;color:#64748b;font-size:10px}@media(max-width:760px){.sd-group-tabs{grid-template-columns:repeat(3,1fr)}.sd-application{grid-column:span 1}.sd-permission-toolbar{align-items:stretch;flex-direction:column}.sd-permission-buttons{display:grid;grid-template-columns:1fr 1fr}.sd-permission-buttons .sd-btn{padding:0 8px}}
        .sd-application-side{display:flex;align-items:flex-end;flex-direction:column;gap:8px}.sd-application-actions{display:flex;align-items:center;gap:6px}.sd-app-action{display:inline-flex;align-items:center;justify-content:center;gap:5px;height:31px;border:0;border-radius:8px;padding:0 10px;color:#fff;font-size:10px;font-weight:900;cursor:pointer}.sd-app-action svg{width:14px;height:14px}.sd-app-approve{background:#059669}.sd-app-approve:hover{background:#047857}.sd-app-delete{width:31px;padding:0;background:#dc2626}.sd-app-delete:hover{background:#b91c1c}@media(max-width:600px){.sd-application-head{align-items:stretch;flex-direction:column}.sd-application-side{align-items:flex-start}}
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
            <div class="sd-card-head"><div><h2>Taqsimlashni sozlash</h2><p>Excel kerak emas. Guruhlarni LMS bazasidan tanlab, to'g'ridan-to'g'ri DB ga saqlang.</p></div></div>
            <div class="sd-setup">
                <div class="sd-setup-box"><div><h3>1. Guruhlarni yuklash</h3><p>Guruh sig'imi va bo'sh joyini kiritib saqlang.</p></div><button class="sd-btn" id="openCatalog" type="button">Guruhlarni ochish</button></div>
                <div class="sd-setup-box"><div><h3>2. Taqsimlanadigan guruhlar</h3><p>Faqat talabalari ko'chiriladigan guruhlarni belgilang.</p></div><button class="sd-btn sd-btn-green" id="openSources" type="button">Guruhlarni belgilash</button></div>
            </div>
        </section>

        <section class="sd-card">
            <div class="sd-card-head"><div><h2>2. Fakultet bo'yicha guruhlar</h2><p>Bo'sh joyi bor guruhni tanlang va talabalarni to'ldiring.</p></div><span class="sd-pill" id="groupCount">{{ $groups->count() }} ta guruh</span></div>
            <div class="sd-filters">
                <div class="sd-field"><label class="sd-label" for="facultyFilter">Fakultet</label><select class="sd-select" id="facultyFilter"><option value="">Barcha fakultetlar</option>@foreach($faculties as $faculty)<option value="{{ $faculty }}">{{ $faculty }}</option>@endforeach</select></div>
                <div class="sd-field"><label class="sd-label" for="specialtyFilter">Yo'nalish</label><select class="sd-select" id="specialtyFilter"><option value="">Barcha yo'nalishlar</option>@foreach($specialties as $specialty)<option value="{{ $specialty }}">{{ $specialty }}</option>@endforeach</select></div>
                <div class="sd-field"><label class="sd-label" for="courseFilter">Kurs</label><select class="sd-select" id="courseFilter"><option value="">Barchasi</option>@foreach($courses as $course)<option value="{{ $course }}">{{ $course }}-kurs</option>@endforeach</select></div>
                <div class="sd-filter-actions"><button type="button" class="sd-btn sd-btn-light" id="resetFilters">Tozalash</button><button type="button" class="sd-btn" id="refreshGroups">Yangilash</button></div>
            </div>
            <div class="sd-group-tabs">
                <button class="sd-group-tab active" id="targetGroupsTab" data-view="target" type="button">To'ldiriladigan guruhlar <span class="sd-group-tab-count" id="targetTabCount">0</span></button>
                <button class="sd-group-tab" id="sourceGroupsTab" data-view="source" type="button">Taqsimlanadigan guruhlar <span class="sd-group-tab-count" id="sourceTabCount">0</span></button>
                <button class="sd-group-tab" id="applicationsTab" data-view="applications" type="button">Arizalar <span class="sd-group-tab-count" id="applicationsTabCount">0</span></button>
            </div>
            <div class="sd-summary"><span class="sd-pill sd-pill-green" id="availableCount">0 ta bo'sh joyli</span><span id="capacitySummary"></span></div>
            <div class="sd-groups" id="groupsGrid"></div>
        </section>
    </div>

    <div class="sd-modal-backdrop" id="catalogModal">
        <div class="sd-modal" role="dialog" aria-modal="true">
            <div class="sd-modal-head"><div><h2>Guruhlarni DB ga yuklash</h2><p>LMS dagi guruhlarni tanlang, sig'im va bo'sh joyni kiriting.</p></div><button class="sd-close" type="button" data-close="catalogModal">&times;</button></div>
                <div class="sd-paste-box">
                    <div><span class="sd-paste-help">Exceldagi guruh, sig'im va bo'sh joy kataklarini copy qilib shu yerga paste qiling.</span><textarea class="sd-paste" id="catalogPaste" placeholder="d1/24-03(a)    15    3"></textarea></div>
                    <button class="sd-btn" id="applyCatalogPaste" type="button">Ro'yxatni qo'llash</button>
                </div>
            <div class="sd-config-body">
                <div class="sd-config-filters">
                    <div class="sd-field"><label class="sd-label">Fakultet</label><select class="sd-select" id="catalogFaculty"></select></div>
                    <div class="sd-field"><label class="sd-label">Yo'nalish</label><select class="sd-select" id="catalogSpecialty"></select></div>
                    <div class="sd-field"><label class="sd-label">Kurs</label><select class="sd-select" id="catalogCourse"></select></div>
                    <div class="sd-field"><label class="sd-label">Qidirish</label><input class="sd-input" id="catalogSearch" placeholder="Guruh nomi"></div>
                </div>
                <div class="sd-config-scroll"><table class="sd-config-table"><thead><tr><th></th><th>Guruh</th><th>Talaba</th><th>Sig'im</th><th>Bo'sh joy</th><th>Holat</th></tr></thead><tbody id="catalogRows"></tbody></table></div>
            </div>
            <div class="sd-config-foot"><span id="catalogCount">0 ta tanlandi</span><button class="sd-btn" id="saveCatalog" type="button">Tanlanganlarni saqlash</button></div>
        </div>
    </div>

    <div class="sd-modal-backdrop" id="sourcesModal">
        <div class="sd-modal" role="dialog" aria-modal="true">
            <div class="sd-modal-head"><div><h2>Talabalari taqsimlanadigan guruhlar</h2><p>Faqat belgilangan guruh talabalarini boshqa guruhga o'tkazish mumkin.</p></div><button class="sd-close" type="button" data-close="sourcesModal">&times;</button></div>
                <div class="sd-paste-box">
                    <div><span class="sd-paste-help">Guruh nomi va ko'chiriladigan talabalar sonini ikki ustun qilib copy-paste qiling.</span><textarea class="sd-paste" id="sourcePaste" placeholder="d1/23-09a    10&#10;d1/23-09b    10"></textarea></div>
                    <button class="sd-btn sd-btn-green" id="applySourcePaste" type="button">Ro'yxatni qo'llash</button>
                </div>
            <div class="sd-config-body">
                <div class="sd-config-filters">
                    <div class="sd-field"><label class="sd-label">Fakultet</label><select class="sd-select" id="sourceFaculty"></select></div>
                    <div class="sd-field"><label class="sd-label">Yo'nalish</label><select class="sd-select" id="sourceSpecialty"></select></div>
                    <div class="sd-field"><label class="sd-label">Kurs</label><select class="sd-select" id="sourceCourse"></select></div>
                    <div class="sd-field"><label class="sd-label">Qidirish</label><input class="sd-input" id="sourceSearch" placeholder="Guruh nomi"></div>
                </div>
                <div class="sd-config-scroll"><table class="sd-config-table"><thead><tr><th></th><th>Guruh</th><th>LMS talabalari</th><th>Ko'chiriladigan son</th><th>Holat</th></tr></thead><tbody id="sourceRows"></tbody></table></div>
            </div>
            <div class="sd-config-foot"><span id="sourceSelectedCount">0 ta tanlandi</span><button class="sd-btn sd-btn-green" id="saveSources" type="button">Ro'yxatni saqlash</button></div>
        </div>
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
                <div class="sd-move-copy">Taqsimlanadigan guruhlarni belgilang. Ruxsat berilgach, shu guruh talabalarining xizmatlar bo'limida guruhni o'zgartirish arizasi ochiladi.</div>
                <div class="sd-permission-toolbar"><label><input class="sd-check" id="permissionSelectAll" type="checkbox"> Hammasini belgilash</label><div class="sd-permission-buttons"><button class="sd-btn sd-btn-light" id="disableGroupPermissions" type="button">Xizmatni yopish</button><button class="sd-btn sd-btn-green" id="enableGroupPermissions" type="button">Ruxsat berish</button></div></div>
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
            const initialGroups = @json($groupPayloads);
            let applications = @json($applicationPayloads);
            const catalog = @json($catalogPayloads);
            const urls = {
                groups: @json(url('/admin/student-distribution/groups')),
                storeGroups: @json(url('/admin/student-distribution/groups')),
                storeSources: @json(url('/admin/student-distribution/source-groups')),
                students: @json(url('/admin/student-distribution/students')),
                assign: @json(url('/admin/student-distribution/assign-student')),
                permissionGroups: @json(url('/admin/student-distribution/permission-groups')),
                groupPermissions: @json(url('/admin/student-distribution/group-change-permissions')),
                applications: @json(url('/admin/student-distribution/applications'))
            };
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content || @json(csrf_token());
            let groups = initialGroups, selectedGroup = null, selectedStudent = null, groupView = 'target';
            const permissionSelected = new Set();
            const catalogSelected = new Set(catalog.filter(item => item.is_saved).map(item => item.key)), sourceSelected = new Set(catalog.filter(item => item.is_source).map(item => item.key)), studentCache = new Map(), catalogDraft = new Map(catalog.map(item => [item.key, {capacity:item.capacity, free_places:item.free_places}]));
            const sourceDraft = new Map(catalog.map(item => [item.key, item.is_source ? item.capacity : item.student_count]));

            const $ = id => document.getElementById(id);
            const esc = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
            const query = params => Object.entries(params).filter(([, value]) => value !== '' && value !== null && value !== undefined).map(([key,value]) => encodeURIComponent(key)+'='+encodeURIComponent(value)).join('&');
            const filterValues = (prefix) => ({faculty: $(prefix+'Faculty').value, specialty: $(prefix+'Specialty').value, course: $(prefix+'Course').value});

            function renderGroups() {
                const faculty = $('facultyFilter').value, specialty = $('specialtyFilter').value, course = $('courseFilter').value;
                const scoped = groups.filter(group => (!faculty || group.faculty_name === faculty) && (!specialty || group.specialty_name === specialty) && (!course || String(group.course) === course));
                const targets = scoped.filter(group => !group.is_source);
                const sources = scoped.filter(group => group.is_source);
                const filtered = groupView === 'source' ? sources : targets;

                $('targetTabCount').textContent = targets.length;
                $('sourceTabCount').textContent = sources.length;
                $('groupCount').textContent = filtered.length + ' ta guruh';
                $('heroGroupCount').textContent = scoped.length;
                const scopedApplications = applications.filter(application => (!faculty || application.faculty_name === faculty) && (!specialty || application.specialty_name === specialty) && (!course || String(application.course) === course));
                $('applicationsTabCount').textContent = scopedApplications.length;
                if (groupView === 'applications') {
                    const pendingCount = scopedApplications.filter(application => application.status === 'pending').length;
                    $('groupCount').textContent = scopedApplications.length + ' ta ariza';
                    $('availableCount').textContent = pendingCount + ' ta kutilmoqda';
                    $('availableCount').classList.remove('sd-pill-green', 'sd-pill-red');
                    $('capacitySummary').textContent = scopedApplications.length + ' ta jami ariza';
                    $('groupsGrid').innerHTML = scopedApplications.length ? scopedApplications.map(application => {
                        const statusLabel = application.status === 'approved' ? 'Qabul qilingan' : (application.status === 'rejected' ? 'Rad etilgan' : 'Kutilmoqda');
                        const statusClass = application.status === 'approved' ? 'sd-pill-green' : (application.status === 'rejected' ? 'sd-pill-red' : '');
                        const actions = application.status === 'pending' ? '<div class="sd-application-actions"><button class="sd-app-action sd-app-approve approve-application" type="button" data-id="'+application.id+'" title="Arizaga ruxsat berish"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6"/></svg>Ruxsat</button><button class="sd-app-action sd-app-delete delete-application" type="button" data-id="'+application.id+'" title="Arizani o\'chirish" aria-label="Arizani o\'chirish"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M9 7V4h6v3m-8 0 1 13h8l1-13M10 11v5m4-5v5"/></svg></button></div>' : '';
                        return '<article class="sd-application"><div class="sd-application-head"><div><h3>'+esc(application.student_name)+'</h3><div class="sd-application-meta">'+esc(application.student_id_number || '-')+' / '+esc(application.specialty_name)+' / '+application.course+'-kurs / '+esc(application.created_at || '')+'</div></div><div class="sd-application-side"><span class="sd-pill '+statusClass+'">'+statusLabel+'</span>'+actions+'</div></div><div class="sd-application-route">'+esc(application.source_group_name)+' &rarr; '+esc(application.target_group_name)+'</div><p class="sd-application-reason"><b>Sabab:</b> '+esc(application.reason)+'</p></article>';
                    }).join('') : '<div class="sd-empty" style="grid-column:1/-1"><strong>Ariza topilmadi</strong>Tanlangan filtr bo\'yicha ariza mavjud emas.</div>';
                    return;
                }


                if (groupView === 'source') {
                    $('availableCount').textContent = sources.length + ' ta taqsimlanadigan';
                    $('availableCount').classList.remove('sd-pill-green');
                    $('availableCount').classList.add('sd-pill-red');
                    $('capacitySummary').textContent = sources.reduce((sum, group) => sum + Number(group.occupied_count || group.capacity || 0), 0) + ' ta talaba qolgan';
                } else {
                    $('availableCount').textContent = targets.filter(group => group.free_places > 0).length + ' ta bo\'sh joyli';
                    $('availableCount').classList.add('sd-pill-green');
                    $('availableCount').classList.remove('sd-pill-red');
                    $('capacitySummary').textContent = targets.reduce((sum, group) => sum + group.free_places, 0) + ' ta bo\'sh o\'rin';
                }

                const emptyTitle = groupView === 'source' ? 'Taqsimlanadigan guruh topilmadi' : 'To\'ldiriladigan guruh topilmadi';
                $('groupsGrid').innerHTML = filtered.length ? filtered.map(group => '<article class="sd-group '+(group.is_source ? 'is-source' : '')+'"><div class="sd-group-head"><h3>'+esc(group.group_name)+'</h3><button class="sd-group-delete delete-group" type="button" data-group="'+group.id+'" data-name="'+esc(group.group_name)+'" title="Draft guruhni o\'chirish" aria-label="Draft guruhni o\'chirish"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M9 7V4h6v3m-8 0 1 13h8l1-13M10 11v5m4-5v5"/></svg></button></div><div class="sd-group-meta">'+esc(group.faculty_name)+'<br>'+esc(group.specialty_name)+' / '+group.course+'-kurs / sig\'im '+group.capacity+'</div><div class="sd-group-foot">'+(group.is_source ? '<span class="sd-pill sd-pill-red">Talabalari ko\'chiriladi</span>' : '<span class="'+(group.free_places > 0 ? 'sd-free' : 'sd-full')+'">'+(group.free_places > 0 ? group.free_places+' ta bo\'sh joy' : 'Joy qolmagan')+'</span>')+(!group.is_source && group.free_places > 0 ? '<button class="sd-btn sd-btn-green fill-trigger" type="button" data-group="'+group.id+'">To\'ldirish</button>' : '')+'</div></article>').join('') : '<div class="sd-empty" style="grid-column:1/-1"><strong>'+emptyTitle+'</strong>Filtrni o\'zgartiring yoki guruhlarni DB ga yuklang.</div>';
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
                const data = await response.json(); groups = data.groups;
                const applicationResponse = await fetch(urls.applications, {headers:{'Accept':'application/json'}});
                const applicationData = await applicationResponse.json();
                if (applicationResponse.ok) applications = applicationData.applications;
                renderGroups();
            }

            function configOptions(items, prefix) {
                const facultySelect = $(prefix+'Faculty');
                const specialtySelect = $(prefix+'Specialty');
                const courseSelect = $(prefix+'Course');

                setOptions(facultySelect, items.map(item => item.faculty_name), 'Barcha fakultetlar');
                const faculty = facultySelect.value;
                setOptions(specialtySelect, items.filter(item => !faculty || item.faculty_name === faculty).map(item => item.specialty_name), 'Barcha yo\'nalishlar');
                const specialty = specialtySelect.value;
                setOptions(courseSelect, items.filter(item => (!faculty || item.faculty_name === faculty) && (!specialty || item.specialty_name === specialty)).map(item => item.course), 'Barcha kurslar');
            }
            function configFiltered(items, prefix) {
                const f = filterValues(prefix), search = $(prefix+'Search').value.trim().toLowerCase();
                return items.filter(item => (!f.faculty || item.faculty_name === f.faculty) && (!f.specialty || item.specialty_name === f.specialty) && (!f.course || String(item.course) === f.course) && (!search || item.group_name.toLowerCase().includes(search)));
            }
            async function postJson(url, payload) {
                const response = await fetch(url, {method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrf,'Accept':'application/json'},body:JSON.stringify(payload)});
                const data = await response.json();
                if (!response.ok) throw new Error(data.message || Object.values(data.errors || {})[0]?.[0] || 'Amalni bajarib bo\'lmadi.');
                return data;
            }
            function renderCatalog() {
                const list = configFiltered(catalog, 'catalog');
                $('catalogRows').innerHTML = list.length ? list.map(item => {
                    const draft = catalogDraft.get(item.key);
                    return '<tr data-key="'+item.key+'"><td><input class="sd-check catalog-check" type="checkbox" '+(catalogSelected.has(item.key)?'checked':'')+'></td><td class="sd-row-title"><b>'+esc(item.group_name)+'</b><span>'+esc(item.faculty_name)+' / '+esc(item.specialty_name)+' / '+item.course+'-kurs</span></td><td>'+item.student_count+'</td><td><input class="sd-number capacity-input" type="number" min="0" value="'+draft.capacity+'"></td><td><input class="sd-number free-input" type="number" min="0" value="'+draft.free_places+'"></td><td>'+(item.is_saved?'<span class="sd-pill sd-pill-green">DB da</span>':'<span class="sd-pill">Yangi</span>')+'</td></tr>';
                }).join('') : '<tr><td colspan="6" class="sd-muted">Guruh topilmadi.</td></tr>';
                $('catalogCount').textContent = catalogSelected.size+' ta tanlandi';
            }
            function renderSources() {
                const list = configFiltered(catalog, 'source');
                $('sourceRows').innerHTML = list.length ? list.map(item => {
                    const count = sourceDraft.get(item.key) ?? item.student_count;
                    return '<tr data-key="'+item.key+'"><td><input class="sd-check source-check" type="checkbox" '+(sourceSelected.has(item.key)?'checked':'')+'></td><td class="sd-row-title"><b>'+esc(item.group_name)+'</b><span>'+esc(item.faculty_name)+' / '+esc(item.specialty_name)+' / '+item.course+'-kurs</span></td><td>'+item.student_count+'</td><td><input class="sd-number source-count-input" type="number" min="0" max="1000" value="'+count+'"></td><td>'+(item.is_source?'<span class="sd-pill sd-pill-red">Taqsimlanadi</span>':'<span class="sd-pill">Yangi</span>')+'</td></tr>';
                }).join('') : '<tr><td colspan="5" class="sd-muted">Mos LMS guruhi topilmadi.</td></tr>';
                $('sourceSelectedCount').textContent = sourceSelected.size+' ta tanlandi';
            }
            function openCatalog() { configOptions(catalog,'catalog'); renderCatalog(); $('catalogModal').classList.add('is-open'); }
            function openSources() { configOptions(catalog,'source'); renderSources(); $('sourcesModal').classList.add('is-open'); }
            const normalizeGroupName = value => {
                return String(value || '').trim().toLowerCase()
                    .replace(/\s+/g,'')
                    .replace(/^(d1|d2|p)\/[dp](?=\d)/, '$1/')
                    .replace(/\(([a-z])\)$/i, '$1');
            };
            function applyCatalogPaste() {
                const text = $('catalogPaste').value.trim();
                if (!text) return alert('Exceldan nusxalangan kataklarni kiriting.');
                const byName = new Map(catalog.map(item => [normalizeGroupName(item.group_name), item]));
                const matched = new Set();
                const unmatched = new Set();
                catalogSelected.clear();

                text.split(/\r?\n/).forEach(line => {
                    const cells = line.includes('\t') ? line.split('\t') : line.trim().split(/\s{2,}/);
                    for (let index = 0; index + 2 < cells.length; index++) {
                        const name = String(cells[index] || '').trim();
                        const capacity = Number(String(cells[index + 1] || '').replace(',','.'));
                        const freePlaces = Number(String(cells[index + 2] || '').replace(',','.'));
                        if (!name || !Number.isFinite(capacity) || !Number.isFinite(freePlaces)) continue;

                        const item = byName.get(normalizeGroupName(name));
                        if (item) {
                            catalogSelected.add(item.key);
                            catalogDraft.set(item.key, {
                                capacity:Math.max(0,Math.trunc(capacity)),
                                free_places:Math.max(0,Math.trunc(freePlaces)),
                            });
                            matched.add(item.key);
                        } else {
                            unmatched.add(name);
                        }
                        index += 2;
                    }
                });

                renderCatalog();
                const total = matched.size + unmatched.size;
                let message = matched.size+'/'+total+' ta guruh topildi.';
                if (unmatched.size) {
                    message += '\n\nTopilmagan guruhlar:\n- '+[...unmatched].join('\n- ');
                } else {
                    message += '\nEndi "Tanlanganlarni saqlash"ni bosing.';
                }
                alert(message);
            }
            function applySourcePaste() {
                const text = $('sourcePaste').value.trim();
                if (!text) return alert('Guruh nomi va talabalar sonini kiriting.');
                const byName = new Map(catalog.map(item => [normalizeGroupName(item.group_name), item]));
                const matched = new Set();
                const unmatched = new Set();
                sourceSelected.clear();

                text.split(/\r?\n/).forEach(line => {
                    const cells = line.includes('\t') ? line.split('\t') : line.trim().split(/\s{2,}/);
                    for (let index = 0; index + 1 < cells.length; index++) {
                        const name = String(cells[index] || '').trim();
                        const studentCount = Number(String(cells[index + 1] || '').replace(',','.'));
                        if (!name || !Number.isFinite(studentCount)) continue;

                        const item = byName.get(normalizeGroupName(name));
                        if (item) {
                            sourceSelected.add(item.key);
                            sourceDraft.set(item.key, Math.max(0,Math.trunc(studentCount)));
                            matched.add(item.key);
                        } else {
                            unmatched.add(name);
                        }
                        index += 1;
                    }
                });

                renderSources();
                const total = matched.size + unmatched.size;
                let message = matched.size+'/'+total+' ta source guruh topildi.';
                if (unmatched.size) {
                    message += '\n\nTopilmagan guruhlar:\n- '+[...unmatched].join('\n- ');
                } else {
                    message += '\nEndi "Ro\'yxatni saqlash"ni bosing.';
                }
                alert(message);
            }
            async function saveCatalog() {
                const rows = [...catalogSelected].map(key => ({key, ...catalogDraft.get(key)}));
                if (!rows.length) return alert('Kamida bitta guruhni tanlang.');
                if (rows.some(row => row.free_places > row.capacity)) return alert('Bo\'sh joy sig\'imdan katta bo\'lmaydi.');
                try { const data = await postJson(urls.storeGroups,{groups:rows}); alert(data.message); location.reload(); } catch (error) { alert(error.message); }
            }
            async function saveSources() {
                const rows = [...sourceSelected].map(key => ({key,student_count:Number(sourceDraft.get(key) || 0)}));
                if (!rows.length) return alert('Kamida bitta source guruhni tanlang.');
                try { const data = await postJson(urls.storeSources,{groups:rows}); alert(data.message); location.reload(); } catch (error) { alert(error.message); }
            }

            function openFill(id) {
                selectedGroup = groups.find(group => group.id === id); if (!selectedGroup) return;
                $('fillTitle').textContent = selectedGroup.group_name + ' guruhini to\'ldirish';
                $('fillSubtitle').textContent = selectedGroup.faculty_name+' ? '+selectedGroup.specialty_name+' ? '+selectedGroup.course+'-kurs ? '+selectedGroup.free_places+' ta bo\'sh joy';
                $('modalFaculty').value = selectedGroup.faculty_name; $('modalSpecialty').value = selectedGroup.specialty_name; $('modalCourse').value = String(selectedGroup.course);
                fillModalOptions(); $('fillModal').classList.add('is-open'); loadManual(); loadPermissionGroups();
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
                const filtered = groups.filter(group => group.is_source && (!f.faculty || group.faculty_name === f.faculty) && (!f.specialty || group.specialty_name === f.specialty) && (!f.course || String(group.course) === f.course));
                $('accordionList').innerHTML = filtered.length ? filtered.map((group,index) => '<details class="sd-accordion" '+(index===0?'open':'')+'><summary>'+esc(group.group_name)+' <span class="sd-pill sd-pill-red">Talabalari ko\'chiriladi</span></summary><div class="sd-accordion-body" id="groupRows'+group.id+'"><div class="sd-muted">Talabalar yuklanmoqda...</div></div></details>').join('') : '<div class="sd-muted">Taqsimlanadigan guruh belgilanmagan.</div>';
                filtered.forEach(async group => {
                    const data = await fetch(urls.students+'?'+base+'&group_id='+group.id).then(response => response.json());
                    data.students.forEach(student => studentCache.set(student.id, student));
                    const target = $('groupRows'+group.id); if (target) target.innerHTML = studentRows(data.students, true);
                });
            }
            function studentRows(students, canMove) {
                if (!students.length) return '<div class="sd-muted">Talabalar topilmadi.</div>';
                return students.map(student => {
                    const draft = student.draft_target_group_name ? '<span class="sd-pill">Draft: '+esc(student.draft_target_group_name)+'</span>' : '';
                    const buttonText = student.draft_target_group_name ? 'Draftni o\'zgartirish' : 'Draft guruhga biriktirish';
                    return '<div class="sd-student-row"><div class="sd-student-info"><b>'+esc(student.name)+'</b><span>'+esc(student.student_id_number)+' / haqiqiy guruh: '+esc(student.group_name || '-')+'</span>'+draft+'</div>'+(canMove ? '<button class="sd-btn sd-btn-green move-trigger" type="button" data-student="'+student.id+'">'+buttonText+'</button>' : '<span class="sd-permission">Biriktirilgan</span>')+'</div>';
                }).join('');
            }
            async function loadPermissionGroups() {
                permissionSelected.clear();
                $('permissionSelectAll').checked = false;
                $('permissionList').innerHTML = '<div class="sd-muted">Guruhlar yuklanmoqda...</div>';
                if (!selectedGroup) return;
                const response = await fetch(urls.permissionGroups+'?'+query({target_group_id:selectedGroup.id}), {headers:{'Accept':'application/json'}});
                const data = await response.json();
                if (!response.ok) {
                    $('permissionList').innerHTML = '<div class="sd-muted">'+esc(data.message || 'Guruhlarni yuklab bo\'lmadi.')+'</div>';
                    return;
                }
                $('permissionList').innerHTML = data.groups.length ? data.groups.map(group => '<label class="sd-permission-group"><input class="sd-check permission-group-check" type="checkbox" data-id="'+group.id+'"><span class="sd-permission-group-info"><b>'+esc(group.group_name)+'</b><span>'+group.student_count+' ta talaba / '+group.permission_count+' tasida xizmat ochiq</span></span><span class="sd-pill '+(group.all_enabled ? 'sd-pill-green' : 'sd-pill-red')+'">'+(group.all_enabled ? 'Ruxsat berilgan' : 'Yopiq yoki qisman')+'</span></label>').join('') : '<div class="sd-muted">Bu yo\'nalishda taqsimlanadigan guruh topilmadi.</div>';
            }

            async function setGroupPermissions(enabled) {
                if (!permissionSelected.size) return alert('Kamida bitta guruhni belgilang.');
                const response = await fetch(urls.groupPermissions, {method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrf,'Accept':'application/json'},body:JSON.stringify({distribution_group_ids:[...permissionSelected],enabled})});
                const data = await response.json();
                if (!response.ok) return alert(data.message || 'Amalni bajarib bo\'lmadi.');
                alert(data.message);
                loadPermissionGroups();
            }

            async function approveApplication(button) {
                button.disabled = true;
                try {
                    const response = await fetch(urls.applications+'/'+button.dataset.id+'/approve', {method:'POST',headers:{'X-CSRF-TOKEN':csrf,'Accept':'application/json'}});
                    const data = await response.json();
                    if (!response.ok) return alert(data.message || 'Arizaga ruxsat berib bo\'lmadi.');
                    alert(data.message);
                    await reloadGroups();
                } finally {
                    button.disabled = false;
                }
            }

            async function deleteApplication(button) {
                if (!confirm('Arizani o\'chirishni tasdiqlaysizmi?')) return;
                button.disabled = true;
                try {
                    const response = await fetch(urls.applications+'/'+button.dataset.id, {method:'DELETE',headers:{'X-CSRF-TOKEN':csrf,'Accept':'application/json'}});
                    const data = await response.json();
                    if (!response.ok) return alert(data.message || 'Arizani o\'chirib bo\'lmadi.');
                    await reloadGroups();
                } finally {
                    button.disabled = false;
                }
            }
            async function deleteGroup(button) {
                const name = button.dataset.name || 'Guruh';
                if (!confirm(name+' draft guruhini o\'chirishni tasdiqlaysizmi?')) return;
                button.disabled = true;
                try {
                    const response = await fetch(urls.groups+'/'+button.dataset.group, {method:'DELETE',headers:{'X-CSRF-TOKEN':csrf,'Accept':'application/json'}});
                    const data = await response.json();
                    if (!response.ok) return alert(data.message || 'Draft guruhni o\'chirib bo\'lmadi.');
                    if (Number(selectedGroup) === Number(button.dataset.group)) {
                        selectedGroup = null;
                        closeModal('fillModal');
                    }
                    alert(data.message);
                    await reloadGroups();
                } finally {
                    button.disabled = false;
                }
            }

            async function openMove(id) {
                selectedStudent = id; const student = studentCache.get(id);
                $('moveStudentName').textContent = student ? student.name+' / '+student.student_id_number+' / '+student.group_name : '';
                const available = groups.filter(group => !group.is_source && group.free_places > 0 && student && group.faculty_name === student.faculty && group.specialty_name === student.specialty && Number(group.course) === Number(student.course));
                $('moveChoices').innerHTML = available.length ? available.map(group => '<div class="sd-choice"><div><b>'+esc(group.group_name)+'</b><span>'+esc(group.specialty_name)+' / '+group.course+'-kurs / '+group.free_places+' ta bo\'sh joy</span></div><button class="sd-btn sd-btn-green assign-trigger" type="button" data-group="'+group.id+'">Tanlash</button></div>').join('') : '<div class="sd-muted">Mos bo\'sh joyli guruh topilmadi.</div>';
                $('moveModal').classList.add('is-open');
            }
            async function assign(groupId) {
                const response = await fetch(urls.assign, {method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrf,'Accept':'application/json'},body:JSON.stringify({student_id:selectedStudent,distribution_group_id:groupId})});
                const data = await response.json(); if (!response.ok) return alert(data.message || 'Amalni bajarib bo\'lmadi.');
                closeModal('moveModal'); await reloadGroups(); if ($('fillModal').classList.contains('is-open')) { loadManual(); loadPermissionGroups(); }
            }

            $('openCatalog').addEventListener('click', openCatalog);
            $('openSources').addEventListener('click', openSources);
            $('saveCatalog').addEventListener('click', saveCatalog);
            $('saveSources').addEventListener('click', saveSources);
            $('applyCatalogPaste').addEventListener('click', applyCatalogPaste);
            $('applySourcePaste').addEventListener('click', applySourcePaste);
            ['catalogFaculty','catalogSpecialty','catalogCourse'].forEach(id => $(id).addEventListener('change', () => { configOptions(catalog,'catalog'); renderCatalog(); }));
            ['sourceFaculty','sourceSpecialty','sourceCourse'].forEach(id => $(id).addEventListener('change', () => { configOptions(catalog,'source'); renderSources(); }));
            $('catalogSearch').addEventListener('input', renderCatalog);
            $('sourceSearch').addEventListener('input', renderSources);
            document.addEventListener('change', event => {
                const row = event.target.closest('tr');
                if (event.target.matches('.catalog-check')) {
                    event.target.checked ? catalogSelected.add(row.dataset.key) : catalogSelected.delete(row.dataset.key);
                    $('catalogCount').textContent = catalogSelected.size+' ta tanlandi';
                }
                if (event.target.matches('.capacity-input,.free-input')) {
                    catalogDraft.set(row.dataset.key, {capacity:Number(row.querySelector('.capacity-input').value),free_places:Number(row.querySelector('.free-input').value)});
                }
                if (event.target.matches('.source-check')) {
                    event.target.checked ? sourceSelected.add(row.dataset.key) : sourceSelected.delete(row.dataset.key);
                    $('sourceSelectedCount').textContent = sourceSelected.size+' ta tanlandi';
                }
                if (event.target.matches('.source-count-input')) {
                    sourceDraft.set(row.dataset.key, Math.max(0,Math.trunc(Number(event.target.value) || 0)));
                }
                if (event.target.matches('.permission-group-check')) {
                    const id = Number(event.target.dataset.id);
                    event.target.checked ? permissionSelected.add(id) : permissionSelected.delete(id);
                }
            });

            function setGroupView(view) {
                groupView = view;
                document.querySelectorAll('.sd-group-tab').forEach(tab => tab.classList.toggle('active', tab.dataset.view === view));
                renderGroups();
            }

            $('targetGroupsTab').addEventListener('click', () => setGroupView('target'));
            $('sourceGroupsTab').addEventListener('click', () => setGroupView('source'));

            $('applicationsTab').addEventListener('click', () => setGroupView('applications'));
            $('permissionSelectAll').addEventListener('change', event => {
                permissionSelected.clear();
                document.querySelectorAll('.permission-group-check').forEach(checkbox => {
                    checkbox.checked = event.target.checked;
                    if (checkbox.checked) permissionSelected.add(Number(checkbox.dataset.id));
                });
            });
            $('enableGroupPermissions').addEventListener('click', () => setGroupPermissions(true));
            $('disableGroupPermissions').addEventListener('click', () => setGroupPermissions(false));
            $('facultyFilter').addEventListener('change', () => { refreshFilterOptions(); renderGroups(); });
            $('specialtyFilter').addEventListener('change', () => { refreshFilterOptions(); renderGroups(); });
            $('courseFilter').addEventListener('change', renderGroups);
            $('resetFilters').addEventListener('click', () => { $('facultyFilter').value=''; $('specialtyFilter').value=''; $('courseFilter').value=''; refreshFilterOptions(); renderGroups(); });
            $('refreshGroups').addEventListener('click', reloadGroups);
            $('modalFaculty').addEventListener('change', fillModalOptions); $('modalSpecialty').addEventListener('change', fillModalOptions);
            $('reloadStudents').addEventListener('click', () => { loadManual(); loadPermissionGroups(); });
            document.addEventListener('click', event => {
                const approveButton = event.target.closest('.approve-application'); if (approveButton) approveApplication(approveButton);
                const deleteButton = event.target.closest('.delete-application'); if (deleteButton) deleteApplication(deleteButton);
                const deleteGroupButton = event.target.closest('.delete-group'); if (deleteGroupButton) deleteGroup(deleteGroupButton);
                const moveButton = event.target.closest('.move-trigger'); if (moveButton) openMove(Number(moveButton.dataset.student));
                const assignButton = event.target.closest('.assign-trigger'); if (assignButton) assign(Number(assignButton.dataset.group));
                const closeButton = event.target.closest('[data-close]'); if (closeButton) closeModal(closeButton.dataset.close);
                const tab = event.target.closest('.sd-tab'); if (tab) { document.querySelectorAll('.sd-tab').forEach(item=>item.classList.remove('active')); document.querySelectorAll('.sd-panel').forEach(item=>item.classList.remove('active')); tab.classList.add('active'); $(tab.dataset.tab).classList.add('active'); }
            });
            document.querySelectorAll('.sd-modal-backdrop').forEach(backdrop => backdrop.addEventListener('click', event => { if (event.target === backdrop) backdrop.classList.remove('is-open'); }));
            refreshFilterOptions(); renderGroups();
        })();
    </script>
</x-app-layout>
