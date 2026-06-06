{{--
    Bitta savol bloki (sarlavha + variantlar).
    Parametrlar:
      $q       - savol massivi (id, type, text, options)
      $isChild - bool, true bo'lsa kichik sarlavha (5.1 kabi nested savol uchun)
      $locale  - tanlangan til (uz/ru/en) — bo'lmasa 'uz'
      $ui      - config['ui'] (UI matnlari, lokalizatsiya uchun)
--}}
@php
    $locale = $locale ?? 'uz';
    $ui = $ui ?? [];
@endphp
<div class="{{ $isChild ? 'mb-2' : 'mb-3' }}">
    <div class="inline-flex items-center gap-1.5 mb-1.5 px-2 py-0.5 {{ $isChild ? 'bg-indigo-200 text-indigo-800' : 'bg-indigo-100 text-indigo-700' }} text-[10px] font-bold uppercase tracking-wide rounded-full">
        {{ sv_t($ui['question_label'] ?? 'Savol', $locale) }} {{ $q['id'] }}
    </div>
    <h3 class="{{ $isChild ? 'text-sm' : 'text-sm sm:text-base' }} font-bold text-slate-800 leading-snug">{{ sv_t($q['text'], $locale) }}</h3>
    @if($q['type'] === 'checkbox')
        <p class="text-xs text-indigo-600 mt-1 font-medium flex items-center gap-1">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
            </svg>
            {{ sv_t($ui['multi_select_hint'] ?? 'Bir nechtasini tanlash mumkin', $locale) }}
        </p>
    @elseif($q['type'] === 'text' && empty($q['required']))
        <p class="text-xs text-slate-500 mt-1 font-medium">{{ sv_t($ui['optional_hint'] ?? 'Ixtiyoriy', $locale) }}</p>
    @endif
</div>

@if($q['type'] === 'text')
    <div>
        <textarea name="q_{{ $q['id'] }}"
                  rows="3"
                  data-qid="{{ $q['id'] }}"
                  placeholder="{{ sv_t($q['placeholder'] ?? ($ui['text_placeholder_default'] ?? 'Javobingizni yozing...'), $locale) }}"
                  class="sv-text-input w-full px-3 py-2 text-sm bg-white border border-slate-200 rounded-lg focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 outline-none transition resize-y leading-snug"></textarea>
    </div>
@else
<div class="space-y-1.5">
    @foreach($q['options'] as $opt)
        <label class="sv-option flex flex-col" data-qid="{{ $q['id'] }}">
            <div class="flex items-start gap-3">
                <span class="sv-dot {{ $q['type'] === 'checkbox' ? 'square' : '' }}"></span>
                @if($q['type'] === 'radio')
                    <input type="radio" name="q_{{ $q['id'] }}" value="{{ $opt['id'] }}" class="sr-only">
                @else
                    <input type="checkbox" name="q_{{ $q['id'] }}[]" value="{{ $opt['id'] }}" class="sr-only">
                @endif
                <span class="sv-text text-sm text-slate-700 leading-snug flex-1">
                    @if($opt['id'] !== 'other')
                        <span class="sv-opt-letter font-bold text-indigo-600 mr-1">{{ $opt['id'] }})</span>
                    @endif
                    {{ sv_t($opt['text'], $locale) }}
                </span>
            </div>
            @if(!empty($opt['has_other']))
                <div class="sv-other-wrap hidden mt-2 ml-7">
                    <input type="text"
                           class="sv-other-input w-full px-3 py-2 text-sm bg-white border border-indigo-200 rounded-lg focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 outline-none transition"
                           placeholder="{{ sv_t($ui['other_placeholder'] ?? 'Iltimos, izoh yozing...', $locale) }}"
                           data-opt="{{ $opt['id'] }}">
                </div>
            @endif
        </label>
    @endforeach
</div>
@endif
