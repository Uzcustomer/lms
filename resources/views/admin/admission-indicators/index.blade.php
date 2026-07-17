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
                        style="background-color:#2563eb;border:1px solid #1d4ed8;box-shadow:0 1px 2px rgba(37,99,235,0.25);">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                    Excel yuklash
                </button>
                <a href="{{ route('admin.admission-indicators.template') }}"
                   class="inline-flex items-center gap-1 px-3 py-2 bg-white border border-slate-300 hover:bg-slate-50 text-gray-700 text-sm font-medium rounded-lg">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Namuna shablon
                </a>
                <a href="{{ route('admin.admission-indicators.export', request()->query()) }}"
                   class="inline-flex items-center gap-1 px-3 py-2 bg-white border border-slate-300 hover:bg-slate-50 text-gray-700 text-sm font-medium rounded-lg">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Eksport
                </a>
            </div>
        </div>
    </x-slot>

    <div class="w-full">
        @if(session('success'))
            <div class="bg-green-100 border border-green-400 text-green-800 px-3 py-2 rounded mb-3 text-sm">{{ session('success') }}</div>
        @endif
        @if(session('warning'))
            <div class="bg-amber-100 border border-amber-400 text-amber-800 px-3 py-2 rounded mb-3 text-sm">{{ session('warning') }}</div>
        @endif
        @if(session('error'))
            <div class="bg-red-100 border border-red-400 text-red-800 px-3 py-2 rounded mb-3 text-sm">{{ session('error') }}</div>
        @endif

        <div class="bg-white rounded-sm border border-slate-200 shadow-sm mb-4 overflow-hidden">
            <form method="GET" action="{{ route('admin.admission-indicators.index') }}" class="p-4">
                <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;">
                    <div style="flex:1 1 calc(50% - 5px);min-width:200px;">
                        <label class="block text-[11px] font-semibold uppercase text-slate-700 mb-1">Qabul yili</label>
                        <select name="qabul_yili" class="w-full rounded-2xl border-slate-400 bg-white px-3 py-2 text-sm focus:border-sky-500 focus:bg-white focus:ring-sky-500">
                            <option value="">Barchasi</option>
                            @foreach($years as $year)
                                <option value="{{ $year }}" @selected((string) request('qabul_yili') === (string) $year)>{{ $year }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div style="flex:1 1 calc(50% - 5px);min-width:200px;">
                        <label class="block text-[11px] font-semibold uppercase text-slate-700 mb-1">Ta'lim turi</label>
                        <select name="talim_turi" class="w-full rounded-2xl border-slate-400 bg-white px-3 py-2 text-sm focus:border-sky-500 focus:bg-white focus:ring-sky-500">
                            <option value="">Barchasi</option>
                            @foreach($talimTurlari as $t)
                                <option value="{{ $t }}" @selected(request('talim_turi') === $t)>{{ $t }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div style="flex:1 1 calc(50% - 5px);min-width:200px;">
                        <label class="block text-[11px] font-semibold uppercase text-slate-700 mb-1">Ta'lim shakli</label>
                        <select name="talim_shakli" class="w-full rounded-2xl border-slate-400 bg-white px-3 py-2 text-sm focus:border-sky-500 focus:bg-white focus:ring-sky-500">
                            <option value="">Barchasi</option>
                            @foreach($talimShakllari as $t)
                                <option value="{{ $t }}" @selected(request('talim_shakli') === $t)>{{ $t }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div style="flex:1 1 calc(50% - 5px);min-width:200px;">
                        <label class="block text-[11px] font-semibold uppercase text-slate-700 mb-1">To'lov shakli</label>
                        <select name="tolov_shakli" class="w-full rounded-2xl border-slate-400 bg-white px-3 py-2 text-sm focus:border-sky-500 focus:bg-white focus:ring-sky-500">
                            <option value="">Barchasi</option>
                            @foreach($tolovShakllari as $t)
                                <option value="{{ $t }}" @selected(request('tolov_shakli') === $t)>{{ $t }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div style="flex:1 1 calc(50% - 5px);min-width:200px;">
                        <label class="block text-[11px] font-semibold uppercase text-slate-700 mb-1">Fakultet</label>
                        <select name="fakultet" class="w-full rounded-2xl border-slate-400 bg-white px-3 py-2 text-sm focus:border-sky-500 focus:bg-white focus:ring-sky-500">
                            <option value="">Barchasi</option>
                            @foreach($fakultetlar as $fakultet)
                                <option value="{{ $fakultet }}" @selected(request('fakultet') === $fakultet)>{{ $fakultet }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div style="flex:1 1 calc(50% - 5px);min-width:200px;">
                        <label class="block text-[11px] font-semibold uppercase text-slate-700 mb-1">Talaba toifasi</label>
                        <select name="talaba_toifasi" class="w-full rounded-2xl border-slate-400 bg-white px-3 py-2 text-sm focus:border-sky-500 focus:bg-white focus:ring-sky-500">
                            <option value="">Barchasi</option>
                            @foreach($talabaToifalari as $toifa)
                                <option value="{{ $toifa }}" @selected(request('talaba_toifasi') === $toifa)>{{ $toifa }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div style="display:flex;align-items:center;gap:8px;flex:1 1 100%;padding-top:2px;">
                        <button type="submit"
                                class="px-4 py-2 text-white text-sm font-medium rounded-xl"
                                style="background-color:#2563eb;border:1px solid #1d4ed8;box-shadow:0 6px 14px rgba(37,99,235,0.18);">Filtrlash</button>
                        <a href="{{ route('admin.admission-indicators.index') }}"
                           class="px-4 py-2 text-sm font-medium rounded-xl"
                           style="background-color:#fff7ed;border:1px solid #fdba74;color:#9a3412;">Tozalash</a>
                    </div>
                </div>
            </form>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-2 mb-3">
            <div class="rounded-2xl border border-cyan-200 bg-[#a8d3e6] p-4 shadow-sm">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <div class="text-[11px] uppercase font-semibold tracking-wide text-slate-800">Qatorlar</div>
                        <div class="mt-1 text-[36px] leading-none font-bold text-slate-800">{{ number_format($summary['qatorlar']) }}</div>
                    </div>
                    <div class="flex h-[50px] w-[50px] items-center justify-center rounded-2xl text-slate-800">
                        <svg class="w-[50px] h-[50px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 6h16M4 12h16M4 18h10"/></svg>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-yellow-200 bg-[#fbf8c8] p-4 shadow-sm">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <div class="text-[11px] uppercase font-semibold tracking-wide text-emerald-700">Jami qabul</div>
                        <div class="mt-1 text-[36px] leading-none font-bold text-emerald-700">{{ number_format((int) $summary['jami_qabul']) }}</div>
                    </div>
                    <div class="flex h-[50px] w-[50px] items-center justify-center rounded-full bg-emerald-100 text-emerald-700">
                        <svg class="w-[24px] h-[24px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-green-200 bg-[#9bc595] p-4 shadow-sm">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <div class="text-[11px] uppercase font-semibold tracking-wide text-slate-800">Eng yuqori ball</div>
                        <div class="mt-1 text-[36px] leading-none font-bold text-slate-800">
                            {{ $summary['top_scorer']?->toplagan_bali !== null ? rtrim(rtrim(number_format((float) $summary['top_scorer']->toplagan_bali, 2, '.', ''), '0'), '.') : '—' }}
                        </div>
                        <div class="mt-1 truncate text-[12px] font-medium text-slate-700" title="{{ $summary['top_scorer']?->full_name }}">
                            {{ $summary['top_scorer']?->full_name ?: 'Ma\'lumot yo\'q' }}
                        </div>
                    </div>
                    <div class="flex h-[50px] w-[50px] items-center justify-center text-slate-800">
                        <svg class="w-[50px] h-[50px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 3l2.4 4.86L20 8.64l-4 3.9.94 5.46L12 15.77 7.06 18l.94-5.46-4-3.9 5.6-.78L12 3z"/></svg>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-pink-200 bg-[#deb9c5] p-4 shadow-sm">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <div class="text-[11px] uppercase font-semibold tracking-wide text-orange-700">Eng past grant ball</div>
                        <div class="mt-1 text-[36px] leading-none font-bold text-slate-800">
                            {{ $summary['lowest_grant_scorer']?->toplagan_bali !== null ? rtrim(rtrim(number_format((float) $summary['lowest_grant_scorer']->toplagan_bali, 2, '.', ''), '0'), '.') : '—' }}
                        </div>
                        <div class="mt-1 truncate text-[12px] font-medium text-slate-700" title="{{ $summary['lowest_grant_scorer']?->full_name }}">
                            {{ $summary['lowest_grant_scorer']?->full_name ?: 'Ma\'lumot yo\'q' }}
                        </div>
                    </div>
                    <div class="flex h-[50px] w-[50px] items-center justify-center rounded-full bg-amber-50 text-orange-500">
                        <svg class="w-[24px] h-[24px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-3xl border border-sky-100 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <form id="admissionTableFiltersForm" method="GET" action="{{ route('admin.admission-indicators.index') }}"></form>
                <table class="min-w-full text-[13px]">
                    <thead class="text-slate-700">
                        <tr>
                            <th class="px-4 py-4 text-left text-[12px] font-semibold whitespace-nowrap bg-green-700 text-white">T/r</th>
                            <th class="px-4 py-4 text-left text-[12px] font-semibold whitespace-nowrap bg-green-700 text-white">Talaba ID</th>
                            <th class="px-4 py-4 text-left text-[12px] font-semibold whitespace-nowrap bg-green-700 text-white">To'liq ismi</th>
                            <th class="px-4 py-4 text-left text-[12px] font-semibold whitespace-nowrap bg-green-700 text-white">Yil</th>
                            <th class="px-4 py-4 text-left text-[12px] font-semibold whitespace-nowrap bg-green-700 text-white">Ta'lim turi</th>
                            <th class="px-4 py-4 text-left text-[12px] font-semibold whitespace-nowrap bg-green-700 text-white">Imtiyoz toifasi</th>
                            <th class="px-4 py-4 text-left text-[12px] font-semibold whitespace-nowrap bg-green-700 text-white">To'lov shakli</th>
                            <th class="px-4 py-4 text-right text-[12px] font-semibold whitespace-nowrap bg-green-700 text-white">To'plagan bali</th>
                        </tr>
                        <tr class="border-t border-sky-100">
                            <th class="px-3 py-2 bg-blue-50"></th>
                            <th class="px-3 py-2 bg-blue-50">
                                <input type="text" name="student_id" value="{{ request('student_id') }}" placeholder="Talaba ID"
                                       form="admissionTableFiltersForm"
                                       class="w-full rounded-md border-slate-400 bg-white px-2.5 py-1.5 text-[12px] focus:border-sky-500 focus:bg-white focus:ring-sky-500">
                            </th>
                            <th class="px-3 py-2 bg-blue-50">
                                <input type="text" name="full_name" value="{{ request('full_name') }}" placeholder="F.I.SH"
                                       form="admissionTableFiltersForm"
                                       class="w-full min-w-[180px] rounded-md border-slate-400 bg-white px-2.5 py-1.5 text-[12px] focus:border-sky-500 focus:bg-white focus:ring-sky-500">
                            </th>
                            <th class="px-3 py-2 bg-blue-50">
                                <select name="qabul_yili" form="admissionTableFiltersForm" class="w-full min-w-[74px] rounded-md border-slate-400 bg-white px-2.5 py-1.5 text-[12px] focus:border-sky-500 focus:bg-white focus:ring-sky-500">
                                    <option value="">Barchasi</option>
                                    @foreach($years as $year)
                                        <option value="{{ $year }}" @selected((string) request('qabul_yili') === (string) $year)>{{ $year }}</option>
                                    @endforeach
                                </select>
                            </th>
                            <th class="px-3 py-2 bg-blue-50">
                                <select name="talim_turi" form="admissionTableFiltersForm" class="w-full min-w-[108px] rounded-md border-slate-400 bg-white px-2.5 py-1.5 text-[12px] focus:border-sky-500 focus:bg-white focus:ring-sky-500">
                                    <option value="">Barchasi</option>
                                    @foreach($talimTurlari as $t)
                                        <option value="{{ $t }}" @selected(request('talim_turi') === $t)>{{ $t }}</option>
                                    @endforeach
                                </select>
                            </th>
                            <th class="px-3 py-2 bg-blue-50">
                                <select name="imtiyoz_toifasi" form="admissionTableFiltersForm" class="w-full min-w-[220px] rounded-md border-slate-400 bg-white px-2.5 py-1.5 text-[12px] focus:border-sky-500 focus:bg-white focus:ring-sky-500">
                                    <option value="">Barchasi</option>
                                    @foreach($imtiyozToifalari as $toifa)
                                        <option value="{{ $toifa }}" @selected(request('imtiyoz_toifasi') === $toifa)>{{ $toifa }}</option>
                                    @endforeach
                                </select>
                            </th>
                            <th class="px-3 py-2 bg-blue-50">
                                <select name="tolov_shakli" form="admissionTableFiltersForm" class="w-full min-w-[122px] rounded-md border-slate-400 bg-white px-2.5 py-1.5 text-[12px] focus:border-sky-500 focus:bg-white focus:ring-sky-500">
                                    <option value="">Barchasi</option>
                                    @foreach($tolovShakllari as $t)
                                        <option value="{{ $t }}" @selected(request('tolov_shakli') === $t)>{{ $t }}</option>
                                    @endforeach
                                </select>
                            </th>
                            <th class="px-3 py-2 bg-blue-50">
                                <div class="flex items-center justify-end gap-2">
                                    <button type="submit" form="admissionTableFiltersForm" class="px-2.5 py-1.5 rounded-lg text-[12px] font-medium text-white"
                                            style="background-color:#2563eb;box-shadow:0 4px 10px rgba(37,99,235,0.16);">OK</button>
                                    <a href="{{ route('admin.admission-indicators.index') }}"
                                       class="px-2.5 py-1.5 rounded-lg text-[12px] font-medium"
                                       style="background-color:#fff7ed;border:1px solid #fdba74;color:#9a3412;">X</a>
                                </div>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-sky-100">
                        @forelse($indicators as $item)
                            <tr class="{{ $loop->odd ? 'bg-slate-50' : 'bg-white' }} hover:bg-sky-50 transition-colors duration-150">
                                <td class="px-4 py-3 font-semibold text-slate-700 align-top">{{ $indicators->firstItem() ? $indicators->firstItem() + $loop->index : $loop->iteration }}</td>
                                <td class="px-4 py-3 font-medium text-slate-800 align-top whitespace-nowrap">{{ $item->student_id ?? '—' }}</td>
                                <td class="px-4 py-3 min-w-[220px] align-top">
                                    <div class="font-semibold text-slate-800 leading-5">{{ $item->full_name ?: '—' }}</div>
                                </td>
                                <td class="px-4 py-3 font-semibold text-slate-800 align-top whitespace-nowrap">{{ $item->qabul_yili }}</td>
                                <td class="px-4 py-3 align-top whitespace-nowrap">{{ $item->talim_turi ?: '—' }}</td>
                                <td class="px-4 py-3 align-top min-w-[300px]">
                                    <div class="leading-5 text-slate-700">{{ $item->imtiyoz_toifasi ?: '—' }}</div>
                                </td>
                                <td class="px-4 py-3 align-top">{{ $item->tolov_shakli ?: '—' }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-sky-700 align-top whitespace-nowrap">{{ $item->toplagan_bali !== null ? rtrim(rtrim(number_format((float) $item->toplagan_bali, 2, '.', ''), '0'), '.') : '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-3 py-8 text-center text-sm text-slate-400">
                                    Ma'lumot topilmadi. "Yangi qo'shish" yoki "Excel yuklash" orqali qo'shing.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-3">
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
