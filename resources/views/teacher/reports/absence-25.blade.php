<x-teacher-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">25% sababsiz hisoboti</h2>
    </x-slot>

    <div class="py-4">
        <div class="max-w-full mx-auto sm:px-4 lg:px-6">
            <div class="report-container">
                <form method="GET" action="{{ route('teacher.reports.absence-25') }}">
                    <div class="report-filter">
                        <div class="filter-item">
                            <label class="filter-label"><span class="fl-dot" style="background:#1a3268;"></span> Guruh</label>
                            <select name="group">
                                <option value="">Barchasi</option>
                                @foreach($tutorGroups as $group)
                                    <option value="{{ $group->group_hemis_id }}" {{ request('group') == $group->group_hemis_id ? 'selected' : '' }}>{{ $group->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="filter-label">&nbsp;</label>
                            <button type="submit" class="btn-filter">
                                <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                Qidirish
                            </button>
                        </div>
                    </div>
                </form>

                <div class="report-header">
                    <span class="report-badge report-badge-warning">25%+ sababsiz: {{ count($results ?? []) }} ta</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="report-table">
                        <thead>
                        <tr>
                            <th>#</th>
                            <th>Talaba</th>
                            <th>ID</th>
                            <th>Guruh</th>
                            <th>Fan</th>
                            <th style="text-align:center;">Sababsiz</th>
                            <th style="text-align:center;">Umumiy soat</th>
                            <th style="text-align:center;">Foiz</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($results ?? [] as $i => $row)
                            <tr>
                                <td style="color:#94a3b8;font-size:12px;">{{ $i + 1 }}</td>
                                <td>
                                    <div class="student-name-cell">
                                        @if($row['image'])
                                            <img src="{{ $row['image'] }}" class="student-avatar">
                                        @else
                                            <div class="student-avatar-placeholder">{{ mb_substr($row['full_name'], 0, 1) }}</div>
                                        @endif
                                        <span style="font-weight:600;color:#1e293b;">{{ $row['full_name'] }}</span>
                                    </div>
                                </td>
                                <td style="color:#64748b;font-family:monospace;font-size:12px;">{{ $row['student_id'] }}</td>
                                <td><span class="badge-sm badge-indigo">{{ $row['group_name'] }}</span></td>
                                <td style="font-size:12px;color:#475569;max-width:200px;">{{ $row['subject_name'] }}</td>
                                <td style="text-align:center;">
                                    <span class="badge-sm badge-red" style="font-weight:700;">{{ $row['unexcused_hours'] }}</span>
                                </td>
                                <td style="text-align:center;color:#64748b;">{{ $row['total_hours'] }}</td>
                                <td style="text-align:center;">
                                    <span class="badge-sm {{ $row['percentage'] >= 50 ? 'badge-red' : 'badge-yellow' }}" style="font-weight:700;">
                                        {{ $row['percentage'] }}%
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr class="empty-row"><td colspan="8">25%+ sababsiz talaba topilmadi</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    @include('teacher.reports.partials.report-styles')
</x-teacher-app-layout>
