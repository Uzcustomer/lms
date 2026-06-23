<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            Vedomost — svodnaya hisobot
        </h2>
    </x-slot>

    @php
        // Joriy filtrlar.
        $baseQuery = collect(request()->query())->except(['dims', 'rsort', 'rdir'])->all();      // back / dims apply
        $dimsQuery = collect(request()->query())->except(['rsort', 'rdir'])->all();              // sort havolalari
        $dateForm  = collect(request()->query())->except(['from', 'to', 'rsort', 'rdir', 'page'])->all(); // sana formasi

        // Ustun bo'yicha sort havolasi + strelka.
        $sortUrl = function ($col) use ($dimsQuery, $sortCol, $sortDir) {
            $dir = ($sortCol === $col && $sortDir === 'asc') ? 'desc' : 'asc';
            return route('admin.vedomost-submission.report', array_merge($dimsQuery, ['rsort' => $col, 'rdir' => $dir]));
        };
        $arrow = fn($col) => $sortCol === $col ? ($sortDir === 'asc' ? ' ▲' : ' ▼') : '';
        $thLink = 'text-decoration:none;color:inherit;cursor:pointer;display:block;';

        // Ustunlar ro'yxati (tekis) — qator metrikasini o'qish uchun.
        $allColKeys = [];
        foreach ($sections as $sec) {
            foreach ($sec['cols'] as $c) { $allColKeys[] = $c['key']; }
        }

        // Bir bo'lim yig'indisi.
        $sectionSum = function ($metrics, $secKey) {
            $s = 0;
            foreach ($metrics as $k => $v) { if (str_starts_with($k, $secKey.'|')) { $s += $v; } }
            return $s;
        };

        $colCount = 1 + collect($sections)->sum(fn($s) => count($s['cols']) + 1) + ($showGrand ? 1 : 0);
    @endphp

    <div style="padding:16px 24px;">
        <div style="background:#fff;border-radius:14px;box-shadow:0 1px 3px rgba(0,0,0,.06);padding:16px;">

            {{-- Yuqori panel: guruhlash quruvchi + sana oralig'i --}}
            <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:14px;margin-bottom:14px;">
                <div style="flex:1;min-width:280px;">
                    <div style="display:flex;gap:18px;flex-wrap:wrap;">
                        <div style="flex:1;min-width:200px;">
                            <div style="font-size:12px;color:#64748b;font-weight:600;margin-bottom:6px;">Guruhlash (tartib bilan) — sudrab joylashtiring</div>
                            <div id="dims-selected" style="min-height:42px;border:1px dashed #c7d2fe;background:#f5f7ff;border-radius:10px;padding:6px;display:flex;flex-wrap:wrap;gap:6px;align-content:flex-start;">
                                @foreach($selectedDims as $i => $d)
                                    <span class="dim-chip" data-dim="{{ $d }}"
                                          style="display:inline-flex;align-items:center;gap:6px;background:#1a3268;color:#fff;padding:6px 12px;border-radius:999px;font-size:13px;font-weight:600;cursor:grab;">
                                        <span style="opacity:.6;">⠿</span><span class="dim-order">{{ $i + 1 }}</span>. {{ $dimensions[$d]['label'] }}
                                    </span>
                                @endforeach
                            </div>
                        </div>
                        <div style="flex:1;min-width:200px;">
                            <div style="font-size:12px;color:#64748b;font-weight:600;margin-bottom:6px;">Mavjud o'lchamlar</div>
                            <div id="dims-available" style="min-height:42px;border:1px dashed #e2e8f0;background:#f8fafc;border-radius:10px;padding:6px;display:flex;flex-wrap:wrap;gap:6px;align-content:flex-start;">
                                @foreach($availableDims as $d)
                                    <span class="dim-chip" data-dim="{{ $d }}"
                                          style="display:inline-flex;align-items:center;gap:6px;background:#fff;border:1px solid #cbd5e1;color:#334155;padding:6px 12px;border-radius:999px;font-size:13px;font-weight:600;cursor:grab;">
                                        <span style="opacity:.5;">⠿</span>{{ $dimensions[$d]['label'] }}
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    <div style="font-size:11px;color:#94a3b8;margin-top:6px;">
                        Tartib muhim: yuqoridagisi asosiy, keyingilari uning ichida ochiladi (masalan «Kafedra → Fan»). Chipni bosib ham qo'shish/olib tashlash mumkin.
                    </div>
                </div>

                {{-- Sana oralig'i --}}
                <div style="min-width:240px;">
                    <div style="font-size:12px;color:#64748b;font-weight:600;margin-bottom:6px;">Hisobot davri (sana oralig'i)</div>
                    <form method="GET" action="{{ route('admin.vedomost-submission.report') }}" style="display:flex;align-items:flex-end;gap:8px;flex-wrap:wrap;">
                        @foreach($dateForm as $k => $val)
                            <input type="hidden" name="{{ $k }}" value="{{ $val }}">
                        @endforeach
                        <div>
                            <label style="display:block;font-size:11px;color:#94a3b8;">dan</label>
                            <input type="date" name="from" value="{{ optional($from)->format('Y-m-d') }}"
                                   style="border:1px solid #cbd5e1;border-radius:8px;padding:6px 8px;font-size:13px;">
                        </div>
                        <div>
                            <label style="display:block;font-size:11px;color:#94a3b8;">gacha</label>
                            <input type="date" name="to" value="{{ optional($to)->format('Y-m-d') }}"
                                   style="border:1px solid #cbd5e1;border-radius:8px;padding:6px 8px;font-size:13px;">
                        </div>
                        <button type="submit" style="background:#1a3268;color:#fff;border:none;padding:7px 14px;border-radius:8px;cursor:pointer;font-size:13px;">Ko'rsatish</button>
                        @if($from && $to)
                            <a href="{{ route('admin.vedomost-submission.report', $baseQuery) }}"
                               style="font-size:12px;color:#b91c1c;text-decoration:none;align-self:center;">Tozalash</a>
                        @endif
                    </form>
                    @if($from && $to)
                        <div style="font-size:11px;color:#0f766e;margin-top:6px;">
                            Davr: {{ $from->format('d.m.Y') }} — {{ $to->format('d.m.Y') }} (qoldiq–harakat–qoldiq)
                        </div>
                    @endif
                    <div style="margin-top:8px;">
                        <a href="{{ route('admin.vedomost-submission.index', $baseQuery) }}"
                           style="background:#f1f5f9;color:#334155;padding:7px 14px;border-radius:8px;text-decoration:none;font-size:13px;">← Ro'yxatga qaytish</a>
                    </div>
                </div>
            </div>

            <div style="overflow-x:auto;">
                <table style="border-collapse:collapse;width:100%;font-size:12px;white-space:nowrap;">
                    <thead>
                        <tr>
                            <th rowspan="2" style="position:sticky;left:0;background:#f8fafc;border:1px solid #e2e8f0;padding:8px 12px;text-align:left;z-index:2;min-width:240px;">
                                <a href="{{ $sortUrl('label') }}" style="{{ $thLink }}">{{ collect($selectedDims)->map(fn($d)=>$dimensions[$d]['label'])->implode(' › ') }}{!! $arrow('label') !!}</a>
                            </th>
                            @foreach($sections as $sec)
                                <th colspan="{{ count($sec['cols']) + 1 }}"
                                    style="border:1px solid #e2e8f0;padding:6px 8px;text-align:center;background:{{ $sec['tint'] }};font-weight:700;">
                                    {{ $sec['label'] }}
                                </th>
                            @endforeach
                            @if($showGrand)
                                <th rowspan="2" style="border:1px solid #e2e8f0;padding:8px 10px;text-align:center;background:#1a3268;color:#fff;">
                                    <a href="{{ $sortUrl('__grand') }}" style="{{ $thLink }};color:#fff;">Jami{!! $arrow('__grand') !!}</a>
                                </th>
                            @endif
                        </tr>
                        <tr>
                            @foreach($sections as $sec)
                                @foreach($sec['cols'] as $c)
                                    @php $col = $c['color'] ?? ['#334155', '#f1f5f9']; @endphp
                                    <th style="border:1px solid #e2e8f0;padding:5px 7px;text-align:center;background:{{ $col[1] }};color:{{ $col[0] }};font-weight:600;">
                                        <a href="{{ $sortUrl($c['key']) }}" style="{{ $thLink }};color:{{ $col[0] }};">{{ $c['label'] }}{!! $arrow($c['key']) !!}</a>
                                    </th>
                                @endforeach
                                @php $tk = $sec['key'].'|__total'; @endphp
                                <th style="border:1px solid #e2e8f0;padding:5px 7px;text-align:center;background:{{ $sec['tint'] }};font-weight:700;">
                                    <a href="{{ $sortUrl($tk) }}" style="{{ $thLink }}">Jami{!! $arrow($tk) !!}</a>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $row)
                            @php
                                $isParent = $row['has_children'];
                                $indent = 12 + $row['depth'] * 20;
                                $rowBg = $row['depth'] === 0 ? '#ffffff' : '#fbfdff';
                                $grand = array_sum($row['metrics']);
                            @endphp
                            <tr style="background:{{ $rowBg }};">
                                <td style="position:sticky;left:0;background:{{ $rowBg }};border:1px solid #e2e8f0;padding:7px 12px 7px {{ $indent }}px;text-align:left;font-weight:{{ $isParent ? 700 : 500 }};color:{{ $isParent ? '#0f172a' : '#475569' }};z-index:1;">
                                    @if($row['depth'] > 0)<span style="color:#cbd5e1;">└ </span>@endif{{ $row['label'] }}
                                </td>
                                @foreach($sections as $sec)
                                    @foreach($sec['cols'] as $c)
                                        @php $val = $row['metrics'][$c['key']] ?? 0; @endphp
                                        <td style="border:1px solid #e2e8f0;padding:6px 8px;text-align:center;color:{{ $val ? '#0f172a' : '#cbd5e1' }};">{{ $val ?: '·' }}</td>
                                    @endforeach
                                    @php $st = $sectionSum($row['metrics'], $sec['key']); @endphp
                                    <td style="border:1px solid #e2e8f0;padding:6px 8px;text-align:center;font-weight:700;background:{{ $sec['tint'] }};">{{ $st ?: '·' }}</td>
                                @endforeach
                                @if($showGrand)
                                    <td style="border:1px solid #e2e8f0;padding:6px 10px;text-align:center;font-weight:700;background:#eef2ff;color:#1a3268;">{{ $grand }}</td>
                                @endif
                            </tr>
                        @empty
                            <tr><td colspan="{{ $colCount }}" style="border:1px solid #e2e8f0;padding:40px;text-align:center;color:#94a3b8;">Ma'lumot yo'q. Filtrlarni o'zgartirib ko'ring.</td></tr>
                        @endforelse
                    </tbody>
                    @if(!empty($rows))
                        <tfoot>
                            <tr style="font-weight:700;">
                                <td style="position:sticky;left:0;background:#f1f5f9;border:1px solid #e2e8f0;padding:8px 12px;text-align:left;z-index:1;">Jami</td>
                                @foreach($sections as $sec)
                                    @foreach($sec['cols'] as $c)
                                        @php $val = $totalMetrics[$c['key']] ?? 0; @endphp
                                        <td style="border:1px solid #e2e8f0;padding:7px 8px;text-align:center;background:#f8fafc;color:{{ $val ? '#0f172a' : '#cbd5e1' }};">{{ $val ?: '·' }}</td>
                                    @endforeach
                                    @php $st = $sectionSum($totalMetrics, $sec['key']); @endphp
                                    <td style="border:1px solid #e2e8f0;padding:7px 8px;text-align:center;background:{{ $sec['tint'] }};">{{ $st ?: '·' }}</td>
                                @endforeach
                                @if($showGrand)
                                    <td style="border:1px solid #e2e8f0;padding:7px 10px;text-align:center;background:#1a3268;color:#fff;">{{ array_sum($totalMetrics) }}</td>
                                @endif
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>

            <div style="margin-top:10px;font-size:12px;color:#94a3b8;">
                @if($from && $to)
                    «Davri boshiga» va «Davri oxiriga» — o'sha sanadagi holat (audit jurnalidan tiklangan). «Davri ichida» — oraliqda qilingan harakatlar soni. Bir varaq davrda bir necha harakatga ega bo'lishi mumkin.
                @else
                    Har bir katak — jamlangan vedomost varaqlari soni (guruhchalar/fan variantlari birlashtirilgan). Sana oralig'ini kiritsangiz hisobot «davri boshiga / ichida / oxiriga» ko'rinishiga o'tadi.
                @endif
                Ota qatorlar avlodlari yig'indisini ko'rsatadi. Ustun nomiga bosib saralash mumkin. Ro'yxatdagi filtrlar shu hisobotga ham qo'llaniladi.
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
    <script>
        (function () {
            const selected = document.getElementById('dims-selected');
            const available = document.getElementById('dims-available');
            const baseUrl = @json(route('admin.vedomost-submission.report'));

            function apply() {
                const dims = Array.from(selected.querySelectorAll('.dim-chip')).map(e => e.dataset.dim);
                const params = new URLSearchParams(window.location.search);
                if (dims.length) { params.set('dims', dims.join(',')); } else { params.delete('dims'); }
                params.delete('rsort');
                params.delete('rdir');
                window.location = baseUrl + '?' + params.toString();
            }

            if (window.Sortable) {
                new Sortable(selected, { group: 'dims', animation: 150, onEnd: apply });
                new Sortable(available, { group: 'dims', animation: 150, onEnd: apply });
            }

            function bindClick(container, target) {
                container.addEventListener('click', function (e) {
                    const chip = e.target.closest('.dim-chip');
                    if (!chip || !container.contains(chip)) return;
                    target.appendChild(chip);
                    apply();
                });
            }
            bindClick(available, selected);
            bindClick(selected, available);
        })();
    </script>
</x-app-layout>
