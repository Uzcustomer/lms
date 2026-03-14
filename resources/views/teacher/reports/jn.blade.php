<x-teacher-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">JN o'zlashtirish hisoboti</h2>
    </x-slot>

    <div class="py-4">
        <div class="max-w-full mx-auto sm:px-4 lg:px-6">
            <div class="report-container">
                <form method="GET" action="{{ route('teacher.reports.jn') }}">
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
                        <div class="filter-item">
                            <label class="filter-label"><span class="fl-dot" style="background:#f59e0b;"></span> Filtr</label>
                            <select name="filter">
                                <option value="">Barchasi</option>
                                <option value="low" {{ request('filter') == 'low' ? 'selected' : '' }}>60 dan past</option>
                                <option value="no_grade" {{ request('filter') == 'no_grade' ? 'selected' : '' }}>Bahosiz</option>
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
                    <span class="report-badge">Jami: {{ count($results ?? []) }} ta yozuv</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="report-table">
                        <thead>
                        <tr>
                            <th>#</th>
                            <th>Guruh</th>
                            <th>Fan</th>
                            <th>Talaba</th>
                            <th>ID</th>
                            <th style="text-align:center;">O'rtacha baho</th>
                            <th style="text-align:center;">Baholar soni</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($results ?? [] as $i => $row)
                            <tr>
                                <td style="color:#94a3b8;font-size:12px;">{{ $i + 1 }}</td>
                                <td><span class="badge-sm badge-indigo">{{ $row['group_name'] }}</span></td>
                                <td style="font-size:12px;color:#475569;max-width:200px;">{{ $row['subject_name'] }}</td>
                                <td style="font-weight:600;color:#1e293b;">{{ $row['student_name'] }}</td>
                                <td style="color:#64748b;font-family:monospace;font-size:12px;">{{ $row['student_id'] }}</td>
                                <td style="text-align:center;">
                                    @if($row['avg_grade'] !== null)
                                        <span class="badge-sm {{ $row['avg_grade'] >= 86 ? 'badge-green' : ($row['avg_grade'] >= 60 ? 'badge-yellow' : 'badge-red') }}" style="font-weight:700;">
                                            {{ $row['avg_grade'] }}
                                        </span>
                                    @else
                                        <span style="color:#94a3b8;">-</span>
                                    @endif
                                </td>
                                <td style="text-align:center;color:#64748b;">{{ $row['grade_count'] }}</td>
                            </tr>
                        @empty
                            <tr class="empty-row"><td colspan="7">Ma'lumot topilmadi</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    @include('teacher.reports.partials.report-styles')
</x-teacher-app-layout>
