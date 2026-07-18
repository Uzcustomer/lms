<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            Guruh tuzatishlari — til / hisobga olish
        </h2>
    </x-slot>

    @php
        $ovMap = $overrides->keyBy('group_hemis_id');
    @endphp

    <div class="py-4">
        <div class="max-w-7xl mx-auto sm:px-4 lg:px-6">

            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mb-4">
                <div style="padding:14px 20px;background:linear-gradient(135deg,#f0f4f8,#e8edf5);border-bottom:2px solid #dbe4ef;display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
                    <div>
                        <div style="font-size:15px;font-weight:800;color:#0f172a;">Aralash tilli / muammoli guruhlar</div>
                        <div style="font-size:12px;color:#64748b;margin-top:2px;">
                            Bitta "katta guruh" ichida bir nechta til bo'lsa — bu HEMIS xatosi. HEMIS to'g'rilanmaguncha shu yerda qo'lda to'g'rilang.
                        </div>
                    </div>
                    <a href="{{ route('admin.reports.oqim') }}" class="btn-back">← Oqim hisobotiga</a>
                </div>

                <div style="padding:12px 20px;">
                    @if(count($mixed) === 0)
                        <div class="ok-box">✓ Aralash tilli guruh topilmadi — hammasi joyida.</div>
                    @else
                        @foreach($mixed as $g)
                            <div class="mix-block">
                                <div class="mix-head">
                                    <span class="mix-base">{{ $g['base'] }}</span>
                                    <span class="mix-meta">{{ $g['level_name'] }} · {{ $g['department_name'] }}</span>
                                    <span class="mix-langs">Tillar: {{ implode(', ', array_map(fn($l)=>['uz'=>"o'z",'rus'=>'rus','ing'=>'ing'][$l] ?? $l, $g['langs'])) }}</span>
                                </div>
                                <table class="ov-table">
                                    <thead>
                                        <tr><th>Guruh</th><th>HEMIS tili</th><th>Talaba</th><th>Tuzatish (LMS)</th></tr>
                                    </thead>
                                    <tbody>
                                        @foreach($g['members'] as $m)
                                            @php $ov = $ovMap[$m['group_id']] ?? null; @endphp
                                            <tr>
                                                <td style="font-weight:700;color:#0f172a;">{{ $m['group_name'] }}</td>
                                                <td style="color:#64748b;">{{ $m['hemis_lang'] ?? '-' }}</td>
                                                <td style="text-align:center;font-weight:700;">{{ $m['count'] }}</td>
                                                <td>
                                                    <select class="ov-select"
                                                        data-gid="{{ $m['group_id'] }}"
                                                        data-gname="{{ $m['group_name'] }}">
                                                        <option value="">HEMISdagidek</option>
                                                        <option value="uz"  {{ $ov && $ov->lang==='uz'  ? 'selected':'' }}>O'zbek qilib belgila</option>
                                                        <option value="rus" {{ $ov && $ov->lang==='rus' ? 'selected':'' }}>Rus qilib belgila</option>
                                                        <option value="ing" {{ $ov && $ov->lang==='ing' ? 'selected':'' }}>Ingliz qilib belgila</option>
                                                        <option value="__exclude__" {{ $ov && $ov->excluded ? 'selected':'' }}>Hisobga olinmasin</option>
                                                    </select>
                                                    <span class="ov-status" data-gid="{{ $m['group_id'] }}"></span>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>

            {{-- Barcha guruhlar — istalgan guruh tilini o'zgartirish --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mb-4">
                <div style="padding:14px 20px;background:linear-gradient(135deg,#f0f4f8,#e8edf5);border-bottom:2px solid #dbe4ef;">
                    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
                        <div>
                            <div style="font-size:15px;font-weight:800;color:#0f172a;">Barcha guruhlar — tilni o'zgartirish</div>
                            <div style="font-size:12px;color:#64748b;margin-top:2px;">
                                Aralash bo'lmagan guruhlarni ham shu yerdan o'zgartirishingiz mumkin. Guruh, fakultet yoki kurs nomi bo'yicha qidiring.
                            </div>
                        </div>
                        <input type="text" id="grp-search" placeholder="🔍 Guruh / fakultet / kurs qidirish..." class="grp-search">
                    </div>
                </div>
                <div style="padding:0;max-height:70vh;overflow:auto;">
                    <table class="ov-table all-table">
                        <thead>
                            <tr>
                                <th>Guruh</th><th>Fakultet</th><th>Kurs</th><th>HEMIS tili</th><th>Talaba</th><th>Tuzatish (LMS)</th>
                            </tr>
                        </thead>
                        <tbody id="all-groups-body">
                            @foreach($all as $g)
                                @foreach($g['members'] as $m)
                                    @php
                                        $ov = $ovMap[$m['group_id']] ?? null;
                                        $langLabel = ['uz'=>"o'z",'rus'=>'rus','ing'=>'ing'][$m['lang']] ?? $m['lang'];
                                        $search = mb_strtolower($m['group_name'].' '.$g['department_name'].' '.$g['level_name'].' '.$g['base']);
                                    @endphp
                                    <tr class="all-row" data-search="{{ $search }}">
                                        <td style="font-weight:700;color:#0f172a;white-space:nowrap;">
                                            {{ $m['group_name'] }}
                                            @if($g['is_mixed'])<span class="tag tag-amber" title="Aralash tilli guruh">aralash</span>@endif
                                        </td>
                                        <td style="color:#475569;">{{ $g['department_name'] }}</td>
                                        <td style="color:#64748b;white-space:nowrap;">{{ $g['level_name'] }}</td>
                                        <td style="color:#64748b;">{{ $m['hemis_lang'] ?? '-' }}</td>
                                        <td style="text-align:center;font-weight:700;">{{ $m['count'] }}</td>
                                        <td>
                                            <select class="ov-select"
                                                data-gid="{{ $m['group_id'] }}"
                                                data-gname="{{ $m['group_name'] }}">
                                                <option value="">HEMISdagidek</option>
                                                <option value="uz"  {{ $ov && $ov->lang==='uz'  ? 'selected':'' }}>O'zbek qilib belgila</option>
                                                <option value="rus" {{ $ov && $ov->lang==='rus' ? 'selected':'' }}>Rus qilib belgila</option>
                                                <option value="ing" {{ $ov && $ov->lang==='ing' ? 'selected':'' }}>Ingliz qilib belgila</option>
                                                <option value="__exclude__" {{ $ov && $ov->excluded ? 'selected':'' }}>Hisobga olinmasin</option>
                                            </select>
                                            <span class="ov-status" data-gid="{{ $m['group_id'] }}"></span>
                                        </td>
                                    </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                    </table>
                    <div id="grp-noresult" style="display:none;padding:20px;text-align:center;color:#94a3b8;font-size:13px;">Mos guruh topilmadi.</div>
                </div>
            </div>

            {{-- Joriy tuzatishlar --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div style="padding:14px 20px;background:#f8fafc;border-bottom:1px solid #e2e8f0;font-size:15px;font-weight:800;color:#0f172a;">
                    Joriy qo'lda tuzatishlar ({{ $overrides->count() }})
                </div>
                <div style="padding:12px 20px;">
                    @if($overrides->count() === 0)
                        <div style="color:#94a3b8;font-size:13px;padding:8px 0;">Hozircha tuzatishlar yo'q.</div>
                    @else
                        <table class="ov-table">
                            <thead><tr><th>Guruh</th><th>Tuzatish</th><th>Izoh</th><th></th></tr></thead>
                            <tbody>
                                @foreach($overrides as $ov)
                                    <tr>
                                        <td style="font-weight:700;">{{ $ov->group_name ?? $ov->group_hemis_id }}</td>
                                        <td>
                                            @if($ov->excluded)
                                                <span class="tag tag-red">Hisobga olinmaydi</span>
                                            @elseif($ov->lang)
                                                <span class="tag tag-blue">Til: {{ ['uz'=>"o'z",'rus'=>'rus','ing'=>'ing'][$ov->lang] ?? $ov->lang }}</span>
                                            @endif
                                        </td>
                                        <td style="color:#64748b;">{{ $ov->note }}</td>
                                        <td style="text-align:right;">
                                            <button class="btn-clear" data-gid="{{ $ov->group_hemis_id }}">Bekor qilish</button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>

        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        const SAVE_URL = '{{ route("admin.reports.oqim.overrides.save") }}';
        const DEL_URL  = '{{ route("admin.reports.oqim.overrides.delete") }}';
        const CSRF = '{{ csrf_token() }}';

        $(document).on('change', '.ov-select', function() {
            const gid = $(this).data('gid');
            const gname = $(this).data('gname');
            const val = $(this).val();
            const $status = $('.ov-status[data-gid="' + gid + '"]');
            $status.text('saqlanmoqda...').css('color', '#94a3b8');

            const payload = { _token: CSRF, group_hemis_id: gid, group_name: gname };
            if (val === '__exclude__') { payload.excluded = 1; }
            else if (val) { payload.lang = val; }

            $.post(SAVE_URL, payload)
                .done(function() {
                    $status.text('✓ saqlandi').css('color', '#16a34a');
                    setTimeout(() => $status.text(''), 2000);
                })
                .fail(function() { $status.text('xatolik').css('color', '#dc2626'); });
        });

        $(document).on('click', '.btn-clear', function() {
            const gid = $(this).data('gid');
            const $row = $(this).closest('tr');
            $.post(DEL_URL, { _token: CSRF, group_hemis_id: gid })
                .done(function() { $row.fadeOut(200, function(){ $(this).remove(); }); })
                .fail(function() { alert('Xatolik yuz berdi'); });
        });

        // "Barcha guruhlar" bo'yicha qidiruv (guruh / fakultet / kurs)
        $('#grp-search').on('input', function() {
            const q = $(this).val().trim().toLowerCase();
            let shown = 0;
            $('#all-groups-body .all-row').each(function() {
                const match = !q || ($(this).data('search') + '').indexOf(q) !== -1;
                $(this).toggle(match);
                if (match) shown++;
            });
            $('#grp-noresult').toggle(shown === 0);
        });
    </script>

    <style>
        .btn-back { font-size:13px;font-weight:700;color:#2b5ea7;text-decoration:none;padding:7px 14px;border:1px solid #bfdbfe;border-radius:8px;background:#eff6ff; }
        .btn-back:hover { background:#2b5ea7;color:#fff; }
        .ok-box { padding:20px;text-align:center;color:#16a34a;font-weight:700;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px; }
        .mix-block { border:1px solid #fde68a;border-radius:10px;margin-bottom:12px;overflow:hidden; }
        .mix-head { background:#fffbeb;padding:8px 12px;display:flex;gap:12px;align-items:baseline;flex-wrap:wrap;border-bottom:1px solid #fde68a; }
        .mix-base { font-size:15px;font-weight:800;color:#b45309; }
        .mix-meta { font-size:12px;color:#94a3b8; }
        .mix-langs { font-size:12px;font-weight:700;color:#dc2626;margin-left:auto; }
        .ov-table { width:100%;border-collapse:collapse;font-size:13px; }
        .ov-table th { text-align:left;padding:7px 12px;background:#f8fafc;color:#475569;font-size:11px;text-transform:uppercase;font-weight:700;border-bottom:1px solid #e2e8f0; }
        .ov-table td { padding:7px 12px;border-bottom:1px solid #f1f5f9;vertical-align:middle; }
        .ov-select { height:32px;border:1px solid #cbd5e1;border-radius:7px;padding:0 8px;font-size:12.5px;font-weight:600;color:#1e293b;background:#fff; }
        .ov-select:focus { outline:none;border-color:#2b5ea7; }
        .ov-status { margin-left:8px;font-size:11.5px;font-weight:700; }
        .tag { display:inline-block;padding:2px 9px;border-radius:6px;font-size:11.5px;font-weight:700; }
        .tag-red { background:#fef2f2;color:#dc2626;border:1px solid #fecaca; }
        .tag-blue { background:#eff6ff;color:#2b5ea7;border:1px solid #bfdbfe; }
        .tag-amber { background:#fffbeb;color:#b45309;border:1px solid #fde68a;margin-left:6px; }
        .grp-search { height:36px;min-width:280px;border:1px solid #cbd5e1;border-radius:8px;padding:0 12px;font-size:13px;font-weight:500;color:#1e293b;background:#fff; }
        .grp-search:focus { outline:none;border-color:#2b5ea7;box-shadow:0 0 0 2px rgba(43,94,167,0.12); }
        .all-table thead th { position:sticky;top:0;z-index:1; }
        .all-table tbody tr:hover td { background:#f8fafc; }
        .btn-clear { font-size:12px;font-weight:700;color:#dc2626;background:#fef2f2;border:1px solid #fecaca;border-radius:6px;padding:4px 10px;cursor:pointer; }
        .btn-clear:hover { background:#dc2626;color:#fff; }
    </style>
</x-app-layout>
