@php
    $editingQuestion = is_array($question);
    $initialQuestion = $question ?: ['options' => $optionDefaults];
@endphp

<form method="POST" action="{{ $action }}" enctype="multipart/form-data" x-data="fanQuestionBuilder(@js($initialQuestion))"
      @submit="if (!prompt.trim()) { lang = 'uz'; $event.preventDefault(); $nextTick(() => $el.querySelector('[name=prompt]').focus()); }" class="bl-qf">
    @csrf
    @if($method) @method($method) @endif

    <div class="bl-qf-bar">
        <select name="type" x-model="type" class="bl-qf-type">
            <option value="single_choice">Bitta to'g'ri javob</option>
            <option value="multiple_choice">Bir nechta to'g'ri javob</option>
            <option value="true_false">To'g'ri / Noto'g'ri</option>
            <option value="fill_in_blank">Bo'sh joyni to'ldirish</option>
            <option value="matching">Moslashtirish (juftlik)</option>
            <option value="ordering">Ketma-ketlikni tuzish</option>
        </select>

        <div class="bl-langs" role="tablist" aria-label="Til">
            @foreach(['uz' => 'UZ', 'ru' => 'RU', 'en' => 'EN'] as $code => $label)
                <button type="button" role="tab" @click="lang = '{{ $code }}'" :class="lang === '{{ $code }}' && 'is-on'"
                        :aria-selected="lang === '{{ $code }}'" class="bl-lang">{{ $label }}</button>
            @endforeach
        </div>

        <div class="bl-qf-end">
            <label class="bl-qf-points" for="points-{{ $questionIndex ?? 'new' }}">
                Ball
                <input id="points-{{ $questionIndex ?? 'new' }}" type="number" name="points" x-model="points" min="1" max="100" required>
            </label>
            <label class="bl-check">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" x-model="is_active">
                Faol
            </label>
        </div>
    </div>

    <div class="bl-qf-top">
        <div class="bl-field">
            <label>Savol <span x-text="lang.toUpperCase()"></span> <b x-show="lang === 'uz'">*</b></label>
            <textarea name="prompt" x-model="prompt" x-show="lang === 'uz'" :required="lang === 'uz'" rows="3" placeholder="Savol matnini kiriting..." class="bl-grow"></textarea>
            <textarea name="prompt_ru" x-model="prompt_ru" x-show="lang === 'ru'" x-cloak rows="3" placeholder="Текст вопроса..." class="bl-grow"></textarea>
            <textarea name="prompt_en" x-model="prompt_en" x-show="lang === 'en'" x-cloak rows="3" placeholder="Question text..." class="bl-grow"></textarea>
        </div>

        <div class="bl-qf-media">
            <span class="bl-label"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8.5" cy="9.5" r="1.5"/><path d="m21 16-5-5L5 20"/></svg> Savol rasmi</span>
            <label class="bl-drop">
                <input type="file" name="question_image" accept="image/jpeg,image/png,image/webp,image/gif" @change="pickImage($event)">
                <span class="bl-drop-icon" aria-hidden="true">&#9635;</span>
                <span class="bl-drop-name" x-text="imageName || 'Rasm tanlang'"></span>
                <span class="bl-drop-hint">JPG · PNG · 4 MB gacha</span>
            </label>
            @if($editingQuestion && !empty($question['image_path']) && $questionIndex !== null)
                <div class="bl-thumb">
                    <img src="{{ route('fan-testi.question-image', [$collection, $questionIndex]) }}" alt="Savol rasmi">
                    <label>
                        <input type="hidden" name="remove_question_image" value="0">
                        <input type="checkbox" name="remove_question_image" value="1"> Rasmni olib tashlash
                    </label>
                </div>
            @endif
        </div>
    </div>

    <div x-show="type === 'single_choice' || type === 'multiple_choice'" x-cloak class="bl-answers">
        <div class="bl-answers-head">
            <span>
                Javob variantlari
                <em x-text="type === 'multiple_choice' ? 'to\'g\'rilarining hammasini belgilang' : 'to\'g\'risini belgilang'"></em>
            </span>
            <button type="button" @click="addOption" class="bl-btn bl-btn-ghost bl-btn-sm">+ Variant</button>
        </div>
        <div class="bl-opts">
            <template x-for="(option, index) in options" :key="index">
                <div class="bl-opt" :class="isCorrectOption(index) && 'is-correct'">
                    <label class="bl-pick">
                        <input x-show="type === 'single_choice'" type="radio" name="correct_option_number" :value="index + 1" x-model="correctOption">
                        <input x-show="type === 'multiple_choice'" x-cloak type="checkbox" name="correct_option_numbers[]" :value="index + 1" x-model="correctOptions">
                        <span x-text="String.fromCharCode(65 + index)">A</span>
                    </label>
                    <input :name="'options[' + index + '][text]'" x-model="option.text" x-show="lang === 'uz'" placeholder="Variant matni">
                    <input :name="'options[' + index + '][text_ru]'" x-model="option.text_ru" x-show="lang === 'ru'" x-cloak placeholder="Вариант ответа">
                    <input :name="'options[' + index + '][text_en]'" x-model="option.text_en" x-show="lang === 'en'" x-cloak placeholder="Answer option">
                    <button type="button" @click="removeOption(index)" class="bl-x" :disabled="options.length <= 2" title="O'chirish" aria-label="Variantni o'chirish">&times;</button>
                </div>
            </template>
        </div>
        <p class="bl-note" x-show="type === 'multiple_choice'" x-cloak>
            Talaba ball olishi uchun barcha to'g'ri variantlarni belgilashi va bittasini ham ortiqcha belgilamasligi kerak.
        </p>
    </div>

    {{-- To'g'ri / Noto'g'ri --}}
    <div x-show="type === 'true_false'" x-cloak class="bl-answers">
        <div class="bl-answers-head"><span>To'g'ri javob</span></div>
        <div class="bl-tf">
            <label class="bl-tf-pick" :class="trueFalse === '1' && 'is-on'">
                <input type="radio" name="true_false_answer" value="1" x-model="trueFalse">
                <span>To'g'ri</span>
            </label>
            <label class="bl-tf-pick" :class="trueFalse === '0' && 'is-off'">
                <input type="radio" name="true_false_answer" value="0" x-model="trueFalse">
                <span>Noto'g'ri</span>
            </label>
        </div>
        <p class="bl-note">Talabaga savol matni bilan birga "To'g'ri / Noto'g'ri" tanlovi ko'rsatiladi.</p>
    </div>

    {{-- Moslashtirish --}}
    <div x-show="type === 'matching'" x-cloak class="bl-answers is-match">
        <div class="bl-answers-head">
            <span>Juftliklar <em>chap ustun — mos javob</em></span>
            <button type="button" @click="addPair" class="bl-btn bl-btn-ghost bl-btn-sm">+ Juftlik</button>
        </div>
        <div class="bl-opts">
            <template x-for="(pair, index) in pairs" :key="index">
                <div class="bl-pair">
                    <span class="bl-pair-no" x-text="index + 1">1</span>
                    <input :name="'pairs[' + index + '][left]'" x-model="pair.left" x-show="lang === 'uz'" placeholder="Chap ustun (savol bandi)">
                    <input :name="'pairs[' + index + '][left_ru]'" x-model="pair.left_ru" x-show="lang === 'ru'" x-cloak placeholder="Левая колонка">
                    <input :name="'pairs[' + index + '][left_en]'" x-model="pair.left_en" x-show="lang === 'en'" x-cloak placeholder="Left column">
                    <span class="bl-pair-arrow">&rarr;</span>
                    <input :name="'pairs[' + index + '][right]'" x-model="pair.right" x-show="lang === 'uz'" placeholder="Mos javob">
                    <input :name="'pairs[' + index + '][right_ru]'" x-model="pair.right_ru" x-show="lang === 'ru'" x-cloak placeholder="Соответствие">
                    <input :name="'pairs[' + index + '][right_en]'" x-model="pair.right_en" x-show="lang === 'en'" x-cloak placeholder="Match">
                    <button type="button" @click="removePair(index)" class="bl-x" :disabled="pairs.length <= 2" title="O'chirish" aria-label="Juftlikni o'chirish">&times;</button>
                </div>
            </template>
        </div>
        <p class="bl-note">Talabaga o'ng ustun aralashtirib ko'rsatiladi. Ball barcha juftlik to'g'ri bo'lgandagina beriladi.</p>
    </div>

    {{-- Ketma-ketlik --}}
    <div x-show="type === 'ordering'" x-cloak class="bl-answers is-order">
        <div class="bl-answers-head">
            <span>Bosqichlar <em>to'g'ri tartibda kiriting</em></span>
            <button type="button" @click="addStep" class="bl-btn bl-btn-ghost bl-btn-sm">+ Bosqich</button>
        </div>
        <div class="bl-opts">
            <template x-for="(step, index) in steps" :key="index">
                <div class="bl-step-row">
                    <span class="bl-step-no" x-text="index + 1">1</span>
                    <input :name="'steps[' + index + '][text]'" x-model="step.text" x-show="lang === 'uz'" placeholder="Bosqich matni">
                    <input :name="'steps[' + index + '][text_ru]'" x-model="step.text_ru" x-show="lang === 'ru'" x-cloak placeholder="Текст шага">
                    <input :name="'steps[' + index + '][text_en]'" x-model="step.text_en" x-show="lang === 'en'" x-cloak placeholder="Step text">
                    <button type="button" @click="removeStep(index)" class="bl-x" :disabled="steps.length <= 3" title="O'chirish" aria-label="Bosqichni o'chirish">&times;</button>
                </div>
            </template>
        </div>
        <p class="bl-note">Talabaga bosqichlar aralashtirib beriladi — u raqamlab to'g'ri ketma-ketlikni tuzadi.</p>
    </div>

    <div x-show="type === 'fill_in_blank'" x-cloak class="bl-answers is-blank">
        <div class="bl-answers-head"><span>To'g'ri javob</span></div>
        <input name="correct_answer_text" x-model="correct_answer_text" x-show="lang === 'uz'" placeholder="Javob matni">
        <input name="correct_answer_text_ru" x-model="correct_answer_text_ru" x-show="lang === 'ru'" x-cloak placeholder="Текст ответа">
        <input name="correct_answer_text_en" x-model="correct_answer_text_en" x-show="lang === 'en'" x-cloak placeholder="Answer text">
        <label class="bl-check">
            <input type="hidden" name="case_sensitive" value="0">
            <input type="checkbox" name="case_sensitive" value="1" x-model="case_sensitive"> Katta-kichik harf farqlansin
        </label>
    </div>

    <details class="bl-more">
        <summary>Yordamchi matn va izoh</summary>
        <div class="bl-more-body">
            <div class="bl-field">
                <label>Yordamchi matn</label>
                <textarea name="helper_text" x-model="helper_text" x-show="lang === 'uz'" rows="2"></textarea>
                <textarea name="helper_text_ru" x-model="helper_text_ru" x-show="lang === 'ru'" x-cloak rows="2"></textarea>
                <textarea name="helper_text_en" x-model="helper_text_en" x-show="lang === 'en'" x-cloak rows="2"></textarea>
            </div>
            <div class="bl-field">
                <label>To'g'ri javob izohi</label>
                <textarea name="correct_explanation" x-model="correct_explanation" x-show="lang === 'uz'" rows="2"></textarea>
                <textarea name="correct_explanation_ru" x-model="correct_explanation_ru" x-show="lang === 'ru'" x-cloak rows="2"></textarea>
                <textarea name="correct_explanation_en" x-model="correct_explanation_en" x-show="lang === 'en'" x-cloak rows="2"></textarea>
            </div>
        </div>
    </details>

    <div class="bl-qf-foot">
        <span class="bl-note" x-show="lang !== 'uz'" x-cloak>Tarjima ixtiyoriy — bo'sh qolsa o'zbekchasi ishlatiladi.</span>
        <button class="bl-btn bl-btn-main">{{ $editingQuestion ? 'Savolni saqlash' : 'Savolni qo\'shish' }}</button>
    </div>
</form>
