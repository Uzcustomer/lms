<x-teacher-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">74 soat dars qoldirish hisoboti</h2>
    </x-slot>

    <div class="py-4">
        <div class="max-w-full mx-auto sm:px-4 lg:px-6">
            <div class="report-container">
                <form method="GET" action="{{ route('teacher.reports.absence-74') }}">
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
                    <span class="report-badge report-badge-danger">74+ soat qoldirganlar: {{ $rows->count() }} ta</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="report-table">
                        <thead>
                        <tr>
                            <th>#</th>
                            <th>Talaba</th>
                            <th>ID</th>
                            <th>Guruh</th>
                            <th style="text-align:center;">Sababsiz (soat)</th>
                            <th style="text-align:center;">Sababli (soat)</th>
                            <th style="text-align:center;">Jami (soat)</th>
                            <th style="text-align:center;">Kunlar</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($rows as $i => $row)
                            <tr>
                                <td style="color:#94a3b8;font-size:12px;">{{ $i + 1 }}</td>
                                <td>
                                    <div class="student-name-cell">
                                        @if($row->image)
                                            <img src="{{ $row->image }}" class="student-avatar">
                                        @else
                                            <div class="student-avatar-placeholder">{{ mb_substr($row->full_name, 0, 1) }}</div>
                                        @endif
                                        <a href="{{ route('teacher.students.show', ['student' => App\Models\Student::where('hemis_id', $row->student_hemis_id)->first()?->id]) }}">{{ $row->full_name }}</a>
                                    </div>
                                </td>
                                <td style="color:#64748b;font-family:monospace;font-size:12px;">{{ $row->student_id_number }}</td>
                                <td><span class="badge-sm badge-indigo">{{ $row->group_name }}</span></td>
                                <td style="text-align:center;">
                                    <span class="badge-sm badge-red" style="font-weight:700;">{{ (int)$row->unexcused_hours }}</span>
                                </td>
                                <td style="text-align:center;">
                                    <span class="badge-sm badge-yellow">{{ (int)$row->excused_hours }}</span>
                                </td>
                                <td style="text-align:center;">
                                    <span style="font-weight:700;color:#991b1b;font-size:14px;">{{ (int)$row->total_hours }}</span>
                                </td>
                                <td style="text-align:center;color:#64748b;">{{ (int)$row->total_days }}</td>
                            </tr>
                        @empty
                            <tr class="empty-row"><td colspan="8">74+ soat qoldirgan talaba topilmadi</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    @include('teacher.reports.partials.report-styles')
</x-teacher-app-layout>
