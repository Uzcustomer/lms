<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('notifications.notifications') }}
        </h2>
    </x-slot>

    <div class="py-4 sm:py-6">
        <div class="max-w-4xl mx-auto px-2 sm:px-6 lg:px-8">
            <!-- Toolbar -->
            <div class="mb-3 flex items-center justify-between">
                <a href="{{ route('admin.notifications.index') }}"
                   class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-700 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    {{ __('notifications.back_to_list') }}
                </a>
                <div class="flex items-center gap-1">
                    <form method="POST" action="{{ route('admin.notifications.destroy', $notification) }}" onsubmit="return confirm('{{ __('notifications.confirm_delete') }}')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="p-2 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition-all" title="{{ __('notifications.delete') }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                            </svg>
                        </button>
                    </form>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <!-- Subject -->
                <div class="px-5 sm:px-6 pt-5 pb-3">
                    <div class="flex items-center gap-2.5 flex-wrap">
                        <h1 class="text-xl font-normal text-gray-900 leading-tight">{{ $notification->subject }}</h1>
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium
                            {{ $notification->type === 'system' ? 'bg-purple-50 text-purple-600' : ($notification->type === 'alert' ? 'bg-red-50 text-red-600' : 'bg-gray-100 text-gray-500') }}">
                            {{ __('notifications.type_' . $notification->type) }}
                        </span>
                    </div>
                </div>

                <!-- Sender info -->
                <div class="px-5 sm:px-6 py-3 flex items-start gap-3">
                    @php
                        $senderName = $notification->sender->name ?? $notification->sender->short_name ?? $notification->sender->full_name ?? null;
                        $initial = $senderName ? mb_strtoupper(mb_substr($senderName, 0, 1)) : '?';
                        $colors = ['bg-blue-500', 'bg-green-500', 'bg-purple-500', 'bg-orange-500', 'bg-pink-500', 'bg-teal-500', 'bg-indigo-500'];
                        $colorIndex = $notification->sender_id ? ($notification->sender_id % count($colors)) : 0;
                    @endphp
                    <div class="w-10 h-10 rounded-full flex items-center justify-center text-white text-sm font-semibold flex-shrink-0 {{ $colors[$colorIndex] }}">
                        {{ $initial }}
                    </div>
                    <div class="flex-1 min-w-0" x-data="{ showDetails: false }">
                        <div class="flex items-baseline gap-2 flex-wrap">
                            <span class="text-sm font-semibold text-gray-900">{{ $senderName ?? __('notifications.system') }}</span>
                            <span class="text-xs text-gray-400">{{ ($notification->sent_at ?? $notification->created_at)->format('d M Y, H:i') }}</span>
                        </div>
                        <button @click="showDetails = !showDetails" class="text-xs text-gray-400 hover:text-gray-600 mt-0.5 flex items-center gap-0.5">
                            <span>{{ __('notifications.recipient') }}: {{ $notification->recipient->name ?? $notification->recipient->full_name ?? '—' }}</span>
                            <svg class="w-3 h-3 transition-transform" :class="showDetails ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>
                        <div x-show="showDetails" x-collapse class="mt-2 text-xs text-gray-500 space-y-0.5 bg-gray-50 rounded-lg p-3 border border-gray-100">
                            <p><span class="font-medium text-gray-600">{{ __('notifications.from') }}:</span> {{ $senderName ?? __('notifications.system') }}</p>
                            <p><span class="font-medium text-gray-600">{{ __('notifications.to') }}:</span> {{ $notification->recipient->name ?? $notification->recipient->full_name ?? '—' }}</p>
                            <p><span class="font-medium text-gray-600">{{ __('notifications.date') }}:</span> {{ ($notification->sent_at ?? $notification->created_at)->format('d.m.Y H:i:s') }}</p>
                            <p><span class="font-medium text-gray-600">{{ __('notifications.type') }}:</span> {{ __('notifications.type_' . $notification->type) }}</p>
                        </div>
                    </div>
                </div>

                <!-- Divider -->
                <div class="mx-5 sm:mx-6 border-t border-gray-100"></div>

                <!-- Message Body -->
                <div class="px-5 sm:px-6 py-6 pl-[4.5rem]">
                    <div class="text-sm text-gray-800 leading-7 whitespace-pre-line">{{ $notification->body }}</div>
                </div>

                {{-- KTR tasdiqlash tugmalari - xodimning barcha rollari uchun --}}
                @if(($notification->data['action'] ?? null) === 'ktr_change_approval')
                    @php
                        $changeRequestId = $notification->data['change_request_id'] ?? null;
                        $user = auth()->user();
                        $userRoles = $user->getRoleNames()->toArray();

                        // Joriy foydalanuvchiga tegishli barcha approval larni topish
                        $userApprovals = collect();
                        if ($changeRequestId) {
                            $allApprovals = \App\Models\KtrChangeApproval::where('change_request_id', $changeRequestId)->get();
                            foreach ($allApprovals as $appr) {
                                $canAct = false;
                                // Aniq approver_id orqali
                                if ($appr->approver_id && $user->id == $appr->approver_id) {
                                    $canAct = true;
                                }
                                // Rol orqali (registrator_ofisi, kafedra_mudiri, dekan)
                                if (in_array($appr->role, $userRoles)) {
                                    $canAct = true;
                                }
                                // Admin har doim
                                if ($user->hasRole(['superadmin', 'admin'])) {
                                    $canAct = true;
                                }
                                if ($canAct) {
                                    $userApprovals->push($appr);
                                }
                            }
                        }

                        // Agar foydalanuvchiga tegishli approval topilmasa, xabarnomadagi approval ni ko'rsatish
                        if ($userApprovals->isEmpty()) {
                            $fallbackApproval = $notification->data['approval_id'] ?? null;
                            if ($fallbackApproval) {
                                $appr = \App\Models\KtrChangeApproval::find($fallbackApproval);
                                if ($appr) $userApprovals->push($appr);
                            }
                        }

                        $roleLabels = [
                            'kafedra_mudiri' => 'Kafedra mudiri',
                            'dekan' => 'Dekan',
                            'registrator_ofisi' => 'Registrator ofisi',
                        ];

                        $hasPending = $userApprovals->contains(fn ($a) => $a->status === 'pending');
                    @endphp

                    @if($userApprovals->isNotEmpty())
                        <div class="px-6 py-4 border-t border-gray-200 bg-gray-50">
                            <p class="text-sm font-medium text-gray-700 mb-3">Fan: <strong>{{ $notification->data['subject_name'] ?? '' }}</strong></p>

                            @foreach($userApprovals as $idx => $approval)
                                <div class="flex items-center justify-between py-2 {{ !$loop->last ? 'border-b border-gray-200' : '' }}">
                                    <p class="text-xs text-gray-500">Rol: <strong>{{ $roleLabels[$approval->role] ?? $approval->role }}</strong></p>

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
                            @endforeach
                        </div>

                        @if($hasPending)
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
                        @endif
                    @endif
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
