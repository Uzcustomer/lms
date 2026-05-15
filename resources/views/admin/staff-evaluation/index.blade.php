<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Xodimlarni baholash
        </h2>
    </x-slot>

    <div class="py-4">
        <div class="max-w-full mx-auto sm:px-4 lg:px-6">

            @if(session('success'))
                <div class="mb-4 p-3 rounded-lg flex items-center gap-2" style="background:#dcfce7;border:1px solid #86efac;color:#166534;">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    <span class="text-sm font-medium">{{ session('success') }}</span>
                </div>
            @endif

            @php $activeTab = request('tab', 'list'); @endphp

            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                {{-- Filter va amallar --}}
                <div class="filter-container">
                    <form method="GET" action="{{ route('admin.staff-evaluation.index') }}" class="flex items-end gap-2 flex-wrap">
                        <input type="hidden" name="tab" value="{{ request('tab', 'list') }}">
                        <div class="filter-item" style="flex:1;min-width:240px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#3b82f6;"></span> Qidirish</label>
                            <input type="text" name="search" value="{{ request('search') }}"
                                   placeholder="Ism, familya, kafedra..." class="filter-input">
                        </div>
                        <button type="submit" class="btn-apply">
                            <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            Qidirish
                        </button>
                        @if(request('search'))
                            <a href="{{ route('admin.staff-evaluation.index', ['tab' => request('tab', 'list')]) }}" class="btn-secondary">Tozalash</a>
                        @endif

                        <div style="flex:1;"></div>

                        @if($activeTab === 'list')
                            <button type="button" id="btn-generate-selected" class="btn-success" disabled
                                    onclick="submitGenerateSelected()">
                                <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6.364 1.636l-.707.707M20 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                                <span id="btn-generate-selected-label">Tanlanganlar uchun QR yaratish</span>
                            </button>
                        @endif

                        <button type="button" class="btn-apply" onclick="document.getElementById('form-all-qr').submit();">
                            Barchaga QR yaratish
                        </button>

                        @if($activeTab === 'qr')
                            <button type="button" id="btn-download-selected-qr" class="btn-success" disabled
                                    onclick="downloadSelectedQrZip()">
                                <svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                <span id="btn-download-selected-qr-label">Tanlanganlarni ZIP yuklab olish</span>
                            </button>
                            <button type="button" class="btn-danger"
                                    onclick="if(confirm('Barcha QR kodlar va baholar o\'chiriladi. Davom etasizmi?')) document.getElementById('form-delete-all-qr').submit();">
                                Hammasini o'chirish
                            </button>
                        @endif
                    </form>

                    <form id="form-all-qr" method="POST" action="{{ route('admin.staff-evaluation.generate-all-qr') }}" style="display:none;">@csrf</form>
                    @if($activeTab === 'qr')
                    <form id="form-delete-all-qr" method="POST" action="{{ route('admin.staff-evaluation.delete-all-qr') }}" style="display:none;">@csrf @method('DELETE')</form>
                    @endif
                </div>

                {{-- Tablar --}}
                <div class="tab-bar">
                    <a href="{{ route('admin.staff-evaluation.index', array_merge(request()->only('search'), ['tab' => 'list'])) }}"
                       class="tab-link {{ $activeTab === 'list' ? 'tab-active' : '' }}">Ro'yxat</a>
                    <a href="{{ route('admin.staff-evaluation.index', array_merge(request()->only('search'), ['tab' => 'qr'])) }}"
                       class="tab-link {{ $activeTab === 'qr' ? 'tab-active' : '' }}">QR kodlar</a>
                    <a href="{{ route('admin.staff-evaluation.index', array_merge(request()->only('search'), ['tab' => 'shablon'])) }}"
                       class="tab-link {{ $activeTab === 'shablon' ? 'tab-active' : '' }}">Shablon</a>
                </div>

        @if($activeTab === 'list')
        {{-- ==================== RO'YXAT TABI ==================== --}}
        <form id="form-generate-selected" method="POST" action="{{ route('admin.staff-evaluation.generate-selected-qr') }}">
            @csrf
            @if(request('search'))<input type="hidden" name="search" value="{{ request('search') }}">@endif
            <div style="overflow-x:auto;">
                <table class="staff-table">
                    <thead>
                        <tr>
                            <th style="width:44px;text-align:center;padding-left:14px;">
                                <input type="checkbox" id="select-all" class="cb-styled" onchange="toggleSelectAll(this)">
                            </th>
                            <th style="width:60px;">#</th>
                            <th>Xodim</th>
                            <th>Kafedra</th>
                            <th style="text-align:center;">O'rtacha baho</th>
                            <th style="text-align:center;">Baholar soni</th>
                            <th style="text-align:center;">QR</th>
                            <th style="text-align:center;">Amallar</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($teachers as $teacher)
                        <tr>
                            <td style="text-align:center;padding-left:14px;">
                                @if(!$teacher->eval_qr_token)
                                    <input type="checkbox" name="teacher_ids[]" value="{{ $teacher->id }}" class="cb-styled row-checkbox" onchange="updateSelected()">
                                @else
                                    <span style="color:#cbd5e1;font-size:14px;" title="QR kodi mavjud">—</span>
                                @endif
                            </td>
                            <td class="td-num">{{ $teachers->firstItem() + $loop->index }}</td>
                            <td>
                                <a href="{{ route('admin.staff-evaluation.show', $teacher) }}" class="staff-name-link">
                                    {{ $teacher->full_name }}
                                </a>
                                @if($teacher->staff_position)
                                    <div style="font-size:11px;color:#94a3b8;margin-top:2px;">{{ $teacher->staff_position }}</div>
                                @endif
                            </td>
                            <td style="font-size:13px;color:#64748b;">{{ $teacher->department ?? '—' }}</td>
                            <td style="text-align:center;">
                                @if($teacher->staff_evaluations_avg_rating)
                                    <div style="display:inline-flex;align-items:center;gap:4px;">
                                        <span style="color:#f59e0b;">&#9733;</span>
                                        <span style="font-weight:700;color:#0f172a;">{{ number_format($teacher->staff_evaluations_avg_rating, 1) }}</span>
                                    </div>
                                @else
                                    <span style="color:#cbd5e1;">—</span>
                                @endif
                            </td>
                            <td style="text-align:center;font-size:13px;color:#475569;font-weight:600;">
                                {{ $teacher->staff_evaluations_count }}
                            </td>
                            <td style="text-align:center;">
                                @if($teacher->eval_qr_token)
                                    <span class="badge-filled">&#10003; Mavjud</span>
                                @else
                                    <span style="color:#cbd5e1;">—</span>
                                @endif
                            </td>
                            <td style="text-align:center;">
                                <div style="display:inline-flex;gap:4px;flex-wrap:wrap;justify-content:center;">
                                    @if($teacher->eval_qr_token)
                                        <a href="{{ route('admin.staff-evaluation.download-qr', $teacher) }}" class="action-btn action-btn-indigo">Yuklab olish</a>
                                    @else
                                        <button type="button" onclick="event.stopPropagation();singleQr({{ $teacher->id }});" class="action-btn action-btn-green">QR yaratish</button>
                                    @endif
                                    <a href="{{ route('admin.staff-evaluation.show', $teacher) }}" class="action-btn action-btn-gray">Batafsil</a>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" style="text-align:center;padding:40px;color:#94a3b8;">
                                @if(request('search'))
                                    "{{ request('search') }}" bo'yicha xodim topilmadi.
                                @else
                                    Xodimlar topilmadi.
                                @endif
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </form>
        {{-- Yagona xodim uchun shaxsiy QR yaratish formasi --}}
        <form id="form-single-qr" method="POST" action="">@csrf</form>

        @elseif($activeTab === 'qr')
        {{-- ==================== QR KODLAR TABI ==================== --}}
        @if($teachers->count() > 0)
        <div class="px-4 pt-3 flex items-center gap-2">
            <label class="flex items-center gap-2 text-sm text-slate-600">
                <input type="checkbox" id="qr-select-all" class="cb-styled" onchange="toggleSelectAllQr(this)">
                Hammasini tanlash
            </label>
        </div>
        @endif
        <div class="space-y-3 p-3">
            @forelse($teachers as $teacher)
            <div class="flex items-center gap-3 border rounded-lg p-3 hover:shadow-md transition-shadow group bg-white">
                <input type="checkbox"
                       class="cb-styled qr-row-checkbox"
                       data-teacher-id="{{ $teacher->id }}"
                       data-teacher-slug="{{ Str::slug($teacher->full_name) }}"
                       onchange="updateSelectedQr()">
                <div class="flex-shrink-0 w-8 text-center text-sm font-bold text-gray-400">
                    {{ $teachers->firstItem() + $loop->index }}
                </div>
                <a href="{{ route('admin.staff-evaluation.show', $teacher) }}" class="flex items-center gap-3 flex-1 min-w-0">
                    <div class="flex-shrink-0 relative">
                        {!! QrCode::size(80)->errorCorrection('H')->margin(0)->generate(route('staff-evaluate.form', $teacher->eval_qr_token)) !!}
                        @if($template['show_logo'])
                        <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                            <div class="bg-white rounded-full" style="padding:3px;">
                                <img src="{{ asset('logo.png') }}" alt="Logo" class="rounded-full" style="width:28px;height:28px;">
                            </div>
                        </div>
                        @endif
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="font-semibold text-gray-800 group-hover:text-blue-600 transition-colors">{{ $teacher->full_name }}</div>
                        @if($teacher->department)
                            <div class="text-sm text-gray-400">{{ $teacher->department }}</div>
                        @endif
                        @if($teacher->staff_position)
                            <div class="text-xs text-gray-400">{{ $teacher->staff_position }}</div>
                        @endif
                    </div>
                </a>
                <div class="flex-shrink-0 text-right">
                    @if($teacher->staff_evaluations_avg_rating)
                        @php
                            $avg = $teacher->staff_evaluations_avg_rating;
                            if ($avg >= 4) $rColor = 'text-green-600';
                            elseif ($avg >= 3) $rColor = 'text-yellow-600';
                            else $rColor = 'text-red-600';
                        @endphp
                        <div class="flex items-center gap-1 {{ $rColor }}">
                            <span>&#9733;</span>
                            <span class="text-lg font-bold">{{ number_format($avg, 1) }}</span>
                        </div>
                        <div class="text-xs text-gray-400">{{ $teacher->staff_evaluations_count }} ta baho</div>
                    @else
                        <span class="text-gray-300 text-sm">Baholar yo'q</span>
                    @endif
                </div>
            </div>
            @empty
            <div class="text-center text-gray-500 py-8">
                @if(request('search'))
                    "{{ request('search') }}" bo'yicha QR kodli xodim topilmadi.
                @else
                    QR kodli xodimlar yo'q. Avval QR kodlarni yarating.
                @endif
            </div>
            @endforelse
        </div>

        {{-- Yashirin shablon kartochkalari (zip yuklash uchun) --}}
        <div id="qr-hidden-cards" style="position:fixed; left:-10000px; top:0; pointer-events:none;">
            @foreach($teachers as $teacher)
                @include('admin.staff-evaluation._card', ['cardId' => 'hidden-card-' . $teacher->id])
            @endforeach
        </div>

        @push('scripts')
        <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
        <script>
        function toggleSelectAllQr(master) {
            document.querySelectorAll('.qr-row-checkbox').forEach(cb => cb.checked = master.checked);
            updateSelectedQr();
        }
        function updateSelectedQr() {
            const count = document.querySelectorAll('.qr-row-checkbox:checked').length;
            const btn = document.getElementById('btn-download-selected-qr');
            const lbl = document.getElementById('btn-download-selected-qr-label');
            if (!btn) return;
            if (count > 0) {
                btn.disabled = false;
                lbl.textContent = 'Tanlanganlarni ZIP yuklab olish (' + count + ')';
            } else {
                btn.disabled = true;
                lbl.textContent = 'Tanlanganlarni ZIP yuklab olish';
            }
        }
        async function downloadSelectedQrZip() {
            const checked = Array.from(document.querySelectorAll('.qr-row-checkbox:checked'));
            if (checked.length === 0) return;
            const btn = document.getElementById('btn-download-selected-qr');
            const lbl = document.getElementById('btn-download-selected-qr-label');
            btn.disabled = true;
            const zip = new JSZip();
            for (let i = 0; i < checked.length; i++) {
                const cb = checked[i];
                const id = cb.dataset.teacherId;
                const slug = cb.dataset.teacherSlug || ('xodim-' + id);
                const el = document.getElementById('hidden-card-' + id);
                if (!el) continue;
                lbl.textContent = 'Tayyorlanmoqda... (' + (i + 1) + '/' + checked.length + ')';
                try {
                    const canvas = await html2canvas(el, { scale: 4, backgroundColor: '#ffffff', useCORS: true });
                    const blob = await new Promise(res => canvas.toBlob(res, 'image/png'));
                    zip.file('qr-' + slug + '.png', blob);
                } catch (e) {
                    console.error('Karta yaratilmadi:', id, e);
                }
            }
            lbl.textContent = 'ZIP yaratilmoqda...';
            const content = await zip.generateAsync({ type: 'blob' });
            const url = URL.createObjectURL(content);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'qr-shablonlar.zip';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
            updateSelectedQr();
        }
        </script>
        @endpush
        @elseif($activeTab === 'shablon')
        {{-- ==================== SHABLON TABI ==================== --}}
        {{-- Manual shablon tahriri --}}
        <div class="p-4 sm:p-6 border-b bg-gradient-to-br from-slate-50 to-slate-100">
            <div class="flex items-center justify-between mb-3">
                <div>
                    <h3 class="text-base font-bold text-slate-800">Shablonni qo'lda tayyorlash</h3>
                    <p class="text-xs text-slate-500 mt-1">Quyidagi maydonlarni to'ldiring — barcha xodim kartochkalarida shu ma'lumotlar ko'rsatiladi.</p>
                </div>
                <button type="button" onclick="toggleTplEditor()" class="btn-secondary" id="tpl-toggle-btn">Tahrirlash</button>
            </div>

            <form method="POST" action="{{ route('admin.staff-evaluation.save-template') }}" id="tpl-form" style="display:none;">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label class="filter-label"><span class="fl-dot" style="background:#1e3a8a;"></span> Muassasa nomi</label>
                        <input type="text" name="institution" value="{{ old('institution', $template['institution']) }}" class="filter-input" style="width:100%;" maxlength="255">
                    </div>
                    <div>
                        <label class="filter-label"><span class="fl-dot" style="background:#1e3a8a;"></span> Filial / bo'lim</label>
                        <input type="text" name="branch" value="{{ old('branch', $template['branch']) }}" class="filter-input" style="width:100%;" maxlength="255">
                    </div>
                    <div>
                        <label class="filter-label"><span class="fl-dot" style="background:#6b7280;"></span> Lavozim yorlig'i</label>
                        <input type="text" name="position_label" value="{{ old('position_label', $template['position_label']) }}" class="filter-input" style="width:100%;" maxlength="255" placeholder="Masalan: Registrator ofisi xodimi:">
                    </div>
                    <div>
                        <label class="filter-label"><span class="fl-dot" style="background:#0f766e;"></span> Sarlavha (ixtiyoriy)</label>
                        <input type="text" name="title" value="{{ old('title', $template['title']) }}" class="filter-input" style="width:100%;" maxlength="255" placeholder="Masalan: Xodimni baholash">
                    </div>
                    <div class="md:col-span-2">
                        <label class="filter-label"><span class="fl-dot" style="background:#0f766e;"></span> Tavsif (ixtiyoriy)</label>
                        <textarea name="description" rows="2" class="filter-input" style="width:100%;height:auto;padding:8px 10px;" maxlength="1000" placeholder="QR kod tagida ko'rinadigan qisqa matn">{{ old('description', $template['description']) }}</textarea>
                    </div>
                    <div>
                        <label class="filter-label"><span class="fl-dot" style="background:#dc2626;"></span> Eni (mm)</label>
                        <input type="number" step="0.1" min="20" max="300" name="width_mm" value="{{ old('width_mm', $template['width_mm']) }}" class="filter-input" style="width:100%;">
                    </div>
                    <div>
                        <label class="filter-label"><span class="fl-dot" style="background:#dc2626;"></span> Bo'yi (mm)</label>
                        <input type="number" step="0.1" min="20" max="300" name="height_mm" value="{{ old('height_mm', $template['height_mm']) }}" class="filter-input" style="width:100%;">
                    </div>
                    <div class="md:col-span-2 flex items-center gap-2">
                        <label class="flex items-center gap-2 text-sm text-slate-700">
                            <input type="checkbox" name="show_logo" value="1" class="cb-styled" {{ $template['show_logo'] ? 'checked' : '' }}>
                            QR markazida logotipni ko'rsatish
                        </label>
                    </div>
                </div>
                {{-- Joylashuv: QR / matn (interaktiv tahrirlovchidan keladi) --}}
                <input type="hidden" name="qr_size_mm" id="lay-qr-size" value="{{ $template['qr_size_mm'] }}">
                <input type="hidden" name="qr_x_mm"    id="lay-qr-x"    value="{{ $template['qr_x_mm'] }}">
                <input type="hidden" name="qr_y_mm"    id="lay-qr-y"    value="{{ $template['qr_y_mm'] }}">
                <input type="hidden" name="text_x_mm"  id="lay-tx-x"    value="{{ $template['text_x_mm'] }}">
                <input type="hidden" name="text_y_mm"  id="lay-tx-y"    value="{{ $template['text_y_mm'] }}">
                <input type="hidden" name="text_w_mm"  id="lay-tx-w"    value="{{ $template['text_w_mm'] }}">
                <input type="hidden" name="text_h_mm"  id="lay-tx-h"    value="{{ $template['text_h_mm'] }}">
                <input type="hidden" name="text_size_mm" id="lay-tx-size" value="{{ $template['text_size_mm'] }}">

                <div class="flex items-center gap-2 mt-3">
                    <button type="submit" class="btn-apply">Saqlash</button>
                    <button type="button" onclick="toggleTplEditor()" class="btn-secondary">Bekor qilish</button>
                </div>
            </form>

            {{-- Interaktiv joylashuv tahrirlovchisi --}}
            <div id="layout-editor" class="mt-4 bg-white rounded-lg border p-4" style="display:none;">
                <div class="flex items-center justify-between mb-3">
                    <h4 class="text-sm font-bold text-slate-800">Joylashuv: QR va matnni sichqoncha bilan harakatlantiring va o'lchamini o'zgartiring</h4>
                    <div class="text-xs text-slate-500">Burchakni sudrab o'lchamini, ichidan sudrab o'rnini o'zgartiring.</div>
                </div>
                <div id="lay-stage-wrap" style="display:flex; gap:18px; align-items:flex-start; flex-wrap:wrap;">
                    <div id="lay-stage" style="position:relative; background:white; border:2px dashed #cbd5e1; box-shadow:0 1px 3px rgba(0,0,0,.06);"></div>
                    <div style="display:flex; flex-direction:column; gap:12px; min-width:200px;">
                        <div>
                            <label class="text-xs text-slate-500 block mb-1">Matn shrifti (mm)</label>
                            <input id="lay-text-size-input" type="range" min="1" max="6" step="0.1" value="{{ $template['text_size_mm'] }}" style="width:100%;">
                            <div class="text-xs text-slate-700 mt-1"><span id="lay-text-size-label">{{ $template['text_size_mm'] }}</span> mm</div>
                        </div>
                        <div>
                            <label class="text-xs text-slate-500 block mb-1">QR o'lchami (mm)</label>
                            <input id="lay-qr-size-input" type="range" min="10" max="80" step="0.5" value="{{ $template['qr_size_mm'] }}" style="width:100%;">
                            <div class="text-xs text-slate-700 mt-1"><span id="lay-qr-size-label">{{ $template['qr_size_mm'] }}</span> mm</div>
                        </div>
                        <button type="button" onclick="resetLayout()" class="btn-secondary text-xs" style="padding:6px 10px;">Standartga qaytarish</button>
                        <div class="text-xs text-slate-400">Saqlash uchun yuqoridagi "Saqlash" tugmasini bosing.</div>
                    </div>
                </div>
            </div>

            <div id="tpl-preview" class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                <div class="bg-white rounded-lg border p-3">
                    <div class="text-[11px] uppercase font-bold text-slate-400 tracking-wide mb-1">Muassasa</div>
                    <div class="font-semibold text-slate-800">{{ $template['institution'] ?: '—' }}</div>
                </div>
                <div class="bg-white rounded-lg border p-3">
                    <div class="text-[11px] uppercase font-bold text-slate-400 tracking-wide mb-1">Filial</div>
                    <div class="font-semibold text-slate-800">{{ $template['branch'] ?: '—' }}</div>
                </div>
                <div class="bg-white rounded-lg border p-3">
                    <div class="text-[11px] uppercase font-bold text-slate-400 tracking-wide mb-1">Lavozim yorlig'i</div>
                    <div class="font-semibold text-slate-800">{{ $template['position_label'] ?: '—' }}</div>
                </div>
                <div class="bg-white rounded-lg border p-3">
                    <div class="text-[11px] uppercase font-bold text-slate-400 tracking-wide mb-1">Sarlavha / tavsif</div>
                    <div class="font-semibold text-slate-800">{{ $template['title'] ?: '—' }}</div>
                    @if($template['description'])
                    <div class="text-xs text-slate-500 mt-1">{{ $template['description'] }}</div>
                    @endif
                </div>
                <div class="bg-white rounded-lg border p-3 md:col-span-2">
                    <div class="text-[11px] uppercase font-bold text-slate-400 tracking-wide mb-1">O'lcham</div>
                    <div class="font-semibold text-slate-800">{{ rtrim(rtrim(number_format($template['width_mm'], 1, '.', ''), '0'), '.') }} mm × {{ rtrim(rtrim(number_format($template['height_mm'], 1, '.', ''), '0'), '.') }} mm</div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6 p-4">
            @forelse($teachers as $teacher)
            @if($teacher->eval_qr_token)
            <div class="border rounded-lg overflow-hidden shadow-sm bg-white flex flex-col items-center">
                @include('admin.staff-evaluation._card', ['cardId' => 'card-' . $teacher->id])
                {{-- Yuklab olish tugmasi --}}
                <div class="border-t px-4 py-3 text-center bg-gray-50">
                    <button onclick="downloadCard('card-{{ $teacher->id }}', '{{ Str::slug($teacher->full_name) }}')"
                            class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 inline-flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                        </svg>
                        Rasm sifatida yuklab olish
                    </button>
                </div>
            </div>
            @endif
            @empty
            <div class="col-span-full text-center text-gray-500 py-8">
                QR kodli xodimlar yo'q. Avval QR kodlarni yarating.
            </div>
            @endforelse
        </div>

        @push('scripts')
        <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
        <script>
        function toggleTplEditor() {
            const f = document.getElementById('tpl-form');
            const p = document.getElementById('tpl-preview');
            const b = document.getElementById('tpl-toggle-btn');
            const le = document.getElementById('layout-editor');
            if (f.style.display === 'none' || !f.style.display) {
                f.style.display = '';
                p.style.display = 'none';
                b.style.display = 'none';
                if (le) { le.style.display = ''; setupLayoutEditor(); }
            } else {
                f.style.display = 'none';
                p.style.display = '';
                b.style.display = '';
                if (le) le.style.display = 'none';
            }
        }

        // ─── Interaktiv joylashuv tahrirlovchisi (QR + matn drag/resize) ───
        const PX_PER_MM = 4; // 1mm = 4px ekranda
        let layoutState = {
            wMm: parseFloat({{ $template['width_mm'] }}),
            hMm: parseFloat({{ $template['height_mm'] }}),
            qr:  { x: parseFloat({{ $template['qr_x_mm'] }}),    y: parseFloat({{ $template['qr_y_mm'] }}),    s: parseFloat({{ $template['qr_size_mm'] }}) },
            tx:  { x: parseFloat({{ $template['text_x_mm'] }}),  y: parseFloat({{ $template['text_y_mm'] }}),  w: parseFloat({{ $template['text_w_mm'] }}), h: parseFloat({{ $template['text_h_mm'] }}), s: parseFloat({{ $template['text_size_mm'] }}) },
        };

        function syncFormFields() {
            document.getElementById('lay-qr-size').value = layoutState.qr.s.toFixed(2);
            document.getElementById('lay-qr-x').value    = layoutState.qr.x.toFixed(2);
            document.getElementById('lay-qr-y').value    = layoutState.qr.y.toFixed(2);
            document.getElementById('lay-tx-x').value    = layoutState.tx.x.toFixed(2);
            document.getElementById('lay-tx-y').value    = layoutState.tx.y.toFixed(2);
            document.getElementById('lay-tx-w').value    = layoutState.tx.w.toFixed(2);
            document.getElementById('lay-tx-h').value    = layoutState.tx.h.toFixed(2);
            document.getElementById('lay-tx-size').value = layoutState.tx.s.toFixed(2);
        }

        function setupLayoutEditor() {
            const widthInput = document.querySelector('input[name="width_mm"]');
            const heightInput = document.querySelector('input[name="height_mm"]');
            const ctaInput = document.querySelector('textarea[name="description"]');
            const stage = document.getElementById('lay-stage');
            if (!stage) return;

            function rebuild() {
                layoutState.wMm = parseFloat(widthInput.value) || layoutState.wMm;
                layoutState.hMm = parseFloat(heightInput.value) || layoutState.hMm;
                stage.style.width = (layoutState.wMm * PX_PER_MM) + 'px';
                stage.style.height = (layoutState.hMm * PX_PER_MM) + 'px';
                stage.innerHTML = '';

                // QR placeholder box
                const qr = document.createElement('div');
                qr.id = 'lay-qr-box';
                qr.style.cssText = 'position:absolute; background:#0f172a; cursor:move; box-sizing:border-box; border:2px solid #4f46e5; display:flex; align-items:center; justify-content:center; color:#fff; font-size:12px; font-weight:600; user-select:none;';
                qr.textContent = 'QR';
                const qrHandle = document.createElement('div');
                qrHandle.style.cssText = 'position:absolute; right:-6px; bottom:-6px; width:14px; height:14px; background:#4f46e5; border:2px solid #fff; border-radius:50%; cursor:nwse-resize;';
                qrHandle.dataset.handle = '1';
                qr.appendChild(qrHandle);
                stage.appendChild(qr);

                // Text box
                const tx = document.createElement('div');
                tx.id = 'lay-text-box';
                tx.style.cssText = 'position:absolute; background:rgba(15,118,110,.10); border:2px solid #0f766e; cursor:move; display:flex; align-items:center; justify-content:center; text-align:center; color:#0f172a; font-weight:600; box-sizing:border-box; overflow:hidden; user-select:none; padding:2px;';
                tx.textContent = (ctaInput && ctaInput.value.trim()) ? ctaInput.value : 'QR kodni skanerlang va xodim xizmatini xolis baholang';
                const txHandle = document.createElement('div');
                txHandle.style.cssText = 'position:absolute; right:-6px; bottom:-6px; width:14px; height:14px; background:#0f766e; border:2px solid #fff; border-radius:50%; cursor:nwse-resize;';
                txHandle.dataset.handle = '1';
                tx.appendChild(txHandle);
                stage.appendChild(tx);

                applyState();
                bindDrag(qr, 'qr', false);
                bindResize(qrHandle, 'qr');
                bindDrag(tx, 'tx', true);
                bindResize(txHandle, 'tx', true);
            }

            function applyState() {
                const qr = document.getElementById('lay-qr-box');
                const tx = document.getElementById('lay-text-box');
                if (qr) {
                    qr.style.left = (layoutState.qr.x * PX_PER_MM) + 'px';
                    qr.style.top  = (layoutState.qr.y * PX_PER_MM) + 'px';
                    qr.style.width  = (layoutState.qr.s * PX_PER_MM) + 'px';
                    qr.style.height = (layoutState.qr.s * PX_PER_MM) + 'px';
                }
                if (tx) {
                    tx.style.left   = (layoutState.tx.x * PX_PER_MM) + 'px';
                    tx.style.top    = (layoutState.tx.y * PX_PER_MM) + 'px';
                    tx.style.width  = (layoutState.tx.w * PX_PER_MM) + 'px';
                    tx.style.height = (layoutState.tx.h * PX_PER_MM) + 'px';
                    tx.style.fontSize = (layoutState.tx.s * PX_PER_MM) + 'px';
                }
                syncFormFields();
            }

            function bindDrag(el, key, hasWH) {
                el.addEventListener('mousedown', (e) => {
                    if (e.target.dataset.handle === '1') return; // resize handle
                    e.preventDefault();
                    const startX = e.clientX, startY = e.clientY;
                    const sx = layoutState[key].x, sy = layoutState[key].y;
                    function move(ev) {
                        const dx = (ev.clientX - startX) / PX_PER_MM;
                        const dy = (ev.clientY - startY) / PX_PER_MM;
                        const wField = hasWH ? layoutState[key].w : layoutState[key].s;
                        const hField = hasWH ? layoutState[key].h : layoutState[key].s;
                        layoutState[key].x = Math.max(0, Math.min(layoutState.wMm - wField, sx + dx));
                        layoutState[key].y = Math.max(0, Math.min(layoutState.hMm - hField, sy + dy));
                        applyState();
                    }
                    function up() {
                        document.removeEventListener('mousemove', move);
                        document.removeEventListener('mouseup', up);
                    }
                    document.addEventListener('mousemove', move);
                    document.addEventListener('mouseup', up);
                });
            }

            function bindResize(handle, key, hasWH) {
                handle.addEventListener('mousedown', (e) => {
                    e.preventDefault(); e.stopPropagation();
                    const startX = e.clientX, startY = e.clientY;
                    const startW = hasWH ? layoutState[key].w : layoutState[key].s;
                    const startH = hasWH ? layoutState[key].h : layoutState[key].s;
                    function move(ev) {
                        const dW = (ev.clientX - startX) / PX_PER_MM;
                        const dH = (ev.clientY - startY) / PX_PER_MM;
                        if (hasWH) {
                            layoutState[key].w = Math.max(5,  Math.min(layoutState.wMm - layoutState[key].x, startW + dW));
                            layoutState[key].h = Math.max(3,  Math.min(layoutState.hMm - layoutState[key].y, startH + dH));
                        } else {
                            // QR — kvadrat, max o'lchamga ko'ra
                            const maxS = Math.min(layoutState.wMm - layoutState[key].x, layoutState.hMm - layoutState[key].y);
                            const ns = Math.max(8, Math.min(maxS, startW + Math.max(dW, dH)));
                            layoutState[key].s = ns;
                            const qrSlider = document.getElementById('lay-qr-size-input');
                            const qrLabel  = document.getElementById('lay-qr-size-label');
                            if (qrSlider) qrSlider.value = ns;
                            if (qrLabel) qrLabel.textContent = ns.toFixed(1);
                        }
                        applyState();
                    }
                    function up() {
                        document.removeEventListener('mousemove', move);
                        document.removeEventListener('mouseup', up);
                    }
                    document.addEventListener('mousemove', move);
                    document.addEventListener('mouseup', up);
                });
            }

            // Sliderlar
            const qrSlider = document.getElementById('lay-qr-size-input');
            const qrLabel  = document.getElementById('lay-qr-size-label');
            if (qrSlider) qrSlider.addEventListener('input', () => {
                layoutState.qr.s = parseFloat(qrSlider.value);
                qrLabel.textContent = layoutState.qr.s.toFixed(1);
                applyState();
            });
            const txSlider = document.getElementById('lay-text-size-input');
            const txLabel  = document.getElementById('lay-text-size-label');
            if (txSlider) txSlider.addEventListener('input', () => {
                layoutState.tx.s = parseFloat(txSlider.value);
                txLabel.textContent = layoutState.tx.s.toFixed(1);
                applyState();
            });

            // O'lcham yoki matn o'zgarsa, qayta chiqaramiz
            if (widthInput)  widthInput.addEventListener('input', rebuild);
            if (heightInput) heightInput.addEventListener('input', rebuild);
            if (ctaInput)    ctaInput.addEventListener('input', rebuild);

            rebuild();
        }

        function resetLayout() {
            const w = layoutState.wMm, h = layoutState.hMm;
            const qrS = Math.max(15, Math.min(h - 4, w * 0.45));
            layoutState.qr = { x: w - qrS - 2, y: Math.max(2, (h - qrS) / 2), s: qrS };
            layoutState.tx = { x: 2, y: Math.max(2, (h - 14) / 2), w: Math.max(12, w - qrS - 5), h: Math.min(16, h - 6), s: 1.9 };
            const qrSlider = document.getElementById('lay-qr-size-input');
            const txSlider = document.getElementById('lay-text-size-input');
            if (qrSlider) { qrSlider.value = qrS; document.getElementById('lay-qr-size-label').textContent = qrS.toFixed(1); }
            if (txSlider) { txSlider.value = 1.9; document.getElementById('lay-text-size-label').textContent = '1.9'; }
            // applyState chaqirilishi uchun setupLayoutEditor ichida bo'lishi kerak;
            // shuning uchun document'dagi DOMni qayta yangilash uchun stage rebuild kerak.
            setupLayoutEditor();
        }
        function downloadCard(elementId, filename) {
            const el = document.getElementById(elementId);
            html2canvas(el, { scale: 3, backgroundColor: '#ffffff', useCORS: true }).then(canvas => {
                const link = document.createElement('a');
                link.download = 'qr-card-' + filename + '.png';
                link.href = canvas.toDataURL('image/png');
                link.click();
            });
        }
        </script>
        @endpush
        @endif

                @if($activeTab !== 'shablon')
                <div style="padding:12px 18px;border-top:1px solid #e2e8f0;background:#f8fafc;">
                    {{ $teachers->links() }}
                </div>
                @endif
            </div>
        </div>
    </div>

    @if($activeTab === 'list')
    @push('scripts')
    <script>
    function toggleSelectAll(master) {
        document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = master.checked);
        updateSelected();
    }
    function updateSelected() {
        const count = document.querySelectorAll('.row-checkbox:checked').length;
        const btn = document.getElementById('btn-generate-selected');
        const lbl = document.getElementById('btn-generate-selected-label');
        if (count > 0) {
            btn.disabled = false;
            lbl.textContent = 'Tanlanganlar uchun QR yaratish (' + count + ')';
        } else {
            btn.disabled = true;
            lbl.textContent = 'Tanlanganlar uchun QR yaratish';
        }
    }
    function submitGenerateSelected() {
        const count = document.querySelectorAll('.row-checkbox:checked').length;
        if (count === 0) return;
        if (!confirm(count + ' ta xodim uchun QR kod yaratilsinmi?')) return;
        document.getElementById('form-generate-selected').submit();
    }
    function singleQr(teacherId) {
        const f = document.getElementById('form-single-qr');
        f.action = '{{ url('/admin/staff-evaluation') }}/' + teacherId + '/generate-qr';
        f.submit();
    }
    </script>
    @endpush
    @endif

    @push('styles')
    <style>
        .filter-container { padding:14px 18px;background:linear-gradient(135deg,#f0f4f8,#e8edf5);border-bottom:2px solid #dbe4ef; }
        .filter-item { display:flex;flex-direction:column; }
        .filter-label { display:flex;align-items:center;gap:5px;margin-bottom:4px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.04em;color:#475569; }
        .fl-dot { width:7px;height:7px;border-radius:50%;display:inline-block; }
        .filter-input { height:36px;padding:0 10px;border:1px solid #cbd5e1;border-radius:8px;background:#fff;font-size:13px;color:#1e293b; }
        .filter-input:focus { outline:none;border-color:#2b5ea7;box-shadow:0 0 0 2px rgba(43,94,167,0.15); }

        .btn-apply { height:36px;padding:0 14px;background:linear-gradient(135deg,#1a3268,#2b5ea7);color:#fff;font-size:12px;font-weight:700;border:none;border-radius:8px;cursor:pointer;display:inline-flex;align-items:center;gap:6px;box-shadow:0 2px 4px rgba(43,94,167,0.25);transition:all .15s; }
        .btn-apply:hover { background:linear-gradient(135deg,#152850,#1e4686);transform:translateY(-1px); }
        .btn-secondary { height:36px;padding:0 14px;background:#e2e8f0;color:#475569;font-size:12px;font-weight:600;border:none;border-radius:8px;cursor:pointer;display:inline-flex;align-items:center;text-decoration:none; }
        .btn-secondary:hover { background:#cbd5e1; }
        .btn-success { height:36px;padding:0 14px;background:linear-gradient(135deg,#15803d,#16a34a);color:#fff;font-size:12px;font-weight:700;border:none;border-radius:8px;cursor:pointer;display:inline-flex;align-items:center;gap:6px;box-shadow:0 2px 4px rgba(22,163,74,0.25);transition:all .15s; }
        .btn-success:hover:not(:disabled) { background:linear-gradient(135deg,#166534,#15803d);transform:translateY(-1px); }
        .btn-success:disabled { opacity:0.5;cursor:not-allowed; }
        .btn-danger { height:36px;padding:0 14px;background:linear-gradient(135deg,#dc2626,#ef4444);color:#fff;font-size:12px;font-weight:700;border:none;border-radius:8px;cursor:pointer;display:inline-flex;align-items:center;gap:6px;box-shadow:0 2px 4px rgba(220,38,38,0.25); }
        .btn-danger:hover { background:linear-gradient(135deg,#b91c1c,#dc2626);transform:translateY(-1px); }

        .tab-bar { display:flex;gap:4px;padding:10px 18px 0;background:#fff;border-bottom:1px solid #e2e8f0; }
        .tab-link { padding:9px 16px;font-size:13px;font-weight:600;color:#64748b;text-decoration:none;border-bottom:2px solid transparent;transition:all .15s; }
        .tab-link:hover { color:#2b5ea7; }
        .tab-active { color:#2b5ea7;border-bottom-color:#2b5ea7; }

        .staff-table { width:100%;border-collapse:separate;border-spacing:0;font-size:13px; }
        .staff-table thead tr { background:linear-gradient(135deg,#e8edf5,#dbe4ef); }
        .staff-table th { padding:12px 10px;text-align:left;font-weight:700;font-size:11px;color:#334155;text-transform:uppercase;letter-spacing:0.05em;white-space:nowrap;border-bottom:2px solid #cbd5e1; }
        .staff-table tbody tr { transition:all .15s;border-bottom:1px solid #f1f5f9; }
        .staff-table tbody tr:nth-child(even) { background:#f8fafc; }
        .staff-table tbody tr:hover { background:#eff6ff;box-shadow:inset 4px 0 0 #2b5ea7; }
        .staff-table td { padding:10px 10px;vertical-align:middle; }
        .td-num { font-weight:700;color:#2b5ea7;text-align:center; }

        .staff-name-link { font-weight:600;color:#1e3a8a;text-decoration:none; }
        .staff-name-link:hover { color:#2b5ea7;text-decoration:underline; }

        .badge-filled { display:inline-flex;align-items:center;padding:3px 10px;border-radius:6px;font-size:11px;font-weight:700;background:#dcfce7;color:#166534;border:1px solid #86efac; }

        .action-btn { display:inline-flex;align-items:center;padding:5px 10px;border-radius:6px;font-size:11px;font-weight:600;text-decoration:none;border:none;cursor:pointer;transition:all .15s; }
        .action-btn-indigo { background:#eef2ff;color:#4338ca;border:1px solid #c7d2fe; }
        .action-btn-indigo:hover { background:#e0e7ff;border-color:#a5b4fc; }
        .action-btn-green { background:#dcfce7;color:#166534;border:1px solid #86efac; }
        .action-btn-green:hover { background:#bbf7d0; }
        .action-btn-gray { background:#f1f5f9;color:#475569;border:1px solid #cbd5e1; }
        .action-btn-gray:hover { background:#e2e8f0; }

        .cb-styled { width:16px;height:16px;cursor:pointer;accent-color:#2b5ea7; }
    </style>
    @endpush
</x-app-layout>
