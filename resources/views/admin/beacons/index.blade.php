<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Beacon'lar (davomat)</h2>
    </x-slot>

    <style>
        .bc-table { width:100%; font-size:13px; border-collapse:separate; }
        .bc-table th { padding:10px 14px; font-size:11px; text-transform:uppercase; letter-spacing:.05em; color:#475569; border-bottom:2px solid #cbd5e1; text-align:left; background:#f1f5f9; }
        .bc-table td { padding:10px 14px; border-bottom:1px solid #f1f5f9; vertical-align:middle; }
        .bc-table tbody tr:hover { background:#eff6ff; }
        .bc-btn { display:inline-flex; align-items:center; gap:6px; padding:7px 12px; border-radius:9px; font-size:12px; font-weight:700; border:1px solid #cbd5e1; background:#fff; cursor:pointer; }
        .bc-btn-primary { background:#1e3a8a; color:#fff; border-color:#1e3a8a; }
        .bc-btn-danger { color:#be123c; border-color:#fecaca; }
        .bc-chip { display:inline-block; padding:3px 9px; border-radius:999px; font-size:11px; font-weight:700; }
        .bc-input { width:100%; border:1px solid #cbd5e1; border-radius:9px; padding:8px 10px; font-size:13px; }
        [x-cloak] { display:none !important; }
    </style>

    @php
        $auditoriumOptions = $auditoriums->map(fn ($a) => [
            'code' => $a->code,
            'name' => $a->name,
            'building' => $a->building_name,
        ])->values();
        $editable = $beacons->map(fn ($b) => [
            'id' => $b->id,
            'uuid' => $b->uuid,
            'major' => $b->major,
            'minor' => $b->minor,
            'auditorium_code' => $b->auditorium_code,
            'label' => $b->label,
        ])->keyBy('id');
    @endphp

    <div class="py-4" x-data="beaconPage()">
        <div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8">

            @if(session('success'))
                <div class="mb-4 p-3 bg-emerald-50 border border-emerald-200 rounded-xl text-sm text-emerald-700 font-medium">{{ session('success') }}</div>
            @endif
            @if($errors->any())
                <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-xl text-sm text-red-700 font-medium">
                    @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
                </div>
            @endif

            <div class="bg-white border border-gray-200 rounded-2xl p-4 mb-4">
                <div class="flex flex-wrap items-center gap-3">
                    <div>
                        <div class="font-bold text-gray-900">Xonalardagi BLE beacon'lar</div>
                        <div class="text-xs text-gray-500 mt-1">
                            Har bir beacon bitta xona kodiga bog'lanadi. Talaba telefoni shu signalni eshitgandagina davomatni tasdiqlay oladi.
                            UUID barcha beacon'larda bir xil bo'lishi mumkin — Major/Minor xonani ajratadi.
                        </div>
                    </div>
                    <button class="bc-btn bc-btn-primary ml-auto" @click="openCreate()">+ Beacon qo'shish</button>
                </div>
            </div>

            <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="bc-table">
                        <thead>
                        <tr>
                            <th>#</th>
                            <th>Xona</th>
                            <th>UUID</th>
                            <th>Major / Minor</th>
                            <th>Yorliq</th>
                            <th>Oxirgi signal</th>
                            <th>Holat</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($beacons as $b)
                            @php $seen = $lastSeen[$b->id] ?? null; @endphp
                            <tr>
                                <td class="text-gray-400">{{ $b->id }}</td>
                                <td>
                                    <div class="font-bold text-gray-900">{{ $b->auditorium_name ?? '—' }}</div>
                                    <div class="text-xs text-gray-500">kod: {{ $b->auditorium_code }}</div>
                                </td>
                                <td class="font-mono text-xs text-gray-700">{{ $b->uuid }}</td>
                                <td class="font-mono">{{ $b->major }} / {{ $b->minor }}</td>
                                <td class="text-gray-700">{{ $b->label ?? '—' }}</td>
                                <td>
                                    @if($seen)
                                        <div class="text-gray-900">{{ \Carbon\Carbon::parse($seen->last_seen)->format('d.m.Y H:i') }}</div>
                                        <div class="text-xs text-gray-500">{{ $seen->students }} ta talaba eshitgan</div>
                                    @else
                                        <span class="text-gray-400">hali yo'q</span>
                                    @endif
                                </td>
                                <td>
                                    @if($b->active)
                                        <span class="bc-chip" style="background:#dcfce7;color:#047857;">faol</span>
                                    @else
                                        <span class="bc-chip" style="background:#f1f5f9;color:#64748b;">o'chiq</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="flex items-center gap-2 justify-end">
                                        <button class="bc-btn" @click="openEdit({{ $b->id }})">Tahrirlash</button>
                                        <form method="POST" action="{{ route('admin.beacons.toggle', $b) }}">
                                            @csrf
                                            <button class="bc-btn" type="submit">{{ $b->active ? "O'chirish" : 'Yoqish' }}</button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.beacons.destroy', $b) }}" onsubmit="return confirm('Beacon butunlay o\'chirilsinmi? Uning signal tarixi ham o\'chadi.')">
                                            @csrf @method('DELETE')
                                            <button class="bc-btn bc-btn-danger" type="submit">Yo'q qilish</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-gray-500 py-10">
                                    Hali beacon qo'shilmagan. Telefondagi <b>nRF Connect</b> ilovasida beacon'ning UUID / Major / Minor qiymatlarini ko'rib, shu yerga kiriting.
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Create / edit modal --}}
        <div x-show="modal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center" style="background:rgba(15,23,42,.45);" @keydown.escape.window="modal = false">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg mx-4" @click.outside="modal = false">
                <form :action="form.id ? '{{ url('/admin/beacons') }}/' + form.id : '{{ route('admin.beacons.store') }}'" method="POST" class="p-5">
                    @csrf
                    <template x-if="form.id"><input type="hidden" name="_method" value="PUT"></template>
                    <div class="text-lg font-bold text-gray-900 mb-4" x-text="form.id ? 'Beacon\'ni tahrirlash' : 'Yangi beacon'"></div>

                    <label class="block text-xs font-semibold text-gray-600 mb-1">UUID</label>
                    <input class="bc-input font-mono mb-3" name="uuid" x-model="form.uuid" placeholder="fda50693-a4e2-4fb1-afcf-c6eb07647825" required>

                    <div class="grid grid-cols-2 gap-3 mb-3">
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Major (bino)</label>
                            <input class="bc-input" name="major" type="number" min="0" max="65535" x-model="form.major" required>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Minor (xona)</label>
                            <input class="bc-input" name="minor" type="number" min="0" max="65535" x-model="form.minor" required>
                        </div>
                    </div>

                    <label class="block text-xs font-semibold text-gray-600 mb-1">Xona (auditorium)</label>
                    <input class="bc-input mb-1" list="auditorium-list" name="auditorium_code" x-model="form.auditorium_code" placeholder="Xona kodini tanlang yoki yozing" required>
                    <datalist id="auditorium-list">
                        @foreach($auditoriumOptions as $a)
                            <option value="{{ $a['code'] }}">{{ $a['name'] }}{{ $a['building'] ? ' — ' . $a['building'] : '' }}</option>
                        @endforeach
                    </datalist>
                    <div class="text-xs text-gray-500 mb-3" x-text="auditoriumHint()"></div>

                    <label class="block text-xs font-semibold text-gray-600 mb-1">Yorliq (ixtiyoriy)</label>
                    <input class="bc-input mb-4" name="label" x-model="form.label" placeholder="2-bino, 3-qavat, 326-xona">

                    <div class="flex justify-end gap-2">
                        <button type="button" class="bc-btn" @click="modal = false">Bekor qilish</button>
                        <button type="submit" class="bc-btn bc-btn-primary">Saqlash</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        function beaconPage() {
            const auditoriums = @json($auditoriumOptions);
            const editable = @json($editable);
            const blank = { id: null, uuid: '', major: '', minor: '', auditorium_code: '', label: '' };
            return {
                modal: false,
                form: { ...blank },
                openCreate() { this.form = { ...blank }; this.modal = true; },
                openEdit(id) { this.form = { ...blank, ...(editable[id] || {}) }; this.modal = true; },
                auditoriumHint() {
                    const a = auditoriums.find(x => String(x.code) === String(this.form.auditorium_code));
                    return a ? (a.name + (a.building ? ' — ' + a.building : '')) : (this.form.auditorium_code ? 'Bunday kod topilmadi' : '');
                },
            };
        }
    </script>
    @endpush
</x-app-layout>
