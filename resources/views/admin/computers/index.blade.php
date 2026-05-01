<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-sm text-gray-800 leading-tight">
            {{ __('Test markazi kompyuterlari') }}
        </h2>
    </x-slot>

    <div class="py-4">
        <div class="max-w-7xl mx-auto sm:px-4 lg:px-6">
            <div class="bg-white shadow rounded-lg p-4 sm:p-6">

                <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900">{{ __('Kompyuter joylashuvi') }}</h3>
                        <p class="text-xs text-gray-500 mt-1">
                            {{ __('Jami:') }} <strong>{{ $computers->count() }}</strong> ·
                            {{ __('IP:') }} 196.168.7.101 – 196.168.7.160
                        </p>
                    </div>
                    <form method="get" class="flex items-center gap-2">
                        <label class="text-xs text-gray-600">{{ __('Sana') }}:</label>
                        <input type="date" name="date" value="{{ $date }}"
                               onchange="this.form.submit()"
                               class="border border-gray-300 rounded px-2 py-1 text-sm">
                    </form>
                </div>

                <div class="text-xs text-gray-500 mb-3 flex flex-wrap gap-3 items-center">
                    <span class="inline-flex items-center gap-1">
                        <span class="inline-block w-3 h-3 rounded bg-gray-100 border border-gray-300"></span>
                        {{ __('bo\'sh') }}
                    </span>
                    <span class="inline-flex items-center gap-1">
                        <span class="inline-block w-3 h-3 rounded bg-yellow-200 border border-yellow-400"></span>
                        {{ __('rejalashtirilgan') }}
                    </span>
                    <span class="inline-flex items-center gap-1">
                        <span class="inline-block w-3 h-3 rounded bg-red-300 border border-red-500"></span>
                        {{ __('test boshlangan') }}
                    </span>
                    <span class="inline-flex items-center gap-1">
                        <span class="inline-block w-3 h-3 rounded bg-green-200 border border-green-400"></span>
                        {{ __('tugagan') }}
                    </span>
                </div>

                {{-- 5 columns × 15 rows grid (row 1 = bottom; rendered top→down) --}}
                <div class="overflow-x-auto">
                    <div class="inline-grid gap-1 mx-auto"
                         style="grid-template-columns: repeat(5, minmax(80px, 1fr));">
                        @for($r = 15; $r >= 1; $r--)
                            @for($c = 1; $c <= 5; $c++)
                                @php
                                    $pc = $grid[$r][$c] ?? null;
                                    $assign = $pc ? ($assignments->get($pc->number) ?? collect())->first() : null;

                                    // Status colors
                                    $bg = 'bg-white border-dashed border-gray-200'; // empty cell
                                    $text = 'text-gray-300';
                                    if ($pc) {
                                        $bg = 'bg-gray-50 border-gray-300';
                                        $text = 'text-gray-700';
                                        if ($assign) {
                                            switch ($assign->status) {
                                                case 'in_progress':
                                                    $bg = 'bg-red-100 border-red-400';
                                                    $text = 'text-red-900';
                                                    break;
                                                case 'finished':
                                                    $bg = 'bg-green-100 border-green-400';
                                                    $text = 'text-green-900';
                                                    break;
                                                case 'abandoned':
                                                    $bg = 'bg-gray-200 border-gray-400';
                                                    $text = 'text-gray-700';
                                                    break;
                                                default:
                                                    $bg = 'bg-yellow-100 border-yellow-400';
                                                    $text = 'text-yellow-900';
                                            }
                                        }
                                    }
                                @endphp
                                @if($pc)
                                    <button type="button"
                                            class="border-2 rounded {{ $bg }} {{ $text }} p-2 text-left text-xs cursor-pointer hover:shadow-md transition h-[78px]"
                                            onclick="openComputerEditor({{ $pc->id }})"
                                            data-id="{{ $pc->id }}"
                                            data-number="{{ $pc->number }}"
                                            data-ip="{{ $pc->ip_address }}"
                                            data-mac="{{ $pc->mac_address }}"
                                            data-label="{{ $pc->label }}"
                                            data-col="{{ $pc->grid_column }}"
                                            data-row="{{ $pc->grid_row }}"
                                            data-active="{{ $pc->active ? 1 : 0 }}">
                                        <div class="font-bold text-sm">№{{ $pc->number }}</div>
                                        <div class="text-[10px] opacity-70 mt-0.5">{{ $pc->ip_address ?? '—' }}</div>
                                        @if($assign && $assign->student)
                                            <div class="text-[10px] mt-1 truncate" title="{{ $assign->student->full_name }}">
                                                {{ \Illuminate\Support\Str::limit($assign->student->full_name, 18) }}
                                            </div>
                                            @if($assign->planned_start)
                                                <div class="text-[10px] opacity-70">
                                                    {{ $assign->planned_start->format('H:i') }}
                                                </div>
                                            @endif
                                        @endif
                                    </button>
                                @else
                                    <div class="border border-dashed border-gray-200 rounded h-[78px] flex items-center justify-center text-[10px] text-gray-300 select-none">
                                        {{ __('bo\'sh') }}<br>{{ "r{$r}c{$c}" }}
                                    </div>
                                @endif
                            @endfor
                        @endfor
                    </div>
                </div>

                <div class="text-center mt-4 text-xs text-gray-400">
                    ↓ {{ __('Kirish (talabalar bu yerdan kiradi)') }} ↓
                </div>
            </div>
        </div>
    </div>

    {{-- Editor modal (vanilla, no extra deps) --}}
    <div id="computerEditor" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4 p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-bold">№<span id="ce-number"></span> {{ __('kompyuteri') }}</h3>
                <button type="button" onclick="closeComputerEditor()" class="text-gray-400 hover:text-gray-600">✕</button>
            </div>
            <form id="ce-form" class="space-y-3">
                @csrf
                <input type="hidden" id="ce-id">
                <div>
                    <label class="block text-xs text-gray-600 mb-1">{{ __('IP manzil') }}</label>
                    <input type="text" id="ce-ip" class="w-full border border-gray-300 rounded px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs text-gray-600 mb-1">{{ __('MAC manzil') }}</label>
                    <input type="text" id="ce-mac" class="w-full border border-gray-300 rounded px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs text-gray-600 mb-1">{{ __('Belgi (label)') }}</label>
                    <input type="text" id="ce-label" class="w-full border border-gray-300 rounded px-3 py-2 text-sm">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">{{ __('Ustun (1-5)') }}</label>
                        <input type="number" id="ce-col" min="1" max="5" class="w-full border border-gray-300 rounded px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-600 mb-1">{{ __('Qator (1-15, 1=past)') }}</label>
                        <input type="number" id="ce-row" min="1" max="15" class="w-full border border-gray-300 rounded px-3 py-2 text-sm">
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <input type="checkbox" id="ce-active" class="rounded border-gray-300">
                    <label for="ce-active" class="text-sm">{{ __('Aktiv (ishlaydi)') }}</label>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" onclick="closeComputerEditor()" class="px-3 py-2 text-sm border border-gray-300 rounded hover:bg-gray-50">{{ __('Bekor') }}</button>
                    <button type="submit" class="px-4 py-2 text-sm bg-indigo-600 text-white rounded hover:bg-indigo-700">{{ __('Saqlash') }}</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openComputerEditor(id) {
            const btn = document.querySelector(`[data-id="${id}"]`);
            if (!btn) return;
            document.getElementById('ce-id').value = id;
            document.getElementById('ce-number').textContent = btn.dataset.number;
            document.getElementById('ce-ip').value = btn.dataset.ip || '';
            document.getElementById('ce-mac').value = btn.dataset.mac || '';
            document.getElementById('ce-label').value = btn.dataset.label || '';
            document.getElementById('ce-col').value = btn.dataset.col || '';
            document.getElementById('ce-row').value = btn.dataset.row || '';
            document.getElementById('ce-active').checked = btn.dataset.active === '1';
            document.getElementById('computerEditor').classList.remove('hidden');
            document.getElementById('computerEditor').classList.add('flex');
        }
        function closeComputerEditor() {
            document.getElementById('computerEditor').classList.add('hidden');
            document.getElementById('computerEditor').classList.remove('flex');
        }
        document.getElementById('ce-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const id = document.getElementById('ce-id').value;
            const payload = {
                ip_address: document.getElementById('ce-ip').value || null,
                mac_address: document.getElementById('ce-mac').value || null,
                label: document.getElementById('ce-label').value || null,
                grid_column: document.getElementById('ce-col').value ? parseInt(document.getElementById('ce-col').value, 10) : null,
                grid_row: document.getElementById('ce-row').value ? parseInt(document.getElementById('ce-row').value, 10) : null,
                active: document.getElementById('ce-active').checked,
            };
            const resp = await fetch(`{{ url('/admin/computers') }}/${id}`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
                        || document.querySelector('input[name="_token"]')?.value,
                    'Accept': 'application/json',
                },
                body: JSON.stringify(payload),
            });
            if (resp.ok) {
                location.reload();
            } else {
                alert('Saqlashda xatolik: ' + resp.status);
            }
        });
    </script>
</x-app-layout>
