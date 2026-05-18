<x-student-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __("Qayta o'qish jurnali") }}
        </h2>
    </x-slot>

    <div class="py-6 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
        @if($groups->isEmpty())
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-10 text-center">
                <p class="text-sm text-gray-500">
                    {{ __("Sizda hozircha qayta o'qish guruhlari yo'q") }}
                </p>
            </div>
        @else
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="divide-y divide-gray-100">
                    @foreach($groups as $g)
                        <div class="p-4 hover:bg-gray-50 transition flex items-center justify-between flex-wrap gap-3">
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-gray-900">{{ $g->subject_name }}</p>
                                <p class="text-xs text-gray-500 mt-0.5">
                                    {{ __("Guruh") }}: {{ $g->name }} ·
                                    {{ __("O'qituvchi") }}: {{ $g->teacher_name ?? '—' }}
                                </p>
                                @if(!empty($g->teacher_phones))
                                    <p class="text-[11px] text-gray-600 mt-0.5 flex flex-wrap items-center gap-1">
                                        <span>📞</span>
                                        @foreach($g->teacher_phones as $phone)
                                            <a href="tel:{{ preg_replace('/[^+\d]/', '', $phone) }}"
                                               class="text-blue-600 hover:underline font-medium">{{ $phone }}</a>
                                            @if(!$loop->last)<span class="text-gray-400">·</span>@endif
                                        @endforeach
                                    </p>
                                @endif
                                <p class="text-[11px] text-gray-500 mt-0.5">
                                    {{ $g->start_date->format('Y-m-d') }} → {{ $g->end_date->format('Y-m-d') }}
                                </p>
                            </div>
                            <a href="{{ route('student.retake-journal.show', $g->id) }}"
                               class="text-xs text-blue-600 hover:underline whitespace-nowrap">
                                {{ __("Ochish") }} →
                            </a>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</x-student-app-layout>
