<x-teacher-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __("Qayta o'qish — Test markazi") }}
        </h2>
    </x-slot>

    <div class="py-6 px-4 sm:px-6 lg:px-8 w-full">
        @if(session('success'))
            <div class="bg-green-50 border border-green-200 rounded-lg p-3 mb-4 text-sm text-green-800">{{ session('success') }}</div>
        @endif

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            @if($groups->isEmpty())
                <div class="p-10 text-center text-sm text-gray-500">
                    {{ __("Hozircha test markaziga yuborilgan guruhlar yo'q") }}
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100">
                        <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left text-[11px] font-medium text-gray-500 uppercase">{{ __("Guruh") }}</th>
                            <th class="px-3 py-2 text-left text-[11px] font-medium text-gray-500 uppercase">{{ __("Fan") }}</th>
                            <th class="px-3 py-2 text-left text-[11px] font-medium text-gray-500 uppercase">{{ __("O'qituvchi") }}</th>
                            <th class="px-3 py-2 text-left text-[11px] font-medium text-gray-500 uppercase">{{ __("Tur") }}</th>
                            <th class="px-3 py-2 text-left text-[11px] font-medium text-gray-500 uppercase">{{ __("OSKE / TEST sanasi") }}</th>
                            <th class="px-3 py-2 text-right text-[11px] font-medium text-gray-500 uppercase">{{ __("Talabalar") }}</th>
                            <th class="px-3 py-2 text-left text-[11px] font-medium text-gray-500 uppercase">{{ __("Yuborilgan") }}</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-100">
                        @foreach($groups as $g)
                            @php
                                $atypeLabels = [
                                    'oske' => 'OSKE',
                                    'test' => 'TEST',
                                    'oske_test' => 'OSKE + TEST',
                                ];
                            @endphp
                            <tr>
                                <td class="px-3 py-2.5 text-sm text-gray-900 font-medium">{{ $g->name }}</td>
                                <td class="px-3 py-2.5 text-sm text-gray-700">
                                    {{ $g->subject_name }}
                                    <span class="block text-[11px] text-gray-500">{{ $g->semester_name }}</span>
                                </td>
                                <td class="px-3 py-2.5 text-sm text-gray-700">{{ $g->teacher_name ?? '—' }}</td>
                                <td class="px-3 py-2.5 text-sm text-gray-700">
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-medium bg-blue-100 text-blue-800">
                                        {{ $atypeLabels[$g->assessment_type] ?? '—' }}
                                    </span>
                                </td>
                                <td class="px-3 py-2.5 text-xs text-gray-700">
                                    @if($g->oske_date)OSKE: {{ $g->oske_date->format('Y-m-d') }}@endif
                                    @if($g->oske_date && $g->test_date)<br>@endif
                                    @if($g->test_date)TEST: {{ $g->test_date->format('Y-m-d') }}@endif
                                </td>
                                <td class="px-3 py-2.5 text-sm text-gray-700 text-right">{{ $g->students_count }}</td>
                                <td class="px-3 py-2.5 text-xs text-gray-500">
                                    {{ $g->sent_to_test_markazi_at?->format('Y-m-d H:i') }}
                                </td>
                                <td class="px-3 py-2.5 text-right">
                                    <a href="{{ route('admin.retake-test-markazi.show', $g->id) }}"
                                       class="text-xs text-blue-600 hover:underline">{{ __("Ochish") }}</a>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="p-3 border-t border-gray-100">{{ $groups->links() }}</div>
            @endif
        </div>
    </div>
</x-teacher-app-layout>
