<x-app-layout>
    @php
        $questions = $test?->questions ?? collect();
        $correctOptionNumber = function ($question) {
            $correct = $question->options->firstWhere('is_correct', true);
            return $correct ? ($correct->sort_order ?: 1) : 1;
        };
        $optionsText = function ($question) {
            return $question->options->pluck('option_text')->implode("\n");
        };
    @endphp

    <div class="py-6" x-data>
        <div class="w-full px-4 sm:px-6 lg:px-8 space-y-6">
            <style>
                .tb-card { background:#fff; border:1px solid #dbe4ef; border-radius:24px; box-shadow:0 10px 30px rgba(15,23,42,.06); }
                .tb-head { padding:20px 24px; border-bottom:1px solid #e8edf5; background:linear-gradient(135deg,#f4f8fc,#e7eef8); border-top-left-radius:24px; border-top-right-radius:24px; }
                .tb-input, .tb-textarea, .tb-select {
                    width:100%; border:1px solid #cbd5e1; border-radius:12px; padding:10px 12px; font-size:14px; color:#0f172a; background:#fff;
                }
                .tb-textarea { min-height:110px; resize:vertical; }
                .tb-input:focus, .tb-textarea:focus, .tb-select:focus {
                    outline:none; border-color:#3b82f6; box-shadow:0 0 0 3px rgba(59,130,246,.14);
                }
                .tb-label { display:block; margin-bottom:6px; font-size:12px; font-weight:800; text-transform:uppercase; letter-spacing:.05em; color:#475569; }
                .tb-btn { display:inline-flex; align-items:center; justify-content:center; gap:8px; border-radius:12px; padding:10px 16px; font-size:13px; font-weight:800; transition:all .18s ease; }
                .tb-btn:hover { transform:translateY(-1px); }
                .tb-btn-primary { background:linear-gradient(135deg,#2b5ea7,#3b7ddb); color:#fff; box-shadow:0 10px 24px rgba(43,94,167,.22); }
                .tb-btn-green { background:linear-gradient(135deg,#059669,#10b981); color:#fff; box-shadow:0 10px 24px rgba(5,150,105,.18); }
                .tb-btn-red { background:linear-gradient(135deg,#dc2626,#ef4444); color:#fff; box-shadow:0 10px 24px rgba(220,38,38,.18); }
                .tb-btn-light { background:#fff; color:#334155; border:1px solid #cbd5e1; }
                .tb-stat { border:1px solid #dbe4ef; border-radius:18px; padding:16px 18px; }
                .tb-stat.blue { background:linear-gradient(135deg,#eff6ff,#dbeafe); }
                .tb-stat.green { background:linear-gradient(135deg,#ecfdf5,#d1fae5); }
                .tb-stat.orange { background:linear-gradient(135deg,#fff7ed,#ffedd5); }
                .tb-question { border:1px solid #dbe4ef; border-radius:20px; overflow:hidden; background:#fff; }
                .tb-question-head { padding:16px 18px; background:linear-gradient(135deg,#f8fafc,#f1f5f9); border-bottom:1px solid #e2e8f0; }
            </style>

            @if(session('success'))
                <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-emerald-700 shadow-sm">
                    {{ session('success') }}
                </div>
            @endif

            @if($errors->any())
                <div class="rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-red-700 shadow-sm">
                    <div class="font-semibold mb-2">Formada xatolik bor.</div>
                    <ul class="list-disc pl-5 space-y-1 text-sm">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="tb-card">
                <div class="tb-head">
                    <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                        <div class="space-y-3">
                            <div class="flex flex-wrap gap-2">
                                <span class="inline-flex items-center rounded-full border border-blue-200 bg-blue-50 px-3 py-1 text-xs font-bold text-blue-700">{{ $testSubject->name }}</span>
                                <span class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700">{{ $lesson->topic_order }}-mavzu</span>
                                <span class="inline-flex items-center rounded-full border border-orange-200 bg-orange-50 px-3 py-1 text-xs font-bold text-orange-700">{{ optional($lesson->lesson_date)->format('d.m.Y') ?: '-' }}</span>
                            </div>
                            <div>
                                <h1 class="text-3xl font-extrabold text-slate-900">{{ $lesson->topic_title ?: ($lesson->topic_order . '-mavzu') }}</h1>
                                <p class="mt-2 text-sm text-slate-600 max-w-4xl">
                                    Shu mavzu uchun test yarating, savollarni kiriting va kerak paytda testni talabalar uchun oching.
                                </p>
                            </div>
                        </div>
                        <a href="{{ route('teacher.test-subjects.show', $testSubject) }}" class="tb-btn tb-btn-light">
                            Orqaga
                        </a>
                    </div>
                </div>

                <div class="p-6">
                    <div class="flex flex-col lg:flex-row" style="gap: 10px;">
                        <div class="tb-stat blue flex-1">
                            <div class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-500">Test holati</div>
                            <div class="mt-2 text-sm font-semibold text-slate-900">{{ $test ? 'Yaratilgan' : 'Hali yaratilmagan' }}</div>
                        </div>
                        <div class="tb-stat green flex-1">
                            <div class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-500">Savollar</div>
                            <div class="mt-2 text-3xl font-extrabold text-slate-900">{{ $questions->count() }}</div>
                        </div>
                        <div class="tb-stat orange flex-1">
                            <div class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-500">Ochiq holat</div>
                            <div class="mt-2 text-sm font-semibold text-slate-900">{{ $test?->is_open ? 'Ochiq' : 'Yopiq' }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 2xl:grid-cols-3 gap-6">
                <div class="tb-card 2xl:col-span-1">
                    <div class="tb-head">
                        <h2 class="text-lg font-bold text-slate-900">Test sozlamalari</h2>
                        <p class="mt-1 text-sm text-slate-500">Testning asosiy parametrlari va ochiq/yopiq holati.</p>
                    </div>
                    <div class="p-6">
                        <form method="POST" action="{{ route('teacher.test-subjects.tests.upsert', [$testSubject, $lesson]) }}" class="space-y-4">
                            @csrf

                            <div>
                                <label class="tb-label">Test nomi</label>
                                <input type="text" name="title" class="tb-input" value="{{ old('title', $test?->title ?? (($lesson->topic_title ?: 'Mavzu testi') . ' testi')) }}" required>
                            </div>

                            <div>
                                <label class="tb-label">Ko‘rsatma</label>
                                <textarea name="description" class="tb-textarea" placeholder="Talabaga ko‘rinadigan ko‘rsatma...">{{ old('description', $test?->description) }}</textarea>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="tb-label">Davomiyligi (daq.)</label>
                                    <input type="number" min="1" max="300" name="duration_minutes" class="tb-input" value="{{ old('duration_minutes', $test?->duration_minutes ?? 20) }}" required>
                                </div>
                                <div>
                                    <label class="tb-label">O‘tish foizi</label>
                                    <input type="number" min="1" max="100" name="pass_percent" class="tb-input" value="{{ old('pass_percent', $test?->pass_percent ?? 60) }}">
                                </div>
                            </div>

                            <div class="space-y-3">
                                <label class="flex items-center gap-3 text-sm text-slate-700">
                                    <input type="checkbox" name="shuffle_questions" value="1" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500" {{ old('shuffle_questions', $test?->shuffle_questions) ? 'checked' : '' }}>
                                    Savollar aralashtirilsin
                                </label>
                                <label class="flex items-center gap-3 text-sm text-slate-700">
                                    <input type="checkbox" name="show_result_after_submit" value="1" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500" {{ old('show_result_after_submit', $test?->show_result_after_submit ?? true) ? 'checked' : '' }}>
                                    Natija topshirgandan keyin ko‘rinsin
                                </label>
                                <label class="flex items-center gap-3 text-sm text-slate-700">
                                    <input type="checkbox" name="is_published" value="1" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500" {{ old('is_published', $test?->is_published) ? 'checked' : '' }}>
                                    Test nashr qilinsin
                                </label>
                                <label class="flex items-center gap-3 text-sm text-slate-700">
                                    <input type="checkbox" name="is_open" value="1" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500" {{ old('is_open', $test?->is_open) ? 'checked' : '' }}>
                                    Hozir test ochiq bo‘lsin
                                </label>
                            </div>

                            <button type="submit" class="tb-btn tb-btn-primary w-full">
                                Test sozlamalarini saqlash
                            </button>
                        </form>
                    </div>
                </div>

                <div class="tb-card 2xl:col-span-2">
                    <div class="tb-head">
                        <h2 class="text-lg font-bold text-slate-900">Yangi savol qo‘shish</h2>
                        <p class="mt-1 text-sm text-slate-500">Multiple choice yoki fill in blank savol yarating.</p>
                    </div>
                    <div class="p-6">
                        @if(!$test)
                            <div class="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-amber-700">
                                Avval test sozlamalarini saqlang. Shundan keyin savol qo‘shish ochiladi.
                            </div>
                        @else
                            <form method="POST" action="{{ route('teacher.test-subjects.tests.questions.store', [$testSubject, $lesson]) }}" class="space-y-4" x-data="{ type: '{{ old('type', 'single_choice') }}' }">
                                @csrf

                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                    <div>
                                        <label class="tb-label">Savol turi</label>
                                        <select name="type" class="tb-select" x-model="type">
                                            <option value="single_choice">Multiple choice</option>
                                            <option value="fill_in_blank">Fill in blank</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="tb-label">Ball</label>
                                        <input type="number" min="1" max="100" name="points" class="tb-input" value="{{ old('points', 1) }}" required>
                                    </div>
                                    <div class="flex items-end">
                                        <label class="flex items-center gap-3 text-sm text-slate-700 pb-2">
                                            <input type="checkbox" name="is_active" value="1" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500" {{ old('is_active', '1') ? 'checked' : '' }}>
                                            Savol faol bo‘lsin
                                        </label>
                                    </div>
                                </div>

                                <div>
                                    <label class="tb-label">Savol matni</label>
                                    <textarea name="prompt" class="tb-textarea" required>{{ old('prompt') }}</textarea>
                                </div>

                                <div>
                                    <label class="tb-label">Yordamchi izoh</label>
                                    <textarea name="helper_text" class="tb-textarea" style="min-height:80px;">{{ old('helper_text') }}</textarea>
                                </div>

                                <div x-show="type === 'single_choice'" x-cloak class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                                    <div>
                                        <label class="tb-label">Variantlar</label>
                                        <textarea name="options_text" class="tb-textarea" placeholder="Har bir variantni yangi qatordan yozing">{{ old('options_text') }}</textarea>
                                    </div>
                                    <div>
                                        <label class="tb-label">To‘g‘ri variant raqami</label>
                                        <input type="number" min="1" name="correct_option_number" class="tb-input" value="{{ old('correct_option_number', 1) }}">
                                        <p class="mt-2 text-xs text-slate-500">Masalan, to‘g‘ri javob 2-variant bo‘lsa `2` deb yozing.</p>
                                    </div>
                                </div>

                                <div x-show="type === 'fill_in_blank'" x-cloak class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                                    <div>
                                        <label class="tb-label">To‘g‘ri javob</label>
                                        <input type="text" name="correct_answer_text" class="tb-input" value="{{ old('correct_answer_text') }}">
                                    </div>
                                    <div class="flex items-end">
                                        <label class="flex items-center gap-3 text-sm text-slate-700 pb-2">
                                            <input type="checkbox" name="case_sensitive" value="1" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500" {{ old('case_sensitive') ? 'checked' : '' }}>
                                            Harf kattaligi farq qilsin
                                        </label>
                                    </div>
                                </div>

                                <button type="submit" class="tb-btn tb-btn-green">
                                    Savol qo‘shish
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>

            <div class="space-y-5">
                <div>
                    <h2 class="text-xl font-bold text-slate-900">Mavjud savollar</h2>
                    <p class="mt-1 text-sm text-slate-500">Har bir savolni tahrirlash, variantlarini almashtirish yoki o‘chirish mumkin.</p>
                </div>

                @forelse($questions as $question)
                    <div class="tb-question" x-data="{ open: false, type: '{{ old('question_type_' . $question->id, $question->type) }}' }">
                        <div class="tb-question-head flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="inline-flex items-center rounded-full border border-blue-200 bg-blue-50 px-3 py-1 text-[11px] font-bold text-blue-700">Savol {{ $question->sort_order }}</span>
                                    <span class="inline-flex items-center rounded-full border px-3 py-1 text-[11px] font-bold {{ $question->type === 'single_choice' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-orange-200 bg-orange-50 text-orange-700' }}">
                                        {{ $question->type === 'single_choice' ? 'Multiple choice' : 'Fill in blank' }}
                                    </span>
                                    <span class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-[11px] font-bold text-slate-700">{{ $question->points }} ball</span>
                                </div>
                                <div class="mt-3 text-base font-bold text-slate-900">{{ $question->prompt }}</div>
                                @if($question->helper_text)
                                    <div class="mt-1 text-sm text-slate-500">{{ $question->helper_text }}</div>
                                @endif
                            </div>

                            <div class="flex items-center gap-2">
                                <button type="button" @click="open = !open" class="tb-btn tb-btn-light">
                                    <span x-text="open ? 'Yopish' : 'Tahrirlash'"></span>
                                </button>
                                <form method="POST" action="{{ route('teacher.test-subjects.tests.questions.destroy', [$testSubject, $lesson, $question]) }}" onsubmit="return confirm('Savolni o‘chirasizmi?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="tb-btn tb-btn-red">O‘chirish</button>
                                </form>
                            </div>
                        </div>

                        <div class="p-6 border-t border-slate-100" x-show="open" x-cloak>
                            <form method="POST" action="{{ route('teacher.test-subjects.tests.questions.update', [$testSubject, $lesson, $question]) }}" class="space-y-4">
                                @csrf
                                @method('PUT')

                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                    <div>
                                        <label class="tb-label">Savol turi</label>
                                        <select name="type" class="tb-select" x-model="type">
                                            <option value="single_choice">Multiple choice</option>
                                            <option value="fill_in_blank">Fill in blank</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="tb-label">Ball</label>
                                        <input type="number" min="1" max="100" name="points" class="tb-input" value="{{ old('points', $question->points) }}" required>
                                    </div>
                                    <div class="flex items-end">
                                        <label class="flex items-center gap-3 text-sm text-slate-700 pb-2">
                                            <input type="checkbox" name="is_active" value="1" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500" {{ old('is_active', $question->is_active) ? 'checked' : '' }}>
                                            Savol faol
                                        </label>
                                    </div>
                                </div>

                                <div>
                                    <label class="tb-label">Savol matni</label>
                                    <textarea name="prompt" class="tb-textarea" required>{{ old('prompt', $question->prompt) }}</textarea>
                                </div>

                                <div>
                                    <label class="tb-label">Yordamchi izoh</label>
                                    <textarea name="helper_text" class="tb-textarea" style="min-height:80px;">{{ old('helper_text', $question->helper_text) }}</textarea>
                                </div>

                                <div x-show="type === 'single_choice'" x-cloak class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                                    <div>
                                        <label class="tb-label">Variantlar</label>
                                        <textarea name="options_text" class="tb-textarea">{{ old('options_text', $optionsText($question)) }}</textarea>
                                    </div>
                                    <div>
                                        <label class="tb-label">To‘g‘ri variant raqami</label>
                                        <input type="number" min="1" name="correct_option_number" class="tb-input" value="{{ old('correct_option_number', $correctOptionNumber($question)) }}">
                                    </div>
                                </div>

                                <div x-show="type === 'fill_in_blank'" x-cloak class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                                    <div>
                                        <label class="tb-label">To‘g‘ri javob</label>
                                        <input type="text" name="correct_answer_text" class="tb-input" value="{{ old('correct_answer_text', $question->correct_answer_text) }}">
                                    </div>
                                    <div class="flex items-end">
                                        <label class="flex items-center gap-3 text-sm text-slate-700 pb-2">
                                            <input type="checkbox" name="case_sensitive" value="1" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500" {{ old('case_sensitive', $question->case_sensitive) ? 'checked' : '' }}>
                                            Harf kattaligi farq qilsin
                                        </label>
                                    </div>
                                </div>

                                @if($question->type === 'single_choice' && $question->options->count())
                                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                                        <div class="text-sm font-semibold text-slate-900 mb-2">Hozirgi variantlar</div>
                                        <div class="space-y-2">
                                            @foreach($question->options as $option)
                                                <div class="flex items-center gap-2 text-sm">
                                                    <span class="inline-flex min-w-[24px] items-center justify-center rounded-full bg-slate-200 px-2 py-1 text-xs font-bold text-slate-700">{{ $option->sort_order }}</span>
                                                    <span class="{{ $option->is_correct ? 'font-bold text-emerald-700' : 'text-slate-700' }}">{{ $option->option_text }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                <button type="submit" class="tb-btn tb-btn-primary">
                                    Savolni yangilash
                                </button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="rounded-2xl border border-slate-200 bg-white px-6 py-10 text-center text-slate-500 shadow-sm">
                        Bu mavzu uchun hali savollar kiritilmagan.
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
