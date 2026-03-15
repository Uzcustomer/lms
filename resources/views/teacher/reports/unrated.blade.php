<x-teacher-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">Baho qo'ymaganlar hisoboti</h2>
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

                <div style="padding:10px 20px;background:#fef9c3;border-bottom:1px solid #fde68a;display:flex;align-items:center;gap:12px;">
                    <span class="badge" style="background:#d97706;color:#fff;padding:6px 14px;font-size:13px;border-radius:8px;">Baho qo'yilmagan darslar: {{ count($results ?? []) }} ta</span>
                </div>

                <div style="max-height:calc(100vh - 300px);overflow-y:auto;overflow-x:auto;">
                    <table class="journal-table">
                        <thead>
                        <tr>
                            <th class="th-num">#</th>
                            <th>Sana</th>
                            <th>Guruh</th>
                            <th class="th-fan">Fan</th>
                            <th>O'qituvchi</th>
                            <th style="text-align:center;">Bahosiz / Jami</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($results ?? [] as $i => $row)
                            <tr>
                                <td class="td-num">{{ $i + 1 }}</td>
                                <td>
                                    <span class="badge badge-teal" style="font-weight:600;">
                                        {{ \Carbon\Carbon::parse($row['lesson_date'])->format('d.m.Y') }}
                                    </span>
                                </td>
                                <td><span class="badge badge-indigo">{{ $row['group_name'] }}</span></td>
                                <td><span class="text-cell text-subject">{{ $row['subject_name'] }}</span></td>
                                <td><span class="text-cell" style="color:#475569;">{{ $row['employee_name'] }}</span></td>
                                <td style="text-align:center;">
                                    <span class="badge badge-grade-red">{{ $row['no_grade_count'] }} / {{ $row['total_students'] }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" style="padding:40px;text-align:center;color:#94a3b8;font-size:14px;">Baho qo'yilmagan dars topilmadi</td></tr>
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
            var url = '{{ route("teacher.reports.unrated") }}';
            if (group) url += '?group=' + encodeURIComponent(group);
            window.location.href = url;
        }
    </script>
</x-teacher-app-layout>
