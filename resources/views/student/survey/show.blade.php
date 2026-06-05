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
        @keyframes sv-slide-in {
            from { opacity: 0; transform: translateY(8px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        @keyframes sv-pop {
            0%, 100% { transform: scale(1); }
            50%      { transform: scale(1.04); }
        }
        @keyframes sv-shake {
            0%, 100% { transform: translateX(0); }
            25%      { transform: translateX(-6px); }
            75%      { transform: translateX(6px); }
        }
        .sv-question.active {
            animation: sv-slide-in 0.35s ease-out;
        }
        .sv-option {
            background: linear-gradient(180deg, #ffffff 0%, #fafbff 100%);
        }
        .sv-option.selected {
            background: linear-gradient(135deg, #eef2ff 0%, #e0e7ff 100%);
            border-color: #6366f1;
            box-shadow: 0 4px 12px -4px rgba(99,102,241,0.35);
        }
        .sv-option.selected .sv-dot {
            background: #6366f1;
            border-color: #6366f1;
            box-shadow: 0 0 0 4px rgba(99,102,241,0.18);
        }
        .sv-option.selected .sv-dot::after {
            opacity: 1;
            transform: scale(1);
        }
        .sv-option.selected .sv-text {
            color: #1e1b4b;
            font-weight: 600;
        }
        .sv-dot {
            width: 22px; height: 22px;
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
            content: '';
            position: absolute;
            inset: 0;
            margin: auto;
            width: 10px; height: 10px;
            background: #fff;
            border-radius: 999px;
            opacity: 0;
            transform: scale(0.4);
            transition: all 0.2s;
        }
        .sv-dot.square::after {
            width: 12px; height: 12px;
            background: transparent;
            border: 2.5px solid #fff;
            border-top: 0;
            border-left: 0;
            transform: scale(0.4) rotate(45deg);
            margin-top: -2px;
            border-radius: 0;
        }
        .sv-option.selected .sv-dot.square::after {
            transform: scale(1) rotate(45deg);
        }
        .sv-option:hover:not(.selected) {
            transform: translateY(-1px);
            border-color: #a5b4fc;
            box-shadow: 0 4px 12px -6px rgba(99,102,241,0.25);
        }
        .sv-btn-primary {
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
            box-shadow: 0 4px 14px -4px rgba(79,70,229,0.5);
        }
        .sv-btn-primary:hover {
            background: linear-gradient(135deg, #4f46e5 0%, #4338ca 100%);
            transform: translateY(-1px);
            box-shadow: 0 6px 18px -4px rgba(79,70,229,0.55);
        }
        .sv-btn-success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            box-shadow: 0 4px 14px -4px rgba(5,150,105,0.5);
        }
        .sv-btn-success:hover {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            transform: translateY(-1px);
        }
        .sv-card {
            box-shadow: 0 20px 50px -20px rgba(99,102,241,0.25), 0 8px 24px -12px rgba(15,23,42,0.12);
        }
        .sv-error.show {
            animation: sv-shake 0.4s ease;
        }
        .sv-progress-bar {
            background: linear-gradient(90deg, #34d399 0%, #10b981 50%, #6366f1 100%);
            background-size: 200% 100%;
            background-position: 0% 50%;
            transition: width 0.5s ease, background-position 0.5s ease;
        }
        .sv-modal-backdrop {
            backdrop-filter: blur(4px);
            background: rgba(15, 23, 42, 0.55);
        }
    </style>

    <div class="min-h-[calc(100vh-80px)] flex items-start justify-center px-3 py-4 sm:py-8 bg-gradient-to-br from-slate-50 via-indigo-50/30 to-blue-50/30">
        <div class="sv-card w-full max-w-2xl bg-white rounded-3xl overflow-hidden flex flex-col" style="min-height:75vh;">

            {{-- HEADER --}}
            <div class="px-5 sm:px-7 pt-5 pb-4 bg-gradient-to-br from-indigo-600 via-indigo-600 to-blue-600 text-white relative overflow-hidden">
                <div class="absolute -top-12 -right-12 w-48 h-48 bg-white/10 rounded-full"></div>
                <div class="absolute -bottom-8 -left-8 w-32 h-32 bg-white/5 rounded-full"></div>
                <div class="relative">
                    <div class="flex items-center justify-between mb-3">
                        <h1 class="text-base sm:text-lg font-bold leading-snug pr-2">{{ $survey['title'] }}</h1>
                        <span id="sv-counter" class="text-xs font-bold bg-white/25 backdrop-blur-sm px-3 py-1.5 rounded-full whitespace-nowrap border border-white/20">1/{{ $totalQuestions }}</span>
                    </div>
                    <div class="h-2.5 w-full bg-white/20 rounded-full overflow-hidden">
                        <div id="sv-progress" class="sv-progress-bar h-full rounded-full" style="width: {{ round(100 / $totalQuestions) }}%"></div>
                    </div>
                </div>
            </div>

            @if($alreadyCompleted)
                <div class="flex-1 flex flex-col items-center justify-center text-center p-8">
                    <div class="w-20 h-20 rounded-full bg-gradient-to-br from-emerald-100 to-emerald-50 flex items-center justify-center mb-5 shadow-inner">
                        <svg class="w-11 h-11 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-slate-800 mb-2">Rahmat!</h3>
                    <p class="text-sm text-slate-600 max-w-sm leading-relaxed">Siz bu so'rovnomani allaqachon bajargansiz. Vaqtingiz uchun rahmat.</p>
                    <a href="{{ route('student.profile') }}" class="mt-6 inline-flex items-center gap-2 px-6 py-3 sv-btn-primary text-white text-sm font-bold rounded-xl transition">
                        Profilga qaytish
                    </a>
                </div>
            @else
                {{-- KIRISH SAHIFA --}}
                <div id="sv-intro" class="flex-1 px-5 sm:px-7 py-6 flex flex-col">
                    <div class="bg-gradient-to-r from-amber-50 to-yellow-50 border-2 border-amber-200 rounded-2xl p-4 mb-5 flex items-start gap-3 shadow-sm">
                        <div class="w-9 h-9 rounded-full bg-amber-200 flex items-center justify-center flex-shrink-0">
                            <svg class="w-5 h-5 text-amber-700" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                        </div>
                        <p class="text-xs sm:text-sm text-amber-900 leading-relaxed pt-1">Bu so'rovnoma <strong class="font-bold">anonim</strong> — javoblaringiz hech kimga ko'rinmaydi.</p>
                    </div>
                    <div class="text-sm text-slate-700 leading-relaxed whitespace-pre-line mb-6">{{ $survey['description'] }}</div>
                    <div class="flex-1"></div>

                    <div class="bg-slate-50 rounded-xl p-3 mb-4 border border-slate-200">
                        <div class="flex items-center gap-2 text-xs text-slate-600">
                            <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <span>Tugash muddati: <strong class="text-slate-800">{{ $deadlineFormatted }}</strong></span>
                        </div>
                    </div>

                    <button type="button" onclick="svStart()" class="w-full py-4 sv-btn-primary text-white text-sm font-bold rounded-2xl transition mb-3 flex items-center justify-center gap-2">
                        <span>So'rovnomani boshlash</span>
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                    </button>
                    @if(!$deadlinePassed)
                    <button type="button" onclick="svShowLaterWarning()" class="w-full py-3 text-sm font-semibold text-slate-600 hover:text-slate-900 bg-white border-2 border-slate-200 hover:border-slate-300 rounded-2xl transition">
                        Keyinroq bajarish
                    </button>
                    @endif
                </div>

                {{-- SAVOLLAR --}}
                <div id="sv-questions" class="flex-1 hidden flex-col">
                    @foreach($questionsForJs as $idx => $q)
                        <div class="sv-question hidden flex-col flex-1 px-5 sm:px-7 py-5"
                             data-qid="{{ $q['id'] }}"
                             data-type="{{ $q['type'] }}"
                             data-index="{{ $idx }}"
                             @if(!empty($q['show_if']))
                                 data-show-if-qid="{{ $q['show_if']['question_id'] }}"
                                 data-show-if-opt="{{ $q['show_if']['when_option'] }}"
                             @endif>
                            <div class="mb-5">
                                <div class="inline-flex items-center gap-1.5 mb-2 px-2.5 py-1 bg-indigo-100 text-indigo-700 text-[10px] font-bold uppercase tracking-wide rounded-full">
                                    Savol {{ $q['id'] }}
                                </div>
                                <h3 class="text-base sm:text-lg font-bold text-slate-800 leading-snug">{{ $q['text'] }}</h3>
                                @if($q['type'] === 'checkbox')
                                    <p class="text-xs text-indigo-600 mt-2 font-medium flex items-center gap-1">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                        Bir nechtasini tanlash mumkin
                                    </p>
                                @endif
                            </div>

                            <div class="space-y-2.5 flex-1">
                                @foreach($q['options'] as $opt)
                                    <label class="sv-option block border-2 border-slate-200 rounded-2xl px-4 py-3.5 cursor-pointer transition-all duration-200">
                                        <div class="flex items-start gap-3.5">
                                            <span class="sv-dot {{ $q['type'] === 'checkbox' ? 'square' : '' }}"></span>
                                            @if($q['type'] === 'radio')
                                                <input type="radio" name="q_{{ $q['id'] }}" value="{{ $opt['id'] }}" class="sr-only">
                                            @else
                                                <input type="checkbox" name="q_{{ $q['id'] }}[]" value="{{ $opt['id'] }}" class="sr-only">
                                            @endif
                                            <span class="sv-text text-sm text-slate-700 leading-snug flex-1">{{ $opt['text'] }}</span>
                                        </div>
                                        @if(!empty($opt['has_other']))
                                            <div class="sv-other-wrap hidden mt-3 ml-[34px]">
                                                <input type="text" class="sv-other-input w-full px-3.5 py-2.5 text-sm bg-white border-2 border-indigo-200 rounded-xl focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100 outline-none transition"
                                                       placeholder="Iltimos, izoh yozing..."
                                                       data-opt="{{ $opt['id'] }}">
                                            </div>
                                        @endif
                                    </label>
                                @endforeach
                            </div>

                            <div class="sv-error hidden mt-4 p-3 bg-red-50 border border-red-200 rounded-xl">
                                <p class="text-xs sm:text-sm text-red-700 font-semibold flex items-center gap-2">
                                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                    <span>Iltimos, javob tanlang yoki to'ldiring</span>
                                </p>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- NAVIGATSIYA --}}
                <div id="sv-nav" class="hidden border-t border-slate-200 px-5 sm:px-7 py-4 bg-gradient-to-b from-white to-slate-50/70">
                    <div class="flex items-center gap-3 mb-3">
                        <button id="sv-back" type="button" onclick="svBack()" class="flex-1 py-3 text-sm font-semibold text-slate-700 bg-white border-2 border-slate-200 hover:border-slate-300 hover:bg-slate-50 rounded-xl transition disabled:opacity-40 disabled:cursor-not-allowed flex items-center justify-center gap-1.5">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 17l-5-5m0 0l5-5m-5 5h12"/></svg>
                            Orqaga
                        </button>
                        <button id="sv-next" type="button" onclick="svNext()" class="flex-[1.5] py-3 text-sm font-bold text-white sv-btn-primary rounded-xl transition flex items-center justify-center gap-1.5">
                            <span id="sv-next-text">Keyingisi</span>
                            <svg id="sv-next-icon" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                        </button>
                    </div>
                    @if(!$deadlinePassed)
                    <button type="button" onclick="svShowLaterWarning()" class="w-full py-3 text-sm font-semibold text-slate-600 hover:text-indigo-700 bg-white border-2 border-slate-200 hover:border-indigo-200 hover:bg-indigo-50/40 rounded-xl transition flex items-center justify-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Keyinroq bajarish
                    </button>
                    @endif
                </div>

                {{-- YUBORISH OYNALARI --}}
                <div id="sv-submitting" class="hidden flex-1 flex-col items-center justify-center p-8 text-center">
                    <div class="w-14 h-14 border-4 border-indigo-100 border-t-indigo-600 rounded-full animate-spin mb-5"></div>
                    <p class="text-sm font-medium text-slate-700">Javoblar yuborilmoqda...</p>
                    <p class="text-xs text-slate-500 mt-1">Biroz kuting</p>
                </div>

                <div id="sv-success" class="hidden flex-1 flex-col items-center justify-center text-center p-8">
                    <div class="w-20 h-20 rounded-full bg-gradient-to-br from-emerald-100 to-emerald-50 flex items-center justify-center mb-5 shadow-inner" style="animation: sv-pop 0.6s ease;">
                        <svg class="w-11 h-11 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-slate-800 mb-2">Rahmat!</h3>
                    <p class="text-sm text-slate-600 max-w-sm leading-relaxed" id="sv-success-msg">Javoblaringiz qabul qilindi.</p>
                    <a href="{{ route('student.profile') }}" class="mt-6 inline-flex items-center gap-2 px-6 py-3 sv-btn-primary text-white text-sm font-bold rounded-xl transition">
                        Profilga qaytish
                    </a>
                </div>

                <div id="sv-error-box" class="hidden border-t border-red-200 px-5 sm:px-7 py-3.5 bg-red-50">
                    <p class="text-sm text-red-700 font-medium flex items-center gap-2" id="sv-error-text"></p>
                </div>
            @endif
        </div>
    </div>

    {{-- "Keyinroq bajarish" ogohlantirish modali --}}
    <div id="sv-later-modal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 sv-modal-backdrop">
        <div class="bg-white rounded-3xl max-w-md w-full overflow-hidden shadow-2xl" style="animation: sv-slide-in 0.3s ease;">
            <div class="px-6 pt-6 pb-2 flex flex-col items-center text-center">
                <div class="w-16 h-16 rounded-full bg-gradient-to-br from-amber-100 to-orange-100 flex items-center justify-center mb-4 shadow-inner">
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
                <button type="button" onclick="svCloseLaterWarning()" class="flex-1 py-3 text-sm font-semibold text-slate-700 bg-white border-2 border-slate-200 hover:bg-slate-50 rounded-xl transition">
                    Davom etish
                </button>
                <a href="{{ route('student.profile') }}" class="flex-1 py-3 text-sm font-bold text-center bg-slate-200 hover:bg-slate-300 text-slate-800 rounded-xl transition">
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
            const order = [];
            SV.questions.forEach((q, idx) => {
                if (q.show_if) {
                    const parentAns = SV.answers[q.show_if.question_id];
                    if (typeof parentAns !== 'string' || !parentAns.startsWith(q.show_if.when_option)) {
                        return;
                    }
                }
                order.push(idx);
            });
            SV.visibleOrder = order;
        }

        function svStart() {
            document.getElementById('sv-intro').classList.add('hidden');
            const qBox = document.getElementById('sv-questions');
            qBox.classList.remove('hidden');
            qBox.classList.add('flex');
            document.getElementById('sv-nav').classList.remove('hidden');
            svRecomputeOrder();
            SV.currentIdx = 0;
            svRender();
        }

        function svRender() {
            document.querySelectorAll('.sv-question').forEach(el => {
                el.classList.remove('flex', 'active');
                el.classList.add('hidden');
            });
            const qIdx = SV.visibleOrder[SV.currentIdx];
            const el = document.querySelectorAll('.sv-question')[qIdx];
            if (!el) return;
            el.classList.remove('hidden');
            el.classList.add('flex', 'active');

            const total = SV.visibleOrder.length;
            const pos = SV.currentIdx + 1;
            document.getElementById('sv-counter').textContent = pos + '/' + total;
            document.getElementById('sv-progress').style.width = Math.round(pos / total * 100) + '%';

            document.getElementById('sv-back').disabled = (SV.currentIdx === 0);

            const isLast = SV.currentIdx === total - 1;
            const nextBtn = document.getElementById('sv-next');
            const nextText = document.getElementById('sv-next-text');
            const nextIcon = document.getElementById('sv-next-icon');
            if (isLast) {
                nextText.textContent = 'Yuborish';
                nextIcon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>';
                nextBtn.classList.remove('sv-btn-primary');
                nextBtn.classList.add('sv-btn-success');
            } else {
                nextText.textContent = 'Keyingisi';
                nextIcon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/>';
                nextBtn.classList.add('sv-btn-primary');
                nextBtn.classList.remove('sv-btn-success');
            }

            const q = SV.questions[qIdx];
            const ans = SV.answers[q.id];
            if (q.type === 'radio') {
                el.querySelectorAll('input[type=radio]').forEach(inp => { inp.checked = false; });
                if (typeof ans === 'string') {
                    const optId = ans.startsWith('other:') ? 'other' : ans;
                    const inp = el.querySelector('input[type=radio][value="' + optId + '"]');
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
                el.querySelectorAll('input[type=checkbox]').forEach(inp => { inp.checked = false; });
                if (Array.isArray(ans)) {
                    ans.forEach(v => {
                        const optId = (typeof v === 'string' && v.startsWith('other:')) ? 'other' : v;
                        const inp = el.querySelector('input[type=checkbox][value="' + optId + '"]');
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
            svPaintSelected(el);
            el.querySelector('.sv-error')?.classList.add('hidden');
            window.scrollTo({top: 0, behavior: 'smooth'});
        }

        function svPaintSelected(qEl) {
            qEl.querySelectorAll('.sv-option').forEach(lb => {
                const inp = lb.querySelector('input');
                if (inp?.checked) lb.classList.add('selected');
                else lb.classList.remove('selected');
            });
        }

        function svCollect() {
            const qIdx = SV.visibleOrder[SV.currentIdx];
            const q = SV.questions[qIdx];
            const el = document.querySelectorAll('.sv-question')[qIdx];

            if (q.type === 'radio') {
                const checked = el.querySelector('input[type=radio]:checked');
                if (!checked) return false;
                const optId = checked.value;
                if (optId === 'other') {
                    const wrap = checked.closest('label').querySelector('.sv-other-wrap');
                    const txt = (wrap?.querySelector('.sv-other-input')?.value || '').trim();
                    if (!txt) return false;
                    SV.answers[q.id] = 'other:' + txt;
                } else {
                    SV.answers[q.id] = optId;
                }
            } else {
                const checks = Array.from(el.querySelectorAll('input[type=checkbox]:checked'));
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
                SV.answers[q.id] = vals;
            }
            return true;
        }

        function svNext() {
            if (!svCollect()) {
                const el = document.querySelectorAll('.sv-question')[SV.visibleOrder[SV.currentIdx]];
                const err = el.querySelector('.sv-error');
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
            const sub = document.getElementById('sv-submitting');
            sub.classList.remove('hidden');
            sub.classList.add('flex');

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
                sub.classList.add('hidden');
                sub.classList.remove('flex');
                if (status >= 200 && status < 300 && data.success) {
                    const ok = document.getElementById('sv-success');
                    ok.classList.remove('hidden');
                    ok.classList.add('flex');
                    document.getElementById('sv-success-msg').textContent = data.message || 'Javoblaringiz qabul qilindi.';
                } else {
                    document.getElementById('sv-error-box').classList.remove('hidden');
                    document.getElementById('sv-error-text').textContent = data.message || 'Xatolik yuz berdi.';
                    document.getElementById('sv-questions').classList.remove('hidden');
                    document.getElementById('sv-questions').classList.add('flex');
                    document.getElementById('sv-nav').classList.remove('hidden');
                }
            })
            .catch(() => {
                sub.classList.add('hidden');
                sub.classList.remove('flex');
                document.getElementById('sv-error-box').classList.remove('hidden');
                document.getElementById('sv-error-text').textContent = "So'rovni yuborib bo'lmadi. Internet ulanishini tekshiring.";
                document.getElementById('sv-questions').classList.remove('hidden');
                document.getElementById('sv-questions').classList.add('flex');
                document.getElementById('sv-nav').classList.remove('hidden');
            });
        }

        document.addEventListener('change', function (e) {
            if (e.target.matches('.sv-question input[type=radio], .sv-question input[type=checkbox]')) {
                const label = e.target.closest('label');
                if (!label) return;
                const wrap = label.querySelector('.sv-other-wrap');
                if (wrap) {
                    if (e.target.value === 'other' && e.target.checked) {
                        wrap.classList.remove('hidden');
                        setTimeout(() => wrap.querySelector('.sv-other-input')?.focus(), 100);
                    } else if (e.target.type === 'radio') {
                        label.closest('.sv-question').querySelectorAll('.sv-other-wrap').forEach(w => w.classList.add('hidden'));
                    } else if (!e.target.checked) {
                        wrap.classList.add('hidden');
                    }
                }
                svPaintSelected(label.closest('.sv-question'));
            }
        });
    </script>
    @endunless
</x-student-app-layout>
