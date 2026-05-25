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
        const CSRF = "{{ csrf_token() }}";

        const $ = (id) => document.getElementById(id);
        const escapeHtml = (s) => String(s ?? '')
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');

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
                subjects.forEach((subj, sIdx) => {
                    const attempts = [
                        { ynType: 'oski', attempt: 1, label: '1-OSKI', dateK: 'oski_date', timeK: 'oski_time' },
                        { ynType: 'test', attempt: 1, label: '1-Test', dateK: 'test_date', timeK: 'test_time' },
                        { ynType: 'oski', attempt: 2, label: '2-OSKI', dateK: 'oski_resit_date', timeK: 'oski_resit_time' },
                        { ynType: 'test', attempt: 2, label: '2-Test', dateK: 'test_resit_date', timeK: 'test_resit_time' },
                        { ynType: 'oski', attempt: 3, label: '3-OSKI', dateK: 'oski_resit2_date', timeK: 'oski_resit2_time' },
                        { ynType: 'test', attempt: 3, label: '3-Test', dateK: 'test_resit2_date', timeK: 'test_resit2_time' },
                    ];
                    attempts.forEach((a, aIdx) => {
                        const groupDate = subj.group ? (subj.group[a.dateK] || '') : '';
                        const groupTime = subj.group ? (subj.group[a.timeK] || '') : '';
                        const indDate = subj.individual ? (subj.individual[a.dateK] || '') : '';
                        const indTime = subj.individual ? (subj.individual[a.timeK] || '') : '';
                        const indNote = subj.individual ? (subj.individual.note || '') : '';
                        const overrideWarn = subj.individual ? !!subj.individual.override_warning : false;
                        const eligAllowKey = 'allow_' + a.attempt;
                        const elig = subj.eligibility || {};
                        const allowed = !!elig[eligAllowKey];
                        const reasons = elig.reasons || {};
                        const reason = reasons[a.attempt] || '';

                        // Status badge
                        let statusBadge;
                        if (a.attempt === 1) {
                            statusBadge = '<span style="background:#dcfce7;color:#15803d;padding:2px 8px;border-radius:6px;font-size:11px;font-weight:600;">Ruxsat ✓</span>';
                        } else {
                            if (allowed) {
                                statusBadge = '<span style="background:#dcfce7;color:#15803d;padding:2px 8px;border-radius:6px;font-size:11px;font-weight:600;">Ruxsat ✓</span>';
                            } else {
                                statusBadge = `<span style="background:#fee2e2;color:#b91c1c;padding:2px 8px;border-radius:6px;font-size:11px;font-weight:600;" title="${escapeHtml(reason)}">Ruxsat ❌</span>`;
                            }
                        }
                        if (subj.individual && indDate) {
                            statusBadge += ' <span style="background:#dbeafe;color:#1d4ed8;padding:2px 8px;border-radius:6px;font-size:11px;font-weight:600;margin-left:4px;">Individual</span>';
                        }
                        if (overrideWarn && indDate) {
                            statusBadge += ' <span style="background:#fef3c7;color:#a16207;padding:2px 8px;border-radius:6px;font-size:11px;font-weight:600;margin-left:4px;" title="Eligibility yo\\'q edi, majburan qo\\'yilgan">⚠️</span>';
                        }

                        const rowId = `r-${sIdx}-${aIdx}`;
                        const isFirstOfSubject = aIdx === 0;
                        const subjectCell = isFirstOfSubject
                            ? `<td style="padding:8px 10px;vertical-align:top;border-top:2px solid #e2e8f0;" rowspan="6">
                                <div style="font-weight:600;color:#1e293b;">${escapeHtml(subj.subject_name)}</div>
                                <div style="font-size:11px;color:#64748b;">${escapeHtml(subj.semester_code)}</div>
                               </td>`
                            : '';

                        html += `
                            <tr style="border-bottom:1px solid #f1f5f9;${isFirstOfSubject ? 'border-top:2px solid #e2e8f0;' : ''}" id="${rowId}">
                                ${subjectCell}
                                <td style="padding:6px 10px;text-align:center;font-weight:600;color:#475569;">${a.label}</td>
                                <td style="padding:6px 10px;text-align:center;color:#475569;">
                                    ${groupDate ? escapeHtml(groupDate) + (groupTime ? '<br><span style="font-size:11px;color:#94a3b8;">' + escapeHtml(groupTime.substring(0,5)) + '</span>' : '') : '<span style="color:#cbd5e1;">—</span>'}
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
                        ? `<s style="color:#94a3b8;">${escapeHtml(a.old_date || '')} ${escapeHtml(a.old_time ? a.old_time.substring(0,5) : '')}</s>`
                        : (a.old_date
                            ? `<s style="color:#94a3b8;">${escapeHtml(a.old_date)} ${escapeHtml(a.old_time ? a.old_time.substring(0,5) : '')}</s> → <b>${escapeHtml(a.new_date || '')}</b> ${escapeHtml(a.new_time ? a.new_time.substring(0,5) : '')}`
                            : `<b>${escapeHtml(a.new_date || '')}</b> ${escapeHtml(a.new_time ? a.new_time.substring(0,5) : '')}`);
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
        };

        // Enter bosishda qidirish
        $('student-query').addEventListener('keydown', (e) => {
            if (e.key === 'Enter') ies.search();
        });
    })();
    </script>
</x-app-layout>
