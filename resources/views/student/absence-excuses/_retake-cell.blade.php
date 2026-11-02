{{-- Qayta topshirish kuni avtomatik (kuniga bitta retake) — talaba faqat
     "Topshirilgan" / "O'z vaqtida" ni belgilaydi, u holda kun ajratilmaydi --}}
<template x-if="!item.jn_submitted">
    <div class="flex items-center gap-2">
        <div class="ae-plan-chip" :class="plan[item._idx] ? '' : 'ae-plan-chip-empty'">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
            <span x-show="plan[item._idx]" x-text="fmtDate(plan[item._idx]) + ', ' + weekdayName(plan[item._idx])"></span>
            <span x-show="!plan[item._idx]">{{ __('Boshlanish kunini tanlang') }}</span>
        </div>
        <button type="button" @click="item.jn_submitted = true"
                class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-lg border-2 border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 hover:border-emerald-300 transition-all">
            <span x-show="item.is_makeup_period">{{ __("O'z vaqtida") }}</span>
            <span x-show="!item.is_makeup_period">{{ __('Topshirilgan') }}</span>
        </button>
    </div>
</template>
<template x-if="item.jn_submitted">
    <div class="flex items-center gap-2 px-3 py-2 bg-emerald-50 border border-emerald-200 rounded-lg">
        <svg class="w-4 h-4 text-emerald-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        <span class="text-sm font-semibold text-emerald-700">
            <span x-show="item.is_makeup_period">{{ __("O'z vaqtida topshiraman") }}</span>
            <span x-show="!item.is_makeup_period">{{ __('Topshirilgan') }}</span>
        </span>
        <button type="button" @click="item.jn_submitted = false"
                class="ml-auto text-xs text-gray-400 hover:text-red-500 font-medium">{{ __('Bekor qilish') }}</button>
    </div>
</template>
{{-- Hidden inputs (sana serverda qayta hisoblanadi) --}}
<input type="hidden" :name="'makeup_dates['+item._idx+'][subject_name]'" :value="item.subject_name">
<input type="hidden" :name="'makeup_dates['+item._idx+'][subject_id]'" :value="item.subject_id || ''">
<input type="hidden" :name="'makeup_dates['+item._idx+'][assessment_type]'" :value="item.assessment_type">
<input type="hidden" :name="'makeup_dates['+item._idx+'][assessment_type_code]'" :value="item.assessment_type_code">
<input type="hidden" :name="'makeup_dates['+item._idx+'][original_date]'" :value="item.original_date">
<input type="hidden" :name="'makeup_dates['+item._idx+'][makeup_date]'" :value="plan[item._idx] || ''">
<input type="hidden" :name="'makeup_dates['+item._idx+'][jn_submitted]'" :value="item.jn_submitted ? '1' : '0'">
<input type="hidden" :name="'makeup_dates['+item._idx+'][is_makeup_period]'" :value="item.is_makeup_period ? '1' : '0'">
<input type="hidden" :name="'makeup_dates['+item._idx+'][already_submitted]'" :value="item.already_submitted ? '1' : '0'">
