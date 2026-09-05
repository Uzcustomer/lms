<x-app-layout>
    @php
        $departmentRoles = ['oquv_bolimi', 'oquv_bolimi_boshligi'];
        $isDepartmentReviewer = in_array($activeRole ?? '', $departmentRoles, true);
        $isViceRector = ($activeRole ?? '') === 'oquv_prorektori';
        $isRegistrar = in_array($activeRole ?? '', ['superadmin', 'admin', 'registrator_ofisi'], true);
        $showReviewColumn = $isDepartmentReviewer || $isViceRector;
    @endphp
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">Akademik mobillik arizalari</h2>
    </x-slot>

    <div class="py-4">
        <div class="max-w-full mx-auto sm:px-4 lg:px-6">
            @if(session('success'))
                <div class="am-alert">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    {{ session('success') }}
                </div>
            @endif

            @if($errors->any())
                <div class="am-alert am-alert-error">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 4h.01M10.3 4.3L2.9 17.1A2 2 0 004.6 20h14.8a2 2 0 001.7-2.9L13.7 4.3a2 2 0 00-3.4 0z"/>
                    </svg>
                    {{ $errors->first() }}
                </div>
            @endif

            <section class="am-overview">
                <header class="am-hero">
                    <div class="am-hero-title">
                        <span class="am-hero-icon">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6M7 3h7l5 5v11a2 2 0 01-2 2H7a2 2 0 01-2-2V5a2 2 0 012-2z"/>
                            </svg>
                        </span>
                        <div>
                            <h1>Akademik mobillik arizalari</h1>
                            <p>Yuborilgan arizalar va biriktirilgan hujjatlar</p>
                        </div>
                    </div>
                    @if($isRegistrar)
                        <a href="{{ route('admin.academic-mobility.index') }}" class="am-back-btn">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7 7-7m-7 7h18"/>
                            </svg>
                            Talabalar ro'yxati
                        </a>
                    @endif
                </header>

                <div class="am-stats">
                    <article class="am-stat am-stat-blue">
                        <span class="am-stat-icon">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6M7 3h7l5 5v11a2 2 0 01-2 2H7a2 2 0 01-2-2V5a2 2 0 012-2z"/>
                            </svg>
                        </span>
                        <div><span>Jami arizalar</span><strong>{{ $stats['total'] }}</strong></div>
                    </article>
                    <article class="am-stat am-stat-amber">
                        <span class="am-stat-icon">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 2m6-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </span>
                        <div><span>Yangi</span><strong>{{ $stats['pending'] }}</strong></div>
                    </article>
                    <article class="am-stat am-stat-green">
                        <span class="am-stat-icon">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828L18 9.828a4 4 0 00-5.657-5.657L5.757 10.757a6 6 0 108.486 8.486L20 13.486"/>
                            </svg>
                        </span>
                        <div><span>Hujjatli</span><strong>{{ $stats['withDocument'] }}</strong></div>
                    </article>
                    <article class="am-stat am-stat-violet">
                        <span class="am-stat-icon">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 012-2h10a2 2 0 012 2v12a2 2 0 01-2 2z"/>
                            </svg>
                        </span>
                        <div><span>To'liq tasdiqlangan</span><strong>{{ $stats['fullyApproved'] }}</strong></div>
                    </article>
                </div>
            </section>

            <section class="am-list-card">
                <form method="GET" action="{{ route('admin.academic-mobility.applications') }}" class="am-search-panel">
                    <div class="am-search-heading">
                        <span>Talaba qidirish</span>
                        <small>Ism yoki familiyani kiriting</small>
                    </div>
                    <div class="am-search-controls">
                        <div class="am-search-input">
                            <span>
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                </svg>
                            </span>
                            <input name="search" value="{{ request('search') }}" placeholder="Talabaning ism-familiyasi...">
                        </div>
                        <button type="submit" class="am-search-btn">Qidirish</button>
                        @if(request()->filled('search'))
                            <a href="{{ route('admin.academic-mobility.applications') }}" class="am-clear-btn">Tozalash</a>
                        @endif
                    </div>
                </form>

                <div class="am-result-bar">
                    <span>Topildi: <strong>{{ $applications->total() }}</strong> ta ariza</span>
                </div>

                <div class="am-table-scroll">
                    <table class="am-table">
                        <thead>
                            <tr>
                                <th class="am-number-heading">№</th>
                                <th>Talaba</th>
                                <th>O'qish ma'lumoti</th>
                                <th>Telefon</th>
                                <th class="am-destination-heading">Mobillik bo'layotgan joy</th>
                                <th>Hujjat</th>
                                @if($showReviewColumn)
                                    <th class="am-review-heading">O'quv reja mosligi</th>
                                @endif
                                <th class="{{ $showReviewColumn ? 'am-status-heading' : '' }}">Holat</th>
                                @if($isRegistrar)
                                    <th class="am-actions-heading">Amal</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($applications as $application)
                                @php
                                    $statusStyles = [
                                        'pending' => ['Yangi', 'am-status-pending'],
                                        'approved' => ['Qabul qilingan', 'am-status-approved'],
                                        'rejected' => ['Rad etilgan', 'am-status-rejected'],
                                    ];
                                    [$statusLabel, $statusClass] = $statusStyles[$application->status] ?? [$application->status, 'am-status-default'];
                                    $approvals = $application->approvals->keyBy('role');
                                    $departmentApproval = $approvals->get('oquv_bolimi');
                                    $viceRectorApproval = $approvals->get('oquv_prorektori');
                                     $departmentApproved = $departmentApproval?->status === 'approved';
                                     $viceRectorEnabled = $departmentApproved && (bool) $application->curriculum_document_path;
                                     $rejectedApprovals = collect([$departmentApproval, $viceRectorApproval])
                                         ->filter(fn ($approval) => $approval?->status === 'rejected' && filled($approval?->rejection_comment));
                                 @endphp
                                <tr>
                                    <td class="am-number-cell">{{ ($applications->firstItem() ?? 1) + $loop->index }}</td>
                                    <td>
                                        <div class="am-student-name">{{ $application->student?->full_name ?? 'Talaba topilmadi' }}</div>
                                        <div class="am-muted">HEMIS: {{ $application->student?->hemis_id ?? '-' }}</div>
                                        <div class="am-muted">ID: {{ $application->student?->student_id_number ?? '-' }}</div>
                                    </td>
                                    <td>
                                        <div class="am-faculty">{{ $application->student?->department_name ?? '-' }}</div>
                                        <div class="am-specialty">{{ $application->student?->specialty_name ?? '-' }}</div>
                                        <div class="am-study-badges">
                                            <span class="am-course">{{ $application->student?->level_name ?? '-' }}</span>
                                            <span class="am-group">{{ $application->student?->group_name ?? '-' }}</span>
                                        </div>
                                    </td>
                                    <td class="am-phone">{{ $application->phone }}</td>
                                    <td class="am-destination-cell">
                                        @if($isRegistrar)
                                            <form method="POST" action="{{ route('admin.academic-mobility.transfer-destination.update', $application) }}" class="am-destination-form">
                                                @csrf
                                                @method('PATCH')
                                                <input
                                                    type="text"
                                                    name="transfer_destination"
                                                    value="{{ $application->transfer_destination }}"
                                                    maxlength="1000"
                                                    placeholder="Talaba qayerga o'tmoqda..."
                                                    aria-label="Mobillik bo'layotgan joy"
                                                >
                                                <button type="submit">Saqlash</button>
                                            </form>
                                        @else
                                            <div class="am-destination-text" title="{{ $application->transfer_destination }}">
                                                {{ $application->transfer_destination ?: 'Kiritilmagan' }}
                                            </div>
                                        @endif
                                    </td>
                                    <td>
                                        @if($application->document_path)
                                            <a href="{{ route('admin.academic-mobility.document', $application) }}" target="_blank" rel="noopener noreferrer" class="am-document-btn" data-am-open-and-save title="{{ $application->document_name }}">
                                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 16V4m0 12 4-4m-4 4-4-4M5 20h14"/>
                                                </svg>
                                                Yuklash
                                            </a>
                                            <div class="am-file-name" title="{{ $application->document_name }}">{{ $application->document_name }}</div>
                                        @elseif(!$application->basis_document_path)
                                            <span class="am-no-document">Hujjat yo'q</span>
                                        @endif
                                        @if($application->basis_document_path && $application->basis_document_path !== 'pending')
                                            <a href="{{ route('admin.academic-mobility.basis-document', $application) }}" target="_blank" rel="noopener noreferrer" class="am-document-btn am-document-btn-green" data-am-open-and-save title="{{ $application->basis_document_name }}">
                                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 16V4m0 12 4-4m-4 4-4-4M5 20h14"/>
                                                </svg>
                                                Asos hujjati
                                            </a>
                                            <div class="am-file-name" title="{{ $application->basis_document_name }}">{{ $application->basis_document_name }}</div>
                                        @endif
                                    </td>
                                    @if($showReviewColumn)
                                        <td class="am-review-cell">
                                            @if($application->curriculum_document_path)
                                                <div class="am-review-file-row">
                                                    @if($isDepartmentReviewer)
                                                        <form method="POST" action="{{ route('admin.academic-mobility.curriculum-document.delete', $application) }}" class="am-review-delete" onsubmit="return confirm('O\'quv reja mosligi hujjatini olib tashlaysizmi?')">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" title="Hujjatni olib tashlash" aria-label="Hujjatni olib tashlash">&times;</button>
                                                        </form>
                                                    @endif
                                                    <a href="{{ route('admin.academic-mobility.curriculum-document', $application) }}" target="_blank" rel="noopener noreferrer" class="am-review-file" data-am-open-and-save title="{{ $application->curriculum_document_name }}">
                                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8m-4-6v6h6M9 15l2 2 4-4"/>
                                                        </svg>
                                                        Hujjatni ko'rish
                                                    </a>
                                                </div>
                                                <small class="am-review-filename" title="{{ $application->curriculum_document_name }}">{{ $application->curriculum_document_name }}</small>
                                            @else
                                                <span class="am-review-missing">Hujjat yuklanmagan</span>
                                            @endif

                                            @if($isDepartmentReviewer)
                                                <form method="POST" action="{{ route('admin.academic-mobility.curriculum-document.upload', $application) }}" enctype="multipart/form-data" class="am-review-upload">
                                                    @csrf
                                                    <label>
                                                        <input type="file" name="curriculum_document" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" required>
                                                        <span>{{ $application->curriculum_document_path ? 'Almashtirish' : 'Hujjat tanlash' }}</span>
                                                    </label>
                                                    <button type="submit">Saqlash</button>
                                                </form>
                                            @endif
                                        </td>
                                    @endif
                                     <td class="{{ $showReviewColumn ? 'am-status-cell' : '' }}">
                                         @if($showReviewColumn)
                                             <div class="am-status-summary">
                                                 <span class="am-status {{ $statusClass }}">{{ $statusLabel }}</span>
                                             </div>
                                             <div class="am-stage-list">
                                                <div class="am-stage">
                                                    <span>O'quv bo'limi</span>
                                                    @if($departmentApproval?->status === 'approved')
                                                        <b class="am-stage-approved">Qabul</b>
                                                     @elseif($departmentApproval?->status === 'rejected')
                                                         <b class="am-stage-rejected">Rad</b>
                                                         @if($departmentApproval->rejection_comment)
                                                             <div class="am-rejection-note"><strong>Izoh:</strong> {{ $departmentApproval->rejection_comment }}</div>
                                                         @endif
                                                    @else
                                                        <b class="am-stage-pending">Kutilmoqda</b>
                                                    @endif
                                                </div>
                                                <div class="am-stage">
                                                    <span>O'quv prorektori</span>
                                                    @if($viceRectorApproval?->status === 'approved')
                                                        <b class="am-stage-approved">Qabul</b>
                                                     @elseif($viceRectorApproval?->status === 'rejected')
                                                         <b class="am-stage-rejected">Rad</b>
                                                         @if($viceRectorApproval->rejection_comment)
                                                             <div class="am-rejection-note"><strong>Izoh:</strong> {{ $viceRectorApproval->rejection_comment }}</div>
                                                         @endif
                                                    @else
                                                        <b class="am-stage-pending">Kutilmoqda</b>
                                                    @endif
                                                </div>
                                            </div>

                                            @php
                                                $decisionDisabled = $isViceRector && !$viceRectorEnabled;
                                            @endphp
                                             <div class="am-decision-actions">
                                                 <form method="POST" action="{{ route('admin.academic-mobility.decision', $application) }}">
                                                     @csrf
                                                     <input type="hidden" name="decision" value="approved">
                                                     <button type="submit" class="am-approve-btn" @disabled($decisionDisabled || !$application->curriculum_document_path)>Qabul</button>
                                                 </form>
                                                 <div x-data="{ rejectOpen: false }" @keydown.escape.window="rejectOpen = false">
                                                     <button type="button" class="am-reject-btn" @click="rejectOpen = true" @disabled(!$application->curriculum_document_path || ($isViceRector && !$departmentApproved))>Rad</button>
                                                     <div x-show="rejectOpen" x-cloak x-transition.opacity class="am-rejection-modal" role="dialog" aria-modal="true" aria-label="Arizani rad etish">
                                                         <div class="am-rejection-modal-backdrop" @click="rejectOpen = false"></div>
                                                         <div class="am-rejection-modal-card" @click.stop>
                                                             <div class="am-rejection-modal-head">
                                                                 <div>
                                                                     <strong>Arizani rad etish</strong>
                                                                     <span>Rad etish sababini kiriting</span>
                                                                 </div>
                                                                 <button type="button" class="am-rejection-modal-close" @click="rejectOpen = false" aria-label="Yopish">&times;</button>
                                                             </div>
                                                             <form method="POST" action="{{ route('admin.academic-mobility.decision', $application) }}">
                                                                 @csrf
                                                                 <input type="hidden" name="decision" value="rejected">
                                                                 <textarea name="rejection_comment" rows="4" maxlength="2000" required placeholder="Rad etish izohini yozing..." x-ref="rejectionComment">{{ old('rejection_comment') }}</textarea>
                                                                 <div class="am-rejection-modal-actions">
                                                                     <button type="button" class="am-rejection-cancel" @click="rejectOpen = false">Bekor qilish</button>
                                                                     <button type="submit" class="am-rejection-submit">Rad etish</button>
                                                                 </div>
                                                             </form>
                                                         </div>
                                                     </div>
                                                 </div>
                                             </div>
                                            @if($isViceRector && !$departmentApproved)
                                                <div class="am-stage-note">O'quv bo'limi tasdig'i kutilmoqda.</div>
                                            @endif
                                         @else
                                             <span class="am-status {{ $statusClass }}">{{ $statusLabel }}</span>
                                             @if($application->status === 'rejected')
                                                 @foreach($rejectedApprovals as $rejectedApproval)
                                                     <div class="am-rejection-note am-rejection-note-global">
                                                         <strong>{{ $rejectedApproval->role === 'oquv_bolimi' ? "O'quv bo'limi" : "O'quv prorektori" }} izohi:</strong>
                                                         {{ $rejectedApproval->rejection_comment }}
                                                     </div>
                                                 @endforeach
                                             @endif
                                         @endif
                                    </td>
                                    @if($isRegistrar)
                                        <td class="am-actions-cell">
                                            @php
                                                // Talaba o'zi yuborgan ariza: created_by talabaning o'zi.
                                                $isStudentSubmitted = (int) $application->created_by_id === (int) $application->student_id
                                                    && trim((string) $application->created_by_name) === trim((string) optional($application->student)->full_name);
                                            @endphp
                                            @if($isStudentSubmitted)
                                                <form method="POST" action="{{ route('admin.academic-mobility.move-to-transfer', $application) }}" onsubmit="return confirm('Bu ariza \'O\'qishni ko\'chirish arizalari\' bo\'limiga o\'tkazilsinmi?')">
                                                    @csrf
                                                    <button type="submit" class="am-move-btn" title="O'qishni ko'chirish arizalariga o'tkazish" aria-label="O'qishni ko'chirish arizalariga o'tkazish">
                                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                                                        </svg>
                                                    </button>
                                                </form>
                                            @endif
                                            <form method="POST" action="{{ route('admin.academic-mobility.destroy', $application) }}" onsubmit="return confirm('Bu arizani barcha hujjatlari va tasdiqlari bilan butunlay o\'chirasizmi?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="am-delete-btn" title="Arizani o'chirish" aria-label="Arizani o'chirish">
                                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 7h12m-10 0v12h8V7M9 7V4h6v3m-7 4v5m4-5v5"/>
                                                    </svg>
                                                </button>
                                            </form>
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr><td colspan="{{ 7 + ($showReviewColumn ? 1 : 0) + ($isRegistrar ? 1 : 0) }}" class="am-empty">Ism-familiya bo'yicha arizalar topilmadi.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($applications->hasPages())
                    <div class="am-pagination">{{ $applications->links() }}</div>
                @endif
            </section>
        </div>
    </div>

    <script>
        // Hujjat havolasi bosilganda ikkala ish bajariladi: fayl yangi oynada
        // ochiladi (havolaning o'z xatti-harakati) va shu bilan birga saqlanadi.
        // Saqlash uchun ?download=1 — server Content-Disposition'ni attachment
        // qilib qaytaradi; ko'rinmas iframe orqali chaqiriladi, shunda joriy
        // sahifa ham, yangi oyna ham bezovta bo'lmaydi.
        document.addEventListener('click', function (event) {
            var link = event.target.closest('a[data-am-open-and-save]');
            if (!link || event.defaultPrevented) return;
            // Ctrl/Cmd yoki o'rta tugma — brauzerning o'z xatti-harakati qolsin.
            if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0) return;

            var url = new URL(link.href, window.location.origin);
            url.searchParams.set('download', '1');

            var frame = document.createElement('iframe');
            frame.style.display = 'none';
            frame.src = url.toString();
            document.body.appendChild(frame);
            setTimeout(function () { frame.remove(); }, 60000);
        });
    </script>

    <style>
        .am-alert { display:flex;align-items:center;gap:10px;margin-bottom:14px;border:1px solid #a7f3d0;border-radius:10px;background:#ecfdf5;padding:11px 14px;font-size:13px;font-weight:600;color:#047857; }
        .am-alert svg { width:19px;height:19px;flex:0 0 19px; }
        .am-alert-error { border-color:#fecaca;background:#fef2f2;color:#b91c1c; }
        .am-overview,.am-list-card { overflow:hidden;border:1px solid #dbe4ef;border-radius:12px;background:#fff;box-shadow:0 4px 16px rgba(15,23,42,.06); }
        .am-overview { margin-bottom:14px; }
        .am-hero { min-height:72px;display:flex;align-items:center;justify-content:space-between;gap:16px;padding:14px 18px;color:#fff;background:linear-gradient(135deg,#1f4f91,#2b67ae 58%,#3b82c4); }
        .am-hero-title { display:flex;align-items:center;gap:12px; }
        .am-hero-title h1 { margin:0;font-size:18px;line-height:1.25;font-weight:800;color:#fff; }
        .am-hero-title p { margin:3px 0 0;font-size:12px;color:#dbeafe; }
        .am-hero-icon { width:42px;height:42px;display:flex;align-items:center;justify-content:center;flex:0 0 42px;border:1px solid rgba(255,255,255,.24);border-radius:11px;background:rgba(255,255,255,.13); }
        .am-hero-icon svg { width:22px;height:22px; }
        .am-back-btn { height:36px;display:inline-flex;align-items:center;gap:7px;border:1px solid rgba(255,255,255,.35);border-radius:8px;background:rgba(255,255,255,.14);padding:0 13px;font-size:12px;font-weight:700;color:#fff;text-decoration:none;transition:.18s;white-space:nowrap; }
        .am-back-btn:hover { background:rgba(255,255,255,.24); }
        .am-back-btn svg { width:16px;height:16px; }
        .am-stats { display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;padding:12px;background:#f8fafc; }
        .am-stat { min-height:72px;display:flex;align-items:center;gap:11px;border:1px solid #e2e8f0;border-radius:10px;background:#fff;padding:11px 13px;box-shadow:0 2px 7px rgba(15,23,42,.04); }
        .am-stat-icon { width:40px;height:40px;display:flex;align-items:center;justify-content:center;flex:0 0 40px;border-radius:10px; }
        .am-stat-icon svg { width:20px;height:20px; }
        .am-stat span:not(.am-stat-icon) { display:block;font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.05em;color:#64748b; }
        .am-stat strong { display:block;margin-top:2px;font-size:21px;line-height:1;font-weight:800;color:#172554; }
        .am-stat-blue { border-top:3px solid #3b82f6; }.am-stat-blue .am-stat-icon { background:#eff6ff;color:#2563eb; }
        .am-stat-amber { border-top:3px solid #f59e0b; }.am-stat-amber .am-stat-icon { background:#fffbeb;color:#d97706; }
        .am-stat-green { border-top:3px solid #10b981; }.am-stat-green .am-stat-icon { background:#ecfdf5;color:#059669; }
        .am-stat-violet { border-top:3px solid #8b5cf6; }.am-stat-violet .am-stat-icon { background:#f5f3ff;color:#7c3aed; }
        .am-search-panel { display:flex;align-items:flex-end;justify-content:space-between;gap:18px;padding:14px 18px;background:linear-gradient(135deg,#f0f4f8,#e8edf5);border-bottom:2px solid #dbe4ef; }
        .am-search-heading span { display:block;font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.04em;color:#334155; }
        .am-search-heading small { display:block;margin-top:2px;font-size:11px;color:#64748b;white-space:nowrap; }
        .am-search-controls { width:min(680px,100%);display:flex;align-items:center;gap:8px; }
        .am-search-input { height:38px;min-width:0;display:flex;flex:1 1 auto;overflow:hidden;border:1px solid #cbd5e1;border-radius:9px;background:#fff; }
        .am-search-input:focus-within { border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,.12); }
        .am-search-input span { width:40px;display:flex;align-items:center;justify-content:center;flex:0 0 40px;border-right:1px solid #e2e8f0;background:#eff6ff;color:#2563eb; }
        .am-search-input svg { width:17px;height:17px; }
        .am-search-input input { min-width:0;flex:1;border:0!important;outline:0!important;padding:0 11px;font-size:13px;color:#1e293b;box-shadow:none!important; }
        .am-search-btn,.am-clear-btn { height:38px;display:inline-flex;align-items:center;justify-content:center;border-radius:8px;padding:0 16px;font-size:12px;font-weight:700;text-decoration:none;cursor:pointer;transition:.18s;white-space:nowrap; }
        .am-search-btn { border:1px solid #2563eb;background:#2563eb;color:#fff;box-shadow:0 3px 9px rgba(37,99,235,.2); }
        .am-search-btn:hover { background:#1d4ed8; }
        .am-clear-btn { border:1px solid #cbd5e1;background:#fff;color:#475569; }
        .am-clear-btn:hover { background:#f8fafc; }
        .am-result-bar { padding:8px 18px;border-bottom:1px solid #e2e8f0;background:#f8fafc;font-size:11px;color:#64748b; }
        .am-table-scroll { overflow-x:auto; }
        .am-table { width:100%;border-collapse:separate;border-spacing:0;font-size:12.5px; }
        .am-table thead tr { background:linear-gradient(135deg,#e8edf5,#dbe4ef,#d1d9e6); }
        .am-table th { padding:11px 10px;text-align:left;border-bottom:2px solid #cbd5e1;font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#334155;white-space:nowrap; }
        .am-table td { padding:10px;vertical-align:middle;border-bottom:1px solid #eef2f7;line-height:1.4;color:#475569; }
        .am-number-heading,.am-number-cell { width:46px;min-width:46px;text-align:center!important; }
        .am-number-cell { font-weight:800;color:#64748b!important; }
        .am-table tbody tr:nth-child(even) { background:#f8fafc; }.am-table tbody tr:nth-child(odd) { background:#fff; }
        .am-table tbody tr:hover { background:#eff6ff;box-shadow:inset 4px 0 0 #2b5ea7; }
        .am-student-name { font-weight:700;color:#1e40af;white-space:nowrap; }
        .am-muted { margin-top:2px;font-size:10.5px;color:#94a3b8; }
        .am-faculty { font-weight:600;color:#047857; }.am-specialty { max-width:230px;color:#0e7490;white-space:normal; }
        .am-study-badges { display:flex;flex-wrap:wrap;gap:4px;margin-top:5px; }
        .am-course,.am-group { display:inline-block;border-radius:5px;padding:2px 6px;font-size:10px;font-weight:600; }
        .am-course { border:1px solid #ddd6fe;background:#ede9fe;color:#5b21b6; }.am-group { background:#1e4b8a;color:#fff; }
        .am-phone { white-space:nowrap;color:#334155!important; }
        .am-destination-heading,.am-destination-cell { min-width:285px; }
        .am-destination-form { display:flex;align-items:center;gap:6px; }
        .am-destination-form input { height:32px;min-width:0;flex:1;border:1px solid #cbd5e1;border-radius:6px;background:#fff;padding:0 9px;font-size:11px;color:#334155;outline:0;box-shadow:none; }
        .am-destination-form input:focus { border-color:#3b82f6;box-shadow:0 0 0 2px rgba(59,130,246,.12); }
        .am-destination-form button { height:32px;display:inline-flex;align-items:center;justify-content:center;border:0;border-radius:6px;background:#2563eb;padding:0 11px;font-size:10.5px;font-weight:700;color:#fff;cursor:pointer; }
        .am-destination-form button:hover { background:#1d4ed8; }
        .am-destination-text { max-width:320px;white-space:normal;overflow-wrap:anywhere;font-weight:600;color:#334155; }
        .am-document-btn { display:inline-flex;align-items:center;gap:5px;border-radius:6px;background:#e0f2fe;padding:5px 9px;font-size:11px;font-weight:700;color:#0369a1;text-decoration:none; }
        .am-document-btn:hover { background:#bae6fd; }.am-document-btn svg { width:15px;height:15px; }
        .am-document-btn-green { background:#dcfce7;color:#15803d; }.am-document-btn-green:hover { background:#bbf7d0; }
        .am-file-name { max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;margin-top:3px;font-size:10px;color:#94a3b8; }
        .am-no-document { display:inline-block;border-radius:6px;background:#f1f5f9;padding:5px 8px;font-size:10.5px;font-weight:600;color:#94a3b8; }
        .am-review-heading { min-width:265px; }
        .am-review-cell { min-width:265px;background:#f8fbff; }
        .am-status-heading,.am-status-cell { min-width:235px; }
        .am-actions-heading,.am-actions-cell { width:62px;min-width:62px;text-align:center!important; }
        .am-actions-cell form { display:inline-flex;margin:0; }
        .am-actions-cell { white-space:nowrap; }
        .am-actions-cell form { display:inline-block;margin:0 2px; }
        .am-move-btn { width:32px;height:32px;display:inline-flex;align-items:center;justify-content:center;border:1px solid #bfdbfe;border-radius:7px;background:#fff;color:#2563eb;cursor:pointer;transition:.15s; }
        .am-move-btn svg { width:16px;height:16px; }
        .am-move-btn:hover { border-color:#2563eb;background:#dbeafe;transform:translateY(-1px); }
        .am-delete-btn { width:32px;height:32px;display:inline-flex;align-items:center;justify-content:center;border:1px solid #fecaca;border-radius:7px;background:#fff;color:#dc2626;cursor:pointer;transition:.15s; }
        .am-delete-btn svg { width:16px;height:16px; }
        .am-delete-btn:hover { border-color:#dc2626;background:#fee2e2;transform:translateY(-1px); }
        .am-review-file-row { display:flex;align-items:center;justify-content:flex-start;gap:5px; }
        .am-review-delete { display:flex;flex:0 0 auto;margin:0; }
        .am-review-delete button { width:22px;height:22px;display:inline-flex;align-items:center;justify-content:center;border:1px solid #fecaca;border-radius:50%;background:#fff;color:#dc2626;font-size:16px;font-weight:700;line-height:1;cursor:pointer;transition:.15s; }
        .am-review-delete button:hover { border-color:#dc2626;background:#fee2e2;transform:scale(1.06); }
        .am-review-file { display:inline-flex;align-items:center;gap:6px;border-radius:6px;background:#e0f2fe;padding:5px 9px;font-size:11px;font-weight:700;color:#0369a1;text-decoration:none; }
        .am-review-file svg { width:15px;height:15px; }.am-review-filename { display:block;max-width:245px;margin-top:3px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#94a3b8; }
        .am-review-missing { display:inline-flex;border-radius:6px;background:#fff7ed;padding:5px 8px;font-size:10.5px;font-weight:700;color:#c2410c; }
        .am-review-upload { display:flex;align-items:center;justify-content:flex-start;gap:6px;margin-top:7px; }
        .am-review-upload label { height:30px;max-width:180px;display:flex;align-items:center;justify-content:center;flex:1;overflow:hidden;border:1px dashed #93c5fd;border-radius:6px;background:#fff;cursor:pointer; }
        .am-review-upload label input { position:absolute;width:1px;height:1px;opacity:0; }
        .am-review-upload label span { width:100%;overflow:hidden;padding:5px 8px;font-size:10.5px;font-weight:700;color:#2563eb;text-align:center;text-overflow:ellipsis;white-space:nowrap; }
        .am-review-upload button { height:30px;display:inline-flex;align-items:center;justify-content:center;border:0;border-radius:6px;background:#2563eb;padding:0 10px;font-size:10.5px;font-weight:700;color:#fff;cursor:pointer; }
        .am-stage-list { display:grid;gap:4px; }
        .am-stage { display:flex;align-items:center;justify-content:space-between;gap:8px;font-size:10.5px;font-weight:700;color:#475569; }
        .am-stage b { border-radius:999px;padding:3px 7px;font-size:9.5px;white-space:nowrap; }
        .am-stage-approved { background:#dcfce7;color:#15803d; }.am-stage-rejected { background:#fee2e2;color:#b91c1c; }.am-stage-pending { background:#fef3c7;color:#b45309; }
        .am-status-summary { margin-bottom:8px; }.am-rejection-note { width:100%; margin-top:6px; padding:7px 9px; border:1px solid #fecaca; border-radius:8px; background:#fff1f2; color:#991b1b; font-size:11px; line-height:1.45; }.am-rejection-note strong { font-weight:800; }.am-rejection-note-global { max-width:260px; }
        .am-decision-actions { display:flex;align-items:center;gap:6px;margin-top:8px; }.am-decision-actions > div { flex:1; }
        .am-decision-actions form { flex:1; }.am-decision-actions button { width:100%;border:0;border-radius:6px;padding:5px 8px;font-size:10px;font-weight:800;color:#fff;cursor:pointer; }
        .am-approve-btn { background:#059669; }.am-reject-btn { background:#dc2626; }
        .am-rejection-modal { position:fixed; inset:0; z-index:120; display:flex; align-items:center; justify-content:center; padding:16px; }.am-rejection-modal-backdrop { position:absolute; inset:0; background:rgba(15,23,42,.58); backdrop-filter:blur(3px); }.am-rejection-modal-card { position:relative; z-index:1; width:min(100%,420px); overflow:hidden; border:1px solid #dbe4ef; border-radius:16px; background:#fff; box-shadow:0 24px 70px rgba(15,23,42,.28); }.am-rejection-modal-head { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; padding:16px 18px; border-bottom:1px solid #e2e8f0; background:linear-gradient(135deg,#fff7f7,#fff); }.am-rejection-modal-head strong { display:block; color:#991b1b; font-size:14px; }.am-rejection-modal-head span { display:block; margin-top:3px; color:#64748b; font-size:11px; }.am-rejection-modal-close { width:28px!important; height:28px; padding:0!important; border:1px solid #fecaca!important; border-radius:50%!important; background:#fff!important; color:#dc2626!important; font-size:20px!important; line-height:1!important; }.am-rejection-modal-card form { display:block!important; padding:16px 18px 18px; }.am-rejection-modal-card textarea { width:100%; min-height:100px; resize:vertical; padding:10px 11px; border:1px solid #cbd5e1; border-radius:10px; background:#fff; color:#334155; font-size:13px; line-height:1.45; }.am-rejection-modal-card textarea:focus { outline:none; border-color:#ef4444; box-shadow:0 0 0 3px rgba(239,68,68,.12); }.am-rejection-modal-actions { display:flex; justify-content:flex-end; gap:8px; margin-top:12px; }.am-rejection-modal-actions button { width:auto!important; padding:8px 14px!important; border-radius:9px!important; font-size:12px!important; }.am-rejection-cancel { border:1px solid #cbd5e1!important; background:#fff!important; color:#475569!important; }.am-rejection-submit { border:0!important; background:#dc2626!important; color:#fff!important; }.am-rejection-submit:hover { background:#b91c1c!important; }
        .am-decision-actions button:disabled { background:#cbd5e1;color:#64748b;cursor:not-allowed; }
        .am-stage-note { margin-top:5px;font-size:9.5px;color:#b45309; }
        .am-creator { font-weight:600;color:#334155; }.am-status { display:inline-block;border-radius:999px;padding:4px 9px;font-size:10.5px;font-weight:700;white-space:nowrap; }
        .am-status-pending { background:#fef3c7;color:#b45309; }.am-status-approved { background:#d1fae5;color:#047857; }
        .am-status-rejected { background:#fee2e2;color:#b91c1c; }.am-status-default { background:#e2e8f0;color:#475569; }
        .am-empty { padding:48px!important;text-align:center;color:#64748b!important; }
        .am-pagination { border-top:1px solid #e2e8f0;padding:10px 16px; }
        @media (max-width:900px) { .am-stats { grid-template-columns:repeat(2,minmax(0,1fr)); }.am-search-panel { align-items:stretch;flex-direction:column; }.am-search-controls { width:100%; } }
        @media (max-width:560px) { .am-hero { align-items:flex-start;flex-direction:column; }.am-stats { grid-template-columns:1fr; }.am-search-controls { flex-wrap:wrap; }.am-search-input { flex-basis:100%; }.am-search-btn,.am-clear-btn { flex:1; } }
    </style>
</x-app-layout>
