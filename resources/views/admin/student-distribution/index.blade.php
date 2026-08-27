<x-app-layout>
    <x-slot name="header">
        <div class="sd-header">
            <div>
                <div class="sd-kicker">REGISTRATOR OFISI</div>
                <h2>Talabalarni taqsimlash</h2>
                <p>Excel fayldagi talabalar jadvalini yuklang va shu sahifada ko'ring.</p>
            </div>
            <div class="sd-header-icon"><i class="bi bi-people-fill" aria-hidden="true"></i></div>
        </div>
    </x-slot>

    <style>
        .sd-page { max-width: 1600px; margin: 0 auto; padding: 20px 18px 34px; color: #16324f; }
        .sd-header { display:flex; align-items:center; justify-content:space-between; gap:16px; }
        .sd-kicker { color:#2563eb; font-size:10px; font-weight:800; letter-spacing:.12em; }
        .sd-header h2 { margin:3px 0 0; font-size:22px; font-weight:800; color:#16324f; }
        .sd-header p { margin:4px 0 0; color:#64748b; font-size:12px; }
        .sd-header-icon { width:44px; height:44px; display:flex; align-items:center; justify-content:center; border-radius:14px; color:#fff; background:linear-gradient(135deg,#2563eb,#0ea5e9); box-shadow:0 8px 18px rgba(37,99,235,.2); font-size:20px; }
        .sd-upload-card { margin-top:18px; padding:20px; border:1px solid #dbe5f0; border-radius:16px; background:linear-gradient(135deg,#eff6ff 0%,#fff 62%); box-shadow:0 8px 24px rgba(30,64,175,.07); }
        .sd-upload-title { display:flex; align-items:flex-start; gap:12px; }
        .sd-upload-badge { flex:0 0 auto; width:38px; height:38px; display:flex; align-items:center; justify-content:center; border-radius:11px; color:#1d4ed8; background:#dbeafe; font-size:18px; }
        .sd-upload-title strong { display:block; color:#16324f; font-size:15px; }
        .sd-upload-title span { display:block; margin-top:3px; color:#64748b; font-size:12px; }
        .sd-form { display:flex; align-items:end; gap:10px; margin-top:16px; }
        .sd-file-wrap { flex:1 1 auto; }
        .sd-label { display:block; margin-bottom:6px; color:#475569; font-size:11px; font-weight:700; }
        .sd-file { width:100%; padding:9px 10px; border:1px solid #bfd0e5; border-radius:9px; background:#fff; color:#475569; font-size:12px; }
        .sd-submit { display:inline-flex; align-items:center; justify-content:center; gap:7px; min-height:39px; padding:0 18px; border:0; border-radius:9px; background:#2563eb; color:#fff; font-size:12px; font-weight:700; cursor:pointer; box-shadow:0 4px 10px rgba(37,99,235,.2); }
        .sd-submit:hover { background:#1d4ed8; }
        .sd-help { margin-top:10px; color:#94a3b8; font-size:11px; }
        .sd-alert { margin-top:14px; padding:10px 12px; border-radius:9px; font-size:12px; }
        .sd-alert-error { border:1px solid #fecaca; background:#fef2f2; color:#b91c1c; }
        .sd-alert-info { border:1px solid #bae6fd; background:#f0f9ff; color:#0369a1; }
        .sd-table-card { margin-top:18px; overflow:hidden; border:1px solid #dbe5f0; border-radius:16px; background:#fff; box-shadow:0 6px 20px rgba(15,23,42,.06); }
        .sd-table-head { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:14px 16px; border-bottom:1px solid #e5edf5; }
        .sd-table-head strong { color:#16324f; font-size:14px; }
        .sd-table-head span { color:#64748b; font-size:11px; }
        .sd-table-scroll { overflow:auto; max-height:620px; }
        .sd-table { width:100%; min-width:760px; border-collapse:separate; border-spacing:0; font-size:11px; }
        .sd-table th { position:sticky; top:0; z-index:1; padding:10px 11px; border-bottom:1px solid #bfdbfe; background:#eff6ff; color:#1e3a8a; text-align:left; white-space:nowrap; font-size:10px; font-weight:800; }
        .sd-table td { padding:9px 11px; border-bottom:1px solid #edf2f7; color:#334155; vertical-align:top; white-space:nowrap; }
        .sd-table tbody tr:nth-child(even) td { background:#fbfdff; }
        .sd-table tbody tr:hover td { background:#f0f7ff; }
        .sd-number { width:42px; color:#94a3b8 !important; font-weight:700; }
        .sd-empty { padding:42px 20px; text-align:center; color:#94a3b8; font-size:12px; }
        @media (max-width: 640px) { .sd-page { padding:14px 10px 24px; } .sd-header h2 { font-size:19px; } .sd-form { display:block; } .sd-submit { width:100%; margin-top:10px; } .sd-upload-card { padding:15px; } }
    </style>

    <div class="sd-page">
        @if($errors->any())
            <div class="sd-alert sd-alert-error">{{ $errors->first() }}</div>
        @endif

        <section class="sd-upload-card">
            <div class="sd-upload-title">
                <div class="sd-upload-badge"><i class="bi bi-file-earmark-spreadsheet" aria-hidden="true"></i></div>
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
                <button class="sd-submit" type="submit"><i class="bi bi-upload" aria-hidden="true"></i> Excelni o'qish</button>
            </form>
            <div class="sd-help">Fayl serverda saqlanmaydi. Jadval faqat yuklashdan keyin ko'rsatish uchun o'qiladi.</div>
        </section>

        @if(count($headers))
            <section class="sd-table-card">
                <div class="sd-table-head">
                    <div>
                        <strong>Yuklangan jadval</strong>
                        <span>{{ $fileName }} ? {{ $sheetName }}</span>
                    </div>
                    <span>{{ number_format($totalRows) }} ta qator</span>
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
                <div class="sd-empty"><i class="bi bi-table" style="font-size:24px;display:block;margin-bottom:8px;"></i>Excel yuklangandan keyin uning jadvali shu yerda ko'rinadi.</div>
            </section>
        @endif
    </div>
</x-app-layout>
