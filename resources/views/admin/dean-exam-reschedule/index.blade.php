<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            Dekanat — guruhning YN vaqtini boshqa vaqtga o'tkazish
        </h2>
    </x-slot>

    <div class="py-4">
        <div class="max-w-full mx-auto sm:px-4 lg:px-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">

                <div style="padding:14px 18px;border-bottom:1px solid #e5e7eb;background:#f8fafc;display:flex;flex-wrap:wrap;align-items:center;gap:8px;">
                    <span style="background:#1a3268;color:#fff;font-size:11px;font-weight:600;padding:3px 8px;border-radius:999px;">
                        Dekanat
                    </span>
                    <span style="color:#64748b;font-size:13px;">
                        Guruh kech qolsa, fakultet dekani guruhning butun YN vaqtini SHU KUN ichida boshqa
                        vaqtga o'tkaza oladi. Har guruhga kunlik <strong>1 marta</strong>.
                    </span>
                </div>

                <form method="GET" action="{{ route('admin.dean-exam-reschedule.index') }}"
                      style="padding:18px;display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;border-bottom:1px solid #e5e7eb;">
                    <div style="display:flex;flex-direction:column;gap:4px;">
                        <label for="date" style="font-size:12px;color:#475569;font-weight:600;">Sana</label>
                        <input type="date" id="date" name="date" value="{{ $date }}"
                               style="height:36px;border:1px solid #cbd5e1;border-radius:8px;padding:0 10px;font-size:13px;color:#1e293b;background:#fff;outline:none;" />
                    </div>
                    <button type="submit"
                            style="height:36px;background:#1a3268;color:#fff;border:0;border-radius:8px;padding:0 16px;font-size:13px;font-weight:600;cursor:pointer;">
                        Ko'rsatish
                    </button>
                    <div style="margin-left:auto;color:#64748b;font-size:13px;">
                        Slotlar jami: <strong style="color:#0f172a;">{{ count($availableSlots) }}</strong>
                        @php $enoughCount = collect($availableSlots)->where('enough', true)->count(); @endphp
                        <span style="color:#94a3b8;">·</span>
                        kamida 1 bo'shi bor: <strong style="color:#0f172a;">{{ $enoughCount }}</strong>
                    </div>
                </form>

                <div style="overflow-x:auto;">
                    <table style="width:100%;border-collapse:collapse;font-size:13px;">
                        <thead>
                            <tr style="background:#f1f5f9;color:#0f172a;">
                                <th style="text-align:left;padding:10px 12px;border-bottom:1px solid #e2e8f0;font-weight:600;">№</th>
                                <th style="text-align:left;padding:10px 12px;border-bottom:1px solid #e2e8f0;font-weight:600;">Joriy vaqt</th>
                                <th style="text-align:left;padding:10px 12px;border-bottom:1px solid #e2e8f0;font-weight:600;">YN turi</th>
                                <th style="text-align:left;padding:10px 12px;border-bottom:1px solid #e2e8f0;font-weight:600;">Guruh</th>
                                <th style="text-align:left;padding:10px 12px;border-bottom:1px solid #e2e8f0;font-weight:600;">Fan</th>
                                <th style="text-align:right;padding:10px 12px;border-bottom:1px solid #e2e8f0;font-weight:600;">Talabalar</th>
                                <th style="text-align:left;padding:10px 12px;border-bottom:1px solid #e2e8f0;font-weight:600;">Amal</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($rows as $i => $r)
                                @php
                                    $key = $r->exam_schedule_id . '|' . $r->yn_type;
                                    $alreadyUsed = isset($usedSet[$key]);
                                @endphp
                                <tr style="border-bottom:1px solid #f1f5f9;">
                                    <td style="padding:9px 12px;color:#64748b;">{{ $i + 1 }}</td>
                                    <td style="padding:9px 12px;font-weight:600;color:#0f172a;white-space:nowrap;">
                                        {{ $r->current_time }}
                                    </td>
                                    <td style="padding:9px 12px;">
                                        <span style="font-size:11px;padding:2px 7px;border-radius:999px;
                                            background:{{ $r->yn_type === 'test' ? '#dbeafe' : '#fef3c7' }};
                                            color:{{ $r->yn_type === 'test' ? '#1e40af' : '#92400e' }};">
                                            {{ strtoupper($r->yn_type) }}
                                        </span>
                                    </td>
                                    <td style="padding:9px 12px;font-weight:600;color:#0f172a;">{{ $r->group_name }}</td>
                                    <td style="padding:9px 12px;color:#475569;">
                                        @if($r->subject_id)
                                            <span style="color:#64748b;font-size:11px;">{{ $r->subject_id }}</span><br/>
                                        @endif
                                        {{ $r->subject_name ?? '—' }}
                                    </td>
                                    <td style="padding:9px 12px;text-align:right;color:#0f172a;">{{ $r->student_count }}</td>
                                    <td style="padding:9px 12px;">
                                        @if($alreadyUsed)
                                            <span style="font-size:11px;padding:3px 8px;border-radius:6px;background:#f1f5f9;color:#64748b;">
                                                Bugun foydalanilgan
                                            </span>
                                        @else
                                            <button type="button"
                                                    onclick="openReschedule({{ $r->exam_schedule_id }}, '{{ $r->yn_type }}', '{{ addslashes($r->group_name) }}', '{{ $r->current_time }}', {{ $r->student_count }})"
                                                    style="height:30px;background:#1a3268;color:#fff;border:0;border-radius:6px;padding:0 12px;font-size:12px;font-weight:600;cursor:pointer;">
                                                Vaqtni o'zgartirish
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" style="padding:48px 20px;text-align:center;color:#94a3b8;">
                                        Tanlangan kun uchun fakultetingiz guruhlariga vaqti qo'yilgan imtihon yo'q.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Reschedule modali --}}
    <div id="rescheduleModal" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,0.45);z-index:1000;align-items:center;justify-content:center;padding:16px;">
        <div style="background:#fff;border-radius:12px;max-width:520px;width:100%;box-shadow:0 20px 50px rgba(0,0,0,0.25);">
            <div style="padding:18px 22px;border-bottom:1px solid #e5e7eb;display:flex;align-items:center;justify-content:space-between;">
                <h3 style="margin:0;font-size:16px;font-weight:600;color:#0f172a;">Guruh uchun yangi vaqt belgilash</h3>
                <button type="button" onclick="closeReschedule()" style="background:transparent;border:0;font-size:20px;color:#64748b;cursor:pointer;">&times;</button>
            </div>
            <div style="padding:22px;">
                <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:12px;margin-bottom:18px;">
                    <div style="font-size:12px;color:#64748b;margin-bottom:4px;">Guruh</div>
                    <div id="rmGroup" style="font-size:14px;color:#0f172a;font-weight:600;"></div>
                    <div style="font-size:12px;color:#64748b;margin-top:8px;">Joriy vaqt</div>
                    <div id="rmOrig" style="font-size:14px;color:#0f172a;"></div>
                    <div style="font-size:12px;color:#64748b;margin-top:8px;">Talabalar soni</div>
                    <div id="rmCount" style="font-size:14px;color:#0f172a;"></div>
                </div>

                <label style="font-size:12px;color:#475569;font-weight:600;display:block;margin-bottom:6px;">
                    Yangi vaqt
                </label>
                <select id="rmTime" onchange="onSlotChanged()"
                        style="width:100%;height:38px;border:1px solid #cbd5e1;border-radius:8px;padding:0 10px;font-size:13px;color:#1e293b;background:#fff;outline:none;">
                    <option value="">— yuklanmoqda —</option>
                </select>
                <div id="rmSlotInfo" style="margin-top:6px;color:#64748b;font-size:11px;"></div>

                <div id="rmWarning" style="display:none;margin-top:10px;padding:10px 12px;border:1px solid #fed7aa;background:#fff7ed;border-radius:8px;color:#9a3412;font-size:12px;line-height:1.5;">
                    <strong>Diqqat:</strong> tanlangan slotda yetarli bo'sh kompyuter yo'q.
                    <span id="rmWarningDetail"></span>
                    <label style="display:flex;align-items:center;gap:6px;margin-top:8px;cursor:pointer;">
                        <input type="checkbox" id="rmForce" onchange="updateSubmitState()" style="cursor:pointer;" />
                        <span>Tasdiqlayman — sig'imdan ortiq bo'lsa ham o'tkazib bering</span>
                    </label>
                </div>

                <label style="font-size:12px;color:#475569;font-weight:600;display:block;margin-top:14px;margin-bottom:6px;">Izoh (ixtiyoriy)</label>
                <textarea id="rmReason" rows="3" placeholder="Masalan: guruh kech qoldi, tushuntirish xati keltirildi..."
                          style="width:100%;border:1px solid #cbd5e1;border-radius:8px;padding:8px 10px;font-size:13px;color:#1e293b;background:#fff;outline:none;resize:vertical;"></textarea>

                <div id="rmError" style="margin-top:12px;color:#dc2626;font-size:12px;display:none;"></div>
            </div>
            <div style="padding:14px 22px;border-top:1px solid #e5e7eb;display:flex;justify-content:flex-end;gap:8px;">
                <button type="button" onclick="closeReschedule()"
                        style="height:36px;background:#fff;color:#475569;border:1px solid #cbd5e1;border-radius:8px;padding:0 16px;font-size:13px;font-weight:600;cursor:pointer;">
                    Bekor qilish
                </button>
                <button type="button" id="rmSubmit" onclick="submitReschedule()"
                        style="height:36px;background:#1a3268;color:#fff;border:0;border-radius:8px;padding:0 16px;font-size:13px;font-weight:600;cursor:pointer;">
                    Saqlash
                </button>
            </div>
        </div>
    </div>

    <script>
        let rmScheduleId = null;
        let rmYnType = null;
        let rmStudentCount = 0;
        let rmSlots = [];
        const SLOTS_URL = '{{ route('admin.dean-exam-reschedule.slots') }}';
        const STORE_URL = '{{ route('admin.dean-exam-reschedule.store') }}';
        const PAGE_DATE = '{{ $date }}';

        async function openReschedule(scheduleId, ynType, groupName, currentTime, studentCount) {
            rmScheduleId = scheduleId;
            rmYnType = ynType;
            rmStudentCount = studentCount;
            document.getElementById('rmGroup').textContent = groupName || '—';
            document.getElementById('rmOrig').textContent = currentTime || '—';
            document.getElementById('rmCount').textContent = studentCount + ' ta';
            document.getElementById('rmReason').value = '';
            document.getElementById('rmError').style.display = 'none';
            document.getElementById('rmSlotInfo').textContent = '';
            document.getElementById('rmWarning').style.display = 'none';
            document.getElementById('rmForce').checked = false;

            const sel = document.getElementById('rmTime');
            sel.innerHTML = '<option value="">— yuklanmoqda —</option>';
            document.getElementById('rescheduleModal').style.display = 'flex';

            try {
                const url = new URL(SLOTS_URL, location.origin);
                url.searchParams.set('date', PAGE_DATE);
                url.searchParams.set('required_free', studentCount);
                url.searchParams.set('exam_schedule_id', scheduleId);
                url.searchParams.set('yn_type', ynType);
                const resp = await fetch(url, { headers: { 'Accept': 'application/json' }});
                const data = await resp.json();
                rmSlots = data.slots || [];
                if (!rmSlots.length) {
                    sel.innerHTML = '<option value="">— slot yo\'q —</option>';
                    document.getElementById('rmSlotInfo').textContent =
                        'Bugun uchun bo\'sh slot qolmadi (ish vaqti tugadi).';
                    return;
                }
                sel.innerHTML = '<option value="">— tanlang —</option>' + rmSlots.map(s => {
                    const label = `${s.time} (${s.free}/${s.capacity} bo'sh)`;
                    const suffix = s.enough ? '' : ' ⚠ yetmaydi';
                    return `<option value="${s.time}" data-enough="${s.enough ? '1' : '0'}" data-free="${s.free}">${label}${suffix}</option>`;
                }).join('');
                onSlotChanged();
            } catch (e) {
                sel.innerHTML = '<option value="">— xatolik —</option>';
                document.getElementById('rmSlotInfo').textContent = 'Slotlarni yuklab bo\'lmadi: ' + e.message;
            }
        }

        function onSlotChanged() {
            const sel = document.getElementById('rmTime');
            const opt = sel.options[sel.selectedIndex];
            const warning = document.getElementById('rmWarning');
            const detail = document.getElementById('rmWarningDetail');
            const force = document.getElementById('rmForce');

            if (!opt || !opt.value) {
                warning.style.display = 'none';
                force.checked = false;
                updateSubmitState();
                return;
            }

            const enough = opt.dataset.enough === '1';
            if (enough) {
                warning.style.display = 'none';
                force.checked = false;
            } else {
                warning.style.display = 'block';
                const free = parseInt(opt.dataset.free || '0', 10);
                detail.textContent = ` Kerak: ${rmStudentCount} ta. Bo'sh: ${free} ta.`;
            }
            updateSubmitState();
        }

        function updateSubmitState() {
            const sel = document.getElementById('rmTime');
            const opt = sel.options[sel.selectedIndex];
            const btn = document.getElementById('rmSubmit');
            const force = document.getElementById('rmForce');

            if (!opt || !opt.value) {
                btn.disabled = false;
                return;
            }
            const enough = opt.dataset.enough === '1';
            if (!enough && !force.checked) {
                btn.disabled = true;
                btn.style.opacity = '0.5';
                btn.style.cursor = 'not-allowed';
            } else {
                btn.disabled = false;
                btn.style.opacity = '1';
                btn.style.cursor = 'pointer';
            }
        }

        function closeReschedule() {
            document.getElementById('rescheduleModal').style.display = 'none';
            rmScheduleId = null;
            rmYnType = null;
        }

        async function submitReschedule() {
            if (!rmScheduleId || !rmYnType) return;
            const sel = document.getElementById('rmTime');
            const opt = sel.options[sel.selectedIndex];
            const time = sel.value;
            const reason = document.getElementById('rmReason').value;
            const force = document.getElementById('rmForce').checked;
            const errBox = document.getElementById('rmError');
            const btn = document.getElementById('rmSubmit');

            if (!time) {
                errBox.textContent = 'Yangi vaqtni tanlang.';
                errBox.style.display = 'block';
                return;
            }
            const enough = opt && opt.dataset.enough === '1';
            if (!enough && !force) {
                errBox.textContent = 'Sig\'im yetarli emas — tasdiqlash katakchasini belgilang.';
                errBox.style.display = 'block';
                return;
            }

            errBox.style.display = 'none';
            btn.disabled = true;
            btn.textContent = 'Saqlanmoqda...';

            try {
                const resp = await fetch(STORE_URL, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    },
                    body: JSON.stringify({
                        exam_schedule_id: rmScheduleId,
                        yn_type: rmYnType,
                        new_time: time,
                        reason: reason || null,
                        force: force,
                    }),
                });
                const data = await resp.json();
                if (data.ok) {
                    location.reload();
                    return;
                }
                errBox.textContent = data.error || 'Saqlashda xatolik.';
                errBox.style.display = 'block';
            } catch (e) {
                errBox.textContent = 'Tarmoq xatoligi: ' + e.message;
                errBox.style.display = 'block';
            } finally {
                btn.disabled = false;
                btn.textContent = 'Saqlash';
            }
        }
    </script>
</x-app-layout>
