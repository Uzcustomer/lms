<x-app-layout>
    <x-slot name="header">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap;">
            <div>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Qabul ko'rsatkichlari</h2>
                <p class="text-sm text-gray-500 mt-1">Oldingi yillardagi qabul statistikasi - hisobotlar uchun</p>
            </div>
            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                <a href="{{ route('admin.admission-indicators.create') }}"
                   class="inline-flex items-center gap-1 px-3 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-lg">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Yangi qo'shish
                </a>
                <button type="button" onclick="openAdmissionImportModal()"
                        class="inline-flex items-center gap-1 px-3 py-2 text-white text-sm font-medium rounded-lg"
                        style="background-color:#2563eb;border:1px solid #2563eb;box-shadow:0 1px 2px rgba(37,99,235,0.25);">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                    Excel yuklash
                </button>
                <a href="{{ route('admin.admission-indicators.template') }}"
                   class="inline-flex items-center gap-1 px-3 py-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-sm font-medium rounded-lg">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Namuna shablon
                </a>
                <a href="{{ route('admin.admission-indicators.export', request()->query()) }}"
                   class="inline-flex items-center gap-1 px-3 py-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-sm font-medium rounded-lg">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Eksport
                </a>
            </div>
        </div>
    </x-slot>

    <div class="w-full">
        @if(session('success'))
            <div class="bg-green-100 border border-green-400 text-green-800 px-4 py-3 rounded mb-4">{{ session('success') }}</div>
        @endif
        @if(session('warning'))
            <div class="bg-amber-100 border border-amber-400 text-amber-800 px-4 py-3 rounded mb-4">{{ session('warning') }}</div>
        @endif
        @if(session('error'))
            <div class="bg-red-100 border border-red-400 text-red-800 px-4 py-3 rounded mb-4">{{ session('error') }}</div>
        @endif

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-4">
            <div class="rounded-xl border border-slate-200 bg-white p-4">
                <div class="text-xs uppercase font-semibold text-slate-500">Qatorlar (filtr bo'yicha)</div>
                <div class="mt-1 text-2xl font-bold text-slate-800">{{ number_format($summary['qatorlar']) }}</div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4">
                <div class="text-xs uppercase font-semibold text-slate-500">Jami reja</div>
                <div class="mt-1 text-2xl font-bold text-slate-800">{{ number_format((int) $summary['jami_reja']) }}</div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4">
                <div class="text-xs uppercase font-semibold text-slate-500">Jami qabul</div>
                <div class="mt-1 text-2xl font-bold text-emerald-700">{{ number_format((int) $summary['jami_qabul']) }}</div>
            </div>
        </div>

        <div class="bg-white rounded-3xl border border-slate-200 shadow-sm mb-4 overflow-hidden">
            <form method="GET" action="{{ route('admin.admission-indicators.index') }}" class="p-5">
                <div class="mb-4 flex items-center justify-between gap-3 flex-wrap">
                    <div>
                        <h3 class="text-base font-semibold text-slate-800">Filtrlar</h3>
                        <p class="text-sm text-slate-500">Qabul yozuvlarini kerakli parametrlar bo'yicha saralang.</p>
                    </div>
                    <div class="inline-flex items-center gap-2 rounded-full bg-slate-100 px-3 py-1.5 text-xs font-medium text-slate-600">
                        <span>Natija:</span>
                        <span class="text-slate-900">{{ number_format($summary['qatorlar']) }} ta</span>
                    </div>
                </div>

                <div style="display:flex;flex-wrap:wrap;gap:14px;align-items:flex-end;">
                    <div style="flex:1 1 100%;min-width:280px;">
                        <label class="block text-xs font-semibold uppercase text-slate-500 mb-1">Qidiruv</label>
                        <input type="text" name="search" value="{{ request('search') }}" placeholder="Talaba ID, JSHSHIR, F.I.SH, mutaxassislik..."
                               class="w-full rounded-2xl border-slate-200 bg-slate-50 px-4 py-3 focus:border-sky-500 focus:bg-white focus:ring-sky-500">
                    </div>

                    <div style="flex:1 1 calc(50% - 7px);min-width:220px;">
                        <label class="block text-xs font-semibold uppercase text-slate-500 mb-1">Qabul yili</label>
                        <select name="qabul_yili" class="w-full rounded-2xl border-slate-200 bg-slate-50 px-4 py-3 focus:border-sky-500 focus:bg-white focus:ring-sky-500">
                            <option value="">Barchasi</option>
                            @foreach($years as $year)
                                <option value="{{ $year }}" @selected((string) request('qabul_yili') === (string) $year)>{{ $year }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div style="flex:1 1 calc(50% - 7px);min-width:220px;">
                        <label class="block text-xs font-semibold uppercase text-slate-500 mb-1">Ta'lim turi</label>
                        <select name="talim_turi" class="w-full rounded-2xl border-slate-200 bg-slate-50 px-4 py-3 focus:border-sky-500 focus:bg-white focus:ring-sky-500">
                            <option value="">Barchasi</option>
                            @foreach($talimTurlari as $t)
                                <option value="{{ $t }}" @selected(request('talim_turi') === $t)>{{ $t }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div style="flex:1 1 calc(50% - 7px);min-width:220px;">
                        <label class="block text-xs font-semibold uppercase text-slate-500 mb-1">Ta'lim shakli</label>
                        <select name="talim_shakli" class="w-full rounded-2xl border-slate-200 bg-slate-50 px-4 py-3 focus:border-sky-500 focus:bg-white focus:ring-sky-500">
                            <option value="">Barchasi</option>
                            @foreach($talimShakllari as $t)
                                <option value="{{ $t }}" @selected(request('talim_shakli') === $t)>{{ $t }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div style="flex:1 1 calc(50% - 7px);min-width:220px;">
                        <label class="block text-xs font-semibold uppercase text-slate-500 mb-1">To'lov shakli</label>
                        <select name="tolov_shakli" class="w-full rounded-2xl border-slate-200 bg-slate-50 px-4 py-3 focus:border-sky-500 focus:bg-white focus:ring-sky-500">
                            <option value="">Barchasi</option>
                            @foreach($tolovShakllari as $t)
                                <option value="{{ $t }}" @selected(request('tolov_shakli') === $t)>{{ $t }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div style="flex:1 1 calc(50% - 7px);min-width:220px;">
                        <label class="block text-xs font-semibold uppercase text-slate-500 mb-1">Fakultet</label>
                        <select name="fakultet" class="w-full rounded-2xl border-slate-200 bg-slate-50 px-4 py-3 focus:border-sky-500 focus:bg-white focus:ring-sky-500">
                            <option value="">Barchasi</option>
                            @foreach($fakultetlar as $fakultet)
                                <option value="{{ $fakultet }}" @selected(request('fakultet') === $fakultet)>{{ $fakultet }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div style="flex:1 1 calc(50% - 7px);min-width:220px;">
                        <label class="block text-xs font-semibold uppercase text-slate-500 mb-1">Talaba toifasi</label>
                        <select name="talaba_toifasi" class="w-full rounded-2xl border-slate-200 bg-slate-50 px-4 py-3 focus:border-sky-500 focus:bg-white focus:ring-sky-500">
                            <option value="">Barchasi</option>
                            @foreach($talabaToifalari as $toifa)
                                <option value="{{ $toifa }}" @selected(request('talaba_toifasi') === $toifa)>{{ $toifa }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div style="display:flex;align-items:center;gap:10px;flex:1 1 100%;padding-top:4px;">
                        <button type="submit"
                                class="px-5 py-2.5 text-white text-sm font-medium rounded-xl"
                                style="background-color:#2563eb;border:1px solid #2563eb;box-shadow:0 8px 20px rgba(37,99,235,0.18);">Filtrlash</button>
                        <a href="{{ route('admin.admission-indicators.index') }}"
                           class="px-5 py-2.5 text-sm font-medium rounded-xl"
                           style="background-color:#f8fafc;border:1px solid #cbd5e1;color:#334155;">Tozalash</a>
                    </div>
                </div>
            </form>
        </div>

        <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <form id="admissionTableFiltersForm" method="GET" action="{{ route('admin.admission-indicators.index') }}"></form>
                <table class="min-w-full text-sm">
                    <thead class="bg-gradient-to-r from-slate-50 to-slate-100 text-slate-700">
                        <tr>
                            <th class="px-5 py-4 text-left font-semibold whitespace-nowrap">T/r</th>
                            <th class="px-5 py-4 text-left font-semibold whitespace-nowrap">Talaba ID</th>
                            <th class="px-5 py-4 text-left font-semibold whitespace-nowrap">To'liq ismi</th>
                            <th class="px-5 py-4 text-left font-semibold whitespace-nowrap">Yil</th>
                            <th class="px-5 py-4 text-left font-semibold whitespace-nowrap">Ta'lim turi</th>
                            <th class="px-5 py-4 text-left font-semibold whitespace-nowrap">Imtiyoz toifasi</th>
                            <th class="px-5 py-4 text-left font-semibold whitespace-nowrap">To'lov shakli</th>
                            <th class="px-5 py-4 text-right font-semibold whitespace-nowrap">To'plagan bali</th>
                        </tr>
                        <tr class="bg-white border-t border-slate-200">
                            <th class="px-4 py-3"></th>
                            <th class="px-4 py-3">
                                <input type="text" name="student_id" value="{{ request('student_id') }}" placeholder="Talaba ID"
                                       form="admissionTableFiltersForm"
                                       class="w-full rounded-xl border-slate-200 bg-slate-50 px-3 py-2 text-xs focus:border-sky-500 focus:bg-white focus:ring-sky-500">
                            </th>
                            <th class="px-4 py-3">
                                <input type="text" name="full_name" value="{{ request('full_name') }}" placeholder="F.I.SH"
                                       form="admissionTableFiltersForm"
                                       class="w-full min-w-[220px] rounded-xl border-slate-200 bg-slate-50 px-3 py-2 text-xs focus:border-sky-500 focus:bg-white focus:ring-sky-500">
                            </th>
                            <th class="px-4 py-3">
                                <select name="qabul_yili" form="admissionTableFiltersForm" class="w-full min-w-[100px] rounded-xl border-slate-200 bg-slate-50 px-3 py-2 text-xs focus:border-sky-500 focus:bg-white focus:ring-sky-500">
                                    <option value="">Barchasi</option>
                                    @foreach($years as $year)
                                        <option value="{{ $year }}" @selected((string) request('qabul_yili') === (string) $year)>{{ $year }}</option>
                                    @endforeach
                                </select>
                            </th>
                            <th class="px-4 py-3">
                                <select name="talim_turi" form="admissionTableFiltersForm" class="w-full min-w-[120px] rounded-xl border-slate-200 bg-slate-50 px-3 py-2 text-xs focus:border-sky-500 focus:bg-white focus:ring-sky-500">
                                    <option value="">Barchasi</option>
                                    @foreach($talimTurlari as $t)
                                        <option value="{{ $t }}" @selected(request('talim_turi') === $t)>{{ $t }}</option>
                                    @endforeach
                                </select>
                            </th>
                            <th class="px-4 py-3">
                                <select name="imtiyoz_toifasi" form="admissionTableFiltersForm" class="w-full min-w-[260px] rounded-xl border-slate-200 bg-slate-50 px-3 py-2 text-xs focus:border-sky-500 focus:bg-white focus:ring-sky-500">
                                    <option value="">Barchasi</option>
                                    @foreach($imtiyozToifalari as $toifa)
                                        <option value="{{ $toifa }}" @selected(request('imtiyoz_toifasi') === $toifa)>{{ $toifa }}</option>
                                    @endforeach
                                </select>
                            </th>
                            <th class="px-4 py-3">
                                <select name="tolov_shakli" form="admissionTableFiltersForm" class="w-full min-w-[140px] rounded-xl border-slate-200 bg-slate-50 px-3 py-2 text-xs focus:border-sky-500 focus:bg-white focus:ring-sky-500">
                                    <option value="">Barchasi</option>
                                    @foreach($tolovShakllari as $t)
                                        <option value="{{ $t }}" @selected(request('tolov_shakli') === $t)>{{ $t }}</option>
                                    @endforeach
                                </select>
                            </th>
                            <th class="px-4 py-3">
                                <div class="flex items-center justify-end gap-2">
                                    <button type="submit" form="admissionTableFiltersForm" class="px-2.5 py-1.5 rounded-lg text-xs font-medium text-white"
                                            style="background-color:#2563eb;box-shadow:0 6px 16px rgba(37,99,235,0.18);">OK</button>
                                    <a href="{{ route('admin.admission-indicators.index') }}"
                                       class="px-2.5 py-1.5 rounded-lg text-xs font-medium"
                                       style="background-color:#f1f5f9;border:1px solid #cbd5e1;color:#334155;">X</a>
                                </div>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @forelse($indicators as $item)
                            <tr class="{{ $loop->odd ? 'bg-slate-50' : 'bg-white' }} hover:bg-sky-50 transition-colors duration-150">
                                <td class="px-5 py-4 font-semibold text-slate-700 align-top">{{ $indicators->firstItem() ? $indicators->firstItem() + $loop->index : $loop->iteration }}</td>
                                <td class="px-5 py-4 font-medium text-slate-800 align-top whitespace-nowrap">{{ $item->student_id ?? '—' }}</td>
                                <td class="px-5 py-4 min-w-[240px] align-top">
                                    <div class="font-semibold text-slate-800 leading-6">{{ $item->full_name ?: '—' }}</div>
                                </td>
                                <td class="px-5 py-4 font-semibold text-slate-800 align-top whitespace-nowrap">{{ $item->qabul_yili }}</td>
                                <td class="px-5 py-4 align-top whitespace-nowrap">{{ $item->talim_turi ?: '—' }}</td>
                                <td class="px-5 py-4 align-top min-w-[360px]">
                                    <div class="leading-6 text-slate-700">{{ $item->imtiyoz_toifasi ?: '—' }}</div>
                                </td>
                                <td class="px-5 py-4 align-top">{{ $item->tolov_shakli ?: '—' }}</td>
                                <td class="px-5 py-4 text-right font-semibold text-sky-700 align-top whitespace-nowrap">{{ $item->toplagan_bali !== null ? rtrim(rtrim(number_format((float) $item->toplagan_bali, 2, '.', ''), '0'), '.') : '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-3 py-10 text-center text-slate-400">
                                    Ma'lumot topilmadi. "Yangi qo'shish" yoki "Excel yuklash" orqali qo'shing.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-4">
            {{ $indicators->links() }}
        </div>
    </div>

    <div id="importModal" class="hidden fixed inset-0 z-[100] flex items-center justify-center p-4"
         style="background:rgba(107,114,128,0.45);backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px);">
        <div class="bg-white rounded-2xl shadow-xl p-6" style="width:min(65vw,960px);max-width:65vw;min-width:320px;">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-lg font-semibold text-slate-800">Excel yuklash</h3>
                    <p class="text-sm text-slate-500 mt-1">Avval faylni yuklang, keyin ma'lumotlarni bosqichma-bosqich jadvalga ko'chiring.</p>
                </div>
                <button type="button" onclick="closeAdmissionImportModal()" class="text-slate-400 hover:text-slate-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <div id="importNotice" class="hidden rounded-lg px-4 py-3 text-sm mb-4"></div>

            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">Excel fayl</label>
                    <input id="admissionImportFile" type="file" accept=".xlsx,.xls"
                           class="w-full text-sm border border-slate-300 rounded-lg p-2">
                </div>

                <div id="importReadyBox" class="hidden rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    <div id="importReadyText"></div>
                </div>

                <div id="importProgressWrap" class="hidden">
                    <div class="flex items-center justify-between text-sm text-slate-600 mb-2">
                        <span>Ko'chirish jarayoni</span>
                        <span id="importPercentText">0%</span>
                    </div>
                    <div class="w-full h-3 bg-slate-200 rounded-full overflow-hidden">
                        <div id="importProgressBar" class="h-full bg-sky-600 transition-all duration-300" style="width: 0%;"></div>
                    </div>
                    <div id="importProgressMeta" class="text-xs text-slate-500 mt-2">0 / 0 qator</div>
                </div>
            </div>

            <div class="flex justify-end gap-2 mt-6">
                <button type="button" onclick="closeAdmissionImportModal()"
                        class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium rounded-lg">Bekor qilish</button>
                <button type="button" id="uploadExcelButton"
                        class="px-4 py-2 text-white text-sm font-medium rounded-lg"
                        style="background-color:#2563eb;border:1px solid #2563eb;box-shadow:0 1px 2px rgba(37,99,235,0.25);">Yuklash</button>
                <button type="button" id="startTransferButton"
                        class="hidden px-4 py-2 text-white text-sm font-medium rounded-lg"
                        style="background-color:#059669;border:1px solid #059669;box-shadow:0 1px 2px rgba(5,150,105,0.25);">Ma'lumotlarni ko'chirish</button>
            </div>
        </div>
    </div>

    <script>
        const importRoute = @json(route('admin.admission-indicators.import'));
        const csrfToken = @json(csrf_token());
        const importModal = document.getElementById('importModal');
        const importNotice = document.getElementById('importNotice');
        const importFileInput = document.getElementById('admissionImportFile');
        const uploadExcelButton = document.getElementById('uploadExcelButton');
        const startTransferButton = document.getElementById('startTransferButton');
        const importReadyBox = document.getElementById('importReadyBox');
        const importReadyText = document.getElementById('importReadyText');
        const importProgressWrap = document.getElementById('importProgressWrap');
        const importProgressBar = document.getElementById('importProgressBar');
        const importPercentText = document.getElementById('importPercentText');
        const importProgressMeta = document.getElementById('importProgressMeta');

        function openAdmissionImportModal() {
            resetAdmissionImportUi();
            importModal.classList.remove('hidden');
        }

        function closeAdmissionImportModal() {
            importModal.classList.add('hidden');
        }

        function resetAdmissionImportUi() {
            importNotice.className = 'hidden rounded-lg px-4 py-3 text-sm mb-4';
            importNotice.textContent = '';
            importReadyBox.classList.add('hidden');
            startTransferButton.classList.add('hidden');
            startTransferButton.disabled = false;
            uploadExcelButton.disabled = false;
            importProgressWrap.classList.add('hidden');
            importProgressBar.style.width = '0%';
            importPercentText.textContent = '0%';
            importProgressMeta.textContent = '0 / 0 qator';
        }

        function showImportNotice(type, message) {
            const base = 'rounded-lg px-4 py-3 text-sm mb-4';
            const classes = {
                success: 'bg-emerald-100 border border-emerald-300 text-emerald-800',
                error: 'bg-rose-100 border border-rose-300 text-rose-800',
                warning: 'bg-amber-100 border border-amber-300 text-amber-800',
                info: 'bg-sky-100 border border-sky-300 text-sky-800',
            };

            importNotice.className = `${base} ${classes[type] || classes.info}`;
            importNotice.textContent = message;
            importNotice.classList.remove('hidden');
        }

        function resolveImportErrorMessage(payload, rawText) {
            if (payload?.message) {
                return payload.message;
            }

            const validationErrors = payload?.errors ? Object.values(payload.errors).flat() : [];
            if (validationErrors.length) {
                return validationErrors.join(' ');
            }

            if (typeof rawText === 'string' && rawText.trim()) {
                const cleaned = rawText.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
                if (cleaned) {
                    return cleaned.slice(0, 300);
                }
            }

            return 'Amalni bajarib bo\'lmadi.';
        }

        async function postImportAction(formData) {
            const response = await fetch(importRoute, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: formData,
            });

            const rawText = await response.text();
            let payload = null;

            try {
                payload = rawText ? JSON.parse(rawText) : null;
            } catch (error) {
                throw new Error(resolveImportErrorMessage(null, rawText));
            }

            if (!response.ok || !payload || payload.success === false) {
                throw new Error(resolveImportErrorMessage(payload, rawText));
            }

            return payload;
        }

        uploadExcelButton.addEventListener('click', async () => {
            if (!importFileInput.files.length) {
                showImportNotice('error', 'Avval Excel faylni tanlang.');
                return;
            }

            uploadExcelButton.disabled = true;
            startTransferButton.classList.add('hidden');
            importReadyBox.classList.add('hidden');
            showImportNotice('info', 'Fayl yuklanmoqda...');

            try {
                const formData = new FormData();
                formData.append('action', 'upload');
                formData.append('file', importFileInput.files[0]);
                const payload = await postImportAction(formData);

                importReadyText.textContent = `${payload.file_name} yuklandi. ${payload.total_rows} ta qator ko'chirishga tayyor.`;
                importReadyBox.classList.remove('hidden');
                startTransferButton.classList.remove('hidden');
                showImportNotice('success', payload.message);
            } catch (error) {
                showImportNotice('error', error.message);
            } finally {
                uploadExcelButton.disabled = false;
            }
        });

        startTransferButton.addEventListener('click', async () => {
            startTransferButton.disabled = true;
            uploadExcelButton.disabled = true;
            importProgressWrap.classList.remove('hidden');
            showImportNotice('info', 'Ma\'lumotlar ko\'chirilmoqda...');
            await processAdmissionImportChunk();
        });

        async function processAdmissionImportChunk() {
            try {
                const formData = new FormData();
                formData.append('action', 'process');
                const payload = await postImportAction(formData);

                importProgressBar.style.width = `${payload.percent}%`;
                importPercentText.textContent = `${payload.percent}%`;
                importProgressMeta.textContent = `${payload.processed_rows} / ${payload.total_rows} qator`;

                if (payload.finished) {
                    const message = payload.errors_count > 0
                        ? `Import tugadi. ${payload.imported_rows} ta qator yozildi, ${payload.errors_count} ta qatorda xatolik bor.`
                        : `Import tugadi. ${payload.imported_rows} ta qator admission_indicators jadvaliga yozildi.`;

                    showImportNotice(payload.errors_count > 0 ? 'warning' : 'success', message);
                    setTimeout(() => window.location.reload(), 1200);
                    return;
                }

                showImportNotice('info', payload.message);
                setTimeout(processAdmissionImportChunk, 150);
            } catch (error) {
                startTransferButton.disabled = false;
                uploadExcelButton.disabled = false;
                showImportNotice('error', error.message);
            }
        }
    </script>
</x-app-layout>
