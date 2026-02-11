<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Faoliyat jurnali - Batafsil
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="mb-4">
                <a href="{{ route('admin.activity-log.index') }}"
                   class="text-indigo-600 hover:text-indigo-900 text-sm">
                    &larr; Orqaga
                </a>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">

                    {{-- Asosiy ma'lumotlar --}}
                    <div class="grid grid-cols-2 gap-4 mb-6">
                        <div>
                            <span class="text-sm font-medium text-gray-500">Vaqt:</span>
                            <p class="text-gray-900">{{ $activityLog->created_at->format('d.m.Y H:i:s') }}</p>
                        </div>
                        <div>
                            <span class="text-sm font-medium text-gray-500">Foydalanuvchi:</span>
                            <p class="text-gray-900">{{ $activityLog->user_name ?? '-' }}</p>
                        </div>
                        <div>
                            <span class="text-sm font-medium text-gray-500">Guard:</span>
                            <p class="text-gray-900">{{ $activityLog->guard }}</p>
                        </div>
                        <div>
                            <span class="text-sm font-medium text-gray-500">Rol:</span>
                            <p class="text-gray-900">{{ $activityLog->role ?? '-' }}</p>
                        </div>
                        <div>
                            <span class="text-sm font-medium text-gray-500">Amal:</span>
                            <p>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded text-xs font-medium
                                    {{ match($activityLog->action) {
                                        'create' => 'bg-green-100 text-green-800',
                                        'update' => 'bg-blue-100 text-blue-800',
                                        'delete' => 'bg-red-100 text-red-800',
                                        'login' => 'bg-indigo-100 text-indigo-800',
                                        'logout' => 'bg-gray-100 text-gray-800',
                                        default => 'bg-gray-100 text-gray-800',
                                    } }}">
                                    {{ $activityLog->action_label }}
                                </span>
                            </p>
                        </div>
                        <div>
                            <span class="text-sm font-medium text-gray-500">Modul:</span>
                            <p class="text-gray-900">{{ $activityLog->module }}</p>
                        </div>
                        <div>
                            <span class="text-sm font-medium text-gray-500">IP manzil:</span>
                            <p class="text-gray-900">{{ $activityLog->ip_address ?? '-' }}</p>
                        </div>
                        <div>
                            <span class="text-sm font-medium text-gray-500">Tavsif:</span>
                            <p class="text-gray-900">{{ $activityLog->description ?? '-' }}</p>
                        </div>
                    </div>

                    @if($activityLog->subject_type)
                    <div class="mb-6 p-3 bg-gray-50 rounded-lg">
                        <span class="text-sm font-medium text-gray-500">Ob'ekt:</span>
                        <p class="text-gray-900 text-sm">{{ class_basename($activityLog->subject_type) }} #{{ $activityLog->subject_id }}</p>
                    </div>
                    @endif

                    {{-- O'zgarishlar diff --}}
                    @if($activityLog->old_values || $activityLog->new_values)
                    <div class="mt-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">O'zgarishlar</h3>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Maydon</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Oldingi qiymat</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Yangi qiymat</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @php
                                        $oldValues = $activityLog->old_values ?? [];
                                        $newValues = $activityLog->new_values ?? [];
                                        $allKeys = array_unique(array_merge(array_keys($oldValues), array_keys($newValues)));
                                        sort($allKeys);
                                    @endphp
                                    @foreach($allKeys as $key)
                                        @php
                                            $oldVal = $oldValues[$key] ?? null;
                                            $newVal = $newValues[$key] ?? null;
                                            $changed = $oldVal !== $newVal;
                                        @endphp
                                        <tr class="{{ $changed ? 'bg-yellow-50' : '' }}">
                                            <td class="px-4 py-2 font-medium text-gray-700">{{ $key }}</td>
                                            <td class="px-4 py-2 text-red-600">
                                                @if(is_array($oldVal))
                                                    <code class="text-xs">{{ json_encode($oldVal, JSON_UNESCAPED_UNICODE) }}</code>
                                                @else
                                                    {{ $oldVal ?? '-' }}
                                                @endif
                                            </td>
                                            <td class="px-4 py-2 text-green-600">
                                                @if(is_array($newVal))
                                                    <code class="text-xs">{{ json_encode($newVal, JSON_UNESCAPED_UNICODE) }}</code>
                                                @else
                                                    {{ $newVal ?? '-' }}
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                    @endif

                    {{-- User Agent --}}
                    @if($activityLog->user_agent)
                    <div class="mt-6 p-3 bg-gray-50 rounded-lg">
                        <span class="text-sm font-medium text-gray-500">Brauzer:</span>
                        <p class="text-gray-600 text-xs mt-1 break-all">{{ $activityLog->user_agent }}</p>
                    </div>
                    @endif

                </div>
            </div>
        </div>
    </div>
</x-app-layout>
