<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Ma'ruza jadvalini joylashtirish
            </h2>
        </div>
    </x-slot>

    @php
        $routePrefix = auth()->guard('teacher')->check()
            && !in_array(session('active_role'), ['superadmin','admin','kichik_admin'])
            ? 'teacher' : 'admin';
    @endphp

    <div class="py-4" x-data="lectureSchedule()" x-init="init()" @click="closeContextMenu()" @keydown.escape.window="closeAllModals()">

        {{-- Flash xabarlar --}}
        @if(session('success'))
        <div class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8 mb-4">
            <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg flex items-center justify-between">
                <div class="flex items-center">
                    <svg class="w-5 h-5 mr-2 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    {{ session('success') }}
                </div>
                <button onclick="this.parentElement.remove()" class="text-green-600 hover:text-green-800">&times;</button>
            </div>
        </div>
        @endif

        @if(session('error'))
        <div class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8 mb-4">
            <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-2 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        {{ session('error') }}
                    </div>
                    <button onclick="this.parentElement.parentElement.remove()" class="text-red-600 hover:text-red-800">&times;</button>
                </div>
                @if(session('import_errors'))
                <ul class="mt-2 text-sm list-disc pl-8">
                    @foreach(session('import_errors') as $err)
                    <li>{{ $err }}</li>
                    @endforeach
                </ul>
                @endif
            </div>
        </div>
        @endif

        {{-- Yuqori panel --}}
        <div class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8 mb-4">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
                <div class="flex flex-wrap items-end gap-4">
                    {{-- Excel yuklash --}}
                    <form action="{{ route($routePrefix . '.lecture-schedule.import') }}" method="POST" enctype="multipart/form-data" class="flex flex-wrap items-end gap-3 flex-1">
                        @csrf
                        <div class="min-w-[200px]">
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Excel fayl</label>
                            <input type="file" name="file" accept=".xlsx,.xls,.csv" required
                                   class="block w-full text-sm text-gray-600 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 file:cursor-pointer file:transition-all file:duration-200 hover:file:shadow-md hover:file:scale-105 dark:text-gray-300 dark:file:bg-blue-900 dark:file:text-blue-200">
                        </div>
                        <div>
                            <button type="submit" class="btn-action btn-upload group">
                                <svg class="w-4 h-4 transition-transform duration-300 group-hover:-translate-y-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                                Yuklash
                            </button>
                        </div>
                    </form>

                    <a href="{{ route($routePrefix . '.lecture-schedule.template') }}"
                       class="btn-action btn-template group">
                        <svg class="w-4 h-4 transition-transform duration-300 group-hover:translate-y-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        Shablon
                    </a>

                    @if(isset($batches) && $batches->count() > 0)
                    <div class="min-w-[220px]">
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Yuklangan jadvallar</label>
                        <select x-model="activeBatchId" @change="loadGrid()"
                                class="w-full text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg focus:ring-blue-500 focus:border-blue-500 transition-shadow duration-200 hover:shadow-md">
                            @foreach($batches as $b)
                            <option value="{{ $b->id }}" {{ isset($activeBatch) && $activeBatch->id === $b->id ? 'selected' : '' }}>
                                {{ $b->file_name }} ({{ $b->created_at->format('d.m.Y H:i') }})
                            </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Hafta navigatsiyasi --}}
                    <div class="flex items-center gap-2">
                        <button @click="prevWeek()" :disabled="currentWeek <= 0"
                                class="w-10 h-10 flex items-center justify-center rounded-xl border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-blue-50 hover:border-blue-400 dark:hover:bg-blue-900/30 disabled:opacity-40 disabled:cursor-not-allowed transition-all shadow-sm hover:shadow">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                        </button>
                        <span class="px-5 py-2.5 text-base font-bold rounded-xl min-w-[120px] text-center select-none shadow-sm"
                              :class="currentWeek === 0 ? 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 border border-gray-200 dark:border-gray-600' : 'bg-blue-100 dark:bg-blue-900/50 text-blue-800 dark:text-blue-200 border border-blue-300 dark:border-blue-700'"
                              x-text="currentWeek === 0 ? 'Barchasi' : currentWeek + '-hafta'"></span>
                        <button @click="nextWeek()" :disabled="currentWeek >= 15"
                                class="w-10 h-10 flex items-center justify-center rounded-xl border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-blue-50 hover:border-blue-400 dark:hover:bg-blue-900/30 disabled:opacity-40 disabled:cursor-not-allowed transition-all shadow-sm hover:shadow">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                        </button>
                    </div>

                    <button @click="compareHemis()" :disabled="comparing"
                            class="btn-action btn-compare group">
                        <svg x-show="!comparing" class="w-4 h-4 transition-transform duration-300 group-hover:rotate-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                        <svg x-show="comparing" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        Hemis solishtirish
                    </button>

                    {{-- Export dropdown --}}
                    <div class="relative" x-data="{ exportOpen: false }" @click.outside="exportOpen = false">
                        <button @click="exportOpen = !exportOpen" class="btn-action btn-export group">
                            <svg class="w-4 h-4 transition-transform duration-300 group-hover:translate-y-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3M3 17V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z"/></svg>
                            Eksport
                            <svg class="w-3 h-3 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div x-show="exportOpen" x-cloak x-transition
                             class="absolute right-0 mt-2 w-56 bg-white dark:bg-gray-800 rounded-lg shadow-xl border border-gray-200 dark:border-gray-600 py-1 z-50">
                            <a :href="exportUrl" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700">
                                <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                Excel (ustunlar)
                            </a>
                            <a :href="exportGridUrl" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700">
                                <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                                Excel (jadval ko'rinishida)
                            </a>
                            <a :href="exportPdfUrl" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700">
                                <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                PDF (jadval ko'rinishida)
                            </a>
                        </div>
                    </div>

                    <form method="POST" action="{{ route($routePrefix . '.lecture-schedule.destroy', ['id' => $activeBatch->id ?? 0]) }}" onsubmit="return confirm('Rostdan o\'chirmoqchimisiz?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn-action btn-delete group">
                            <svg class="w-4 h-4 transition-transform duration-300 group-hover:scale-110" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            O'chirish
                        </button>
                    </form>
                    @endif
                </div>
            </div>
        </div>

        {{-- Statistika panel --}}
        <div x-show="hemisResult" x-cloak class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8 mb-4">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Hemis solishtirish natijalari</h3>
                <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
                    <div class="text-center p-3 rounded-lg bg-gray-50 dark:bg-gray-700/50">
                        <div class="text-2xl font-bold text-gray-800 dark:text-gray-200" x-text="hemisResult?.total || 0"></div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">Jami</div>
                    </div>
                    <div class="text-center p-3 rounded-lg bg-green-50 dark:bg-green-900/30">
                        <div class="text-2xl font-bold text-green-600 dark:text-green-400" x-text="hemisResult?.match || 0"></div>
                        <div class="text-xs text-green-600 dark:text-green-400">Mos</div>
                    </div>
                    <div class="text-center p-3 rounded-lg bg-yellow-50 dark:bg-yellow-900/30">
                        <div class="text-2xl font-bold text-yellow-600 dark:text-yellow-400" x-text="hemisResult?.partial || 0"></div>
                        <div class="text-xs text-yellow-600 dark:text-yellow-400">Qisman</div>
                    </div>
                    <div class="text-center p-3 rounded-lg bg-red-50 dark:bg-red-900/30">
                        <div class="text-2xl font-bold text-red-600 dark:text-red-400" x-text="hemisResult?.mismatch || 0"></div>
                        <div class="text-xs text-red-600 dark:text-red-400">Mos emas</div>
                    </div>
                    <div class="text-center p-3 rounded-lg bg-gray-100 dark:bg-gray-600/50">
                        <div class="text-2xl font-bold text-gray-500 dark:text-gray-400" x-text="hemisResult?.not_found || 0"></div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">Topilmadi</div>
                    </div>
                </div>
                <template x-if="hemisResult?.mismatches?.length > 0">
                    <div class="mt-4">
                        <h4 class="text-xs font-semibold text-red-600 dark:text-red-400 mb-2">Nomuvofiqliklar:</h4>
                        <div class="max-h-48 overflow-y-auto space-y-1">
                            <template x-for="m in hemisResult.mismatches" :key="m.id">
                                <div class="text-xs px-3 py-2 rounded-lg bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-300 cursor-pointer hover:bg-red-100 dark:hover:bg-red-900/40"
                                     @click="highlightCell(m.id)" x-text="m.message"></div>
                            </template>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        {{-- ===== TAB NAVIGATSIYA ===== --}}
        <div class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8 mb-4">
            <div class="flex items-center gap-1 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-1.5">
                <button @click="activeTab = 'jadval'"
                        class="tab-btn" :class="activeTab === 'jadval' ? 'tab-btn-active' : 'tab-btn-inactive'">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                    Jadval
                </button>
                <button @click="activeTab = 'xatolar'"
                        class="tab-btn" :class="activeTab === 'xatolar' ? 'tab-btn-active tab-btn-active-red' : 'tab-btn-inactive'">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    Xatolar
                    <span x-show="conflicts.length > 0" x-cloak class="ml-1 px-1.5 py-0.5 text-xs font-bold rounded-full bg-red-500 text-white" x-text="conflicts.length"></span>
                </button>
                <button @click="activeTab = 'curriculum'; if (!curriculumResult) loadCurriculum()"
                        class="tab-btn" :class="activeTab === 'curriculum' ? 'tab-btn-active tab-btn-active-emerald' : 'tab-btn-inactive'">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                    O'quv reja
                    <span x-show="curriculumResult && (curriculumResult.not_in_schedule + curriculumResult.hours_mismatch) > 0" x-cloak
                          class="ml-1 px-1.5 py-0.5 text-xs font-bold rounded-full bg-amber-500 text-white"
                          x-text="curriculumResult.not_in_schedule + curriculumResult.hours_mismatch"></span>
                </button>
                <button @click="activeTab = 'xonalar'"
                        class="tab-btn" :class="activeTab === 'xonalar' ? 'tab-btn-active tab-btn-active-teal' : 'tab-btn-inactive'">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                    Xonalar kesimida
                </button>
            </div>
        </div>

        {{-- ===== TAB 1: JADVAL ===== --}}
        <div x-show="activeTab === 'jadval'" class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div x-show="loading" class="p-12 text-center">
                    <svg class="w-8 h-8 animate-spin mx-auto text-blue-500" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Yuklanmoqda...</p>
                </div>

                <div x-show="!loading && Object.keys(gridItems).length === 0" class="p-12 text-center">
                    <svg class="w-16 h-16 mx-auto text-gray-300 dark:text-gray-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    <p class="text-gray-500 dark:text-gray-400 text-sm">Hali jadval yuklanmagan. Excel faylni yuklang yoki ro'yxatdan tanlang.</p>
                </div>

                {{-- Grid jadval --}}
                <div x-show="!loading && Object.keys(gridItems).length > 0" class="overflow-x-auto">
                    <table class="w-full border-collapse min-w-[900px]">
                        <thead>
                            <tr>
                                <th class="asc-header asc-time-col">Juftlik</th>
                                <template x-for="(dayName, dayNum) in days" :key="dayNum">
                                    <th class="asc-header" x-text="dayName"></th>
                                </template>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="pair in pairs" :key="pair.code">
                                <tr>
                                    <td class="asc-time-cell">
                                        <div class="font-semibold text-sm" x-text="pair.name"></div>
                                        <div class="text-xs text-gray-400 mt-0.5">
                                            <span x-text="pair.start ? pair.start.substring(0,5) : ''"></span>
                                            <span x-show="pair.start && pair.end"> - </span>
                                            <span x-text="pair.end ? pair.end.substring(0,5) : ''"></span>
                                        </div>
                                    </td>
                                    <template x-for="(dayName, dayNum) in days" :key="dayNum">
                                        <td class="asc-cell"
                                            :id="'cell-' + dayNum + '-' + pair.code"
                                            :data-day="dayNum"
                                            :data-pair="pair.code"
                                            @dragover.prevent="onDragOver($event)"
                                            @dragleave="onDragLeave($event)"
                                            @drop.prevent="onDrop($event, dayNum, pair.code)"
                                            @dblclick="openAddModal(dayNum, pair.code)">
                                            <template x-for="card in getCellCards(dayNum, pair.code)" :key="card.id">
                                                <div class="asc-card"
                                                     :id="'card-' + card.id"
                                                     :class="getCardClass(card)"
                                                     draggable="true"
                                                     @dragstart="onDragStart($event, card)"
                                                     @dragend="onDragEnd($event)"
                                                     @contextmenu.prevent="openContextMenu($event, card)"
                                                     @click.stop="openEditModal(card)"
                                                     title="Bosing - tahrirlash">
                                                    <div class="asc-card-subject">
                                                        <span x-text="card.subject_name"></span>
                                                        <span x-show="currentWeek === 0 && (card.week_parity || card.weeks)"
                                                              class="asc-card-week-label"
                                                              x-text="getWeekLabel(card)"></span>
                                                    </div>
                                                    <div class="asc-card-teacher" x-text="card.employee_name || ''"></div>
                                                    <div class="asc-card-meta">
                                                        <span x-show="card.building_name || card.auditorium_name" x-text="[card.building_name, card.auditorium_name].filter(Boolean).join(', ')"></span>
                                                        <span x-show="card.training_type_name" class="mx-1">|</span>
                                                        <span x-show="card.training_type_name" class="asc-card-type" x-text="card.training_type_name"></span>
                                                    </div>
                                                    <div class="asc-card-group" x-text="card.group_source || card.group_name"></div>
                                                    <div class="asc-card-edit-icon">
                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                                    </div>
                                                    <div x-show="card.has_conflict" class="asc-card-badge asc-badge-conflict" title="Ichki konflikt">!</div>
                                                    <template x-if="card.hemis_diff && card.hemis_diff.length > 0">
                                                        <div class="asc-card-tooltip" x-show="hoveredCard === card.id"
                                                             @mouseenter="hoveredCard = card.id" @mouseleave="hoveredCard = null">
                                                            <div class="text-xs font-semibold mb-1">Hemis farqlar:</div>
                                                            <template x-for="d in card.hemis_diff" :key="d.field">
                                                                <div class="text-xs">
                                                                    <span class="font-medium" x-text="d.field + ':'"></span>
                                                                    <span class="text-red-300 line-through" x-text="d.uploaded"></span>
                                                                    <span class="mx-1">&rarr;</span>
                                                                    <span class="text-green-300" x-text="d.hemis"></span>
                                                                </div>
                                                            </template>
                                                        </div>
                                                    </template>
                                                </div>
                                            </template>
                                            {{-- Bo'sh yacheykada "+" ko'rsatish --}}
                                            <div x-show="getCellCards(dayNum, pair.code).length === 0"
                                                 class="asc-cell-empty" @click.stop="openAddModal(dayNum, pair.code)">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                            </div>
                                        </td>
                                    </template>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                {{-- Legend --}}
                <div x-show="!loading && Object.keys(gridItems).length > 0" class="px-5 py-3 border-t border-gray-200 dark:border-gray-700 flex flex-wrap items-center gap-4 text-xs">
                    <span class="text-gray-500 dark:text-gray-400 font-medium mr-1">Izoh:</span>
                    <div class="flex items-center gap-1.5"><span class="w-3.5 h-3.5 rounded bg-green-100 dark:bg-green-900/40 border border-green-300"></span><span class="text-gray-500 dark:text-gray-400">Mos</span></div>
                    <div class="flex items-center gap-1.5"><span class="w-3.5 h-3.5 rounded bg-yellow-100 dark:bg-yellow-900/40 border border-yellow-300"></span><span class="text-gray-500 dark:text-gray-400">Qisman</span></div>
                    <div class="flex items-center gap-1.5"><span class="w-3.5 h-3.5 rounded bg-red-100 dark:bg-red-900/40 border border-red-300"></span><span class="text-gray-500 dark:text-gray-400">Mos emas</span></div>
                    <div class="flex items-center gap-1.5"><span class="w-3.5 h-3.5 rounded bg-gray-200 dark:bg-gray-600 border border-gray-300"></span><span class="text-gray-500 dark:text-gray-400">Topilmadi</span></div>
                    <div class="flex items-center gap-1.5"><span class="w-3.5 h-3.5 rounded border-2 border-purple-400"></span><span class="text-gray-500 dark:text-gray-400">Konflikt</span></div>
                    <div class="flex items-center gap-1.5"><span class="w-3.5 h-3.5 rounded bg-blue-50 dark:bg-blue-900/30 border border-blue-200"></span><span class="text-gray-500 dark:text-gray-400">Tekshirilmagan</span></div>
                    <span class="text-gray-400 dark:text-gray-500 ml-2">| Drag&Drop = ko'chirish | Bosish = tahrirlash | O'ng tugma = menyu</span>
                </div>
            </div>
        </div>

        {{-- ===== TAB 2: XATOLAR ===== --}}
        <div x-show="activeTab === 'xatolar'" x-cloak class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                {{-- Xatolar bosh qismi --}}
                <div class="p-5 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                    <h3 class="text-base font-bold text-gray-800 dark:text-gray-200 flex items-center gap-2">
                        <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        Ichki konfliktlar
                    </h3>
                    <span class="px-3 py-1 text-sm font-bold rounded-full"
                          :class="filteredConflicts.length > 0 ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' : 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'"
                          x-text="filteredConflicts.length > 0 ? filteredConflicts.length + ' / ' + conflicts.length + ' ta xato' : 'Xato yo\'q'"></span>
                </div>

                {{-- Filtr bo'yicha --}}
                <div x-show="conflicts.length > 0" class="px-5 py-3 border-b border-gray-200 dark:border-gray-700 flex flex-wrap items-center gap-2">
                    <span class="text-xs font-semibold text-gray-500 dark:text-gray-400 mr-1">Filtr:</span>
                    <button @click="conflictFilter = ''"
                            class="conflict-filter-pill"
                            :class="conflictFilter === '' ? 'conflict-filter-active' : 'conflict-filter-inactive'">
                        Barchasi <span class="ml-1 opacity-60" x-text="'(' + conflicts.length + ')'"></span>
                    </button>
                    <template x-for="ft in conflictTypes" :key="ft.type">
                        <button @click="conflictFilter = ft.type"
                                class="conflict-filter-pill"
                                :class="conflictFilter === ft.type ? 'conflict-filter-active' : 'conflict-filter-inactive'"
                                x-text="ft.label + ' (' + ft.count + ')'"></button>
                    </template>
                </div>

                {{-- Bo'sh holat --}}
                <div x-show="conflicts.length === 0" class="p-12 text-center">
                    <svg class="w-16 h-16 mx-auto text-green-300 dark:text-green-700 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <p class="text-gray-500 dark:text-gray-400 text-sm">Konflikt topilmadi. Jadval to'g'ri tuzilgan.</p>
                </div>

                {{-- Konfliktlar ro'yxati --}}
                <div x-show="filteredConflicts.length > 0" class="divide-y divide-gray-100 dark:divide-gray-700">
                    <template x-for="(c, ci) in filteredConflicts" :key="c._uid">
                        <div class="conflict-row">
                            <div class="flex items-center gap-3 px-5 py-3 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors"
                                 @click="openConflictDetail(c)">
                                {{-- Raqam --}}
                                <span class="flex-shrink-0 w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold text-white"
                                      :class="getConflictBadgeColor(c.type)"
                                      x-text="ci + 1"></span>

                                {{-- Turi badge --}}
                                <span class="flex-shrink-0 px-2 py-0.5 text-xs font-semibold rounded"
                                      :class="getConflictBadgeClass(c.type)"
                                      x-text="getConflictTypeName(c.type)"></span>

                                {{-- Kun va juftlik --}}
                                <span x-show="c.weekDay" class="flex-shrink-0 px-2 py-0.5 text-xs font-medium rounded bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300"
                                      x-text="c.dayName + ', ' + c.pairName"></span>
                                <span x-show="!c.weekDay" class="flex-shrink-0 px-2 py-0.5 text-xs font-medium rounded bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300">Umumiy</span>

                                {{-- Xabar --}}
                                <span class="flex-1 text-sm text-gray-700 dark:text-gray-300 truncate" x-text="c.message"></span>

                                {{-- Batafsil icon --}}
                                <svg class="w-5 h-5 flex-shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        {{-- ===== KONFLIKT BATAFSIL MODAL ===== --}}
        <div x-show="conflictDetail.show" x-cloak class="ls-modal-overlay" @click.self="conflictDetail.show = false" style="z-index:1001">
            <div class="ls-modal-box" style="max-width:640px" @click.stop>
                {{-- Header --}}
                <div class="ls-modal-header" :style="'background:' + getConflictHeaderGradient(conflictDetail.data?.type)">
                    <div>
                        <h3 style="font-size:15px;font-weight:700;color:#fff;margin:0;" x-text="getConflictTypeName(conflictDetail.data?.type || '') + ' konflikti'"></h3>
                        <p style="font-size:12px;color:rgba(255,255,255,0.8);margin:3px 0 0;" x-text="conflictDetail.data?.dayName ? (conflictDetail.data.dayName + ', ' + conflictDetail.data.pairName) : 'Umumiy tekshiruv'"></p>
                    </div>
                    <button @click="conflictDetail.show = false" class="ls-modal-close">&times;</button>
                </div>

                {{-- Body --}}
                <div style="padding:16px 20px;max-height:70vh;overflow-y:auto;">
                    {{-- Xabar --}}
                    <div class="mb-4 p-3 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800">
                        <div class="text-sm font-semibold text-red-700 dark:text-red-300" x-text="conflictDetail.data?.message"></div>
                    </div>

                    {{-- Batafsil tushuntirish --}}
                    <div class="mb-4">
                        <h4 class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">Muammo nima?</h4>
                        <div class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed space-y-2" x-html="getConflictExplanation(conflictDetail.data)"></div>
                    </div>

                    {{-- Tegishli darslar --}}
                    <template x-if="conflictDetail.data?.cards?.length > 0">
                        <div class="mb-4">
                            <h4 class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">Tegishli darslar</h4>
                            <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                                <table class="w-full text-xs">
                                    <thead>
                                        <tr class="bg-gray-100 dark:bg-gray-800">
                                            <th class="px-3 py-2 text-left font-semibold text-gray-600 dark:text-gray-400">Potok/Guruh</th>
                                            <th class="px-3 py-2 text-left font-semibold text-gray-600 dark:text-gray-400">Fan</th>
                                            <th class="px-3 py-2 text-left font-semibold text-gray-600 dark:text-gray-400">O'qituvchi</th>
                                            <th class="px-3 py-2 text-left font-semibold text-gray-600 dark:text-gray-400">Xona</th>
                                            <th class="px-3 py-2 text-left font-semibold text-gray-600 dark:text-gray-400">Hafta</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                        <template x-for="card in conflictDetail.data.cards" :key="card.id">
                                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800 cursor-pointer"
                                                @click="conflictDetail.show = false; activeTab = 'jadval'; $nextTick(() => highlightCell(card.id))">
                                                <td class="px-3 py-2 font-medium text-gray-800 dark:text-gray-200">
                                                    <span x-text="card.group_source || card.group_name"></span>
                                                </td>
                                                <td class="px-3 py-2 text-gray-700 dark:text-gray-300" x-text="card.subject_name"></td>
                                                <td class="px-3 py-2 text-gray-700 dark:text-gray-300" x-text="card.employee_name || '—'"></td>
                                                <td class="px-3 py-2 text-gray-700 dark:text-gray-300" x-text="[card.building_name, card.auditorium_name].filter(Boolean).join(', ') || '—'"></td>
                                                <td class="px-3 py-2 text-gray-700 dark:text-gray-300" x-text="card.week_parity || 'barcha'"></td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </template>

                    {{-- Yechim tavsiyasi --}}
                    <div class="p-3 rounded-lg bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800">
                        <h4 class="text-xs font-bold text-blue-600 dark:text-blue-400 uppercase tracking-wide mb-1">Yechim</h4>
                        <div class="text-sm text-blue-700 dark:text-blue-300" x-html="getConflictSolution(conflictDetail.data)"></div>
                    </div>
                </div>

                {{-- Footer --}}
                <div class="ls-modal-footer">
                    <button @click="conflictDetail.show = false" class="ls-btn-cancel">Yopish</button>
                    <button x-show="conflictDetail.data?.ids?.length > 0"
                            @click="conflictDetail.show = false; activeTab = 'jadval'; $nextTick(() => highlightCells(conflictDetail.data.ids))"
                            class="ls-btn-save" style="background:linear-gradient(135deg,#3b82f6,#1d4ed8)">
                        Jadvalda ko'rsatish
                    </button>
                </div>
            </div>
        </div>

        {{-- ===== TAB 3: O'QUV REJA BILAN SOLISHTIRISH ===== --}}
        <div x-show="activeTab === 'curriculum'" x-cloak class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">

                {{-- Bosh qism + Yangilash tugmasi --}}
                <div class="p-5 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                    <h3 class="text-base font-bold text-gray-800 dark:text-gray-200 flex items-center gap-2">
                        <svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                        O'quv reja bilan solishtirish
                    </h3>
                    <button @click="loadCurriculum()" :disabled="curriculumLoading"
                            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold rounded-lg transition-all"
                            :class="curriculumLoading ? 'bg-gray-200 text-gray-400 cursor-not-allowed' : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100 dark:bg-emerald-900/30 dark:text-emerald-300 dark:hover:bg-emerald-900/50'">
                        <svg x-show="!curriculumLoading" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        <svg x-show="curriculumLoading" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        <span x-text="curriculumLoading ? 'Tekshirilmoqda...' : 'Qayta tekshirish'"></span>
                    </button>
                </div>

                {{-- Yuklanmoqda --}}
                <div x-show="curriculumLoading && !curriculumResult" class="p-12 text-center">
                    <svg class="w-8 h-8 animate-spin mx-auto text-emerald-500" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">O'quv reja bilan solishtirilmoqda...</p>
                </div>

                {{-- Natijalar --}}
                <template x-if="curriculumResult">
                    <div>
                        {{-- Statistika kartalar --}}
                        <div class="p-5 border-b border-gray-200 dark:border-gray-700">
                            <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
                                <div class="text-center p-3 rounded-lg bg-gray-50 dark:bg-gray-700/50">
                                    <div class="text-2xl font-bold text-gray-800 dark:text-gray-200" x-text="curriculumResult.total_curriculum"></div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">O'quv rejada</div>
                                </div>
                                <div class="text-center p-3 rounded-lg bg-green-50 dark:bg-green-900/30 cursor-pointer hover:ring-2 ring-green-300" @click="curriculumTab = 'matched'">
                                    <div class="text-2xl font-bold text-green-600 dark:text-green-400" x-text="curriculumResult.matched"></div>
                                    <div class="text-xs text-green-600 dark:text-green-400">Mos</div>
                                </div>
                                <div class="text-center p-3 rounded-lg bg-amber-50 dark:bg-amber-900/30 cursor-pointer hover:ring-2 ring-amber-300" @click="curriculumTab = 'hours_mismatch'">
                                    <div class="text-2xl font-bold text-amber-600 dark:text-amber-400" x-text="curriculumResult.hours_mismatch"></div>
                                    <div class="text-xs text-amber-600 dark:text-amber-400">Soat farqi</div>
                                </div>
                                <div class="text-center p-3 rounded-lg bg-red-50 dark:bg-red-900/30 cursor-pointer hover:ring-2 ring-red-300" @click="curriculumTab = 'not_in_schedule'">
                                    <div class="text-2xl font-bold text-red-600 dark:text-red-400" x-text="curriculumResult.not_in_schedule"></div>
                                    <div class="text-xs text-red-600 dark:text-red-400">Jadvalda yo'q</div>
                                </div>
                                <div class="text-center p-3 rounded-lg bg-blue-50 dark:bg-blue-900/30 cursor-pointer hover:ring-2 ring-blue-300" @click="curriculumTab = 'not_in_curriculum'">
                                    <div class="text-2xl font-bold text-blue-600 dark:text-blue-400" x-text="curriculumResult.not_in_curriculum"></div>
                                    <div class="text-xs text-blue-600 dark:text-blue-400">Rejada yo'q</div>
                                </div>
                            </div>
                        </div>

                        {{-- Sub-tab filtr --}}
                        <div class="px-5 py-3 border-b border-gray-200 dark:border-gray-700 flex flex-wrap items-center gap-2">
                            <span class="text-xs font-semibold text-gray-500 dark:text-gray-400 mr-1">Ko'rsatish:</span>
                            <button @click="curriculumTab = 'all'"
                                    class="conflict-filter-pill"
                                    :class="curriculumTab === 'all' ? 'curriculum-filter-active' : 'conflict-filter-inactive'">
                                Barchasi
                            </button>
                            <button @click="curriculumTab = 'matched'"
                                    class="conflict-filter-pill"
                                    :class="curriculumTab === 'matched' ? 'curriculum-filter-active' : 'conflict-filter-inactive'"
                                    x-text="'Mos (' + curriculumResult.matched + ')'"></button>
                            <button @click="curriculumTab = 'hours_mismatch'"
                                    class="conflict-filter-pill"
                                    :class="curriculumTab === 'hours_mismatch' ? 'curriculum-filter-active-amber' : 'conflict-filter-inactive'"
                                    x-text="'Soat farqi (' + curriculumResult.hours_mismatch + ')'"></button>
                            <button @click="curriculumTab = 'not_in_schedule'"
                                    class="conflict-filter-pill"
                                    :class="curriculumTab === 'not_in_schedule' ? 'curriculum-filter-active-red' : 'conflict-filter-inactive'"
                                    x-text="'Jadvalda yo\'q (' + curriculumResult.not_in_schedule + ')'"></button>
                            <button @click="curriculumTab = 'not_in_curriculum'"
                                    class="conflict-filter-pill"
                                    :class="curriculumTab === 'not_in_curriculum' ? 'curriculum-filter-active-blue' : 'conflict-filter-inactive'"
                                    x-text="'Rejada yo\'q (' + curriculumResult.not_in_curriculum + ')'"></button>
                        </div>

                        {{-- Mos kelgan ro'yxat --}}
                        <div x-show="curriculumTab === 'all' || curriculumTab === 'matched'" class="divide-y divide-gray-100 dark:divide-gray-700">
                            <template x-if="curriculumResult.matched_items.length > 0 && (curriculumTab === 'all' || curriculumTab === 'matched')">
                                <div>
                                    <div x-show="curriculumTab === 'all'" class="px-5 py-2 bg-green-50 dark:bg-green-900/20 text-xs font-bold text-green-700 dark:text-green-400 uppercase tracking-wide">
                                        Mos kelgan fanlar
                                    </div>
                                    <div class="overflow-x-auto">
                                        <table class="w-full text-xs">
                                            <thead><tr class="bg-gray-50 dark:bg-gray-800">
                                                <th class="px-4 py-2 text-left font-semibold text-gray-600 dark:text-gray-400">Guruh</th>
                                                <th class="px-4 py-2 text-left font-semibold text-gray-600 dark:text-gray-400">Fan</th>
                                                <th class="px-4 py-2 text-left font-semibold text-gray-600 dark:text-gray-400">Turi</th>
                                                <th class="px-4 py-2 text-left font-semibold text-gray-600 dark:text-gray-400">O'qituvchi</th>
                                                <th class="px-4 py-2 text-center font-semibold text-gray-600 dark:text-gray-400">Reja soat</th>
                                                <th class="px-4 py-2 text-center font-semibold text-gray-600 dark:text-gray-400">Jadval soat</th>
                                                <th class="px-4 py-2 text-center font-semibold text-gray-600 dark:text-gray-400">Holat</th>
                                            </tr></thead>
                                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                                <template x-for="item in curriculumResult.matched_items" :key="item.group_name + item.subject_name + item.training_type">
                                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                                                        <td class="px-4 py-2 font-medium text-gray-800 dark:text-gray-200" x-text="item.group_name"></td>
                                                        <td class="px-4 py-2 text-gray-700 dark:text-gray-300" x-text="item.subject_name"></td>
                                                        <td class="px-4 py-2 text-gray-500 dark:text-gray-400" x-text="item.training_type || '—'"></td>
                                                        <td class="px-4 py-2 text-gray-500 dark:text-gray-400" x-text="item.employee_name || '—'"></td>
                                                        <td class="px-4 py-2 text-center" x-text="item.curriculum_hours || '—'"></td>
                                                        <td class="px-4 py-2 text-center" x-text="item.schedule_hours || '—'"></td>
                                                        <td class="px-4 py-2 text-center"><span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300">Mos</span></td>
                                                    </tr>
                                                </template>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </template>
                        </div>

                        {{-- Soat farqi --}}
                        <div x-show="curriculumTab === 'all' || curriculumTab === 'hours_mismatch'" class="divide-y divide-gray-100 dark:divide-gray-700">
                            <template x-if="curriculumResult.hours_mismatch_items.length > 0 && (curriculumTab === 'all' || curriculumTab === 'hours_mismatch')">
                                <div>
                                    <div x-show="curriculumTab === 'all'" class="px-5 py-2 bg-amber-50 dark:bg-amber-900/20 text-xs font-bold text-amber-700 dark:text-amber-400 uppercase tracking-wide">
                                        Soat farqi bor fanlar
                                    </div>
                                    <div class="overflow-x-auto">
                                        <table class="w-full text-xs">
                                            <thead><tr class="bg-gray-50 dark:bg-gray-800">
                                                <th class="px-4 py-2 text-left font-semibold text-gray-600 dark:text-gray-400">Guruh</th>
                                                <th class="px-4 py-2 text-left font-semibold text-gray-600 dark:text-gray-400">Fan</th>
                                                <th class="px-4 py-2 text-left font-semibold text-gray-600 dark:text-gray-400">Turi</th>
                                                <th class="px-4 py-2 text-left font-semibold text-gray-600 dark:text-gray-400">O'qituvchi</th>
                                                <th class="px-4 py-2 text-center font-semibold text-gray-600 dark:text-gray-400">Reja soat</th>
                                                <th class="px-4 py-2 text-center font-semibold text-gray-600 dark:text-gray-400">Jadval soat</th>
                                                <th class="px-4 py-2 text-center font-semibold text-gray-600 dark:text-gray-400">Farq</th>
                                            </tr></thead>
                                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                                <template x-for="item in curriculumResult.hours_mismatch_items" :key="item.group_name + item.subject_name + item.training_type">
                                                    <tr class="hover:bg-amber-50/50 dark:hover:bg-amber-900/10 cursor-pointer"
                                                        @click="if (item.ids?.length) { activeTab = 'jadval'; $nextTick(() => highlightCells(item.ids)); }">
                                                        <td class="px-4 py-2 font-medium text-gray-800 dark:text-gray-200" x-text="item.group_name"></td>
                                                        <td class="px-4 py-2 text-gray-700 dark:text-gray-300" x-text="item.subject_name"></td>
                                                        <td class="px-4 py-2 text-gray-500 dark:text-gray-400" x-text="item.training_type || '—'"></td>
                                                        <td class="px-4 py-2 text-gray-500 dark:text-gray-400" x-text="item.employee_name || '—'"></td>
                                                        <td class="px-4 py-2 text-center font-semibold" x-text="item.curriculum_hours"></td>
                                                        <td class="px-4 py-2 text-center font-semibold" x-text="item.schedule_hours"></td>
                                                        <td class="px-4 py-2 text-center">
                                                            <span class="px-2 py-0.5 rounded-full text-xs font-bold"
                                                                  :class="item.diff > 0 ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300' : 'bg-orange-100 text-orange-800 dark:bg-orange-900/40 dark:text-orange-300'"
                                                                  x-text="(item.diff > 0 ? '+' : '') + item.diff + ' soat'"></span>
                                                        </td>
                                                    </tr>
                                                </template>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </template>
                        </div>

                        {{-- Jadvalda yo'q (o'quv rejada bor) --}}
                        <div x-show="curriculumTab === 'all' || curriculumTab === 'not_in_schedule'" class="divide-y divide-gray-100 dark:divide-gray-700">
                            <template x-if="curriculumResult.not_in_schedule_items.length > 0 && (curriculumTab === 'all' || curriculumTab === 'not_in_schedule')">
                                <div>
                                    <div x-show="curriculumTab === 'all'" class="px-5 py-2 bg-red-50 dark:bg-red-900/20 text-xs font-bold text-red-700 dark:text-red-400 uppercase tracking-wide">
                                        Jadvalda yo'q (o'quv rejada bor)
                                    </div>
                                    <div class="overflow-x-auto">
                                        <table class="w-full text-xs">
                                            <thead><tr class="bg-gray-50 dark:bg-gray-800">
                                                <th class="px-4 py-2 text-left font-semibold text-gray-600 dark:text-gray-400">Guruh</th>
                                                <th class="px-4 py-2 text-left font-semibold text-gray-600 dark:text-gray-400">Fan</th>
                                                <th class="px-4 py-2 text-left font-semibold text-gray-600 dark:text-gray-400">Turi</th>
                                                <th class="px-4 py-2 text-left font-semibold text-gray-600 dark:text-gray-400">O'qituvchi</th>
                                                <th class="px-4 py-2 text-center font-semibold text-gray-600 dark:text-gray-400">Reja soat</th>
                                                <th class="px-4 py-2 text-center font-semibold text-gray-600 dark:text-gray-400">Holat</th>
                                            </tr></thead>
                                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                                <template x-for="item in curriculumResult.not_in_schedule_items" :key="item.group_name + item.subject_name + item.training_type">
                                                    <tr class="hover:bg-red-50/50 dark:hover:bg-red-900/10">
                                                        <td class="px-4 py-2 font-medium text-gray-800 dark:text-gray-200" x-text="item.group_name"></td>
                                                        <td class="px-4 py-2 text-gray-700 dark:text-gray-300" x-text="item.subject_name"></td>
                                                        <td class="px-4 py-2 text-gray-500 dark:text-gray-400" x-text="item.training_type || '—'"></td>
                                                        <td class="px-4 py-2 text-gray-500 dark:text-gray-400" x-text="item.employee_name || '—'"></td>
                                                        <td class="px-4 py-2 text-center" x-text="item.curriculum_hours || '—'"></td>
                                                        <td class="px-4 py-2 text-center"><span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300">Jadvalda yo'q</span></td>
                                                    </tr>
                                                </template>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </template>
                            <div x-show="curriculumTab === 'not_in_schedule' && curriculumResult.not_in_schedule_items.length === 0" class="p-8 text-center">
                                <svg class="w-12 h-12 mx-auto text-green-300 dark:text-green-700 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <p class="text-sm text-gray-500 dark:text-gray-400">O'quv rejadagi barcha fanlar jadvalda mavjud.</p>
                            </div>
                        </div>

                        {{-- Rejada yo'q (jadvalda bor) --}}
                        <div x-show="curriculumTab === 'all' || curriculumTab === 'not_in_curriculum'" class="divide-y divide-gray-100 dark:divide-gray-700">
                            <template x-if="curriculumResult.not_in_curriculum_items.length > 0 && (curriculumTab === 'all' || curriculumTab === 'not_in_curriculum')">
                                <div>
                                    <div x-show="curriculumTab === 'all'" class="px-5 py-2 bg-blue-50 dark:bg-blue-900/20 text-xs font-bold text-blue-700 dark:text-blue-400 uppercase tracking-wide">
                                        O'quv rejada yo'q (jadvalda bor)
                                    </div>
                                    <div class="overflow-x-auto">
                                        <table class="w-full text-xs">
                                            <thead><tr class="bg-gray-50 dark:bg-gray-800">
                                                <th class="px-4 py-2 text-left font-semibold text-gray-600 dark:text-gray-400">Guruh</th>
                                                <th class="px-4 py-2 text-left font-semibold text-gray-600 dark:text-gray-400">Fan</th>
                                                <th class="px-4 py-2 text-left font-semibold text-gray-600 dark:text-gray-400">Turi</th>
                                                <th class="px-4 py-2 text-left font-semibold text-gray-600 dark:text-gray-400">O'qituvchi</th>
                                                <th class="px-4 py-2 text-center font-semibold text-gray-600 dark:text-gray-400">Darslar soni</th>
                                                <th class="px-4 py-2 text-center font-semibold text-gray-600 dark:text-gray-400">Holat</th>
                                            </tr></thead>
                                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                                <template x-for="item in curriculumResult.not_in_curriculum_items" :key="item.group_name + item.subject_name + item.training_type">
                                                    <tr class="hover:bg-blue-50/50 dark:hover:bg-blue-900/10 cursor-pointer"
                                                        @click="if (item.ids?.length) { activeTab = 'jadval'; $nextTick(() => highlightCells(item.ids)); }">
                                                        <td class="px-4 py-2 font-medium text-gray-800 dark:text-gray-200" x-text="item.group_name"></td>
                                                        <td class="px-4 py-2 text-gray-700 dark:text-gray-300" x-text="item.subject_name"></td>
                                                        <td class="px-4 py-2 text-gray-500 dark:text-gray-400" x-text="item.training_type || '—'"></td>
                                                        <td class="px-4 py-2 text-gray-500 dark:text-gray-400" x-text="item.employee_name || '—'"></td>
                                                        <td class="px-4 py-2 text-center" x-text="item.schedule_count"></td>
                                                        <td class="px-4 py-2 text-center"><span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300">Rejada yo'q</span></td>
                                                    </tr>
                                                </template>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </template>
                            <div x-show="curriculumTab === 'not_in_curriculum' && curriculumResult.not_in_curriculum_items.length === 0" class="p-8 text-center">
                                <svg class="w-12 h-12 mx-auto text-green-300 dark:text-green-700 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Jadvaldagi barcha fanlar o'quv rejada mavjud.</p>
                            </div>
                        </div>

                        {{-- Hammasi mos bo'lsa --}}
                        <div x-show="curriculumResult.hours_mismatch === 0 && curriculumResult.not_in_schedule === 0 && curriculumResult.not_in_curriculum === 0 && curriculumTab === 'all'" class="p-8 text-center">
                            <svg class="w-16 h-16 mx-auto text-green-400 dark:text-green-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <p class="text-green-600 dark:text-green-400 text-sm font-semibold">Jadval o'quv rejaga to'liq mos keladi!</p>
                        </div>
                    </div>
                </template>

                {{-- O'quv reja bo'sh --}}
                <div x-show="!curriculumLoading && !curriculumResult" class="p-12 text-center">
                    <svg class="w-16 h-16 mx-auto text-gray-300 dark:text-gray-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    <p class="text-gray-500 dark:text-gray-400 text-sm">O'quv reja bilan solishtirish uchun yuqoridagi tabni bosing.</p>
                </div>
            </div>
        </div>

        {{-- ===== TAB 4: XONALAR KESIMIDA ===== --}}
        <div x-show="activeTab === 'xonalar'" x-cloak class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                {{-- Xonalar jadvali: ustunlar = xonalar, qatorlar = kunlar + juftliklar --}}
                <div x-show="allRooms.length > 0" class="overflow-x-auto">
                    <table class="w-full border-collapse" :style="'min-width:' + (160 + allRooms.length * 160) + 'px'">
                        <thead>
                            <tr>
                                <th class="asc-header" style="width:160px;min-width:160px;position:sticky;left:0;z-index:11">Kun / Juftlik</th>
                                <template x-for="room in allRooms" :key="'rh-' + room">
                                    <th class="asc-header" style="min-width:150px">
                                        <div class="flex items-center justify-center gap-1">
                                            <svg class="w-3.5 h-3.5 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5"/></svg>
                                            <span x-text="room"></span>
                                        </div>
                                    </th>
                                </template>
                            </tr>
                        </thead>
                        <tbody>
                            {{-- roomViewRows — tekis massiv: kunlar x juftliklar --}}
                            <template x-for="row in roomViewRows" :key="row.key">
                                <tr>
                                    <td class="asc-time-cell" style="width:160px;min-width:160px;position:sticky;left:0;z-index:5;background:#f8fafc">
                                        <div x-show="row.isFirstPair" class="font-bold text-sm text-blue-700 dark:text-blue-400 mb-0.5" x-text="row.dayName"></div>
                                        <div class="font-semibold text-xs" x-text="row.pair.name"></div>
                                        <div class="text-[0.65rem] text-gray-400 mt-0.5">
                                            <span x-text="row.pair.start ? row.pair.start.substring(0,5) : ''"></span><span x-show="row.pair.start && row.pair.end"> - </span><span x-text="row.pair.end ? row.pair.end.substring(0,5) : ''"></span>
                                        </div>
                                    </td>
                                    <template x-for="room in allRooms" :key="'rvc-' + row.key + '-' + room">
                                        <td class="asc-cell" :class="row.isFirstPair && 'border-t-2 border-t-blue-200 dark:border-t-blue-800'">
                                            <template x-for="card in getRoomDayCardsGrouped(room, row.dayNum, row.pair.code)" :key="'rvcc-' + card._groupKey">
                                                <div class="asc-card asc-card-not-checked" @click.stop="openEditModal(card)" style="cursor:pointer">
                                                    <div class="asc-card-subject">
                                                        <span x-text="card.subject_name"></span>
                                                        <span x-show="currentWeek === 0 && (card.week_parity || card.weeks)"
                                                              class="asc-card-week-label"
                                                              x-text="getWeekLabel(card)"></span>
                                                    </div>
                                                    <div class="asc-card-teacher" x-text="card.employee_name || ''"></div>
                                                    <div class="asc-card-group" x-text="card.group_source || card.group_name"></div>
                                                </div>
                                            </template>
                                        </td>
                                    </template>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                {{-- Bo'sh holat --}}
                <div x-show="allRooms.length === 0" class="p-12 text-center">
                    <svg class="w-16 h-16 mx-auto text-gray-300 dark:text-gray-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                    <p class="text-gray-500 dark:text-gray-400 text-sm">Xonalar topilmadi. Jadvalda auditoriya ma'lumotlarini kiriting.</p>
                </div>
            </div>
        </div>

        {{-- CONTEXT MENU --}}
        <div x-show="ctxMenu.show" x-cloak
             class="fixed z-[100] bg-white dark:bg-gray-800 rounded-lg shadow-xl border border-gray-200 dark:border-gray-600 py-1 min-w-[180px]"
             :style="'top:' + ctxMenu.y + 'px;left:' + ctxMenu.x + 'px'"
             @click.stop>
            <button @click="openEditModal(ctxMenu.card); closeContextMenu()"
                    class="w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 flex items-center gap-2">
                <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                Tahrirlash
            </button>
            <button @click="duplicateCard(ctxMenu.card); closeContextMenu()"
                    class="w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 flex items-center gap-2">
                <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                Nusxa olish
            </button>
            <div class="border-t border-gray-200 dark:border-gray-600 my-1"></div>
            <button @click="deleteCard(ctxMenu.card); closeContextMenu()"
                    class="w-full text-left px-4 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/30 flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                O'chirish
            </button>
        </div>

        {{-- EDIT / ADD MODAL (74-soat stilida) --}}
        <div x-show="modal.show" x-cloak class="ls-modal-overlay" @click.self="modal.show = false">
            <div class="ls-modal-box" @click.stop>
                {{-- Header --}}
                <div class="ls-modal-header">
                    <div>
                        <h3 style="font-size:15px;font-weight:700;color:#fff;margin:0;" x-text="modal.isEdit ? 'Darsni tahrirlash' : 'Yangi dars qo\'shish'"></h3>
                        <p style="font-size:12px;color:rgba(255,255,255,0.75);margin:3px 0 0;">
                            <span x-text="getModalDayName()"></span> &mdash; <span x-text="getModalPairName()"></span>
                        </p>
                    </div>
                    <button @click="modal.show = false" class="ls-modal-close">&times;</button>
                </div>

                {{-- Form --}}
                <div style="padding:14px 20px;">
                    <div class="ls-form-grid">
                        <div class="ls-form-group ls-form-full">
                            <label class="ls-form-label">Fan nomi <span style="color:#ef4444">*</span></label>
                            <input x-model="modal.form.subject_name" type="text" placeholder="Masalan: Matematika" class="ls-form-input">
                        </div>
                        <div class="ls-form-group">
                            <label class="ls-form-label">Guruh <span style="color:#ef4444">*</span></label>
                            <input x-model="modal.form.group_name" type="text" placeholder="21-01 AT" class="ls-form-input">
                        </div>
                        <div class="ls-form-group">
                            <label class="ls-form-label">Guruh_source</label>
                            <input x-model="modal.form.group_source" type="text" placeholder="Birlashtirilgan guruh" class="ls-form-input">
                        </div>
                        <div class="ls-form-group">
                            <label class="ls-form-label">O'qituvchi</label>
                            <input x-model="modal.form.employee_name" type="text" placeholder="F.I.O" class="ls-form-input">
                        </div>
                        <div class="ls-form-group">
                            <label class="ls-form-label">Bino</label>
                            <input x-model="modal.form.building_name" type="text" placeholder="Bosh bino" class="ls-form-input">
                        </div>
                        <div class="ls-form-group">
                            <label class="ls-form-label">Auditoriya</label>
                            <input x-model="modal.form.auditorium_name" type="text" placeholder="301" class="ls-form-input">
                        </div>
                        <div class="ls-form-group">
                            <label class="ls-form-label">Dars turi</label>
                            <select x-model="modal.form.training_type_name" class="ls-form-input">
                                <option value="">Tanlang...</option>
                                <option value="Ma'ruza">Ma'ruza</option>
                                <option value="Amaliy">Amaliy</option>
                                <option value="Seminar">Seminar</option>
                                <option value="Laboratoriya">Laboratoriya</option>
                            </select>
                        </div>
                    </div>
                </div>

                {{-- Footer --}}
                <div class="ls-modal-footer">
                    <button @click="modal.show = false" class="ls-btn-cancel">Bekor qilish</button>
                    <button @click="saveModal()" :disabled="modal.saving" class="ls-btn-save" :style="modal.isEdit ? 'background:linear-gradient(135deg,#1a3268,#2b5ea7)' : 'background:linear-gradient(135deg,#059669,#0d9488)'">
                        <svg x-show="modal.saving" style="width:14px;height:14px;animation:spin 1s linear infinite;" fill="none" viewBox="0 0 24 24"><circle style="opacity:.25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path style="opacity:.75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        <span x-text="modal.isEdit ? 'Saqlash' : 'Qo\'shish'"></span>
                    </button>
                </div>
            </div>
        </div>

        {{-- Toast notification --}}
        <div x-show="toast.show" x-cloak
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-y-2"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 translate-y-0"
             x-transition:leave-end="opacity-0 translate-y-2"
             class="fixed bottom-6 right-6 z-[100] px-4 py-3 rounded-lg shadow-lg text-sm font-medium"
             :class="toast.type === 'success' ? 'bg-green-600 text-white' : 'bg-red-600 text-white'"
             x-text="toast.message"></div>
    </div>

    {{-- CSS --}}
    <style>
        [x-cloak] { display: none !important; }

        /* ===== INTERACTIVE BUTTONS ===== */
        .btn-action {
            position: relative;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.625rem 1.25rem;
            font-size: 0.875rem;
            font-weight: 600;
            border-radius: 0.75rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            overflow: hidden;
            border: none;
            text-decoration: none;
            outline: none;
        }
        .btn-action::before {
            content: '';
            position: absolute;
            inset: 0;
            opacity: 0;
            transition: opacity 0.3s ease;
            border-radius: inherit;
        }
        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }
        .btn-action:active {
            transform: translateY(0) scale(0.97);
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
        }
        .btn-action:focus-visible {
            outline: 2px solid currentColor;
            outline-offset: 2px;
        }

        /* Yuklash - Blue gradient */
        .btn-upload {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            color: #fff;
            box-shadow: 0 2px 8px rgba(59, 130, 246, 0.35);
        }
        .btn-upload::before { background: linear-gradient(135deg, #60a5fa 0%, #3b82f6 100%); }
        .btn-upload:hover { box-shadow: 0 6px 20px rgba(59, 130, 246, 0.45); }
        .btn-upload:hover::before { opacity: 1; }

        /* Shablon - Teal/Cyan gradient */
        .btn-template {
            background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);
            color: #fff;
            box-shadow: 0 2px 8px rgba(6, 182, 212, 0.35);
        }
        .btn-template::before { background: linear-gradient(135deg, #22d3ee 0%, #06b6d4 100%); }
        .btn-template:hover { box-shadow: 0 6px 20px rgba(6, 182, 212, 0.45); }
        .btn-template:hover::before { opacity: 1; }

        /* Hemis solishtirish - Indigo/Purple gradient */
        .btn-compare {
            background: linear-gradient(135deg, #8b5cf6 0%, #6d28d9 100%);
            color: #fff;
            box-shadow: 0 2px 8px rgba(139, 92, 246, 0.35);
        }
        .btn-compare::before { background: linear-gradient(135deg, #a78bfa 0%, #8b5cf6 100%); }
        .btn-compare:hover { box-shadow: 0 6px 20px rgba(139, 92, 246, 0.45); }
        .btn-compare:hover::before { opacity: 1; }
        .btn-compare:disabled { opacity: 0.6; cursor: not-allowed; transform: none !important; box-shadow: 0 2px 8px rgba(139, 92, 246, 0.2) !important; }

        /* Excel eksport - Emerald/Green gradient */
        .btn-export {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: #fff;
            box-shadow: 0 2px 8px rgba(16, 185, 129, 0.35);
        }
        .btn-export::before { background: linear-gradient(135deg, #34d399 0%, #10b981 100%); }
        .btn-export:hover { box-shadow: 0 6px 20px rgba(16, 185, 129, 0.45); }
        .btn-export:hover::before { opacity: 1; }

        /* O'chirish - Red/Rose gradient */
        .btn-delete {
            background: linear-gradient(135deg, #f43f5e 0%, #e11d48 100%);
            color: #fff;
            box-shadow: 0 2px 8px rgba(244, 63, 94, 0.3);
        }
        .btn-delete::before { background: linear-gradient(135deg, #fb7185 0%, #f43f5e 100%); }
        .btn-delete:hover { box-shadow: 0 6px 20px rgba(244, 63, 94, 0.45); }
        .btn-delete:hover::before { opacity: 1; }

        /* Dark mode adjustments for buttons */
        .dark .btn-upload { box-shadow: 0 2px 8px rgba(59, 130, 246, 0.25); }
        .dark .btn-upload:hover { box-shadow: 0 6px 20px rgba(59, 130, 246, 0.35); }
        .dark .btn-template { box-shadow: 0 2px 8px rgba(6, 182, 212, 0.25); }
        .dark .btn-template:hover { box-shadow: 0 6px 20px rgba(6, 182, 212, 0.35); }
        .dark .btn-compare { box-shadow: 0 2px 8px rgba(139, 92, 246, 0.25); }
        .dark .btn-compare:hover { box-shadow: 0 6px 20px rgba(139, 92, 246, 0.35); }
        .dark .btn-export { box-shadow: 0 2px 8px rgba(16, 185, 129, 0.25); }
        .dark .btn-export:hover { box-shadow: 0 6px 20px rgba(16, 185, 129, 0.35); }
        .dark .btn-delete { box-shadow: 0 2px 8px rgba(244, 63, 94, 0.2); }
        .dark .btn-delete:hover { box-shadow: 0 6px 20px rgba(244, 63, 94, 0.35); }

        /* ===== TABLE STYLES ===== */
        .asc-header { background: #f0f4ff; color: #374151; font-size: 0.8rem; font-weight: 600; text-align: center; padding: 10px 8px; border: 1px solid #e2e8f0; position: sticky; top: 0; z-index: 10; }
        .dark .asc-header { background: #1e293b; color: #e2e8f0; border-color: #334155; }
        .asc-time-col { width: 100px; min-width: 100px; }
        .asc-time-cell { background: #f8fafc; text-align: center; padding: 8px; border: 1px solid #e2e8f0; vertical-align: middle; color: #475569; width: 100px; min-width: 100px; }
        .dark .asc-time-cell { background: #1e293b; border-color: #334155; color: #94a3b8; }
        .asc-cell { border: 1px solid #e2e8f0; padding: 4px; vertical-align: top; min-height: 80px; min-width: 130px; position: relative; transition: background 0.15s; }
        .dark .asc-cell { border-color: #334155; }
        .asc-cell-dragover { background: #dbeafe !important; }
        .dark .asc-cell-dragover { background: #1e3a5f !important; }
        .asc-cell-empty { display: flex; align-items: center; justify-content: center; min-height: 60px; color: #cbd5e1; cursor: pointer; border: 2px dashed transparent; border-radius: 8px; transition: all 0.15s; }
        .asc-cell-empty:hover { color: #3b82f6; border-color: #93c5fd; background: #eff6ff; }
        .dark .asc-cell-empty:hover { color: #60a5fa; border-color: #2563eb; background: #1e3a5f; }
        .asc-card { border-radius: 8px; padding: 6px 8px; margin-bottom: 3px; font-size: 0.72rem; line-height: 1.3; position: relative; border: 2px solid transparent; transition: all 0.15s ease; cursor: grab; }
        .asc-card:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.12); transform: scale(1.02); z-index: 5; cursor: pointer; }
        .asc-card:active { cursor: grabbing; }
        .asc-card-edit-icon { position: absolute; top: 4px; right: 4px; width: 18px; height: 18px; border-radius: 4px; background: rgba(59,130,246,0.15); color: #3b82f6; display: flex; align-items: center; justify-content: center; opacity: 0; transition: opacity 0.15s ease; }
        .asc-card:hover .asc-card-edit-icon { opacity: 1; }
        .asc-card.asc-card-conflict .asc-card-edit-icon { right: 22px; }
        .dark .asc-card-edit-icon { background: rgba(96,165,250,0.2); color: #60a5fa; }
        .asc-card-dragging { opacity: 0.4; transform: scale(0.95); }
        .asc-card-not-checked { background: #eff6ff; border-color: #bfdbfe; color: #1e40af; }
        .dark .asc-card-not-checked { background: #1e3a5f; border-color: #2563eb; color: #93c5fd; }
        .asc-card-match { background: #f0fdf4; border-color: #86efac; color: #166534; }
        .dark .asc-card-match { background: #14532d33; border-color: #22c55e; color: #86efac; }
        .asc-card-partial { background: #fefce8; border-color: #fde047; color: #854d0e; }
        .dark .asc-card-partial { background: #854d0e33; border-color: #eab308; color: #fde047; }
        .asc-card-mismatch { background: #fef2f2; border-color: #fca5a5; color: #991b1b; }
        .dark .asc-card-mismatch { background: #991b1b33; border-color: #ef4444; color: #fca5a5; }
        .asc-card-not-found { background: #f3f4f6; border-color: #d1d5db; color: #6b7280; }
        .dark .asc-card-not-found { background: #374151; border-color: #6b7280; color: #9ca3af; }
        .asc-card-conflict { border-color: #a855f7 !important; box-shadow: 0 0 0 1px #a855f7; }
        .dark .asc-card-conflict { border-color: #c084fc !important; box-shadow: 0 0 0 1px #c084fc; }
        .asc-card-highlight { animation: highlightPulse 1.5s ease-in-out 3; }
        @keyframes highlightPulse { 0%, 100% { box-shadow: 0 0 0 0 rgba(59,130,246,0); } 50% { box-shadow: 0 0 0 4px rgba(59,130,246,0.4); } }
        .asc-card-subject { font-weight: 600; font-size: 0.75rem; margin-bottom: 2px; }
        .asc-card-teacher { font-size: 0.68rem; opacity: 0.85; }
        .asc-card-meta { font-size: 0.65rem; opacity: 0.7; margin-top: 2px; }
        .asc-card-type { font-style: italic; }
        .asc-card-group { font-size: 0.65rem; font-weight: 500; margin-top: 2px; opacity: 0.8; }
        .asc-card-week-label { display: inline-block; font-size: 0.58rem; font-weight: 600; padding: 0px 4px; margin-left: 3px; border-radius: 4px; background: rgba(99,102,241,0.15); color: #4f46e5; vertical-align: middle; line-height: 1.4; }
        .dark .asc-card-week-label { background: rgba(129,140,248,0.2); color: #a5b4fc; }
        .asc-card-badge { position: absolute; top: 3px; right: 3px; width: 16px; height: 16px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.6rem; font-weight: 700; }
        .asc-badge-conflict { background: #a855f7; color: #fff; }
        .asc-card-tooltip { position: absolute; bottom: 100%; left: 50%; transform: translateX(-50%); background: #1e293b; color: #e2e8f0; padding: 8px 10px; border-radius: 8px; min-width: 200px; z-index: 50; box-shadow: 0 4px 16px rgba(0,0,0,0.3); pointer-events: none; margin-bottom: 6px; }
        .asc-card-tooltip::after { content: ''; position: absolute; top: 100%; left: 50%; transform: translateX(-50%); border: 5px solid transparent; border-top-color: #1e293b; }

        /* ===== MODAL (74-soat stilida) ===== */
        .ls-modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            backdrop-filter: blur(4px);
        }
        .ls-modal-box {
            background: #fff;
            border-radius: 16px;
            width: 100%;
            max-width: 480px;
            box-shadow: 0 25px 60px rgba(0,0,0,0.25);
            overflow: hidden;
        }
        .dark .ls-modal-box { background: #1e293b; }
        .ls-modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 20px;
            background: linear-gradient(135deg, #1a3268, #2b5ea7);
        }
        .ls-modal-close {
            width: 32px;
            height: 32px;
            border: none;
            background: rgba(255,255,255,0.15);
            color: #fff;
            border-radius: 8px;
            font-size: 20px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.15s;
            line-height: 1;
        }
        .ls-modal-close:hover { background: rgba(255,255,255,0.3); }
        .ls-form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        .ls-form-full { grid-column: 1 / -1; }
        .ls-form-label {
            display: block;
            font-size: 11px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            margin-bottom: 4px;
        }
        .dark .ls-form-label { color: #94a3b8; }
        .ls-form-input {
            width: 100%;
            font-size: 13px;
            padding: 7px 10px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: #f8fafc;
            color: #0f172a;
            outline: none;
            transition: border-color 0.15s, box-shadow 0.15s;
        }
        .ls-form-input:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 2px rgba(59,130,246,0.15);
            background: #fff;
        }
        .ls-form-input::placeholder { color: #94a3b8; }
        .dark .ls-form-input { background: #334155; border-color: #475569; color: #e2e8f0; }
        .dark .ls-form-input:focus { border-color: #60a5fa; background: #1e293b; box-shadow: 0 0 0 2px rgba(96,165,250,0.15); }
        .ls-modal-footer {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 8px;
            padding: 12px 20px;
            border-top: 1px solid #e2e8f0;
            background: #f8fafc;
        }
        .dark .ls-modal-footer { background: #0f172a; border-color: #334155; }
        .ls-btn-cancel {
            padding: 7px 16px;
            font-size: 13px;
            font-weight: 600;
            color: #64748b;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.15s;
        }
        .ls-btn-cancel:hover { background: #f1f5f9; border-color: #cbd5e1; }
        .dark .ls-btn-cancel { background: #334155; border-color: #475569; color: #94a3b8; }
        .dark .ls-btn-cancel:hover { background: #475569; }
        .ls-btn-save {
            padding: 7px 20px;
            font-size: 13px;
            font-weight: 700;
            color: #fff;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.15s;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .ls-btn-save:hover { opacity: 0.9; }
        .ls-btn-save:disabled { opacity: 0.5; cursor: not-allowed; }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* ===== TABS ===== */
        .tab-btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; font-size: 0.875rem; font-weight: 600; border-radius: 0.5rem; transition: all 0.15s; cursor: pointer; border: none; outline: none; }
        .tab-btn-active { background: #3b82f6; color: #fff; box-shadow: 0 2px 8px rgba(59,130,246,0.3); }
        .tab-btn-active-red { background: #ef4444; box-shadow: 0 2px 8px rgba(239,68,68,0.3); }
        .tab-btn-active-teal { background: #0d9488; box-shadow: 0 2px 8px rgba(13,148,136,0.3); }
        .tab-btn-active-emerald { background: #059669; box-shadow: 0 2px 8px rgba(5,150,105,0.3); }
        .tab-btn-inactive { background: transparent; color: #64748b; }
        .tab-btn-inactive:hover { background: #f1f5f9; color: #374151; }
        .dark .tab-btn-inactive { color: #94a3b8; }
        .dark .tab-btn-inactive:hover { background: #334155; color: #e2e8f0; }

        /* ===== ROOM PILLS ===== */
        .room-pill { padding: 6px 14px; font-size: 0.8rem; font-weight: 600; border-radius: 9999px; border: 2px solid transparent; transition: all 0.15s; cursor: pointer; outline: none; }
        .room-pill-active { background: #0d9488; color: #fff; border-color: #0d9488; box-shadow: 0 2px 8px rgba(13,148,136,0.3); }
        .room-pill-inactive { background: #f1f5f9; color: #475569; border-color: #e2e8f0; }
        .room-pill-inactive:hover { background: #e0f2fe; border-color: #7dd3fc; color: #0369a1; }
        .dark .room-pill-inactive { background: #334155; color: #94a3b8; border-color: #475569; }
        .dark .room-pill-inactive:hover { background: #1e3a5f; border-color: #38bdf8; color: #7dd3fc; }

        /* ===== CONFLICT FILTER PILLS ===== */
        .conflict-filter-pill { padding: 4px 12px; font-size: 0.75rem; font-weight: 600; border-radius: 9999px; border: 1.5px solid transparent; transition: all 0.15s; cursor: pointer; outline: none; white-space: nowrap; }
        .conflict-filter-active { background: #ef4444; color: #fff; border-color: #ef4444; box-shadow: 0 2px 6px rgba(239,68,68,0.3); }
        .conflict-filter-inactive { background: #f1f5f9; color: #64748b; border-color: #e2e8f0; }
        .conflict-filter-inactive:hover { background: #fee2e2; border-color: #fca5a5; color: #991b1b; }
        .dark .conflict-filter-inactive { background: #334155; color: #94a3b8; border-color: #475569; }
        .dark .conflict-filter-inactive:hover { background: #451a1a; border-color: #ef4444; color: #fca5a5; }

        /* Curriculum filter pills */
        .curriculum-filter-active { background: #059669; color: #fff; border-color: #059669; box-shadow: 0 2px 6px rgba(5,150,105,0.3); }
        .curriculum-filter-active-amber { background: #d97706; color: #fff; border-color: #d97706; box-shadow: 0 2px 6px rgba(217,119,6,0.3); }
        .curriculum-filter-active-red { background: #dc2626; color: #fff; border-color: #dc2626; box-shadow: 0 2px 6px rgba(220,38,38,0.3); }
        .curriculum-filter-active-blue { background: #2563eb; color: #fff; border-color: #2563eb; box-shadow: 0 2px 6px rgba(37,99,235,0.3); }

    </style>

    {{-- JavaScript --}}
    <script>
    function lectureSchedule() {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        const routePrefix = '{{ $routePrefix }}';

        return {
            activeBatchId: {{ $activeBatch->id ?? 'null' }},
            currentWeek: 0, // 0 = barchasi, 1-15 = aniq hafta
            activeTab: 'jadval', // 'jadval' | 'xatolar' | 'curriculum' | 'xonalar'
            gridItems: {},
            pairs: [],
            days: {},
            batchAllRooms: [],
            loading: false,
            comparing: false,
            hemisResult: null,
            conflicts: [],
            conflictFilter: '', // '' = barchasi, yoki 'teacher', 'hours', etc.
            curriculumResult: null,
            curriculumLoading: false,
            curriculumTab: 'all', // 'all' | 'matched' | 'hours_mismatch' | 'not_in_schedule' | 'not_in_curriculum'
            conflictDetail: { show: false, data: null },
            hoveredCard: null,
            draggedCard: null,

            // Context menu
            ctxMenu: { show: false, x: 0, y: 0, card: null },

            // Modal
            modal: {
                show: false,
                isEdit: false,
                saving: false,
                cardId: null,
                weekDay: null,
                pairCode: null,
                form: { subject_name: '', group_name: '', group_source: '', employee_name: '', auditorium_name: '', floor: '', building_name: '', training_type_name: '', weeks: '', week_parity: '' },
            },

            // Toast
            toast: { show: false, message: '', type: 'success' },

            get exportUrl() {
                return '{{ route($routePrefix . ".lecture-schedule.export", ["id" => "__ID__"]) }}'.replace('__ID__', this.activeBatchId || 0);
            },
            get exportGridUrl() {
                let url = '{{ route($routePrefix . ".lecture-schedule.export-grid", ["id" => "__ID__"]) }}'.replace('__ID__', this.activeBatchId || 0);
                if (this.currentWeek > 0) url += '?week=' + this.currentWeek;
                return url;
            },
            get exportPdfUrl() {
                let url = '{{ route($routePrefix . ".lecture-schedule.export-pdf", ["id" => "__ID__"]) }}'.replace('__ID__', this.activeBatchId || 0);
                if (this.currentWeek > 0) url += '?week=' + this.currentWeek;
                return url;
            },

            init() {
                if (this.activeBatchId) this.loadGrid();
            },

            showToast(message, type = 'success') {
                this.toast = { show: true, message, type };
                setTimeout(() => this.toast.show = false, 3000);
            },

            closeAllModals() {
                this.modal.show = false;
                this.ctxMenu.show = false;
            },

            // ===== HAFTA NAVIGATSIYA =====
            prevWeek() {
                if (this.currentWeek > 0) {
                    this.currentWeek--;
                    this.loadGrid();
                }
            },
            nextWeek() {
                if (this.currentWeek < 15) {
                    this.currentWeek++;
                    this.loadGrid();
                }
            },

            // ===== GRID LOADING =====
            async loadGrid() {
                if (!this.activeBatchId) return;
                this.loading = true;
                this.hemisResult = null;
                this.curriculumResult = null;
                try {
                    let url = '{{ route($routePrefix . ".lecture-schedule.data") }}?batch_id=' + this.activeBatchId;
                    if (this.currentWeek > 0) {
                        url += '&week=' + this.currentWeek;
                    }
                    const res = await fetch(url);
                    const data = await res.json();
                    this.gridItems = data.items || {};
                    this.pairs = data.pairs || [];
                    this.days = data.days || {};
                    if (data.all_rooms) {
                        this.batchAllRooms = data.all_rooms;
                    }
                    // DEBUG: server debug info
                    if (data._debug) {
                        console.log('=== SERVER DEBUG ===', data._debug);
                    }
                    // DEBUG: Dushanba 1-juftlik cell card'larini ko'rsatish
                    if (this.gridItems['1_1']) {
                        console.log('Cell 1_1 (Dushanba 1-juftlik):', this.gridItems['1_1'].length, 'ta card');
                        this.gridItems['1_1'].forEach(c => {
                            console.log('  ', c.id, c.subject_name, 'potok=' + c.group_source, 'guruh=' + c.group_name, 'xona=' + c.auditorium_name, 'parity=' + c.week_parity);
                        });
                    }
                    this.collectConflicts();
                } catch (e) {
                    console.error('Grid load error:', e);
                } finally {
                    this.loading = false;
                }
            },

            collectConflicts() {
                this.conflicts = [];
                let uid = 0;
                for (const key in this.gridItems) {
                    for (const card of this.gridItems[key]) {
                        if (card.has_conflict && card.conflict_details) {
                            const pair = this.pairs.find(p => String(p.code) === String(card.lesson_pair_code));
                            for (const cd of card.conflict_details) {
                                const exists = this.conflicts.find(c => c.message === cd.message);
                                if (!exists) {
                                    this.conflicts.push({
                                        _uid: ++uid,
                                        type: cd.type,
                                        message: cd.message,
                                        ids: [card.id],
                                        cards: [card],
                                        dayName: this.days[card.week_day] || card.week_day + '-kun',
                                        weekDay: card.week_day,
                                        pairName: pair ? pair.name : card.lesson_pair_code + '-juftlik',
                                        pairTime: pair ? ((pair.start || '').substring(0,5) + ' - ' + (pair.end || '').substring(0,5)) : '',
                                    });
                                } else {
                                    if (!exists.ids.includes(card.id)) {
                                        exists.ids.push(card.id);
                                        exists.cards.push(card);
                                    }
                                }
                            }
                        }
                    }
                }
                this.conflictFilter = '';
            },

            // ===== KONFLIKT FILTR VA DETAIL =====
            get filteredConflicts() {
                if (!this.conflictFilter) return this.conflicts;
                return this.conflicts.filter(c => c.type === this.conflictFilter);
            },
            get conflictTypes() {
                const map = {};
                for (const c of this.conflicts) {
                    if (!map[c.type]) {
                        map[c.type] = { type: c.type, label: this.getConflictTypeName(c.type), count: 0 };
                    }
                    map[c.type].count++;
                }
                return Object.values(map);
            },
            openConflictDetail(c) {
                this.conflictDetail = { show: true, data: c };
            },

            // ===== XONALAR KESIMIDA =====
            get allRooms() {
                return this.batchAllRooms;
            },
            get roomViewRows() {
                const rows = [];
                for (const [dayNum, dayName] of Object.entries(this.days)) {
                    this.pairs.forEach((pair, pi) => {
                        rows.push({
                            key: dayNum + '_' + pair.code,
                            dayNum,
                            dayName,
                            pair,
                            isFirstPair: pi === 0,
                        });
                    });
                }
                return rows;
            },
            getRoomDayCardsGrouped(room, dayNum, pairCode) {
                const cards = this.getCellCards(dayNum, pairCode);
                const roomCards = cards.filter(c => c.auditorium_name === room);
                // Bir xil group_source yoki subject+teacher = bitta dars, 1 marta ko'rsatish
                // "Barchasi" rejimida juft/toq alohida ko'rsatilishi kerak
                const grouped = {};
                for (const card of roomCards) {
                    let key = card.group_source || (card.subject_name + '||' + (card.employee_name || ''));
                    if (this.currentWeek === 0 && card.week_parity) {
                        key += '||' + card.week_parity;
                    }
                    if (!grouped[key]) {
                        grouped[key] = { ...card, _groupKey: key + '_' + dayNum + '_' + pairCode };
                    }
                }
                return Object.values(grouped);
            },

            // ===== HEMIS =====
            async compareHemis() {
                if (!this.activeBatchId) return;
                this.comparing = true;
                try {
                    const res = await fetch('{{ route($routePrefix . ".lecture-schedule.compare") }}?batch_id=' + this.activeBatchId);
                    this.hemisResult = await res.json();
                    await this.loadGrid();
                } catch (e) { console.error(e); } finally { this.comparing = false; }
            },

            // ===== O'QUV REJA SOLISHTIRISH =====
            async loadCurriculum() {
                if (!this.activeBatchId) return;
                this.curriculumLoading = true;
                this.curriculumTab = 'all';
                try {
                    const res = await fetch('{{ route($routePrefix . ".lecture-schedule.compare-curriculum") }}?batch_id=' + this.activeBatchId);
                    this.curriculumResult = await res.json();
                } catch (e) {
                    console.error('Curriculum compare error:', e);
                    this.showToast('O\'quv reja solishtirish xatosi', 'error');
                } finally {
                    this.curriculumLoading = false;
                }
            },

            // ===== DRAG & DROP =====
            onDragStart(e, card) {
                this.draggedCard = card;
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', card.id);
                setTimeout(() => e.target.classList.add('asc-card-dragging'), 0);
            },
            onDragEnd(e) {
                e.target.classList.remove('asc-card-dragging');
                this.draggedCard = null;
                document.querySelectorAll('.asc-cell-dragover').forEach(el => el.classList.remove('asc-cell-dragover'));
            },
            onDragOver(e) {
                if (!this.draggedCard) return;
                e.currentTarget.classList.add('asc-cell-dragover');
            },
            onDragLeave(e) {
                e.currentTarget.classList.remove('asc-cell-dragover');
            },
            async onDrop(e, dayNum, pairCode) {
                e.currentTarget.classList.remove('asc-cell-dragover');
                if (!this.draggedCard) return;
                const card = this.draggedCard;
                // O'sha joyga tashlab qo'ygan bo'lsa, hech narsa qilmaymiz
                if (String(card.week_day) === String(dayNum) && String(card.lesson_pair_code) === String(pairCode)) return;

                try {
                    const res = await fetch('{{ route($routePrefix . ".lecture-schedule.move") }}', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                        body: JSON.stringify({ id: card.id, week_day: dayNum, lesson_pair_code: pairCode }),
                    });
                    const data = await res.json();
                    if (data.success) {
                        this.showToast('Dars ko\'chirildi');
                        await this.loadGrid();
                    }
                } catch (e) {
                    this.showToast('Xatolik yuz berdi', 'error');
                    console.error(e);
                }
            },

            // ===== CONTEXT MENU =====
            openContextMenu(e, card) {
                this.ctxMenu = { show: true, x: e.clientX, y: e.clientY, card };
            },
            closeContextMenu() {
                this.ctxMenu.show = false;
            },

            // ===== MODAL =====
            openEditModal(card) {
                this.modal = {
                    show: true, isEdit: true, saving: false,
                    cardId: card.id,
                    weekDay: card.week_day,
                    pairCode: card.lesson_pair_code,
                    form: {
                        subject_name: card.subject_name || '',
                        group_name: card.group_name || '',
                        group_source: card.group_source || '',
                        employee_name: card.employee_name || '',
                        auditorium_name: card.auditorium_name || '',
                        floor: card.floor || '',
                        building_name: card.building_name || '',
                        training_type_name: card.training_type_name || '',
                        weeks: card.weeks || '',
                        week_parity: card.week_parity || '',
                    },
                };
            },
            openAddModal(dayNum, pairCode) {
                if (!this.activeBatchId) return;
                this.modal = {
                    show: true, isEdit: false, saving: false,
                    cardId: null,
                    weekDay: dayNum,
                    pairCode: pairCode,
                    form: { subject_name: '', group_name: '', group_source: '', employee_name: '', auditorium_name: '', floor: '', building_name: '', training_type_name: '', weeks: '', week_parity: '' },
                };
            },
            async saveModal() {
                if (!this.modal.form.subject_name || !this.modal.form.group_name) {
                    this.showToast('Fan nomi va guruh majburiy!', 'error');
                    return;
                }
                this.modal.saving = true;
                try {
                    let url, method, body;
                    if (this.modal.isEdit) {
                        url = '{{ route($routePrefix . ".lecture-schedule.update", ["id" => "__ID__"]) }}'.replace('__ID__', this.modal.cardId);
                        method = 'PUT';
                        body = this.modal.form;
                    } else {
                        url = '{{ route($routePrefix . ".lecture-schedule.store") }}';
                        method = 'POST';
                        body = {
                            ...this.modal.form,
                            batch_id: this.activeBatchId,
                            week_day: this.modal.weekDay,
                            lesson_pair_code: this.modal.pairCode,
                        };
                    }
                    const res = await fetch(url, {
                        method, headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                        body: JSON.stringify(body),
                    });
                    const data = await res.json();
                    if (data.success) {
                        this.showToast(this.modal.isEdit ? 'Saqlandi' : 'Qo\'shildi');
                        this.modal.show = false;
                        await this.loadGrid();
                    }
                } catch (e) {
                    this.showToast('Xatolik', 'error');
                    console.error(e);
                } finally {
                    this.modal.saving = false;
                }
            },

            // ===== DELETE =====
            async deleteCard(card) {
                if (!confirm('Bu darsni o\'chirmoqchimisiz?')) return;
                try {
                    const url = '{{ route($routePrefix . ".lecture-schedule.destroy-item", ["id" => "__ID__"]) }}'.replace('__ID__', card.id);
                    const res = await fetch(url, {
                        method: 'DELETE',
                        headers: { 'X-CSRF-TOKEN': csrfToken },
                    });
                    const data = await res.json();
                    if (data.success) {
                        this.showToast('O\'chirildi');
                        await this.loadGrid();
                    }
                } catch (e) {
                    this.showToast('Xatolik', 'error');
                }
            },

            // ===== DUPLICATE =====
            async duplicateCard(card) {
                try {
                    const res = await fetch('{{ route($routePrefix . ".lecture-schedule.store") }}', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                        body: JSON.stringify({
                            batch_id: this.activeBatchId,
                            week_day: card.week_day,
                            lesson_pair_code: card.lesson_pair_code,
                            subject_name: card.subject_name,
                            group_name: card.group_name,
                            group_source: card.group_source,
                            employee_name: card.employee_name,
                            auditorium_name: card.auditorium_name,
                            floor: card.floor,
                            building_name: card.building_name,
                            training_type_name: card.training_type_name,
                            weeks: card.weeks,
                            week_parity: card.week_parity,
                        }),
                    });
                    const data = await res.json();
                    if (data.success) {
                        this.showToast('Nusxa yaratildi');
                        await this.loadGrid();
                    }
                } catch (e) {
                    this.showToast('Xatolik', 'error');
                }
            },

            // ===== HELPERS =====
            getModalDayName() {
                if (!this.modal.weekDay || !this.days) return '';
                return this.days[this.modal.weekDay] || this.modal.weekDay + '-kun';
            },
            getModalPairName() {
                if (!this.modal.pairCode || !this.pairs) return '';
                const pair = this.pairs.find(p => String(p.code) === String(this.modal.pairCode));
                if (pair) {
                    let label = pair.name || (pair.code + '-juftlik');
                    if (pair.start) label += ' (' + pair.start.substring(0, 5) + (pair.end ? ' - ' + pair.end.substring(0, 5) : '') + ')';
                    return label;
                }
                return this.modal.pairCode + '-juftlik';
            },
            getCellCards(dayNum, pairCode) {
                return this.gridItems[dayNum + '_' + pairCode] || [];
            },
            getCardClass(card) {
                let cls = 'asc-card-' + (card.hemis_status || 'not_checked');
                if (card.has_conflict) cls += ' asc-card-conflict';
                return cls;
            },
            highlightCell(id) {
                const el = document.getElementById('card-' + id);
                if (el) {
                    el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    el.classList.add('asc-card-highlight');
                    setTimeout(() => el.classList.remove('asc-card-highlight'), 4500);
                }
            },
            highlightCells(ids) {
                if (ids?.length) ids.forEach(id => this.highlightCell(id));
            },

            // ===== CONFLICT HELPERS =====
            getConflictTypeName(type) {
                const map = {
                    'teacher': "O'qituvchi",
                    'auditorium': 'Auditoriya',
                    'group': 'Guruh',
                    'room_potok': 'Xona/Potok',
                    'hours': 'Soat farqi',
                    'duplicate': 'Dublikat',
                    'missing_teacher': "O'qituvchisiz",
                    'missing_room': 'Xonasiz',
                    'potok_inconsistent': 'Potok noizchil',
                };
                return map[type] || type;
            },
            getConflictBadgeColor(type) {
                const map = {
                    'teacher': 'bg-orange-500',
                    'auditorium': 'bg-pink-500',
                    'group': 'bg-purple-500',
                    'room_potok': 'bg-cyan-600',
                    'hours': 'bg-amber-500',
                    'duplicate': 'bg-gray-500',
                    'missing_teacher': 'bg-yellow-500',
                    'missing_room': 'bg-yellow-500',
                    'potok_inconsistent': 'bg-indigo-500',
                };
                return map[type] || 'bg-gray-500';
            },
            getConflictBadgeClass(type) {
                const map = {
                    'teacher': 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-300',
                    'auditorium': 'bg-pink-100 text-pink-700 dark:bg-pink-900/30 dark:text-pink-300',
                    'group': 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300',
                    'room_potok': 'bg-cyan-100 text-cyan-700 dark:bg-cyan-900/30 dark:text-cyan-300',
                    'hours': 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
                    'duplicate': 'bg-gray-200 text-gray-700 dark:bg-gray-600 dark:text-gray-300',
                    'missing_teacher': 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300',
                    'missing_room': 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300',
                    'potok_inconsistent': 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300',
                };
                return map[type] || 'bg-gray-200 text-gray-700';
            },
            getConflictHeaderGradient(type) {
                const map = {
                    'teacher': 'linear-gradient(135deg,#ea580c,#c2410c)',
                    'auditorium': 'linear-gradient(135deg,#db2777,#be185d)',
                    'group': 'linear-gradient(135deg,#9333ea,#7e22ce)',
                    'room_potok': 'linear-gradient(135deg,#0891b2,#0e7490)',
                    'hours': 'linear-gradient(135deg,#d97706,#b45309)',
                    'duplicate': 'linear-gradient(135deg,#6b7280,#4b5563)',
                    'missing_teacher': 'linear-gradient(135deg,#ca8a04,#a16207)',
                    'missing_room': 'linear-gradient(135deg,#ca8a04,#a16207)',
                    'potok_inconsistent': 'linear-gradient(135deg,#4f46e5,#4338ca)',
                };
                return map[type] || 'linear-gradient(135deg,#6b7280,#4b5563)';
            },

            // ===== KONFLIKT TUSHUNTIRISH =====
            getConflictExplanation(c) {
                if (!c) return '';
                const cards = c.cards || [];
                switch (c.type) {
                    case 'teacher': {
                        const teacher = cards[0]?.employee_name || 'Noma\'lum';
                        const rooms = [...new Set(cards.map(x => x.auditorium_name).filter(Boolean))];
                        const groups = [...new Set(cards.map(x => x.group_source || x.group_name))];
                        return `<p><b>${teacher}</b> o'qituvchi <b>${c.dayName}</b> kuni <b>${c.pairName}</b> juftlikda bir vaqtning o'zida <b>${groups.length}</b> ta guruhga dars o'tishi kerak bo'lib qolgan:</p>`
                            + `<p>Guruhlar: <b>${groups.join(', ')}</b></p>`
                            + `<p>Auditoriyalar: <b>${rooms.join(', ')}</b></p>`
                            + `<p>Bitta o'qituvchi bir vaqtda faqat bitta auditoriyada bo'la oladi. Shu sababli bu konflikt hisoblanadi.</p>`;
                    }
                    case 'auditorium': {
                        const room = cards[0]?.auditorium_name || '';
                        const teachers = [...new Set(cards.map(x => x.employee_name).filter(Boolean))];
                        const groups = [...new Set(cards.map(x => x.group_source || x.group_name))];
                        return `<p><b>${room}</b> auditoriyasi <b>${c.dayName}</b> kuni <b>${c.pairName}</b> juftlikda bir vaqtda <b>${teachers.length}</b> ta turli o'qituvchiga ajratilgan:</p>`
                            + `<p>O'qituvchilar: <b>${teachers.join(', ')}</b></p>`
                            + `<p>Guruhlar: <b>${groups.join(', ')}</b></p>`
                            + `<p>Bitta xonada bir vaqtda faqat bitta dars o'tishi mumkin (turli o'qituvchi = turli dars).</p>`;
                    }
                    case 'group': {
                        const group = cards[0]?.group_name || '';
                        const subjects = [...new Set(cards.map(x => x.subject_name))];
                        return `<p><b>${group}</b> guruh <b>${c.dayName}</b> kuni <b>${c.pairName}</b> juftlikda bir vaqtda <b>${subjects.length}</b> ta turli fandan dars olishi kerak:</p>`
                            + `<p>Fanlar: <b>${subjects.join(', ')}</b></p>`
                            + `<p>Talabalar bir vaqtda faqat bitta fandan dars olishi mumkin.</p>`;
                    }
                    case 'room_potok': {
                        const room = cards[0]?.auditorium_name || '';
                        const potoks = [...new Set(cards.map(x => x.group_source).filter(Boolean))];
                        const parities = {};
                        cards.forEach(x => { if (x.group_source) parities[x.group_source] = x.week_parity || 'barcha'; });
                        let detail = '';
                        potoks.forEach(p => {
                            const pCards = cards.filter(x => x.group_source === p);
                            detail += `<p>• <b>${p}</b> — ${pCards[0]?.subject_name || ''}, hafta: <b>${parities[p]}</b></p>`;
                        });
                        return `<p><b>${room}</b> auditoriyasi <b>${c.dayName}</b> kuni <b>${c.pairName}</b> juftlikda bir vaqtda <b>${potoks.length}</b> ta potokka ajratilgan:</p>`
                            + detail
                            + `<p>Bitta ma'ruza xonasida bir vaqtda faqat bitta potok bo'lishi mumkin. Bu potoklar bir xil hafta turida (juft/toq) bo'lgani uchun konflikt.</p>`;
                    }
                    case 'hours': {
                        return `<p>${c.message}</p>`
                            + `<p>O'quv rejadagi ajratilgan soatlar bilan jadvalda rejalashtirilgan soatlar bir-biriga mos kelmayapti.</p>`
                            + `<p>Darslar soni (hafta) × 2 soat = jadvaldagi umumiy soat. Bu raqam o'quv rejadagi academic_load bilan teng bo'lishi kerak.</p>`;
                    }
                    case 'duplicate': {
                        const first = cards[0];
                        return `<p>Bir xil yozuv <b>${cards.length}</b> marta takrorlangan:</p>`
                            + `<p>Guruh: <b>${first?.group_name || ''}</b>, Fan: <b>${first?.subject_name || ''}</b></p>`
                            + `<p>Bir xil kun, juftlik, guruh, fan va hafta turi (juft/toq) kombinatsiyasi jadvalda faqat bir marta bo'lishi kerak.</p>`;
                    }
                    case 'missing_teacher':
                        return `<p>Quyidagi darslar uchun o'qituvchi tayinlanmagan. Jadval to'liq bo'lishi uchun barcha darslarga o'qituvchi ko'rsatilishi kerak.</p>`;
                    case 'missing_room':
                        return `<p>Quyidagi darslar uchun auditoriya ko'rsatilmagan. Talabalar va o'qituvchilar qaysi xonaga borishi kerakligini bilishi zarur.</p>`;
                    case 'potok_inconsistent': {
                        const potok = cards[0]?.group_source || '';
                        const subjects = [...new Set(cards.map(x => x.subject_name))];
                        const rooms = [...new Set(cards.map(x => x.auditorium_name).filter(Boolean))];
                        let msg = `<p><b>${potok}</b> potok ichidagi guruhlar o'rtasida nomuvofiqlik aniqlandi:</p>`;
                        if (subjects.length > 1) msg += `<p>Turli fanlar: <b>${subjects.join(', ')}</b></p>`;
                        if (rooms.length > 1) msg += `<p>Turli auditoriyalar: <b>${rooms.join(', ')}</b></p>`;
                        msg += `<p>Bitta potok ichidagi barcha guruhlar bir xil fandan, bir xil xonada dars olishi kerak (chunki ular bitta ma'ruzada birgalikda o'tiradi).</p>`;
                        return msg;
                    }
                    default:
                        return `<p>${c.message}</p>`;
                }
            },
            getConflictSolution(c) {
                if (!c) return '';
                switch (c.type) {
                    case 'teacher':
                        return 'Guruhlardan birini boshqa juftlikka yoki boshqa o\'qituvchiga o\'tkazing.';
                    case 'auditorium':
                        return 'Guruhlardan birini boshqa auditoriyaga ko\'chiring yoki boshqa juftlikka o\'tkazing.';
                    case 'group':
                        return 'Fanlardan birini boshqa juftlikka ko\'chiring.';
                    case 'room_potok':
                        return 'Potokalardan birini boshqa auditoriyaga ko\'chiring yoki hafta turini (juft/toq) o\'zgartiring.';
                    case 'hours':
                        return 'Darslar sonini (hafta) o\'quv rejadagi soatlarga mos ravishda tuzating. Yoki o\'quv rejadagi soat noto\'g\'ri kiritilganligini tekshiring.';
                    case 'duplicate':
                        return 'Takroriy yozuvlardan birini o\'chirib tashlang (o\'ng tugma → O\'chirish).';
                    case 'missing_teacher':
                        return 'Dars kartasini bosib o\'qituvchi ismini kiriting.';
                    case 'missing_room':
                        return 'Dars kartasini bosib auditoriya nomini kiriting.';
                    case 'potok_inconsistent':
                        return 'Potok ichidagi barcha guruhlar uchun fan nomi va auditoriyani bir xil qiling.';
                    default:
                        return 'Jadvalda tegishli darslarni tekshirib, kerakli o\'zgartirishlarni kiriting.';
                }
            },

            // ===== WEEK LABEL (Barchasi rejimida) =====
            getWeekLabel(card) {
                const MAX_WEEK = 15; // Semestr maksimal hafta soni
                const parity = (card.week_parity || '').toLowerCase();
                const weeks = card.weeks;

                if (!parity && !weeks) return '';

                // "1-8" oraliq formati
                if (weeks && String(weeks).includes('-')) {
                    if (parity === 'juft') return '(J: ' + weeks + ')';
                    if (parity === 'toq') return '(T: ' + weeks + ')';
                    return '(' + weeks + ')';
                }

                // "1,3,5,7" ro'yxat formati
                if (weeks && String(weeks).includes(',')) {
                    if (parity === 'juft') return '(J: ' + weeks + ')';
                    if (parity === 'toq') return '(T: ' + weeks + ')';
                    return '(' + weeks + ')';
                }

                // Darslar soni (bitta raqam) yoki bo'sh
                let lessonCount = MAX_WEEK;
                if (weeks && !isNaN(weeks)) {
                    lessonCount = parseInt(weeks);
                }

                // Paritetga qarab maxWeek hisoblash, lekin semestrdan oshmasin
                let maxWeek;
                if (parity === 'juft') {
                    maxWeek = Math.min(lessonCount * 2, MAX_WEEK);
                } else if (parity === 'toq') {
                    maxWeek = Math.min(lessonCount * 2 - 1, MAX_WEEK);
                } else {
                    maxWeek = Math.min(lessonCount, MAX_WEEK);
                }

                // Haqiqiy hafta raqamlarini generatsiya qilish
                let weekNums = [];
                if (parity === 'juft') {
                    for (let w = 2; w <= maxWeek; w += 2) weekNums.push(w);
                } else if (parity === 'toq') {
                    for (let w = 1; w <= maxWeek; w += 2) weekNums.push(w);
                } else if (weeks) {
                    return '(1-' + maxWeek + ')';
                } else {
                    return '';
                }

                if (weekNums.length === 0) return '';

                const prefix = parity === 'juft' ? 'J' : 'T';
                // Ixcham ko'rinish
                if (weekNums.length > 4) {
                    return '(' + prefix + ': ' + weekNums[0] + ',' + weekNums[1] + '..' + weekNums[weekNums.length - 1] + ')';
                }
                return '(' + prefix + ': ' + weekNums.join(',') + ')';
            },
        };
    }
    </script>
</x-app-layout>
