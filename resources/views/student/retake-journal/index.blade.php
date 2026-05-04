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
                        <a href="{{ route('student.retake-journal.show', $g->id) }}"
                           class="block p-4 hover:bg-gray-50 transition flex items-center justify-between flex-wrap gap-3">
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-gray-900">{{ $g->subject_name }}</p>
                                <p class="text-xs text-gray-500 mt-0.5">
                                    {{ __("Guruh") }}: {{ $g->name }} ·
                                    {{ __("O'qituvchi") }}: {{ $g->teacher_name ?? '—' }}
                                </p>
                                <p class="text-[11px] text-gray-500 mt-0.5">
                                    {{ $g->start_date->format('Y-m-d') }} → {{ $g->end_date->format('Y-m-d') }}
                                </p>
                            </div>
                            <span class="text-xs text-blue-600">{{ __("Ochish") }} →</span>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</x-student-app-layout>
