<div class="cal-dropdown-wrap" @click.outside="item.show_cal = false">
    {{-- Kelajakdagi testlar uchun: "O'z vaqtida topshiraman" va "Qayta topshiraman" --}}
    <template x-if="item.is_makeup_period">
        <div>
            <template x-if="!item.jn_submitted && !item.makeup_date && !(item.assessment_type === 'jn' && item.makeup_start)">
                <div class="flex items-center gap-2">
                    <button type="button" @click="item.jn_submitted = true; item.makeup_date = ''; item.makeup_start = ''; item.makeup_end = ''; item.show_cal = false"
                            class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-lg border-2 border-blue-200 bg-blue-50 text-blue-700 hover:bg-blue-100 hover:border-blue-300 transition-all">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        {{ __("O'z vaqtida") }}
                    </button>
                    <button type="button" @click="item.show_cal = !item.show_cal"
                            class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-lg border-2 border-orange-200 bg-orange-50 text-orange-700 hover:bg-orange-100 hover:border-orange-300 transition-all">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        {{ __('Qayta topshiraman') }}
                    </button>
                </div>
            </template>
            {{-- Sana tanlangan holat --}}
            <template x-if="!item.jn_submitted && (item.makeup_date || (item.assessment_type === 'jn' && item.makeup_start))">
                <div class="flex items-center gap-2">
                    <div class="cal-trigger flex-1" :class="item.show_cal ? 'active' : ''" @click="item.show_cal = !item.show_cal"
                         style="padding:8px 12px; font-size:13px; border-radius:8px;">
                        <div>
                            <template x-if="item.assessment_type === 'jn' && item.makeup_start && item.makeup_end">
                                <span class="cal-trigger-text" x-text="fmtDate(item.makeup_start) + ' {{ __('dan') }} ' + fmtDate(item.makeup_end) + ' {{ __('gacha') }}'"></span>
                            </template>
                            <template x-if="item.assessment_type === 'jn' && item.makeup_start && !item.makeup_end">
                                <span class="cal-trigger-text"><span x-text="fmtDate(item.makeup_start)"></span> <span class="text-gray-400 font-normal">— {{ __('tugash?') }}</span></span>
                            </template>
                            <template x-if="item.assessment_type !== 'jn' && item.makeup_date">
                                <span class="cal-trigger-text flex items-center gap-1.5">
                                    <svg class="w-4 h-4 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                    <span x-text="fmtDate(item.makeup_date)"></span>
                                </span>
                            </template>
                        </div>
                        <button type="button" @click.stop="item.makeup_date = ''; item.makeup_start = ''; item.makeup_end = ''; item.show_cal = false"
                                class="text-xs text-red-400 hover:text-red-600 font-medium">{{ __('Bekor qilish') }}</button>
                    </div>
                </div>
            </template>
            {{-- O'z vaqtida topshiraman tanlangan holat --}}
            <template x-if="item.jn_submitted">
                <div class="flex items-center gap-2 px-3 py-2 bg-blue-50 border border-blue-200 rounded-lg">
                    <svg class="w-4 h-4 text-blue-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <span class="text-sm font-semibold text-blue-700">{{ __("O'z vaqtida topshiraman") }}</span>
                    <button type="button" @click="item.jn_submitted = false"
                            class="ml-auto text-xs text-gray-400 hover:text-red-500 font-medium">{{ __('Bekor qilish') }}</button>
                </div>
            </template>
        </div>
    </template>

    {{-- Oddiy (qoldirilgan) testlar uchun: "Topshirilgan" + sana tanlash --}}
    <template x-if="!item.is_makeup_period">
        <div>
            <template x-if="!item.jn_submitted">
                <div class="flex items-center gap-2">
                    <button type="button" @click="item.jn_submitted = true; item.makeup_date = ''; item.makeup_start = ''; item.makeup_end = ''; item.show_cal = false"
                            class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-semibold rounded-lg border-2 border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 hover:border-emerald-300 transition-all">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                        {{ __('Topshirilgan') }}
                    </button>
                    {{-- JN: sana oralig'i tanlash --}}
                    <template x-if="item.assessment_type === 'jn'">
                        <div class="cal-trigger flex-1" :class="item.show_cal ? 'active' : ''" @click="item.show_cal = !item.show_cal"
                             style="padding:8px 12px; font-size:13px; border-radius:8px;">
                            <div>
                                <template x-if="item.makeup_start && item.makeup_end">
                                    <span class="cal-trigger-text" x-text="fmtDate(item.makeup_start) + ' {{ __('dan') }} ' + fmtDate(item.makeup_end) + ' {{ __('gacha') }}'"></span>
                                </template>
                                <template x-if="item.makeup_start && !item.makeup_end">
                                    <span class="cal-trigger-text"><span x-text="fmtDate(item.makeup_start)"></span> <span class="text-gray-400 font-normal">— {{ __('tugash?') }}</span></span>
                                </template>
                                <template x-if="!item.makeup_start">
                                    <span class="cal-trigger-placeholder">{{ __('Sana oralig\'ini tanlang') }}</span>
                                </template>
                            </div>
                            <div class="flex items-center gap-2">
                                <template x-if="item.makeup_start || item.makeup_end">
                                    <button type="button" @click.stop="clearMiniDates(item._idx); item.show_cal = false"
                                            class="text-xs text-red-400 hover:text-red-600 font-medium">{{ __('Tozalash') }}</button>
                                </template>
                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                            </div>
                        </div>
                    </template>
                    {{-- Non-JN: bitta sana tanlash --}}
                    <template x-if="item.assessment_type !== 'jn'">
                        <div class="cal-trigger flex-1" :class="item.show_cal ? 'active' : ''" @click="item.show_cal = !item.show_cal"
                             style="padding:8px 12px; font-size:13px; border-radius:8px;">
                            <div>
                                <template x-if="item.makeup_date">
                                    <span class="cal-trigger-text flex items-center gap-1.5">
                                        <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                        <span x-text="fmtDate(item.makeup_date)"></span>
                                    </span>
                                </template>
                                <template x-if="!item.makeup_date">
                                    <span class="cal-trigger-placeholder">{{ __('Sanani tanlang') }}</span>
                                </template>
                            </div>
                            <div class="flex items-center gap-2">
                                <template x-if="item.makeup_date">
                                    <button type="button" @click.stop="item.makeup_date = ''; item.show_cal = false"
                                            class="text-xs text-red-400 hover:text-red-600 font-medium">{{ __('Bekor qilish') }}</button>
                                </template>
                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                            </div>
                        </div>
                    </template>
                </div>
            </template>
            <template x-if="item.jn_submitted">
                <div class="flex items-center gap-2 px-3 py-2 bg-emerald-50 border border-emerald-200 rounded-lg">
                    <svg class="w-4 h-4 text-emerald-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <span class="text-sm font-semibold text-emerald-700">{{ __('Topshirilgan') }}</span>
                    <button type="button" @click="item.jn_submitted = false"
                            class="ml-auto text-xs text-gray-400 hover:text-red-500 font-medium">{{ __('Bekor qilish') }}</button>
                </div>
            </template>
        </div>
    </template>

    {{-- Mini calendar dropdown (faqat submitted bo'lmaganda) --}}
    <div x-show="item.show_cal && !item.jn_submitted" x-transition.origin.top x-cloak class="cal-dropdown">
        <div class="rc-calendar rc-mini">
            <div class="rc-header">
                <button type="button" @click="miniPrevMonth(item._idx)" class="rc-nav">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"></path></svg>
                </button>
                <span class="rc-header-title" x-text="getMiniMonthLabel(item._idx)"></span>
                <button type="button" @click="miniNextMonth(item._idx)" class="rc-nav">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                </button>
            </div>
            <div class="rc-weekdays">
                <span>Du</span><span>Se</span><span>Cho</span><span>Pa</span><span>Ju</span><span>Sha</span><span>Ya</span>
            </div>
            <div class="rc-grid">
                <template x-for="cell in getMiniCells(item._idx)" :key="cell.key">
                    <button type="button"
                            @click="cell.date && !cell.disabled && miniPickDate(item._idx, cell.dateStr)"
                            :disabled="cell.disabled || !cell.date"
                            :class="{
                                'rc-empty': !cell.date,
                                'rc-disabled': cell.disabled && cell.date,
                                'rc-sunday': cell.isSunday && cell.date,
                                'rc-today': cell.isToday,
                                'rc-start': item.assessment_type === 'jn' && cell.dateStr === item.makeup_start,
                                'rc-end': item.assessment_type === 'jn' && cell.dateStr === item.makeup_end,
                                'rc-in-range': item.assessment_type === 'jn' && miniInRange(item._idx, cell.dateStr),
                                'rc-picked-single': item.assessment_type !== 'jn' && cell.dateStr === item.makeup_date,
                                'rc-taken': cell.takenByJn || cell.takenByOski
                            }"
                            class="rc-day"
                            x-text="cell.day"></button>
                </template>
            </div>
        </div>
    </div>
</div>
{{-- Hidden inputs --}}
<input type="hidden" :name="'makeup_dates['+item._idx+'][subject_name]'" :value="item.subject_name">
<input type="hidden" :name="'makeup_dates['+item._idx+'][subject_id]'" :value="item.subject_id || ''">
<input type="hidden" :name="'makeup_dates['+item._idx+'][assessment_type]'" :value="item.assessment_type">
<input type="hidden" :name="'makeup_dates['+item._idx+'][assessment_type_code]'" :value="item.assessment_type_code">
<input type="hidden" :name="'makeup_dates['+item._idx+'][original_date]'" :value="item.original_date">
<input type="hidden" :name="'makeup_dates['+item._idx+'][makeup_date]'" :value="item.assessment_type !== 'jn' ? (item.makeup_date || '') : ''">
<input type="hidden" :name="'makeup_dates['+item._idx+'][makeup_start]'" :value="item.assessment_type === 'jn' ? (item.makeup_start || '') : ''">
<input type="hidden" :name="'makeup_dates['+item._idx+'][makeup_end]'" :value="item.assessment_type === 'jn' ? (item.makeup_end || '') : ''">
<input type="hidden" :name="'makeup_dates['+item._idx+'][jn_submitted]'" :value="item.jn_submitted ? '1' : '0'">
<input type="hidden" :name="'makeup_dates['+item._idx+'][is_makeup_period]'" :value="item.is_makeup_period ? '1' : '0'">
