<x-teacher-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">Kontraktlar hisoboti</h2>
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

                <div style="padding:10px 20px;background:#f0fdf4;border-bottom:1px solid #bbf7d0;display:flex;align-items:center;gap:12px;">
                    <span class="badge" style="background:#16a34a;color:#fff;padding:6px 14px;font-size:13px;border-radius:8px;">Jami: {{ $contracts->count() }} ta kontrakt</span>
                </div>

                <div style="max-height:calc(100vh - 300px);overflow-y:auto;overflow-x:auto;">
                    <table class="journal-table">
                        <thead>
                        <tr>
                            <th class="th-num">#</th>
                            <th>Talaba</th>
                            <th>Kontrakt raqami</th>
                            <th>Turi</th>
                            <th style="text-align:right;">Summa</th>
                            <th style="text-align:right;">To'langan</th>
                            <th style="text-align:right;">Qoldiq</th>
                            <th>Holat</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($contracts as $i => $contract)
                            <tr>
                                <td class="td-num">{{ $i + 1 }}</td>
                                <td>
                                    <div class="student-name-cell">
                                        @if($contract->student_data && $contract->student_data->image)
                                            <img src="{{ $contract->student_data->image }}" class="student-avatar">
                                        @else
                                            <div class="student-avatar-placeholder">{{ mb_substr($contract->full_name, 0, 1) }}</div>
                                        @endif
                                        <div>
                                            <span class="text-cell" style="font-weight:700;color:#0f172a;">{{ $contract->full_name }}</span>
                                            @if($contract->student_data)
                                                <span style="font-size:11px;color:#94a3b8;">{{ $contract->student_data->group_name }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td style="color:#64748b;font-family:monospace;font-size:12px;">{{ $contract->contract_number ?? '-' }}</td>
                                <td><span class="badge badge-teal">{{ $contract->edu_contract_type_name ?? '-' }}</span></td>
                                <td style="text-align:right;font-weight:600;color:#1e293b;">
                                    {{ number_format($contract->edu_contract_sum ?? 0, 0, '.', ' ') }}
                                </td>
                                <td style="text-align:right;color:#059669;font-weight:600;">
                                    {{ number_format($contract->paid_credit_amount ?? 0, 0, '.', ' ') }}
                                </td>
                                <td style="text-align:right;">
                                    @php $remaining = ($contract->edu_contract_sum ?? 0) - ($contract->paid_credit_amount ?? 0); @endphp
                                    <span style="font-weight:600;color:{{ $remaining > 0 ? '#dc2626' : '#059669' }};">
                                        {{ number_format($remaining, 0, '.', ' ') }}
                                    </span>
                                </td>
                                <td>
                                    @if($contract->status)
                                        <span class="badge {{ str_contains(mb_strtolower($contract->status), 'aktiv') || str_contains(mb_strtolower($contract->status), 'faol') ? 'badge-grade-green' : 'badge-grade-yellow' }}">
                                            {{ $contract->status }}
                                        </span>
                                    @else
                                        <span style="color:#94a3b8;">-</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" style="padding:40px;text-align:center;color:#94a3b8;font-size:14px;">Kontrakt ma'lumotlari topilmadi</td></tr>
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
            var url = '{{ route("teacher.reports.contracts") }}';
            if (group) url += '?group=' + encodeURIComponent(group);
            window.location.href = url;
        }
    </script>
</x-teacher-app-layout>
