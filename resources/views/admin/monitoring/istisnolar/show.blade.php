<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">Istisno #{{ $istisno->id }} — {{ $istisno->detector?->code }}</h2>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            @include('admin.monitoring.partials.nav')

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2 space-y-6">
                    <div class="bg-white rounded-lg shadow p-5">
                        <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                            <h3 class="text-lg font-semibold">{{ $istisno->subject_label }}</h3>
                            @include('admin.monitoring.partials.status', ['istisno' => $istisno])
                        </div>
                        @if($istisno->warning_only)
                            <div class="p-2 mb-3 text-sm bg-yellow-50 border border-yellow-200 rounded">
                                Ogohlantirish rejimi: istisno mas'ulga avtomatik yuborilmaydi (normasi tekshirilishi kerak yoki muddat hali kelmagan).
                            </div>
                        @endif
                        <h4 class="font-semibold text-sm text-gray-700 mb-2">Nima topildi</h4>
                        @include('admin.monitoring.partials.details', ['details' => $istisno->details])
                    </div>

                    <div class="bg-white rounded-lg shadow p-5">
                        <h4 class="font-semibold mb-2">Qoida va huquqiy asos</h4>
                        <p class="text-sm mb-2">{{ $istisno->detector?->rule_text }}</p>
                        <p class="text-sm text-gray-600"><b>Asos:</b> {{ $istisno->detector?->legal_basis ?? '—' }}</p>
                        <p class="text-sm text-gray-600"><b>Aniqlash:</b> {{ $istisno->detector?->detection_text ?? '—' }}</p>
                    </div>

                    <div class="bg-white rounded-lg shadow p-5">
                        <h4 class="font-semibold mb-3">Tarix, javoblar va dalillar</h4>
                        <div class="space-y-3">
                            @foreach($istisno->comments as $c)
                                <div class="border-l-4 pl-3 {{ $c->kind === 'tizim' ? 'border-gray-300' : ($c->kind === 'javob' ? 'border-blue-400' : 'border-green-400') }}">
                                    <div class="text-xs text-gray-500">
                                        {{ $c->created_at->format('d.m.Y H:i') }} · {{ $c->authorName() }}
                                        @if($c->new_status) · {{ \App\Models\MonitoringIstisno::STATUSES[$c->old_status] ?? $c->old_status ?? '—' }} → {{ \App\Models\MonitoringIstisno::STATUSES[$c->new_status] ?? $c->new_status }} @endif
                                    </div>
                                    @if($c->body)<div class="text-sm whitespace-pre-line">{{ $c->body }}</div>@endif
                                    @foreach($istisno->evidences->where('comment_id', $c->id) as $e)
                                        <div class="text-sm">📎 <a href="{{ route('admin.monitoring.istisnolar.evidence', [$istisno, $e]) }}">{{ $e->original_name }}</a></div>
                                    @endforeach
                                </div>
                            @endforeach
                        </div>

                        @if($canRespond)
                            <form method="POST" action="{{ route('admin.monitoring.istisnolar.respond', $istisno) }}" enctype="multipart/form-data" class="mt-5 space-y-2">
                                @csrf
                                <textarea name="body" rows="3" class="w-full border-gray-300 rounded text-sm" placeholder="Javob yoki izoh (nima qilindi, buyruq raqami va h.k.)">{{ old('body') }}</textarea>
                                <input type="file" name="files[]" multiple class="text-sm" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx">
                                <div class="flex flex-wrap items-center gap-4">
                                    @if($isOwner)
                                        <label class="text-sm"><input type="checkbox" name="mark_done" value="1"> «Bajarildi» deb belgilash (yopishni administrator tasdiqlaydi)</label>
                                    @endif
                                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded text-sm">Yuborish</button>
                                </div>
                            </form>
                        @endif
                    </div>
                </div>

                <div class="space-y-6">
                    <div class="bg-white rounded-lg shadow p-5 text-sm space-y-1">
                        <div><span class="text-gray-500">Detektor:</span> {{ $istisno->detector?->code }} — {{ $istisno->detector?->title }}</div>
                        <div><span class="text-gray-500">Guruh:</span> {{ $istisno->detector?->group_name }}</div>
                        <div><span class="text-gray-500">Topilgan:</span> {{ $istisno->found_at?->format('d.m.Y H:i') }}</div>
                        <div><span class="text-gray-500">Oxirgi tekshiruv:</span> {{ $istisno->last_seen_at?->format('d.m.Y H:i') }}</div>
                        <div><span class="text-gray-500">Javob muddati:</span> <span class="{{ $istisno->isOverdue() ? 'text-red-700 font-semibold' : '' }}">{{ $istisno->due_at?->format('d.m.Y') ?? '—' }}</span></div>
                        <div><span class="text-gray-500">Mas'ul:</span> {{ \App\Services\Monitoring\MonitoringAccess::displayName($istisno->owner) }}</div>
                        @if($istisno->closed_at)<div><span class="text-gray-500">Yopilgan:</span> {{ $istisno->closed_at->format('d.m.Y H:i') }}</div>@endif
                    </div>

                    @if($canAssign && $istisno->isOpen())
                        <form method="POST" action="{{ route('admin.monitoring.istisnolar.assign', $istisno) }}" class="bg-white rounded-lg shadow p-5 space-y-2">
                            @csrf
                            <h4 class="font-semibold text-sm">Mas'ul biriktirish</h4>
                            <select name="owner" class="mon-staff-select">
                                @if($istisno->owner)<option value="{{ \App\Services\Monitoring\MonitoringAccess::ownerKey($istisno->owner) }}" selected>{{ \App\Services\Monitoring\MonitoringAccess::displayName($istisno->owner) }}</option>@endif
                            </select>
                            <button type="submit" class="px-4 py-2 bg-gray-800 text-white rounded text-sm">Saqlash va xabar berish</button>
                        </form>
                    @endif

                    @if($canClose)
                        <form method="POST" action="{{ route('admin.monitoring.istisnolar.transition', $istisno) }}" class="bg-white rounded-lg shadow p-5 space-y-2">
                            @csrf
                            <h4 class="font-semibold text-sm">Holat (monitoring administratori)</h4>
                            <textarea name="body" rows="2" class="w-full border-gray-300 rounded text-sm" placeholder="Izoh (asosli istisno va qayta ochish uchun majburiy)"></textarea>
                            <div class="flex flex-wrap gap-2">
                                @if($istisno->isOpen())
                                    <button name="action" value="close" class="px-3 py-1.5 bg-green-600 text-white rounded text-sm">Yopish</button>
                                    <button name="action" value="justify" class="px-3 py-1.5 bg-purple-600 text-white rounded text-sm">Asosli istisno</button>
                                @endif
                                @if(!$istisno->isOpen() || $istisno->status === 'javob_berildi')
                                    <button name="action" value="reopen" class="px-3 py-1.5 bg-orange-600 text-white rounded text-sm">Qayta ochish</button>
                                @endif
                            </div>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @include('admin.monitoring.partials.staff-select-script')
</x-app-layout>
