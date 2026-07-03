<x-app-layout>
    @php
        $questions = $test?->questions ?? collect();
        $alphabetLabel = function (int $index): string {
            $letters = range('a', 'z');
            return ($letters[$index] ?? ('v' . ($index + 1))) . ')';
        };
        $questionOptions = function ($question) {
            $options = $question->options
                ->sortBy('sort_order')
                ->values()
                ->map(fn ($option) => [
                    'text' => $option->option_text,
                    'text_ru' => data_get($option->option_text_translations, 'ru', ''),
                    'text_en' => data_get($option->option_text_translations, 'en', ''),
                ])
                ->all();

            while (count($options) < 3) {
                $options[] = ['text' => '', 'text_ru' => '', 'text_en' => ''];
            }

            return $options;
        };
        $correctOptionNumber = function ($question) {
            $correct = $question->options->firstWhere('is_correct', true);
            return $correct ? ($correct->sort_order ?: 1) : 1;
        };
        $tr = function ($array, string $key): string {
            return (string) data_get($array, $key, '');
        };
    @endphp

    <div class="py-6">
        <div class="w-full px-4 sm:px-6 lg:px-8 space-y-5">
            <style>
                .tb-card { background:#fff; border:1px solid #dbe4ef; border-radius:22px; box-shadow:0 10px 28px rgba(15,23,42,.06); }
                .tb-head { padding:14px 18px; border-bottom:1px solid #e8edf5; background:linear-gradient(135deg,#f4f8fc,#e7eef8); border-top-left-radius:22px; border-top-right-radius:22px; }
                .tb-section { padding:16px 18px; }
                .tb-chip { display:inline-flex; align-items:center; padding:6px 12px; border-radius:999px; font-size:12px; font-weight:800; border:1px solid transparent; }
                .tb-chip.blue { background:#eff6ff; color:#1d4ed8; border-color:#bfdbfe; }
                .tb-chip.green { background:#ecfdf5; color:#15803d; border-color:#bbf7d0; }
                .tb-chip.orange { background:#fff7ed; color:#c2410c; border-color:#fdba74; }
                .tb-chip.gray { background:#f8fafc; color:#475569; border-color:#cbd5e1; }
                .tb-input, .tb-textarea, .tb-select {
                    width:100%;
                    border:1px solid #cbd5e1;
                    border-radius:10px;
                    padding:9px 11px;
                    font-size:14px;
                    color:#0f172a;
                    background:#fff;
                }
                .tb-textarea { min-height:82px; resize:vertical; }
                .tb-preview-image { width:100%; max-height:220px; object-fit:contain; border-radius:14px; border:1px solid #dbe4ef; background:#f8fafc; }
                .tb-input:focus, .tb-textarea:focus, .tb-select:focus {
                    outline:none;
                    border-color:#3b82f6;
                    box-shadow:0 0 0 3px rgba(59,130,246,.14);
                }
                .tb-label {
                    display:block;
                    margin-bottom:5px;
                    font-size:11px;
                    font-weight:800;
                    text-transform:uppercase;
                    letter-spacing:.05em;
                    color:#475569;
                }
                .tb-btn {
                    display:inline-flex;
                    align-items:center;
                    justify-content:center;
                    gap:8px;
                    border-radius:10px;
                    padding:9px 14px;
                    font-size:13px;
                    font-weight:800;
                    transition:all .18s ease;
                }
                .tb-btn:hover { transform:translateY(-1px); }
                .tb-btn-primary { background:linear-gradient(135deg,#2b5ea7,#3b7ddb); color:#fff; box-shadow:0 8px 20px rgba(43,94,167,.22); }
                .tb-btn-green { background:linear-gradient(135deg,#059669,#10b981); color:#fff; box-shadow:0 8px 20px rgba(5,150,105,.18); }
                .tb-btn-red { background:linear-gradient(135deg,#dc2626,#ef4444); color:#fff; box-shadow:0 8px 20px rgba(220,38,38,.18); }
                .tb-btn-light { background:#fff; color:#334155; border:1px solid #cbd5e1; }
                .tb-btn-ghost { background:#f8fafc; color:#334155; border:1px dashed #cbd5e1; }
                .tb-stat { flex:1 1 0; min-width:180px; border:1px solid #dbe4ef; border-radius:16px; padding:14px 16px; }
                .tb-stat.blue { background:linear-gradient(135deg,#eff6ff,#dbeafe); }
                .tb-stat.green { background:linear-gradient(135deg,#ecfdf5,#d1fae5); }
                .tb-stat.orange { background:linear-gradient(135deg,#fff7ed,#ffedd5); }
                .tb-split { display:flex; gap:12px; flex-wrap:wrap; align-items:flex-start; }
                .tb-pane { min-width:0; }
                .tb-soft-box { border:1px dashed #cbd5e1; border-radius:14px; background:#f8fafc; padding:12px; }
                .tb-inline { display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end; }
                .tb-col { min-width:0; }
                .tb-option-row { display:flex; align-items:center; gap:10px; }
                .tb-option-prefix {
                    min-width:34px;
                    height:38px;
                    border-radius:10px;
                    display:flex;
                    align-items:center;
                    justify-content:center;
                    background:#f8fafc;
                    border:1px solid #e2e8f0;
                    font-size:12px;
                    font-weight:800;
                    color:#475569;
                }
                .tb-option-remove {
                    width:38px;
                    height:38px;
                    border-radius:10px;
                    display:inline-flex;
                    align-items:center;
                    justify-content:center;
                    border:1px solid #fecaca;
                    background:#fef2f2;
                    color:#dc2626;
                    font-weight:800;
                }
                .tb-question { border:1px solid #dbe4ef; border-radius:18px; overflow:hidden; background:#fff; }
                .tb-question-head { padding:14px 18px; background:linear-gradient(135deg,#f8fafc,#f1f5f9); border-bottom:1px solid #e2e8f0; }
                .tb-question-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:14px; align-items:start; }
                @media (max-width: 1200px) {
                    .tb-question-grid { grid-template-columns:repeat(2,minmax(0,1fr)); }
                }
                @media (max-width: 900px) {
                    .tb-split { flex-direction:column; }
                    .tb-inline { flex-direction:column; align-items:stretch; }
                    .tb-option-row { flex-wrap:wrap; }
                    .tb-question-grid { grid-template-columns:1fr; }
                }
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

            <div>
                <a href="{{ route('teacher.test-subjects.show', $testSubject) }}" class="tb-btn tb-btn-light" style="padding:6px 11px; font-size:12px; width:auto;">Orqaga</a>
            </div>

            <div class="tb-card">
                <div class="tb-head">
                    <div class="space-y-3">
                        <div class="space-y-3">
                            <div class="flex flex-wrap gap-2">
                                <span class="tb-chip blue">{{ $testSubject->name }}</span>
                                <span class="tb-chip green">{{ $lesson->topic_order }}-mavzu</span>
                                <span class="tb-chip orange">{{ optional($lesson->lesson_date)->format('d.m.Y') ?: '-' }}</span>
                                <span class="tb-chip gray">{{ $lesson->starts_at ? substr($lesson->starts_at, 0, 5) : '--:--' }} - {{ $lesson->ends_at ? substr($lesson->ends_at, 0, 5) : '--:--' }}</span>
                            </div>
                            <div>
                                <h1 class="text-2xl font-extrabold text-slate-900">{{ $lesson->topic_title ?: ($lesson->topic_order . '-mavzu') }}</h1>
                                <p class="mt-1 text-sm text-slate-600">Bu mavzu uchun test yarating va savollarni boshqaring.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="tb-section">
                    <div style="display:flex; gap:10px; flex-wrap:wrap;">
                        <div class="tb-stat blue">
                            <div class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-500">Savollar</div>
                            <div class="mt-2 text-3xl font-extrabold text-slate-900">{{ $questions->count() }}</div>
                        </div>
                        <div class="tb-stat green">
                            <div class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-500">Test holati</div>
                            <div class="mt-2 text-sm font-semibold text-slate-900">{{ $test ? 'Draft / tayyor' : 'Yaratilmoqda' }}</div>
                        </div>
                        <div class="tb-stat orange">
                            <div class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-500">Ochiq holat</div>
                            <div class="mt-2 text-sm font-semibold text-slate-900">{{ $test?->is_open ? 'Ochiq' : 'Yopiq' }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="tb-split">
                <div class="tb-card tb-pane" style="flex:0 0 350px; width:350px; max-width:100%;">
                    <div class="tb-head">
                        <h2 class="text-lg font-bold text-slate-900">Test sozlamalari</h2>
                        <p class="mt-1 text-sm text-slate-500">Savol yaratishdan alohida boshqariladi.</p>
                    </div>
                    <div class="tb-section">
                        <form method="POST" action="{{ route('teacher.test-subjects.tests.upsert', [$testSubject, $lesson]) }}" class="space-y-4">
                            @csrf

                            <div>
                                <label class="tb-label">Test nomi</label>
                                <input type="text" name="title" class="tb-input" value="{{ old('title', $test?->title ?? (($lesson->topic_title ?: 'Mavzu testi') . ' testi')) }}" required>
                            </div>

                            <div>
                                <label class="tb-label">Qisqa ko'rsatma</label>
                                <textarea name="description" class="tb-textarea" style="min-height:70px;" placeholder="Talabaga ko'rinadigan qisqa ko'rsatma...">{{ old('description', $test?->description) }}</textarea>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="tb-label">Davomiyligi</label>
                                    <input type="number" min="1" max="300" name="duration_minutes" class="tb-input" value="{{ old('duration_minutes', $test?->duration_minutes ?? 20) }}" required>
                                </div>
                                <div>
                                    <label class="tb-label">O'tish foizi</label>
                                    <input type="number" min="1" max="100" name="pass_percent" class="tb-input" value="{{ old('pass_percent', $test?->pass_percent ?? 60) }}">
                                </div>
                            </div>

                            <div class="grid grid-cols-1 gap-3">
                                <label class="flex items-center gap-3 text-sm text-slate-700">
                                    <input type="checkbox" name="shuffle_questions" value="1" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500" {{ old('shuffle_questions', $test?->shuffle_questions) ? 'checked' : '' }}>
                                    Savollar aralashsin
                                </label>
                                <label class="flex items-center gap-3 text-sm text-slate-700">
                                    <input type="checkbox" name="show_result_after_submit" value="1" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500" {{ old('show_result_after_submit', $test?->show_result_after_submit ?? true) ? 'checked' : '' }}>
                                    Natija ko'rinsin
                                </label>
                                <label class="flex items-center gap-3 text-sm text-slate-700">
                                    <input type="checkbox" name="is_published" value="1" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500" {{ old('is_published', $test?->is_published) ? 'checked' : '' }}>
                                    Nashr qilingan
                                </label>
                                <label class="flex items-center gap-3 text-sm text-slate-700">
                                    <input type="checkbox" name="is_open" value="1" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500" {{ old('is_open', $test?->is_open) ? 'checked' : '' }}>
                                    Test ochiq
                                </label>
                            </div>

                            <button type="submit" class="tb-btn tb-btn-primary w-full">Sozlamalarni saqlash</button>
                        </form>
                    </div>
                </div>

                <div class="tb-card tb-pane" style="flex:1 1 760px; min-width:0;"
                     x-data="questionBuilder({
                        type: '{{ old('type', 'single_choice') }}',
                        options: @js(old('options', [['text' => '', 'text_ru' => '', 'text_en' => ''], ['text' => '', 'text_ru' => '', 'text_en' => ''], ['text' => '', 'text_ru' => '', 'text_en' => '']])),
                        correctOption: {{ (int) old('correct_option_number', 1) }}
                     })">
                    <div class="tb-head">
                        <h2 class="text-lg font-bold text-slate-900">Yangi savol qo'shish</h2>
                        <p class="mt-1 text-sm text-slate-500">Multiple choice va fill in blank savollarni shu yerning o'zida yarating.</p>
                    </div>
                    <div class="tb-section">
                        <form method="POST" action="{{ route('teacher.test-subjects.tests.questions.store', [$testSubject, $lesson]) }}" class="space-y-4" enctype="multipart/form-data">
                            @csrf

                            <div class="tb-inline">
                                <div class="tb-col" style="flex:1 1 240px;">
                                    <label class="tb-label">Savol turi</label>
                                    <select name="type" class="tb-select" x-model="type">
                                        <option value="single_choice">Multiple choice</option>
                                        <option value="fill_in_blank">Fill in blank</option>
                                    </select>
                                </div>
                                <div class="tb-col" style="flex:0 0 130px;">
                                    <label class="tb-label">Ball</label>
                                    <input type="number" min="1" max="100" name="points" class="tb-input" value="{{ old('points', 1) }}" required>
                                </div>
                                <div class="tb-col" style="flex:0 0 180px;">
                                    <label class="flex items-center gap-3 text-sm text-slate-700 h-[42px]">
                                        <input type="checkbox" name="is_active" value="1" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500" {{ old('is_active', '1') ? 'checked' : '' }}>
                                        Savol faol bo'lsin
                                    </label>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
                                <div>
                                    <label class="tb-label">Savol matni (UZ)</label>
                                    <textarea name="prompt" class="tb-textarea" required>{{ old('prompt') }}</textarea>
                                </div>
                                <div>
                                    <label class="tb-label">Savol matni (RU)</label>
                                    <textarea name="prompt_ru" class="tb-textarea">{{ old('prompt_ru') }}</textarea>
                                </div>
                                <div>
                                    <label class="tb-label">Savol matni (EN)</label>
                                    <textarea name="prompt_en" class="tb-textarea">{{ old('prompt_en') }}</textarea>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
                                <div>
                                    <label class="tb-label">Yordamchi izoh (UZ)</label>
                                    <textarea name="helper_text" class="tb-textarea">{{ old('helper_text') }}</textarea>
                                </div>
                                <div>
                                    <label class="tb-label">Yordamchi izoh (RU)</label>
                                    <textarea name="helper_text_ru" class="tb-textarea">{{ old('helper_text_ru') }}</textarea>
                                </div>
                                <div>
                                    <label class="tb-label">Yordamchi izoh (EN)</label>
                                    <textarea name="helper_text_en" class="tb-textarea">{{ old('helper_text_en') }}</textarea>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
                                <div>
                                    <label class="tb-label">Savol rasmi</label>
                                    <input type="file" name="question_image" class="tb-input" accept=".jpg,.jpeg,.png,.webp,.gif,image/*">
                                    <div class="mt-2 text-xs text-slate-500">JPG, PNG, WEBP yoki GIF. Maksimal 4 MB.</div>
                                </div>
                                <div>
                                    @if(old('question_image'))
                                        <div class="tb-soft-box text-xs text-slate-500">Tanlangan rasm yuborilgandan keyin ko'rinadi.</div>
                                    @else
                                        <div class="tb-soft-box text-xs text-slate-500">Rasm savol matniga bog'liq bo'lsa shu yerga yuklang. Multiple choice va fill in blank uchun ham ishlaydi.</div>
                                    @endif
                                </div>
                            </div>

                            <div x-show="type === 'single_choice'" x-cloak class="space-y-3">
                                <div class="tb-soft-box">
                                    <div class="flex items-center justify-between gap-3 mb-3">
                                        <label class="tb-label !mb-0">Variantlar</label>
                                        <button type="button" @click="addOption()" class="tb-btn tb-btn-ghost">+ Variant qo'shish</button>
                                    </div>

                                    <div class="space-y-2">
                                        <template x-for="(option, index) in options" :key="index">
                                            <div class="tb-option-row">
                                                <div class="tb-option-prefix" x-text="optionLabel(index)"></div>
                                                <div style="flex:1 1 auto; display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:8px;">
                                                    <input type="text"
                                                           class="tb-input"
                                                           :name="`options[${index}][text]`"
                                                           x-model="option.text"
                                                           placeholder="UZ variant">
                                                    <input type="text"
                                                           class="tb-input"
                                                           :name="`options[${index}][text_ru]`"
                                                           x-model="option.text_ru"
                                                           placeholder="RU variant">
                                                    <input type="text"
                                                           class="tb-input"
                                                           :name="`options[${index}][text_en]`"
                                                           x-model="option.text_en"
                                                           placeholder="EN variant">
                                                </div>
                                                <label class="flex items-center gap-2 text-sm text-slate-700 whitespace-nowrap">
                                                    <input type="radio" name="correct_option_number" :value="index + 1" x-model="correctOption" class="text-emerald-600 focus:ring-emerald-500">
                                                    To'g'ri
                                                </label>
                                                <button type="button" class="tb-option-remove" @click="removeOption(index)" x-show="options.length > 2">x</button>
                                            </div>
                                        </template>
                                    </div>

                                    <div class="mt-3 text-xs text-slate-500">
                                        Standart holatda a), b), c) variantlar turadi. Kerak bo'lsa yangi variant qo'shing.
                                    </div>
                                </div>
                            </div>

                            <div x-show="type === 'fill_in_blank'" x-cloak class="tb-inline">
                                    <div class="tb-col" style="flex:1 1 300px;">
                                        <label class="tb-label">To'g'ri javob (UZ)</label>
                                        <input type="text" name="correct_answer_text" class="tb-input" value="{{ old('correct_answer_text') }}">
                                    </div>
                                    <div class="tb-col" style="flex:1 1 300px;">
                                        <label class="tb-label">To'g'ri javob (RU)</label>
                                        <input type="text" name="correct_answer_text_ru" class="tb-input" value="{{ old('correct_answer_text_ru') }}">
                                    </div>
                                    <div class="tb-col" style="flex:1 1 300px;">
                                        <label class="tb-label">To'g'ri javob (EN)</label>
                                        <input type="text" name="correct_answer_text_en" class="tb-input" value="{{ old('correct_answer_text_en') }}">
                                    </div>
                                <div class="tb-col" style="flex:0 0 220px;">
                                    <label class="flex items-center gap-3 text-sm text-slate-700 h-[42px]">
                                        <input type="checkbox" name="case_sensitive" value="1" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500" {{ old('case_sensitive') ? 'checked' : '' }}>
                                        Harf kattaligi farq qilsin
                                    </label>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
                                <div>
                                    <label class="tb-label">To'g'ri javob izohi (UZ)</label>
                                    <textarea name="correct_explanation" class="tb-textarea">{{ old('correct_explanation') }}</textarea>
                                </div>
                                <div>
                                    <label class="tb-label">To'g'ri javob izohi (RU)</label>
                                    <textarea name="correct_explanation_ru" class="tb-textarea">{{ old('correct_explanation_ru') }}</textarea>
                                </div>
                                <div>
                                    <label class="tb-label">To'g'ri javob izohi (EN)</label>
                                    <textarea name="correct_explanation_en" class="tb-textarea">{{ old('correct_explanation_en') }}</textarea>
                                </div>
                            </div>

                            <button type="submit" class="tb-btn tb-btn-green">Savol qo'shish</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="space-y-4">
                <div>
                    <h2 class="text-xl font-bold text-slate-900">Mavjud savollar</h2>
                    <p class="mt-1 text-sm text-slate-500">Har bir savolni shu yerning o'zida tahrirlash yoki o'chirish mumkin.</p>
                </div>

                @if($questions->isNotEmpty())
                    <div class="tb-question-grid">
                        @foreach($questions as $question)
                            <div class="tb-question"
                                 x-data="questionBuilder({
                                    type: '{{ $question->type }}',
                                    options: @js($questionOptions($question)),
                                    correctOption: {{ (int) $correctOptionNumber($question) }}
                                 })">
                                <div class="tb-question-head flex flex-col gap-3">
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="tb-chip blue">Savol {{ $question->sort_order }}</span>
                                            <span class="tb-chip {{ $question->type === 'single_choice' ? 'green' : 'orange' }}">
                                                {{ $question->type === 'single_choice' ? 'Multiple choice' : 'Fill in blank' }}
                                            </span>
                                            <span class="tb-chip gray">{{ $question->points }} ball</span>
                                        </div>
                                        <div class="mt-2 text-sm font-bold text-slate-900">{{ $question->prompt }}</div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <button type="button" @click="open = !open" class="tb-btn tb-btn-light">
                                            <span x-text="open ? 'Yopish' : 'Ochish'"></span>
                                        </button>
                                        <form method="POST" action="{{ route('teacher.test-subjects.tests.questions.destroy', [$testSubject, $lesson, $question]) }}" onsubmit="return confirm('Savolni o\'chirasizmi?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="tb-btn tb-btn-red">O'chirish</button>
                                        </form>
                                    </div>
                                </div>

                                <div class="tb-section border-t border-slate-100" x-show="open" x-cloak>
                                    <form method="POST" action="{{ route('teacher.test-subjects.tests.questions.update', [$testSubject, $lesson, $question]) }}" class="space-y-4" enctype="multipart/form-data">
                                        @csrf
                                        @method('PUT')

                                        <div class="tb-inline">
                                            <div class="tb-col" style="flex:1 1 240px;">
                                                <label class="tb-label">Savol turi</label>
                                                <select name="type" class="tb-select" x-model="type">
                                                    <option value="single_choice">Multiple choice</option>
                                                    <option value="fill_in_blank">Fill in blank</option>
                                                </select>
                                            </div>
                                            <div class="tb-col" style="flex:0 0 130px;">
                                                <label class="tb-label">Ball</label>
                                                <input type="number" min="1" max="100" name="points" class="tb-input" value="{{ $question->points }}" required>
                                            </div>
                                            <div class="tb-col" style="flex:0 0 180px;">
                                                <label class="flex items-center gap-3 text-sm text-slate-700 h-[42px]">
                                                    <input type="checkbox" name="is_active" value="1" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500" {{ $question->is_active ? 'checked' : '' }}>
                                                    Savol faol
                                                </label>
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
                                            <div>
                                                <label class="tb-label">Savol matni (UZ)</label>
                                                <textarea name="prompt" class="tb-textarea" required>{{ $question->prompt }}</textarea>
                                            </div>
                                            <div>
                                                <label class="tb-label">Savol matni (RU)</label>
                                                <textarea name="prompt_ru" class="tb-textarea">{{ $tr($question->prompt_translations, 'ru') }}</textarea>
                                            </div>
                                            <div>
                                                <label class="tb-label">Savol matni (EN)</label>
                                                <textarea name="prompt_en" class="tb-textarea">{{ $tr($question->prompt_translations, 'en') }}</textarea>
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
                                            <div>
                                                <label class="tb-label">Yordamchi izoh (UZ)</label>
                                                <textarea name="helper_text" class="tb-textarea">{{ $question->helper_text }}</textarea>
                                            </div>
                                            <div>
                                                <label class="tb-label">Yordamchi izoh (RU)</label>
                                                <textarea name="helper_text_ru" class="tb-textarea">{{ $tr($question->helper_text_translations, 'ru') }}</textarea>
                                            </div>
                                            <div>
                                                <label class="tb-label">Yordamchi izoh (EN)</label>
                                                <textarea name="helper_text_en" class="tb-textarea">{{ $tr($question->helper_text_translations, 'en') }}</textarea>
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
                                            <div>
                                                <label class="tb-label">Savol rasmi</label>
                                                <input type="file" name="question_image" class="tb-input" accept=".jpg,.jpeg,.png,.webp,.gif,image/*">
                                                <div class="mt-2 text-xs text-slate-500">Yangi rasm yuklansa eski rasm almashtiriladi.</div>
                                            </div>
                                            <div>
                                                @if($question->imageUrl())
                                                    <div class="space-y-3">
                                                        <img src="{{ $question->imageUrl() }}" alt="Savol rasmi" class="tb-preview-image">
                                                        <label class="flex items-center gap-3 text-sm text-slate-700">
                                                            <input type="checkbox" name="remove_question_image" value="1" class="rounded border-slate-300 text-red-600 focus:ring-red-500">
                                                            Joriy rasmni olib tashlash
                                                        </label>
                                                    </div>
                                                @else
                                                    <div class="tb-soft-box text-xs text-slate-500">Hozircha rasm biriktirilmagan.</div>
                                                @endif
                                            </div>
                                        </div>

                                        <div x-show="type === 'single_choice'" x-cloak class="space-y-3">
                                            <div class="tb-soft-box">
                                                <div class="flex items-center justify-between gap-3 mb-3">
                                                    <label class="tb-label !mb-0">Variantlar</label>
                                                    <button type="button" @click="addOption()" class="tb-btn tb-btn-ghost">+ Variant qo'shish</button>
                                                </div>
                                                <div class="space-y-2">
                                                    <template x-for="(option, index) in options" :key="index">
                                                        <div class="tb-option-row">
                                                            <div class="tb-option-prefix" x-text="optionLabel(index)"></div>
                                                            <div style="flex:1 1 auto; display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:8px;">
                                                                <input type="text"
                                                                       class="tb-input"
                                                                       :name="`options[${index}][text]`"
                                                                       x-model="option.text"
                                                                       placeholder="UZ variant">
                                                                <input type="text"
                                                                       class="tb-input"
                                                                       :name="`options[${index}][text_ru]`"
                                                                       x-model="option.text_ru"
                                                                       placeholder="RU variant">
                                                                <input type="text"
                                                                       class="tb-input"
                                                                       :name="`options[${index}][text_en]`"
                                                                       x-model="option.text_en"
                                                                       placeholder="EN variant">
                                                            </div>
                                                            <label class="flex items-center gap-2 text-sm text-slate-700 whitespace-nowrap">
                                                                <input type="radio" name="correct_option_number" :value="index + 1" x-model="correctOption" class="text-emerald-600 focus:ring-emerald-500">
                                                                To'g'ri
                                                            </label>
                                                            <button type="button" class="tb-option-remove" @click="removeOption(index)" x-show="options.length > 2">x</button>
                                                        </div>
                                                    </template>
                                                </div>
                                            </div>
                                        </div>

                                        <div x-show="type === 'fill_in_blank'" x-cloak class="tb-inline">
                                            <div class="tb-col" style="flex:1 1 300px;">
                                                <label class="tb-label">To'g'ri javob (UZ)</label>
                                                <input type="text" name="correct_answer_text" class="tb-input" value="{{ $question->correct_answer_text }}">
                                            </div>
                                            <div class="tb-col" style="flex:1 1 300px;">
                                                <label class="tb-label">To'g'ri javob (RU)</label>
                                                <input type="text" name="correct_answer_text_ru" class="tb-input" value="{{ $tr($question->correct_answer_translations, 'ru') }}">
                                            </div>
                                            <div class="tb-col" style="flex:1 1 300px;">
                                                <label class="tb-label">To'g'ri javob (EN)</label>
                                                <input type="text" name="correct_answer_text_en" class="tb-input" value="{{ $tr($question->correct_answer_translations, 'en') }}">
                                            </div>
                                            <div class="tb-col" style="flex:0 0 220px;">
                                                <label class="flex items-center gap-3 text-sm text-slate-700 h-[42px]">
                                                    <input type="checkbox" name="case_sensitive" value="1" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500" {{ $question->case_sensitive ? 'checked' : '' }}>
                                                    Harf kattaligi farq qilsin
                                                </label>
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
                                            <div>
                                                <label class="tb-label">To'g'ri javob izohi (UZ)</label>
                                                <textarea name="correct_explanation" class="tb-textarea">{{ $question->correct_explanation }}</textarea>
                                            </div>
                                            <div>
                                                <label class="tb-label">To'g'ri javob izohi (RU)</label>
                                                <textarea name="correct_explanation_ru" class="tb-textarea">{{ $tr($question->correct_explanation_translations, 'ru') }}</textarea>
                                            </div>
                                            <div>
                                                <label class="tb-label">To'g'ri javob izohi (EN)</label>
                                                <textarea name="correct_explanation_en" class="tb-textarea">{{ $tr($question->correct_explanation_translations, 'en') }}</textarea>
                                            </div>
                                        </div>

                                        <button type="submit" class="tb-btn tb-btn-primary">Savolni yangilash</button>
                                    </form>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="rounded-2xl border border-slate-200 bg-white px-6 py-10 text-center text-slate-500 shadow-sm">
                        Bu mavzu uchun hali savollar kiritilmagan.
                    </div>
                @endif
            </div>
        </div>
    </div>

    <script>
        function questionBuilder(initial, collapsible = true) {
            return {
                open: !collapsible,
                type: initial.type || 'single_choice',
                options: (initial.options && initial.options.length
                    ? initial.options
                    : [{ text: '', text_ru: '', text_en: '' }, { text: '', text_ru: '', text_en: '' }, { text: '', text_ru: '', text_en: '' }]
                ).map(option => ({
                    text: option.text ?? '',
                    text_ru: option.text_ru ?? '',
                    text_en: option.text_en ?? '',
                })),
                correctOption: Number(initial.correctOption || 1),
                init() {
                    this.ensureMinimumOptions();
                },
                optionLabel(index) {
                    const alphabet = 'abcdefghijklmnopqrstuvwxyz';
                    return (alphabet[index] || ('v' + (index + 1))) + ')';
                },
                ensureMinimumOptions() {
                    if (this.type !== 'single_choice') {
                        return;
                    }

                    while (this.options.length < 3) {
                        this.options.push({ text: '', text_ru: '', text_en: '' });
                    }

                    if (this.correctOption < 1) {
                        this.correctOption = 1;
                    }
                },
                addOption() {
                    this.options.push({ text: '', text_ru: '', text_en: '' });
                },
                removeOption(index) {
                    if (this.options.length <= 2) {
                        return;
                    }

                    this.options.splice(index, 1);

                    if (this.correctOption > this.options.length) {
                        this.correctOption = this.options.length;
                    }
                }
            }
        }
    </script>
</x-app-layout>
