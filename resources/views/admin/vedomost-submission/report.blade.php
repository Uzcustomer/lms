<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            Vedomost — svodnaya hisobot
        </h2>
    </x-slot>

    @php
        // Status ranglari: [matn, fon]
        $statusColor = [
            'pending'   => ['#475569', '#f1f5f9'],
            'received'  => ['#1d4ed8', '#dbeafe'],
            'reviewing' => ['#b45309', '#fef3c7'],
            'approved'  => ['#166534', '#dcfce7'],
            'rejected'  => ['#b91c1c', '#fee2e2'],
        ];
        $formTint = ['12' => '#eef2ff', '12a' => '#ecfeff', '12b' => '#fef2f2'];

        $statusKeys = array_keys($statuses);

        // Joriy filtrlar (dims/sort dan tashqari) — havolalarda saqlanadi.
        $baseQuery = collect(request()->query())->except(['dims', 'rsort', 'rdir'])->all();
        $dimsQuery = collect(request()->query())->except(['rsort', 'rdir'])->all();

        // Ustun bo'yicha sort havolasi + strelka.
        $sortUrl = function ($col) use ($dimsQuery, $sortCol, $sortDir) {
            $dir = ($sortCol === $col && $sortDir === 'asc') ? 'desc' : 'asc';
            return route('admin.vedomost-submission.report', array_merge($dimsQuery, ['rsort' => $col, 'rdir' => $dir]));
        };
        $arrow = fn($col) => $sortCol === $col ? ($sortDir === 'asc' ? ' ▲' : ' ▼') : '';
        $thLink = 'text-decoration:none;color:inherit;cursor:pointer;display:block;';

        $grandTotal = 0;
        foreach ($formStatusTotals as $byStatus) { $grandTotal += array_sum($byStatus); }

        $colCount = 1 + count($forms) * (count($statusKeys) + 1) + 1;
    @endphp

    <div style="padding:16px 24px;">
        <div style="background:#fff;border-radius:14px;box-shadow:0 1px 3px rgba(0,0,0,.06);padding:16px;">

            {{-- Guruhlash quruvchi (drag-and-drop) --}}
            <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:14px;margin-bottom:14px;">
                <div style="flex:1;min-width:280px;">
                    <div style="display:flex;gap:18px;flex-wrap:wrap;">
                        <div style="flex:1;min-width:200px;">
                            <div style="font-size:12px;color:#64748b;font-weight:600;margin-bottom:6px;">Guruhlash (tartib bilan) — sudrab joylashtiring</div>
                            <div id="dims-selected" style="min-height:42px;border:1px dashed #c7d2fe;background:#f5f7ff;border-radius:10px;padding:6px;display:flex;flex-wrap:wrap;gap:6px;align-content:flex-start;">
                                @foreach($selectedDims as $i => $d)
                                    <span class="dim-chip" data-dim="{{ $d }}"
                                          style="display:inline-flex;align-items:center;gap:6px;background:#1a3268;color:#fff;padding:6px 12px;border-radius:999px;font-size:13px;font-weight:600;cursor:grab;">
                                        <span style="opacity:.6;">⠿</span>
                                        <span class="dim-order">{{ $i + 1 }}</span>.
                                        {{ $dimensions[$d]['label'] }}
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
                <a href="{{ route('admin.vedomost-submission.index', $baseQuery) }}"
                   style="background:#f1f5f9;color:#334155;padding:8px 16px;border-radius:8px;text-decoration:none;font-size:13px;white-space:nowrap;">
                    ← Ro'yxatga qaytish
                </a>
            </div>

            <div style="overflow-x:auto;">
                <table style="border-collapse:collapse;width:100%;font-size:12px;white-space:nowrap;">
                    <thead>
                        <tr>
                            <th rowspan="2" style="position:sticky;left:0;background:#f8fafc;border:1px solid #e2e8f0;padding:8px 12px;text-align:left;z-index:2;min-width:240px;">
                                <a href="{{ $sortUrl('label') }}" style="{{ $thLink }}">{{ collect($selectedDims)->map(fn($d)=>$dimensions[$d]['label'])->implode(' › ') }}{!! $arrow('label') !!}</a>
                            </th>
                            @foreach($forms as $fkey => $flabel)
                                <th colspan="{{ count($statusKeys) + 1 }}"
                                    style="border:1px solid #e2e8f0;padding:6px 8px;text-align:center;background:{{ $formTint[$fkey] ?? '#f8fafc' }};">
                                    {{ $flabel }}
                                </th>
                            @endforeach
                            <th rowspan="2" style="border:1px solid #e2e8f0;padding:8px 10px;text-align:center;background:#1a3268;color:#fff;">
                                <a href="{{ $sortUrl('__grand') }}" style="{{ $thLink }};color:#fff;">Jami{!! $arrow('__grand') !!}</a>
                            </th>
                        </tr>
                        <tr>
                            @foreach($forms as $fkey => $flabel)
                                @foreach($statuses as $st => $slabel)
                                    @php $sc = $statusColor[$st] ?? ['#334155','#f1f5f9']; $col = $fkey.'|'.$st; @endphp
                                    <th style="border:1px solid #e2e8f0;padding:5px 7px;text-align:center;background:{{ $sc[1] }};color:{{ $sc[0] }};font-weight:600;">
                                        <a href="{{ $sortUrl($col) }}" style="{{ $thLink }};color:{{ $sc[0] }};">{{ $slabel }}{!! $arrow($col) !!}</a>
                                    </th>
                                @endforeach
                                @php $col = $fkey.'|__total'; @endphp
                                <th style="border:1px solid #e2e8f0;padding:5px 7px;text-align:center;background:{{ $formTint[$fkey] ?? '#f8fafc' }};font-weight:700;">
                                    <a href="{{ $sortUrl($col) }}" style="{{ $thLink }}">Jami{!! $arrow($col) !!}</a>
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
                            @endphp
                            <tr style="background:{{ $rowBg }};">
                                <td style="position:sticky;left:0;background:{{ $rowBg }};border:1px solid #e2e8f0;padding:7px 12px 7px {{ $indent }}px;text-align:left;font-weight:{{ $isParent ? 700 : 500 }};color:{{ $isParent ? '#0f172a' : '#475569' }};z-index:1;">
                                    @if($row['depth'] > 0)<span style="color:#cbd5e1;">└ </span>@endif{{ $row['label'] }}
                                </td>
                                @foreach($forms as $fkey => $flabel)
                                    @php $formTotal = 0; @endphp
                                    @foreach($statusKeys as $st)
                                        @php $c = $row['metrics'][$fkey][$st] ?? 0; $formTotal += $c; @endphp
                                        <td style="border:1px solid #e2e8f0;padding:6px 8px;text-align:center;color:{{ $c ? '#0f172a' : '#cbd5e1' }};">{{ $c ?: '·' }}</td>
                                    @endforeach
                                    <td style="border:1px solid #e2e8f0;padding:6px 8px;text-align:center;font-weight:700;background:{{ $formTint[$fkey] ?? '#f8fafc' }};">{{ $formTotal ?: '·' }}</td>
                                @endforeach
                                <td style="border:1px solid #e2e8f0;padding:6px 10px;text-align:center;font-weight:700;background:#eef2ff;color:#1a3268;">{{ $row['total'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="{{ $colCount }}" style="border:1px solid #e2e8f0;padding:40px;text-align:center;color:#94a3b8;">Ma'lumot yo'q. Filtrlarni o'zgartirib ko'ring.</td></tr>
                        @endforelse
                    </tbody>
                    @if(!empty($rows))
                        <tfoot>
                            <tr style="font-weight:700;">
                                <td style="position:sticky;left:0;background:#f1f5f9;border:1px solid #e2e8f0;padding:8px 12px;text-align:left;z-index:1;">Jami</td>
                                @foreach($forms as $fkey => $flabel)
                                    @php $formTotal = 0; @endphp
                                    @foreach($statusKeys as $st)
                                        @php $c = $formStatusTotals[$fkey][$st] ?? 0; $formTotal += $c; @endphp
                                        <td style="border:1px solid #e2e8f0;padding:7px 8px;text-align:center;background:#f8fafc;color:{{ $c ? '#0f172a' : '#cbd5e1' }};">{{ $c ?: '·' }}</td>
                                    @endforeach
                                    <td style="border:1px solid #e2e8f0;padding:7px 8px;text-align:center;background:{{ $formTint[$fkey] ?? '#f8fafc' }};">{{ $formTotal ?: '·' }}</td>
                                @endforeach
                                <td style="border:1px solid #e2e8f0;padding:7px 10px;text-align:center;background:#1a3268;color:#fff;">{{ $grandTotal }}</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>

            <div style="margin-top:10px;font-size:12px;color:#94a3b8;">
                Har bir katak — jamlangan vedomost varaqlari soni (guruhchalar/fan variantlari birlashtirilgan). Ota qatorlar avlodlari yig'indisini ko'rsatadi. Ustun nomiga bosib o'sish/kamayish bo'yicha saralash mumkin. Ro'yxatdagi filtrlar shu hisobotga ham qo'llaniladi.
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
                // Guruhlash o'zgarganda sortni nom bo'yicha qaytaramiz (ustunlar mos kelishi uchun).
                params.delete('rsort');
                params.delete('rdir');
                window.location = baseUrl + '?' + params.toString();
            }

            if (window.Sortable) {
                new Sortable(selected, { group: 'dims', animation: 150, onEnd: apply });
                new Sortable(available, { group: 'dims', animation: 150, onEnd: apply });
            }

            // Chipni bosib ko'chirish (drag ishlamasa ham): tanlangan<->mavjud.
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
