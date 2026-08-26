<x-app-layout>
<style>
    form[action*='test-collections']:not([x-data]) {
        overflow: hidden;
        border: 1px solid #d7e2f2 !important;
        border-radius: 24px !important;
        background: #ffffff;
        box-shadow: 0 18px 45px rgba(37, 83, 146, 0.09) !important;
    }

    form[action*='test-collections']:not([x-data]) > div:first-of-type {
        position: relative;
        display: flex;
        align-items: center;
        gap: 16px;
        min-height: 96px;
        padding: 22px 28px 22px 96px !important;
        border-bottom: 1px solid #dbe7f7 !important;
        background: linear-gradient(110deg, #f7faff 0%, #eef5ff 58%, #f8fbff 100%) !important;
    }

    form[action*='test-collections']:not([x-data]) > div:first-of-type::before {
        content: '01';
        position: absolute;
        left: 28px;
        display: flex;
        align-items: center;
        justify-content: center;
        width: 48px;
        height: 48px;
        border-radius: 14px;
        background: #2563eb;
        color: #ffffff;
        font-size: 15px;
        font-weight: 900;
        box-shadow: 0 10px 22px rgba(37, 99, 235, 0.22);
    }

    form[action*='test-collections']:not([x-data]) > div:nth-of-type(2) {
        gap: 24px !important;
        padding: 28px !important;
        background: #ffffff;
    }

    form[action*='test-collections'] label {
        color: #3d506d !important;
        font-size: 11px !important;
        font-weight: 800 !important;
        letter-spacing: .02em !important;
        text-transform: none !important;
    }

    form[action*='test-collections'] input:not([type='hidden']):not([type='checkbox']):not([type='radio']),
    form[action*='test-collections'] select,
    form[action*='test-collections'] textarea {
        width: 100%;
        min-height: 44px;
        border: 1px solid #c7d7ee !important;
        border-radius: 9px !important;
        background: #ffffff !important;
        color: #172b4d !important;
        box-shadow: inset 0 1px 2px rgba(31, 61, 101, 0.03);
        transition: border-color .2s ease, box-shadow .2s ease, background .2s ease;
    }

    form[action*='test-collections'] textarea {
        min-height: 82px;
        padding-top: 11px;
    }

    form[action*='test-collections'] input:not([type='hidden']):not([type='checkbox']):not([type='radio']):focus,
    form[action*='test-collections'] select:focus,
    form[action*='test-collections'] textarea:focus {
        border-color: #4d83f1 !important;
        background: #fbfdff !important;
        box-shadow: 0 0 0 3px rgba(77, 131, 241, 0.13) !important;
        outline: none;
    }

    form[action*='test-collections']:not([x-data]) > div:nth-of-type(2) > div:last-child label {
        min-height: 42px;
        padding: 10px 13px !important;
        border: 1px solid #dbe6f5 !important;
        border-radius: 10px !important;
        background: #f8fbff !important;
    }

    form[action*='test-collections']:not([x-data]) > div:last-of-type {
        padding: 18px 28px !important;
        border-top: 1px solid #e5edf8 !important;
        background: #fbfdff !important;
    }

    form[action*='test-collections'] button[type='submit'] {
        min-height: 44px;
        border-radius: 10px !important;
        background: #2563eb !important;
        box-shadow: 0 8px 18px rgba(37, 99, 235, 0.2) !important;
        transition: transform .2s ease, box-shadow .2s ease, background .2s ease;
    }

    form[action*='test-collections'] button[type='submit']:hover {
        background: #1d4ed8 !important;
        box-shadow: 0 11px 22px rgba(37, 99, 235, 0.26) !important;
        transform: translateY(-1px);
    }

    form[x-data^='fanQuestionBuilder'] {
        padding: 28px !important;
        background: #ffffff;
    }

    form[x-data^='fanQuestionBuilder'] > div:first-of-type {
        padding: 22px;
        border: 1px solid #e0eafa;
        border-radius: 16px;
        background: #fbfdff;
    }

    form[x-data^='fanQuestionBuilder'] > div:first-of-type > div:first-child {
        padding-right: 8px;
    }

    form[x-data^='fanQuestionBuilder'] > div[x-show*='single_choice'],
    form[x-data^='fanQuestionBuilder'] > div[x-show*='fill_in_blank'] {
        border-radius: 16px !important;
        box-shadow: inset 0 0 0 1px rgba(255, 255, 255, .6);
    }

    form[x-data^='fanQuestionBuilder'] input[type='file'] {
        min-height: 46px;
        padding: 10px !important;
        border-radius: 9px !important;
        background: #f8fbff !important;
    }

    form[x-data^='fanQuestionBuilder'] > div:last-of-type {
        padding-top: 20px;
        border-top: 1px solid #e4ecf8;
    }

    @media (max-width: 640px) {
        form[action*='test-collections']:not([x-data]) > div:first-of-type {
            padding: 18px 18px 18px 78px !important;
        }

        form[action*='test-collections']:not([x-data]) > div:first-of-type::before {
            left: 18px;
        }

        form[action*='test-collections']:not([x-data]) > div:nth-of-type(2),
        form[x-data^='fanQuestionBuilder'] {
            padding: 18px !important;
        }
    }
</style>

@php
        $questions = $collection?->questions ?? [];
        $isEdit = (bool) $collection;
        $optionDefaults = [
            ['text' => '', 'text_ru' => '', 'text_en' => ''],
            ['text' => '', 'text_ru' => '', 'text_en' => ''],
            ['text' => '', 'text_ru' => '', 'text_en' => ''],
        ];
    @endphp

    <div class="py-6">
        <div class="w-full px-4 sm:px-6 lg:px-8 space-y-5">
            @if(session('success'))
                <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-semibold text-emerald-700">{{ session('success') }}</div>
            @endif
            @if($errors->any())
                <div class="rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-700">
                    <div class="font-black">Ma'lumotlarni tekshiring:</div>
                    <ul class="mt-2 list-disc space-y-1 pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif

            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <a href="{{ route('teacher.fan-testlari.index') }}" class="text-sm font-bold text-blue-600 hover:text-blue-800">← Test yaratish</a>
                    <h1 class="mt-2 text-2xl font-black tracking-tight text-slate-900">{{ $isEdit ? 'Test to\'plamini tahrirlash' : 'Yangi test to\'plami' }}</h1>
                    <p class="mt-1 text-sm text-slate-500">Dars jadvali tayyor bo'lmasdan test savollarini oldindan yig'ing.</p>
                </div>
                @if($isEdit)
                    <div class="flex items-center gap-2 rounded-xl bg-blue-50 px-4 py-3 text-sm font-bold text-blue-800">
                        <span class="text-lg">{{ count($questions) }}</span> ta savol tayyor
                    </div>
                @endif
            </div>

            <form method="POST" action="{{ $isEdit ? route('teacher.fan-testlari.update', $collection) : route('teacher.fan-testlari.store') }}" class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                @csrf
                @if($isEdit) @method('PUT') @endif
                <div class="border-b border-slate-100 bg-gradient-to-r from-slate-50 to-blue-50 px-5 py-4 sm:px-6">
                    <h2 class="font-black text-slate-900">Test sozlamalari</h2>
                    <p class="mt-1 text-xs text-slate-500">Bu ma'lumotlar keyinchalik darsga biriktiriladigan testga o'tkaziladi.</p>
                </div>
                <div class="grid gap-5 p-5 sm:p-6 lg:grid-cols-2">
                    <div class="lg:col-span-2">
                        <label class="mb-1.5 block text-xs font-black uppercase tracking-wider text-slate-500">Fan</label>
                        <select name="curriculum_subject_id" required class="w-full rounded-xl border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">Fan tanlang</option>
                            @foreach($subjects as $subject)
                                <option value="{{ $subject->id }}" @selected((int) old('curriculum_subject_id', $collection?->curriculum_subject_id) === (int) $subject->id)>
                                    {{ $subject->subject_name }} @if($subject->subject_code) ({{ $subject->subject_code }}) @endif @if($subject->semester_name) - {{ $subject->semester_name }} @endif
                                </option>
                            @endforeach
                        </select>
                        @if($subjects->isEmpty())
                            <p class="mt-2 text-xs font-semibold text-amber-600">Sizga tegishli kafedra fanlari topilmadi.</p>
                        @endif
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-black uppercase tracking-wider text-slate-500">Test to'plami nomi</label>
                        <input name="name" required maxlength="255" value="{{ old('name', $collection?->name) }}" placeholder="Masalan: 1-mavzu nazorat testi" class="w-full rounded-xl border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="mb-1.5 block text-xs font-black uppercase tracking-wider text-slate-500">Vaqt (daqiqa)</label>
                            <input type="number" name="duration_minutes" min="1" max="300" required value="{{ old('duration_minutes', $collection?->duration_minutes ?? 20) }}" class="w-full rounded-xl border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="mb-1.5 block text-xs font-black uppercase tracking-wider text-slate-500">O'tish foizi</label>
                            <input type="number" name="pass_percent" min="1" max="100" value="{{ old('pass_percent', $collection?->pass_percent ?? 60) }}" class="w-full rounded-xl border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                    </div>
                    <div class="lg:col-span-2">
                        <label class="mb-1.5 block text-xs font-black uppercase tracking-wider text-slate-500">Tavsif</label>
                        <textarea name="description" rows="2" placeholder="Test to'plami haqida qisqacha izoh..." class="w-full rounded-xl border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">{{ old('description', $collection?->description) }}</textarea>
                    </div>
                    <div class="flex flex-wrap gap-3 lg:col-span-2">
                        @foreach([
                            'shuffle_questions' => ['Savollarni aralashtirish', $collection?->shuffle_questions ?? false],
                            'show_result_after_submit' => ['Topshirgandan keyin natijani ko\'rsatish', $collection?->show_result_after_submit ?? true],
                            'is_active' => ['To\'plam faol', $collection?->is_active ?? true],
                        ] as $field => [$label, $checked])
                            <label class="inline-flex cursor-pointer items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-bold text-slate-700 hover:border-blue-200 hover:bg-blue-50">
                                <input type="hidden" name="{{ $field }}" value="0">
                                <input type="checkbox" name="{{ $field }}" value="1" @checked(old($field, $checked)) class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                                {{ $label }}
                            </label>
                        @endforeach
                    </div>
                </div>
                <div class="flex justify-end border-t border-slate-100 bg-slate-50 px-5 py-4 sm:px-6">
                    <button class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-black text-white shadow-lg shadow-blue-600/20 transition hover:-translate-y-0.5 hover:bg-blue-700">{{ $isEdit ? 'Sozlamalarni saqlash' : 'To\'plamni yaratish' }}</button>
                </div>
            </form>

            @if($isEdit)
                <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-100 bg-gradient-to-r from-slate-50 to-cyan-50 px-5 py-4 sm:px-6">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h2 class="font-black text-slate-900">Yangi savol qo'shish</h2>
                                <p class="mt-1 text-xs text-slate-500">Mavjud test yaratish tartibidagi barcha asosiy maydonlar shu yerda.</p>
                            </div>
                            <span class="rounded-full bg-white px-3 py-1 text-xs font-bold text-slate-600 shadow-sm">2 xil savol turi</span>
                        </div>
                    </div>
                    @include('teacher.fan-testlari._question-form', ['action' => route('teacher.fan-testlari.questions.store', $collection), 'method' => null, 'question' => null, 'questionIndex' => null, 'optionDefaults' => $optionDefaults])
                </div>

                <div class="space-y-4">
                    <div class="flex items-end justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-black text-slate-900">Kiritilgan savollar</h2>
                            <p class="mt-1 text-sm text-slate-500">Savollarni tahrirlash yoki keraksizlarini olib tashlash mumkin.</p>
                        </div>
                        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600">{{ count($questions) }} ta</span>
                    </div>
                    @forelse($questions as $index => $question)
                        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                            <div class="flex flex-col gap-3 border-b border-slate-100 bg-slate-50/70 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                                <div class="flex items-center gap-3">
                                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-blue-600 text-sm font-black text-white">{{ $index + 1 }}</span>
                                    <div>
                                        <div class="font-black text-slate-900">Savol #{{ $index + 1 }}</div>
                                        <div class="mt-0.5 text-xs text-slate-500">{{ $question['type'] === 'fill_in_blank' ? 'Bo\'sh joyni to\'ldirish' : 'Bitta to\'g\'ri javob' }} · {{ $question['points'] ?? 1 }} ball</div>
                                    </div>
                                </div>
                                <form method="POST" action="{{ route('teacher.fan-testlari.questions.destroy', [$collection, $index]) }}" onsubmit="return confirm('Bu savolni o\'chirishni tasdiqlaysizmi?')">
                                    @csrf @method('DELETE')
                                    <button class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-bold text-red-700 hover:bg-red-100">Savolni o'chirish</button>
                                </form>
                            </div>
                            @include('teacher.fan-testlari._question-form', ['action' => route('teacher.fan-testlari.questions.update', [$collection, $index]), 'method' => 'PUT', 'question' => $question, 'questionIndex' => $index, 'optionDefaults' => $optionDefaults])
                        </div>
                    @empty
                        <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-6 py-12 text-center">
                            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-white text-2xl text-blue-600 shadow-sm">?</div>
                            <h3 class="mt-4 font-black text-slate-800">Hali savol qo'shilmagan</h3>
                            <p class="mt-1 text-sm text-slate-500">Yuqoridagi forma orqali test to'plamingizni savollar bilan to'ldiring.</p>
                        </div>
                    @endforelse
                </div>
            @else
                <div class="rounded-2xl border border-dashed border-blue-200 bg-blue-50/60 px-6 py-10 text-center">
                    <h2 class="font-black text-blue-900">Avval test to'plamini saqlang</h2>
                    <p class="mt-1 text-sm text-blue-700">To'plam yaratilgach, shu sahifada savol qo'shish va sozlash oynalari ochiladi.</p>
                </div>
            @endif
        </div>
    </div>

    <script>
        function fanQuestionBuilder(initial) {
            const source = initial || {};
            const sourceOptions = Array.isArray(source.options) ? source.options : [];
            const correctIndex = Math.max(0, sourceOptions.findIndex(option => option.is_correct) || 0);
            const options = sourceOptions.map(option => ({
                text: option.text || '', text_ru: option.text_ru || '', text_en: option.text_en || ''
            }));
            while (options.length < 3) options.push({text: '', text_ru: '', text_en: ''});

            return {
                type: source.type || 'single_choice',
                prompt: source.prompt || '', prompt_ru: source.prompt_ru || '', prompt_en: source.prompt_en || '',
                helper_text: source.helper_text || '', helper_text_ru: source.helper_text_ru || '', helper_text_en: source.helper_text_en || '',
                correct_explanation: source.correct_explanation || '', correct_explanation_ru: source.correct_explanation_ru || '', correct_explanation_en: source.correct_explanation_en || '',
                correct_answer_text: source.correct_answer_text || '', correct_answer_text_ru: source.correct_answer_text_ru || '', correct_answer_text_en: source.correct_answer_text_en || '',
                case_sensitive: Boolean(source.case_sensitive), points: source.points || 1, is_active: source.is_active !== false,
                options: options, correctOption: correctIndex + 1,
                addOption() { this.options.push({text: '', text_ru: '', text_en: ''}); },
                removeOption(index) {
                    if (this.options.length <= 2) return;
                    this.options.splice(index, 1);
                    if (this.correctOption > this.options.length) this.correctOption = this.options.length;
                    if (this.correctOption > index + 1) this.correctOption--;
                }
            };
        }
    </script>
</x-app-layout>
