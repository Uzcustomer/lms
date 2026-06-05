<x-student-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-sm text-gray-800 leading-tight">
            {{ __("So'rovnoma") }}
        </h2>
    </x-slot>

    @php
        $questionsForJs = $survey['questions'];
        $totalQuestions = count($questionsForJs);
    @endphp

    <div class="min-h-[calc(100vh-80px)] flex items-start justify-center px-3 py-4 sm:py-8 bg-slate-50">
        <div class="w-full max-w-2xl bg-white rounded-2xl shadow-lg overflow-hidden flex flex-col" style="min-height:75vh;">

            {{-- HEADER --}}
            <div class="px-5 sm:px-7 pt-5 pb-4 bg-gradient-to-br from-indigo-600 to-blue-600 text-white">
                <div class="flex items-center justify-between mb-3">
                    <h1 class="text-base sm:text-lg font-bold leading-snug pr-2">{{ $survey['title'] }}</h1>
                    <span id="sv-counter" class="text-xs font-bold bg-white/20 px-3 py-1 rounded-full whitespace-nowrap">1/{{ $totalQuestions }}</span>
                </div>
                {{-- Progress bar --}}
                <div class="h-2 w-full bg-white/20 rounded-full overflow-hidden">
                    <div id="sv-progress" class="h-full bg-gradient-to-r from-emerald-400 to-green-300 transition-all duration-500" style="width: {{ round(100 / $totalQuestions) }}%"></div>
                </div>
            </div>

            @if($alreadyCompleted)
                {{-- Allaqachon bajarilgan --}}
                <div class="flex-1 flex flex-col items-center justify-center text-center p-8">
                    <div class="w-16 h-16 rounded-full bg-emerald-100 flex items-center justify-center mb-4">
                        <svg class="w-9 h-9 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>
                    <h3 class="text-lg font-bold text-slate-800 mb-2">Rahmat!</h3>
                    <p class="text-sm text-slate-600 max-w-sm">Siz bu so'rovnomani allaqachon bajargansiz. Vaqtingiz uchun rahmat.</p>
                    <a href="{{ route('student.profile') }}" class="mt-6 inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-lg transition">
                        Profilga qaytish
                    </a>
                </div>
            @else
                {{-- KIRISH SAHIFA --}}
                <div id="sv-intro" class="flex-1 px-5 sm:px-7 py-5 flex flex-col">
                    <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 mb-4 flex items-start gap-2">
                        <svg class="w-5 h-5 text-amber-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                        </svg>
                        <p class="text-xs text-amber-800 leading-relaxed">Bu so'rovnoma <strong>anonim</strong> — javoblaringiz hech kimga ko'rinmaydi.</p>
                    </div>
                    <div class="text-sm text-slate-700 leading-relaxed whitespace-pre-line mb-6">{{ $survey['description'] }}</div>
                    <div class="flex-1"></div>
                    <button type="button" onclick="svStart()" class="w-full py-3.5 bg-gradient-to-r from-indigo-600 to-blue-600 hover:from-indigo-700 hover:to-blue-700 text-white text-sm font-bold rounded-xl shadow-sm transition">
                        So'rovnomani boshlash
                    </button>
                    @if(!$deadlinePassed)
                    <a href="{{ route('student.profile') }}" class="mt-3 block text-center text-sm text-slate-500 hover:text-slate-700 py-2">
                        Keyinroq bajarish
                    </a>
                    @endif
                </div>

                {{-- SAVOLLAR — har biri alohida step --}}
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
                            <div class="mb-4">
                                <div class="text-[11px] font-bold uppercase tracking-wide text-indigo-600 mb-1">
                                    Savol {{ $q['id'] }}
                                </div>
                                <h3 class="text-base sm:text-lg font-semibold text-slate-800 leading-snug">{{ $q['text'] }}</h3>
                                @if($q['type'] === 'checkbox')
                                    <p class="text-xs text-slate-500 mt-1.5">Bir nechtasini tanlash mumkin</p>
                                @endif
                            </div>

                            <div class="space-y-2.5 flex-1">
                                @foreach($q['options'] as $opt)
                                    <label class="sv-option block border-2 border-slate-200 rounded-xl p-3.5 cursor-pointer transition hover:border-indigo-300 hover:bg-indigo-50/30">
                                        <div class="flex items-start gap-3">
                                            @if($q['type'] === 'radio')
                                                <input type="radio" name="q_{{ $q['id'] }}" value="{{ $opt['id'] }}"
                                                       class="mt-0.5 w-5 h-5 accent-indigo-600 flex-shrink-0">
                                            @else
                                                <input type="checkbox" name="q_{{ $q['id'] }}[]" value="{{ $opt['id'] }}"
                                                       class="mt-0.5 w-5 h-5 accent-indigo-600 flex-shrink-0">
                                            @endif
                                            <span class="text-sm text-slate-700 leading-snug flex-1">{{ $opt['text'] }}</span>
                                        </div>
                                        @if(!empty($opt['has_other']))
                                            <div class="sv-other-wrap hidden mt-3 ml-8">
                                                <input type="text" class="sv-other-input w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 outline-none"
                                                       placeholder="Iltimos, izoh yozing..."
                                                       data-opt="{{ $opt['id'] }}">
                                            </div>
                                        @endif
                                    </label>
                                @endforeach
                            </div>

                            <div class="sv-error hidden text-xs text-red-600 font-medium mt-3 px-1">⚠ Iltimos, javob tanlang</div>
                        </div>
                    @endforeach
                </div>

                {{-- NAVIGATSIYA --}}
                <div id="sv-nav" class="hidden border-t border-slate-200 px-5 sm:px-7 py-3.5 bg-slate-50/70 flex items-center gap-3">
                    <button id="sv-back" type="button" onclick="svBack()" class="flex-1 py-2.5 text-sm font-semibold text-slate-700 bg-white border border-slate-300 hover:bg-slate-100 rounded-lg transition disabled:opacity-40">
                        ← Orqaga
                    </button>
                    <button id="sv-next" type="button" onclick="svNext()" class="flex-[1.5] py-2.5 text-sm font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg transition">
                        Keyingisi →
                    </button>
                </div>

                {{-- YUBORISH OYNASI --}}
                <div id="sv-submitting" class="hidden flex-1 flex-col items-center justify-center p-8 text-center">
                    <div class="w-12 h-12 border-4 border-indigo-200 border-t-indigo-600 rounded-full animate-spin mb-4"></div>
                    <p class="text-sm text-slate-600">Javoblar yuborilmoqda...</p>
                </div>

                <div id="sv-success" class="hidden flex-1 flex-col items-center justify-center text-center p-8">
                    <div class="w-16 h-16 rounded-full bg-emerald-100 flex items-center justify-center mb-4">
                        <svg class="w-9 h-9 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>
                    <h3 class="text-lg font-bold text-slate-800 mb-2">Rahmat!</h3>
                    <p class="text-sm text-slate-600 max-w-sm" id="sv-success-msg">Javoblaringiz qabul qilindi.</p>
                    <a href="{{ route('student.profile') }}" class="mt-6 inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-lg transition">
                        Profilga qaytish
                    </a>
                </div>

                <div id="sv-error-box" class="hidden border-t border-red-200 px-5 sm:px-7 py-3 bg-red-50">
                    <p class="text-sm text-red-700" id="sv-error-text"></p>
                </div>
            @endif
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

        function svRecomputeOrder() {
            // Conditional savollar — show_if shartiga ko'ra ko'rinadigan tartib
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
            // Barchasini yashirish
            document.querySelectorAll('.sv-question').forEach(el => {
                el.classList.remove('flex');
                el.classList.add('hidden');
            });
            // Joriyni ko'rsatish
            const qIdx = SV.visibleOrder[SV.currentIdx];
            const el = document.querySelectorAll('.sv-question')[qIdx];
            if (!el) return;
            el.classList.remove('hidden');
            el.classList.add('flex');

            // Counter va progress
            const total = SV.visibleOrder.length;
            const pos = SV.currentIdx + 1;
            document.getElementById('sv-counter').textContent = pos + '/' + total;
            document.getElementById('sv-progress').style.width = Math.round(pos / total * 100) + '%';

            // Back tugmasi
            document.getElementById('sv-back').disabled = (SV.currentIdx === 0);

            // Next tugmasi: oxirgi savolda "Yuborish" matni
            const nextBtn = document.getElementById('sv-next');
            nextBtn.textContent = (SV.currentIdx === total - 1) ? "Yuborish ✓" : "Keyingisi →";
            nextBtn.classList.toggle('bg-emerald-600', SV.currentIdx === total - 1);
            nextBtn.classList.toggle('hover:bg-emerald-700', SV.currentIdx === total - 1);
            nextBtn.classList.toggle('bg-indigo-600', SV.currentIdx !== total - 1);
            nextBtn.classList.toggle('hover:bg-indigo-700', SV.currentIdx !== total - 1);

            // Avval kiritilgan javobni qayta tiklash
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

            el.querySelector('.sv-error')?.classList.add('hidden');
            window.scrollTo({top: 0, behavior: 'smooth'});
        }

        function svCollect() {
            // Joriy savol javobini SV.answers ga qo'shish; agar tanlanmagan bo'lsa false qaytarish
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
                el.querySelector('.sv-error')?.classList.remove('hidden');
                return;
            }
            // Conditional yangilash
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
                svCollect(); // Joriy javobni ham saqlash (xato bo'lsa ham OK)
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

        // "Boshqa" input qayta ko'rinish/ko'rsatilmaslik
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
                        // radio: agar boshqa option tanlandi → others ni yopish
                        label.closest('.sv-question').querySelectorAll('.sv-other-wrap').forEach(w => w.classList.add('hidden'));
                    } else if (!e.target.checked) {
                        wrap.classList.add('hidden');
                    }
                }
                // Tanlangan label vizual ajratish
                label.closest('.sv-question').querySelectorAll('.sv-option').forEach(lb => {
                    const inp = lb.querySelector('input');
                    if (inp?.checked) {
                        lb.classList.add('border-indigo-500', 'bg-indigo-50');
                        lb.classList.remove('border-slate-200');
                    } else {
                        lb.classList.remove('border-indigo-500', 'bg-indigo-50');
                        lb.classList.add('border-slate-200');
                    }
                });
            }
        });
    </script>
    @endunless
</x-student-app-layout>
