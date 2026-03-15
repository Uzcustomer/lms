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

                <div style="padding:10px 20px;background:#fef2f2;border-bottom:1px solid #fecaca;display:flex;align-items:center;gap:12px;">
                    <span class="badge" style="background:#dc2626;color:#fff;padding:6px 14px;font-size:13px;border-radius:8px;">4+ qarzdor: {{ count($results ?? []) }} ta</span>
                </div>

                <div style="max-height:calc(100vh - 300px);overflow-y:auto;overflow-x:auto;">
                    <table class="journal-table">
                        <thead>
                        <tr>
                            <th class="th-num">#</th>
                            <th>Talaba FISH</th>
                            <th>Guruh</th>
                            <th style="text-align:center;">Qarzlar soni</th>
                            <th>Qarz fanlari</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($results ?? [] as $i => $row)
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
                                    </div>
                                </td>
                                <td><span class="badge badge-indigo">{{ $row['group_name'] }}</span></td>
                                <td style="text-align:center;">
                                    <span class="badge badge-grade-red" style="font-size:14px;">{{ $row['debt_count'] }}</span>
                                </td>
                                <td>
                                    <div style="display:flex;flex-wrap:wrap;gap:4px;">
                                        @foreach($row['debts'] as $debt)
                                            <span class="badge badge-grade-yellow" style="font-size:10px;" title="{{ $debt['semester_code'] }}-semestr">
                                                {{ Str::limit($debt['subject_name'], 25) }}
                                            </span>
                                        @endforeach
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" style="padding:40px;text-align:center;color:#94a3b8;font-size:14px;">4+ qarzdor talaba topilmadi</td></tr>
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
    </script>
</x-teacher-app-layout>
