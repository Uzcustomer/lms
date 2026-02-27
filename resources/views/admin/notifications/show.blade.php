<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('notifications.notifications') }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="mb-4">
                <a href="{{ route('admin.notifications.index') }}"
                   class="inline-flex items-center text-sm text-blue-600 hover:text-blue-800">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                    {{ __('notifications.back_to_list') }}
                </a>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <!-- Header -->
                <div class="px-6 py-4 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h1 class="text-lg font-semibold text-gray-900">{{ $notification->subject }}</h1>
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                {{ $notification->type === 'system' ? 'bg-purple-100 text-purple-800' : ($notification->type === 'alert' ? 'bg-red-100 text-red-800' : ($notification->type === 'info' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800')) }}">
                                {{ __('notifications.type_' . $notification->type) }}
                            </span>
                            <form method="POST" action="{{ route('admin.notifications.destroy', $notification) }}" onsubmit="return confirm('{{ __('notifications.confirm_delete') }}')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-400 hover:text-red-600 p-1" title="{{ __('notifications.delete') }}">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                    </svg>
                                </button>
                            </form>
                        </div>
                    </div>
                    <div class="mt-2 flex items-center gap-4 text-sm text-gray-500">
                        @if($notification->sender)
                        <span>
                            <strong>{{ __('notifications.from') }}:</strong>
                            {{ $notification->sender->name ?? $notification->sender->full_name ?? __('notifications.system') }}
                        </span>
                        @endif
                        <span>
                            <strong>{{ __('notifications.date') }}:</strong>
                            {{ ($notification->sent_at ?? $notification->created_at)->format('d.m.Y H:i') }}
                        </span>
                    </div>
                </div>

                <!-- Body -->
                <div class="px-6 py-6">
                    <div class="prose max-w-none text-gray-700 text-sm leading-relaxed">
                        {!! nl2br(e($notification->body)) !!}
                    </div>
                </div>

                {{-- KTR tasdiqlash tugmalari - faqat shu notificationga tegishli approval --}}
                @if(($notification->data['action'] ?? null) === 'ktr_change_approval')
                    @php
                        $approvalId = $notification->data['approval_id'] ?? null;
                        $approval = $approvalId ? \App\Models\KtrChangeApproval::find($approvalId) : null;

                        $roleLabels = [
                            'kafedra_mudiri' => 'Kafedra mudiri',
                            'dekan' => 'Dekan',
                            'registrator_ofisi' => 'Registrator ofisi',
                        ];
                    @endphp

                    @if($approval)
                        <div class="px-6 py-4 border-t border-gray-200 bg-gray-50">
                            <p class="text-sm font-medium text-gray-700 mb-3">Fan: <strong>{{ $notification->data['subject_name'] ?? '' }}</strong></p>
                            <p class="text-xs text-gray-500 mb-3">Rol: <strong>{{ $roleLabels[$approval->role] ?? $approval->role }}</strong></p>

                            @if($approval->status === 'pending')
                                <div class="flex items-center gap-2" id="ktr-approval-actions-{{ $approval->id }}">
                                    <button onclick="ktrApprove({{ $approval->id }}, 'approved')"
                                            class="inline-flex items-center px-4 py-2 bg-green-600 text-white text-sm font-semibold rounded-lg hover:bg-green-700 transition-colors">
                                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                        </svg>
                                        Tasdiqlash
                                    </button>
                                    <button onclick="ktrApprove({{ $approval->id }}, 'rejected')"
                                            class="inline-flex items-center px-4 py-2 bg-red-600 text-white text-sm font-semibold rounded-lg hover:bg-red-700 transition-colors">
                                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                        </svg>
                                        Rad etish
                                    </button>
                                </div>
                                <div id="ktr-approval-result-{{ $approval->id }}" class="hidden"></div>

                                <script>
                                    function ktrApprove(approvalId, status) {
                                        if (!confirm(status === 'approved' ? 'Rostdan ham tasdiqlaysizmi?' : 'Rostdan ham rad etasizmi?')) return;

                                        const actionsEl = document.getElementById('ktr-approval-actions-' + approvalId);
                                        const resultEl = document.getElementById('ktr-approval-result-' + approvalId);

                                        actionsEl.innerHTML = '<span class="text-sm text-gray-500">Yuborilmoqda...</span>';

                                        fetch(`/admin/ktr/change-approve/${approvalId}`, {
                                            method: 'POST',
                                            headers: {
                                                'Content-Type': 'application/json',
                                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                                'Accept': 'application/json',
                                            },
                                            body: JSON.stringify({ status: status })
                                        })
                                        .then(r => r.json())
                                        .then(data => {
                                            actionsEl.classList.add('hidden');
                                            resultEl.classList.remove('hidden');
                                            if (data.success) {
                                                const color = status === 'approved' ? 'green' : 'red';
                                                const text = status === 'approved' ? 'Tasdiqlandi!' : 'Rad etildi!';
                                                resultEl.innerHTML = `<span class="inline-flex items-center px-3 py-1.5 bg-${color}-100 text-${color}-800 text-sm font-medium rounded-lg">${text}</span>`;
                                            } else {
                                                resultEl.innerHTML = `<span class="inline-flex items-center px-3 py-1.5 bg-red-100 text-red-800 text-sm font-medium rounded-lg">${data.message || 'Xatolik yuz berdi'}</span>`;
                                            }
                                        })
                                        .catch(() => {
                                            actionsEl.classList.add('hidden');
                                            resultEl.classList.remove('hidden');
                                            resultEl.innerHTML = '<span class="inline-flex items-center px-3 py-1.5 bg-red-100 text-red-800 text-sm font-medium rounded-lg">Tarmoq xatoligi</span>';
                                        });
                                    }
                                </script>
                            @elseif($approval->status === 'approved')
                                <span class="inline-flex items-center px-3 py-1.5 bg-green-100 text-green-800 text-sm font-medium rounded-lg">
                                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    Tasdiqlangan
                                    @if($approval->responded_at)
                                        ({{ $approval->responded_at->format('d.m.Y H:i') }})
                                    @endif
                                </span>
                            @elseif($approval->status === 'rejected')
                                <span class="inline-flex items-center px-3 py-1.5 bg-red-100 text-red-800 text-sm font-medium rounded-lg">
                                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                    Rad etilgan
                                    @if($approval->responded_at)
                                        ({{ $approval->responded_at->format('d.m.Y H:i') }})
                                    @endif
                                </span>
                            @endif
                        </div>
                    @endif
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
