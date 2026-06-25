<x-app-layout>
    <div class="py-6">
        <div class="w-full px-4 sm:px-6 lg:px-8 space-y-6">
            <style>
                .tr-card {
                    background:#fff;
                    border:1px solid #dbe4ef;
                    border-radius:22px;
                    box-shadow:0 10px 28px rgba(15,23,42,.06);
                    overflow:hidden;
                }
                .tr-soft {
                    background:linear-gradient(135deg,#f8fbff 0%,#eef4ff 45%,#f7fbff 100%);
                }
                .tr-head {
                    padding:16px 20px;
                    border-bottom:1px solid #e8edf5;
                    background:linear-gradient(135deg,#f4f8fc,#e7eef8);
                }
                .tr-section { padding:18px 20px; }
                .tr-chip {
                    display:inline-flex;
                    align-items:center;
                    padding:6px 12px;
                    border-radius:999px;
                    font-size:12px;
                    font-weight:800;
                    border:1px solid transparent;
                }
                .tr-chip.blue { background:#eff6ff; color:#1d4ed8; border-color:#bfdbfe; }
                .tr-chip.green { background:#ecfdf5; color:#15803d; border-color:#bbf7d0; }
                .tr-chip.orange { background:#fff7ed; color:#c2410c; border-color:#fdba74; }
                .tr-chip.gray { background:#f8fafc; color:#475569; border-color:#cbd5e1; }
                .tr-stat-grid {
                    display:grid;
                    grid-template-columns:repeat(6,minmax(0,1fr));
                    gap:12px;
                }
                .tr-stat {
                    min-width:0;
                    border:1px solid #dbe4ef;
                    border-radius:18px;
                    padding:16px 18px;
                    background:#fff;
                }
                .tr-stat.blue { background:linear-gradient(135deg,#eff6ff,#dbeafe); }
                .tr-stat.green { background:linear-gradient(135deg,#ecfdf5,#d1fae5); }
                .tr-stat.orange { background:linear-gradient(135deg,#fff7ed,#ffedd5); }
                .tr-stat.gray { background:linear-gradient(135deg,#f8fafc,#f1f5f9); }
                .tr-label {
                    font-size:11px;
                    font-weight:800;
                    letter-spacing:.08em;
                    text-transform:uppercase;
                    color:#64748b;
                }
                .tr-value {
                    margin-top:8px;
                    font-size:28px;
                    line-height:1.1;
                    font-weight:800;
                    color:#0f172a;
                }
                .tr-btn {
                    display:inline-flex;
                    align-items:center;
                    justify-content:center;
                    gap:8px;
                    border-radius:12px;
                    padding:10px 16px;
                    font-size:13px;
                    font-weight:800;
                    transition:all .18s ease;
                }
                .tr-btn:hover { transform:translateY(-1px); }
                .tr-btn-light { background:#fff; color:#334155; border:1px solid #cbd5e1; }
                .tr-table-wrap { overflow:auto; }
                .tr-table { min-width:100%; width:100%; border-collapse:separate; border-spacing:0; }
                .tr-table thead tr { background:linear-gradient(135deg,#e8edf5,#dbe4ef,#d1d9e6); }
                .tr-table th, .tr-table td { padding:14px 16px; vertical-align:top; }
                .tr-table th {
                    font-size:11px;
                    letter-spacing:.05em;
                    text-transform:uppercase;
                    color:#475569;
                    font-weight:800;
                    white-space:nowrap;
                }
                .tr-table tbody tr:hover { background:#f8fbff; }
                .tr-status {
                    display:inline-flex;
                    align-items:center;
                    padding:6px 12px;
                    border-radius:999px;
                    font-size:11px;
                    font-weight:800;
                    border:1px solid transparent;
                }
                .tr-status.green { background:#ecfdf5; color:#059669; border-color:#a7f3d0; }
                .tr-status.red { background:#fef2f2; color:#dc2626; border-color:#fecaca; }
                .tr-status.orange { background:#fff7ed; color:#ea580c; border-color:#fdba74; }
                .tr-status.gray { background:#f8fafc; color:#475569; border-color:#cbd5e1; }
                .tr-question-grid {
                    display:grid;
                    grid-template-columns:repeat(3,minmax(0,1fr));
                    gap:12px;
                }
                .tr-question-box {
                    border:1px solid #dbe4ef;
                    border-radius:16px;
                    padding:14px;
                    background:#fff;
                }
                .tr-answer-list {
                    display:grid;
                    grid-template-columns:repeat(2,minmax(0,1fr));
                    gap:12px;
                }
                .tr-answer-item {
                    border:1px solid #dbe4ef;
                    border-radius:14px;
                    padding:12px;
                    background:#fff;
                }
                .tr-answer-item.correct { border-color:#86efac; background:#f0fdf4; }
                .tr-answer-item.incorrect { border-color:#fecaca; background:#fef2f2; }
                .tr-answer-item.empty { border-color:#e2e8f0; background:#f8fafc; }
                .tr-modal-backdrop {
                    position:fixed;
                    inset:0;
                    background:rgba(15,23,42,.55);
                    backdrop-filter:blur(6px);
                    display:none;
                    align-items:center;
                    justify-content:center;
                    padding:20px;
                    z-index:60;
                }
                .tr-modal-backdrop.is-open { display:flex; }
                .tr-modal {
                    width:min(1120px, 100%);
                    max-height:calc(100vh - 40px);
                    border-radius:24px;
                    overflow:hidden;
                    background:#fff;
                    border:1px solid #dbe4ef;
                    box-shadow:0 24px 70px rgba(15,23,42,.22);
                    display:flex;
                    flex-direction:column;
                }
                .tr-modal-head {
                    padding:16px 20px;
                    border-bottom:1px solid #e8edf5;
                    background:linear-gradient(135deg,#f4f8fc,#e7eef8);
                    display:flex;
                    align-items:flex-start;
                    justify-content:space-between;
                    gap:16px;
                }
                .tr-modal-body {
                    padding:18px 20px;
                    overflow:auto;
                }
                .tr-modal-close {
                    width:40px;
                    height:40px;
                    border-radius:12px;
                    border:1px solid #cbd5e1;
                    background:#fff;
                    color:#334155;
                    font-size:20px;
                    font-weight:700;
                    line-height:1;
                    display:inline-flex;
                    align-items:center;
                    justify-content:center;
                }
                .tr-summary-grid {
                    display:grid;
                    grid-template-columns:repeat(3,minmax(0,1fr));
                    gap:12px;
                    margin-bottom:16px;
                }
                .tr-summary-box {
                    border:1px solid #dbe4ef;
                    border-radius:16px;
                    background:#f8fafc;
                    padding:12px 14px;
                }
                body.tr-modal-open { overflow:hidden; }
                @media (max-width: 1200px) {
                    .tr-stat-grid { grid-template-columns:repeat(3,minmax(0,1fr)); }
                    .tr-question-grid { grid-template-columns:repeat(2,minmax(0,1fr)); }
                }
                @media (max-width: 768px) {
                    .tr-section, .tr-head { padding:14px; }
                    .tr-stat-grid { grid-template-columns:repeat(2,minmax(0,1fr)); }
                    .tr-summary-grid,
                    .tr-question-grid,
                    .tr-answer-list { grid-template-columns:1fr; }
                    .tr-value { font-size:22px; }
                    .tr-modal-backdrop { padding:10px; }
                    .tr-modal-head,
                    .tr-modal-body { padding:14px; }
                }
            </style>

            <div>
                <a href="{{ route('teacher.test-subjects.show', $testSubject) }}" class="tr-btn tr-btn-light">Orqaga</a>
            </div>

            <div class="tr-card tr-soft">
                <div class="tr-section space-y-4">
                    <div class="flex flex-wrap gap-2">
                        <span class="tr-chip blue">{{ $testSubject->name }}</span>
                        <span class="tr-chip green">{{ $lesson->topic_order }}-mavzu</span>
                        <span class="tr-chip orange">{{ optional($lesson->lesson_date)->format('d.m.Y') ?: '-' }}</span>
                        <span class="tr-chip gray">{{ $test->title }}</span>
                    </div>

                    <div>
                        <h1 class="text-3xl font-extrabold text-slate-900">Test natijalari</h1>
                        <p class="mt-2 text-sm text-slate-600">
                            Qaysi talaba testni bajargani, kim bajarmagani va har bir savol bo'yicha to'g'ri yoki noto'g'ri javoblar shu yerda ko'rinadi.
                        </p>
                    </div>

                    <div class="tr-stat-grid">
                        <div class="tr-stat blue">
                            <div class="tr-label">Jami talaba</div>
                            <div class="tr-value">{{ $summary['total_students'] }}</div>
                        </div>
                        <div class="tr-stat green">
                            <div class="tr-label">Bajargan</div>
                            <div class="tr-value">{{ $summary['submitted_count'] }}</div>
                        </div>
                        <div class="tr-stat orange">
                            <div class="tr-label">Bajarmagan</div>
                            <div class="tr-value">{{ $summary['not_submitted_count'] }}</div>
                        </div>
                        <div class="tr-stat green">
                            <div class="tr-label">O'tgan</div>
                            <div class="tr-value">{{ $summary['passed_count'] }}</div>
                        </div>
                        <div class="tr-stat orange">
                            <div class="tr-label">Yiqilgan</div>
                            <div class="tr-value">{{ $summary['failed_count'] }}</div>
                        </div>
                        <div class="tr-stat gray">
                            <div class="tr-label">O'rtacha foiz</div>
                            <div class="tr-value">{{ number_format($summary['average_percent'], 2) }}%</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="tr-card">
                <div class="tr-head">
                    <h2 class="text-lg font-bold text-slate-900">Talabalar natijalari</h2>
                    <p class="mt-1 text-sm text-slate-500">Qatorni ochib har bir talabaga qaysi savolda nima javob berganini ko'rishingiz mumkin.</p>
                </div>
                <div class="tr-section">
                    <div class="tr-table-wrap">
                        <table class="tr-table text-sm">
                            <thead>
                            <tr>
                                <th class="text-left">#</th>
                                <th class="text-left">Talaba</th>
                                <th class="text-left">Guruh</th>
                                <th class="text-left">Topshirgan vaqti</th>
                                <th class="text-left">Ball</th>
                                <th class="text-left">Foiz</th>
                                <th class="text-left">Holat</th>
                                <th class="text-left">Batafsil</th>
                            </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                            @forelse($studentRows as $row)
                                @php
                                    $attempt = $row['attempt'];
                                    $submitted = $attempt && $attempt->status === 'submitted';
                                @endphp
                                <tr>
                                    <td class="font-bold text-slate-900">{{ $row['row_no'] }}</td>
                                    <td>
                                        <div class="font-semibold text-slate-900">{{ $row['student']->full_name }}</div>
                                        <div class="mt-1 text-xs text-slate-500">ID: {{ $row['student']->student_id_number ?: $row['student']->hemis_id }}</div>
                                    </td>
                                    <td class="text-slate-700">{{ $row['student']->group_name ?: '-' }}</td>
                                    <td class="text-slate-700">
                                        {{ $submitted ? optional($attempt->submitted_at)->format('d.m.Y H:i') : '-' }}
                                    </td>
                                    <td class="font-semibold text-slate-900">
                                        {{ $submitted ? (rtrim(rtrim((string) $attempt->score, '0'), '.') . ' / ' . $attempt->total_points) : '-' }}
                                    </td>
                                    <td class="font-semibold text-slate-900">{{ $submitted ? ($attempt->percent . '%') : '-' }}</td>
                                    <td>
                                        @if(!$attempt)
                                            <span class="tr-status gray">Kirmagan</span>
                                        @elseif(!$submitted)
                                            <span class="tr-status orange">Yakunlamagan</span>
                                        @elseif($attempt->is_passed)
                                            <span class="tr-status green">O'tgan</span>
                                        @else
                                            <span class="tr-status red">Yiqilgan</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($attempt)
                                            @php
                                                $modalPayload = [
                                                    'student_name' => $row['student']->full_name,
                                                    'group_name' => $row['student']->group_name ?: '-',
                                                    'score' => $submitted ? (rtrim(rtrim((string) $attempt->score, '0'), '.') . ' / ' . $attempt->total_points) : '-',
                                                    'percent' => $submitted ? ($attempt->percent . '%') : '-',
                                                    'submitted_at' => $submitted ? optional($attempt->submitted_at)->format('d.m.Y H:i') : '-',
                                                    'status' => !$attempt ? 'Kirmagan' : (!$submitted ? 'Yakunlamagan' : ($attempt->is_passed ? "O'tgan" : 'Yiqilgan')),
                                                    'question_details' => $row['question_details'],
                                                ];
                                            @endphp
                                            <button
                                                type="button"
                                                class="tr-btn tr-btn-light js-open-result-modal"
                                                data-modal-json-id="result-modal-json-{{ $row['student']->id }}"
                                            >
                                                Javoblarni ko'rish
                                            </button>
                                            <script type="application/json" id="result-modal-json-{{ $row['student']->id }}">
                                                @json($modalPayload)
                                            </script>
                                        @else
                                            <span class="text-slate-400">-</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="py-10 text-center text-slate-500">Bu test uchun talabalar topilmadi.</td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="result-modal-backdrop" class="tr-modal-backdrop">
        <div class="tr-modal">
            <div class="tr-modal-head">
                <div class="min-w-0">
                    <div id="result-modal-student" class="text-xl font-extrabold text-slate-900"></div>
                    <div id="result-modal-meta" class="mt-1 text-sm text-slate-500"></div>
                </div>
                <button type="button" class="tr-modal-close" id="result-modal-close">×</button>
            </div>
            <div class="tr-modal-body">
                <div class="tr-summary-grid">
                    <div class="tr-summary-box">
                        <div class="tr-label">Guruh</div>
                        <div id="result-modal-group" class="mt-2 text-base font-bold text-slate-900"></div>
                    </div>
                    <div class="tr-summary-box">
                        <div class="tr-label">Ball</div>
                        <div id="result-modal-score" class="mt-2 text-base font-bold text-slate-900"></div>
                    </div>
                    <div class="tr-summary-box">
                        <div class="tr-label">Foiz / Holat</div>
                        <div id="result-modal-status" class="mt-2 text-base font-bold text-slate-900"></div>
                    </div>
                </div>
                <div id="result-modal-answers" class="tr-answer-list"></div>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const backdrop = document.getElementById('result-modal-backdrop');
            const closeBtn = document.getElementById('result-modal-close');
            const studentEl = document.getElementById('result-modal-student');
            const metaEl = document.getElementById('result-modal-meta');
            const groupEl = document.getElementById('result-modal-group');
            const scoreEl = document.getElementById('result-modal-score');
            const statusEl = document.getElementById('result-modal-status');
            const answersEl = document.getElementById('result-modal-answers');

            if (!backdrop || !closeBtn || !studentEl || !metaEl || !groupEl || !scoreEl || !statusEl || !answersEl) {
                return;
            }

            const escapeHtml = (value) => String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');

            const closeModal = () => {
                backdrop.classList.remove('is-open');
                document.body.classList.remove('tr-modal-open');
                answersEl.innerHTML = '';
            };

            const renderAnswerCard = (detail) => {
                const boxClass = !detail.is_answered
                    ? 'empty'
                    : (detail.is_correct ? 'correct' : 'incorrect');

                let statusBadge = '<span class="tr-status gray">Javobsiz</span>';
                if (detail.is_answered && detail.is_correct) {
                    statusBadge = "<span class=\"tr-status green\">To'g'ri</span>";
                } else if (detail.is_answered) {
                    statusBadge = "<span class=\"tr-status red\">Noto'g'ri</span>";
                }

                let answerBlock = '';
                if (detail.type === 'single_choice') {
                    answerBlock = `
                        <div><span class="font-semibold">Tanlagan:</span> ${escapeHtml(detail.selected_option || '-')}</div>
                        <div><span class="font-semibold">To'g'ri javob:</span> ${escapeHtml(detail.correct_option || '-')}</div>
                    `;
                } else {
                    answerBlock = `
                        <div><span class="font-semibold">Yozgan javob:</span> ${escapeHtml(detail.answer_text || '-')}</div>
                        <div><span class="font-semibold">To'g'ri javob:</span> ${escapeHtml(detail.correct_answer_text || '-')}</div>
                    `;
                }

                return `
                    <div class="tr-answer-item ${boxClass}">
                        <div class="flex items-center justify-between gap-3">
                            <div class="text-sm font-extrabold text-slate-900">Savol ${escapeHtml(detail.question_no)}</div>
                            ${statusBadge}
                        </div>
                        <div class="mt-2 text-sm font-medium text-slate-700">${escapeHtml(detail.prompt)}</div>
                        <div class="mt-3 space-y-2 text-sm text-slate-700">
                            ${answerBlock}
                            <div><span class="font-semibold">Ball:</span> ${escapeHtml(detail.points_earned)} / ${escapeHtml(detail.points)}</div>
                        </div>
                    </div>
                `;
            };

            document.querySelectorAll('.js-open-result-modal').forEach((button) => {
                button.addEventListener('click', () => {
                    const scriptId = button.getAttribute('data-modal-json-id');
                    const payloadScript = scriptId ? document.getElementById(scriptId) : null;
                    if (!payloadScript) {
                        return;
                    }

                    let payload = null;
                    try {
                        payload = JSON.parse(payloadScript.textContent);
                    } catch (e) {
                        return;
                    }

                    studentEl.textContent = payload.student_name || '';
                    metaEl.textContent = `Topshirgan vaqti: ${payload.submitted_at || '-'}`;
                    groupEl.textContent = payload.group_name || '-';
                    scoreEl.textContent = payload.score || '-';
                    statusEl.textContent = `${payload.percent || '-'} | ${payload.status || '-'}`;
                    answersEl.innerHTML = (payload.question_details || []).map(renderAnswerCard).join('');

                    backdrop.classList.add('is-open');
                    document.body.classList.add('tr-modal-open');
                });
            });

            closeBtn.addEventListener('click', closeModal);
            backdrop.addEventListener('click', (event) => {
                if (event.target === backdrop) {
                    closeModal();
                }
            });
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && backdrop.classList.contains('is-open')) {
                    closeModal();
                }
            });
        })();
    </script>
</x-app-layout>
