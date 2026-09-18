<x-app-layout>
    @php
        $tabs = [
            'pending' => "Kutilmoqda",
            'active' => 'Ochilgan',
            'expired' => 'Muddati tugagan',
            'rejected' => 'Rad etilgan',
            'all' => 'Barchasi',
        ];
        $statusLabels = [
            'pending' => ['Kutilmoqda', 'lo-badge-pending'],
            'active' => ['Ochilgan', 'lo-badge-active'],
            'expired' => ['Muddati tugagan', 'lo-badge-expired'],
            'rejected' => ['Rad etilgan', 'lo-badge-rejected'],
        ];
    @endphp

    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">Dars ochish so'rovlari</h2>
    </x-slot>

    <style>
        .lo-wrap { --lo-navy:#0f2748; --lo-line:#e5e7eb; --lo-muted:#6b7280; }
        .lo-alert { display:flex; align-items:center; gap:8px; padding:10px 14px; border-radius:8px; margin-bottom:12px; font-size:14px; background:#ecfdf5; color:#065f46; border:1px solid #a7f3d0; }
        .lo-alert-error { background:#fef2f2; color:#991b1b; border-color:#fecaca; }
        .lo-card { background:#fff; border:1px solid var(--lo-line); border-radius:12px; overflow:hidden; }
        .lo-head { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:12px; padding:14px 16px; border-bottom:1px solid var(--lo-line); }
        .lo-note { font-size:13px; color:var(--lo-muted); }
        .lo-tabs { display:flex; flex-wrap:wrap; gap:6px; }
        .lo-tab { display:inline-flex; align-items:center; gap:6px; padding:6px 12px; border-radius:999px; font-size:13px; font-weight:600; color:#374151; background:#f3f4f6; text-decoration:none; }
        .lo-tab:hover { background:#e5e7eb; }
        .lo-tab.is-active { background:var(--lo-navy); color:#fff; }
        .lo-tab-count { min-width:20px; padding:0 6px; border-radius:999px; font-size:11px; line-height:18px; text-align:center; background:rgba(0,0,0,.08); }
        .lo-tab.is-active .lo-tab-count { background:rgba(255,255,255,.2); }
        .lo-tab-count.is-hot { background:#f59e0b; color:#fff; }
        .lo-table-wrap { overflow-x:auto; }
        .lo-table { width:100%; border-collapse:collapse; font-size:13px; }
        .lo-table th { text-align:left; padding:10px 12px; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.03em; color:var(--lo-muted); background:#f9fafb; border-bottom:1px solid var(--lo-line); white-space:nowrap; }
        .lo-table td { padding:10px 12px; border-bottom:1px solid #f1f5f9; vertical-align:top; color:#111827; }
        .lo-table tr:last-child td { border-bottom:0; }
        .lo-sub { display:block; margin-top:2px; font-size:12px; color:var(--lo-muted); }
        .lo-link { color:#1d4ed8; text-decoration:none; font-weight:600; }
        .lo-link:hover { text-decoration:underline; }
        .lo-file { display:inline-flex; align-items:center; gap:4px; max-width:220px; color:#1d4ed8; text-decoration:none; font-weight:600; }
        .lo-file span { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .lo-file:hover span { text-decoration:underline; }
        .lo-badge { display:inline-block; padding:2px 10px; border-radius:999px; font-size:12px; font-weight:600; white-space:nowrap; }
        .lo-badge-pending { background:#fef3c7; color:#92400e; }
        .lo-badge-active { background:#dcfce7; color:#166534; }
        .lo-badge-expired { background:#f3f4f6; color:#4b5563; }
        .lo-badge-rejected { background:#fee2e2; color:#991b1b; }
        .lo-comment { margin-top:4px; font-size:12px; color:#991b1b; max-width:260px; }
        .lo-actions { display:flex; gap:6px; white-space:nowrap; }
        .lo-btn { display:inline-flex; align-items:center; gap:4px; padding:6px 12px; border-radius:8px; border:0; font-size:13px; font-weight:600; cursor:pointer; }
        .lo-btn-approve { background:#16a34a; color:#fff; }
        .lo-btn-approve:hover { background:#15803d; }
        .lo-btn-reject { background:#fff; color:#b91c1c; border:1px solid #fecaca; }
        .lo-btn-reject:hover { background:#fef2f2; }
        .lo-btn-ghost { background:#f3f4f6; color:#374151; }
        .lo-empty { padding:40px 16px; text-align:center; color:var(--lo-muted); font-size:14px; }
        .lo-pager { padding:12px 16px; border-top:1px solid var(--lo-line); }
        .lo-modal { position:fixed; inset:0; z-index:60; display:none; align-items:center; justify-content:center; padding:16px; background:rgba(15,23,42,.5); }
        .lo-modal.is-open { display:flex; }
        .lo-modal-box { width:100%; max-width:460px; background:#fff; border-radius:12px; padding:20px; box-shadow:0 20px 40px rgba(0,0,0,.2); }
        .lo-modal-box h3 { margin:0 0 4px; font-size:16px; font-weight:700; color:#111827; }
        .lo-modal-box textarea { width:100%; margin-top:12px; padding:8px 10px; border:1px solid #d1d5db; border-radius:8px; font-size:14px; resize:vertical; }
        .lo-modal-foot { display:flex; justify-content:flex-end; gap:8px; margin-top:14px; }
    </style>

    <div class="py-4 lo-wrap">
        <div class="max-w-full mx-auto sm:px-4 lg:px-6">
            @if(session('success'))
                <div class="lo-alert">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="lo-alert lo-alert-error">{{ session('error') }}</div>
            @endif
            @if($errors->any())
                <div class="lo-alert lo-alert-error">{{ $errors->first() }}</div>
            @endif

            <div class="lo-card">
                <div class="lo-head">
                    <div class="lo-tabs">
                        @foreach($tabs as $key => $label)
                            @php
                                $count = $key === 'all' ? $counts->sum() : (int) ($counts[$key] ?? 0);
                            @endphp
                            <a href="{{ route('admin.lesson-opening-requests.index', ['status' => $key]) }}"
                               class="lo-tab {{ $status === $key ? 'is-active' : '' }}">
                                {{ $label }}
                                <span class="lo-tab-count {{ $key === 'pending' && $count > 0 && $status !== 'pending' ? 'is-hot' : '' }}">{{ $count }}</span>
                            </a>
                        @endforeach
                    </div>
                    <div class="lo-note">
                        Tasdiqlangach o'qituvchiga {{ $openingDays }} kun (oxirgi kuni 23:59 gacha) baho qo'yish imkoniyati beriladi.
                    </div>
                </div>

                @if($openings->isEmpty())
                    <div class="lo-empty">
                        {{ $status === 'pending' ? "Ko'rib chiqilishi kerak bo'lgan so'rov yo'q." : "So'rov topilmadi." }}
                    </div>
                @else
                    <div class="lo-table-wrap">
                        <table class="lo-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Guruh / Fan</th>
                                    <th>Dars sanasi</th>
                                    <th>So'rov yubordi</th>
                                    <th>Asos hujjat</th>
                                    <th>Holat</th>
                                    @if($canReview && in_array($status, ['pending', 'all'], true))
                                        <th>Amal</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($openings as $opening)
                                    @php
                                        $group = $groups[$opening->group_hemis_id] ?? null;
                                        $subjectName = $subjectNames[$opening->subject_id] ?? ('Fan #' . $opening->subject_id);
                                        $semesterNumber = is_numeric($opening->semester_code) ? ((int) $opening->semester_code - 10) : null;
                                        $label = $statusLabels[$opening->status] ?? [$opening->status, 'lo-badge-expired'];
                                    @endphp
                                    <tr>
                                        <td>{{ $openings->firstItem() + $loop->index }}</td>
                                        <td>
                                            @if($group)
                                                <a class="lo-link" target="_blank"
                                                   href="{{ route('admin.journal.show', [$group->id, $opening->subject_id, $opening->semester_code]) }}">{{ $group->name }}</a>
                                            @else
                                                {{ $opening->group_hemis_id }}
                                            @endif
                                            <span class="lo-sub">{{ $subjectName }}{{ $semesterNumber > 0 ? ' · ' . $semesterNumber . '-semestr' : '' }}</span>
                                        </td>
                                        <td style="white-space:nowrap">{{ $opening->lesson_date?->format('d.m.Y') }}</td>
                                        <td>
                                            {{ $opening->opened_by_name }}
                                            <span class="lo-sub">{{ $opening->created_at?->format('d.m.Y H:i') }}</span>
                                            @if($opening->request_note)
                                                <span class="lo-sub">{{ $opening->request_note }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($opening->file_path)
                                                <a class="lo-file" href="{{ route('admin.journal.download-lesson-file', $opening->id) }}" title="{{ $opening->file_original_name }}">
                                                    &#128206; <span>{{ $opening->file_original_name ?: 'Fayl' }}</span>
                                                </a>
                                            @else
                                                <span class="lo-sub">—</span>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="lo-badge {{ $label[1] }}">{{ $label[0] }}</span>
                                            @if($opening->status === 'active' && $opening->deadline)
                                                <span class="lo-sub">Muddat: {{ $opening->deadline->format('d.m.Y H:i') }}</span>
                                            @endif
                                            @if($opening->reviewed_by_name)
                                                <span class="lo-sub">{{ $opening->reviewed_by_name }}, {{ $opening->reviewed_at?->format('d.m.Y H:i') }}</span>
                                            @endif
                                            @if($opening->status === 'rejected' && $opening->review_comment)
                                                <div class="lo-comment">{{ $opening->review_comment }}</div>
                                            @endif
                                        </td>
                                        @if($canReview && in_array($status, ['pending', 'all'], true))
                                            <td>
                                                @if($opening->status === 'pending')
                                                    <div class="lo-actions">
                                                        <form method="POST" action="{{ route('admin.lesson-opening-requests.approve', $opening->id) }}"
                                                              onsubmit="return confirm('Dars ochilsinmi? O\'qituvchiga {{ $openingDays }} kun muddat beriladi.')">
                                                            @csrf
                                                            <button type="submit" class="lo-btn lo-btn-approve">Tasdiqlash</button>
                                                        </form>
                                                        <button type="button" class="lo-btn lo-btn-reject"
                                                                onclick="loOpenReject('{{ route('admin.lesson-opening-requests.reject', $opening->id) }}', @js(($group->name ?? '') . ' · ' . $opening->lesson_date?->format('d.m.Y')))">Rad etish</button>
                                                    </div>
                                                @endif
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if($openings->hasPages())
                        <div class="lo-pager">{{ $openings->links() }}</div>
                    @endif
                @endif
            </div>
        </div>
    </div>

    <div class="lo-modal" id="loRejectModal" onclick="if (event.target === this) loCloseReject()">
        <div class="lo-modal-box">
            <h3>So'rovni rad etish</h3>
            <div class="lo-note" id="loRejectTitle"></div>
            <form method="POST" id="loRejectForm">
                @csrf
                <textarea name="comment" id="loRejectComment" rows="4" required minlength="3" maxlength="1000"
                          placeholder="Rad etish sababi (registrator jurnalda ko'radi)"></textarea>
                <div class="lo-modal-foot">
                    <button type="button" class="lo-btn lo-btn-ghost" onclick="loCloseReject()">Bekor qilish</button>
                    <button type="submit" class="lo-btn lo-btn-reject">Rad etish</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function loOpenReject(action, title) {
            document.getElementById('loRejectForm').action = action;
            document.getElementById('loRejectTitle').textContent = title;
            document.getElementById('loRejectComment').value = '';
            document.getElementById('loRejectModal').classList.add('is-open');
            setTimeout(function () { document.getElementById('loRejectComment').focus(); }, 50);
        }
        function loCloseReject() {
            document.getElementById('loRejectModal').classList.remove('is-open');
        }
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') loCloseReject();
        });
    </script>
</x-app-layout>
