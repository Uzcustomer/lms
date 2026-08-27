<x-app-layout>
    <style>
        .sd-page { max-width:1540px; margin:0 auto; padding:16px 12px 32px; color:#0f172a; }
        .sd-upload-card { overflow:hidden; padding:20px; border:1px solid #e2e8f0; border-radius:16px; background:#fff; box-shadow:0 1px 3px rgba(15,23,42,.06); }
        .sd-upload-title { display:flex; align-items:flex-start; gap:12px; }
        .sd-upload-badge { flex:0 0 auto; width:38px; height:38px; display:flex; align-items:center; justify-content:center; border-radius:11px; color:#047857; background:#d1fae5; font-size:18px; }
        .sd-upload-title strong { display:block; color:#0f172a; font-size:14px; font-weight:800; }
        .sd-upload-title span { display:block; margin-top:3px; color:#64748b; font-size:11px; }
        .sd-form { display:flex; align-items:end; gap:10px; margin-top:16px; }
        .sd-file-wrap { flex:1 1 auto; }
        .sd-label { display:block; margin-bottom:6px; color:#475569; font-size:11px; font-weight:700; }
        .sd-file { width:100%; padding:8px 10px; border:1px solid #cbd5e1; border-radius:10px; background:#f8fafc; color:#475569; font-size:12px; transition:.15s; }
        .sd-file:hover,.sd-file:focus { border-color:#60a5fa; background:#eff6ff; outline:none; box-shadow:0 0 0 3px rgba(59,130,246,.1); }
        .sd-submit { display:inline-flex; align-items:center; justify-content:center; gap:7px; min-height:40px; padding:0 20px; border:0; border-radius:10px; background:#2563eb; color:#fff; font-size:12px; font-weight:800; cursor:pointer; box-shadow:0 4px 10px rgba(37,99,235,.18); transition:.15s; }
        .sd-submit:hover { background:#1d4ed8; }
        .sd-help { margin-top:12px; padding-top:10px; border-top:1px solid #f1f5f9; color:#64748b; font-size:11px; }
        .sd-alert { margin:0 0 14px; padding:10px 12px; border-radius:10px; font-size:12px; }
        .sd-alert-error { border:1px solid #fecaca; background:#fef2f2; color:#b91c1c; }
        .sd-alert-info { border:1px solid #bae6fd; background:#f0f9ff; color:#0369a1; }
        .sd-table-card { margin-top:16px; overflow:hidden; border:1px solid #e2e8f0; border-radius:16px; background:#fff; box-shadow:0 1px 3px rgba(15,23,42,.06); }
        .sd-table-head { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:13px 16px; border-bottom:1px solid #f1f5f9; background:linear-gradient(90deg,#f8fafc,#fff); }
        .sd-table-head strong { color:#0f172a; font-size:14px; font-weight:800; }
        .sd-table-head span { color:#64748b; font-size:11px; }
        .sd-table-scroll { overflow:auto; max-height:620px; }
        .sd-table { width:100%; min-width:760px; border-collapse:separate; border-spacing:0; font-size:11px; }
        .sd-table th { position:sticky; top:0; z-index:10; padding:10px 11px; border-bottom:1px solid #cbd5e1; background:#f1f5f9; color:#475569; text-align:left; white-space:nowrap; font-size:10px; font-weight:800; text-transform:uppercase; letter-spacing:.04em; }
        .sd-table td { padding:9px 11px; border-bottom:1px solid #edf2f7; color:#334155; vertical-align:top; white-space:nowrap; }
        .sd-table tbody tr:nth-child(even) td { background:#fbfdff; }
        .sd-table tbody tr:hover td { background:#f0f7ff; }
        .sd-number { position:sticky; left:0; z-index:5; width:48px; border-right:1px solid #e2e8f0; background:#fff; color:#94a3b8 !important; font-weight:700; text-align:center !important; }
        .sd-table th.sd-number { z-index:20; background:#f1f5f9; }
        .sd-empty { margin:16px; padding:46px 20px; border:1px dashed #cbd5e1; border-radius:12px; background:#f8fafc; text-align:center; color:#64748b; font-size:12px; }
        @media (min-width:640px) { .sd-page { padding-left:20px; padding-right:20px; } }
        @media (max-width:640px) { .sd-page { padding:12px 8px 24px; } .sd-form { display:block; } .sd-submit { width:100%; margin-top:10px; } .sd-upload-card { padding:15px; } }
    </style>

    <div class="sd-page">
        <section class="relative mb-4 overflow-hidden rounded-2xl border border-blue-900/20 px-4 py-5 text-white shadow-lg sm:px-6"
                 style="background:linear-gradient(115deg,#102f5b 0%,#1d4ed8 58%,#0ea5e9 100%);">
            <div class="absolute -right-12 -top-16 h-44 w-44 rounded-full bg-white/10"></div>
            <div class="relative flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-3">
                    <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-white/15 ring-1 ring-white/25">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7 4h7l4 4v12H7a2 2 0 01-2-2V6a2 2 0 012-2zm7 0v4h4M9 12h6m-6 4h6"/>
                        </svg>
                    </span>
                    <div>
                        <div class="text-[10px] font-extrabold uppercase tracking-[0.18em] text-blue-100">Registrator ofisi</div>
                        <h1 class="mt-0.5 text-2xl font-extrabold tracking-tight sm:text-[28px]">Talabalarni taqsimlash</h1>
                        <p class="mt-1 text-xs text-blue-50 sm:text-sm">Taqsimot faylini yuklang va ma'lumotlarni qulay jadvalda tekshiring.</p>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div class="rounded-xl border border-white/20 bg-white/10 px-3 py-2">
                        <div class="text-[9px] font-bold uppercase text-blue-100">Formatlar</div>
                        <div class="mt-1 text-sm font-extrabold">XLSX, XLS, CSV</div>
                    </div>
                    <div class="rounded-xl border border-white/20 bg-white/10 px-3 py-2">
                        <div class="text-[9px] font-bold uppercase text-blue-100">Fayl limiti</div>
                        <div class="mt-1 text-sm font-extrabold">20 MB</div>
                    </div>
                </div>
            </div>
        </section>

        @if($errors->any())
            <div class="sd-alert sd-alert-error">{{ $errors->first() }}</div>
        @endif

        <section class="sd-upload-card">
            <div class="sd-upload-title">
                <div class="sd-upload-badge">
                    <svg width="19" height="19" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 16V4m0 12l-4-4m4 4l4-4M5 20h14"/>
                    </svg>
                </div>
                <div>
                    <strong>Talabalar taqsimoti faylini yuklash</strong>
                    <span>XLSX, XLS yoki CSV formatidagi faylni tanlang. Birinchi to'ldirilgan qator ustun nomi sifatida olinadi.</span>
                </div>
            </div>
            <form class="sd-form" method="POST" action="{{ route('admin.student-distribution.upload') }}" enctype="multipart/form-data">
                @csrf
                <div class="sd-file-wrap">
                    <label class="sd-label" for="student_file">Excel fayl</label>
                    <input class="sd-file" id="student_file" name="student_file" type="file" accept=".xlsx,.xls,.csv,.txt" required>
                </div>
                <button class="sd-submit" type="submit">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 16V4m0 0L8 8m4-4l4 4M5 14v5h14v-5"/>
                    </svg>
                    Jadvalni ko'rish
                </button>
            </form>
            <div class="sd-help">Fayl serverda saqlanmaydi. Jadval faqat yuklashdan keyin ko'rsatish uchun o'qiladi.</div>
        </section>

        @if(count($headers))
            <section class="sd-table-card">
                <div class="sd-table-head">
                    <div>
                        <strong>Yuklangan jadval</strong>
                        <span title="{{ $fileName }} / {{ $sheetName }}">{{ $fileName }} / {{ $sheetName }}</span>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="rounded-full bg-blue-50 px-2.5 py-1 font-extrabold text-blue-700">{{ number_format($totalRows) }} ta qator</span>
                        <span class="rounded-full bg-slate-100 px-2.5 py-1 font-bold text-slate-600">{{ count($headers) }} ta ustun</span>
                    </div>
                </div>
                @if($truncated)
                    <div class="sd-alert sd-alert-info" style="margin:12px 16px 0;">Juda katta fayl bo'lgani uchun dastlabki 10 000 ta qator ko'rsatildi.</div>
                @endif
                <div class="sd-table-scroll">
                    <table class="sd-table">
                        <thead>
                            <tr>
                                <th class="sd-number">#</th>
                                @foreach($headers as $header)
                                    <th>{{ $header }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($rows as $rowIndex => $row)
                                <tr>
                                    <td class="sd-number">{{ $rowIndex + 1 }}</td>
                                    @foreach($headers as $columnIndex => $header)
                                        <td>{{ $row[$columnIndex] ?? '' }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @else
            <section class="sd-table-card">
                <div class="sd-empty">
                    <svg class="mx-auto mb-2 h-7 w-7 text-blue-500" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 5h16v14H4V5zm0 5h16M9 5v14"/>
                    </svg>
                    <strong class="block text-sm text-slate-800">Jadval hali yuklanmagan</strong>
                    <span class="mt-1 block">Taqsimot faylini tanlang, jadval shu yerning o'zida ochiladi.</span>
                </div>
            </section>
        @endif
    </div>
</x-app-layout>
