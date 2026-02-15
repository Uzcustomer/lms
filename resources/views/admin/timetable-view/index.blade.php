<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Dars jadvali
            </h2>
        </div>
    </x-slot>

    @php
        $routePrefix = auth()->guard('teacher')->check()
            && !in_array(session('active_role'), ['superadmin','admin','kichik_admin'])
            ? 'teacher' : 'admin';
    @endphp

    <div class="py-4" x-data="timetableView()" x-init="init()">

        {{-- Filtr paneli --}}
        <div class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8 mb-4">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">

                {{-- Filtr turi tanlash (tablar) --}}
                <div class="flex flex-wrap gap-2 mb-4">
                    <button @click="setFilterType('teacher')"
                            :class="filterType === 'teacher' ? 'bg-blue-600 text-white shadow-md' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600'"
                            class="px-4 py-2 text-sm font-medium rounded-lg transition-all duration-200 flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        O'qituvchi kesimida
                    </button>
                    <button @click="setFilterType('auditorium')"
                            :class="filterType === 'auditorium' ? 'bg-emerald-600 text-white shadow-md' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600'"
                            class="px-4 py-2 text-sm font-medium rounded-lg transition-all duration-200 flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                        Auditoriya kesimida
                    </button>
                    <button @click="setFilterType('subject')"
                            :class="filterType === 'subject' ? 'bg-purple-600 text-white shadow-md' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600'"
                            class="px-4 py-2 text-sm font-medium rounded-lg transition-all duration-200 flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                        Fan kesimida
                    </button>
                    <button @click="setFilterType('group')"
                            :class="filterType === 'group' ? 'bg-amber-600 text-white shadow-md' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600'"
                            class="px-4 py-2 text-sm font-medium rounded-lg transition-all duration-200 flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                        Guruh kesimida
                    </button>
                </div>

                {{-- Semestr va Hafta tanlash --}}
                <div class="flex flex-wrap items-end gap-4">

                    {{-- Joriy semestr toggle --}}
                    <div class="flex items-center">
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" x-model="currentSemesterOnly" @change="onCurrentSemesterToggle()" class="sr-only peer">
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 rounded-full peer dark:bg-gray-600 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:after:border-gray-500 peer-checked:bg-blue-600"></div>
                            <span class="ml-2 text-sm font-medium text-gray-700 dark:text-gray-300">Joriy semestr</span>
                        </label>
                    </div>

                    {{-- Semestr tanlash --}}
                    <div class="min-w-[220px]" x-show="!currentSemesterOnly" x-cloak>
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Semestr</label>
                        <select x-model="selectedSemesterId" @change="onSemesterChange()"
                                class="w-full text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Tanlang...</option>
                            <template x-for="sem in allSemesters" :key="sem.semester_hemis_id">
                                <option :value="sem.semester_hemis_id"
                                        :selected="sem.semester_hemis_id == selectedSemesterId"
                                        x-text="sem.name + ' (' + sem.education_year + ')' + (sem.current ? ' - Joriy' : '')"></option>
                            </template>
                        </select>
                    </div>

                    {{-- Joriy semestr nomi (toggle on bo'lganda) --}}
                    <div x-show="currentSemesterOnly && currentSemesterName" x-cloak
                         class="flex items-center gap-2 px-3 py-2 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                        <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span class="text-sm font-medium text-blue-700 dark:text-blue-300" x-text="currentSemesterName"></span>
                    </div>

                    {{-- Hafta tanlash --}}
                    <div class="min-w-[320px]">
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">O'quv hafta</label>
                        <select x-model="selectedWeekId" @change="onWeekChange()"
                                class="w-full text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Tanlang...</option>
                            <template x-for="week in weeks" :key="week.id">
                                <option :value="week.id"
                                        :selected="week.id == selectedWeekId"
                                        x-text="week.label"></option>
                            </template>
                        </select>
                    </div>

                    {{-- Filtr qiymati tanlash --}}
                    <div class="min-w-[280px] flex-1 max-w-md" x-data="{ searchText: '' }">
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                            <span x-text="filterLabels[filterType]"></span>
                        </label>
                        <div class="relative">
                            <input type="text" x-model="searchText"
                                   :placeholder="'Qidirish...'"
                                   @input="filterDropdownOptions(searchText)"
                                   @focus="showDropdown = true"
                                   @click.stop
                                   class="w-full text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg focus:ring-blue-500 focus:border-blue-500 pr-8">
                            <div x-show="filterValue" @click="clearFilter(); searchText = ''"
                                 class="absolute right-2 top-1/2 -translate-y-1/2 cursor-pointer text-gray-400 hover:text-gray-600">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </div>
                            {{-- Dropdown ro'yxat --}}
                            <div x-show="showDropdown && filteredOptions.length > 0" x-cloak
                                 @click.outside="showDropdown = false"
                                 class="absolute z-50 mt-1 w-full max-h-60 overflow-y-auto bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-lg shadow-xl">
                                <template x-for="(opt, idx) in filteredOptions" :key="idx">
                                    <button @click="selectFilter(opt); searchText = opt; showDropdown = false"
                                            class="w-full text-left px-3 py-2 text-sm hover:bg-blue-50 dark:hover:bg-gray-700 transition"
                                            :class="filterValue === opt ? 'bg-blue-50 dark:bg-gray-700 text-blue-700 dark:text-blue-300 font-medium' : 'text-gray-700 dark:text-gray-300'"
                                            x-text="opt"></button>
                                </template>
                            </div>
                        </div>
                    </div>

                    {{-- Ko'rsatish tugmasi --}}
                    <button @click="loadGrid()" :disabled="loading || !filterValue"
                            class="px-5 py-2.5 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 disabled:opacity-50 transition flex items-center gap-2">
                        <svg x-show="!loading" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <svg x-show="loading" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        Ko'rsatish
                    </button>
                </div>

                {{-- Tanlangan filtr ko'rsatish --}}
                <div x-show="filterValue && !loading" x-cloak class="mt-3 flex items-center gap-2 flex-wrap">
                    <span class="text-xs text-gray-500 dark:text-gray-400">Filtr:</span>
                    <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-medium"
                          :class="{
                              'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300': filterType === 'teacher',
                              'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300': filterType === 'auditorium',
                              'bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300': filterType === 'subject',
                              'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300': filterType === 'group',
                          }">
                        <span x-text="filterLabels[filterType] + ': '"></span>
                        <span class="font-semibold" x-text="filterValue"></span>
                    </span>
                    <span x-show="selectedWeekLabel" class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300">
                        <span x-text="selectedWeekLabel"></span>
                    </span>
                    <span x-show="totalCount > 0" class="text-xs text-gray-400 dark:text-gray-500">
                        (<span x-text="totalCount"></span> ta dars)
                    </span>
                </div>
            </div>
        </div>

        {{-- Statistika --}}
        <div x-show="loaded && totalCount > 0" x-cloak class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8 mb-4">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                    <div class="text-center p-3 rounded-lg bg-blue-50 dark:bg-blue-900/20">
                        <div class="text-2xl font-bold text-blue-700 dark:text-blue-300" x-text="totalCount"></div>
                        <div class="text-xs text-blue-600 dark:text-blue-400">Jami darslar</div>
                    </div>
                    <div class="text-center p-3 rounded-lg bg-emerald-50 dark:bg-emerald-900/20">
                        <div class="text-2xl font-bold text-emerald-700 dark:text-emerald-300" x-text="uniqueGroups"></div>
                        <div class="text-xs text-emerald-600 dark:text-emerald-400">Guruhlar</div>
                    </div>
                    <div class="text-center p-3 rounded-lg bg-purple-50 dark:bg-purple-900/20">
                        <div class="text-2xl font-bold text-purple-700 dark:text-purple-300" x-text="uniqueSubjects"></div>
                        <div class="text-xs text-purple-600 dark:text-purple-400">Fanlar</div>
                    </div>
                    <div class="text-center p-3 rounded-lg bg-amber-50 dark:bg-amber-900/20">
                        <div class="text-2xl font-bold text-amber-700 dark:text-amber-300" x-text="uniqueTeachers"></div>
                        <div class="text-xs text-amber-600 dark:text-amber-400">O'qituvchilar</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ASC TIMETABLE GRID --}}
        <div class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                {{-- Loading --}}
                <div x-show="loading" class="p-12 text-center">
                    <svg class="w-8 h-8 animate-spin mx-auto text-blue-500" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Yuklanmoqda...</p>
                </div>

                {{-- Bo'sh holat --}}
                <div x-show="!loading && !loaded" class="p-12 text-center">
                    <svg class="w-16 h-16 mx-auto text-gray-300 dark:text-gray-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    <p class="text-gray-500 dark:text-gray-400 text-sm">Dars jadvalini ko'rish uchun hafta va filtrni tanlang.</p>
                    <p class="text-gray-400 dark:text-gray-500 text-xs mt-1">Tepada filtr turini, haftani va qiymatni tanlang, so'ng "Ko'rsatish" tugmasini bosing.</p>
                </div>

                {{-- Ma'lumot topilmadi --}}
                <div x-show="!loading && loaded && totalCount === 0" class="p-12 text-center">
                    <svg class="w-16 h-16 mx-auto text-gray-300 dark:text-gray-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <p class="text-gray-500 dark:text-gray-400 text-sm">Tanlangan filtr bo'yicha jadval topilmadi.</p>
                </div>

                {{-- Grid jadval --}}
                <div x-show="!loading && loaded && totalCount > 0" x-cloak class="overflow-x-auto">
                    <table class="w-full border-collapse min-w-[900px]">
                        <thead>
                            <tr>
                                <th class="tv-header tv-time-col">Juftlik</th>
                                <template x-for="(dayName, dayNum) in days" :key="dayNum">
                                    <th class="tv-header" x-text="dayName"></th>
                                </template>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="pair in pairs" :key="pair.code">
                                <tr>
                                    <td class="tv-time-cell">
                                        <div class="font-semibold text-sm" x-text="pair.name"></div>
                                        <div class="text-xs text-gray-400 mt-0.5">
                                            <span x-text="pair.start ? pair.start.substring(0,5) : ''"></span>
                                            <span x-show="pair.start && pair.end"> - </span>
                                            <span x-text="pair.end ? pair.end.substring(0,5) : ''"></span>
                                        </div>
                                    </td>
                                    <template x-for="(dayName, dayNum) in days" :key="dayNum">
                                        <td class="tv-cell" :id="'cell-' + dayNum + '-' + pair.code">
                                            <template x-for="card in getCellCards(dayNum, pair.code)" :key="card.id">
                                                <div class="tv-card" :class="getCardColorClass(card)">
                                                    {{-- Fan nomi --}}
                                                    <div class="tv-card-subject" x-text="card.subject_name"></div>

                                                    {{-- Filtrga qarab info ko'rsatish --}}
                                                    <template x-if="filterType !== 'teacher'">
                                                        <div class="tv-card-teacher" x-text="card.employee_name || ''"></div>
                                                    </template>

                                                    <div class="tv-card-meta">
                                                        <template x-if="filterType !== 'auditorium'">
                                                            <span x-show="card.auditorium_name" x-text="card.auditorium_name"></span>
                                                        </template>
                                                        <span x-show="card.training_type_name" class="tv-card-type">
                                                            <template x-if="filterType !== 'auditorium' && card.auditorium_name">
                                                                <span class="mx-1">|</span>
                                                            </template>
                                                            <span x-text="card.training_type_name"></span>
                                                        </span>
                                                    </div>

                                                    <template x-if="filterType !== 'group'">
                                                        <div class="tv-card-group" x-text="card.group_name"></div>
                                                    </template>

                                                    {{-- Sana --}}
                                                    <div class="tv-card-date" x-text="card.lesson_date"></div>
                                                </div>
                                            </template>
                                            {{-- Bo'sh yacheyka --}}
                                            <div x-show="getCellCards(dayNum, pair.code).length === 0"
                                                 class="tv-cell-empty">
                                                <span class="text-xs">-</span>
                                            </div>
                                        </td>
                                    </template>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                {{-- Legend --}}
                <div x-show="!loading && loaded && totalCount > 0" x-cloak
                     class="px-5 py-3 border-t border-gray-200 dark:border-gray-700 flex flex-wrap items-center gap-4 text-xs">
                    <span class="text-gray-500 dark:text-gray-400 font-medium mr-1">Ranglar:</span>
                    <div class="flex items-center gap-1.5"><span class="w-3.5 h-3.5 rounded bg-blue-100 dark:bg-blue-900/40 border border-blue-300"></span><span class="text-gray-500 dark:text-gray-400">Ma'ruza</span></div>
                    <div class="flex items-center gap-1.5"><span class="w-3.5 h-3.5 rounded bg-green-100 dark:bg-green-900/40 border border-green-300"></span><span class="text-gray-500 dark:text-gray-400">Amaliy</span></div>
                    <div class="flex items-center gap-1.5"><span class="w-3.5 h-3.5 rounded bg-amber-100 dark:bg-amber-900/40 border border-amber-300"></span><span class="text-gray-500 dark:text-gray-400">Seminar</span></div>
                    <div class="flex items-center gap-1.5"><span class="w-3.5 h-3.5 rounded bg-purple-100 dark:bg-purple-900/40 border border-purple-300"></span><span class="text-gray-500 dark:text-gray-400">Laboratoriya</span></div>
                    <div class="flex items-center gap-1.5"><span class="w-3.5 h-3.5 rounded bg-gray-100 dark:bg-gray-600 border border-gray-300"></span><span class="text-gray-500 dark:text-gray-400">Boshqa</span></div>
                </div>
            </div>
        </div>
    </div>

    {{-- CSS --}}
    <style>
        [x-cloak] { display: none !important; }
        .tv-header { background: #f0f4ff; color: #374151; font-size: 0.8rem; font-weight: 600; text-align: center; padding: 10px 8px; border: 1px solid #e2e8f0; position: sticky; top: 0; z-index: 10; }
        .dark .tv-header { background: #1e293b; color: #e2e8f0; border-color: #334155; }
        .tv-time-col { width: 100px; min-width: 100px; }
        .tv-time-cell { background: #f8fafc; text-align: center; padding: 8px; border: 1px solid #e2e8f0; vertical-align: middle; color: #475569; width: 100px; min-width: 100px; }
        .dark .tv-time-cell { background: #1e293b; border-color: #334155; color: #94a3b8; }
        .tv-cell { border: 1px solid #e2e8f0; padding: 4px; vertical-align: top; min-height: 80px; min-width: 130px; position: relative; }
        .dark .tv-cell { border-color: #334155; }
        .tv-cell-empty { display: flex; align-items: center; justify-content: center; min-height: 50px; color: #cbd5e1; }

        .tv-card { border-radius: 8px; padding: 6px 8px; margin-bottom: 3px; font-size: 0.72rem; line-height: 1.3; position: relative; border: 2px solid transparent; transition: all 0.15s ease; }
        .tv-card:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.12); transform: scale(1.02); z-index: 5; }

        /* Dars turiga qarab ranglar */
        .tv-card-lecture { background: #eff6ff; border-color: #bfdbfe; color: #1e40af; }
        .dark .tv-card-lecture { background: #1e3a5f; border-color: #2563eb; color: #93c5fd; }
        .tv-card-practical { background: #f0fdf4; border-color: #86efac; color: #166534; }
        .dark .tv-card-practical { background: #14532d33; border-color: #22c55e; color: #86efac; }
        .tv-card-seminar { background: #fffbeb; border-color: #fcd34d; color: #92400e; }
        .dark .tv-card-seminar { background: #854d0e33; border-color: #eab308; color: #fde047; }
        .tv-card-lab { background: #faf5ff; border-color: #c4b5fd; color: #5b21b6; }
        .dark .tv-card-lab { background: #5b21b633; border-color: #8b5cf6; color: #c4b5fd; }
        .tv-card-other { background: #f3f4f6; border-color: #d1d5db; color: #374151; }
        .dark .tv-card-other { background: #374151; border-color: #6b7280; color: #d1d5db; }

        .tv-card-subject { font-weight: 600; font-size: 0.75rem; margin-bottom: 2px; }
        .tv-card-teacher { font-size: 0.68rem; opacity: 0.85; }
        .tv-card-meta { font-size: 0.65rem; opacity: 0.7; margin-top: 2px; }
        .tv-card-type { font-style: italic; }
        .tv-card-group { font-size: 0.65rem; font-weight: 500; margin-top: 2px; opacity: 0.8; }
        .tv-card-date { font-size: 0.6rem; opacity: 0.5; margin-top: 1px; text-align: right; }
    </style>

    {{-- JavaScript --}}
    <script>
    function timetableView() {
        const routePrefix = '{{ $routePrefix }}';

        // Server dan kelgan boshlang'ich ma'lumotlar
        const initialSemesters = @json($allSemesters);
        const initialCurrentSemester = @json($currentSemester);
        const initialWeeks = @json($currentWeeks);
        const initialCurrentWeekId = @json($currentWeekId);

        return {
            // Filtr
            filterType: 'teacher',
            filterValue: '',
            loading: false,
            loaded: false,
            showDropdown: false,

            // Semestr / Hafta
            currentSemesterOnly: true,
            allSemesters: initialSemesters,
            selectedSemesterId: initialCurrentSemester?.semester_hemis_id || '',
            currentSemesterName: initialCurrentSemester ? (initialCurrentSemester.name + ' (' + initialCurrentSemester.education_year + ')') : '',
            weeks: initialWeeks,
            selectedWeekId: initialCurrentWeekId || '',

            // Grid
            gridItems: {},
            pairs: [],
            days: {},

            // Filtr opsiyalari
            allOptions: [],
            filteredOptions: [],

            // Statistika
            totalCount: 0,
            uniqueGroups: 0,
            uniqueSubjects: 0,
            uniqueTeachers: 0,

            filterLabels: {
                'teacher': "O'qituvchi",
                'auditorium': 'Auditoriya',
                'subject': 'Fan',
                'group': 'Guruh',
            },

            get selectedWeekLabel() {
                if (!this.selectedWeekId) return '';
                const w = this.weeks.find(w => w.id == this.selectedWeekId);
                return w ? w.label : '';
            },

            init() {
                this.loadFilterOptions();
            },

            // ===== Semestr / Hafta =====
            onCurrentSemesterToggle() {
                if (this.currentSemesterOnly) {
                    // Joriy semestrga qaytish
                    if (initialCurrentSemester) {
                        this.selectedSemesterId = initialCurrentSemester.semester_hemis_id;
                        this.weeks = initialWeeks;
                        this.selectedWeekId = initialCurrentWeekId || '';
                    }
                } else {
                    // Barcha semestrlar ko'rsatish
                }
                this.loadFilterOptions();
            },

            async onSemesterChange() {
                if (!this.selectedSemesterId) {
                    this.weeks = [];
                    this.selectedWeekId = '';
                    return;
                }

                try {
                    const res = await fetch(`{{ route($routePrefix . '.timetable-view.weeks') }}?semester_hemis_id=${this.selectedSemesterId}`);
                    const data = await res.json();
                    this.weeks = data.weeks || [];
                    this.selectedWeekId = data.current_week_id || (this.weeks.length > 0 ? this.weeks[0].id : '');
                    this.loadFilterOptions();
                } catch (e) {
                    console.error('Weeks load error:', e);
                }
            },

            onWeekChange() {
                this.loadFilterOptions();
                if (this.filterValue) {
                    this.loadGrid();
                }
            },

            // ===== Filtr =====
            setFilterType(type) {
                if (this.filterType === type) return;
                this.filterType = type;
                this.filterValue = '';
                this.loadFilterOptions();
            },

            async loadFilterOptions() {
                try {
                    const params = new URLSearchParams({ filter_type: this.filterType });
                    if (this.selectedWeekId) {
                        params.set('week_id', this.selectedWeekId);
                    }
                    const res = await fetch(`{{ route($routePrefix . '.timetable-view.filter-options') }}?${params}`);
                    const data = await res.json();
                    this.allOptions = data.options || [];
                    this.filteredOptions = this.allOptions.slice(0, 100);
                } catch (e) {
                    console.error('Filter options error:', e);
                    this.allOptions = [];
                    this.filteredOptions = [];
                }
            },

            filterDropdownOptions(search) {
                this.showDropdown = true;
                if (!search) {
                    this.filteredOptions = this.allOptions.slice(0, 100);
                    return;
                }
                const q = search.toLowerCase();
                this.filteredOptions = this.allOptions.filter(o => o.toLowerCase().includes(q)).slice(0, 50);
            },

            selectFilter(value) {
                this.filterValue = value;
            },

            clearFilter() {
                this.filterValue = '';
                this.loaded = false;
                this.gridItems = {};
                this.totalCount = 0;
            },

            // ===== Grid =====
            async loadGrid() {
                if (!this.filterValue) return;
                this.loading = true;
                this.loaded = false;

                try {
                    const params = new URLSearchParams({
                        filter_type: this.filterType,
                        filter_value: this.filterValue,
                    });
                    if (this.selectedWeekId) {
                        params.set('week_id', this.selectedWeekId);
                    }

                    const res = await fetch(`{{ route($routePrefix . '.timetable-view.data') }}?${params}`);
                    const data = await res.json();
                    this.gridItems = data.items || {};
                    this.pairs = data.pairs || [];
                    this.days = data.days || {};

                    this.calculateStats();
                    this.loaded = true;
                } catch (e) {
                    console.error('Grid load error:', e);
                } finally {
                    this.loading = false;
                }
            },

            calculateStats() {
                const allCards = [];
                for (const key in this.gridItems) {
                    for (const card of this.gridItems[key]) {
                        allCards.push(card);
                    }
                }
                this.totalCount = allCards.length;
                this.uniqueGroups = new Set(allCards.map(c => c.group_name).filter(Boolean)).size;
                this.uniqueSubjects = new Set(allCards.map(c => c.subject_name).filter(Boolean)).size;
                this.uniqueTeachers = new Set(allCards.map(c => c.employee_name).filter(Boolean)).size;
            },

            getCellCards(dayNum, pairCode) {
                return this.gridItems[dayNum + '_' + pairCode] || [];
            },

            getCardColorClass(card) {
                const type = (card.training_type_name || '').toLowerCase();
                if (type.includes("ma'ruza") || type.includes('maruza') || type.includes('lecture')) return 'tv-card-lecture';
                if (type.includes('amaliy') || type.includes('practical')) return 'tv-card-practical';
                if (type.includes('seminar')) return 'tv-card-seminar';
                if (type.includes('laborat') || type.includes('lab')) return 'tv-card-lab';
                return 'tv-card-other';
            },
        };
    }
    </script>
</x-app-layout>
