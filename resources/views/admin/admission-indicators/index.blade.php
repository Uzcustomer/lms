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

        <div style="background:#fff;border:1px solid #d7dde7;border-radius:3px;box-shadow:0 2px 10px rgba(15,23,42,0.06);padding:12px 14px 14px;margin-bottom:14px;">
            <form method="GET" action="{{ route('admin.admission-indicators.index') }}">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px 10px;align-items:end;">
                    <div>
                        <label style="display:block;font-size:10px;font-weight:700;text-transform:uppercase;color:#1e293b;margin:0 0 4px;">Qabul yili</label>
                        <select name="qabul_yili" style="width:100%;height:36px;border:1px solid #94a3b8;border-radius:12px;background:#fff;padding:0 14px;font-size:13px;color:#0f172a;">
                            <option value="">Barchasi</option>
                            @foreach($years as $year)
                                <option value="{{ $year }}" @selected((string) request('qabul_yili') === (string) $year)>{{ $year }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label style="display:block;font-size:10px;font-weight:700;text-transform:uppercase;color:#1e293b;margin:0 0 4px;">Ta'lim turi</label>
                        <select name="talim_turi" style="width:100%;height:36px;border:1px solid #94a3b8;border-radius:12px;background:#fff;padding:0 14px;font-size:13px;color:#0f172a;">
                            <option value="">Barchasi</option>
                            @foreach($talimTurlari as $t)
                                <option value="{{ $t }}" @selected(request('talim_turi') === $t)>{{ $t }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label style="display:block;font-size:10px;font-weight:700;text-transform:uppercase;color:#1e293b;margin:0 0 4px;">Ta'lim shakli</label>
                        <select name="talim_shakli" style="width:100%;height:36px;border:1px solid #94a3b8;border-radius:12px;background:#fff;padding:0 14px;font-size:13px;color:#0f172a;">
                            <option value="">Barchasi</option>
                            @foreach($talimShakllari as $t)
                                <option value="{{ $t }}" @selected(request('talim_shakli') === $t)>{{ $t }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label style="display:block;font-size:10px;font-weight:700;text-transform:uppercase;color:#1e293b;margin:0 0 4px;">To'lov shakli</label>
                        <select name="tolov_shakli" style="width:100%;height:36px;border:1px solid #94a3b8;border-radius:12px;background:#fff;padding:0 14px;font-size:13px;color:#0f172a;">
                            <option value="">Barchasi</option>
                            @foreach($tolovShakllari as $t)
                                <option value="{{ $t }}" @selected(request('tolov_shakli') === $t)>{{ $t }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label style="display:block;font-size:10px;font-weight:700;text-transform:uppercase;color:#1e293b;margin:0 0 4px;">Fakultet</label>
                        <select name="fakultet" style="width:100%;height:36px;border:1px solid #94a3b8;border-radius:12px;background:#fff;padding:0 14px;font-size:13px;color:#0f172a;">
                            <option value="">Barchasi</option>
                            @foreach($fakultetlar as $fakultet)
                                <option value="{{ $fakultet }}" @selected(request('fakultet') === $fakultet)>{{ $fakultet }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label style="display:block;font-size:10px;font-weight:700;text-transform:uppercase;color:#1e293b;margin:0 0 4px;">Talaba toifasi</label>
                        <select name="talaba_toifasi" style="width:100%;height:36px;border:1px solid #94a3b8;border-radius:12px;background:#fff;padding:0 14px;font-size:13px;color:#0f172a;">
                            <option value="">Barchasi</option>
                            @foreach($talabaToifalari as $toifa)
                                <option value="{{ $toifa }}" @selected(request('talaba_toifasi') === $toifa)>{{ $toifa }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div style="display:flex;gap:8px;margin-top:12px;">
                    <button type="submit" style="height:34px;padding:0 22px;border-radius:10px;border:1px solid #1d4ed8;background:#2563eb;color:#fff;font-size:13px;font-weight:600;box-shadow:0 6px 14px rgba(37,99,235,0.18);">Filtrlash</button>
                    <a href="{{ route('admin.admission-indicators.index') }}" style="display:inline-flex;align-items:center;justify-content:center;height:34px;padding:0 22px;border-radius:10px;border:1px solid #fb923c;background:#fff7ed;color:#c2410c;font-size:13px;font-weight:500;">Tozalash</a>
                </div>
            </form>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px 8px;margin-top:12px;margin-bottom:14px;">
            <div style="min-height:92px;border-radius:14px;border:1px solid #b8d9e6;background:#a8d3e6;box-shadow:0 2px 8px rgba(15,23,42,0.05);padding:14px 18px;display:flex;align-items:center;justify-content:space-between;">
                <div>
                    <div style="font-size:10px;font-weight:700;text-transform:uppercase;color:#18334a;">Qatorlar</div>
                    <div style="margin-top:6px;font-size:33px;line-height:1;font-weight:700;color:#16324f;">{{ number_format($summary['qatorlar']) }}</div>
                </div>
                <div style="width:50px;height:50px;display:flex;align-items:center;justify-content:center;color:#203040;">
                    <svg style="width:50px;height:50px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 6h16M4 12h16M4 18h10"/></svg>
                </div>
            </div>

            <div style="min-height:92px;border-radius:14px;border:1px solid #f3e7a4;background:#fbf8c8;box-shadow:0 2px 8px rgba(15,23,42,0.05);padding:14px 18px;display:flex;align-items:center;justify-content:space-between;">
                <div>
                    <div style="font-size:10px;font-weight:700;text-transform:uppercase;color:#1f8b55;">Jami qabul</div>
                    <div style="margin-top:6px;font-size:33px;line-height:1;font-weight:700;color:#0f9f67;">{{ number_format((int) $summary['jami_qabul']) }}</div>
                </div>
                <div style="width:50px;height:50px;display:flex;align-items:center;justify-content:center;border-radius:9999px;background:#d5f5de;color:#10b981;">
                    <svg style="width:24px;height:24px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                </div>
            </div>

            <div style="min-height:92px;border-radius:14px;border:1px solid #b3d3aa;background:#9bc595;box-shadow:0 2px 8px rgba(15,23,42,0.05);padding:14px 18px;display:flex;align-items:center;justify-content:space-between;">
                <div style="min-width:0;">
                    <div style="font-size:10px;font-weight:700;text-transform:uppercase;color:#203725;">Eng yuqori ball</div>
                    <div style="margin-top:6px;font-size:33px;line-height:1;font-weight:700;color:#203725;">
                        {{ $summary['top_scorer']?->toplagan_bali !== null ? rtrim(rtrim(number_format((float) $summary['top_scorer']->toplagan_bali, 2, '.', ''), '0'), '.') : '—' }}
                    </div>
                    <div style="margin-top:8px;font-size:11px;font-weight:500;color:#34495e;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="{{ $summary['top_scorer']?->full_name }}">
                        {{ $summary['top_scorer']?->full_name ?: 'Ma\'lumot yo\'q' }}
                    </div>
                </div>
                <div style="width:50px;height:50px;display:flex;align-items:center;justify-content:center;color:#203725;">
                    <svg style="width:50px;height:50px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 3l2.4 4.86L20 8.64l-4 3.9.94 5.46L12 15.77 7.06 18l.94-5.46-4-3.9 5.6-.78L12 3z"/></svg>
                </div>
            </div>

            <div style="min-height:92px;border-radius:14px;border:1px solid #e4c1cb;background:#deb9c5;box-shadow:0 2px 8px rgba(15,23,42,0.05);padding:14px 18px;display:flex;align-items:center;justify-content:space-between;">
                <div style="min-width:0;">
                    <div style="font-size:10px;font-weight:700;text-transform:uppercase;color:#d05c1f;">Eng past grant ball</div>
                    <div style="margin-top:6px;font-size:33px;line-height:1;font-weight:700;color:#20324d;">
                        {{ $summary['lowest_grant_scorer']?->toplagan_bali !== null ? rtrim(rtrim(number_format((float) $summary['lowest_grant_scorer']->toplagan_bali, 2, '.', ''), '0'), '.') : '—' }}
                    </div>
                    <div style="margin-top:8px;font-size:11px;font-weight:500;color:#34495e;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="{{ $summary['lowest_grant_scorer']?->full_name }}">
                        {{ $summary['lowest_grant_scorer']?->full_name ?: 'Ma\'lumot yo\'q' }}
                    </div>
                </div>
                <div style="width:50px;height:50px;display:flex;align-items:center;justify-content:center;border-radius:9999px;background:#fff4cf;color:#f59e0b;">
                    <svg style="width:24px;height:24px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
            </div>
        </div>

        <div style="background:#fff;border:1px solid #d6dde8;border-radius:3px;box-shadow:0 2px 10px rgba(15,23,42,0.06);overflow:hidden;">
            <div style="overflow-x:auto;">
                <form id="admissionTableFiltersForm" method="GET" action="{{ route('admin.admission-indicators.index') }}"></form>
                <table style="min-width:100%;border-collapse:separate;border-spacing:0;font-size:13px;">
                    <thead>
                        <tr>
                            <th style="background:#2f8f3f;color:#fff;font-size:11px;font-weight:700;padding:14px 12px;text-align:left;">T/r</th>
                            <th style="background:#2f8f3f;color:#fff;font-size:11px;font-weight:700;padding:14px 12px;text-align:left;">Talaba ID</th>
                            <th style="background:#2f8f3f;color:#fff;font-size:11px;font-weight:700;padding:14px 12px;text-align:left;">To'liq ismi</th>
                            <th style="background:#2f8f3f;color:#fff;font-size:11px;font-weight:700;padding:14px 12px;text-align:left;">Yil</th>
                            <th style="background:#2f8f3f;color:#fff;font-size:11px;font-weight:700;padding:14px 12px;text-align:left;">Ta'lim turi</th>
                            <th style="background:#2f8f3f;color:#fff;font-size:11px;font-weight:700;padding:14px 12px;text-align:left;">Imtiyoz toifasi</th>
                            <th style="background:#2f8f3f;color:#fff;font-size:11px;font-weight:700;padding:14px 12px;text-align:left;">To'lov shakli</th>
                            <th style="background:#2f8f3f;color:#fff;font-size:11px;font-weight:700;padding:14px 12px;text-align:right;">To'plagan bali</th>
                        </tr>
                        <tr>
                            <th style="background:#dce9f9;padding:12px 10px;"></th>
                            <th style="background:#dce9f9;padding:12px 10px;">
                                <input type="text" name="student_id" value="{{ request('student_id') }}" placeholder="Talaba ID" form="admissionTableFiltersForm"
                                       style="width:100%;height:34px;border:1px solid #94a3b8;border-radius:4px;background:#fff;padding:0 10px;font-size:12px;">
                            </th>
                            <th style="background:#dce9f9;padding:12px 10px;">
                                <input type="text" name="full_name" value="{{ request('full_name') }}" placeholder="F.I.SH" form="admissionTableFiltersForm"
                                       style="width:100%;min-width:120px;height:34px;border:1px solid #94a3b8;border-radius:4px;background:#fff;padding:0 10px;font-size:12px;">
                            </th>
                            <th style="background:#dce9f9;padding:12px 10px;">
                                <select name="qabul_yili" form="admissionTableFiltersForm" style="width:100%;min-width:64px;height:34px;border:1px solid #94a3b8;border-radius:4px;background:#fff;padding:0 10px;font-size:12px;">
                                    <option value="">Barchasi</option>
                                    @foreach($years as $year)
                                        <option value="{{ $year }}" @selected((string) request('qabul_yili') === (string) $year)>{{ $year }}</option>
                                    @endforeach
                                </select>
                            </th>
                            <th style="background:#dce9f9;padding:12px 10px;">
                                <select name="talim_turi" form="admissionTableFiltersForm" style="width:100%;min-width:78px;height:34px;border:1px solid #94a3b8;border-radius:4px;background:#fff;padding:0 10px;font-size:12px;">
                                    <option value="">Barchasi</option>
                                    @foreach($talimTurlari as $t)
                                        <option value="{{ $t }}" @selected(request('talim_turi') === $t)>{{ $t }}</option>
                                    @endforeach
                                </select>
                            </th>
                            <th style="background:#dce9f9;padding:12px 10px;">
                                <select name="imtiyoz_toifasi" form="admissionTableFiltersForm" style="width:100%;min-width:184px;height:34px;border:1px solid #94a3b8;border-radius:4px;background:#fff;padding:0 10px;font-size:12px;">
                                    <option value="">Barchasi</option>
                                    @foreach($imtiyozToifalari as $toifa)
                                        <option value="{{ $toifa }}" @selected(request('imtiyoz_toifasi') === $toifa)>{{ $toifa }}</option>
                                    @endforeach
                                </select>
                            </th>
                            <th style="background:#dce9f9;padding:12px 10px;">
                                <select name="tolov_shakli" form="admissionTableFiltersForm" style="width:100%;min-width:84px;height:34px;border:1px solid #94a3b8;border-radius:4px;background:#fff;padding:0 10px;font-size:12px;">
                                    <option value="">Barchasi</option>
                                    @foreach($tolovShakllari as $t)
                                        <option value="{{ $t }}" @selected(request('tolov_shakli') === $t)>{{ $t }}</option>
                                    @endforeach
                                </select>
                            </th>
                            <th style="background:#dce9f9;padding:12px 10px;">
                                <div style="display:flex;align-items:center;justify-content:flex-end;gap:8px;">
                                    <button type="submit" form="admissionTableFiltersForm" style="height:28px;padding:0 10px;border-radius:6px;border:1px solid #2563eb;background:#2563eb;color:#fff;font-size:11px;font-weight:600;">OK</button>
                                    <a href="{{ route('admin.admission-indicators.index') }}" style="display:inline-flex;align-items:center;justify-content:center;height:28px;padding:0 10px;border-radius:6px;border:1px solid #fb923c;background:#fff7ed;color:#c2410c;font-size:11px;font-weight:500;">X</a>
                                </div>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($indicators as $item)
                            <tr style="background:{{ $loop->odd ? '#dce9f9' : '#ffffff' }};">
                                <td style="padding:12px 12px;border-bottom:1px solid #d9e2ec;color:#0f172a;vertical-align:top;">{{ $indicators->firstItem() ? $indicators->firstItem() + $loop->index : $loop->iteration }}</td>
                                <td style="padding:12px 12px;border-bottom:1px solid #d9e2ec;color:#0f172a;vertical-align:top;white-space:nowrap;">{{ $item->student_id ?? '—' }}</td>
                                <td style="padding:12px 12px;border-bottom:1px solid #d9e2ec;color:#0f172a;vertical-align:top;min-width:180px;line-height:1.55;font-weight:500;">{{ $item->full_name ?: '—' }}</td>
                                <td style="padding:12px 12px;border-bottom:1px solid #d9e2ec;color:#0f172a;vertical-align:top;white-space:nowrap;">{{ $item->qabul_yili }}</td>
                                <td style="padding:12px 12px;border-bottom:1px solid #d9e2ec;color:#0f172a;vertical-align:top;white-space:nowrap;">{{ $item->talim_turi ?: '—' }}</td>
                                <td style="padding:12px 12px;border-bottom:1px solid #d9e2ec;color:#334155;vertical-align:top;min-width:180px;line-height:1.55;">{{ $item->imtiyoz_toifasi ?: '—' }}</td>
                                <td style="padding:12px 12px;border-bottom:1px solid #d9e2ec;color:#0f172a;vertical-align:top;line-height:1.55;">{{ $item->tolov_shakli ?: '—' }}</td>
                                <td style="padding:12px 12px;border-bottom:1px solid #d9e2ec;color:#0f172a;vertical-align:top;white-space:nowrap;text-align:right;font-weight:600;">{{ $item->toplagan_bali !== null ? rtrim(rtrim(number_format((float) $item->toplagan_bali, 2, '.', ''), '0'), '.') : '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" style="padding:30px 12px;text-align:center;color:#94a3b8;">Ma'lumot topilmadi. "Yangi qo'shish" yoki "Excel yuklash" orqali qo'shing.</td>
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
