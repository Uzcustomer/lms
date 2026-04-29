<x-student-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-sm text-gray-800 leading-tight">
            Qayta o'qish uchun ariza
        </h2>
    </x-slot>

    @php
        $statusBadges = [
            'eligible' => ['Tanlash mumkin', 'bg-blue-50 text-blue-700 border-blue-200'],
            'pending_dean_registrar' => ['Dekan va Registrator ofisi ko\'rib chiqmoqda', 'bg-yellow-50 text-yellow-700 border-yellow-200'],
            'pending_registrar' => ['Registrator ofisi ko\'rib chiqmoqda (dekan tasdiqlagan)', 'bg-yellow-50 text-yellow-700 border-yellow-200'],
            'pending_dean' => ['Dekan ko\'rib chiqmoqda (registrator tasdiqlagan)', 'bg-yellow-50 text-yellow-700 border-yellow-200'],
            'pending_academic_dept' => ['So\'nggi bosqich — O\'quv bo\'limida kutishda', 'bg-amber-50 text-amber-800 border-amber-200'],
            'approved' => ['Tasdiqlangan ✓', 'bg-emerald-50 text-emerald-700 border-emerald-200'],
            'rejected' => ['Rad etilgan', 'bg-red-50 text-red-700 border-red-200'],
        ];

        $debtsBySemester = $debts->groupBy('semester_name')->sortKeys();
    @endphp

    <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 px-3 py-4">

        @if(session('success'))
            <div class="mb-4 p-3 bg-emerald-50 border border-emerald-200 rounded-xl text-sm text-emerald-700 font-medium">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-xl text-sm text-red-700">
                {{ session('error') }}
            </div>
        @endif

        @if($errors->any())
            <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-xl text-sm text-red-700">
                <ul class="list-disc list-inside space-y-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- ── Qabul oynasi holati ─────────────────────────── --}}
        <div class="mb-4 bg-white rounded-2xl border border-gray-200 p-4 shadow-sm">
            <h3 class="text-sm font-semibold text-gray-700 mb-2">Qabul oynasi</h3>
            @if($activePeriod)
                <div class="flex items-center gap-3 p-3 bg-emerald-50 border border-emerald-200 rounded-xl">
                    <div class="w-10 h-10 rounded-xl bg-emerald-100 flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>
                    <div class="flex-1">
                        <div class="text-sm font-semibold text-emerald-800">
                            {{ $activePeriod->start_date->format('d.m.Y') }} → {{ $activePeriod->end_date->format('d.m.Y') }}
                        </div>
                        <div class="text-xs text-emerald-700">
                            <span class="font-bold">{{ $activePeriod->days_left }}</span> kun qoldi
                        </div>
                    </div>
                </div>
            @elseif($latestPeriod && $latestPeriod->is_upcoming)
                <div class="p-3 bg-blue-50 border border-blue-200 rounded-xl text-sm text-blue-800">
                    Qabul oynasi <span class="font-semibold">{{ $latestPeriod->start_date->format('d.m.Y') }}</span> kuni ochiladi.
                </div>
            @elseif($latestPeriod)
                <div class="p-3 bg-gray-50 border border-gray-200 rounded-xl text-sm text-gray-700">
                    Qabul muddati tugagan ({{ $latestPeriod->end_date->format('d.m.Y') }}).
                </div>
            @else
                <div class="p-3 bg-gray-50 border border-gray-200 rounded-xl text-sm text-gray-700">
                    Sizning yo'nalishingiz va kursingiz uchun qayta o'qish ariza qabul qilish oynasi hali ochilmagan.
                </div>
            @endif
        </div>

        {{-- ── Qarzdor fanlar ──────────────────────────────── --}}
        @if($debts->isEmpty())
            <div class="bg-white rounded-2xl border border-gray-200 p-8 text-center shadow-sm">
                <div class="w-16 h-16 mx-auto mb-3 rounded-full bg-emerald-100 flex items-center justify-center">
                    <svg class="w-8 h-8 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <h3 class="text-base font-semibold text-gray-800">Sizda akademik qarzdorlik mavjud emas</h3>
                <p class="text-sm text-gray-500 mt-1">Hamma fanlardan akkreditatsiya bahosi mavjud.</p>
            </div>
        @else
            <div x-data="retakeForm({{ $maxSubjects }})" x-cloak>
                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                    <div class="px-4 py-3 bg-gray-50 border-b border-gray-200">
                        <h3 class="text-sm font-semibold text-gray-700">Akademik qarzdorliklar</h3>
                        <p class="text-xs text-gray-500 mt-0.5">Eng ko'pi {{ $maxSubjects }} ta fan tanlay olasiz</p>
                    </div>

                    @foreach($debtsBySemester as $semesterName => $semDebts)
                        <div class="px-4 pt-3 pb-1 bg-gray-50 text-xs font-semibold text-gray-600 uppercase tracking-wider">
                            {{ $semesterName ?: '—' }}
                        </div>
                        @foreach($semDebts as $debt)
                            @php
                                [$badgeText, $badgeClass] = $statusBadges[$debt['application_status']] ?? $statusBadges['eligible'];
                                $isEligible = $debt['is_eligible_for_new'];
                                $isApproved = $debt['application_status'] === 'approved';
                            @endphp
                            <label class="flex items-start gap-3 px-4 py-3 border-b border-gray-100 hover:bg-blue-50/30 cursor-pointer last:border-b-0"
                                   :class="{ 'opacity-60 cursor-not-allowed': !{{ $isEligible ? 'true' : 'false' }} || (selected.length >= max && !isSelected({{ $debt['subject_id'] }}, {{ $debt['semester_id'] }})) }">
                                <input type="checkbox"
                                       value="{{ $debt['subject_id'] }}"
                                       data-subject-id="{{ $debt['subject_id'] }}"
                                       data-semester-id="{{ $debt['semester_id'] }}"
                                       data-credit="{{ $debt['credit'] }}"
                                       data-name="{{ $debt['subject_name'] }}"
                                       data-semester-name="{{ $debt['semester_name'] }}"
                                       @if(!$isEligible) disabled @endif
                                       @change="toggleSubject($event)"
                                       :disabled="!{{ $isEligible ? 'true' : 'false' }} || (selected.length >= max && !isSelected({{ $debt['subject_id'] }}, {{ $debt['semester_id'] }}))"
                                       class="mt-1 rounded border-gray-300 text-blue-600 focus:ring-blue-500 disabled:opacity-50" />
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="text-sm font-medium text-gray-800 leading-snug">
                                            {{ $debt['subject_name'] }}
                                        </div>
                                        <div class="text-xs text-gray-500 flex-shrink-0 font-medium">
                                            {{ number_format($debt['credit'], 1) }} kr
                                        </div>
                                    </div>
                                    <div class="mt-1 inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-medium border {{ $badgeClass }}">
                                        {{ $badgeText }}
                                    </div>
                                    @if($isApproved && $debt['active_application']?->retakeGroup)
                                        @php $g = $debt['active_application']->retakeGroup; @endphp
                                        <div class="mt-1 text-[11px] text-emerald-700">
                                            {{ $g->start_date->format('d.m.Y') }} → {{ $g->end_date->format('d.m.Y') }} —
                                            {{ $g->teacher?->full_name ?? 'O\'qituvchi' }}
                                        </div>
                                    @elseif($debt['application_status'] === 'rejected' && $debt['active_application'])
                                        <div class="mt-1 text-[11px] text-red-700">
                                            Sabab:
                                            {{ $debt['active_application']->dean_rejection_reason
                                                ?? $debt['active_application']->registrar_rejection_reason
                                                ?? $debt['active_application']->academic_dept_rejection_reason }}
                                        </div>
                                    @endif
                                </div>
                            </label>
                        @endforeach
                    @endforeach
                </div>

                {{-- ── Real-time hisoblagich ─────────── --}}
                <div class="mt-3 sticky bottom-3 bg-white rounded-2xl border border-gray-200 shadow-lg p-3 flex items-center justify-between gap-3">
                    <div class="text-sm">
                        <div class="font-semibold text-gray-800">
                            Tanlangan: <span x-text="selected.length"></span>/<span x-text="max"></span> fan
                        </div>
                        <div class="text-xs text-gray-500">
                            jami <span x-text="totalCredits.toFixed(1)" class="font-semibold"></span> kredit
                        </div>
                    </div>
                    <button type="button"
                            @click="openModal = true"
                            :disabled="selected.length === 0 || !{{ $activePeriod ? 'true' : 'false' }}"
                            class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-gray-300 disabled:cursor-not-allowed text-white text-sm font-semibold rounded-xl transition">
                        Ariza yuborish
                    </button>
                </div>

                {{-- ── Ariza yuborish modali ─────────── --}}
                <div x-show="openModal" x-cloak
                     class="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-black/50 px-3 pb-3 sm:pb-0"
                     @keydown.escape.window="openModal = false">
                    <div class="bg-white w-full max-w-lg rounded-2xl shadow-xl max-h-[90vh] overflow-y-auto"
                         @click.outside="openModal = false">
                        <form method="POST" action="{{ route('student.retake.store') }}" enctype="multipart/form-data">
                            @csrf

                            {{-- Hidden inputs for selected subjects --}}
                            <template x-for="(item, idx) in selected" :key="idx">
                                <div>
                                    <input type="hidden" :name="'subjects[' + idx + '][subject_id]'" :value="item.subject_id" />
                                    <input type="hidden" :name="'subjects[' + idx + '][semester_id]'" :value="item.semester_id" />
                                </div>
                            </template>

                            <div class="p-4 border-b border-gray-100">
                                <h3 class="text-base font-semibold text-gray-800">Ariza yuborish</h3>
                            </div>

                            <div class="p-4 space-y-4">
                                <div>
                                    <div class="text-xs font-semibold text-gray-600 uppercase tracking-wider mb-2">Tanlangan fanlar</div>
                                    <ul class="space-y-1">
                                        <template x-for="item in selected" :key="item.subject_id + '-' + item.semester_id">
                                            <li class="text-sm text-gray-700">
                                                <span x-text="item.semester_name"></span> —
                                                <span class="font-medium" x-text="item.name"></span>
                                                (<span x-text="item.credit.toFixed(1)"></span> kr)
                                            </li>
                                        </template>
                                    </ul>
                                    <div class="mt-2 pt-2 border-t border-gray-100 text-sm font-semibold text-gray-800">
                                        Jami: <span x-text="totalCredits.toFixed(1)"></span> kredit
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Kvitansiya <span class="text-red-500">*</span>
                                    </label>
                                    <input type="file" name="receipt" required
                                           accept="application/pdf,image/jpeg,image/png"
                                           class="w-full text-sm rounded-lg border border-gray-300 p-2 file:mr-3 file:py-1 file:px-3 file:rounded-md file:border-0 file:bg-blue-50 file:text-blue-700 file:text-xs file:font-medium" />
                                    <p class="mt-1 text-xs text-gray-500">PDF, JPG yoki PNG. Maksimal 5 MB.</p>
                                </div>

                                <div x-data="{ note: '' }">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Izoh (ixtiyoriy)</label>
                                    <textarea name="student_note" x-model="note" maxlength="500" rows="3"
                                              class="w-full text-sm rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500"
                                              placeholder="Qo'shimcha ma'lumot..."></textarea>
                                    <div class="mt-1 text-right text-xs text-gray-400">
                                        <span x-text="note.length"></span>/500
                                    </div>
                                </div>
                            </div>

                            <div class="p-4 border-t border-gray-100 flex justify-end gap-2">
                                <button type="button" @click="openModal = false"
                                        class="px-4 py-2 bg-white border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50">
                                    Bekor qilish
                                </button>
                                <button type="submit"
                                        class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg">
                                    Yuborish
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endif

        {{-- ── Mavjud arizalar ──────────────────────────────── --}}
        @if($applications->isNotEmpty())
            <div class="mt-6 bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="px-4 py-3 bg-gray-50 border-b border-gray-200">
                    <h3 class="text-sm font-semibold text-gray-700">Mening arizalarim</h3>
                </div>
                <div class="divide-y divide-gray-100">
                    @foreach($applications as $app)
                        <div class="px-4 py-3">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0 flex-1">
                                    <div class="text-sm font-medium text-gray-800">{{ $app->subject_name }}</div>
                                    <div class="text-xs text-gray-500 mt-0.5">
                                        {{ $app->semester_name }} — {{ number_format((float) $app->credit, 1) }} kr —
                                        {{ $app->submitted_at?->format('d.m.Y H:i') }}
                                    </div>
                                    <div class="mt-1 text-xs text-gray-700">
                                        {{ $app->stage_description }}
                                    </div>
                                </div>
                                @if($app->final_status === 'approved')
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-[11px] font-medium bg-emerald-100 text-emerald-800 flex-shrink-0">✓</span>
                                @elseif($app->final_status === 'rejected')
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-[11px] font-medium bg-red-100 text-red-800 flex-shrink-0">✕</span>
                                @else
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-[11px] font-medium bg-yellow-100 text-yellow-800 flex-shrink-0">…</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    @push('styles')
        <style>[x-cloak] { display: none !important; }</style>
    @endpush

    <script>
        function retakeForm(maxSubjects) {
            return {
                max: maxSubjects,
                selected: [],
                openModal: false,
                get totalCredits() {
                    return this.selected.reduce((sum, s) => sum + s.credit, 0);
                },
                isSelected(subjectId, semesterId) {
                    return this.selected.some(s => s.subject_id === subjectId && s.semester_id === semesterId);
                },
                toggleSubject(event) {
                    const cb = event.target;
                    const item = {
                        subject_id: parseInt(cb.dataset.subjectId, 10),
                        semester_id: parseInt(cb.dataset.semesterId, 10),
                        credit: parseFloat(cb.dataset.credit) || 0,
                        name: cb.dataset.name,
                        semester_name: cb.dataset.semesterName,
                    };
                    if (cb.checked) {
                        if (this.selected.length >= this.max) {
                            cb.checked = false;
                            alert('Maksimal ' + this.max + ' ta fan tanlash mumkin');
                            return;
                        }
                        if (!this.isSelected(item.subject_id, item.semester_id)) {
                            this.selected.push(item);
                        }
                    } else {
                        this.selected = this.selected.filter(s => !(s.subject_id === item.subject_id && s.semester_id === item.semester_id));
                    }
                },
            };
        }
    </script>
</x-student-app-layout>
