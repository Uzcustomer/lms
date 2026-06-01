<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            Individual imtihon sanasi
            @if(!empty($currentEducationYear))
                <span style="font-size:13px;color:#64748b;font-weight:500;">— {{ $currentEducationYear }} o'quv yili</span>
            @endif
        </h2>
    </x-slot>

    <div class="py-4">
        <div style="max-width:1400px;margin:0 auto;padding:0 16px;">

            {{-- 1) Qidirish paneli --}}
            <div style="background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:16px;margin-bottom:14px;">
                <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
                    <div style="flex:1;min-width:260px;">
                        <label style="display:block;font-size:12px;color:#64748b;font-weight:600;margin-bottom:4px;">HEMIS ID, talaba raqami yoki F.I.Sh.</label>
                        <input type="text" id="student-query" placeholder="masalan 368241100123 yoki Tursunov Sardor"
                               style="width:100%;padding:9px 11px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;">
                    </div>
                    <div>
                        <button type="button" id="btn-search" onclick="ies.search()"
                                style="padding:9px 18px;background:linear-gradient(135deg,#0ea5e9,#0284c7);color:#fff;border:none;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;">
                            Qidirish
                        </button>
                    </div>
                </div>
                <div id="search-results" style="margin-top:10px;display:none;"></div>
            </div>

            {{-- 2) Talaba kartochkasi (qidirilgandan keyin to'ldiriladi) --}}
            <div id="student-card" style="display:none;background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:16px;margin-bottom:14px;"></div>

            {{-- 3) Fanlar jadvali (talaba tanlanganida to'ldiriladi) --}}
            <div id="subjects-wrap" style="display:none;background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:16px;margin-bottom:14px;">
                <h3 style="font-size:14px;font-weight:700;color:#1e293b;margin:0 0 10px;">Fanlar va individual sanalar</h3>
                <div id="subjects-table-wrap"></div>
            </div>

            {{-- 4) Audit tarixi --}}
            <div id="audit-wrap" style="display:none;background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:16px;">
                <h3 style="font-size:14px;font-weight:700;color:#1e293b;margin:0 0 10px;">📋 Tarix (oxirgi o'zgarishlar)</h3>
                <div id="audit-list" style="font-size:13px;"></div>
            </div>

            {{-- 5) Bo'sh boshlanish holati --}}
            <div id="empty-state" style="background:#f8fafc;border:1px dashed #cbd5e1;border-radius:10px;padding:48px 20px;text-align:center;">
                <p style="color:#64748b;font-size:14px;font-weight:600;">Yuqoridagi qidiruv maydoniga HEMIS ID yoki F.I.Sh. kiriting</p>
                <p style="color:#94a3b8;font-size:12px;margin-top:4px;">Talaba topilgach, fanlari va individual sana belgilash maydonlari ko'rinadi.</p>
            </div>

        </div>
    </div>

    <script>
    (function() {
        const SEARCH_URL = "{{ route($routePrefix . '.individual-exam-schedule.search') }}";
        const SUBJECTS_URL = "{{ route($routePrefix . '.individual-exam-schedule.student-subjects') }}";
        const SAVE_URL = "{{ route($routePrefix . '.individual-exam-schedule.save') }}";
        const CLEAR_URL = "{{ route($routePrefix . '.individual-exam-schedule.clear') }}";
        const ATTACH_UPLOAD_URL = "{{ route($routePrefix . '.individual-exam-schedule.attachments.upload') }}";
        // Download URL prefix — at.id va '/download' JS tomonda qo'shiladi
        const DOWNLOAD_URL_PREFIX = "{{ url($routePrefix . '/individual-exam-schedule/attachments') }}";
        const ATTACH_DELETE_URL = "{{ url($routePrefix . '/individual-exam-schedule/attachments') }}"; // + /{id}/delete
        const CSRF = "{{ csrf_token() }}";

        const $ = (id) => document.getElementById(id);
        const escapeHtml = (s) => String(s ?? '')
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');

        // YYYY-MM-DD → DD.MM.YYYY
        const formatDate = (s) => {
            if (!s || typeof s !== 'string') return '';
            const m = s.match(/^(\d{4})-(\d{2})-(\d{2})/);
            if (!m) return s;
            return `${m[3]}.${m[2]}.${m[1]}`;
        };

        window.ies = {
            currentStudent: null,
            subjects: [],

            async search() {
                const q = $('student-query').value.trim();
                if (q.length < 3) {
                    alert('Kamida 3 ta belgi kiriting.');
                    return;
                }
                const results = $('search-results');
                results.style.display = 'block';
                results.innerHTML = '<div style="padding:10px;color:#64748b;font-size:13px;">Qidirilmoqda...</div>';
                try {
                    const resp = await fetch(SEARCH_URL + '?q=' + encodeURIComponent(q), {
                        headers: { 'Accept': 'application/json' },
                    });
                    const data = await resp.json();
                    if (!data.students || data.students.length === 0) {
                        results.innerHTML = '<div style="padding:10px;color:#94a3b8;font-size:13px;">Talaba topilmadi.</div>';
                        return;
                    }
                    results.innerHTML = data.students.map(s => `
                        <div style="padding:8px 12px;border-bottom:1px solid #f1f5f9;cursor:pointer;display:flex;gap:12px;align-items:center;"
                             onclick="ies.loadStudent('${escapeHtml(s.hemis_id)}')"
                             onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='#fff'">
                            <div style="flex:1;">
                                <div style="font-weight:600;color:#1e293b;font-size:13px;">${escapeHtml(s.full_name)}</div>
                                <div style="font-size:11px;color:#64748b;">
                                    HEMIS: ${escapeHtml(s.hemis_id)} ·
                                    Tal. №: ${escapeHtml(s.student_id_number || '—')} ·
                                    Guruh: ${escapeHtml(s.group_name || s.group_id)}
                                </div>
                            </div>
                            <span style="color:#0284c7;font-size:12px;">Tanlash →</span>
                        </div>
                    `).join('');
                } catch (e) {
                    results.innerHTML = '<div style="padding:10px;color:#dc2626;font-size:13px;">Qidirishda xato: ' + escapeHtml(e.message) + '</div>';
                }
            },

            async loadStudent(hemisId) {
                $('search-results').style.display = 'none';
                $('empty-state').style.display = 'none';
                $('student-card').style.display = 'block';
                $('student-card').innerHTML = '<div style="padding:10px;color:#64748b;">Yuklanmoqda...</div>';
                $('subjects-wrap').style.display = 'none';
                $('audit-wrap').style.display = 'none';
                try {
                    const resp = await fetch(SUBJECTS_URL + '?student_hemis_id=' + encodeURIComponent(hemisId), {
                        headers: { 'Accept': 'application/json' },
                    });
                    if (!resp.ok) {
                        const txt = await resp.text();
                        throw new Error(txt);
                    }
                    const data = await resp.json();
                    this.currentStudent = data.student;
                    this.subjects = data.subjects || [];
                    this.renderStudentCard(data.student);
                    this.renderSubjects(data.subjects || []);
                    this.renderAudits(data.audits || []);
                } catch (e) {
                    $('student-card').innerHTML = '<div style="padding:10px;color:#dc2626;">Xato: ' + escapeHtml(e.message) + '</div>';
                }
            },

            renderStudentCard(s) {
                $('student-card').innerHTML = `
                    <div style="display:flex;gap:16px;align-items:center;flex-wrap:wrap;">
                        <div style="width:48px;height:48px;border-radius:50%;background:linear-gradient(135deg,#0ea5e9,#0284c7);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:18px;">
                            👤
                        </div>
                        <div style="flex:1;min-width:240px;">
                            <div style="font-size:16px;font-weight:700;color:#1e293b;">${escapeHtml(s.full_name)}</div>
                            <div style="font-size:12px;color:#64748b;margin-top:2px;">
                                HEMIS: <b>${escapeHtml(s.hemis_id)}</b> ·
                                Talaba №: ${escapeHtml(s.student_id_number || '—')}
                            </div>
                        </div>
                        <div style="text-align:right;font-size:12px;color:#475569;">
                            <div><b>Guruh:</b> ${escapeHtml(s.group_name || s.group_id)}</div>
                            <div><b>Yo'nalish:</b> ${escapeHtml(s.specialty_name || '—')}</div>
                        </div>
                    </div>
                `;
            },

            renderSubjects(subjects) {
                if (subjects.length === 0) {
                    $('subjects-wrap').style.display = 'block';
                    $('subjects-table-wrap').innerHTML = '<div style="padding:14px;color:#94a3b8;font-size:13px;text-align:center;">Joriy semestrda fanlar topilmadi.</div>';
                    return;
                }
                let html = `
                    <div style="overflow-x:auto;">
                    <table style="width:100%;border-collapse:collapse;font-size:13px;">
                        <thead>
                            <tr style="background:#f8fafc;border-bottom:2px solid #e2e8f0;">
                                <th style="padding:8px 10px;text-align:left;font-weight:700;color:#475569;">Fan</th>
                                <th style="padding:8px 10px;text-align:center;font-weight:700;color:#475569;">Urinish</th>
                                <th style="padding:8px 10px;text-align:center;font-weight:700;color:#475569;">Guruh sanasi</th>
                                <th style="padding:8px 10px;text-align:center;font-weight:700;color:#475569;">Holat</th>
                                <th style="padding:8px 10px;text-align:center;font-weight:700;color:#475569;">Individual sana</th>
                                <th style="padding:8px 10px;text-align:center;font-weight:700;color:#475569;">Vaqt</th>
                                <th style="padding:8px 10px;text-align:left;font-weight:700;color:#475569;">Izoh</th>
                                <th style="padding:8px 10px;text-align:center;font-weight:700;color:#475569;">Amal</th>
                            </tr>
                        </thead>
                        <tbody>
                `;
                // YN kunini belgilashdagi badge ranglari (1=yashil, 2=amber, 3=orange)
                const attemptColors = { 1: '#16a34a', 2: '#d97706', 3: '#ea580c' };
                const attemptBgs    = { 1: '#dcfce7', 2: '#fef3c7', 3: '#ffedd5' };
                const attemptLabels = { 1: '1-urinish', 2: '2-urinish', 3: '3-urinish' };

                // YN yopilish shakli — YN kunini belgilash sahifasidagi uslub.
                const cfMeta = {
                    'oski':      { label: 'Faqat OSKI',   bg: '#dbeafe', fg: '#1d4ed8', br: '#bfdbfe' },
                    'test':      { label: 'Faqat Test',   bg: '#dcfce7', fg: '#15803d', br: '#bbf7d0' },
                    'oski_test': { label: 'OSKI + Test',  bg: '#ede9fe', fg: '#6d28d9', br: '#ddd6fe' },
                    'normativ':  { label: 'Normativ',     bg: '#fef3c7', fg: '#a16207', br: '#fde68a' },
                    'sinov':     { label: 'Sinov (test)', bg: '#ffedd5', fg: '#c2410c', br: '#fed7aa' },
                    'none':      { label: "Yo'q",         bg: '#f1f5f9', fg: '#475569', br: '#cbd5e1' },
                };

                subjects.forEach((subj, sIdx) => {
                    // Talabaning shu fan uchun "joriy urinishi" — eligibility'dan kelib chiqamiz.
                    // 1-urinishda yiqilgan bo'lsa va attempt 2 baholari kelmagan bo'lsa → joriy = 2
                    // 2-urinishda yiqilgan bo'lsa va attempt 3 baholari kelmagan bo'lsa → joriy = 3
                    // Boshqa hollarda → joriy = 1 (boshlanish holati)
                    const elig = subj.eligibility || {};
                    let activeAttempt = 1;
                    if (elig.failed_attempt_2 && !elig.has_attempt_3_grade) activeAttempt = 3;
                    else if (elig.failed_attempt_1 && !elig.has_attempt_2_grade) activeAttempt = 2;

                    // YN yopilish shakli — qaysi YN turi (OSKI/Test) qatorlari kerakligi
                    const cf = subj.closing_form || null;
                    const showOski = cf === null || cf === 'oski' || cf === 'oski_test';
                    const showTest = cf === null || cf === 'test' || cf === 'oski_test';
                    const cfBadgeMeta = cf && cfMeta[cf] ? cfMeta[cf] : null;
                    const cfBadge = cfBadgeMeta
                        ? `<div style="margin-top:4px;"><span style="display:inline-block;padding:2px 7px;border-radius:6px;font-size:10px;font-weight:700;background:${cfBadgeMeta.bg};color:${cfBadgeMeta.fg};border:1px solid ${cfBadgeMeta.br};">${cfBadgeMeta.label}</span></div>`
                        : '';

                    const allAttempts = [
                        { ynType: 'oski', attempt: 1, label: 'OSKI', dateK: 'oski_date', timeK: 'oski_time' },
                        { ynType: 'test', attempt: 1, label: 'Test', dateK: 'test_date', timeK: 'test_time' },
                        { ynType: 'oski', attempt: 2, label: 'OSKI', dateK: 'oski_resit_date', timeK: 'oski_resit_time' },
                        { ynType: 'test', attempt: 2, label: 'Test', dateK: 'test_resit_date', timeK: 'test_resit_time' },
                        { ynType: 'oski', attempt: 3, label: 'OSKI', dateK: 'oski_resit2_date', timeK: 'oski_resit2_time' },
                        { ynType: 'test', attempt: 3, label: 'Test', dateK: 'test_resit2_date', timeK: 'test_resit2_time' },
                    ];
                    // Filtrlash mantig'i:
                    //  1) Yopilish shakli "oski" bo'lsa OSKI qatorlari, "test" bo'lsa
                    //     Test qatorlari ko'rsatiladi.
                    //  2) Mavjud individual sana bo'lgan qatorlar har doim qoladi
                    //     (admin tahrirlash yoki o'chirish uchun ko'rishi kerak).
                    //  3) Guruhda sana belgilangan qatorlar ham doim qoladi —
                    //     admin xohlasa shu talaba uchun alohida sana qo'yishi mumkin
                    //     (eligibilitydan qat'iy nazar). Bu ariza/pullik/maxsus sabab
                    //     stsenariylarida kerak bo'ladi.
                    //  4) Aks holda — talaba urinishni topshira oladigan qatorlargina:
                    //       1-urinish: baho hali kelmagan bo'lsa
                    //       2-urinish: 1-da yiqilgan VA 2-bahosi yo'q
                    //       3-urinish: 2-da yiqilgan VA 3-bahosi yo'q
                    const attempts = allAttempts.filter(a => {
                        if (a.ynType === 'oski' && !showOski) return false;
                        if (a.ynType === 'test' && !showTest) return false;

                        const hasIndividual = subj.individual && subj.individual[a.dateK];
                        if (hasIndividual) return true;

                        const hasGroupDate = subj.group && subj.group[a.dateK];
                        if (hasGroupDate) return true;

                        if (a.attempt === 1) {
                            return !elig.has_attempt_1_grade;
                        }
                        if (a.attempt === 2) {
                            return !!elig.failed_attempt_1 && !elig.has_attempt_2_grade;
                        }
                        if (a.attempt === 3) {
                            return !!elig.failed_attempt_2 && !elig.has_attempt_3_grade;
                        }
                        return true;
                    });
                    if (attempts.length === 0) {
                        // Yopilish shakli normativ/sinov/none — yoki talaba
                        // barcha urinishlarini topshirib bo'lgan: sana qo'yib bo'lmaydi
                        const cfBlocked = cf && ['normativ', 'sinov', 'none'].includes(cf);
                        const msg = cfBlocked
                            ? `Bu fan uchun YN sanasi qo'yilmaydi (${escapeHtml(cfBadgeMeta ? cfBadgeMeta.label : cf)}).`
                            : `Talaba bu fan bo'yicha mavjud urinish(lar)ni topshirib bo'lgan — qo'shimcha sana kerak emas.`;
                        html += `
                            <tr style="border-bottom:1px solid #f1f5f9;border-top:2px solid #e2e8f0;">
                                <td style="padding:8px 10px;vertical-align:top;">
                                    <div style="font-weight:600;color:#1e293b;">${escapeHtml(subj.subject_name)}</div>
                                    <div style="font-size:11px;color:#64748b;">${escapeHtml(subj.semester_code)}</div>
                                    ${cfBadge}
                                </td>
                                <td colspan="7" style="padding:10px;text-align:center;color:#94a3b8;font-size:12px;font-style:italic;">
                                    ${msg}
                                </td>
                            </tr>
                        `;
                        return; // forEach next subject
                    }
                    const attemptCount = attempts.length;
                    attempts.forEach((a, aIdx) => {
                        const groupDate = subj.group ? (subj.group[a.dateK] || '') : '';
                        const groupTime = subj.group ? (subj.group[a.timeK] || '') : '';
                        const indDate = subj.individual ? (subj.individual[a.dateK] || '') : '';
                        const indTime = subj.individual ? (subj.individual[a.timeK] || '') : '';
                        const indNote = subj.individual ? (subj.individual.note || '') : '';
                        const overrideWarn = subj.individual ? !!subj.individual.override_warning : false;
                        const eligAllowKey = 'allow_' + a.attempt;
                        const allowed = !!elig[eligAllowKey];
                        const reasons = elig.reasons || {};
                        const reason = reasons[a.attempt] || '';
                        const isActiveRow = a.attempt === activeAttempt;

                        // Urinish badge — YN kunini belgilash uslubidagi rangli badge
                        const urinishBadge = `<span style="display:inline-block;padding:3px 9px;border-radius:8px;font-size:11px;font-weight:700;background:${attemptBgs[a.attempt]};color:${attemptColors[a.attempt]};white-space:nowrap;">${attemptLabels[a.attempt]}</span>`
                            + `<div style="font-size:10px;color:#64748b;margin-top:2px;font-weight:600;">${a.label}</div>`;

                        // Status badge
                        let statusBadge;
                        if (allowed) {
                            statusBadge = '<span style="background:#dcfce7;color:#15803d;padding:2px 8px;border-radius:6px;font-size:11px;font-weight:600;">Ruxsat ✓</span>';
                        } else {
                            statusBadge = `<span style="background:#fee2e2;color:#b91c1c;padding:2px 8px;border-radius:6px;font-size:11px;font-weight:600;" title="${escapeHtml(reason)}">Ruxsat ❌</span>`;
                        }
                        if (isActiveRow) {
                            statusBadge += ' <span style="background:#fff7ed;color:#c2410c;padding:2px 8px;border-radius:6px;font-size:11px;font-weight:700;margin-left:4px;border:1px solid #fed7aa;" title="Talabaning hozirgi urinishi">★ Faol</span>';
                        }
                        if (subj.individual && indDate) {
                            statusBadge += ' <span style="background:#dbeafe;color:#1d4ed8;padding:2px 8px;border-radius:6px;font-size:11px;font-weight:600;margin-left:4px;">Individual</span>';
                        }
                        if (overrideWarn && indDate) {
                            statusBadge += ' <span style="background:#fef3c7;color:#a16207;padding:2px 8px;border-radius:6px;font-size:11px;font-weight:600;margin-left:4px;" title="Eligibility yo\'q edi, majburan qo\'yilgan">⚠️</span>';
                        }

                        const rowId = `r-${sIdx}-${aIdx}`;
                        const isFirstOfSubject = aIdx === 0;

                        // Hujjatlar bloki (faqat individual sana qo'yilgan bo'lsa)
                        let attachBlock = '';
                        if (subj.individual && subj.individual.id) {
                            const atts = (subj.individual.attachments || []);
                            const attList = atts.map(at => `
                                <div style="display:flex;gap:4px;align-items:center;font-size:11px;margin-top:3px;padding:3px 5px;background:#f1f5f9;border-radius:4px;">
                                    <a href="${DOWNLOAD_URL_PREFIX}/${at.id}/download" target="_blank"
                                       style="color:#0284c7;text-decoration:underline;flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                                       title="${escapeHtml(at.filename)} (${escapeHtml(at.uploaded_by_name || '—')}, ${escapeHtml(at.uploaded_at || '')})">
                                       📎 ${escapeHtml(at.filename)}
                                    </a>
                                    <button type="button" onclick="ies.deleteAttachment(${at.id})"
                                       style="border:none;background:#fee2e2;color:#b91c1c;border-radius:3px;font-size:10px;padding:1px 5px;cursor:pointer;"
                                       title="O'chirish">✕</button>
                                </div>
                            `).join('');
                            attachBlock = `
                                <div style="margin-top:8px;padding-top:6px;border-top:1px dashed #e2e8f0;">
                                    <div style="font-size:10px;color:#64748b;font-weight:600;margin-bottom:3px;">📎 Asoslovchi hujjatlar</div>
                                    ${attList || '<div style="font-size:11px;color:#94a3b8;">Ilova qilinmagan</div>'}
                                    <div style="margin-top:4px;">
                                        <input type="file" id="att-input-${sIdx}" style="display:none;"
                                            onchange="ies.uploadAttachment(this, '${escapeHtml(subj.subject_id)}', '${escapeHtml(subj.semester_code)}')">
                                        <button type="button" onclick="document.getElementById('att-input-${sIdx}').click()"
                                            style="padding:3px 7px;background:#e0f2fe;color:#0284c7;border:1px solid #bae6fd;border-radius:4px;font-size:11px;font-weight:600;cursor:pointer;">
                                            + Hujjat yuklash
                                        </button>
                                    </div>
                                </div>
                            `;
                        }

                        const subjectCell = isFirstOfSubject
                            ? `<td style="padding:8px 10px;vertical-align:top;border-top:2px solid #e2e8f0;" rowspan="${attemptCount}">
                                <div style="font-weight:600;color:#1e293b;">${escapeHtml(subj.subject_name)}</div>
                                <div style="font-size:11px;color:#64748b;">${escapeHtml(subj.semester_code)}</div>
                                ${cfBadge}
                                ${attachBlock}
                               </td>`
                            : '';

                        // Faol qator uchun nozik fon
                        const rowBg = isActiveRow ? 'background:#fffbeb;' : '';

                        html += `
                            <tr style="border-bottom:1px solid #f1f5f9;${isFirstOfSubject ? 'border-top:2px solid #e2e8f0;' : ''}${rowBg}" id="${rowId}">
                                ${subjectCell}
                                <td style="padding:6px 10px;text-align:center;">${urinishBadge}</td>
                                <td style="padding:6px 10px;text-align:center;color:#475569;">
                                    ${groupDate ? escapeHtml(formatDate(groupDate)) + (groupTime ? '<br><span style="font-size:11px;color:#94a3b8;">' + escapeHtml(groupTime.substring(0,5)) + '</span>' : '') : '<span style="color:#cbd5e1;">—</span>'}
                                </td>
                                <td style="padding:6px 10px;text-align:center;">${statusBadge}</td>
                                <td style="padding:6px 10px;text-align:center;">
                                    <input type="date" data-row="${rowId}" data-yn="${a.ynType}" data-att="${a.attempt}"
                                        data-subject-id="${escapeHtml(subj.subject_id)}" data-sem="${escapeHtml(subj.semester_code)}"
                                        data-subject-name="${escapeHtml(subj.subject_name)}"
                                        value="${escapeHtml(indDate)}" class="ies-date-input"
                                        style="width:130px;padding:4px 6px;border:1px solid #cbd5e1;border-radius:6px;font-size:12px;">
                                </td>
                                <td style="padding:6px 10px;text-align:center;">
                                    <input type="time" data-row="${rowId}" class="ies-time-input"
                                        value="${escapeHtml(indTime ? indTime.substring(0,5) : '')}"
                                        style="width:80px;padding:4px 6px;border:1px solid #cbd5e1;border-radius:6px;font-size:12px;">
                                </td>
                                <td style="padding:6px 10px;">
                                    <input type="text" data-row="${rowId}" class="ies-note-input"
                                        value="${escapeHtml(indNote)}" placeholder="Izoh / sabab..."
                                        style="width:100%;min-width:140px;padding:4px 6px;border:1px solid #cbd5e1;border-radius:6px;font-size:12px;">
                                </td>
                                <td style="padding:6px 10px;text-align:center;white-space:nowrap;">
                                    <button type="button" class="ies-save-btn" onclick="ies.saveRow('${rowId}', ${allowed ? 'true' : 'false'})"
                                        style="padding:5px 10px;background:#10b981;color:#fff;border:none;border-radius:5px;font-size:11px;font-weight:600;cursor:pointer;">
                                        Saqlash
                                    </button>
                                    ${indDate ? `<button type="button" onclick="ies.clearRow('${rowId}')"
                                        style="padding:5px 8px;background:#fee2e2;color:#b91c1c;border:none;border-radius:5px;font-size:11px;font-weight:600;cursor:pointer;margin-left:3px;"
                                        title="Individual sanani o'chirish">✕</button>` : ''}
                                </td>
                            </tr>
                        `;
                    });
                });
                html += '</tbody></table></div>';
                $('subjects-wrap').style.display = 'block';
                $('subjects-table-wrap').innerHTML = html;
            },

            renderAudits(audits) {
                if (audits.length === 0) {
                    $('audit-wrap').style.display = 'none';
                    return;
                }
                $('audit-wrap').style.display = 'block';
                const actionLabel = {
                    'set': 'Qo\'yildi',
                    'update': 'Yangilandi',
                    'clear': 'O\'chirildi',
                };
                $('audit-list').innerHTML = audits.map(a => {
                    const colorMap = { 'set': '#10b981', 'update': '#0284c7', 'clear': '#b91c1c' };
                    const c = colorMap[a.action] || '#475569';
                    const dateRow = a.action === 'clear'
                        ? `<s style="color:#94a3b8;">${escapeHtml(formatDate(a.old_date || ''))} ${escapeHtml(a.old_time ? a.old_time.substring(0,5) : '')}</s>`
                        : (a.old_date
                            ? `<s style="color:#94a3b8;">${escapeHtml(formatDate(a.old_date))} ${escapeHtml(a.old_time ? a.old_time.substring(0,5) : '')}</s> → <b>${escapeHtml(formatDate(a.new_date || ''))}</b> ${escapeHtml(a.new_time ? a.new_time.substring(0,5) : '')}`
                            : `<b>${escapeHtml(formatDate(a.new_date || ''))}</b> ${escapeHtml(a.new_time ? a.new_time.substring(0,5) : '')}`);
                    return `
                        <div style="padding:8px 10px;border-bottom:1px solid #f1f5f9;display:flex;gap:12px;">
                            <div style="min-width:130px;color:#64748b;font-size:11px;">${escapeHtml(a.created_at)}</div>
                            <div style="min-width:140px;font-weight:600;color:#1e293b;">${escapeHtml(a.actor_name || '—')}<div style="font-size:10px;color:#94a3b8;font-weight:400;">${escapeHtml(a.actor_role || '')}</div></div>
                            <div style="flex:1;">
                                <span style="color:${c};font-weight:700;">${actionLabel[a.action] || a.action}</span> ·
                                <span style="font-weight:600;color:#475569;">${escapeHtml(a.subject_name)}</span>
                                <span style="color:#64748b;">${a.attempt}-${a.yn_type === 'oski' ? 'OSKI' : 'Test'}</span>
                                <div style="font-size:12px;margin-top:2px;">${dateRow}</div>
                                ${a.note ? `<div style="font-size:12px;color:#475569;margin-top:2px;font-style:italic;">"${escapeHtml(a.note)}"</div>` : ''}
                                ${a.override_warning ? `<div style="font-size:11px;color:#a16207;margin-top:2px;">⚠️ Ogohlantirish bilan: eligibility yo'q edi</div>` : ''}
                            </div>
                        </div>
                    `;
                }).join('');
            },

            async saveRow(rowId, allowed) {
                const row = $(rowId);
                const dateInput = row.querySelector('.ies-date-input');
                const timeInput = row.querySelector('.ies-time-input');
                const noteInput = row.querySelector('.ies-note-input');
                const subjectId = dateInput.dataset.subjectId;
                const sem = dateInput.dataset.sem;
                const yn = dateInput.dataset.yn;
                const att = parseInt(dateInput.dataset.att);
                const date = dateInput.value;
                const time = timeInput.value;
                const note = noteInput.value.trim();

                if (!date) {
                    alert('Sana kiriting yoki "✕" tugmasi orqali o\'chiring.');
                    return;
                }

                let override = false;
                if (!allowed) {
                    if (!note) {
                        alert('Eligibility ruxsat bermayapti. Saqlash uchun izoh maydonini to\'ldiring.');
                        noteInput.focus();
                        return;
                    }
                    if (!confirm('Eligibility ruxsat bermayapti. Majburan saqlanadimi?\n\nIzoh: ' + note)) return;
                    override = true;
                }

                const payload = {
                    student_hemis_id: this.currentStudent.hemis_id,
                    subject_id: subjectId,
                    semester_code: sem,
                    yn_type: yn,
                    attempt: att,
                    date: date,
                    time: time || null,
                    note: note || null,
                    override: override,
                };

                try {
                    const resp = await fetch(SAVE_URL, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': CSRF,
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify(payload),
                    });
                    const data = await resp.json();
                    if (!resp.ok) {
                        alert(data.error || 'Xato yuz berdi');
                        return;
                    }
                    // Sahifani yangilash (sanani ko'rish)
                    await this.loadStudent(this.currentStudent.hemis_id);
                } catch (e) {
                    alert('Saqlashda xato: ' + e.message);
                }
            },

            async clearRow(rowId) {
                const row = $(rowId);
                const dateInput = row.querySelector('.ies-date-input');
                const noteInput = row.querySelector('.ies-note-input');
                const subjectId = dateInput.dataset.subjectId;
                const sem = dateInput.dataset.sem;
                const yn = dateInput.dataset.yn;
                const att = parseInt(dateInput.dataset.att);
                const note = noteInput.value.trim();

                if (!confirm('Individual sanani o\'chirib, guruh sanasiga qaytaramizmi?')) return;

                try {
                    const resp = await fetch(CLEAR_URL, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': CSRF,
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({
                            student_hemis_id: this.currentStudent.hemis_id,
                            subject_id: subjectId,
                            semester_code: sem,
                            yn_type: yn,
                            attempt: att,
                            note: note || null,
                        }),
                    });
                    const data = await resp.json();
                    if (!resp.ok) {
                        alert(data.error || 'Xato');
                        return;
                    }
                    await this.loadStudent(this.currentStudent.hemis_id);
                } catch (e) {
                    alert('O\'chirishda xato: ' + e.message);
                }
            },

            async uploadAttachment(input, subjectId, sem) {
                if (!input.files || input.files.length === 0) return;
                const file = input.files[0];
                if (file.size > 10 * 1024 * 1024) {
                    alert('Fayl hajmi 10MB dan oshmasligi kerak.');
                    input.value = '';
                    return;
                }
                const note = prompt('Fayl haqida qisqa izoh (masalan: "Pullik xizmat kvitansiyasi", "Tibbiy spravka"). Ixtiyoriy:', '');
                if (note === null) {
                    input.value = '';
                    return; // Bekor qilindi
                }

                const formData = new FormData();
                formData.append('file', file);
                formData.append('student_hemis_id', this.currentStudent.hemis_id);
                formData.append('subject_id', subjectId);
                formData.append('semester_code', sem);
                if (note.trim()) formData.append('note', note.trim());

                try {
                    const resp = await fetch(ATTACH_UPLOAD_URL, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                        body: formData,
                    });
                    const data = await resp.json();
                    if (!resp.ok) {
                        alert(data.error || 'Yuklashda xato.');
                        input.value = '';
                        return;
                    }
                    input.value = '';
                    await this.loadStudent(this.currentStudent.hemis_id);
                } catch (e) {
                    alert('Yuklashda xato: ' + e.message);
                    input.value = '';
                }
            },

            async deleteAttachment(attachmentId) {
                if (!confirm('Bu hujjatni o\'chiramizmi?')) return;
                try {
                    const resp = await fetch(ATTACH_DELETE_URL + '/' + attachmentId + '/delete', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': CSRF,
                            'Accept': 'application/json',
                        },
                    });
                    const data = await resp.json();
                    if (!resp.ok) {
                        alert(data.error || 'O\'chirishda xato.');
                        return;
                    }
                    await this.loadStudent(this.currentStudent.hemis_id);
                } catch (e) {
                    alert('O\'chirishda xato: ' + e.message);
                }
            },
        };

        // Enter bosishda qidirish
        $('student-query').addEventListener('keydown', (e) => {
            if (e.key === 'Enter') ies.search();
        });
    })();
    </script>
</x-app-layout>
