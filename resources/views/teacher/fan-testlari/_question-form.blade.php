@php
    $editingQuestion = is_array($question);
    $initialQuestion = $question ?: ['options' => $optionDefaults];
    $uid = $questionIndex ?? 'new';
@endphp

<form method="POST" action="{{ $action }}" enctype="multipart/form-data" x-data="fanQuestionBuilder(@js($initialQuestion))"
      @submit="if (!prompt.trim()) { lang = 'uz'; $event.preventDefault(); $nextTick(() => $el.querySelector('[name=prompt]').focus()); }"
      class="ft-qf">
    @csrf
    @if($method) @method($method) @endif

    {{-- Ustki qator: tur · til · ball · faol --}}
    <div class="ft-qf-bar">
        <select name="type" x-model="type" class="ft-qf-type">
            <option value="single_choice">Bitta to'g'ri javob</option>
            <option value="fill_in_blank">Bo'sh joyni to'ldirish</option>
        </select>

        <div class="ft-langs" role="tablist" aria-label="Til">
            @foreach(['uz' => 'UZ', 'ru' => 'RU', 'en' => 'EN'] as $code => $label)
                <button type="button" role="tab" @click="lang = '{{ $code }}'" :class="lang === '{{ $code }}' && 'is-on'"
                        :aria-selected="lang === '{{ $code }}'" class="ft-lang">{{ $label }}</button>
            @endforeach
        </div>

        <div class="ft-qf-bar-end">
            <label class="ft-qf-points">
                Ball
                <input type="number" name="points" x-model="points" min="1" max="100" required>
            </label>
            <label class="ft-check">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" x-model="is_active">
                Faol
            </label>
        </div>
    </div>

    {{-- Savol matni + rasm --}}
    <div class="ft-qf-top">
        <div class="ft-field">
            <label>Savol <span x-text="lang.toUpperCase()"></span> <b x-show="lang === 'uz'">*</b></label>
            <textarea name="prompt" x-model="prompt" x-show="lang === 'uz'" :required="lang === 'uz'" rows="3" placeholder="Savol matnini kiriting..." class="ft-textarea-grow"></textarea>
            <textarea name="prompt_ru" x-model="prompt_ru" x-show="lang === 'ru'" x-cloak rows="3" placeholder="Текст вопроса..." class="ft-textarea-grow"></textarea>
            <textarea name="prompt_en" x-model="prompt_en" x-show="lang === 'en'" x-cloak rows="3" placeholder="Question text..." class="ft-textarea-grow"></textarea>
        </div>

        <div class="ft-qf-media">
            <label class="ft-qf-drop">
                <input type="file" name="question_image" accept="image/jpeg,image/png,image/webp,image/gif" @change="pickImage($event)">
                <span aria-hidden="true">🖼</span>
                <span class="ft-qf-drop-name" x-text="imageName || 'Savol rasmi'"></span>
                <span class="ft-qf-drop-hint">JPG · PNG · 4 MB</span>
            </label>
            @if($editingQuestion && !empty($question['image_path']) && $questionIndex !== null)
                <div class="ft-qf-thumb">
                    <img src="{{ route('fan-testi.question-image', [$collection, $questionIndex]) }}" alt="Savol rasmi">
                    <label class="ft-qf-thumb-del">
                        <input type="hidden" name="remove_question_image" value="0">
                        <input type="checkbox" name="remove_question_image" value="1"> Rasmni olib tashlash
                    </label>
                </div>
            @endif
        </div>
    </div>

    {{-- Javoblar --}}
    <div x-show="type === 'single_choice'" x-cloak class="ft-qf-block">
        <div class="ft-qf-block-head">
            <span>Javob variantlari <em>to'g'risini belgilang</em></span>
            <button type="button" @click="addOption" class="ft-btn ft-btn-soft ft-btn-sm">+ Variant</button>
        </div>
        <div class="ft-qf-opts">
            <template x-for="(option, index) in options" :key="index">
                <div class="ft-qf-opt" :class="correctOption === index + 1 && 'is-correct'">
                    <label class="ft-qf-pick">
                        <input type="radio" name="correct_option_number" :value="index + 1" x-model="correctOption">
                        <span x-text="String.fromCharCode(65 + index)">A</span>
                    </label>
                    <input :name="'options[' + index + '][text]'" x-model="option.text" x-show="lang === 'uz'" placeholder="Variant matni">
                    <input :name="'options[' + index + '][text_ru]'" x-model="option.text_ru" x-show="lang === 'ru'" x-cloak placeholder="Вариант ответа">
                    <input :name="'options[' + index + '][text_en]'" x-model="option.text_en" x-show="lang === 'en'" x-cloak placeholder="Answer option">
                    <button type="button" @click="removeOption(index)" class="ft-icon-btn" :disabled="options.length <= 2" title="O'chirish" aria-label="Variantni o'chirish">&times;</button>
                </div>
            </template>
        </div>
    </div>

    <div x-show="type === 'fill_in_blank'" x-cloak class="ft-qf-block is-blank">
        <div class="ft-qf-block-head"><span>To'g'ri javob</span></div>
        <input name="correct_answer_text" x-model="correct_answer_text" x-show="lang === 'uz'" placeholder="Javob matni">
        <input name="correct_answer_text_ru" x-model="correct_answer_text_ru" x-show="lang === 'ru'" x-cloak placeholder="Текст ответа">
        <input name="correct_answer_text_en" x-model="correct_answer_text_en" x-show="lang === 'en'" x-cloak placeholder="Answer text">
        <label class="ft-check">
            <input type="hidden" name="case_sensitive" value="0">
            <input type="checkbox" name="case_sensitive" value="1" x-model="case_sensitive"> Katta-kichik harf farqlansin
        </label>
    </div>

    {{-- Qo'shimcha matnlar --}}
    <details class="ft-more">
        <summary>Yordamchi matn va izoh</summary>
        <div class="ft-more-body">
            <div class="ft-field">
                <label>Yordamchi matn</label>
                <textarea name="helper_text" x-model="helper_text" x-show="lang === 'uz'" rows="2"></textarea>
                <textarea name="helper_text_ru" x-model="helper_text_ru" x-show="lang === 'ru'" x-cloak rows="2"></textarea>
                <textarea name="helper_text_en" x-model="helper_text_en" x-show="lang === 'en'" x-cloak rows="2"></textarea>
            </div>
            <div class="ft-field">
                <label>To'g'ri javob izohi</label>
                <textarea name="correct_explanation" x-model="correct_explanation" x-show="lang === 'uz'" rows="2"></textarea>
                <textarea name="correct_explanation_ru" x-model="correct_explanation_ru" x-show="lang === 'ru'" x-cloak rows="2"></textarea>
                <textarea name="correct_explanation_en" x-model="correct_explanation_en" x-show="lang === 'en'" x-cloak rows="2"></textarea>
            </div>
        </div>
    </details>

    <div class="ft-qf-foot">
        <span class="ft-hint" x-show="lang !== 'uz'" x-cloak>Tarjima ixtiyoriy — bo'sh qolsa o'zbekchasi ishlatiladi.</span>
        <button class="ft-btn ft-btn-primary">{{ $editingQuestion ? 'Savolni saqlash' : "Savolni qo'shish" }}</button>
    </div>
</form>
