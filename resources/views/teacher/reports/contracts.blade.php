<x-teacher-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Kontraktlar hisoboti</h2>
    </x-slot>

    <div class="py-4">
        <div class="max-w-full mx-auto sm:px-4 lg:px-6">
            <div class="report-container">
                <form method="GET" action="{{ route('teacher.reports.contracts') }}">
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
                    <span class="report-badge">Jami: {{ $contracts->count() }} ta kontrakt</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="report-table">
                        <thead>
                        <tr>
                            <th>#</th>
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
                                <td style="color:#94a3b8;font-size:12px;">{{ $i + 1 }}</td>
                                <td>
                                    <div class="student-name-cell">
                                        @if($contract->student_data && $contract->student_data->image)
                                            <img src="{{ $contract->student_data->image }}" class="student-avatar">
                                        @else
                                            <div class="student-avatar-placeholder">{{ mb_substr($contract->full_name, 0, 1) }}</div>
                                        @endif
                                        <div>
                                            <span style="font-weight:600;color:#1e293b;display:block;">{{ $contract->full_name }}</span>
                                            @if($contract->student_data)
                                                <span style="font-size:11px;color:#94a3b8;">{{ $contract->student_data->group_name }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td style="color:#64748b;font-family:monospace;font-size:12px;">{{ $contract->contract_number ?? '-' }}</td>
                                <td>
                                    <span class="badge-sm badge-blue">{{ $contract->edu_contract_type_name ?? '-' }}</span>
                                </td>
                                <td style="text-align:right;font-weight:600;color:#1e293b;">
                                    {{ number_format($contract->edu_contract_sum ?? 0, 0, '.', ' ') }}
                                </td>
                                <td style="text-align:right;color:#059669;font-weight:600;">
                                    {{ number_format($contract->paid_credit_amount ?? 0, 0, '.', ' ') }}
                                </td>
                                <td style="text-align:right;">
                                    @php
                                        $remaining = ($contract->edu_contract_sum ?? 0) - ($contract->paid_credit_amount ?? 0);
                                    @endphp
                                    <span style="font-weight:600;color:{{ $remaining > 0 ? '#dc2626' : '#059669' }};">
                                        {{ number_format($remaining, 0, '.', ' ') }}
                                    </span>
                                </td>
                                <td>
                                    @if($contract->status)
                                        <span class="badge-sm {{ str_contains(mb_strtolower($contract->status), 'aktiv') || str_contains(mb_strtolower($contract->status), 'faol') ? 'badge-green' : 'badge-yellow' }}">
                                            {{ $contract->status }}
                                        </span>
                                    @else
                                        <span style="color:#94a3b8;">-</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr class="empty-row"><td colspan="8">Kontrakt ma'lumotlari topilmadi</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    @include('teacher.reports.partials.report-styles')
</x-teacher-app-layout>
