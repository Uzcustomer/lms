<x-app-layout>
<x-fan-testi-kit />

<div class="ft ft-page">
    <div class="ft-head">
        <div>
            <h1 class="ft-title">Test jurnali</h1>
            <p class="ft-sub">Har bir guruh qaysi test to'plamidan qanday natija ko'rsatganini ko'ring.</p>
        </div>
    </div>

    @if($migrationPending)
        <div class="ft-alert ft-alert-err">
            Test natijalari jadvali hali yaratilmagan. Serverda <code>php artisan migrate</code> ni ishga tushiring.
        </div>
    @elseif($collections->isEmpty())
        <div class="ft-empty">
            <h3>Hali test to'plami yo'q</h3>
            <p>Avval "Test yaratish" bo'limida to'plam tuzing.</p>
        </div>
    @else
        <form method="GET" class="ft-card ft-jr-filters">
            <div class="ft-field">
                <label for="test_id">Test to'plami</label>
                <select name="test_id" id="test_id">
                    @foreach($collections as $item)
                        <option value="{{ $item->id }}" @selected($selected && $item->id === $selected->id)>
                            {{ $item->name }} — {{ $item->subject?->subject_name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="ft-field">
                <label for="group">Guruh</label>
                <select name="group" id="group">
                    <option value="">Barcha guruhlar</option>
                    @foreach($allGroups ?? [] as $groupName)
                        <option value="{{ $groupName }}" @selected(request('group') === $groupName)>{{ $groupName }}</option>
                    @endforeach
                </select>
            </div>
            <button class="ft-btn ft-btn-primary" type="submit">Ko'rsatish</button>
        </form>

        @if($selected)
            <div class="ft-jr-stats">
                <div class="ft-jr-stat"><b>{{ $summary['total'] }}</b><span>Jami talaba</span></div>
                <div class="ft-jr-stat"><b>{{ $summary['submitted'] }}</b><span>Topshirgan</span></div>
                <div class="ft-jr-stat"><b>{{ $summary['in_progress'] }}</b><span>Ishlamoqda</span></div>
                <div class="ft-jr-stat is-ok"><b>{{ $summary['passed'] }}</b><span>O'tgan</span></div>
                <div class="ft-jr-stat"><b>{{ $summary['average_percent'] }}%</b><span>O'rtacha natija</span></div>
            </div>

            @php $kioskUrl = route('kiosk.fan-testi.show', $selected); @endphp
            <div class="ft-jr-link">
                <span class="ft-label" style="margin:0">Talaba havolasi</span>
                <code>{{ $kioskUrl }}</code>
                <button type="button" class="ft-btn ft-btn-soft ft-btn-sm" id="copyUrl" data-url="{{ $kioskUrl }}">Nusxalash</button>
                <span class="ft-hint">shu havolani sinf kompyuterlarida oching</span>
            </div>

            @forelse($groups as $group)
                <div class="ft-card">
                    <div class="ft-card-head">
                        <h2>{{ $group['name'] }}</h2>
                        <div class="ft-jr-tags">
                            <span class="ft-badge ft-badge-info">{{ $group['submitted_count'] }} topshirgan</span>
                            <span class="ft-badge ft-badge-ok">{{ $group['passed_count'] }} o'tgan</span>
                            <span class="ft-badge ft-badge-off">o'rtacha {{ $group['average_percent'] }}%</span>
                        </div>
                    </div>

                    <div class="ft-scroll-x">
                        <table class="ft-table">
                            <thead>
                                <tr>
                                    <th style="width:24%">Talaba</th>
                                    <th style="width:11%">Holat</th>
                                    <th style="width:9%">Ball</th>
                                    <th style="width:8%">Foiz</th>
                                    <th style="width:14%">Topshirgan vaqti</th>
                                    <th>Javoblari</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($group['attempts'] as $attempt)
                                    <tr>
                                        <td>
                                            <div class="ft-t-name">{{ $attempt->student_name }}</div>
                                            <div class="ft-t-sub">{{ $attempt->student_id_number }}</div>
                                        </td>
                                        <td>
                                            @if($attempt->status === 'in_progress')
                                                <span class="ft-badge ft-badge-off">Ishlamoqda</span>
                                            @else
                                                <span class="ft-badge {{ $attempt->is_passed ? 'ft-badge-ok' : 'ft-badge-bad' }}">
                                                    {{ $attempt->is_passed ? "O'tdi" : "O'tmadi" }}
                                                </span>
                                                @if($attempt->status === 'expired')
                                                    <div class="ft-t-sub">vaqt tugagan</div>
                                                @endif
                                            @endif
                                        </td>
                                        <td style="font-weight:700">{{ (int) $attempt->score }} / {{ $attempt->total_points }}</td>
                                        <td>
                                            <span style="font-weight:700; {{ !$attempt->is_passed && $attempt->status !== 'in_progress' ? 'color:var(--ft-danger)' : '' }}">
                                                {{ $attempt->percent !== null ? rtrim(rtrim(number_format((float) $attempt->percent, 1, '.', ''), '0'), '.') . '%' : '—' }}
                                            </span>
                                        </td>
                                        <td>{{ $attempt->submitted_at?->format('d.m.Y H:i') ?? '—' }}</td>
                                        <td>
                                            @if($attempt->answers->isEmpty())
                                                <span class="ft-t-sub">javob yo'q</span>
                                            @else
                                                <details class="ft-more">
                                                    <summary>{{ $attempt->correct_count }} / {{ $attempt->questions_count }} to'g'ri</summary>
                                                    <div class="ft-more-body ft-jr-answers">
                                                        @foreach($attempt->answers as $answer)
                                                            @php
                                                                $given = $answer->question_type === 'fill_in_blank'
                                                                    ? $answer->answer_text
                                                                    : $answer->selected_option_text;
                                                            @endphp
                                                            <div class="ft-jr-answer">
                                                                <span class="ft-jr-mark {{ $answer->is_correct ? 'is-ok' : 'is-bad' }}">{{ $answer->is_correct ? '✓' : '✕' }}</span>
                                                                <div>
                                                                    {{ $answer->question_index + 1 }}. {{ \Illuminate\Support\Str::limit($answer->question_prompt, 90) }}<br>
                                                                    <i>javobi:</i> {{ $given ?: '—' }}
                                                                    @unless($answer->is_correct)
                                                                        &nbsp;·&nbsp; <i>to'g'risi:</i> <em>{{ $answer->correct_answer_text }}</em>
                                                                    @endunless
                                                                </div>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                </details>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @empty
                <div class="ft-empty">
                    <h3>Hali hech kim topshirmagan</h3>
                    <p>Talabalar havola orqali testni topshirgach, natijalar shu yerda guruhlar bo'yicha chiqadi.</p>
                </div>
            @endforelse
        @endif
    @endif
</div>

<style>
    /* Jurnalga xos qo'shimchalar (kit ustida) */
    .ft-jr-filters { display: grid; grid-template-columns: 2fr 1fr auto; gap: 12px; align-items: end; padding: 14px 16px; }
    .ft-jr-stats { display: grid; grid-template-columns: repeat(5, 1fr); overflow: hidden; border: 1px solid var(--ft-line); border-radius: var(--ft-radius); background: var(--ft-surface); box-shadow: var(--ft-shadow); }
    .ft-jr-stat { padding: 12px 15px; border-right: 1px solid var(--ft-line-soft); }
    .ft-jr-stat:last-child { border-right: 0; }
    .ft-jr-stat b { display: block; font-size: 20px; font-weight: 800; line-height: 1.1; }
    .ft-jr-stat span { display: block; margin-top: 2px; color: var(--ft-ink-mute); font-size: 11px; }
    .ft-jr-stat.is-ok b { color: var(--ft-ok); }

    .ft-jr-link { display: flex; flex-wrap: wrap; align-items: center; gap: 9px; padding: 10px 14px; border: 1px solid var(--ft-line); border-radius: var(--ft-radius); background: var(--ft-surface); }
    .ft-jr-link code { padding: 4px 9px; border-radius: 6px; background: #f1f5fb; color: var(--ft-brand-dark); font-size: 12px; }
    .ft-jr-link .ft-hint { margin: 0; }

    .ft-jr-tags { display: inline-flex; flex-wrap: wrap; gap: 6px; margin-left: auto; }

    .ft-jr-answers { gap: 7px; font-size: 12px; }
    .ft-jr-answer { display: grid; grid-template-columns: 18px minmax(0, 1fr); gap: 7px; align-items: start; }
    .ft-jr-answer i { color: var(--ft-ink-mute); font-style: normal; }
    .ft-jr-answer em { color: var(--ft-ok); font-style: normal; font-weight: 600; }
    .ft-jr-mark { font-weight: 800; }
    .ft-jr-mark.is-ok { color: var(--ft-ok); }
    .ft-jr-mark.is-bad { color: var(--ft-danger); }

    .ft-badge-bad { background: var(--ft-danger-soft); color: #b91c1c; }

    @media (max-width: 900px) {
        .ft-jr-filters { grid-template-columns: 1fr; }
        .ft-jr-stats { grid-template-columns: repeat(2, 1fr); }
        .ft-jr-stat { border-bottom: 1px solid var(--ft-line-soft); }
        .ft-jr-tags { margin-left: 0; }
    }
</style>

<script>
(() => {
    const button = document.getElementById('copyUrl');
    if (!button) return;
    button.addEventListener('click', async () => {
        const url = button.dataset.url;
        try {
            await navigator.clipboard.writeText(url);
        } catch (error) {
            const field = document.createElement('textarea');
            field.value = url;
            document.body.appendChild(field);
            field.select();
            document.execCommand('copy');
            field.remove();
        }
        button.textContent = 'Nusxalandi';
        setTimeout(() => { button.textContent = 'Nusxalash'; }, 1600);
    });
})();
</script>
</x-app-layout>
