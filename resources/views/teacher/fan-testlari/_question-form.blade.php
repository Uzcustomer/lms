@php
    $editingQuestion = is_array($question);
    $initialQuestion = $question ?: ['options' => $optionDefaults];
    $options = $question['options'] ?? $optionDefaults;
    while (count($options) < 3) $options[] = ['text' => '', 'text_ru' => '', 'text_en' => ''];
@endphp

<form method="POST" action="{{ $action }}" enctype="multipart/form-data" x-data="fanQuestionBuilder(@js($initialQuestion))" class="space-y-5 p-5 sm:p-6">
    @csrf
    @if($method) @method($method) @endif
    <div class="grid gap-5 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-4">
            <div>
                <label class="mb-1.5 block text-xs font-black uppercase tracking-wider text-slate-500">Savol turi</label>
                <select name="type" x-model="type" class="w-full rounded-xl border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                    <option value="single_choice">Bitta to'g'ri javob</option>
                    <option value="fill_in_blank">Bo'sh joyni to'ldirish</option>
                </select>
            </div>
            <div class="grid gap-3 md:grid-cols-3">
                <div class="md:col-span-3">
                    <label class="mb-1.5 block text-xs font-black uppercase tracking-wider text-slate-500">Savol (UZ) *</label>
                    <textarea name="prompt" x-model="prompt" required rows="3" placeholder="Savol matnini kiriting..." class="w-full rounded-xl border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                </div>
                <div><label class="mb-1.5 block text-xs font-bold text-slate-500">Savol (RU)</label><textarea name="prompt_ru" x-model="prompt_ru" rows="2" class="w-full rounded-xl border-slate-300 text-sm"></textarea></div>
                <div><label class="mb-1.5 block text-xs font-bold text-slate-500">Savol (EN)</label><textarea name="prompt_en" x-model="prompt_en" rows="2" class="w-full rounded-xl border-slate-300 text-sm"></textarea></div>
                <div><label class="mb-1.5 block text-xs font-bold text-slate-500">Yordamchi matn (UZ)</label><textarea name="helper_text" x-model="helper_text" rows="2" class="w-full rounded-xl border-slate-300 text-sm"></textarea></div>
                <div><label class="mb-1.5 block text-xs font-bold text-slate-500">Yordamchi matn (RU)</label><textarea name="helper_text_ru" x-model="helper_text_ru" rows="2" class="w-full rounded-xl border-slate-300 text-sm"></textarea></div>
                <div><label class="mb-1.5 block text-xs font-bold text-slate-500">Yordamchi matn (EN)</label><textarea name="helper_text_en" x-model="helper_text_en" rows="2" class="w-full rounded-xl border-slate-300 text-sm"></textarea></div>
            </div>
        </div>
        <div class="space-y-4">
            <div>
                <label class="mb-1.5 block text-xs font-black uppercase tracking-wider text-slate-500">Savol rasmi</label>
                <input type="file" name="question_image" accept="image/jpeg,image/png,image/webp,image/gif" class="block w-full rounded-xl border border-slate-300 bg-white p-2 text-xs text-slate-500">
                <p class="mt-1 text-[11px] text-slate-500">JPG, PNG, WEBP yoki GIF. Maksimum 4 MB.</p>
                @if($editingQuestion && !empty($question['image_path']))
                    <div class="mt-3 rounded-xl border border-slate-200 bg-slate-50 p-2">
                        <img src="{{ asset('storage/' . ltrim($question['image_path'], '/')) }}" alt="Savol rasmi" class="max-h-36 w-full rounded-lg object-contain">
                        <label class="mt-2 flex items-center gap-2 text-xs font-semibold text-red-600"><input type="hidden" name="remove_question_image" value="0"><input type="checkbox" name="remove_question_image" value="1" class="rounded border-slate-300 text-red-600"> Eski rasmni olib tashlash</label>
                    </div>
                @endif
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="mb-1.5 block text-xs font-black uppercase tracking-wider text-slate-500">Ball</label><input type="number" name="points" x-model="points" min="1" max="100" required class="w-full rounded-xl border-slate-300 text-sm"></div>
                <label class="flex items-end gap-2 pb-2 text-xs font-bold text-slate-700"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" x-model="is_active" class="rounded border-slate-300 text-blue-600"> Faol savol</label>
            </div>
        </div>
    </div>

    <div x-show="type === 'single_choice'" x-cloak class="rounded-2xl border border-blue-100 bg-blue-50/60 p-4">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2"><div><h3 class="font-black text-slate-800">Javob variantlari</h3><p class="mt-1 text-xs text-slate-500">Radio orqali to'g'ri javobni belgilang.</p></div><button type="button" @click="addOption" class="rounded-lg bg-white px-3 py-2 text-xs font-bold text-blue-700 shadow-sm hover:bg-blue-100">+ Variant qo'shish</button></div>
        <div class="space-y-3">
            <template x-for="(option, index) in options" :key="index">
                <div class="grid gap-2 rounded-xl border border-white bg-white p-3 md:grid-cols-[auto_1fr_1fr_1fr_auto] md:items-center">
                    <label class="flex items-center gap-2 text-xs font-black text-blue-700"><input type="radio" name="correct_option_number" :value="index + 1" x-model="correctOption" class="text-blue-600"> <span x-text="String.fromCharCode(65 + index) + ')'">A)</span></label>
                    <input :name="'options[' + index + '][text]'" x-model="option.text" placeholder="Variant (UZ)" class="rounded-lg border-slate-300 text-sm">
                    <input :name="'options[' + index + '][text_ru]'" x-model="option.text_ru" placeholder="Variant (RU)" class="rounded-lg border-slate-300 text-sm">
                    <input :name="'options[' + index + '][text_en]'" x-model="option.text_en" placeholder="Variant (EN)" class="rounded-lg border-slate-300 text-sm">
                    <button type="button" @click="removeOption(index)" class="rounded-lg px-2 py-2 text-xs font-bold text-red-600 hover:bg-red-50" :disabled="options.length <= 2">O'chirish</button>
                </div>
            </template>
        </div>
    </div>

    <div x-show="type === 'fill_in_blank'" x-cloak class="rounded-2xl border border-amber-100 bg-amber-50/70 p-4">
        <h3 class="font-black text-slate-800">To'g'ri javob</h3>
        <div class="mt-3 grid gap-3 md:grid-cols-3">
            <input name="correct_answer_text" x-model="correct_answer_text" placeholder="Javob (UZ) *" class="rounded-xl border-slate-300 text-sm">
            <input name="correct_answer_text_ru" x-model="correct_answer_text_ru" placeholder="Javob (RU)" class="rounded-xl border-slate-300 text-sm">
            <input name="correct_answer_text_en" x-model="correct_answer_text_en" placeholder="Javob (EN)" class="rounded-xl border-slate-300 text-sm">
        </div>
        <label class="mt-3 inline-flex items-center gap-2 text-xs font-bold text-slate-700"><input type="hidden" name="case_sensitive" value="0"><input type="checkbox" name="case_sensitive" value="1" x-model="case_sensitive" class="rounded border-slate-300 text-amber-600"> Katta-kichik harf farqlansin</label>
    </div>

    <div class="grid gap-3 md:grid-cols-3">
        <div><label class="mb-1.5 block text-xs font-bold text-slate-500">To'g'ri javob izohi (UZ)</label><textarea name="correct_explanation" x-model="correct_explanation" rows="2" class="w-full rounded-xl border-slate-300 text-sm"></textarea></div>
        <div><label class="mb-1.5 block text-xs font-bold text-slate-500">To'g'ri javob izohi (RU)</label><textarea name="correct_explanation_ru" x-model="correct_explanation_ru" rows="2" class="w-full rounded-xl border-slate-300 text-sm"></textarea></div>
        <div><label class="mb-1.5 block text-xs font-bold text-slate-500">To'g'ri javob izohi (EN)</label><textarea name="correct_explanation_en" x-model="correct_explanation_en" rows="2" class="w-full rounded-xl border-slate-300 text-sm"></textarea></div>
    </div>

    <div class="flex justify-end border-t border-slate-100 pt-4">
        <button class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-black text-white shadow-lg shadow-blue-600/20 transition hover:-translate-y-0.5 hover:bg-blue-700">{{ $editingQuestion ? 'Savolni saqlash' : 'Savolni qo\'shish' }}</button>
    </div>
</form>
