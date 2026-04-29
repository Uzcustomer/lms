<x-teacher-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __("Ariza tafsilotlari") }}
            <a href="{{ route('teacher.retake.index') }}" class="text-sm text-blue-600 hover:underline ml-2">← {{ __('Orqaga') }}</a>
        </h2>
    </x-slot>

    <div class="py-6 px-4 sm:px-6 lg:px-8 max-w-5xl mx-auto space-y-4">
        @include('teacher.retake._group_card', ['group' => $group, 'role' => $role, 'minReasonLength' => $minReasonLength])

        {{-- Audit log --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100">
                <h3 class="text-sm font-semibold text-gray-900">{{ __('Audit log') }}</h3>
            </div>
            <div class="divide-y divide-gray-100">
                @foreach($group->applications as $app)
                    @foreach($app->logs as $log)
                        <div class="px-5 py-3">
                            <p class="text-xs text-gray-700">
                                <span class="font-medium">{{ $log->user_name ?? __('Tizim') }}</span>
                                <span class="text-gray-500">·</span>
                                <span class="text-gray-500">{{ $log->action }}</span>
                                <span class="text-gray-500">·</span>
                                <span class="text-gray-400">{{ $log->created_at->format('Y-m-d H:i') }}</span>
                                <span class="text-gray-400">·</span>
                                <span class="text-gray-600">{{ $app->subject_name }}</span>
                            </p>
                            @if($log->reason)
                                <p class="text-[11px] text-gray-500 mt-0.5">{{ $log->reason }}</p>
                            @endif
                        </div>
                    @endforeach
                @endforeach
            </div>
        </div>
    </div>
</x-teacher-app-layout>
