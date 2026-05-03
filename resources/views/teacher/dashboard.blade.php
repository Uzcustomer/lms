<x-teacher-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Dashboard
            @if($isTeacherRole && !empty($stats['semester_codes']))
                <small class="text-muted" style="font-size: 14px; font-weight: normal;">
                    — joriy semestrlar: {{ implode(', ', $stats['semester_codes']) }}
                </small>
            @endif
        </h2>
    </x-slot>

    <style>
        .td-card { border: none; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
        .td-card .card-body { padding: 20px; }
        .td-card .td-label { font-size: 13px; color: #6c757d; margin-bottom: 8px; text-transform: uppercase; letter-spacing: .3px; }
        .td-card .td-count { font-size: 28px; font-weight: 700; line-height: 1.1; }
        .td-card .td-percent { font-size: 14px; color: #495057; margin-top: 4px; }
        .td-card.td-green   { border-left: 4px solid #28a745; }
        .td-card.td-blue    { border-left: 4px solid #17a2b8; }
        .td-card.td-orange  { border-left: 4px solid #fd7e14; }
        .td-card.td-red     { border-left: 4px solid #dc3545; }
        .td-empty { background: #fff; border-radius: 12px; padding: 40px; text-align: center; color: #6c757d; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
        .td-top10-table { background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
        .td-top10-table table { margin-bottom: 0; }
        .td-top10-table th { background: #f8f9fa; font-size: 13px; text-transform: uppercase; letter-spacing: .3px; color: #495057; }
        .td-top10-table tr.td-me { background: #fff3cd; font-weight: 600; }
        .td-top10-table .td-rank { font-weight: 700; width: 70px; text-align: center; font-size: 18px; }
        .td-top10-table tr.gold   .td-rank { background: linear-gradient(180deg, #fff7d6 0%, #ffefb3 100%); }
        .td-top10-table tr.silver .td-rank { background: linear-gradient(180deg, #f1f3f5 0%, #dee2e6 100%); }
        .td-top10-table tr.bronze .td-rank { background: linear-gradient(180deg, #fde2cf 0%, #f9c79c 100%); }
        .td-top10-table .td-foiz { font-weight: 700; color: #28a745; }
        .td-my-rank { background: linear-gradient(135deg, #fff3cd 0%, #ffe69c 100%); border-left: 4px solid #ffc107; padding: 16px 20px; border-radius: 12px; margin-bottom: 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
        .td-my-rank .td-rank-big { font-size: 32px; font-weight: 700; color: #856404; line-height: 1; }
        .td-my-rank .td-rank-label { font-size: 13px; color: #6c757d; }
        .td-summary-bar { display: flex; height: 8px; border-radius: 4px; overflow: hidden; margin-top: 16px; }
        .td-summary-bar > div { height: 100%; }
        .td-stale { font-size: 12px; color: #6c757d; }
    </style>

    <div style="padding: 16px 0;">
        <div class="max-w-full mx-auto sm:px-4 lg:px-6">

        @if($isTeacherRole)
            {{-- 4 ta kart yoki bo'sh holat --}}
            @if($stats)
                <div style="margin-bottom: 8px;">
                    <h5 style="margin-bottom: 4px;">Baholash sifati statistikasi</h5>
                    <p class="td-stale">
                        Joriy semestr boshidan jami: <strong>{{ number_format($stats['jami']) }}</strong> ta baholash holati.
                        Oxirgi yangilanish: {{ $stats['last_updated'] }} (kunda 1 marta, soat 03:00 da).
                    </p>
                </div>

                <div class="row" style="margin-bottom: 24px;">
                    <div class="col-md-6 col-lg-3" style="margin-bottom: 16px;">
                        <div class="card td-card td-green">
                            <div class="card-body">
                                <div class="td-label">Dars vaqtida</div>
                                <div class="td-count">{{ number_format($stats['dars_vaqtida']) }} ta</div>
                                <div class="td-percent">{{ $stats['dars_foiz'] }}%</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-3" style="margin-bottom: 16px;">
                        <div class="card td-card td-blue">
                            <div class="card-body">
                                <div class="td-label">Ish vaqtida (18:00 gacha)</div>
                                <div class="td-count">{{ number_format($stats['ish_vaqtida']) }} ta</div>
                                <div class="td-percent">{{ $stats['ish_foiz'] }}%</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-3" style="margin-bottom: 16px;">
                        <div class="card td-card td-orange">
                            <div class="card-body">
                                <div class="td-label">Kech baholangan (18:00 — 23:59)</div>
                                <div class="td-count">{{ number_format($stats['kech']) }} ta</div>
                                <div class="td-percent">{{ $stats['kech_foiz'] }}%</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-3" style="margin-bottom: 16px;">
                        <div class="card td-card td-red">
                            <div class="card-body">
                                <div class="td-label">Baholanmagan</div>
                                <div class="td-count">{{ number_format($stats['baholanmagan']) }} ta</div>
                                <div class="td-percent">{{ $stats['baholanmagan_foiz'] }}%</div>
                            </div>
                        </div>
                    </div>
                </div>

                @if($stats['jami'] > 0)
                    <div style="margin-bottom: 24px;">
                        <div class="td-stale" style="margin-bottom: 4px;">
                            Sizning umumiy "vaqtida baholanganlik" foizingiz:
                            <strong style="color: #28a745;">{{ $stats['vaqtida_foiz'] }}%</strong>
                            (dars vaqti + ish vaqti)
                        </div>
                        <div class="td-summary-bar">
                            <div style="background: #28a745; width: {{ $stats['dars_foiz'] }}%;" title="Dars vaqtida {{ $stats['dars_foiz'] }}%"></div>
                            <div style="background: #17a2b8; width: {{ $stats['ish_foiz'] }}%;" title="Ish vaqtida {{ $stats['ish_foiz'] }}%"></div>
                            <div style="background: #fd7e14; width: {{ $stats['kech_foiz'] }}%;" title="Kech {{ $stats['kech_foiz'] }}%"></div>
                            <div style="background: #dc3545; width: {{ $stats['baholanmagan_foiz'] }}%;" title="Baholanmagan {{ $stats['baholanmagan_foiz'] }}%"></div>
                        </div>
                    </div>
                @endif

                {{-- Shaxsiy o'rin --}}
                @if(!empty($stats['rank']))
                    @php
                        $rankEmoji = match(true) {
                            $stats['rank'] === 1 => '🥇',
                            $stats['rank'] === 2 => '🥈',
                            $stats['rank'] === 3 => '🥉',
                            $stats['rank'] <= 10 => '🏆',
                            $stats['rank'] <= 20 => '🎖️',
                            default => '🏅',
                        };
                    @endphp
                    <div class="td-my-rank" style="margin-bottom: 24px; display: flex; align-items: center; gap: 16px;">
                        <div style="font-size: 40px;">{{ $rankEmoji }}</div>
                        <div>
                            <div class="td-rank-label">Sizning o'rningiz universitet bo'yicha</div>
                            <div class="td-rank-big">
                                {{ $stats['rank'] }}<small style="font-size: 18px; color: #856404; font-weight: 500;">/{{ $stats['total_ranked'] }}</small>
                            </div>
                        </div>
                    </div>
                @endif
            @else
                <div class="td-empty" style="margin-bottom: 24px;">
                    <i class="fas fa-clock" style="font-size: 40px; color: #adb5bd; margin-bottom: 12px;"></i>
                    <h5>Statistika hali tayyor emas</h5>
                    <p style="margin-bottom: 0;">
                        @if($teacher && empty($teacher->hemis_id))
                            Sizning HEMIS ID raqamingiz tizimda yo'q. Administrator bilan bog'laning.
                        @else
                            Ma'lumotlar har kuni soat <strong>03:00</strong> da yangilanadi.
                            Birinchi hisoblashdan keyin shu yerda statistika ko'rinadi.
                        @endif
                    </p>
                </div>
            @endif

            {{-- Top N o'qituvchilar --}}
            <div style="margin-top: 24px;">
                <h5 style="margin-bottom: 12px;">
                    🏆 Top {{ $topSize }} o'qituvchi — vaqtida baholanganlik bo'yicha
                </h5>
                @if(count($topItems) > 0)
                    <div class="td-top10-table">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th class="td-rank">O'rin</th>
                                    <th>F.I.SH</th>
                                    <th>Kafedra</th>
                                    <th class="text-right">Jami baholash</th>
                                    <th class="text-right">Vaqtida foizi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($topItems as $entry)
                                    @php
                                        $isMe = $teacher && !empty($teacher->hemis_id)
                                                && (int)$teacher->hemis_id === (int)$entry['employee_id'];
                                        $rowClass = match($entry['rank']) {
                                            1 => 'gold',
                                            2 => 'silver',
                                            3 => 'bronze',
                                            default => '',
                                        };
                                        if ($isMe) { $rowClass .= ' td-me'; }
                                    @endphp
                                    <tr class="{{ $rowClass }}">
                                        <td class="td-rank">
                                            @if(!empty($entry['medal']))
                                                {{ $entry['medal'] }}
                                            @else
                                                {{ $entry['rank'] }}
                                            @endif
                                        </td>
                                        <td>
                                            {{ $entry['fio'] }}
                                            @if($isMe) <small class="text-muted">(siz)</small> @endif
                                        </td>
                                        <td>
                                            <span style="margin-right: 4px;">{{ $entry['kafedra_emoji'] ?? '🎓' }}</span>
                                            {{ $entry['kafedra'] }}
                                        </td>
                                        <td class="text-right">{{ number_format($entry['jami']) }}</td>
                                        <td class="text-right td-foiz">{{ $entry['foiz'] }}%</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if(!empty($topUpdatedAt))
                        <p class="td-stale" style="margin-top: 8px;">
                            Top {{ $topSize }} yangilangan: {{ $topUpdatedAt }} —
                            jami {{ $topTotalRanked }} ta o'qituvchi reyting ichida
                        </p>
                    @endif
                @else
                    <div class="td-empty">
                        <p style="margin-bottom: 0;">Top ro'yxati hali tayyor emas yoki ma'lumotlar yetarli emas.</p>
                    </div>
                @endif
            </div>
        @endif

        </div>
    </div>
</x-teacher-app-layout>
