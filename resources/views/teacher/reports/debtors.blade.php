<x-teacher-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">4 va undan ortiq qarzdorlar hisoboti</h2>
    </x-slot>

    <div class="py-4">
        <div class="max-w-full mx-auto sm:px-4 lg:px-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">

                <div class="filter-container">
                    <div class="filter-row">
                        <div class="filter-item" style="min-width: 170px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#1a3268;"></span> Guruh</label>
                            <select name="group" id="group-select" class="filter-select">
                                <option value="">Barchasi</option>
                                @foreach($tutorGroups as $group)
                                    <option value="{{ $group->group_hemis_id }}" {{ request('group') == $group->group_hemis_id ? 'selected' : '' }}>{{ $group->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="filter-item" style="min-width: 120px;">
                            <label class="filter-label">&nbsp;</label>
                            <button type="button" class="btn-calc" onclick="applyFilter()">
                                <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                Hisoblash
                            </button>
                        </div>
                    </div>
                </div>

                @php
                    $results = $results ?? [];
                    $totalPast = count(array_filter($results, fn($r) => $r['debt_count'] > 0));
                    $totalCurrent = count(array_filter($results, fn($r) => !empty($r['current_risks'])));
                @endphp

                <div style="padding:10px 20px;background:#fef2f2;border-bottom:1px solid #fecaca;display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                    <span class="badge" style="background:#dc2626;color:#fff;padding:6px 14px;font-size:13px;border-radius:8px;">
                        O'tgan semestr qarzdor: {{ $totalPast }} ta
                    </span>
                    <span class="badge" style="background:#d97706;color:#fff;padding:6px 14px;font-size:13px;border-radius:8px;">
                        Joriy semestr xavfi: {{ $totalCurrent }} ta
                    </span>
                </div>

                <div style="max-height:calc(100vh - 320px);overflow-y:auto;overflow-x:auto;">
                    <table class="journal-table">
                        <thead>
                        <tr>
                            <th class="th-num">#</th>
                            <th>Talaba FISH</th>
                            <th>Guruh</th>
                            <th style="text-align:center;">O'tgan semestr</th>
                            <th style="text-align:center;">Joriy semestr</th>
                            <th style="text-align:center;">Batafsil</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($results as $i => $row)
                            <tr>
                                <td class="td-num">{{ $i + 1 }}</td>
                                <td>
                                    <div class="student-name-cell">
                                        @if($row['image'])
                                            <img src="{{ $row['image'] }}" class="student-avatar">
                                        @else
                                            <div class="student-avatar-placeholder">{{ mb_substr($row['full_name'], 0, 1) }}</div>
                                        @endif
                                        <span class="text-cell" style="font-weight:700;color:#0f172a;">{{ $row['full_name'] }}</span>
                                        <span style="font-size:11px;color:#64748b;">{{ $row['student_id'] }}</span>
                                    </div>
                                </td>
                                <td><span class="badge badge-indigo">{{ $row['group_name'] }}</span></td>
                                <td style="text-align:center;">
                                    @if($row['debt_count'] > 0)
                                        <span class="badge badge-grade-red" style="font-size:14px;" title="O'tgan semestrlardagi qarzlar soni">
                                            {{ $row['debt_count'] }} ta
                                        </span>
                                    @else
                                        <span style="color:#94a3b8;font-size:12px;">—</span>
                                    @endif
                                </td>
                                <td style="text-align:center;">
                                    @php $crCount = $row['current_risk_count'] ?? 0; @endphp
                                    @if($crCount > 0)
                                        <span class="badge" style="background:#d97706;color:#fff;font-size:14px;" title="Joriy semestrda xavf ostidagi fanlar">
                                            {{ $crCount }} ta
                                        </span>
                                    @else
                                        <span style="color:#94a3b8;font-size:12px;">—</span>
                                    @endif
                                </td>
                                <td style="text-align:center;">
                                    <button type="button"
                                        onclick="toggleDetails({{ $i }})"
                                        style="padding:4px 10px;font-size:12px;border:1px solid #cbd5e1;border-radius:6px;background:#f8fafc;cursor:pointer;color:#334155;">
                                        ▼ Ko'rish
                                    </button>
                                </td>
                            </tr>
                            <tr id="details-{{ $i }}" style="display:none;background:#f8fafc;">
                                <td colspan="6" style="padding:0;">
                                    <div style="display:flex;gap:0;flex-wrap:wrap;">

                                        {{-- O'tgan semestr qarzlari --}}
                                        @if($row['debt_count'] > 0)
                                        <div style="flex:1;min-width:280px;padding:14px 20px;border-right:1px solid #e2e8f0;">
                                            <div style="font-size:12px;font-weight:700;color:#dc2626;margin-bottom:8px;text-transform:uppercase;letter-spacing:.05em;">
                                                O'tgan semestr qarzlari ({{ $row['debt_count'] }} ta)
                                            </div>
                                            <div style="display:flex;flex-direction:column;gap:4px;">
                                                @foreach($row['debts'] as $debt)
                                                    <div style="display:flex;align-items:center;gap:8px;">
                                                        <span style="font-size:11px;color:#64748b;min-width:60px;">{{ $debt['semester_code'] }}-sem</span>
                                                        <span style="font-size:12px;color:#0f172a;">{{ $debt['subject_name'] }}</span>
                                                        @if($debt['credit'])
                                                            <span style="font-size:10px;color:#94a3b8;">({{ $debt['credit'] }} kr)</span>
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                        @endif

                                        {{-- Joriy semestr xavflari --}}
                                        @if(!empty($row['current_risks']))
                                        <div style="flex:1;min-width:280px;padding:14px 20px;">
                                            <div style="font-size:12px;font-weight:700;color:#d97706;margin-bottom:8px;text-transform:uppercase;letter-spacing:.05em;">
                                                Joriy semestr xavflari ({{ $row['current_risk_count'] }} ta)
                                            </div>
                                            <div style="display:flex;flex-direction:column;gap:6px;">
                                                @foreach($row['current_risks'] as $risk)
                                                    <div>
                                                        <div style="font-size:12px;font-weight:600;color:#0f172a;margin-bottom:3px;">
                                                            {{ $risk['subject_name'] }}
                                                        </div>
                                                        <div style="display:flex;flex-wrap:wrap;gap:3px;">
                                                            @foreach($risk['reasons'] as $reason)
                                                                @php
                                                                    $badgeColor = match(true) {
                                                                        str_contains($reason, 'Akademik qarzdor') => '#dc2626',
                                                                        str_contains($reason, '3-urinish') => '#dc2626',
                                                                        str_contains($reason, '2-urinish') => '#d97706',
                                                                        str_contains($reason, '1-urinish') => '#ca8a04',
                                                                        str_contains($reason, 'Davomat') => '#be123c',
                                                                        default => '#b45309',
                                                                    };
                                                                @endphp
                                                                <span style="background:{{ $badgeColor }};color:#fff;font-size:10px;padding:2px 7px;border-radius:4px;font-weight:600;">
                                                                    {{ $reason }}
                                                                </span>
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                        @endif

                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" style="padding:40px;text-align:center;color:#94a3b8;font-size:14px;">Talabalar topilmadi</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    @include('teacher.reports.partials.report-styles')
    <script>
        function applyFilter() {
            var group = document.getElementById('group-select').value;
            var url = '{{ route("teacher.reports.debtors") }}';
            if (group) url += '?group=' + encodeURIComponent(group);
            window.location.href = url;
        }

        function toggleDetails(idx) {
            var row = document.getElementById('details-' + idx);
            if (row.style.display === 'none') {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        }
    </script>
</x-teacher-app-layout>
