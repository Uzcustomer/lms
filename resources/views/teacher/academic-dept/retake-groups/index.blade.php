<x-teacher-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __("Qayta o'qish guruhlari") }}
        </h2>
    </x-slot>

    @include('partials._retake_tom_select')

    <div class="py-6 px-4 sm:px-6 lg:px-8 w-full"
         x-data="groupFormation({
             lookupUrl: '{{ route('admin.retake-groups.lookup') }}',
             storeUrl: '{{ route('admin.retake-groups.store') }}',
             rejectUrlBase: '{{ url('/admin/retake-groups/applications') }}',
             csrf: '{{ csrf_token() }}',
             minReasonLength: {{ \App\Models\RetakeSetting::rejectReasonMinLength() }},
             pageApps: @js($pendingApps->getCollection()->map(fn($a) => [
                 'id' => $a->id,
                 'subject_id' => $a->subject_id,
                 'subject_name' => $a->subject_name,
                 'semester_id' => $a->semester_id,
                 'semester_name' => $a->semester_name,
             ])->values()->all()),
         })">

        @if(session('success'))
            <div class="bg-green-50 border border-green-200 rounded-lg p-3 mb-4 text-sm text-green-800">
                {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="bg-red-50 border border-red-200 rounded-lg p-3 mb-4 text-sm text-red-800">
                <ul class="list-disc list-inside">@foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach</ul>
            </div>
        @endif

        {{-- Cascading filtrlar (talaba ma'lumotlari + Fan bo'yicha) --}}
        @include('partials._retake_filters', [
            'formAction' => route('admin.retake-groups.index'),
            'educationTypes' => $educationTypes ?? collect(),
            'subjects' => $subjects ?? collect(),
            'subjectMultiple' => true,
            'extraQueryFields' => array_filter([
                'status' => $statusFilter !== 'all' ? $statusFilter : null,
                'search' => $search ?: null,
            ]),
        ])

        {{-- Tasdiqlanishi kutilayotgan arizalar — yassi raqamlangan ro'yxat (checkbox bilan) --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 mb-6 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between flex-wrap gap-2">
                <h3 class="text-sm font-semibold text-gray-900">
                    {{ __("Guruh shakllantirish kutilmoqda") }}
                    <span class="text-xs font-normal text-gray-500">
                        ({{ __("O'quv bo'limi tasdiqlagan, guruh kutmoqda") }})
                    </span>
                </h3>
                @if($pendingApps->total() > 0)
                    <span class="text-xs text-gray-600">
                        {{ __("Jami") }}: <span class="font-bold text-blue-700">{{ $pendingApps->total() }}</span> {{ __("ta ariza") }}
                    </span>
                @endif
            </div>

            {{-- Bulk action panel (faqat kamida 1 ta tanlanganda) --}}
            <div x-show="selectedApps.length > 0" x-cloak
                 class="px-5 py-3 bg-blue-50 border-b border-blue-200 flex items-center justify-between flex-wrap gap-3">
                <div class="text-xs text-blue-900 flex items-center gap-2 flex-wrap">
                    <span class="font-bold text-base text-blue-700" x-text="selectedApps.length"></span>
                    <span>{{ __("ta ariza tanlangan") }}</span>
                    <span class="text-blue-700">·</span>
                    <template x-if="selectedSubjectNames.length === 1">
                        <span class="font-medium" x-text="selectedApps[0]?.subject_name"></span>
                    </template>
                    <template x-if="selectedSubjectNames.length > 1">
                        <span class="font-medium inline-flex items-center gap-1">
                            <span class="px-1.5 py-0.5 rounded bg-purple-100 text-purple-800 text-[10px] font-bold"
                                  x-text="'⛓ ' + selectedSubjectNames.length + ' ta fan'"></span>
                            <span class="text-[11px] text-blue-800"
                                  x-text="selectedSubjectNames.slice(0, 2).join(' / ') + (selectedSubjectNames.length > 2 ? ' …' : '')"></span>
                        </span>
                    </template>
                    <span class="text-blue-600">·</span>
                    <template x-if="selectedSemesterNames.length === 1">
                        <span x-text="selectedApps[0]?.semester_name"></span>
                    </template>
                    <template x-if="selectedSemesterNames.length > 1">
                        <span class="inline-flex items-center px-1.5 py-0.5 rounded bg-amber-100 text-amber-800 text-[10px] font-bold"
                              x-text="'⚠ ' + selectedSemesterNames.length + ' ta semestr'"></span>
                    </template>
                </div>
                <div class="flex gap-2">
                    <button type="button"
                            @click="bulkOpenFormation()"
                            class="px-3 py-1.5 text-xs font-medium bg-blue-600 text-white rounded-lg hover:bg-blue-700 inline-flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        {{ __("Guruh shakllantirish") }}
                    </button>
                    <button type="button" @click="clearSelection()"
                            class="px-3 py-1.5 text-xs font-medium bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">
                        {{ __("Tozalash") }}
                    </button>
                </div>
            </div>

            @if($pendingApps->count() === 0)
                <div class="p-10 text-center text-gray-500 text-sm">
                    {{ __("Hozircha tasdiqlanishi kutilayotgan ariza yo'q") }}
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100">
                        <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-center" style="width:40px;">
                                <input type="checkbox"
                                       :checked="allPageSelected"
                                       @change="toggleAllOnPage()"
                                       title="{{ __('Hammasini tanlash (bir xil fan + semestr)') }}"
                                       class="rounded text-blue-600 focus:ring-blue-500">
                            </th>
                            <th class="px-3 py-2 text-center text-[11px] font-medium text-gray-500 uppercase" style="width:48px;">{{ __("T/R") }}</th>
                            <th class="px-3 py-2 text-left text-[11px] font-medium text-gray-500 uppercase">{{ __("Talaba") }}</th>
                            <th class="px-3 py-2 text-left text-[11px] font-medium text-gray-500 uppercase">{{ __("Fakultet / Yo'nalish") }}</th>
                            <th class="px-3 py-2 text-left text-[11px] font-medium text-gray-500 uppercase">{{ __("Kurs / Guruh") }}</th>
                            <th class="px-3 py-2 text-left text-[11px] font-medium text-gray-500 uppercase">{{ __("Fan") }}</th>
                            <th class="px-3 py-2 text-left text-[11px] font-medium text-gray-500 uppercase">{{ __("Semestr") }}</th>
                            <th class="px-3 py-2 text-right text-[11px] font-medium text-gray-500 uppercase" style="width:80px;">{{ __("Kredit") }}</th>
                        </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-100">
                        @foreach($pendingApps as $i => $app)
                            @php
                                $student = $app->group?->student;
                                $rowMeta = json_encode([
                                    'id' => $app->id,
                                    'subject_id' => $app->subject_id,
                                    'subject_name' => $app->subject_name,
                                    'semester_id' => $app->semester_id,
                                    'semester_name' => $app->semester_name,
                                ]);
                            @endphp
                            <tr :class="isLocked({{ $rowMeta }}) ? 'opacity-40 bg-gray-50' : ''">
                                <td class="px-3 py-2.5 text-center">
                                    <input type="checkbox"
                                           :disabled="isLocked({{ $rowMeta }})"
                                           :checked="selectedApps.some(a => a.id === {{ $app->id }})"
                                           @change="toggleApp({{ $rowMeta }})"
                                           class="rounded text-blue-600 focus:ring-blue-500"
                                           title="{{ $app->subject_name }} · {{ $app->semester_name }}">
                                </td>
                                <td class="px-3 py-2.5 text-center text-sm font-bold text-blue-700">
                                    {{ ($pendingApps->currentPage() - 1) * $pendingApps->perPage() + $i + 1 }}
                                </td>
                                <td class="px-3 py-2.5 text-sm">
                                    <div class="font-medium text-gray-900">{{ $student?->full_name ?? '—' }}</div>
                                    <div class="text-[11px] text-gray-500">{{ $student?->hemis_id ?? '' }}</div>
                                </td>
                                <td class="px-3 py-2.5 text-xs text-gray-700">
                                    <div>{{ $student?->department_name ?? '—' }}</div>
                                    <div class="text-[11px] text-gray-500">{{ $student?->specialty_name ?? '' }}</div>
                                </td>
                                <td class="px-3 py-2.5 text-xs text-gray-700">
                                    <div>{{ $student?->level_name ?? '—' }}</div>
                                    <div class="text-[11px] text-gray-500">{{ $student?->group_name ?? '' }}</div>
                                </td>
                                <td class="px-3 py-2.5 text-sm font-medium text-gray-800">{{ $app->subject_name }}</td>
                                <td class="px-3 py-2.5 text-xs text-gray-600">{{ $app->semester_name }}</td>
                                <td class="px-3 py-2.5 text-sm text-gray-700 text-right">{{ rtrim(rtrim((string) $app->credit, '0'), '.') ?: '—' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="p-3 border-t border-gray-100 flex items-center justify-between flex-wrap gap-2">
                    <p class="text-[11px] text-gray-500">
                        💡 {{ __("Har qanday arizani tanlash mumkin — fan va semestr har xil bo'lishi mumkin (turli curriculum talabalari bir guruhga birlashtirilishi uchun)") }}
                    </p>
                    {{ $pendingApps->links() }}
                </div>
            @endif
        </div>

        {{-- Mavjud fan/semestr to'plamlari (statistika) --}}
        @if($aggregations->count() > 0)
            <details class="bg-white rounded-xl shadow-sm border border-gray-100 mb-6">
                <summary class="px-5 py-3 cursor-pointer text-sm font-medium text-gray-700 hover:bg-gray-50">
                    {{ __("Fan + semestr bo'yicha to'plamlar") }}
                    <span class="text-xs text-gray-500">({{ $aggregations->count() }} {{ __("ta") }})</span>
                </summary>
                <div class="divide-y divide-gray-100 border-t border-gray-100">
                    @foreach($aggregations as $agg)
                        @php $variantsCount = count($agg['subject_id_variants'] ?? []); @endphp
                        <div class="px-5 py-2.5 flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-900 flex items-center gap-2">
                                    {{ $agg['subject_name'] }}
                                    @if($variantsCount > 1)
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded bg-purple-100 text-purple-800 text-[10px] font-bold"
                                              title="{{ __('Turli curriculum talabalari birlashtirilgan') }}">
                                            ⛓ {{ $variantsCount }}
                                        </span>
                                    @endif
                                </p>
                                <p class="text-[11px] text-gray-500">{{ $agg['semester_name'] }}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-xs text-gray-700">
                                    <span class="font-bold text-blue-700">{{ $agg['count'] }}</span> {{ __("ta") }}
                                </span>
                                <button type="button"
                                        @click="openFormation({{ json_encode([
                                            'subject_id' => $agg['subject_id'],
                                            'subject_name' => $agg['subject_name'],
                                            'semester_id' => $agg['semester_id'],
                                            'semester_name' => $agg['semester_name'],
                                        ]) }})"
                                        class="px-2.5 py-1 text-xs bg-blue-600 text-white rounded hover:bg-blue-700">
                                    {{ __("Shakllantirish") }}
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </details>
        @endif

        {{-- Mavjud guruhlar --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100"
             x-data="{
                bulkSelected: [],
                get bulkDeletableIds() { return @js($deletableGroupIds ?? []); },
                get bulkAllChecked() { return this.bulkDeletableIds.length > 0 && this.bulkDeletableIds.every(id => this.bulkSelected.includes(id)); },
                bulkToggleAll(ev) {
                    if (ev.target.checked) {
                        this.bulkDeletableIds.forEach(id => { if (!this.bulkSelected.includes(id)) this.bulkSelected.push(id); });
                    } else {
                        this.bulkDeletableIds.forEach(id => {
                            const idx = this.bulkSelected.indexOf(id);
                            if (idx > -1) this.bulkSelected.splice(idx, 1);
                        });
                    }
                },
                bulkConfirmDelete(ev) {
                    if (this.bulkSelected.length === 0) { ev.preventDefault(); return; }
                    if (!confirm(this.bulkSelected.length + ' ta guruhni arxivga ko\'chirishni tasdiqlaysizmi?')) {
                        ev.preventDefault();
                    }
                }
             }">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between flex-wrap gap-2">
                <h3 class="text-sm font-semibold text-gray-900">{{ __("Mavjud guruhlar") }}</h3>
                <div class="flex items-center gap-2 flex-wrap">
                    <a href="{{ route('admin.retake-groups.trashed') }}"
                       class="px-3 py-1.5 text-xs bg-gray-100 text-gray-700 rounded hover:bg-gray-200">
                        📦 {{ __("Tarix") }}
                        @if(($trashedCount ?? 0) > 0)
                            <span class="ml-1 text-gray-500">({{ $trashedCount }})</span>
                        @endif
                    </a>
                    <form method="GET" action="{{ route('admin.retake-groups.index') }}" class="flex gap-2 items-center flex-wrap">
                        @php
                            $keptSubjects = is_array(request('subject')) ? request('subject') : (filled(request('subject')) ? [request('subject')] : []);
                        @endphp
                        @foreach($keptSubjects as $sId)
                            <input type="hidden" name="subject[]" value="{{ $sId }}">
                        @endforeach
                        @foreach(['education_type','department','specialty','level_code','semester_code','group','per_page'] as $kept)
                            @if(request($kept))
                                <input type="hidden" name="{{ $kept }}" value="{{ request($kept) }}">
                            @endif
                        @endforeach
                        <input type="text" name="search" value="{{ $search ?? '' }}"
                               placeholder="{{ __('Nom, fan yoki o\'qituvchi') }}"
                               class="px-3 py-1.5 text-xs border border-gray-300 rounded w-56">
                        <select name="status" class="px-3 py-1.5 text-xs border border-gray-300 rounded">
                            <option value="all" {{ $statusFilter === 'all' ? 'selected' : '' }}>{{ __("Barchasi") }}</option>
                            <option value="forming" {{ $statusFilter === 'forming' ? 'selected' : '' }}>{{ __("Shakllantirilmoqda") }}</option>
                            <option value="scheduled" {{ $statusFilter === 'scheduled' ? 'selected' : '' }}>{{ __("Tasdiqlangan") }}</option>
                            <option value="in_progress" {{ $statusFilter === 'in_progress' ? 'selected' : '' }}>{{ __("Borayotgan") }}</option>
                            <option value="completed" {{ $statusFilter === 'completed' ? 'selected' : '' }}>{{ __("Tugagan") }}</option>
                        </select>
                        <button type="submit" class="px-3 py-1.5 text-xs bg-blue-600 text-white rounded hover:bg-blue-700">{{ __("Filtrlash") }}</button>
                        @if(($search ?? '') !== '' || $statusFilter !== 'all')
                            <a href="{{ route('admin.retake-groups.index') }}" class="text-xs text-gray-500 hover:underline">{{ __("Tozalash") }}</a>
                        @endif
                    </form>
                </div>
            </div>

            @if(!empty($deletableGroupIds))
                <div class="px-5 py-2.5 border-b border-gray-100 bg-gray-50 flex items-center justify-between flex-wrap gap-2">
                    <label class="flex items-center gap-2 text-xs text-gray-700 cursor-pointer">
                        <input type="checkbox"
                               :checked="bulkAllChecked"
                               @change="bulkToggleAll($event)"
                               class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        <span>{{ __("Bo'sh guruhlarning barchasini tanlash") }}</span>
                        <span class="text-gray-500" x-show="bulkSelected.length > 0">
                            (<span x-text="bulkSelected.length"></span> {{ __("ta tanlangan") }})
                        </span>
                    </label>
                    <div class="flex items-center gap-2 flex-wrap">
                        <form method="POST" action="{{ route('admin.retake-groups.bulk-delete') }}" @submit="bulkConfirmDelete($event)">
                            @csrf
                            <template x-for="id in bulkSelected" :key="id">
                                <input type="hidden" name="group_ids[]" :value="id">
                            </template>
                            <button type="submit"
                                    :disabled="bulkSelected.length === 0"
                                    :class="bulkSelected.length === 0 ? 'bg-gray-200 text-gray-400 cursor-not-allowed' : 'bg-red-600 text-white hover:bg-red-700'"
                                    class="px-3 py-1.5 text-xs font-medium rounded">
                                {{ __("Tanlanganlarni arxivga") }}
                                <span x-show="bulkSelected.length > 0">(<span x-text="bulkSelected.length"></span>)</span>
                            </button>
                        </form>

                        @if(auth()->user()?->hasAnyRole(['superadmin']))
                            <form method="POST"
                                  action="{{ route('admin.retake-groups.bulk-force-delete') }}"
                                  onsubmit="return confirm('{{ __("Tanlangan guruhlarni butunlay o'chirishni tasdiqlaysizmi? Tarixda qolmaydi.") }}')">
                                @csrf
                                <template x-for="id in bulkSelected" :key="id">
                                    <input type="hidden" name="group_ids[]" :value="id">
                                </template>
                                <button type="submit"
                                        :disabled="bulkSelected.length === 0"
                                        :class="bulkSelected.length === 0 ? 'bg-gray-200 text-gray-400 cursor-not-allowed' : 'bg-rose-700 text-white hover:bg-rose-800 ring-2 ring-rose-200'"
                                        class="px-3 py-1.5 text-xs font-bold rounded">
                                    💀 {{ __("Butunlay o'chirish") }}
                                    <span x-show="bulkSelected.length > 0">(<span x-text="bulkSelected.length"></span>)</span>
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            @endif

            @if($groups->count() === 0)
                <div class="p-8 text-center text-gray-500 text-sm">{{ __("Guruh topilmadi") }}</div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100">
                        <thead class="bg-gray-50">
                        <tr>
                            <th class="w-10 px-3 py-2"></th>
                            <th class="px-3 py-2 text-left text-[11px] font-medium text-gray-500 uppercase">{{ __("Nom") }}</th>
                            <th class="px-3 py-2 text-left text-[11px] font-medium text-gray-500 uppercase">{{ __("Fan") }}</th>
                            <th class="px-3 py-2 text-left text-[11px] font-medium text-gray-500 uppercase">{{ __("O'qituvchi") }}</th>
                            <th class="px-3 py-2 text-left text-[11px] font-medium text-gray-500 uppercase">{{ __("Sanalar") }}</th>
                            <th class="px-3 py-2 text-right text-[11px] font-medium text-gray-500 uppercase">{{ __("Talabalar") }}</th>
                            <th class="px-3 py-2 text-left text-[11px] font-medium text-gray-500 uppercase">{{ __("Holat") }}</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-100">
                        @foreach($groups as $g)
                            @php $isDeletable = ($g->students_count ?? 0) === 0; @endphp
                            <tr>
                                <td class="px-3 py-2.5">
                                    @if($isDeletable)
                                        <input type="checkbox"
                                               :checked="bulkSelected.includes({{ $g->id }})"
                                               @change="if ($event.target.checked) {
                                                    if (!bulkSelected.includes({{ $g->id }})) bulkSelected.push({{ $g->id }});
                                               } else {
                                                    const idx = bulkSelected.indexOf({{ $g->id }});
                                                    if (idx > -1) bulkSelected.splice(idx, 1);
                                               }"
                                               class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-sm text-gray-900">{{ $g->name }}</td>
                                <td class="px-3 py-2.5 text-sm text-gray-700">
                                    {{ $g->subject_name }}
                                    <span class="block text-[11px] text-gray-500">{{ $g->semester_name }}</span>
                                </td>
                                <td class="px-3 py-2.5 text-sm text-gray-700">{{ $g->teacher_name ?? '—' }}</td>
                                <td class="px-3 py-2.5 text-xs text-gray-700">
                                    {{ $g->start_date->format('Y-m-d') }} → {{ $g->end_date->format('Y-m-d') }}
                                </td>
                                <td class="px-3 py-2.5 text-sm text-gray-700 text-right">{{ $g->students_count }}</td>
                                <td class="px-3 py-2.5">
                                    @php
                                        $colors = [
                                            'forming' => 'bg-gray-100 text-gray-700',
                                            'scheduled' => 'bg-blue-100 text-blue-800',
                                            'in_progress' => 'bg-green-100 text-green-800',
                                            'completed' => 'bg-purple-100 text-purple-800',
                                        ];
                                    @endphp
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium {{ $colors[$g->status] }}">
                                        {{ $g->statusLabel() }}
                                    </span>
                                </td>
                                <td class="px-3 py-2.5 text-right whitespace-nowrap">
                                    @if($g->status === 'forming')
                                        <form method="POST" action="{{ route('admin.retake-groups.publish', $g->id) }}" class="inline">
                                            @csrf
                                            <button type="submit" class="text-xs text-green-600 hover:underline mr-2">{{ __("Tasdiqlash") }}</button>
                                        </form>
                                    @endif
                                    <a href="{{ route('admin.retake-groups.edit', $g->id) }}"
                                       class="text-xs text-blue-600 hover:underline mr-2">{{ __("Tahrirlash") }}</a>
                                    @if($isDeletable)
                                        <form method="POST" action="{{ route('admin.retake-groups.destroy', $g->id) }}"
                                              onsubmit="return confirm('{{ __("Guruhni arxivga ko'chirishni tasdiqlaysizmi?") }}')"
                                              class="inline">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="text-xs text-red-600 hover:underline">{{ __("O'chirish") }}</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="p-3 border-t border-gray-100">{{ $groups->links() }}</div>
            @endif
        </div>

        {{-- Guruh shakllantirish modal --}}
        <div x-show="showFormation" x-cloak class="fixed inset-0 z-50 overflow-y-auto" @keydown.escape.window="closeFormation()">
            <div class="flex items-start justify-center min-h-screen p-4">
                <div class="fixed inset-0 bg-black bg-opacity-50" @click="closeFormation()"></div>
                <div class="relative bg-white rounded-2xl shadow-xl max-w-3xl w-full p-6 z-10 my-8">
                    <h3 class="text-base font-bold text-gray-900 mb-1">{{ __("Guruh shakllantirish") }}</h3>
                    <p class="text-xs text-gray-500 mb-4 flex items-center gap-2 flex-wrap">
                        <span x-text="formData.display_subject || formData.subject_name"></span>
                        <template x-if="(formData.multi_subject_count || 1) > 1">
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded bg-purple-100 text-purple-800 text-[10px] font-bold"
                                  :title="'{{ __("Birlashtirilgan fanlar soni") }}: ' + formData.multi_subject_count">
                                ⛓ <span x-text="formData.multi_subject_count"></span> {{ __("fan birlashtirildi") }}
                            </span>
                        </template>
                        <span>·</span>
                        <span x-text="formData.semester_name"></span>
                    </p>

                    <form method="POST" action="{{ route('admin.retake-groups.store') }}" class="space-y-4">
                        @csrf
                        <input type="hidden" name="subject_id" :value="formData.subject_id">
                        <input type="hidden" name="subject_name" :value="formData.subject_name">
                        <input type="hidden" name="semester_id" :value="formData.semester_id">
                        <input type="hidden" name="semester_name" :value="formData.semester_name">

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div class="md:col-span-2">
                                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __("Guruh nomi") }} <span class="text-red-500">*</span></label>
                                <input type="text" name="name" x-model="formData.name" required
                                       class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg">
                            </div>

                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __("O'qituvchi") }} <span class="text-red-500">*</span></label>
                                <select name="teacher_id" x-model="formData.teacher_id" required
                                        x-ref="teacherSelect"
                                        class="tom-select w-full px-3 py-2 text-sm border border-gray-300 rounded-lg">
                                    <option value="">— {{ __("Tanlang") }} —</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __("Maks. talabalar (ixtiyoriy)") }}</label>
                                <input type="number" name="max_students" min="1"
                                       class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg">
                            </div>

                            {{-- Sanalar — qabul oynasidan avtomatik olinadi, tahrirlanmaydi --}}
                            <div class="md:col-span-2">
                                <label class="block text-xs font-medium text-gray-700 mb-1">
                                    {{ __("O'qish davri") }}
                                    <span class="text-[10px] font-normal text-gray-500">({{ __("qabul oynasidan avtomatik") }})</span>
                                </label>
                                <div class="px-3 py-2 text-sm bg-gray-50 border border-gray-200 rounded-lg flex items-center gap-2"
                                     x-show="windowDates" x-cloak>
                                    <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                    <span class="font-medium text-gray-800" x-text="windowDates?.start_date"></span>
                                    <span class="text-gray-400">→</span>
                                    <span class="font-medium text-gray-800" x-text="windowDates?.end_date"></span>
                                </div>
                                <div class="px-3 py-2 text-sm bg-amber-50 border border-amber-200 rounded-lg text-amber-800"
                                     x-show="!windowDates" x-cloak>
                                    ⚠️ {{ __("Qabul oynasi sanasi topilmadi") }}
                                </div>
                                <input type="hidden" name="start_date" :value="windowDates?.start_date || ''">
                                <input type="hidden" name="end_date" :value="windowDates?.end_date || ''">
                            </div>
                        </div>

                        {{-- Baholash turi — O'quv bo'limi qo'lda tanlaydi (majburiy) --}}
                        <div class="bg-amber-50 border border-amber-200 rounded-lg p-3">
                            <label class="block text-xs font-medium text-amber-900 mb-2">
                                {{ __("Fan yopilish turi") }} <span class="text-red-500">*</span>
                            </label>
                            <div class="grid grid-cols-2 gap-2">
                                <label class="flex items-center gap-2 bg-white border border-gray-200 rounded px-2 py-1.5 cursor-pointer hover:bg-gray-50 text-xs">
                                    <input type="radio" name="assessment_type" value="oske" x-model="assessmentType" required class="text-amber-600">
                                    <span class="font-medium">OSKE</span>
                                </label>
                                <label class="flex items-center gap-2 bg-white border border-gray-200 rounded px-2 py-1.5 cursor-pointer hover:bg-gray-50 text-xs">
                                    <input type="radio" name="assessment_type" value="test" x-model="assessmentType" class="text-amber-600">
                                    <span class="font-medium">TEST</span>
                                </label>
                                <label class="flex items-center gap-2 bg-white border border-gray-200 rounded px-2 py-1.5 cursor-pointer hover:bg-gray-50 text-xs">
                                    <input type="radio" name="assessment_type" value="oske_test" x-model="assessmentType" class="text-amber-600">
                                    <span class="font-medium">OSKE + TEST</span>
                                </label>
                                <label class="flex items-center gap-2 bg-white border border-gray-200 rounded px-2 py-1.5 cursor-pointer hover:bg-gray-50 text-xs">
                                    <input type="radio" name="assessment_type" value="sinov_fan" x-model="assessmentType" class="text-amber-600">
                                    <span class="font-medium">{{ __("Sinov fan") }}</span>
                                </label>
                            </div>

                            {{-- OSKE va TEST sanalari (turiga qarab ko'rinadi) --}}
                            <div class="grid grid-cols-2 gap-3 mt-3" x-show="assessmentType === 'oske' || assessmentType === 'test' || assessmentType === 'oske_test'">
                                <div x-show="assessmentType === 'oske' || assessmentType === 'oske_test'">
                                    <label class="block text-xs font-medium text-gray-700 mb-1">
                                        {{ __("OSKE sanasi") }} <span class="text-red-500">*</span>
                                    </label>
                                    <input type="date"
                                           name="oske_date"
                                           x-model="oskeDate"
                                           :required="assessmentType === 'oske' || assessmentType === 'oske_test'"
                                           :min="assessmentType === 'oske_test' ? null : null"
                                           class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg">
                                    <p class="text-[10px] text-gray-500 mt-0.5">{{ __("Vaqtini Test markazi belgilaydi") }}</p>
                                </div>
                                <div x-show="assessmentType === 'test' || assessmentType === 'oske_test'">
                                    <label class="block text-xs font-medium text-gray-700 mb-1">
                                        {{ __("TEST sanasi") }} <span class="text-red-500">*</span>
                                    </label>
                                    <input type="date"
                                           name="test_date"
                                           x-model="testDate"
                                           :required="assessmentType === 'test' || assessmentType === 'oske_test'"
                                           :min="assessmentType === 'oske_test' ? oskeDate : null"
                                           class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg">
                                    <p class="text-[10px] text-gray-500 mt-0.5">{{ __("Vaqtini Test markazi belgilaydi") }}</p>
                                </div>
                            </div>

                            <p class="text-[11px] text-amber-800 mt-2" x-show="assessmentType === 'oske_test'" x-cloak>
                                ⚠️ {{ __("OSKE+TEST holatida: avval OSKE topshiriladi, keyin TEST. TEST sanasi OSKE sanasidan oldin bo'lishi mumkin emas.") }}
                            </p>
                        </div>

                        {{-- Talabalar checkbox ro'yxati --}}
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <label class="text-xs font-medium text-gray-700">{{ __("Talabalar") }} <span class="text-red-500">*</span></label>
                                <span class="text-xs text-gray-500">
                                    {{ __("Tanlangan") }}: <span class="font-bold text-blue-700" x-text="selectedCount"></span> / <span x-text="applications.length"></span>
                                </span>
                            </div>

                            <div class="border border-gray-200 rounded-lg max-h-72 overflow-y-auto">
                                <div class="px-3 py-2 bg-gray-50 border-b border-gray-200 flex items-center gap-2">
                                    <input type="checkbox" :checked="allSelected" @change="toggleAll($event.target.checked)" class="rounded">
                                    <span class="text-xs font-medium text-gray-700">{{ __("Barchasini tanlash") }}</span>
                                </div>
                                <template x-for="app in applications" :key="app.id">
                                    <div class="flex items-start px-3 py-2 hover:bg-gray-50 border-b border-gray-100">
                                        <label class="flex items-start flex-1 cursor-pointer">
                                            <input type="checkbox" name="application_ids[]" :value="app.id" x-model="selected" class="mt-0.5 rounded">
                                            <span class="ml-2 flex-1 text-xs">
                                                <span class="font-medium text-gray-900" x-text="app.student_name"></span>
                                                <span class="text-gray-500" x-text="' · ' + app.student_hemis_id"></span>
                                                <template x-if="(formData.multi_subject_count || 1) > 1 && app.subject_name">
                                                    <span class="ml-2 inline-block px-1.5 py-0.5 rounded bg-purple-50 text-purple-700 text-[10px] font-medium"
                                                          x-text="app.subject_name"></span>
                                                </template>
                                                <span class="block text-[11px] text-gray-500"
                                                      x-text="(app.department_name || '') + ' · ' + (app.specialty_name || '') + ' · ' + (app.level_name || '') + ' · ' + (app.group_name || '')"></span>
                                            </span>
                                            <span class="text-xs text-gray-500 mr-2" x-text="app.credit + ' kr'"></span>
                                        </label>
                                        <button type="button" @click="rejectApp(app)"
                                                class="ml-2 text-[11px] text-red-600 hover:underline whitespace-nowrap">
                                            {{ __("Rad etish") }}
                                        </button>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <div class="flex gap-2 pt-2">
                            <button type="button" @click="closeFormation()"
                                    class="flex-1 px-3 py-2 text-sm bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">{{ __("Bekor qilish") }}</button>
                            <button type="submit" name="action" value="save"
                                    :disabled="!windowDates"
                                    :class="!windowDates ? 'bg-gray-300 cursor-not-allowed' : 'bg-yellow-500 hover:bg-yellow-600'"
                                    class="flex-1 px-3 py-2 text-sm text-white rounded-lg">
                                {{ __("Saqlash (draft)") }}
                            </button>
                            <button type="submit" name="action" value="publish"
                                    :disabled="!windowDates"
                                    :class="!windowDates ? 'bg-gray-300 cursor-not-allowed' : 'bg-blue-600 hover:bg-blue-700'"
                                    class="flex-1 px-3 py-2 text-sm text-white rounded-lg">
                                {{ __("Tasdiqlash") }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            function groupFormation({ lookupUrl, storeUrl, csrf, rejectUrlBase, minReasonLength, pageApps }) {
                return {
                    lookupUrl, storeUrl, csrf, rejectUrlBase, minReasonLength,
                    showFormation: false,
                    formData: { subject_id: '', subject_name: '', semester_id: '', semester_name: '', name: '', teacher_id: '' },
                    applications: [],
                    teachers: [],
                    selected: [],
                    assessmentType: '',
                    oskeDate: '',
                    testDate: '',
                    windowDates: null, // {start_date, end_date} — qabul oynasidan

                    // Bulk tanlash uchun (yassi jadvaldagi arizalar)
                    pageApps: pageApps || [], // joriy sahifadagi barcha arizalar
                    selectedApps: [], // [{id, subject_id, subject_name, semester_id, semester_name}]

                    // Bulk-tanlashda hech qanday lock yo'q — har xil fan va har
                    // xil semestrdagi arizalarni birga belgilash mumkin (turli
                    // curriculum talabalari bir guruhga birlashishi uchun).
                    matchingPageApps() {
                        return this.pageApps.slice();
                    },

                    // Tanlangan arizalardagi unikal fan nomlari (modal preambula uchun)
                    get selectedSubjectNames() {
                        const set = new Set();
                        this.selectedApps.forEach(a => set.add(a.subject_name));
                        return Array.from(set);
                    },

                    // Tanlangan arizalardagi unikal semestrlar (preambula uchun)
                    get selectedSemesterNames() {
                        const set = new Set();
                        this.selectedApps.forEach(a => set.add(a.semester_name));
                        return Array.from(set);
                    },

                    get allPageSelected() {
                        const matching = this.matchingPageApps();
                        if (matching.length === 0) return false;
                        return matching.every(m => this.selectedApps.some(s => s.id === m.id));
                    },

                    toggleAllOnPage() {
                        const matching = this.matchingPageApps();
                        if (matching.length === 0) return;
                        const allSelected = matching.every(m => this.selectedApps.some(s => s.id === m.id));
                        this.selectedApps = allSelected ? [] : matching.slice();
                    },

                    get selectedCount() { return this.selected.length; },
                    get allSelected() { return this.applications.length > 0 && this.selected.length === this.applications.length; },

                    // ── Bulk tanlash mantiqi ─────────────────────────────────
                    // Hech qanday lock yo'q — fan/semestr har xil bo'lishi mumkin.
                    isLocked(row) {
                        return false;
                    },

                    toggleApp(row) {
                        if (this.isLocked(row)) return;
                        const idx = this.selectedApps.findIndex(x => x.id === row.id);
                        if (idx >= 0) {
                            this.selectedApps.splice(idx, 1);
                        } else {
                            this.selectedApps.push(row);
                        }
                    },

                    clearSelection() { this.selectedApps = []; },

                    bulkOpenFormation() {
                        if (this.selectedApps.length === 0) return;
                        const first = this.selectedApps[0];
                        const ids = this.selectedApps.map(a => a.id);
                        const subjects = this.selectedSubjectNames;
                        // Birlashtirilgan fan nomi (preambula uchun)
                        const combinedSubject = subjects.length > 1
                            ? subjects.slice(0, 2).join(' / ') + (subjects.length > 2 ? ' …' : '')
                            : first.subject_name;
                        this.openFormation({
                            subject_id: first.subject_id,
                            subject_name: first.subject_name, // birinchi tanlangan = "primary" subject
                            semester_id: first.semester_id,
                            semester_name: first.semester_name,
                            display_subject: combinedSubject,
                            multi_subject_count: subjects.length,
                        }, ids);
                    },
                    // ─────────────────────────────────────────────────────────

                    async openFormation(data, preselectedIds = null) {
                        this.formData = {
                            subject_id: data.subject_id,
                            subject_name: data.subject_name,
                            semester_id: data.semester_id,
                            semester_name: data.semester_name,
                            display_subject: data.display_subject || data.subject_name,
                            multi_subject_count: data.multi_subject_count || 1,
                            name: (data.display_subject || data.subject_name) + ' — qayta o\'qish',
                            teacher_id: '',
                        };
                        this.applications = [];
                        this.teachers = [];
                        this.selected = [];
                        this.assessmentType = '';
                        this.oskeDate = '';
                        this.testDate = '';
                        this.windowDates = null;
                        this.showFormation = true;

                        // Bulk (multi-subject) holatda — application_ids bo'yicha
                        // aniq arizalarni olamiz. Aks holda — subject+semester bo'yicha.
                        let url;
                        if (preselectedIds && preselectedIds.length > 0 && (data.multi_subject_count || 1) > 1) {
                            const params = new URLSearchParams();
                            preselectedIds.forEach(id => params.append('application_ids[]', id));
                            url = `${this.lookupUrl}?${params.toString()}`;
                        } else {
                            url = `${this.lookupUrl}?subject_name=${encodeURIComponent(data.subject_name)}&semester_name=${encodeURIComponent(data.semester_name)}`;
                        }
                        const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                        const json = await res.json();
                        this.applications = json.applications || [];
                        this.teachers = json.teachers || [];
                        this.windowDates = json.windowDates || null;
                        if (preselectedIds && preselectedIds.length > 0) {
                            const set = new Set(preselectedIds.map(Number));
                            this.selected = this.applications
                                .filter(a => set.has(Number(a.id)))
                                .map(a => a.id);
                        } else {
                            this.selected = this.applications.map(a => a.id);
                        }

                        this.$nextTick(() => this.syncTeacherOptions());
                    },

                    syncTeacherOptions() {
                        const sel = this.$refs.teacherSelect;
                        if (!sel) return;
                        // TomSelect endi init bo'lmagan bo'lsa, init qilamiz
                        if (!sel.tomselect && window.initTomSelects) {
                            window.initTomSelects(sel.parentElement);
                        }
                        const ts = sel.tomselect;
                        if (!ts) return;
                        ts.clear();
                        ts.clearOptions();
                        ts.addOption({ value: '', text: '— {{ __("Tanlang") }} —' });
                        this.teachers.forEach(t => {
                            ts.addOption({
                                value: String(t.id),
                                text: t.full_name + (t.department ? ' — ' + t.department : ''),
                            });
                        });
                        ts.refreshOptions(false);
                    },

                    closeFormation() { this.showFormation = false; },

                    toggleAll(checked) {
                        this.selected = checked ? this.applications.map(a => a.id) : [];
                    },

                    rejectApp(app) {
                        const promptMsg = "{{ __('Rad etish sababi (kamida') }} " + this.minReasonLength + " {{ __('belgi):') }}\n\n" + app.student_name;
                        const reason = window.prompt(promptMsg, '');
                        if (reason === null) return;
                        const trimmed = reason.trim();
                        if (trimmed.length < this.minReasonLength) {
                            alert("{{ __('Sabab juda qisqa. Kamida') }} " + this.minReasonLength + " {{ __('belgi bo\'lishi kerak.') }}");
                            return;
                        }

                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.action = `${this.rejectUrlBase}/${app.id}/reject`;

                        const csrfInput = document.createElement('input');
                        csrfInput.type = 'hidden';
                        csrfInput.name = '_token';
                        csrfInput.value = this.csrf;
                        form.appendChild(csrfInput);

                        const reasonInput = document.createElement('input');
                        reasonInput.type = 'hidden';
                        reasonInput.name = 'reason';
                        reasonInput.value = trimmed;
                        form.appendChild(reasonInput);

                        document.body.appendChild(form);
                        form.submit();
                    },
                };
            }
        </script>
    @endpush
</x-teacher-app-layout>
