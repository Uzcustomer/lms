<div class="cal-dropdown-wrap" @click.outside="item.show_cal = false">
    {{-- JN: range trigger --}}
    <template x-if="item.assessment_type === 'jn'">
        <div>
            <div class="cal-trigger" :class="item.show_cal ? 'active' : ''" @click="item.show_cal = !item.show_cal"
                 style="padding:8px 12px; font-size:13px; border-radius:8px;">
                <div>
                    <template x-if="item.makeup_start && item.makeup_end">
                        <span class="cal-trigger-text" x-text="fmtDate(item.makeup_start) + ' dan ' + fmtDate(item.makeup_end) + ' gacha'"></span>
                    </template>
                    <template x-if="item.makeup_start && !item.makeup_end">
                        <span class="cal-trigger-text"><span x-text="fmtDate(item.makeup_start)"></span> <span class="text-gray-400 font-normal">— tugash?</span></span>
                    </template>
                    <template x-if="!item.makeup_start">
                        <span class="cal-trigger-placeholder">Sana oralig'ini tanlang</span>
                    </template>
                </div>
                <div class="flex items-center gap-2">
                    <template x-if="item.makeup_start || item.makeup_end">
                        <button type="button" @click.stop="clearMiniDates(item._idx); item.show_cal = false"
                                class="text-xs text-red-400 hover:text-red-600 font-medium">Tozalash</button>
                    </template>
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                </div>
            </div>
        </div>
    </template>
    {{-- Non-JN: single date trigger --}}
    <template x-if="item.assessment_type !== 'jn'">
        <div>
            <div class="cal-trigger" :class="item.show_cal ? 'active' : ''" @click="item.show_cal = !item.show_cal"
                 style="padding:8px 12px; font-size:13px; border-radius:8px;">
                <div>
                    <template x-if="item.makeup_date">
                        <span class="cal-trigger-text flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            <span x-text="fmtDate(item.makeup_date)"></span>
                        </span>
                    </template>
                    <template x-if="!item.makeup_date">
                        <span class="cal-trigger-placeholder">Sanani tanlang</span>
                    </template>
                </div>
                <div class="flex items-center gap-2">
                    <template x-if="item.makeup_date">
                        <button type="button" @click.stop="item.makeup_date = ''; item.show_cal = false"
                                class="text-xs text-red-400 hover:text-red-600 font-medium">Bekor qilish</button>
                    </template>
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                </div>
            </div>
        </div>
    </template>
    {{-- Mini calendar dropdown --}}
    <div x-show="item.show_cal" x-transition.origin.top x-cloak class="cal-dropdown">
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
