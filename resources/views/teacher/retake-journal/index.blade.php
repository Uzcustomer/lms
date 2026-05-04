<x-teacher-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __("Qayta o'qish jurnali") }}
        </h2>
    </x-slot>

    <div class="py-6 px-4 sm:px-6 lg:px-8 w-full">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            @if($groups->isEmpty())
                <div class="p-10 text-center text-sm text-gray-500">
                    {{ __("Hozircha qayta o'qish guruhlari yo'q") }}
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100">
                        <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left text-[11px] font-medium text-gray-500 uppercase">{{ __("Nom") }}</th>
                            <th class="px-3 py-2 text-left text-[11px] font-medium text-gray-500 uppercase">{{ __("Fan") }}</th>
                            <th class="px-3 py-2 text-left text-[11px] font-medium text-gray-500 uppercase">{{ __("O'qituvchi") }}</th>
                            <th class="px-3 py-2 text-left text-[11px] font-medium text-gray-500 uppercase">{{ __("Sanalar") }}</th>
                            <th class="px-3 py-2 text-right text-[11px] font-medium text-gray-500 uppercase">{{ __("Talabalar") }}</th>
                            <th class="px-3 py-2 text-left text-[11px] font-medium text-gray-500 uppercase">{{ __("Holat") }}</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-100">
                        @foreach($groups as $g)
                            <tr>
                                <td class="px-3 py-2.5 text-sm text-gray-900">{{ $g->name }}</td>
                                <td class="px-3 py-2.5 text-sm text-gray-700">
                                    {{ $g->subject_name }}
                                    <span class="block text-[11px] text-gray-500">{{ $g->semester_name }}</span>
                                </td>
                                <td class="px-3 py-2.5 text-sm text-gray-700">{{ $g->teacher_name ?? '—' }}</td>
                                <td class="px-3 py-2.5 text-xs text-gray-700">
                                    {{ $g->start_date->format('Y-m-d') }} → {{ $g->end_date->format('Y-m-d') }}
                                </td>
                                <td class="px-3 py-2.5 text-sm text-gray-700 text-right">{{ $g->students_count }}</td>
                                <td class="px-3 py-2.5">
                                    @php
                                        $colors = [
                                            'forming' => 'bg-gray-100 text-gray-700',
                                            'scheduled' => 'bg-blue-100 text-blue-800',
                                            'in_progress' => 'bg-green-100 text-green-800',
                                            'completed' => 'bg-purple-100 text-purple-800',
                                        ];
                                    @endphp
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium {{ $colors[$g->status] ?? 'bg-gray-100' }}">
                                        {{ $g->statusLabel() }}
                                    </span>
                                </td>
                                <td class="px-3 py-2.5 text-right">
                                    <a href="{{ route('admin.retake-journal.show', $g->id) }}"
                                       class="text-xs text-blue-600 hover:underline">{{ __("Jurnal") }}</a>
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
