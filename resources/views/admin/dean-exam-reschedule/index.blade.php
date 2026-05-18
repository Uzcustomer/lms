<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            Dekanat — kech qolgan talabani boshqa vaqtga o'tkazish
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
                        Talaba kech qolsa, fakultet dekani uni SHU KUN ichida boshqa bo'sh vaqtga o'tkaza oladi.
                        Har talabaga kunlik <strong>1 marta</strong>.
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
                        Bo'sh slotlar: <strong style="color:#0f172a;">{{ count($availableSlots) }}</strong>
                    </div>
                </form>

                <div style="overflow-x:auto;">
                    <table style="width:100%;border-collapse:collapse;font-size:13px;">
                        <thead>
                            <tr style="background:#f1f5f9;color:#0f172a;">
                                <th style="text-align:left;padding:10px 12px;border-bottom:1px solid #e2e8f0;font-weight:600;">№</th>
                                <th style="text-align:left;padding:10px 12px;border-bottom:1px solid #e2e8f0;font-weight:600;">Vaqt</th>
                                <th style="text-align:left;padding:10px 12px;border-bottom:1px solid #e2e8f0;font-weight:600;">PC</th>
                                <th style="text-align:left;padding:10px 12px;border-bottom:1px solid #e2e8f0;font-weight:600;">Talaba</th>
                                <th style="text-align:left;padding:10px 12px;border-bottom:1px solid #e2e8f0;font-weight:600;">ID raqami</th>
                                <th style="text-align:left;padding:10px 12px;border-bottom:1px solid #e2e8f0;font-weight:600;">Fan</th>
                                <th style="text-align:left;padding:10px 12px;border-bottom:1px solid #e2e8f0;font-weight:600;">Status</th>
                                <th style="text-align:left;padding:10px 12px;border-bottom:1px solid #e2e8f0;font-weight:600;">Amal</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($assignments as $i => $a)
                                @php
                                    $alreadyUsed = isset($usedHemisIds[$a->student_hemis_id]);
                                @endphp
                                <tr style="border-bottom:1px solid #f1f5f9;">
                                    <td style="padding:9px 12px;color:#64748b;">{{ $i + 1 }}</td>
                                    <td style="padding:9px 12px;color:#0f172a;white-space:nowrap;font-weight:600;">
                                        {{ $a->planned_start?->format('H:i') }}–{{ $a->planned_end?->format('H:i') }}
                                    </td>
                                    <td style="padding:9px 12px;color:#0f172a;">#{{ $a->computer_number }}</td>
                                    <td style="padding:9px 12px;color:#0f172a;">{{ $a->student?->full_name ?? '—' }}</td>
                                    <td style="padding:9px 12px;color:#475569;">{{ $a->student_id_number }}</td>
                                    <td style="padding:9px 12px;color:#475569;">{{ $a->examSchedule?->subject_name ?? '—' }}</td>
                                    <td style="padding:9px 12px;">
                                        <span style="font-size:11px;padding:2px 7px;border-radius:999px;
                                            background:{{ $a->status === 'abandoned' ? '#fee2e2' : ($a->status === 'in_progress' ? '#fef3c7' : '#dbeafe') }};
                                            color:{{ $a->status === 'abandoned' ? '#991b1b' : ($a->status === 'in_progress' ? '#92400e' : '#1e40af') }};">
                                            {{ $a->status }}
                                        </span>
                                    </td>
                                    <td style="padding:9px 12px;">
                                        @if($alreadyUsed)
                                            <span style="font-size:11px;padding:3px 8px;border-radius:6px;background:#f1f5f9;color:#64748b;">
                                                Bugun foydalanilgan
                                            </span>
                                        @else
                                            <button type="button"
                                                    onclick="openReschedule({{ $a->id }}, '{{ addslashes($a->student?->full_name ?? '') }}', '{{ $a->planned_start?->format('H:i') }}')"
                                                    style="height:30px;background:#1a3268;color:#fff;border:0;border-radius:6px;padding:0 12px;font-size:12px;font-weight:600;cursor:pointer;">
                                                Vaqtni o'zgartirish
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" style="padding:48px 20px;text-align:center;color:#94a3b8;">
                                        Tanlangan kun uchun fakultetingiz talabalariga imtihon belgilanmagan.
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
                <h3 style="margin:0;font-size:16px;font-weight:600;color:#0f172a;">Yangi vaqt belgilash</h3>
                <button type="button" onclick="closeReschedule()" style="background:transparent;border:0;font-size:20px;color:#64748b;cursor:pointer;">&times;</button>
            </div>
            <div style="padding:22px;">
                <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:12px;margin-bottom:18px;">
                    <div style="font-size:12px;color:#64748b;margin-bottom:4px;">Talaba</div>
                    <div id="rmStudent" style="font-size:14px;color:#0f172a;font-weight:600;"></div>
                    <div style="font-size:12px;color:#64748b;margin-top:8px;">Asl vaqt</div>
                    <div id="rmOrig" style="font-size:14px;color:#0f172a;"></div>
                </div>

                <label style="font-size:12px;color:#475569;font-weight:600;display:block;margin-bottom:6px;">Yangi vaqt (bo'sh slotlardan)</label>
                <select id="rmTime" style="width:100%;height:38px;border:1px solid #cbd5e1;border-radius:8px;padding:0 10px;font-size:13px;color:#1e293b;background:#fff;outline:none;">
                    <option value="">— tanlang —</option>
                    @foreach($availableSlots as $slot)
                        <option value="{{ $slot['time'] }}">
                            {{ $slot['time'] }} ({{ $slot['free'] }}/{{ $slot['capacity'] }} bo'sh)
                        </option>
                    @endforeach
                </select>
                @if(empty($availableSlots))
                    <div style="margin-top:8px;color:#dc2626;font-size:12px;">
                        Bugun uchun bo'sh slot qolmadi.
                    </div>
                @endif

                <label style="font-size:12px;color:#475569;font-weight:600;display:block;margin-top:14px;margin-bottom:6px;">Izoh (ixtiyoriy)</label>
                <textarea id="rmReason" rows="3" placeholder="Masalan: talaba tushuntirish xati keltirdi..."
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
        let rmAssignmentId = null;

        function openReschedule(id, name, origTime) {
            rmAssignmentId = id;
            document.getElementById('rmStudent').textContent = name || '—';
            document.getElementById('rmOrig').textContent = origTime || '—';
            document.getElementById('rmTime').value = '';
            document.getElementById('rmReason').value = '';
            document.getElementById('rmError').style.display = 'none';
            document.getElementById('rescheduleModal').style.display = 'flex';
        }

        function closeReschedule() {
            document.getElementById('rescheduleModal').style.display = 'none';
            rmAssignmentId = null;
        }

        async function submitReschedule() {
            if (!rmAssignmentId) return;
            const time = document.getElementById('rmTime').value;
            const reason = document.getElementById('rmReason').value;
            const errBox = document.getElementById('rmError');
            const btn = document.getElementById('rmSubmit');

            if (!time) {
                errBox.textContent = 'Yangi vaqtni tanlang.';
                errBox.style.display = 'block';
                return;
            }

            errBox.style.display = 'none';
            btn.disabled = true;
            btn.textContent = 'Saqlanmoqda...';

            try {
                const resp = await fetch('{{ route('admin.dean-exam-reschedule.store') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    },
                    body: JSON.stringify({
                        computer_assignment_id: rmAssignmentId,
                        new_time: time,
                        reason: reason || null,
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
