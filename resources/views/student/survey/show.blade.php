<x-student-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-sm text-gray-800 leading-tight">
            {{ __("So'rovnoma") }}
        </h2>
    </x-slot>

    @php
        $questionsForJs = $survey['questions'];
        $totalQuestions = count($questionsForJs);
        $deadlineFormatted = \Carbon\Carbon::parse($survey['deadline'])->format('d.m.Y H:i');
    @endphp

    <style>
        :root {
            --sv-primary: #6366f1;
            --sv-primary-dark: #4f46e5;
            --sv-primary-light: #eef2ff;
        }
        @keyframes sv-slide-in {
            from { opacity: 0; transform: translateY(10px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        @keyframes sv-pop {
            0%, 100% { transform: scale(1); }
            50%      { transform: scale(1.06); }
        }
        @keyframes sv-shake {
            0%, 100% { transform: translateX(0); }
            25%      { transform: translateX(-6px); }
            75%      { transform: translateX(6px); }
        }
        .sv-question.active { animation: sv-slide-in 0.35s ease-out; }
        .sv-error.show     { animation: sv-shake 0.4s ease; }

        .sv-card {
            box-shadow:
                0 24px 60px -28px rgba(99,102,241,0.30),
                0 12px 28px -16px rgba(15,23,42,0.10);
        }
        .sv-card-header {
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 55%, #4338ca 100%);
        }
        .sv-progress-bar {
            background: linear-gradient(90deg, #34d399 0%, #10b981 45%, #06b6d4 100%);
        }

        .sv-option {
            background: #fafbff;
            border: 1.5px solid transparent;
            transition: background 0.2s ease, transform 0.15s ease, box-shadow 0.2s ease;
            cursor: pointer;
            padding: 9px 12px;
        }
        .sv-option:hover:not(.selected) {
            background: #f4f4ff;
            transform: translateY(-1px);
            box-shadow: 0 4px 14px -6px rgba(99,102,241,0.25);
        }
        .sv-option.selected {
            background: linear-gradient(135deg, #eef2ff 0%, #e0e7ff 100%);
            border-color: #6366f1;
            box-shadow: 0 6px 18px -8px rgba(99,102,241,0.45);
        }
        .sv-option.selected .sv-text {
            color: #1e1b4b;
            font-weight: 600;
        }

        .sv-dot {
            width: 20px; height: 20px;
            flex-shrink: 0;
            border: 2px solid #cbd5e1;
            border-radius: 999px;
            background: #fff;
            position: relative;
            transition: all 0.2s ease;
            margin-top: 1px;
        }
        .sv-dot.square { border-radius: 6px; }
        .sv-dot::after {
            content: ''; position: absolute; inset: 0; margin: auto;
            width: 9px; height: 9px; background: #fff;
            border-radius: 999px; opacity: 0;
            transform: scale(0.4); transition: all 0.2s ease;
        }
        .sv-dot.square::after {
            width: 10px; height: 10px;
            background: transparent;
            border: 2.5px solid #fff;
            border-top: 0; border-left: 0;
            transform: scale(0.4) rotate(45deg);
            margin-top: -2px;
            border-radius: 0;
            opacity: 0;
        }
        .sv-option.selected .sv-dot {
            background: var(--sv-primary);
            border-color: var(--sv-primary);
            box-shadow: 0 0 0 4px rgba(99,102,241,0.18);
        }
        .sv-option.selected .sv-dot::after {
            opacity: 1; transform: scale(1);
        }
        .sv-option.selected .sv-dot.square::after {
            opacity: 1; transform: scale(1) rotate(45deg);
        }

        .sv-btn-primary {
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
            color: #fff;
            box-shadow: 0 4px 14px -4px rgba(79,70,229,0.55);
            transition: all 0.2s ease;
        }
        .sv-btn-primary:hover {
            background: linear-gradient(135deg, #4f46e5 0%, #4338ca 100%);
            transform: translateY(-1px);
            box-shadow: 0 6px 18px -4px rgba(79,70,229,0.6);
        }
        .sv-btn-success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: #fff;
            box-shadow: 0 4px 14px -4px rgba(5,150,105,0.55);
            transition: all 0.2s ease;
        }
        .sv-btn-success:hover {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            transform: translateY(-1px);
        }
        .sv-btn-blue {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: #fff;
            box-shadow: 0 4px 14px -4px rgba(37,99,235,0.55);
            transition: all 0.2s ease;
        }
        .sv-btn-blue:hover:not(:disabled) {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            transform: translateY(-1px);
        }
        .sv-btn-purple {
            background: linear-gradient(135deg, #a855f7 0%, #9333ea 100%);
            color: #fff;
            box-shadow: 0 4px 14px -4px rgba(147,51,234,0.55);
            transition: all 0.2s ease;
        }
        .sv-btn-purple:hover {
            background: linear-gradient(135deg, #9333ea 0%, #7e22ce 100%);
            transform: translateY(-1px);
        }
        .sv-modal-backdrop {
            backdrop-filter: blur(4px);
            background: rgba(15, 23, 42, 0.55);
        }

        /* Layout uchun student-app-layout mobil header'ining 32px o'ng bo'shliq joyini
           yashirish istalmaydi — lekin survey sahifasi uchun toza ko'rinish */
    </style>

    <div class="px-2 py-2 sm:py-5 bg-gradient-to-br from-slate-50 via-indigo-50/30 to-blue-50/30 min-h-[calc(100vh-80px)]">
        <div class="max-w-2xl mx-auto">
            <div class="sv-card bg-white rounded-2xl overflow-hidden">

                {{-- HEADER — gradient + counter + progress (har doim ko'rinadi) --}}
                <div class="sv-card-header px-4 sm:px-5 pt-3 pb-2.5 text-white">
                    <div class="flex items-center justify-between gap-2 mb-2">
                        <h1 class="text-sm sm:text-base font-bold leading-snug">{{ $survey['title'] }}</h1>
                        <span id="sv-counter"
                              class="text-[11px] font-bold bg-white text-indigo-700 px-2.5 py-1 rounded-full whitespace-nowrap shadow-sm">
                            1/{{ $totalQuestions }}
                        </span>
                    </div>
                    <div class="h-1.5 w-full bg-white/25 rounded-full overflow-hidden">
                        <div id="sv-progress" class="sv-progress-bar h-full rounded-full transition-all duration-500"
                             style="width: {{ round(100 / $totalQuestions) }}%"></div>
                    </div>
                </div>

                @if($alreadyCompleted)
                    <div class="px-6 py-10 flex flex-col items-center text-center">
                        <div class="w-20 h-20 rounded-full bg-emerald-100 flex items-center justify-center mb-5">
                            <svg class="w-11 h-11 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-slate-800 mb-2">Rahmat!</h3>
                        <p class="text-sm text-slate-600 max-w-sm">Siz bu so'rovnomani allaqachon bajargansiz. Vaqtingiz uchun rahmat.</p>
                        <a href="{{ route('student.profile') }}"
                           class="mt-6 inline-flex items-center gap-2 px-6 py-3 sv-btn-primary text-sm font-bold rounded-xl">
                            Profilga qaytish
                        </a>
                    </div>
                @else
                    {{-- KIRISH SAHIFA --}}
                    <div id="sv-intro" class="px-4 sm:px-5 py-4">
                        <div class="bg-gradient-to-r from-amber-50 to-yellow-50 border border-amber-200 rounded-xl p-2.5 mb-3 flex items-start gap-2">
                            <div class="w-7 h-7 rounded-full bg-amber-200 flex items-center justify-center flex-shrink-0">
                                <svg class="w-4 h-4 text-amber-700" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                </svg>
                            </div>
                            <p class="text-xs sm:text-sm text-amber-900 leading-snug pt-0.5">
                                Bu so'rovnoma <strong class="font-bold">anonim</strong> — javoblaringiz hech kimga ko'rinmaydi.
                            </p>
                        </div>

                        <div class="text-sm text-slate-700 leading-snug whitespace-pre-line mb-3">{{ $survey['description'] }}</div>

                        <div class="bg-slate-50 border border-slate-200 rounded-lg px-2.5 py-2 mb-3 flex items-center gap-2 text-xs text-slate-600">
                            <svg class="w-3.5 h-3.5 text-indigo-600 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <span>Tugash muddati: <strong class="text-slate-800">{{ $deadlineFormatted }}</strong></span>
                        </div>

                        <button type="button" onclick="svStart()"
                                class="w-full sv-btn-primary text-sm font-bold rounded-xl mb-2 flex items-center justify-center gap-2"
                                style="padding-top:10px;padding-bottom:10px;">
                            <span>So'rovnomani boshlash</span>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                            </svg>
                        </button>
                        @if(!$deadlinePassed)
                        <button type="button" onclick="svShowLaterWarning()"
                                class="w-full text-sm font-semibold text-slate-700 hover:text-slate-900 bg-white border border-slate-200 hover:border-slate-300 rounded-xl transition"
                                style="padding-top:10px;padding-bottom:10px;">
                            Keyinroq bajarish
                        </button>
                        @endif
                    </div>

                    {{-- SAVOLLAR --}}
                    <div id="sv-questions" class="hidden">
                        @php
                            // show_if'li savollar — ota savolga nested qilib joylashtiriladi
                            $childrenByParent = [];
                            foreach ($questionsForJs as $cq) {
                                if (!empty($cq['show_if']['question_id'])) {
                                    $childrenByParent[$cq['show_if']['question_id']][] = $cq;
                                }
                            }
                            $rootQuestions = array_values(array_filter($questionsForJs, fn($q) => empty($q['show_if'])));
                        @endphp

                        @foreach($rootQuestions as $idx => $q)
                            <div class="sv-question hidden px-4 sm:px-5 py-4"
                                 data-qid="{{ $q['id'] }}"
                                 data-type="{{ $q['type'] }}"
                                 data-index="{{ $idx }}">

                                @include('student.survey._question-block', ['q' => $q, 'isChild' => false])

                                {{-- Nested conditional bolalar (5.1 kabi) --}}
                                @foreach($childrenByParent[$q['id']] ?? [] as $child)
                                    <div class="sv-child-q hidden mt-3"
                                         data-child-qid="{{ $child['id'] }}"
                                         data-child-type="{{ $child['type'] }}"
                                         data-show-when="{{ $child['show_if']['when_option'] }}">
                                        <div class="bg-indigo-50/60 border-l-4 border-indigo-400 rounded-r-xl rounded-l-lg p-3 sm:p-3.5">
                                            @include('student.survey._question-block', ['q' => $child, 'isChild' => true])
                                        </div>
                                    </div>
                                @endforeach

                                <div class="sv-error hidden mt-3 p-2.5 bg-red-50 border border-red-200 rounded-lg">
                                    <p class="text-xs sm:text-sm text-red-700 font-semibold flex items-center gap-2">
                                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                        </svg>
                                        <span class="sv-error-text">Iltimos, javob tanlang yoki to'ldiring</span>
                                    </p>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    {{-- NAVIGATSIYA --}}
                    <div id="sv-nav" class="hidden border-t border-slate-200 px-4 sm:px-5 py-2.5 bg-gradient-to-b from-white to-slate-50/70">
                        <div class="flex items-center gap-1.5 mb-1.5">
                            <button id="sv-back" type="button" onclick="svBack()"
                                    class="flex-1 py-2 text-sm font-bold sv-btn-blue rounded-lg flex items-center justify-center gap-1.5 disabled:opacity-40 disabled:cursor-not-allowed">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 17l-5-5m0 0l5-5m-5 5h12"/>
                                </svg>
                                Orqaga
                            </button>
                            <button id="sv-next" type="button" onclick="svNext()"
                                    class="flex-1 py-2 text-sm font-bold sv-btn-success rounded-lg flex items-center justify-center gap-1.5">
                                <span id="sv-next-text">Keyingisi</span>
                                <svg id="sv-next-icon" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                                </svg>
                            </button>
                        </div>
                        @if(!$deadlinePassed)
                        <button type="button" onclick="svShowLaterWarning()"
                                class="w-full py-2 text-sm font-bold sv-btn-purple rounded-lg flex items-center justify-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Keyinroq bajarish
                        </button>
                        @endif
                    </div>

                    {{-- YUBORISH OYNALARI --}}
                    <div id="sv-submitting" class="hidden px-8 py-10 flex flex-col items-center text-center">
                        <div class="w-14 h-14 border-4 border-indigo-100 border-t-indigo-600 rounded-full animate-spin mb-5"></div>
                        <p class="text-sm font-medium text-slate-700">Javoblar yuborilmoqda...</p>
                        <p class="text-xs text-slate-500 mt-1">Biroz kuting</p>
                    </div>

                    <div id="sv-success" class="hidden px-8 py-10 flex flex-col items-center text-center">
                        <div class="w-20 h-20 rounded-full bg-emerald-100 flex items-center justify-center mb-5" style="animation: sv-pop 0.6s ease;">
                            <svg class="w-11 h-11 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-slate-800 mb-2">Rahmat!</h3>
                        <p class="text-sm text-slate-600 max-w-sm leading-relaxed" id="sv-success-msg">Javoblaringiz qabul qilindi.</p>
                        <a href="{{ route('student.profile') }}"
                           class="mt-6 inline-flex items-center gap-2 px-6 py-3 sv-btn-primary text-sm font-bold rounded-xl">
                            Profilga qaytish
                        </a>
                    </div>

                    <div id="sv-error-box" class="hidden border-t border-red-200 px-5 sm:px-7 py-3.5 bg-red-50">
                        <p class="text-sm text-red-700 font-medium flex items-center gap-2" id="sv-error-text"></p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- "Keyinroq bajarish" ogohlantirish modali --}}
    <div id="sv-later-modal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 sv-modal-backdrop">
        <div class="bg-white rounded-3xl max-w-md w-full overflow-hidden shadow-2xl" style="animation: sv-slide-in 0.3s ease;">
            <div class="px-6 pt-6 pb-2 flex flex-col items-center text-center">
                <div class="w-16 h-16 rounded-full bg-amber-100 flex items-center justify-center mb-4">
                    <svg class="w-9 h-9 text-amber-600" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>
                <h3 class="text-lg font-bold text-slate-800 mb-2">E'tibor!</h3>
                <p class="text-sm text-slate-600 leading-relaxed mb-3">
                    Sizga <strong class="text-slate-800">{{ $deadlineFormatted }}</strong> gacha muhlat berilgan.
                </p>
                <p class="text-sm text-slate-600 leading-relaxed">
                    Agar so'rovnomani belgilangan muddatda bajarmasangiz,
                    <strong class="text-red-600">tizim xizmatlaridan foydalanish cheklanadi</strong>.
                </p>
            </div>
            <div class="px-6 py-5 bg-slate-50/50 border-t border-slate-100 flex flex-col sm:flex-row gap-2.5">
                <button type="button" onclick="svCloseLaterWarning()"
                        class="flex-1 py-3 text-sm font-semibold text-slate-700 bg-white border border-slate-200 hover:bg-slate-50 rounded-xl transition">
                    Davom etish
                </button>
                <a href="{{ route('student.profile') }}"
                   class="flex-1 py-3 text-sm font-bold text-center bg-slate-200 hover:bg-slate-300 text-slate-800 rounded-xl transition">
                    Profilga qaytish
                </a>
            </div>
        </div>
    </div>

    @unless($alreadyCompleted)
    <script>
        const SV = {
            csrf: '{{ csrf_token() }}',
            submitUrl: '{{ route("student.survey.submit") }}',
            questions: @json($questionsForJs),
            answers: {},
            currentIdx: 0,
            visibleOrder: [],
        };

        function svShowLaterWarning() {
            const m = document.getElementById('sv-later-modal');
            m.classList.remove('hidden');
            m.classList.add('flex');
        }
        function svCloseLaterWarning() {
            const m = document.getElementById('sv-later-modal');
            m.classList.add('hidden');
            m.classList.remove('flex');
        }
        document.addEventListener('click', function (e) {
            const m = document.getElementById('sv-later-modal');
            if (e.target === m) svCloseLaterWarning();
        });

        function svRecomputeOrder() {
            // Conditional savollar (show_if'li) alohida step emas — ota savolning
            // ichida nested chiqadi. Bu yerda faqat root savollarni step sifatida
            // ro'yxatga olamiz.
            const order = [];
            SV.questions.forEach((q, idx) => {
                if (q.show_if) return;
                order.push(idx);
            });
            SV.visibleOrder = order;
        }

        // Ota savolning tanlangan variantiga qarab bolalarni ko'rsatish/yashirish
        function svToggleChildren(parentEl) {
            const parentQid = parentEl.dataset.qid;
            const selectedOpt = parentEl.querySelector('input[type=radio]:checked')?.value;
            parentEl.querySelectorAll('.sv-child-q').forEach(child => {
                const showWhen = child.dataset.showWhen;
                if (selectedOpt && (selectedOpt === showWhen || (selectedOpt === 'other' && showWhen === 'other'))) {
                    child.classList.remove('hidden');
                } else {
                    child.classList.add('hidden');
                    // Yashiringan bola tanlovini tozalash
                    child.querySelectorAll('input').forEach(inp => { if (inp.type !== 'text') inp.checked = false; });
                    child.querySelectorAll('.sv-other-wrap').forEach(w => w.classList.add('hidden'));
                    child.querySelectorAll('.sv-option').forEach(o => o.classList.remove('selected'));
                    const childQid = child.dataset.childQid;
                    if (childQid && SV.answers[childQid] !== undefined) delete SV.answers[childQid];
                }
            });
        }

        function svStart() {
            document.getElementById('sv-intro').classList.add('hidden');
            document.getElementById('sv-questions').classList.remove('hidden');
            document.getElementById('sv-nav').classList.remove('hidden');
            svRecomputeOrder();
            SV.currentIdx = 0;
            svRender();
        }

        function svRestoreAnswer(scopeEl, qid, qType) {
            // Yagona savol uchun saqlangan javobni inputlarga qaytarish
            const ans = SV.answers[qid];
            if (qType === 'text') {
                const ta = scopeEl.querySelector('textarea[name="q_' + qid + '"]');
                if (ta) ta.value = (typeof ans === 'string') ? ans : '';
                return;
            }
            if (qType === 'radio') {
                scopeEl.querySelectorAll('input[type=radio][name="q_' + qid + '"]').forEach(inp => inp.checked = false);
                if (typeof ans === 'string') {
                    const optId = ans.startsWith('other:') ? 'other' : ans;
                    const inp = scopeEl.querySelector('input[type=radio][name="q_' + qid + '"][value="' + optId + '"]');
                    if (inp) inp.checked = true;
                    if (ans.startsWith('other:')) {
                        const wrap = inp?.closest('label')?.querySelector('.sv-other-wrap');
                        if (wrap) {
                            wrap.classList.remove('hidden');
                            const oi = wrap.querySelector('.sv-other-input');
                            if (oi) oi.value = ans.substring(6);
                        }
                    }
                }
            } else {
                scopeEl.querySelectorAll('input[type=checkbox][name="q_' + qid + '[]"]').forEach(inp => inp.checked = false);
                if (Array.isArray(ans)) {
                    ans.forEach(v => {
                        const optId = (typeof v === 'string' && v.startsWith('other:')) ? 'other' : v;
                        const inp = scopeEl.querySelector('input[type=checkbox][name="q_' + qid + '[]"][value="' + optId + '"]');
                        if (inp) inp.checked = true;
                        if (typeof v === 'string' && v.startsWith('other:')) {
                            const wrap = inp?.closest('label')?.querySelector('.sv-other-wrap');
                            if (wrap) {
                                wrap.classList.remove('hidden');
                                const oi = wrap.querySelector('.sv-other-input');
                                if (oi) oi.value = v.substring(6);
                            }
                        }
                    });
                }
            }
        }

        function svRender() {
            document.querySelectorAll('.sv-question').forEach(el => {
                el.classList.add('hidden');
                el.classList.remove('active');
            });
            const qIdx = SV.visibleOrder[SV.currentIdx];
            const q = SV.questions[qIdx];
            const el = document.querySelector('.sv-question[data-qid="' + q.id + '"]');
            if (!el) return;
            el.classList.remove('hidden');
            el.classList.add('active');

            const total = SV.visibleOrder.length;
            const pos = SV.currentIdx + 1;
            document.getElementById('sv-counter').textContent = pos + '/' + total;
            document.getElementById('sv-progress').style.width = Math.round(pos / total * 100) + '%';

            document.getElementById('sv-back').disabled = (SV.currentIdx === 0);

            const isLast = SV.currentIdx === total - 1;
            const nextText = document.getElementById('sv-next-text');
            const nextIcon = document.getElementById('sv-next-icon');
            if (isLast) {
                nextText.textContent = 'Yuborish';
                nextIcon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>';
            } else {
                nextText.textContent = 'Keyingisi';
                nextIcon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/>';
            }

            svRestoreAnswer(el, q.id, q.type);
            // Bolalar (5.1 kabi) — saqlangan javobini ko'rsatish va shartga qarab ochish
            el.querySelectorAll('.sv-child-q').forEach(child => {
                const cqid = child.dataset.childQid;
                const ctype = child.dataset.childType;
                svRestoreAnswer(child, cqid, ctype);
            });
            svToggleChildren(el);

            svPaintSelected(el);
            el.querySelector('.sv-error')?.classList.add('hidden');
            window.scrollTo({top: 0, behavior: 'smooth'});
        }

        function svPaintSelected(scopeEl) {
            // scopeEl ham .sv-question, ham .sv-child-q bo'lishi mumkin — barcha optionlarini
            // tekshiramiz, lekin ichki child savollar o'z scope'iga ega bo'lganda alohida boshqariladi.
            const options = scopeEl.classList.contains('sv-question')
                ? Array.from(scopeEl.querySelectorAll(':scope > div > .sv-option, :scope > .space-y-3 > .sv-option, :scope .sv-option'))
                : Array.from(scopeEl.querySelectorAll('.sv-option'));
            options.forEach(lb => {
                const inp = lb.querySelector('input');
                if (inp?.checked) lb.classList.add('selected');
                else lb.classList.remove('selected');
            });
        }

        // Bitta savol uchun javobni inputlardan yig'ish. Muvaffaqiyatda javobni
        // SV.answers ga yozadi va true qaytaradi; bo'sh/noto'g'ri bo'lsa false.
        function svCollectQuestion(scopeEl, qid, qType, required) {
            if (qType === 'text') {
                const ta = scopeEl.querySelector('textarea[name="q_' + qid + '"]');
                const txt = (ta?.value || '').trim();
                if (!txt) {
                    if (required) return false;
                    delete SV.answers[qid];
                    return true;
                }
                SV.answers[qid] = txt;
                return true;
            }
            if (qType === 'radio') {
                const checked = scopeEl.querySelector('input[type=radio][name="q_' + qid + '"]:checked');
                if (!checked) return false;
                const optId = checked.value;
                if (optId === 'other') {
                    const wrap = checked.closest('label').querySelector('.sv-other-wrap');
                    const txt = (wrap?.querySelector('.sv-other-input')?.value || '').trim();
                    if (!txt) return false;
                    SV.answers[qid] = 'other:' + txt;
                } else {
                    SV.answers[qid] = optId;
                }
                return true;
            }
            const checks = Array.from(scopeEl.querySelectorAll('input[type=checkbox][name="q_' + qid + '[]"]:checked'));
            if (checks.length === 0) return false;
            const vals = [];
            for (const c of checks) {
                const optId = c.value;
                if (optId === 'other') {
                    const wrap = c.closest('label').querySelector('.sv-other-wrap');
                    const txt = (wrap?.querySelector('.sv-other-input')?.value || '').trim();
                    if (!txt) return false;
                    vals.push('other:' + txt);
                } else {
                    vals.push(optId);
                }
            }
            SV.answers[qid] = vals;
            return true;
        }

        function svCollect() {
            const qIdx = SV.visibleOrder[SV.currentIdx];
            const q = SV.questions[qIdx];
            const el = document.querySelector('.sv-question[data-qid="' + q.id + '"]');
            if (!el) return false;

            if (!svCollectQuestion(el, q.id, q.type, q.required !== false)) return false;

            // Ko'rinadigan bolalar — ularning javobi ham majburiy
            const visibleChildren = el.querySelectorAll('.sv-child-q:not(.hidden)');
            for (const child of visibleChildren) {
                const cqid = child.dataset.childQid;
                const ctype = child.dataset.childType;
                const cq = SV.questions.find(x => x.id === cqid);
                if (!svCollectQuestion(child, cqid, ctype, cq?.required !== false)) return false;
            }
            return true;
        }

        function svNext() {
            if (!svCollect()) {
                const q = SV.questions[SV.visibleOrder[SV.currentIdx]];
                const el = document.querySelector('.sv-question[data-qid="' + q.id + '"]');
                const err = el?.querySelector('.sv-error');
                err?.classList.remove('hidden');
                err?.classList.add('show');
                setTimeout(() => err?.classList.remove('show'), 500);
                return;
            }
            svRecomputeOrder();
            if (SV.currentIdx < SV.visibleOrder.length - 1) {
                SV.currentIdx++;
                svRender();
            } else {
                svSubmit();
            }
        }

        function svBack() {
            if (SV.currentIdx > 0) {
                svCollect();
                SV.currentIdx--;
                svRecomputeOrder();
                svRender();
            }
        }

        function svSubmit() {
            document.getElementById('sv-questions').classList.add('hidden');
            document.getElementById('sv-nav').classList.add('hidden');
            document.getElementById('sv-submitting').classList.remove('hidden');

            fetch(SV.submitUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': SV.csrf,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ answers: SV.answers }),
            })
            .then(r => r.json().then(d => ({ status: r.status, data: d })))
            .then(({ status, data }) => {
                document.getElementById('sv-submitting').classList.add('hidden');
                if (status >= 200 && status < 300 && data.success) {
                    document.getElementById('sv-success').classList.remove('hidden');
                    document.getElementById('sv-success-msg').textContent = data.message || 'Javoblaringiz qabul qilindi.';
                } else {
                    document.getElementById('sv-error-box').classList.remove('hidden');
                    document.getElementById('sv-error-text').textContent = data.message || 'Xatolik yuz berdi.';
                    document.getElementById('sv-questions').classList.remove('hidden');
                    document.getElementById('sv-nav').classList.remove('hidden');
                }
            })
            .catch(() => {
                document.getElementById('sv-submitting').classList.add('hidden');
                document.getElementById('sv-error-box').classList.remove('hidden');
                document.getElementById('sv-error-text').textContent = "So'rovni yuborib bo'lmadi. Internet ulanishini tekshiring.";
                document.getElementById('sv-questions').classList.remove('hidden');
                document.getElementById('sv-nav').classList.remove('hidden');
            });
        }

        document.addEventListener('change', function (e) {
            if (e.target.matches('.sv-question input[type=radio], .sv-question input[type=checkbox], .sv-child-q input[type=radio], .sv-child-q input[type=checkbox]')) {
                const label = e.target.closest('label');
                if (!label) return;
                const wrap = label.querySelector('.sv-other-wrap');
                if (wrap) {
                    if (e.target.value === 'other' && e.target.checked) {
                        wrap.classList.remove('hidden');
                        setTimeout(() => wrap.querySelector('.sv-other-input')?.focus(), 100);
                    } else if (e.target.type === 'radio') {
                        // Radio ichidagi boshqa "Boshqa" inputlarni yashirish — faqat shu savol uchun
                        const scope = label.closest('.sv-child-q') || label.closest('.sv-question');
                        const sameNameRadios = scope.querySelectorAll('input[type=radio][name="' + e.target.name + '"]');
                        sameNameRadios.forEach(r => {
                            const wp = r.closest('label').querySelector('.sv-other-wrap');
                            if (wp && r !== e.target) wp.classList.add('hidden');
                        });
                    } else if (!e.target.checked) {
                        wrap.classList.add('hidden');
                    }
                }

                // Tanlangan vizual
                const parentScope = label.closest('.sv-child-q') || label.closest('.sv-question');
                svPaintSelected(parentScope);

                // Ota savol radio'si o'zgardi → bolalarni qayta hisoblash
                const rootQuestion = label.closest('.sv-question');
                if (rootQuestion && !label.closest('.sv-child-q')) {
                    svToggleChildren(rootQuestion);
                }
            }
        });
    </script>
    @endunless
</x-student-app-layout>
