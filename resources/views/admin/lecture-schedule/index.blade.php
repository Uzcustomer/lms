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

                    <button @click="compareHemis()" :disabled="comparing"
                            class="btn-action btn-compare group">
                        <svg x-show="!comparing" class="w-4 h-4 transition-transform duration-300 group-hover:rotate-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                        <svg x-show="comparing" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        Hemis solishtirish
                    </button>

                    {{-- Export --}}
                    <a :href="exportUrl" class="btn-action btn-export group">
                        <svg class="w-4 h-4 transition-transform duration-300 group-hover:translate-y-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3M3 17V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z"/></svg>
                        Excel eksport
                    </a>

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

        {{-- Konfliktlar paneli --}}
        <div x-show="conflicts.length > 0" x-cloak class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8 mb-4">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-purple-200 dark:border-purple-700 p-5">
                <h3 class="text-sm font-semibold text-purple-700 dark:text-purple-300 mb-3 flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    Ichki konfliktlar (<span x-text="conflicts.length"></span>)
                </h3>
                <div class="space-y-1 max-h-48 overflow-y-auto">
                    <template x-for="(c, ci) in conflicts" :key="ci">
                        <div class="flex items-start gap-2 text-xs px-3 py-2 rounded-lg cursor-pointer hover:bg-purple-50 dark:hover:bg-purple-900/30"
                             :class="{
                                 'bg-orange-50 dark:bg-orange-900/20 text-orange-700 dark:text-orange-300': c.type === 'teacher',
                                 'bg-pink-50 dark:bg-pink-900/20 text-pink-700 dark:text-pink-300': c.type === 'auditorium',
                                 'bg-purple-50 dark:bg-purple-900/20 text-purple-700 dark:text-purple-300': c.type === 'group',
                             }"
                             @click="highlightCells(c.ids)">
                            <span class="font-medium whitespace-nowrap" x-text="c.type === 'teacher' ? 'O`qituvchi:' : c.type === 'auditorium' ? 'Auditoriya:' : 'Guruh:'"></span>
                            <span x-text="c.message"></span>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        {{-- ASC TIMETABLE GRID --}}
        <div class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8">
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
                                                    <div class="asc-card-subject" x-text="card.subject_name"></div>
                                                    <div class="asc-card-teacher" x-text="card.employee_name || ''"></div>
                                                    <div class="asc-card-meta">
                                                        <span x-show="card.building_name" x-text="card.building_name"></span>
                                                        <span x-show="card.building_name && card.floor">, </span>
                                                        <span x-show="card.floor" x-text="card.floor + '-qavat'"></span>
                                                        <span x-show="(card.building_name || card.floor) && card.auditorium_name"> | </span>
                                                        <span x-show="card.auditorium_name" x-text="card.auditorium_name + '-xona'"></span>
                                                        <span x-show="card.training_type_name" class="mx-1">|</span>
                                                        <span x-show="card.training_type_name" class="asc-card-type" x-text="card.training_type_name"></span>
                                                    </div>
                                                    <div class="asc-card-group">
                                                        <span x-text="card.group_name"></span>
                                                        <span x-show="card.group_source" class="text-gray-400 ml-1" x-text="'(' + card.group_source + ')'"></span>
                                                    </div>
                                                    <div x-show="card.weeks || card.week_parity" style="font-size:0.6rem;opacity:0.65;margin-top:1px;">
                                                        <span x-show="card.weeks" x-text="card.weeks + '-haftalar'"></span>
                                                        <span x-show="card.weeks && card.week_parity"> | </span>
                                                        <span x-show="card.week_parity" x-text="card.week_parity"></span>
                                                    </div>
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
                            <label class="ls-form-label">Xona</label>
                            <input x-model="modal.form.auditorium_name" type="text" placeholder="Xona raqami" class="ls-form-input">
                        </div>
                        <div class="ls-form-group">
                            <label class="ls-form-label">Qavat</label>
                            <input x-model="modal.form.floor" type="text" placeholder="3" class="ls-form-input">
                        </div>
                        <div class="ls-form-group">
                            <label class="ls-form-label">Bino</label>
                            <input x-model="modal.form.building_name" type="text" placeholder="Bosh bino" class="ls-form-input">
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
                        <div class="ls-form-group">
                            <label class="ls-form-label">Haftalar</label>
                            <input x-model="modal.form.weeks" type="text" placeholder="1-16" class="ls-form-input">
                        </div>
                        <div class="ls-form-group">
                            <label class="ls-form-label">Juft-toq</label>
                            <select x-model="modal.form.week_parity" class="ls-form-input">
                                <option value="">Har hafta</option>
                                <option value="juft">Juft</option>
                                <option value="toq">Toq</option>
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
    </style>

    {{-- JavaScript --}}
    <script>
    function lectureSchedule() {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        const routePrefix = '{{ $routePrefix }}';

        return {
            activeBatchId: {{ $activeBatch->id ?? 'null' }},
            gridItems: {},
            pairs: [],
            days: {},
            loading: false,
            comparing: false,
            hemisResult: null,
            conflicts: [],
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

            // ===== GRID LOADING =====
            async loadGrid() {
                if (!this.activeBatchId) return;
                this.loading = true;
                this.hemisResult = null;
                try {
                    const res = await fetch('{{ route($routePrefix . ".lecture-schedule.data") }}?batch_id=' + this.activeBatchId);
                    const data = await res.json();
                    this.gridItems = data.items || {};
                    this.pairs = data.pairs || [];
                    this.days = data.days || {};
                    this.collectConflicts();
                } catch (e) {
                    console.error('Grid load error:', e);
                } finally {
                    this.loading = false;
                }
            },

            collectConflicts() {
                this.conflicts = [];
                for (const key in this.gridItems) {
                    for (const card of this.gridItems[key]) {
                        if (card.has_conflict && card.conflict_details) {
                            for (const cd of card.conflict_details) {
                                const exists = this.conflicts.find(c => c.message === cd.message);
                                if (!exists) {
                                    this.conflicts.push({ type: cd.type, message: cd.message, ids: [card.id] });
                                } else {
                                    if (!exists.ids.includes(card.id)) exists.ids.push(card.id);
                                }
                            }
                        }
                    }
                }
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
        };
    }
    </script>
</x-app-layout>
