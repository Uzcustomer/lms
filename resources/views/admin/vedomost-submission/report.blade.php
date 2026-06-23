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
        // Shakl fon ranglari (ustun guruhini ajratish uchun)
        $formTint = ['12' => '#eef2ff', '12a' => '#ecfeff', '12b' => '#fef2f2'];

        $statusKeys = array_keys($statuses);
        $queryNoDim = collect(request()->query())->except('dimension')->all();

        // Umumiy jami (o'ng pastki katak)
        $grandTotal = 0;
        foreach ($formStatusTotals as $byStatus) {
            $grandTotal += array_sum($byStatus);
        }
    @endphp

    <div style="padding:16px 24px;">
        <div style="background:#fff;border-radius:14px;box-shadow:0 1px 3px rgba(0,0,0,.06);padding:16px;">

            {{-- Boshqaruv: kesim tanlash + orqaga --}}
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:14px;">
                <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;">
                    <span style="font-size:13px;color:#64748b;margin-right:4px;">Kesim:</span>
                    @foreach($dimensions as $k => $d)
                        @php $active = $k === $dimension; @endphp
                        <a href="{{ route('admin.vedomost-submission.report', array_merge($queryNoDim, ['dimension' => $k])) }}"
                           style="padding:6px 14px;border-radius:999px;text-decoration:none;font-size:13px;font-weight:600;
                                  {{ $active ? 'background:#1a3268;color:#fff;' : 'background:#f1f5f9;color:#334155;' }}">
                            {{ $d['label'] }}
                        </a>
                    @endforeach
                </div>
                <a href="{{ route('admin.vedomost-submission.index', $queryNoDim) }}"
                   style="background:#f1f5f9;color:#334155;padding:8px 16px;border-radius:8px;text-decoration:none;font-size:13px;">
                    ← Ro'yxatga qaytish
                </a>
            </div>

            <div style="overflow-x:auto;">
                <table style="border-collapse:collapse;width:100%;font-size:12px;white-space:nowrap;">
                    <thead>
                        <tr>
                            <th rowspan="2" style="position:sticky;left:0;background:#f8fafc;border:1px solid #e2e8f0;padding:8px 12px;text-align:left;z-index:2;">
                                {{ $dimensions[$dimension]['label'] }}
                            </th>
                            @foreach($forms as $fkey => $flabel)
                                <th colspan="{{ count($statusKeys) + 1 }}"
                                    style="border:1px solid #e2e8f0;padding:6px 8px;text-align:center;background:{{ $formTint[$fkey] ?? '#f8fafc' }};">
                                    {{ $flabel }}
                                </th>
                            @endforeach
                            <th rowspan="2" style="border:1px solid #e2e8f0;padding:8px 10px;text-align:center;background:#1a3268;color:#fff;">
                                Jami
                            </th>
                        </tr>
                        <tr>
                            @foreach($forms as $fkey => $flabel)
                                @foreach($statuses as $st => $slabel)
                                    @php $sc = $statusColor[$st] ?? ['#334155','#f1f5f9']; @endphp
                                    <th style="border:1px solid #e2e8f0;padding:5px 7px;text-align:center;background:{{ $sc[1] }};color:{{ $sc[0] }};font-weight:600;">
                                        {{ $slabel }}
                                    </th>
                                @endforeach
                                <th style="border:1px solid #e2e8f0;padding:5px 7px;text-align:center;background:{{ $formTint[$fkey] ?? '#f8fafc' }};font-weight:700;">
                                    Jami
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($pivot as $rowKey => $byForm)
                            @php $rowTotal = 0; @endphp
                            <tr>
                                <td style="position:sticky;left:0;background:#fff;border:1px solid #e2e8f0;padding:7px 12px;text-align:left;font-weight:600;color:#334155;z-index:1;">
                                    {{ $rowKey }}
                                </td>
                                @foreach($forms as $fkey => $flabel)
                                    @php $formTotal = 0; @endphp
                                    @foreach($statusKeys as $st)
                                        @php
                                            $c = $byForm[$fkey][$st] ?? 0;
                                            $formTotal += $c;
                                        @endphp
                                        <td style="border:1px solid #e2e8f0;padding:6px 8px;text-align:center;color:{{ $c ? '#0f172a' : '#cbd5e1' }};">
                                            {{ $c ?: '·' }}
                                        </td>
                                    @endforeach
                                    @php $rowTotal += $formTotal; @endphp
                                    <td style="border:1px solid #e2e8f0;padding:6px 8px;text-align:center;font-weight:700;background:{{ $formTint[$fkey] ?? '#f8fafc' }};">
                                        {{ $formTotal ?: '·' }}
                                    </td>
                                @endforeach
                                <td style="border:1px solid #e2e8f0;padding:6px 10px;text-align:center;font-weight:700;background:#eef2ff;color:#1a3268;">
                                    {{ $rowTotal }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ 1 + count($forms) * (count($statusKeys) + 1) + 1 }}"
                                    style="border:1px solid #e2e8f0;padding:40px;text-align:center;color:#94a3b8;">
                                    Ma'lumot yo'q. Filtrlarni o'zgartirib ko'ring.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if(!empty($pivot))
                        <tfoot>
                            <tr style="font-weight:700;">
                                <td style="position:sticky;left:0;background:#f1f5f9;border:1px solid #e2e8f0;padding:8px 12px;text-align:left;z-index:1;">
                                    Jami
                                </td>
                                @foreach($forms as $fkey => $flabel)
                                    @php $formTotal = 0; @endphp
                                    @foreach($statusKeys as $st)
                                        @php
                                            $c = $formStatusTotals[$fkey][$st] ?? 0;
                                            $formTotal += $c;
                                        @endphp
                                        <td style="border:1px solid #e2e8f0;padding:7px 8px;text-align:center;background:#f8fafc;color:{{ $c ? '#0f172a' : '#cbd5e1' }};">
                                            {{ $c ?: '·' }}
                                        </td>
                                    @endforeach
                                    <td style="border:1px solid #e2e8f0;padding:7px 8px;text-align:center;background:{{ $formTint[$fkey] ?? '#f8fafc' }};">
                                        {{ $formTotal ?: '·' }}
                                    </td>
                                @endforeach
                                <td style="border:1px solid #e2e8f0;padding:7px 10px;text-align:center;background:#1a3268;color:#fff;">
                                    {{ $grandTotal }}
                                </td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>

            <div style="margin-top:10px;font-size:12px;color:#94a3b8;">
                Har bir katak — jamlangan vedomost varaqlari soni (guruhchalar/fan variantlari birlashtirilgan). Ro'yxatdagi filtrlar shu hisobotga ham qo'llaniladi.
            </div>
        </div>
    </div>
</x-app-layout>
