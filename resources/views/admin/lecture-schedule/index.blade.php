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

    <div class="py-4" x-data="lectureSchedule()" x-init="init()">

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

        {{-- Yuqori panel: Upload + Batch tanlash --}}
        <div class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8 mb-4">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
                <div class="flex flex-wrap items-end gap-4">
                    {{-- Excel yuklash --}}
                    <form action="{{ route($routePrefix . '.lecture-schedule.import') }}" method="POST" enctype="multipart/form-data" class="flex flex-wrap items-end gap-3 flex-1">
                        @csrf
                        <div class="min-w-[200px]">
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Excel fayl</label>
                            <input type="file" name="file" accept=".xlsx,.xls,.csv" required
                                   class="block w-full text-sm text-gray-600 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 dark:text-gray-300 dark:file:bg-blue-900 dark:file:text-blue-200">
                        </div>
                        <div>
                            <button type="submit" class="px-5 py-2.5 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                                Yuklash
                            </button>
                        </div>
                    </form>

                    {{-- Namuna shablon --}}
                    <a href="{{ route($routePrefix . '.lecture-schedule.template') }}"
                       class="px-4 py-2.5 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 text-sm font-medium rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        Shablon
                    </a>

                    {{-- Batch tanlash --}}
                    @if(isset($batches) && $batches->count() > 0)
                    <div class="min-w-[220px]">
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Yuklangan jadvallar</label>
                        <select x-model="activeBatchId" @change="loadGrid()"
                                class="w-full text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                            @foreach($batches as $b)
                            <option value="{{ $b->id }}" {{ isset($activeBatch) && $activeBatch->id === $b->id ? 'selected' : '' }}>
                                {{ $b->file_name }} ({{ $b->created_at->format('d.m.Y H:i') }})
                            </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Hemis bilan solishtirish --}}
                    <button @click="compareHemis()" :disabled="comparing"
                            class="px-4 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50 transition flex items-center gap-2">
                        <svg x-show="!comparing" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                        <svg x-show="comparing" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        Hemis bilan solishtirish
                    </button>

                    {{-- O'chirish --}}
                    <form method="POST" action="{{ route($routePrefix . '.lecture-schedule.destroy', ['id' => $activeBatch->id ?? 0]) }}" onsubmit="return confirm('Rostdan o\'chirmoqchimisiz?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="px-4 py-2.5 bg-red-50 text-red-600 text-sm font-medium rounded-lg hover:bg-red-100 dark:bg-red-900/30 dark:text-red-400 dark:hover:bg-red-900/50 transition flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
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

                {{-- Nomuvofiqliklar ro'yxati --}}
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
                {{-- Loading --}}
                <div x-show="loading" class="p-12 text-center">
                    <svg class="w-8 h-8 animate-spin mx-auto text-blue-500" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Yuklanmoqda...</p>
                </div>

                {{-- Bo'sh holat --}}
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
                                    {{-- Vaqt ustuni --}}
                                    <td class="asc-time-cell">
                                        <div class="font-semibold text-sm" x-text="pair.name"></div>
                                        <div class="text-xs text-gray-400 mt-0.5">
                                            <span x-text="pair.start ? pair.start.substring(0,5) : ''"></span>
                                            <span x-show="pair.start && pair.end"> - </span>
                                            <span x-text="pair.end ? pair.end.substring(0,5) : ''"></span>
                                        </div>
                                    </td>
                                    {{-- Har bir kun uchun yacheyka --}}
                                    <template x-for="(dayName, dayNum) in days" :key="dayNum">
                                        <td class="asc-cell" :id="'cell-' + dayNum + '-' + pair.code">
                                            <template x-for="card in getCellCards(dayNum, pair.code)" :key="card.id">
                                                <div class="asc-card"
                                                     :id="'card-' + card.id"
                                                     :class="getCardClass(card)"
                                                     @mouseenter="hoveredCard = card.id"
                                                     @mouseleave="hoveredCard = null">
                                                    {{-- Fan nomi --}}
                                                    <div class="asc-card-subject" x-text="card.subject_name"></div>
                                                    {{-- O'qituvchi --}}
                                                    <div class="asc-card-teacher" x-text="card.employee_name || ''"></div>
                                                    {{-- Auditoriya + Turi --}}
                                                    <div class="asc-card-meta">
                                                        <span x-show="card.auditorium_name" x-text="card.auditorium_name"></span>
                                                        <span x-show="card.auditorium_name && card.training_type_name" class="mx-1">|</span>
                                                        <span x-show="card.training_type_name" class="asc-card-type" x-text="card.training_type_name"></span>
                                                    </div>
                                                    {{-- Guruh --}}
                                                    <div class="asc-card-group" x-text="card.group_name"></div>

                                                    {{-- Konflikt ikonkasi --}}
                                                    <div x-show="card.has_conflict" class="asc-card-badge asc-badge-conflict" title="Ichki konflikt">!</div>

                                                    {{-- Hemis diff tooltip --}}
                                                    <template x-if="card.hemis_diff && card.hemis_diff.length > 0">
                                                        <div class="asc-card-tooltip" x-show="hoveredCard === card.id">
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
                                        </td>
                                    </template>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                {{-- Legend --}}
                <div x-show="!loading && Object.keys(gridItems).length > 0" class="px-5 py-3 border-t border-gray-200 dark:border-gray-700 flex flex-wrap gap-4 text-xs">
                    <div class="flex items-center gap-1.5">
                        <span class="w-4 h-4 rounded bg-green-100 dark:bg-green-900/40 border border-green-300 dark:border-green-700"></span>
                        <span class="text-gray-600 dark:text-gray-400">Hemis bilan mos</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span class="w-4 h-4 rounded bg-yellow-100 dark:bg-yellow-900/40 border border-yellow-300 dark:border-yellow-700"></span>
                        <span class="text-gray-600 dark:text-gray-400">Qisman mos (auditoriya farq)</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span class="w-4 h-4 rounded bg-red-100 dark:bg-red-900/40 border border-red-300 dark:border-red-700"></span>
                        <span class="text-gray-600 dark:text-gray-400">Mos kelmaydi</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span class="w-4 h-4 rounded bg-gray-100 dark:bg-gray-600/50 border border-gray-300 dark:border-gray-500"></span>
                        <span class="text-gray-600 dark:text-gray-400">Hemis da topilmadi</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span class="w-4 h-4 rounded bg-white dark:bg-gray-800 border-2 border-purple-400 dark:border-purple-500"></span>
                        <span class="text-gray-600 dark:text-gray-400">Ichki konflikt</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span class="w-4 h-4 rounded bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-700"></span>
                        <span class="text-gray-600 dark:text-gray-400">Tekshirilmagan</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- CSS --}}
    <style>
        [x-cloak] { display: none !important; }

        .asc-header {
            background: #f0f4ff;
            color: #374151;
            font-size: 0.8rem;
            font-weight: 600;
            text-align: center;
            padding: 10px 8px;
            border: 1px solid #e2e8f0;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .dark .asc-header {
            background: #1e293b;
            color: #e2e8f0;
            border-color: #334155;
        }

        .asc-time-col { width: 100px; min-width: 100px; }

        .asc-time-cell {
            background: #f8fafc;
            text-align: center;
            padding: 8px;
            border: 1px solid #e2e8f0;
            vertical-align: middle;
            color: #475569;
            width: 100px;
            min-width: 100px;
        }
        .dark .asc-time-cell {
            background: #1e293b;
            border-color: #334155;
            color: #94a3b8;
        }

        .asc-cell {
            border: 1px solid #e2e8f0;
            padding: 4px;
            vertical-align: top;
            min-height: 80px;
            min-width: 130px;
            position: relative;
        }
        .dark .asc-cell { border-color: #334155; }

        .asc-card {
            border-radius: 8px;
            padding: 6px 8px;
            margin-bottom: 3px;
            font-size: 0.72rem;
            line-height: 1.3;
            position: relative;
            border: 2px solid transparent;
            transition: all 0.15s ease;
        }
        .asc-card:hover {
            box-shadow: 0 2px 8px rgba(0,0,0,0.12);
            transform: scale(1.02);
            z-index: 5;
        }

        /* Hemis status ranglar */
        .asc-card-not-checked {
            background: #eff6ff;
            border-color: #bfdbfe;
            color: #1e40af;
        }
        .dark .asc-card-not-checked { background: #1e3a5f; border-color: #2563eb; color: #93c5fd; }

        .asc-card-match {
            background: #f0fdf4;
            border-color: #86efac;
            color: #166534;
        }
        .dark .asc-card-match { background: #14532d33; border-color: #22c55e; color: #86efac; }

        .asc-card-partial {
            background: #fefce8;
            border-color: #fde047;
            color: #854d0e;
        }
        .dark .asc-card-partial { background: #854d0e33; border-color: #eab308; color: #fde047; }

        .asc-card-mismatch {
            background: #fef2f2;
            border-color: #fca5a5;
            color: #991b1b;
        }
        .dark .asc-card-mismatch { background: #991b1b33; border-color: #ef4444; color: #fca5a5; }

        .asc-card-not-found {
            background: #f3f4f6;
            border-color: #d1d5db;
            color: #6b7280;
        }
        .dark .asc-card-not-found { background: #374151; border-color: #6b7280; color: #9ca3af; }

        .asc-card-conflict { border-color: #a855f7 !important; box-shadow: 0 0 0 1px #a855f7; }
        .dark .asc-card-conflict { border-color: #c084fc !important; box-shadow: 0 0 0 1px #c084fc; }

        .asc-card-highlight { animation: highlightPulse 1.5s ease-in-out 3; }
        @keyframes highlightPulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(59,130,246,0); }
            50% { box-shadow: 0 0 0 4px rgba(59,130,246,0.4); }
        }

        .asc-card-subject { font-weight: 600; font-size: 0.75rem; margin-bottom: 2px; }
        .asc-card-teacher { font-size: 0.68rem; opacity: 0.85; }
        .asc-card-meta { font-size: 0.65rem; opacity: 0.7; margin-top: 2px; }
        .asc-card-type { font-style: italic; }
        .asc-card-group { font-size: 0.65rem; font-weight: 500; margin-top: 2px; opacity: 0.8; }

        .asc-card-badge {
            position: absolute;
            top: 3px;
            right: 3px;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.6rem;
            font-weight: 700;
        }
        .asc-badge-conflict {
            background: #a855f7;
            color: #fff;
        }

        .asc-card-tooltip {
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            background: #1e293b;
            color: #e2e8f0;
            padding: 8px 10px;
            border-radius: 8px;
            min-width: 200px;
            z-index: 50;
            box-shadow: 0 4px 16px rgba(0,0,0,0.3);
            pointer-events: none;
            margin-bottom: 6px;
        }
        .asc-card-tooltip::after {
            content: '';
            position: absolute;
            top: 100%;
            left: 50%;
            transform: translateX(-50%);
            border: 5px solid transparent;
            border-top-color: #1e293b;
        }
    </style>

    {{-- JavaScript --}}
    <script>
    function lectureSchedule() {
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
            routePrefix: '{{ $routePrefix }}',

            init() {
                if (this.activeBatchId) {
                    this.loadGrid();
                }
            },

            async loadGrid() {
                if (!this.activeBatchId) return;
                this.loading = true;
                this.hemisResult = null;

                try {
                    const url = '{{ route($routePrefix . ".lecture-schedule.data") }}' + '?batch_id=' + this.activeBatchId;
                    const res = await fetch(url);
                    const data = await res.json();
                    this.gridItems = data.items || {};
                    this.pairs = data.pairs || [];
                    this.days = data.days || {};

                    // Konfliktlarni yig'ish
                    this.conflicts = [];
                    for (const key in this.gridItems) {
                        for (const card of this.gridItems[key]) {
                            if (card.has_conflict && card.conflict_details) {
                                for (const cd of card.conflict_details) {
                                    const exists = this.conflicts.find(c => c.message === cd.message);
                                    if (!exists) {
                                        this.conflicts.push({
                                            type: cd.type,
                                            message: cd.message,
                                            ids: [card.id],
                                        });
                                    } else {
                                        if (!exists.ids.includes(card.id)) exists.ids.push(card.id);
                                    }
                                }
                            }
                        }
                    }
                } catch (e) {
                    console.error('Grid load error:', e);
                } finally {
                    this.loading = false;
                }
            },

            async compareHemis() {
                if (!this.activeBatchId) return;
                this.comparing = true;

                try {
                    const url = '{{ route($routePrefix . ".lecture-schedule.compare") }}' + '?batch_id=' + this.activeBatchId;
                    const res = await fetch(url);
                    this.hemisResult = await res.json();
                    // Gridni qayta yuklash (hemis_status yangilangan)
                    await this.loadGrid();
                } catch (e) {
                    console.error('Compare error:', e);
                } finally {
                    this.comparing = false;
                }
            },

            getCellCards(dayNum, pairCode) {
                const key = dayNum + '_' + pairCode;
                return this.gridItems[key] || [];
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
                if (!ids || !ids.length) return;
                ids.forEach(id => this.highlightCell(id));
            },
        };
    }
    </script>
</x-app-layout>
