<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            Test bahosi apelyatsiyalari — tarix
        </h2>
    </x-slot>

    <style>
        .qa-wrap { padding: 16px; }
        .qa-card { background:#fff; border:1px solid #e2e8f0; border-radius:12px; box-shadow:0 1px 3px rgba(0,0,0,0.05); overflow:hidden; }
        .qa-note { margin:14px 16px; padding:10px 14px; background:#eff6ff; border:1px solid #bfdbfe; border-radius:8px; font-size:12.5px; color:#1e3a8a; line-height:1.6; }
        .qa-table-wrap { overflow-x:auto; }
        table.qa-table { width:100%; border-collapse:separate; border-spacing:0; font-size:13px; }
        .qa-table thead th { padding:10px 12px; text-align:left; background:linear-gradient(135deg,#e0e7ff,#c7d2fe); font-weight:700; font-size:11px; color:#334155; text-transform:uppercase; letter-spacing:.04em; border-bottom:2px solid #a5b4fc; white-space:nowrap; }
        .qa-table tbody tr { border-bottom:1px solid #f1f5f9; }
        .qa-table tbody tr:hover { background:#eff6ff; }
        .qa-table td { padding:9px 12px; vertical-align:middle; }
        .qa-badge { display:inline-block; padding:2px 8px; border-radius:6px; font-size:11px; font-weight:700; }
        .qa-badge-replace { background:#dbeafe; color:#1e40af; border:1px solid #93c5fd; }
        .qa-badge-delete { background:#fee2e2; color:#991b1b; border:1px solid #fca5a5; }
        .qa-old { color:#991b1b; text-decoration:line-through; }
        .qa-new { color:#166534; font-weight:700; }
        .qa-doc { color:#1d4ed8; text-decoration:none; font-weight:600; }
        .qa-doc:hover { text-decoration:underline; }
        .qa-empty { padding:50px 20px; text-align:center; color:#64748b; }
    </style>

    <div class="qa-wrap">
        <div class="qa-card">
            <div class="qa-note">
                Bu yerda sistemaga adashib yuklangan test natijalari bo'yicha o'quv prorektori tomonidan
                qilingan tuzatishlar (baho almashtirish / o'chirish) va ularning asoslovchi hujjatlari saqlanadi.
                Tuzatish "Sistemaga yuklangan natijalar" sahifasidagi <strong>Apelyatsiya</strong> tugmasi orqali amalga oshiriladi.
            </div>

            <div class="qa-table-wrap">
                <table class="qa-table">
                    <thead>
                        <tr>
                            <th>Sana</th>
                            <th>Talaba</th>
                            <th>Fan</th>
                            <th>Amal</th>
                            <th>Eski &rarr; Yangi</th>
                            <th>Sabab</th>
                            <th>Hujjat</th>
                            <th>Kim</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse(($appeals ?? []) as $a)
                            <tr>
                                <td style="white-space:nowrap;">{{ $a->created_at?->format('d.m.Y H:i') }}</td>
                                <td>
                                    <div style="font-weight:700;color:#0f172a;">{{ $a->student_name ?: '-' }}</div>
                                    <div style="font-size:11px;color:#64748b;">{{ $a->student_hemis_id }}</div>
                                </td>
                                <td>{{ $a->subject_name ?: ('#' . $a->subject_id) }}</td>
                                <td>
                                    @if($a->action === 'delete')
                                        <span class="qa-badge qa-badge-delete">O'chirildi</span>
                                    @else
                                        <span class="qa-badge qa-badge-replace">Almashtirildi</span>
                                    @endif
                                </td>
                                <td style="white-space:nowrap;">
                                    <span class="qa-old">{{ $a->old_grade !== null ? rtrim(rtrim(number_format($a->old_grade,2,'.',''),'0'),'.') : '-' }}</span>
                                    @if($a->action !== 'delete')
                                        &rarr; <span class="qa-new">{{ $a->new_grade !== null ? rtrim(rtrim(number_format($a->new_grade,2,'.',''),'0'),'.') : '-' }}</span>
                                    @endif
                                </td>
                                <td style="max-width:280px;">{{ $a->reason }}</td>
                                <td>
                                    @if($a->document_path)
                                        <a class="qa-doc" target="_blank"
                                           href="{{ route($routePrefix . '.quiz-grade-appeals.download', $a->id) }}">
                                            📎 {{ \Illuminate\Support\Str::limit($a->document_original_name ?: 'hujjat', 24) }}
                                        </a>
                                    @else
                                        <span style="color:#94a3b8;">—</span>
                                    @endif
                                </td>
                                <td>
                                    <div>{{ $a->performed_by_name ?: '-' }}</div>
                                    <div style="font-size:11px;color:#64748b;">{{ $a->performed_by_role }}</div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="qa-empty">Hozircha apelyatsiya yo'q.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($appeals && $appeals->hasPages())
                <div style="padding:12px 16px;">{{ $appeals->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>
