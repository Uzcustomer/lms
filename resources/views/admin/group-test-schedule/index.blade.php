<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            Guruh test jadvali
        </h2>
    </x-slot>

    <div class="py-4">
        <div class="max-w-full mx-auto sm:px-4 lg:px-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">

                <div style="padding:14px 18px;border-bottom:1px solid #e5e7eb;background:#f8fafc;display:flex;flex-wrap:wrap;align-items:center;gap:8px;">
                    <span style="background:#1a3268;color:#fff;font-size:11px;font-weight:600;padding:3px 8px;border-radius:999px;">
                        {{ $scopeLabel }}
                    </span>
                    <span style="color:#64748b;font-size:13px;">
                        Qaysi guruh qachon testga kirishi haqidagi ma'lumot.
                    </span>
                </div>

                <form method="GET" action="{{ route('admin.group-test-schedule.index') }}"
                      style="padding:18px;display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;border-bottom:1px solid #e5e7eb;">
                    <div style="display:flex;flex-direction:column;gap:4px;">
                        <label for="date_from" style="font-size:12px;color:#475569;font-weight:600;">Sanadan</label>
                        <input type="date" id="date_from" name="date_from" value="{{ $dateFrom }}"
                               style="height:36px;border:1px solid #cbd5e1;border-radius:8px;padding:0 10px;font-size:13px;color:#1e293b;background:#fff;outline:none;" />
                    </div>
                    <div style="display:flex;flex-direction:column;gap:4px;">
                        <label for="date_to" style="font-size:12px;color:#475569;font-weight:600;">Sanagacha</label>
                        <input type="date" id="date_to" name="date_to" value="{{ $dateTo }}"
                               style="height:36px;border:1px solid #cbd5e1;border-radius:8px;padding:0 10px;font-size:13px;color:#1e293b;background:#fff;outline:none;" />
                    </div>
                    <div style="display:flex;gap:8px;">
                        <button type="submit"
                                style="height:36px;background:#1a3268;color:#fff;border:0;border-radius:8px;padding:0 16px;font-size:13px;font-weight:600;cursor:pointer;">
                            Yangilash
                        </button>
                        <a href="{{ route('admin.group-test-schedule.export', ['date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
                           style="height:36px;background:#16a34a;color:#fff;border:0;border-radius:8px;padding:0 16px;font-size:13px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;text-decoration:none;">
                            <svg style="width:14px;height:14px;margin-right:6px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            Excel yuklab olish
                        </a>
                    </div>
                    <div style="margin-left:auto;color:#64748b;font-size:13px;">
                        Topildi: <strong style="color:#0f172a;">{{ $rows->count() }}</strong>
                    </div>
                </form>

                <div style="overflow-x:auto;">
                    <table style="width:100%;border-collapse:collapse;font-size:13px;">
                        <thead>
                            <tr style="background:#f1f5f9;color:#0f172a;">
                                <th style="text-align:left;padding:10px 12px;border-bottom:1px solid #e2e8f0;font-weight:600;">№</th>
                                <th style="text-align:left;padding:10px 12px;border-bottom:1px solid #e2e8f0;font-weight:600;">Sana</th>
                                <th style="text-align:left;padding:10px 12px;border-bottom:1px solid #e2e8f0;font-weight:600;">Vaqt</th>
                                <th style="text-align:left;padding:10px 12px;border-bottom:1px solid #e2e8f0;font-weight:600;">Urinish</th>
                                <th style="text-align:left;padding:10px 12px;border-bottom:1px solid #e2e8f0;font-weight:600;">Guruh</th>
                                <th style="text-align:left;padding:10px 12px;border-bottom:1px solid #e2e8f0;font-weight:600;">Yo'nalish</th>
                                <th style="text-align:left;padding:10px 12px;border-bottom:1px solid #e2e8f0;font-weight:600;">Fakultet</th>
                                <th style="text-align:left;padding:10px 12px;border-bottom:1px solid #e2e8f0;font-weight:600;">Kafedra</th>
                                <th style="text-align:left;padding:10px 12px;border-bottom:1px solid #e2e8f0;font-weight:600;">Fan</th>
                                <th style="text-align:right;padding:10px 12px;border-bottom:1px solid #e2e8f0;font-weight:600;">Talabalar</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($rows as $i => $r)
                                <tr style="border-bottom:1px solid #f1f5f9;">
                                    <td style="padding:9px 12px;color:#64748b;">{{ $i + 1 }}</td>
                                    <td style="padding:9px 12px;font-weight:600;color:#0f172a;white-space:nowrap;">
                                        {{ \Carbon\Carbon::parse($r->test_date)->format('d.m.Y') }}
                                    </td>
                                    <td style="padding:9px 12px;color:#0f172a;white-space:nowrap;">
                                        {{ $r->test_time ?: '—' }}
                                    </td>
                                    <td style="padding:9px 12px;">
                                        <span style="font-size:11px;padding:2px 7px;border-radius:999px;
                                            background:{{ $r->attempt === '1-urinish' ? '#dbeafe' : '#fef3c7' }};
                                            color:{{ $r->attempt === '1-urinish' ? '#1e40af' : '#92400e' }};">
                                            {{ $r->attempt }}
                                        </span>
                                    </td>
                                    <td style="padding:9px 12px;font-weight:600;color:#0f172a;">{{ $r->group_name }}</td>
                                    <td style="padding:9px 12px;color:#475569;">{{ $r->specialty_name ?: '—' }}</td>
                                    <td style="padding:9px 12px;color:#475569;">{{ $r->faculty_name ?: '—' }}</td>
                                    <td style="padding:9px 12px;color:#475569;">{{ $r->kafedra_name ?: '—' }}</td>
                                    <td style="padding:9px 12px;color:#0f172a;">
                                        @if($r->subject_id)
                                            <span style="color:#64748b;font-size:11px;">{{ $r->subject_id }}</span><br/>
                                        @endif
                                        {{ $r->subject_name }}
                                    </td>
                                    <td style="padding:9px 12px;text-align:right;color:#0f172a;">{{ $r->student_count }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" style="padding:48px 20px;text-align:center;color:#94a3b8;">
                                        Tanlangan oraliqda jadval bo'sh.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
