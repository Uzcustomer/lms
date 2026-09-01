<x-app-layout>
<style>
    .j-filters { display: grid; grid-template-columns: 2fr 1fr auto; gap: 10px; align-items: end; }
    .j-filters label { display: block; margin-bottom: 5px; color: #47597a; font-size: 11px; font-weight: 800; }
    .j-filters select {
        width: 100%; height: 38px; padding: 0 10px;
        border: 1px solid #ccdaee; border-radius: 8px; background: #fff; font-size: 13px;
    }
    .j-filters button { height: 38px; padding: 0 18px; border: 0; border-radius: 8px; background: #2563eb; color: #fff; font-size: 12px; font-weight: 800; cursor: pointer; }
    .j-filters button:hover { background: #1d4ed8; }

    .j-stats { display: grid; grid-template-columns: repeat(5, 1fr); gap: 10px; }
    .j-stat { padding: 13px 15px; border: 1px solid #e2eaf6; border-radius: 12px; background: #fff; }
    .j-stat b { display: block; color: #16263f; font-size: 21px; font-weight: 900; }
    .j-stat span { color: #93a5bf; font-size: 10px; font-weight: 800; letter-spacing: .04em; }

    .j-group { overflow: hidden; margin-bottom: 14px; border: 1px solid #dde7f5; border-radius: 14px; background: #fff; }
    .j-group-head {
        display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px;
        padding: 12px 16px; border-bottom: 1px solid #eaf0f9;
        background: linear-gradient(120deg, #f6f9fe, #eef5ff);
    }
    .j-group-head b { color: #16263f; font-size: 14px; font-weight: 900; }
    .j-badges { display: flex; flex-wrap: wrap; gap: 6px; }
    .j-badge { padding: 4px 9px; border-radius: 999px; background: #eef4fd; color: #2f4468; font-size: 10px; font-weight: 800; }
    .j-badge.ok { background: #ecfdf5; color: #047857; }

    .j-table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .j-table th {
        padding: 9px 14px; border-bottom: 1px solid #eaf0f9; background: #fafcff;
        color: #7e91ad; font-size: 10px; font-weight: 900; letter-spacing: .05em; text-align: left; text-transform: uppercase;
    }
    .j-table td { padding: 10px 14px; border-bottom: 1px solid #f2f6fc; color: #21375c; vertical-align: top; }
    .j-table tr:last-child td { border-bottom: 0; }
    .j-name { font-weight: 800; }
    .j-id { display: block; margin-top: 2px; color: #93a5bf; font-size: 11px; font-weight: 600; }
    .j-pill { display: inline-flex; padding: 3px 9px; border-radius: 999px; font-size: 10px; font-weight: 800; }
    .j-pill.ok { background: #ecfdf5; color: #047857; }
    .j-pill.no { background: #fef2f2; color: #b91c1c; }
    .j-pill.wait { background: #fff7ed; color: #c2410c; }
    .j-score { font-weight: 900; }

    .j-answers { padding: 0; }
    .j-answers summary {
        padding: 6px 0; color: #2563eb; font-size: 11px; font-weight: 800; cursor: pointer; list-style: none;
    }
    .j-answers summary::-webkit-details-marker { display: none; }
    .j-answers summary::before { content: '▸ '; }
    .j-answers[open] summary::before { content: '▾ '; }
    .j-answer { display: flex; gap: 8px; padding: 6px 0; border-top: 1px solid #f2f6fc; }
    .j-mark { flex: none; display: grid; place-items: center; width: 19px; height: 19px; border-radius: 5px; color: #fff; font-size: 11px; font-weight: 900; }
    .j-mark.ok { background: #059669; }
    .j-mark.no { background: #dc2626; }
    .j-answer-text { font-size: 12px; line-height: 1.5; }
    .j-answer-text i { font-style: normal; color: #93a5bf; }
    .j-answer-text em { font-style: normal; color: #047857; font-weight: 800; }

    .j-empty { padding: 44px 20px; color: #93a5bf; font-size: 13px; text-align: center; }
    @media (max-width: 860px) { .j-stats { grid-template-columns: repeat(2, 1fr); } .j-filters { grid-template-columns: 1fr; } }
</style>

    <div class="py-6">
        <div class="w-full px-4 sm:px-6 lg:px-8 space-y-4">

            <div class="rounded-[20px] bg-gradient-to-r from-slate-950 via-blue-900 to-cyan-700 px-6 py-6 text-white shadow-xl">
                <div class="mb-1 text-[10px] font-black uppercase tracking-[0.2em] text-cyan-200">Test moduli</div>
                <h1 class="text-2xl font-black tracking-tight">Test jurnali</h1>
                <p class="mt-1 text-sm text-blue-100">Har bir guruh qaysi test to'plamidan qanday natija ko'rsatganini ko'ring.</p>
            </div>

            @if($migrationPending)
                <div class="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm font-bold text-amber-800">
                    Test natijalari jadvali hali yaratilmagan. Serverda <code>php artisan migrate</code> ni ishga tushiring.
                </div>
            @elseif($collections->isEmpty())
                <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center">
                    <h3 class="font-black text-slate-800">Hali test to'plami yo'q</h3>
                    <p class="mt-1 text-sm text-slate-500">Avval "Test yaratish" bo'limida to'plam tuzing.</p>
                </div>
            @else
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <form method="GET" class="j-filters">
                        <div>
                            <label for="test_id">Test to'plami</label>
                            <select name="test_id" id="test_id">
                                @foreach($collections as $item)
                                    <option value="{{ $item->id }}" @selected($selected && $item->id === $selected->id)>
                                        {{ $item->name }} — {{ $item->subject?->subject_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="group">Guruh</label>
                            <select name="group" id="group">
                                <option value="">Barcha guruhlar</option>
                                @foreach($allGroups ?? [] as $groupName)
                                    <option value="{{ $groupName }}" @selected(request('group') === $groupName)>{{ $groupName }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit">Ko'rsatish</button>
                    </form>
                </div>

                @if($selected)
                    <div class="j-stats">
                        <div class="j-stat"><b>{{ $summary['total'] }}</b><span>JAMI TALABA</span></div>
                        <div class="j-stat"><b>{{ $summary['submitted'] }}</b><span>TOPSHIRGAN</span></div>
                        <div class="j-stat"><b>{{ $summary['in_progress'] }}</b><span>ISHLAMOQDA</span></div>
                        <div class="j-stat"><b>{{ $summary['passed'] }}</b><span>O'TGAN</span></div>
                        <div class="j-stat"><b>{{ $summary['average_percent'] }}%</b><span>O'RTACHA</span></div>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-white px-5 py-3 text-xs font-bold text-slate-600 shadow-sm">
                        Talaba havolasi:
                        <a href="{{ route('kiosk.fan-testi.show', $selected) }}" target="_blank"
                           class="text-blue-600 hover:text-blue-800">{{ route('kiosk.fan-testi.show', $selected) }}</a>
                        — shu havolani sinf kompyuterlarida oching.
                    </div>

                    @forelse($groups as $group)
                        <div class="j-group">
                            <div class="j-group-head">
                                <b>{{ $group['name'] }}</b>
                                <div class="j-badges">
                                    <span class="j-badge">{{ $group['submitted_count'] }} ta topshirgan</span>
                                    <span class="j-badge ok">{{ $group['passed_count'] }} ta o'tgan</span>
                                    <span class="j-badge">O'rtacha {{ $group['average_percent'] }}%</span>
                                </div>
                            </div>

                            <table class="j-table">
                                <thead>
                                <tr>
                                    <th style="width:30%">Talaba</th>
                                    <th style="width:12%">Holat</th>
                                    <th style="width:10%">Ball</th>
                                    <th style="width:10%">Foiz</th>
                                    <th style="width:14%">Topshirgan vaqti</th>
                                    <th>Javoblari</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($group['attempts'] as $attempt)
                                    <tr>
                                        <td>
                                            <span class="j-name">{{ $attempt->student_name }}</span>
                                            <span class="j-id">{{ $attempt->student_id_number }}</span>
                                        </td>
                                        <td>
                                            @if($attempt->status === 'in_progress')
                                                <span class="j-pill wait">Ishlamoqda</span>
                                            @else
                                                <span class="j-pill {{ $attempt->is_passed ? 'ok' : 'no' }}">
                                                    {{ $attempt->is_passed ? 'O\'tdi' : 'O\'tmadi' }}
                                                </span>
                                                @if($attempt->status === 'expired')
                                                    <span class="j-id">vaqt tugagan</span>
                                                @endif
                                            @endif
                                        </td>
                                        <td class="j-score">{{ (int) $attempt->score }} / {{ $attempt->total_points }}</td>
                                        <td class="j-score">{{ $attempt->percent !== null ? rtrim(rtrim(number_format((float) $attempt->percent, 1, '.', ''), '0'), '.') . '%' : '—' }}</td>
                                        <td>{{ $attempt->submitted_at?->format('d.m.Y H:i') ?? '—' }}</td>
                                        <td>
                                            @if($attempt->answers->isEmpty())
                                                <span class="j-id">javob yo'q</span>
                                            @else
                                                <details class="j-answers">
                                                    <summary>{{ $attempt->correct_count }} / {{ $attempt->questions_count }} to'g'ri</summary>
                                                    @foreach($attempt->answers as $answer)
                                                        <div class="j-answer">
                                                            <span class="j-mark {{ $answer->is_correct ? 'ok' : 'no' }}">{{ $answer->is_correct ? '✓' : '✕' }}</span>
                                                            <div class="j-answer-text">
                                                                {{ $answer->question_index + 1 }}. {{ \Illuminate\Support\Str::limit($answer->question_prompt, 90) }}
                                                                <br>
                                                                <i>Javobi:</i>
                                                                {{ $answer->question_type === 'fill_in_blank'
                                                                    ? ($answer->answer_text ?: '—')
                                                                    : ($answer->selected_option_text ?: '—') }}
                                                                @unless($answer->is_correct)
                                                                    · <i>to'g'risi:</i> <em>{{ $answer->correct_answer_text }}</em>
                                                                @endunless
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                </details>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @empty
                        <div class="j-group"><div class="j-empty">
                            <strong style="display:block;color:#5b7091;font-size:14px">Hali hech kim topshirmagan</strong>
                            Talabalar havola orqali testni topshirgach, natijalar shu yerda guruhlar bo'yicha chiqadi.
                        </div></div>
                    @endforelse
                @endif
            @endif
        </div>
    </div>
</x-app-layout>
