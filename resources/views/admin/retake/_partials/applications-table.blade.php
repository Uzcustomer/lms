{{-- Arizalar jadvali (dekan + registrator uchun umumiy)
     Parametrlar:
     - $applications: Paginator
     - $showRoute: 'admin.retake.dean.show' yoki 'admin.retake.registrar.show'
     - $showDepartment: bool (registrator uchun true)
--}}
@php
    $showDept = $showDepartment ?? false;
@endphp
<div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-3 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Talaba</th>
                    @if($showDept)
                        <th class="px-3 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Fakultet</th>
                    @endif
                    <th class="px-3 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Guruh</th>
                    <th class="px-3 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Fan</th>
                    <th class="px-3 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Semestr</th>
                    <th class="px-3 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Kr.</th>
                    <th class="px-3 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Dekan</th>
                    <th class="px-3 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Reg.</th>
                    <th class="px-3 py-3 text-left text-xs font-semibold text-gray-600 uppercase">O'qb.</th>
                    <th class="px-3 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Yuborilgan</th>
                    <th class="px-3 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($applications as $app)
                    <tr class="hover:bg-gray-50">
                        <td class="px-3 py-2.5">
                            <div class="font-medium text-gray-800">{{ $app->student?->full_name }}</div>
                            <div class="text-xs text-gray-500">{{ $app->student?->level_name }}</div>
                        </td>
                        @if($showDept)
                            <td class="px-3 py-2.5 text-xs text-gray-700">{{ $app->student?->department_name }}</td>
                        @endif
                        <td class="px-3 py-2.5 text-xs text-gray-700">{{ $app->student?->group_name }}</td>
                        <td class="px-3 py-2.5">
                            <div class="text-sm text-gray-800">{{ $app->subject_name }}</div>
                        </td>
                        <td class="px-3 py-2.5 text-xs text-gray-700">{{ $app->semester_name }}</td>
                        <td class="px-3 py-2.5 text-xs font-medium text-gray-700">{{ number_format((float) $app->credit, 1) }}</td>
                        <td class="px-3 py-2.5">
                            @php $ds = $app->dean_status?->value; @endphp
                            <span class="inline-flex px-2 py-0.5 rounded-md text-[11px] font-medium
                                @if($ds === 'approved') bg-emerald-100 text-emerald-700
                                @elseif($ds === 'rejected') bg-red-100 text-red-700
                                @else bg-yellow-100 text-yellow-700
                                @endif">
                                @if($ds === 'approved') ✓
                                @elseif($ds === 'rejected') ✕
                                @else …
                                @endif
                            </span>
                        </td>
                        <td class="px-3 py-2.5">
                            @php $rs = $app->registrar_status?->value; @endphp
                            <span class="inline-flex px-2 py-0.5 rounded-md text-[11px] font-medium
                                @if($rs === 'approved') bg-emerald-100 text-emerald-700
                                @elseif($rs === 'rejected') bg-red-100 text-red-700
                                @else bg-yellow-100 text-yellow-700
                                @endif">
                                @if($rs === 'approved') ✓
                                @elseif($rs === 'rejected') ✕
                                @else …
                                @endif
                            </span>
                        </td>
                        <td class="px-3 py-2.5">
                            @php $as = $app->academic_dept_status?->value; @endphp
                            <span class="inline-flex px-2 py-0.5 rounded-md text-[11px] font-medium
                                @if($as === 'approved') bg-emerald-100 text-emerald-700
                                @elseif($as === 'rejected') bg-red-100 text-red-700
                                @elseif($as === 'pending') bg-amber-100 text-amber-700
                                @else bg-gray-100 text-gray-500
                                @endif">
                                @if($as === 'approved') ✓
                                @elseif($as === 'rejected') ✕
                                @elseif($as === 'pending') …
                                @else –
                                @endif
                            </span>
                        </td>
                        <td class="px-3 py-2.5 text-xs text-gray-500">
                            {{ $app->submitted_at?->format('d.m.Y') }}<br>
                            <span class="text-gray-400">{{ $app->submitted_at?->format('H:i') }}</span>
                        </td>
                        <td class="px-3 py-2.5">
                            <a href="{{ route($showRoute, $app->id) }}"
                               class="inline-flex items-center px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-medium rounded-lg">
                                Ko'rish
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $showDept ? 11 : 10 }}" class="px-4 py-12 text-center text-gray-500">
                            Hech qanday ariza topilmadi.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($applications->hasPages())
        <div class="px-4 py-3 border-t border-gray-100">
            {{ $applications->links() }}
        </div>
    @endif
</div>
