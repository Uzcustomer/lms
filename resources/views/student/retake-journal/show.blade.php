<x-student-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-2 flex-wrap">
            <a href="{{ route('student.retake-journal.index') }}" class="text-sm text-blue-600 hover:underline">
                ← {{ __("Jurnal ro'yxati") }}
            </a>
            <span class="text-gray-300">/</span>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ $group->subject_name }}
            </h2>
        </div>
    </x-slot>

    <div class="py-6 px-4 sm:px-6 lg:px-8 max-w-5xl mx-auto">

        {{-- Guruh ma'lumotlari --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-4">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                <div>
                    <p class="text-xs text-gray-500 uppercase">{{ __("Guruh") }}</p>
                    <p class="font-medium text-gray-900 mt-0.5">{{ $group->name }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 uppercase">{{ __("O'qituvchi") }}</p>
                    <p class="font-medium text-gray-900 mt-0.5">{{ $group->teacher_name ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 uppercase">{{ __("Sanalar") }}</p>
                    <p class="font-medium text-gray-900 mt-0.5 text-xs">
                        {{ $group->start_date->format('Y-m-d') }} → {{ $group->end_date->format('Y-m-d') }}
                    </p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 uppercase">{{ __("Davr") }}</p>
                    <p class="font-medium text-gray-900 mt-0.5">{{ count($dates) }} {{ __("kun") }}</p>
                </div>
            </div>
        </div>

        {{-- Baholar jadvali --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-5 py-3 border-b border-gray-100">
                <h3 class="text-sm font-semibold text-gray-900">{{ __("Mening baholarim") }}</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-xs">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium text-gray-500 uppercase">{{ __("Sana") }}</th>
                            <th class="px-3 py-2 text-center font-medium text-gray-500 uppercase">{{ __("Baho") }}</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-500 uppercase">{{ __("Izoh") }}</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-500 uppercase">{{ __("Qo'yilgan") }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @php
                            $values = $grades->map(fn ($g) => $g->grade)->filter(fn ($v) => $v !== null);
                            $avg = $values->isNotEmpty() ? round($values->avg(), 1) : null;
                        @endphp
                        @foreach($dates as $d)
                            @php $g = $grades->get($d); @endphp
                            <tr class="hover:bg-gray-50">
                                <td class="px-3 py-2 text-gray-700">{{ \Carbon\Carbon::parse($d)->format('Y-m-d') }}</td>
                                <td class="px-3 py-2 text-center">
                                    @if($g && $g->grade !== null)
                                        @php
                                            $v = (float) $g->grade;
                                            $color = $v >= 75 ? 'text-green-700' : ($v >= 60 ? 'text-amber-700' : 'text-red-700');
                                        @endphp
                                        <span class="font-semibold {{ $color }}">{{ rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.') }}</span>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-gray-700">{{ $g?->comment ?? '' }}</td>
                                <td class="px-3 py-2 text-gray-500 text-[11px]">
                                    @if($g && $g->graded_at)
                                        {{ $g->graded_by_name }} · {{ $g->graded_at->format('Y-m-d H:i') }}
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-blue-50">
                        <tr>
                            <td class="px-3 py-2 font-semibold text-gray-700">{{ __("O'rtacha") }}</td>
                            <td class="px-3 py-2 text-center">
                                @if($avg !== null)
                                    <span class="font-bold text-blue-700">{{ $avg }}</span>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

    </div>
</x-student-app-layout>
