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
            <option value="fill_in_blank">Bo'sh joyni to'ldirish</option>
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
            <label class="bl-drop">
                <input type="file" name="question_image" accept="image/jpeg,image/png,image/webp,image/gif" @change="pickImage($event)">
                <span class="bl-drop-icon" aria-hidden="true">&#9635;</span>
                <span class="bl-drop-name" x-text="imageName || 'Savol rasmi'"></span>
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

    <div x-show="type === 'single_choice'" x-cloak class="bl-answers">
        <div class="bl-answers-head">
            <span>Javob variantlari <em>to'g'risini belgilang</em></span>
            <button type="button" @click="addOption" class="bl-btn bl-btn-ghost bl-btn-sm">+ Variant</button>
        </div>
        <div class="bl-opts">
            <template x-for="(option, index) in options" :key="index">
                <div class="bl-opt" :class="correctOption === index + 1 && 'is-correct'">
                    <label class="bl-pick">
                        <input type="radio" name="correct_option_number" :value="index + 1" x-model="correctOption">
                        <span x-text="String.fromCharCode(65 + index)">A</span>
                    </label>
                    <input :name="'options[' + index + '][text]'" x-model="option.text" x-show="lang === 'uz'" placeholder="Variant matni">
                    <input :name="'options[' + index + '][text_ru]'" x-model="option.text_ru" x-show="lang === 'ru'" x-cloak placeholder="Вариант ответа">
                    <input :name="'options[' + index + '][text_en]'" x-model="option.text_en" x-show="lang === 'en'" x-cloak placeholder="Answer option">
                    <button type="button" @click="removeOption(index)" class="bl-x" :disabled="options.length <= 2" title="O'chirish" aria-label="Variantni o'chirish">&times;</button>
                </div>
            </template>
        </div>
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
