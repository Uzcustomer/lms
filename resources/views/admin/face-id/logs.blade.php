<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Face ID
        </h2>
    </x-slot>

    <div class="py-4">
        <div class="max-w-full mx-auto sm:px-4 lg:px-6">

            @if(session('success'))
            <div class="mb-4 p-3 bg-green-50 border border-green-200 text-green-700 rounded-lg text-sm">{{ session('success') }}</div>
            @endif

            @if(!empty($settingsError))
            <div class="mb-4 p-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm">⚠️ {{ $settingsError }}</div>
            @endif

            {{-- Tab bar --}}
            <div class="flex gap-2 border-b mb-4">
                <a href="{{ route('admin.face-id.logs', ['tab' => 'logs']) }}"
                   class="px-4 py-2 text-sm font-semibold {{ $activeTab === 'logs' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-500 hover:text-gray-700' }}">
                    📋 Loglar
                </a>
                <a href="{{ route('admin.face-id.logs', ['tab' => 'settings']) }}"
                   class="px-4 py-2 text-sm font-semibold {{ $activeTab === 'settings' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-500 hover:text-gray-700' }}">
                    ⚙️ Sozlamalar
                </a>
            </div>

            @if($activeTab === 'settings')
            {{-- ==================== SOZLAMALAR ==================== --}}
            <form method="POST" action="{{ route('admin.face-id.settings.update') }}">
                @csrf

                {{-- Liveness o'chirilgan; backend validatsiyasi uchun yashirin qiymatlar --}}
                <input type="hidden" name="faceid_blinks_required"    value="{{ $settings['blinks_required'] ?? 0 }}">
                <input type="hidden" name="faceid_head_turn_required" value="{{ !empty($settings['head_turn_required']) ? 1 : 0 }}">
                <input type="hidden" name="faceid_liveness_timeout"   value="{{ $settings['liveness_timeout'] ?? 30 }}">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {{-- Global --}}
                    <div class="settings-card">
                        <div class="settings-card-title">🌐 Global holat</div>
                        <label class="settings-row">
                            <span class="lms-toggle">
                                <input type="hidden" name="faceid_global_enabled" value="0">
                                <input type="checkbox" name="faceid_global_enabled" value="1"
                                       {{ !empty($settings['global_enabled']) ? 'checked' : '' }}>
                                <span class="lms-toggle-track"></span>
                                <span class="lms-toggle-knob"></span>
                            </span>
                            <span class="settings-row-text">
                                <span class="settings-row-title">Face ID login yoqilgan</span>
                                <span class="settings-row-hint">O'chirilsa barcha talabalar parol bilan kiradi</span>
                            </span>
                        </label>
                    </div>

                    {{-- Snapshot --}}
                    <div class="settings-card">
                        <div class="settings-card-title">📸 Snapshot</div>
                        <label class="settings-row">
                            <span class="lms-toggle">
                                <input type="hidden" name="faceid_save_snapshots" value="0">
                                <input type="checkbox" name="faceid_save_snapshots" value="1"
                                       {{ !empty($settings['save_snapshots']) ? 'checked' : '' }}>
                                <span class="lms-toggle-track"></span>
                                <span class="lms-toggle-knob"></span>
                            </span>
                            <span class="settings-row-text">
                                <span class="settings-row-title">Snapshotni saqlash</span>
                                <span class="settings-row-hint">Har bir urinishda talaba rasmi logga saqlanadi</span>
                            </span>
                        </label>
                        <div class="settings-field">
                            <label class="settings-label">Maksimal snapshot hajmi (KB)</label>
                            <input type="number" name="faceid_max_snapshot_kb"
                                   value="{{ $settings['max_snapshot_kb'] ?? 50 }}"
                                   min="10" max="500" class="settings-input" style="width:140px;">
                        </div>
                    </div>

                    {{-- ArcFace --}}
                    <div class="settings-card">
                        <div class="settings-card-title">🎯 ArcFace (server-side)</div>
                        <label class="settings-row">
                            <span class="lms-toggle">
                                <input type="hidden" name="faceid_arcface_enabled" value="0">
                                <input type="checkbox" name="faceid_arcface_enabled" value="1"
                                       {{ !empty($settings['arcface_enabled']) ? 'checked' : '' }}>
                                <span class="lms-toggle-track"></span>
                                <span class="lms-toggle-knob"></span>
                            </span>
                            <span class="settings-row-text">
                                <span class="settings-row-title">ArcFace yoqilgan</span>
                                <span class="settings-row-hint">Asosiy tekshiruv usuli sifatida ishlatiladi</span>
                            </span>
                        </label>
                        <div class="settings-field">
                            <label class="settings-label">Similarity chegarasi (%)</label>
                            <input type="number" name="faceid_arcface_threshold"
                                   value="{{ $settings['arcface_threshold'] ?? 85 }}"
                                   min="50" max="99.9" step="0.1" class="settings-input" style="width:140px;">
                            <span class="settings-hint">Tavsiya: 82–90</span>
                        </div>
                    </div>

                    {{-- Euclidean --}}
                    <div class="settings-card">
                        <div class="settings-card-title">📏 Euclidean distance</div>
                        <div class="settings-field">
                            <label class="settings-label">Distance chegarasi</label>
                            <input type="number" name="faceid_threshold"
                                   value="{{ $settings['threshold'] ?? 0.40 }}"
                                   min="0.10" max="1.00" step="0.01" class="settings-input" style="width:140px;">
                            <span class="settings-hint">Tavsiya: 0.35–0.45 (kichikroq → qat'iyroq)</span>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end mt-4">
                    <button type="submit" class="btn-primary-lms">Saqlash</button>
                </div>
            </form>

            @push('styles')
            <style>
                .settings-card { background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:18px 18px 16px; box-shadow:0 1px 2px rgba(15,23,42,0.04); }
                .settings-card-title { font-size:14px; font-weight:700; color:#1e293b; margin-bottom:14px; display:flex; align-items:center; gap:6px; }
                .settings-row { display:flex; align-items:center; gap:12px; cursor:pointer; padding:8px 0; }
                .settings-row-text { display:flex; flex-direction:column; }
                .settings-row-title { font-size:13px; font-weight:600; color:#0f172a; }
                .settings-row-hint { font-size:11px; color:#64748b; margin-top:2px; }
                .settings-field { margin-top:14px; padding-top:12px; border-top:1px dashed #e2e8f0; }
                .settings-label { display:block; font-size:12px; font-weight:600; color:#475569; margin-bottom:6px; }
                .settings-input { height:36px; padding:0 10px; border:1px solid #cbd5e1; border-radius:8px; background:#fff; font-size:13px; color:#1e293b; }
                .settings-input:focus { outline:none; border-color:#2b5ea7; box-shadow:0 0 0 2px rgba(43,94,167,0.15); }
                .settings-hint { display:block; font-size:11px; color:#94a3b8; margin-top:6px; }
                .btn-primary-lms { height:38px; padding:0 22px; background:linear-gradient(135deg,#1a3268,#2b5ea7); color:#fff; font-size:13px; font-weight:700; border:none; border-radius:8px; cursor:pointer; box-shadow:0 2px 4px rgba(43,94,167,0.25); transition:all .15s; }
                .btn-primary-lms:hover { background:linear-gradient(135deg,#152850,#1e4686); transform:translateY(-1px); }

                /* LMS toggle */
                .lms-toggle { position:relative; display:inline-block; width:42px; height:24px; flex-shrink:0; }
                .lms-toggle input[type=checkbox] { position:absolute; opacity:0; width:0; height:0; }
                .lms-toggle-track { position:absolute; inset:0; background:#cbd5e1; border-radius:9999px; transition:background .2s; }
                .lms-toggle-knob { position:absolute; top:2px; left:2px; width:20px; height:20px; background:#fff; border-radius:50%; box-shadow:0 1px 3px rgba(0,0,0,0.2); transition:transform .2s; }
                .lms-toggle input[type=checkbox]:checked ~ .lms-toggle-track { background:#2b5ea7; }
                .lms-toggle input[type=checkbox]:checked ~ .lms-toggle-knob { transform:translateX(18px); }
            </style>
            @endpush
            @else
            {{-- ==================== LOGLAR ==================== --}}
            <div class="mb-4 text-sm text-gray-600">
                Bugun: <span class="font-semibold">{{ $todaySuccess ?? 0 }}/{{ $todayTotal ?? 0 }}</span> muvaffaqiyatli
            </div>

            <form method="GET" class="bg-white rounded-lg border p-4 mb-4 flex flex-wrap gap-3 items-end">
                <input type="hidden" name="tab" value="logs">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Natija</label>
                    <select name="result" class="border border-gray-300 rounded px-2 py-1.5 text-sm">
                        <option value="">Barchasi</option>
                        <option value="success"         {{ request('result') === 'success'         ? 'selected' : '' }}>Muvaffaqiyatli</option>
                        <option value="failed"          {{ request('result') === 'failed'          ? 'selected' : '' }}>Muvaffaqiyatsiz</option>
                        <option value="liveness_failed" {{ request('result') === 'liveness_failed' ? 'selected' : '' }}>Liveness</option>
                        <option value="not_found"       {{ request('result') === 'not_found'       ? 'selected' : '' }}>Topilmadi</option>
                        <option value="disabled"        {{ request('result') === 'disabled'        ? 'selected' : '' }}>O'chirilgan</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Talaba ID</label>
                    <input type="text" name="student_id_number" value="{{ request('student_id_number') }}"
                           placeholder="ID raqam" class="border border-gray-300 rounded px-2 py-1.5 text-sm w-44">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Sana</label>
                    <input type="date" name="date" value="{{ request('date') }}"
                           class="border border-gray-300 rounded px-2 py-1.5 text-sm">
                </div>
                <button type="submit" class="px-4 py-1.5 bg-blue-600 text-white text-sm rounded-lg">Filter</button>
                <a href="{{ route('admin.face-id.logs', ['tab' => 'logs']) }}" class="px-4 py-1.5 bg-gray-100 text-gray-600 text-sm rounded-lg">Tozalash</a>
            </form>

            <div class="bg-white rounded-lg border overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 border-b">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Vaqt</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Talaba</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Natija</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Yaqinlik</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Sabab</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">IP</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rasmlar</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($logs as $log)
                            @php
                                $colors = [
                                    'success'         => 'bg-green-100 text-green-700',
                                    'failed'          => 'bg-red-100 text-red-700',
                                    'liveness_failed' => 'bg-yellow-100 text-yellow-700',
                                    'not_found'       => 'bg-gray-100 text-gray-600',
                                    'disabled'        => 'bg-gray-100 text-gray-500',
                                ];
                                $labels = [
                                    'success'         => 'Muvaffaqiyatli',
                                    'failed'          => 'Muvaffaqiyatsiz',
                                    'liveness_failed' => 'Liveness',
                                    'not_found'       => 'Topilmadi',
                                    'disabled'        => 'O\'chirilgan',
                                ];
                            @endphp
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-xs text-gray-500 whitespace-nowrap">{{ optional($log->created_at)->format('d.m.Y H:i:s') }}</td>
                                <td class="px-4 py-3">
                                    @if($log->student)
                                        <div class="font-medium text-gray-800 text-xs">{{ $log->student->full_name }}</div>
                                    @endif
                                    <div class="text-gray-400 text-xs font-mono">{{ $log->student_id_number }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $colors[$log->result] ?? 'bg-gray-100 text-gray-600' }}">
                                        {{ $labels[$log->result] ?? $log->result }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-xs">
                                    @if($log->confidence !== null)
                                        <span class="{{ $log->confidence >= 0.6 ? 'text-green-600' : 'text-red-600' }} font-mono">{{ round($log->confidence * 100, 1) }}%</span>
                                        @if($log->distance !== null)
                                        <div class="text-gray-400 text-xs">d={{ round($log->distance, 3) }}</div>
                                        @endif
                                    @else
                                        <span class="text-gray-300">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-xs text-gray-500 max-w-xs truncate">{{ $log->failure_reason ?? '—' }}</td>
                                <td class="px-4 py-3 text-xs text-gray-400 font-mono">{{ $log->ip_address }}</td>
                                <td class="px-4 py-3">
                                    @php
                                        $sp = $studentPhotos[$log->student_id_number] ?? null;
                                    @endphp
                                    @php
                                        $snapshotUrl = $log->snapshot ? route('admin.face-id.logs.snapshot', $log->id) : '';
                                        $studentUrl  = ($sp && $sp->photo_path) ? asset($sp->photo_path) : '';
                                        $titleName   = $log->student->full_name ?? $log->student_id_number ?? '—';
                                    @endphp
                                    <div class="flex items-center gap-2">
                                        <div class="text-center">
                                            <div class="text-[10px] text-gray-400 mb-0.5">Snapshot</div>
                                            @if($snapshotUrl)
                                                <img src="{{ $snapshotUrl }}" alt="snapshot"
                                                     class="compare-thumb w-12 h-12 object-cover rounded border border-gray-200 hover:ring-2 hover:ring-blue-300 cursor-pointer"
                                                     data-snap="{{ $snapshotUrl }}"
                                                     data-stud="{{ $studentUrl }}"
                                                     data-name="{{ $titleName }}">
                                            @else
                                                <div class="w-12 h-12 rounded border border-dashed border-gray-200 flex items-center justify-center text-gray-300 text-xs">—</div>
                                            @endif
                                        </div>
                                        <div class="text-center">
                                            <div class="text-[10px] text-gray-400 mb-0.5">Talaba</div>
                                            @if($studentUrl)
                                                <img src="{{ $studentUrl }}" alt="talaba"
                                                     class="compare-thumb w-12 h-12 object-cover rounded border border-gray-200 hover:ring-2 hover:ring-blue-300 cursor-pointer"
                                                     data-snap="{{ $snapshotUrl }}"
                                                     data-stud="{{ $studentUrl }}"
                                                     data-name="{{ $titleName }}">
                                            @else
                                                <div class="w-12 h-12 rounded border border-dashed border-gray-200 flex items-center justify-center text-gray-300 text-xs">—</div>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-gray-400 text-sm">Loglar topilmadi</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($logs->hasPages())
                <div class="px-4 py-3 border-t">{{ $logs->links() }}</div>
                @endif
            </div>

            {{-- Compare modal --}}
            <div id="compareModal"
                 style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.75); z-index:9999; align-items:center; justify-content:center; padding:20px;"
                 onclick="if(event.target === this) closeCompareModal()">
                <div style="background:#fff; border-radius:14px; width:96vw; max-width:1600px; max-height:96vh; display:flex; flex-direction:column; box-shadow:0 20px 60px rgba(0,0,0,0.4);">
                    <div style="padding:14px 18px; border-bottom:1px solid #e2e8f0; display:flex; align-items:center; justify-content:space-between; flex-shrink:0;">
                        <div style="font-size:14px; font-weight:700; color:#0f172a;" id="compareModalTitle">Rasmlarni solishtirish</div>
                        <button type="button" onclick="closeCompareModal()" style="background:none; border:none; font-size:28px; line-height:1; color:#64748b; cursor:pointer;">&times;</button>
                    </div>
                    <div style="padding:20px; display:grid; grid-template-columns:1fr 1fr; gap:20px; flex:1 1 auto; min-height:0;">
                        <div style="display:flex; flex-direction:column; min-height:0;">
                            <div style="font-size:12px; font-weight:700; color:#475569; margin-bottom:8px; text-transform:uppercase; letter-spacing:0.04em; text-align:center;">Snapshot</div>
                            <div style="background:#f1f5f9; border-radius:10px; flex:1 1 auto; min-height:0; display:flex; align-items:center; justify-content:center; padding:8px; overflow:hidden;">
                                <img id="compareSnapshot" src="" alt="snapshot" style="max-width:100%; max-height:100%; width:auto; height:auto; object-fit:contain; border-radius:8px; display:none;">
                                <span id="compareSnapshotEmpty" style="color:#94a3b8; font-size:13px;">Yo'q</span>
                            </div>
                        </div>
                        <div style="display:flex; flex-direction:column; min-height:0;">
                            <div style="font-size:12px; font-weight:700; color:#475569; margin-bottom:8px; text-transform:uppercase; letter-spacing:0.04em; text-align:center;">Talaba (student_photos)</div>
                            <div style="background:#f1f5f9; border-radius:10px; flex:1 1 auto; min-height:0; display:flex; align-items:center; justify-content:center; padding:8px; overflow:hidden;">
                                <img id="compareStudent" src="" alt="talaba" style="max-width:100%; max-height:100%; width:auto; height:auto; object-fit:contain; border-radius:8px; display:none;">
                                <span id="compareStudentEmpty" style="color:#94a3b8; font-size:13px;">Yo'q</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            @push('scripts')
            <script>
            function openComparePair(snapUrl, studUrl, name) {
                const modal = document.getElementById('compareModal');
                const snap = document.getElementById('compareSnapshot');
                const stud = document.getElementById('compareStudent');
                const snapE = document.getElementById('compareSnapshotEmpty');
                const studE = document.getElementById('compareStudentEmpty');
                document.getElementById('compareModalTitle').textContent = 'Rasmlarni solishtirish — ' + (name || '');
                if (snapUrl) { snap.src = snapUrl; snap.style.display = ''; snapE.style.display = 'none'; }
                else         { snap.src = '';      snap.style.display = 'none'; snapE.style.display = ''; }
                if (studUrl) { stud.src = studUrl; stud.style.display = ''; studE.style.display = 'none'; }
                else         { stud.src = '';      stud.style.display = 'none'; studE.style.display = ''; }
                modal.style.display = 'flex';
            }
            function closeCompareModal() {
                document.getElementById('compareModal').style.display = 'none';
            }
            document.addEventListener('keydown', e => { if (e.key === 'Escape') closeCompareModal(); });
            document.addEventListener('click', function(e) {
                const t = e.target.closest('.compare-thumb');
                if (!t) return;
                openComparePair(t.dataset.snap || '', t.dataset.stud || '', t.dataset.name || '');
            });
            </script>
            @endpush
            @endif

        </div>
    </div>
</x-app-layout>
