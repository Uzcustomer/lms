<x-teacher-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __("Ariza tafsilotlari") }}
            <a href="{{ route('admin.retake.index') }}" class="text-sm text-blue-600 hover:underline ml-2">← {{ __('Orqaga') }}</a>
        </h2>
    </x-slot>

    <div class="py-6 px-4 sm:px-6 lg:px-8 max-w-5xl mx-auto space-y-4">
        @include('teacher.retake._group_card', ['group' => $group, 'role' => $role, 'minReasonLength' => $minReasonLength])

        {{-- Audit log: butun ariza guruhi bo'yicha xronologik tartibda --}}
        @php
            $allLogs = $group->applications->flatMap(function($a){
                return $a->logs->map(function($l) use ($a){
                    $l->subject_name_cached = $a->subject_name;
                    return $l;
                });
            })->sortByDesc('created_at')->values();

            $actionLabels = [
                'submitted' => ['label' => 'Ariza yuborildi', 'color' => 'bg-blue-100 text-blue-800', 'icon' => '📤'],
                'dean_approved' => ['label' => 'Dekan tasdiqladi', 'color' => 'bg-green-100 text-green-800', 'icon' => '✓'],
                'dean_rejected' => ['label' => 'Dekan rad etdi', 'color' => 'bg-red-100 text-red-800', 'icon' => '✗'],
                'registrar_approved' => ['label' => 'Registrator tasdiqladi', 'color' => 'bg-green-100 text-green-800', 'icon' => '✓'],
                'registrar_rejected' => ['label' => 'Registrator rad etdi', 'color' => 'bg-red-100 text-red-800', 'icon' => '✗'],
                'academic_approved' => ['label' => "O'quv bo'limi tasdiqladi", 'color' => 'bg-green-100 text-green-800', 'icon' => '✓'],
                'academic_rejected' => ['label' => "O'quv bo'limi rad etdi", 'color' => 'bg-red-100 text-red-800', 'icon' => '✗'],
                'auto_cancelled_hemis' => ['label' => 'HEMIS auto-cancel', 'color' => 'bg-gray-100 text-gray-700', 'icon' => '⏹'],
                'group_assigned' => ['label' => 'Guruhga biriktirildi', 'color' => 'bg-purple-100 text-purple-800', 'icon' => '👥'],
                'status_changed' => ['label' => "Holat o'zgartirildi", 'color' => 'bg-yellow-100 text-yellow-800', 'icon' => '🔄'],
            ];
        @endphp

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100">
                <h3 class="text-sm font-semibold text-gray-900">
                    {{ __('Audit log') }}
                    <span class="text-xs text-gray-500 font-normal">({{ $allLogs->count() }} {{ __('ta yozuv') }})</span>
                </h3>
            </div>
            @if($allLogs->isEmpty())
                <p class="p-6 text-center text-sm text-gray-500">{{ __("Hali audit yozuvlari yo'q") }}</p>
            @else
                <div class="divide-y divide-gray-100">
                    @foreach($allLogs as $log)
                        @php $a = $actionLabels[$log->action] ?? ['label' => $log->action, 'color' => 'bg-gray-100 text-gray-700', 'icon' => '•']; @endphp
                        <div class="px-5 py-3 flex items-start gap-3">
                            <div class="flex-shrink-0 w-7 h-7 rounded-full {{ $a['color'] }} flex items-center justify-center text-xs">
                                {{ $a['icon'] }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between flex-wrap gap-1">
                                    <p class="text-sm text-gray-900">
                                        <span class="font-medium">{{ $a['label'] }}</span>
                                        <span class="text-gray-400">·</span>
                                        <span class="text-gray-600">{{ $log->subject_name_cached }}</span>
                                    </p>
                                    <span class="text-[11px] text-gray-400">{{ $log->created_at->format('Y-m-d H:i') }}</span>
                                </div>
                                <p class="text-[11px] text-gray-500 mt-0.5">
                                    @if($log->user_name)
                                        {{ $log->user_name }}
                                        <span class="text-gray-400">({{ $log->user_type }})</span>
                                    @else
                                        <span class="text-gray-400">{{ __('Tizim (avtomatik)') }}</span>
                                    @endif
                                </p>
                                @if($log->reason)
                                    <p class="text-xs text-gray-700 mt-1 italic">"{{ $log->reason }}"</p>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-teacher-app-layout>
