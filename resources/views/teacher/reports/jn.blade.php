<x-teacher-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">JN o'zlashtirish hisoboti</h2>
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
                        <div class="filter-item" style="min-width: 150px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#f59e0b;"></span> Filtr</label>
                            <select name="filter" id="filter-select" class="filter-select">
                                <option value="">Barchasi</option>
                                <option value="low" {{ request('filter') == 'low' ? 'selected' : '' }}>60 dan past</option>
                                <option value="no_grade" {{ request('filter') == 'no_grade' ? 'selected' : '' }}>Bahosiz</option>
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

                <div style="padding:10px 20px;background:#f0fdf4;border-bottom:1px solid #bbf7d0;display:flex;align-items:center;gap:12px;">
                    <span class="badge" style="background:#16a34a;color:#fff;padding:6px 14px;font-size:13px;border-radius:8px;">Jami: {{ count($results ?? []) }} ta yozuv</span>
                </div>

                <div style="max-height:calc(100vh - 300px);overflow-y:auto;overflow-x:auto;">
                    <table class="journal-table">
                        <thead>
                        <tr>
                            <th class="th-num">#</th>
                            <th>Guruh</th>
                            <th>Fan</th>
                            <th>Talaba FISH</th>
                            <th style="text-align:center;">O'rtacha baho</th>
                            <th style="text-align:center;">Darslar soni</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($results ?? [] as $i => $row)
                            <tr>
                                <td class="td-num">{{ $i + 1 }}</td>
                                <td><span class="badge badge-indigo">{{ $row['group_name'] }}</span></td>
                                <td><span class="text-cell text-subject">{{ $row['subject_name'] }}</span></td>
                                <td><span class="text-cell" style="font-weight:700;color:#0f172a;">{{ $row['student_name'] }}</span></td>
                                <td style="text-align:center;">
                                    @if($row['avg_grade'] !== null)
                                        <span class="badge {{ $row['avg_grade'] < 60 ? 'badge-grade-red' : ($row['avg_grade'] < 75 ? 'badge-grade-yellow' : 'badge-grade-green') }}">
                                            {{ $row['avg_grade'] }}
                                        </span>
                                    @else
                                        <span style="color:#94a3b8;">-</span>
                                    @endif
                                </td>
                                <td style="text-align:center;font-weight:600;color:#475569;">{{ $row['grade_count'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" style="padding:40px;text-align:center;color:#94a3b8;font-size:14px;">Ma'lumot topilmadi</td></tr>
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
            var filter = document.getElementById('filter-select').value;
            var params = [];
            if (group) params.push('group=' + encodeURIComponent(group));
            if (filter) params.push('filter=' + encodeURIComponent(filter));
            window.location.href = '{{ route("teacher.reports.jn") }}' + (params.length ? '?' + params.join('&') : '');
        }
    </script>
</x-teacher-app-layout>
