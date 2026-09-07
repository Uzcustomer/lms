<x-app-layout>
<x-fan-testi-kit />

@php
    $questions = $collection?->questions ?? [];
    $isEdit = (bool) $collection;
    $optionDefaults = [
        ['text' => '', 'text_ru' => '', 'text_en' => ''],
        ['text' => '', 'text_ru' => '', 'text_en' => ''],
        ['text' => '', 'text_ru' => '', 'text_en' => ''],
    ];
@endphp

<div class="ft ft-page">
    @if(session('success'))
        <div class="ft-alert ft-alert-ok">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="ft-alert ft-alert-err">
            Ma'lumotlarni tekshiring:
            <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="ft-head">
        <div>
            @if($isEdit)
                <a href="{{ route('teacher.fan-testlari.index') }}" class="ft-back">← Test yaratish</a>
            @endif
            <h1 class="ft-title">{{ $isEdit ? "Test to'plamini tahrirlash" : "Yangi test to'plami" }}</h1>
            <p class="ft-sub">Dars jadvali tayyor bo'lmasdan test savollarini oldindan yig'ing.</p>
        </div>
        @if($isEdit)
            <span class="ft-badge ft-badge-info">{{ count($questions) }} ta savol tayyor</span>
        @endif
    </div>

    {{-- 01 · To'plam sozlamalari --}}
    <form method="POST" action="{{ $isEdit ? route('teacher.fan-testlari.update', $collection) : route('teacher.fan-testlari.store') }}" class="ft-card">
        @csrf
        @if($isEdit) @method('PUT') @endif

        <div class="ft-card-head">
            <span class="ft-step">01</span>
            <div>
                <h2>Test sozlamalari</h2>
                <p>Bu ma'lumotlar keyinchalik darsga biriktiriladigan testga o'tkaziladi.</p>
            </div>
        </div>

        <div class="ft-card-body ft-grid ft-cols-2">
            <div class="ft-field ft-span-2">
                <label>Fan</label>
                <select name="curriculum_subject_id" required>
                    <option value="">Fan tanlang</option>
                    @foreach($subjects as $subject)
                        <option value="{{ $subject->id }}" @selected((int) old('curriculum_subject_id', $collection?->curriculum_subject_id) === (int) $subject->id)>
                            {{ $subject->subject_name }}@if($subject->subject_code) ({{ $subject->subject_code }})@endif@if($subject->semester_name) · {{ $subject->semester_name }}@endif
                        </option>
                    @endforeach
                </select>
                @if($subjects->isEmpty())
                    <p class="ft-hint ft-hint-warn">Sizga tegishli kafedra fanlari topilmadi.</p>
                @endif
            </div>

            <div class="ft-field">
                <label>Test to'plami nomi</label>
                <input name="name" required maxlength="255" value="{{ old('name', $collection?->name) }}" placeholder="Masalan: 1-mavzu nazorat testi">
            </div>

            <div class="ft-grid" style="grid-template-columns: repeat(2, minmax(0, 1fr));">
                <div class="ft-field">
                    <label>Vaqt (daqiqa)</label>
                    <input type="number" name="duration_minutes" min="1" max="300" required value="{{ old('duration_minutes', $collection?->duration_minutes ?? 20) }}">
                </div>
                <div class="ft-field">
                    <label>O'tish foizi</label>
                    <input type="number" name="pass_percent" min="1" max="100" value="{{ old('pass_percent', $collection?->pass_percent ?? 60) }}">
                </div>
            </div>

            <div class="ft-field ft-span-2">
                <label>Tavsif</label>
                <textarea name="description" rows="2" placeholder="Test to'plami haqida qisqacha izoh...">{{ old('description', $collection?->description) }}</textarea>
            </div>

            <div class="ft-checks ft-span-2">
                @foreach([
                    'shuffle_questions' => ['Savollarni aralashtirish', $collection?->shuffle_questions ?? false],
                    'show_result_after_submit' => ["Natijani ko'rsatish", $collection?->show_result_after_submit ?? true],
                    'is_active' => ["To'plam faol", $collection?->is_active ?? true],
                ] as $field => [$label, $checked])
                    <label class="ft-check">
                        <input type="hidden" name="{{ $field }}" value="0">
                        <input type="checkbox" name="{{ $field }}" value="1" @checked(old($field, $checked))>
                        {{ $label }}
                    </label>
                @endforeach
            </div>
        </div>

        <div class="ft-card-foot">
            <button class="ft-btn ft-btn-primary">{{ $isEdit ? 'Sozlamalarni saqlash' : "To'plamni yaratish" }}</button>
        </div>
    </form>

    @if($isEdit)
        {{-- 02 · Yangi savol --}}
        <div class="ft-card">
            <div class="ft-card-head">
                <span class="ft-step">02</span>
                <div>
                    <h2>Yangi savol qo'shish</h2>
                    <p>Savol turini tanlang, matn va javoblarni kiriting.</p>
                </div>
            </div>
            @include('teacher.fan-testlari._question-form', ['action' => route('teacher.fan-testlari.questions.store', $collection), 'method' => null, 'question' => null, 'questionIndex' => null, 'optionDefaults' => $optionDefaults])
        </div>

        {{-- Kiritilgan savollar --}}
        <div class="ft-card">
            <div class="ft-card-head">
                <div>
                    <h2>Kiritilgan savollar</h2>
                    <p>Sarlavhani bosib savolni ochish yoki tahrirlash mumkin.</p>
                </div>
                <span class="ft-count">{{ count($questions) }} ta</span>
            </div>

            @forelse($questions as $index => $question)
                <details class="ft-q" @if($errors->any() && old('question_index') == $index) open @endif>
                    <summary class="ft-q-sum">
                        <span class="ft-q-no">{{ $index + 1 }}</span>
                        <span class="ft-q-text">{{ \Illuminate\Support\Str::limit(strip_tags($question['prompt'] ?? ''), 110) ?: 'Savol matni kiritilmagan' }}</span>
                        <span class="ft-q-meta">
                            {{ $question['type'] === 'fill_in_blank' ? "Bo'sh joy" : 'Bitta javob' }} · {{ $question['points'] ?? 1 }} ball
                        </span>
                    </summary>
                    <div class="ft-q-body">
                        @include('teacher.fan-testlari._question-form', ['action' => route('teacher.fan-testlari.questions.update', [$collection, $index]), 'method' => 'PUT', 'question' => $question, 'questionIndex' => $index, 'optionDefaults' => $optionDefaults])
                        <form method="POST" action="{{ route('teacher.fan-testlari.questions.destroy', [$collection, $index]) }}" onsubmit="return confirm('Bu savolni o\'chirishni tasdiqlaysizmi?')" class="ft-q-del">
                            @csrf @method('DELETE')
                            <button class="ft-btn ft-btn-danger ft-btn-sm">Savolni o'chirish</button>
                        </form>
                    </div>
                </details>
            @empty
                <div class="ft-card-body">
                    <div class="ft-empty">
                        <h3>Hali savol qo'shilmagan</h3>
                        <p>Yuqoridagi forma orqali test to'plamingizni savollar bilan to'ldiring.</p>
                    </div>
                </div>
            @endforelse
        </div>
    @else
        <div class="ft-empty">
            <h3>Avval test to'plamini saqlang</h3>
            <p>To'plam yaratilgach, shu sahifada savol qo'shish oynasi ochiladi.</p>
        </div>
    @endif

    {{-- Mavjud to'plamlar --}}
    @if(($collections ?? collect())->isNotEmpty())
        <div class="ft-card">
            <div class="ft-card-head">
                <div>
                    <h2>Yaratilgan test to'plamlari</h2>
                    <p>Keyinchalik bu to'plamlardan dars testiga biriktiriladi.</p>
                </div>
                <span class="ft-count">{{ $collections->count() }} ta</span>
            </div>
            <div class="ft-scroll-x">
                <table class="ft-table">
                    <thead>
                        <tr>
                            <th>Test to'plami</th>
                            <th>Fan</th>
                            <th style="text-align:center">Savollar</th>
                            <th style="text-align:center">Vaqt</th>
                            <th>Holat</th>
                            <th style="text-align:right">Amal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($collections as $item)
                            <tr class="{{ $isEdit && $item->id === $collection->id ? 'is-current' : '' }}">
                                <td>
                                    <div class="ft-t-name">{{ $item->name }}</div>
                                    @if($item->description)
                                        <div class="ft-t-sub">{{ \Illuminate\Support\Str::limit($item->description, 60) }}</div>
                                    @endif
                                </td>
                                <td>
                                    <div>{{ $item->subject?->subject_name ?? '-' }}</div>
                                    <div class="ft-t-sub">{{ $item->subject?->semester_name ?? $item->subject?->subject_code ?? '-' }}</div>
                                </td>
                                <td style="text-align:center; font-weight:700">{{ $item->questionCount() }}</td>
                                <td style="text-align:center">{{ $item->duration_minutes }} daq.</td>
                                <td>
                                    <span class="ft-badge {{ $item->is_active ? 'ft-badge-ok' : 'ft-badge-off' }}">{{ $item->is_active ? 'Faol' : 'Nofaol' }}</span>
                                </td>
                                <td style="text-align:right">
                                    <div class="ft-actions">
                                        @if($item->is_active && $item->questionCount() > 0)
                                            <a href="{{ route('kiosk.fan-testi.show', $item) }}" target="_blank" class="ft-btn ft-btn-ok ft-btn-sm" title="Talabalar uchun test sahifasini ochish">▶ Ochish</a>
                                        @endif
                                        <a href="{{ route('teacher.fan-testlari.edit', $item) }}" class="ft-btn ft-btn-soft ft-btn-sm">Tahrirlash</a>
                                        <form method="POST" action="{{ route('teacher.fan-testlari.destroy', $item) }}" onsubmit="return confirm('Bu test to\'plami va savollarini o\'chirishni tasdiqlaysizmi?')">
                                            @csrf @method('DELETE')
                                            <button class="ft-btn ft-btn-danger ft-btn-sm">O'chirish</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>

<style>
    /* Savollar ro'yxati — yig'iladigan qatorlar (shu sahifaga xos) */
    .ft-q { border-bottom: 1px solid var(--ft-line-soft); }
    .ft-q:last-of-type { border-bottom: 0; }
    .ft-q-sum { display: flex; align-items: center; gap: 10px; padding: 10px 16px; cursor: pointer; list-style: none; transition: background .15s; }
    .ft-q-sum::-webkit-details-marker { display: none; }
    .ft-q-sum:hover { background: #f8fbff; }
    .ft-q[open] .ft-q-sum { background: var(--ft-brand-soft); }
    .ft-q-no { display: inline-flex; flex: none; align-items: center; justify-content: center; width: 22px; height: 22px; border-radius: 6px; background: #e2eafc; color: var(--ft-brand-dark); font-size: 11px; font-weight: 800; }
    .ft-q-text { overflow: hidden; flex: 1 1 auto; min-width: 0; font-size: 12.5px; text-overflow: ellipsis; white-space: nowrap; }
    .ft-q-meta { flex: none; color: var(--ft-ink-mute); font-size: 11px; white-space: nowrap; }
    .ft-q-body { border-top: 1px solid var(--ft-line-soft); }
    .ft-q-del { display: flex; justify-content: flex-end; padding: 0 16px 14px; }
    @media (max-width: 640px) { .ft-q-meta { display: none; } }
</style>

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
            lang: 'uz', imageName: '',
            pickImage(event) { this.imageName = event.target.files?.[0]?.name || ''; },
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
