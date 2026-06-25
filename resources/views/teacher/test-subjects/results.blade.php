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
                .tr-details {
                    border:1px solid #dbe4ef;
                    border-radius:16px;
                    background:#fff;
                    overflow:hidden;
                }
                .tr-details summary {
                    list-style:none;
                    cursor:pointer;
                    padding:12px 14px;
                    font-size:13px;
                    font-weight:800;
                    color:#334155;
                    background:#f8fafc;
                    border-bottom:1px solid #e2e8f0;
                }
                .tr-details summary::-webkit-details-marker { display:none; }
                .tr-answer-list {
                    display:grid;
                    grid-template-columns:repeat(2,minmax(0,1fr));
                    gap:12px;
                    padding:14px;
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
                @media (max-width: 1200px) {
                    .tr-stat-grid { grid-template-columns:repeat(3,minmax(0,1fr)); }
                    .tr-question-grid { grid-template-columns:repeat(2,minmax(0,1fr)); }
                }
                @media (max-width: 768px) {
                    .tr-section, .tr-head { padding:14px; }
                    .tr-stat-grid { grid-template-columns:repeat(2,minmax(0,1fr)); }
                    .tr-question-grid,
                    .tr-answer-list { grid-template-columns:1fr; }
                    .tr-value { font-size:22px; }
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
                    <h2 class="text-lg font-bold text-slate-900">Savollar kesimida tahlil</h2>
                    <p class="mt-1 text-sm text-slate-500">Har bir savol bo'yicha nechta talaba to'g'ri, noto'g'ri yoki javobsiz qoldirganini ko'ring.</p>
                </div>
                <div class="tr-section">
                    <div class="tr-question-grid">
                        @foreach($questionSummaries as $question)
                            <div class="tr-question-box">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="text-sm font-extrabold text-slate-900">Savol {{ $question['question_no'] }}</div>
                                    <span class="tr-status {{ $question['type'] === 'single_choice' ? 'green' : 'orange' }}">
                                        {{ $question['type'] === 'single_choice' ? 'Multiple choice' : 'Fill in blank' }}
                                    </span>
                                </div>
                                <div class="mt-2 text-sm font-medium text-slate-700">{{ $question['prompt'] }}</div>
                                <div class="mt-4 grid grid-cols-3 gap-2 text-center">
                                    <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-2 py-3">
                                        <div class="text-[11px] font-bold uppercase text-emerald-700">To'g'ri</div>
                                        <div class="mt-1 text-xl font-extrabold text-emerald-700">{{ $question['correct_count'] }}</div>
                                    </div>
                                    <div class="rounded-xl border border-red-200 bg-red-50 px-2 py-3">
                                        <div class="text-[11px] font-bold uppercase text-red-700">Noto'g'ri</div>
                                        <div class="mt-1 text-xl font-extrabold text-red-700">{{ $question['incorrect_count'] }}</div>
                                    </div>
                                    <div class="rounded-xl border border-slate-200 bg-slate-50 px-2 py-3">
                                        <div class="text-[11px] font-bold uppercase text-slate-600">Javobsiz</div>
                                        <div class="mt-1 text-xl font-extrabold text-slate-700">{{ $question['unanswered_count'] }}</div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
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
                                            <details class="tr-details">
                                                <summary>Javoblarni ko'rish</summary>
                                                <div class="tr-answer-list">
                                                    @foreach($row['question_details'] as $detail)
                                                        @php
                                                            $boxClass = !$submitted || !$detail['is_answered']
                                                                ? 'empty'
                                                                : ($detail['is_correct'] ? 'correct' : 'incorrect');
                                                        @endphp
                                                        <div class="tr-answer-item {{ $boxClass }}">
                                                            <div class="flex items-center justify-between gap-3">
                                                                <div class="text-sm font-extrabold text-slate-900">Savol {{ $detail['question_no'] }}</div>
                                                                @if(!$submitted || !$detail['is_answered'])
                                                                    <span class="tr-status gray">Javobsiz</span>
                                                                @elseif($detail['is_correct'])
                                                                    <span class="tr-status green">To'g'ri</span>
                                                                @else
                                                                    <span class="tr-status red">Noto'g'ri</span>
                                                                @endif
                                                            </div>
                                                            <div class="mt-2 text-sm font-medium text-slate-700">{{ $detail['prompt'] }}</div>
                                                            <div class="mt-3 space-y-2 text-sm text-slate-700">
                                                                @if($detail['type'] === 'single_choice')
                                                                    <div><span class="font-semibold">Tanlagan:</span> {{ $detail['selected_option'] ?: '-' }}</div>
                                                                    <div><span class="font-semibold">To'g'ri javob:</span> {{ $detail['correct_option'] ?: '-' }}</div>
                                                                @else
                                                                    <div><span class="font-semibold">Yozgan javob:</span> {{ $detail['answer_text'] ?: '-' }}</div>
                                                                    <div><span class="font-semibold">To'g'ri javob:</span> {{ $detail['correct_answer_text'] ?: '-' }}</div>
                                                                @endif
                                                                <div><span class="font-semibold">Ball:</span> {{ $detail['points_earned'] }} / {{ $detail['points'] }}</div>
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </details>
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
</x-app-layout>
