<x-student-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-sm text-gray-800 leading-tight">
            {{ __("Qayta o'qish arizasi") }}
        </h2>
    </x-slot>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 px-3 pb-10"
         x-data="retakeApplicationPage({
             debts: @js($debts->map(function($d) use ($activeApplications) {
                 $key = $d->subject_id . '|' . $d->semester_id;
                 $app = $activeApplications->get($key);
                 $rg = $app?->retakeGroup;
                 return [
                     'subject_id' => $d->subject_id,
                     'subject_name' => $d->subject_name,
                     'semester_id' => $d->semester_id,
                     'semester_name' => $d->semester_name,
                     'credit' => (float) $d->credit,
                     'active_status' => optional($app)->studentDisplayStatus(),
                     'is_active' => $activeApplications->has($key),
                     'final_status' => optional($app)->final_status,
                     'retake_group' => $rg ? [
                         'name' => $rg->name,
                         'teacher_name' => $rg->teacher_name ?? ($rg->teacher?->full_name ?? null),
                         'start_date' => optional($rg->start_date)->format('Y-m-d'),
                         'end_date' => optional($rg->end_date)->format('Y-m-d'),
                     ] : null,
                 ];
             })),
             remainingSlots: {{ $remainingSlots }},
             maxPerApplication: {{ $maxSubjectsPerApplication }},
             creditPrice: {{ $creditPrice }},
             windowOpen: {{ $window && $window->isOpen() ? 'true' : 'false' }},
         })">

        {{-- Sarlavha --}}
        <div class="bg-white rounded-xl shadow-sm p-5 mb-4 border border-gray-100">
            <h1 class="text-lg font-bold text-gray-900 mb-1">{{ __("Qayta o'qish arizasi") }}</h1>
            <p class="text-xs text-gray-500">{{ __("Akkreditatsiya bahosi mavjud bo'lmagan fanlar uchun ariza yuboring") }}</p>
        </div>

        {{-- Cheklov haqida ogohlantirish --}}
        <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 mb-4 flex items-start gap-3">
            <div class="flex-shrink-0 w-8 h-8 rounded-full bg-amber-100 flex items-center justify-center">
                <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div class="text-xs text-amber-900 leading-relaxed">
                <span class="font-semibold">{{ __("Hurmatli talaba!") }}</span>
                {{ __("Siz bitta arizada eng ko'pi") }}
                <span class="font-bold">{{ $maxSubjectsPerApplication }} ta</span>
                {{ __("fanga ariza yubora olasiz. Aktiv (kutilayotgan + tasdiqlangan) arizalaringiz bilan birga jami 3 dan oshmasligi kerak. Rad etilgan arizalar bu hisobga kirmaydi va qaytadan yuborish mumkin.") }}
            </div>
        </div>

        {{-- Oyna holati --}}
        @if($window)
            <div class="bg-gradient-to-br from-blue-50 to-indigo-50 border border-blue-200 rounded-xl p-4 mb-4">
                <div class="flex items-start">
                    <div class="flex-shrink-0 w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center">
                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    </div>
                    <div class="ml-3 flex-1">
                        <p class="text-xs text-gray-500 uppercase tracking-wide">{{ __("Qabul oynasi") }}</p>
                        <p class="text-sm font-semibold text-gray-900 mt-0.5">
                            {{ $student->specialty_name ?? '—' }} ·
                            {{ $student->level_name ?? $student->level_code }} ·
                            {{ $window->semester_name }}
                        </p>
                        <p class="text-xs text-gray-700 mt-1">
                            {{ $window->start_date->format('Y-m-d') }} → {{ $window->end_date->format('Y-m-d') }}
                            @if($window->isOpen())
                                <span class="ml-2 text-green-700 font-medium">({{ $window->remaining_days }} {{ __('kun qoldi') }})</span>
                            @elseif($window->status === 'upcoming')
                                <span class="ml-2 text-yellow-700 font-medium">({{ __('Hali ochilmagan') }})</span>
                            @else
                                <span class="ml-2 text-red-700 font-medium">({{ __('Muddat tugagan') }})</span>
                            @endif
                        </p>
                    </div>
                </div>
            </div>
        @else
            <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-4 mb-4">
                <p class="text-sm text-yellow-800">
                    {{ __("Sizning yo'nalishingiz va kursingiz uchun qayta o'qish ariza qabul qilish oynasi hali ochilmagan.") }}
                </p>
            </div>
        @endif

        {{-- Xabarlar --}}
        @if(session('success'))
            <div class="bg-green-50 border border-green-200 rounded-lg p-3 mb-4 text-sm text-green-800">
                {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="bg-red-50 border border-red-200 rounded-lg p-3 mb-4 text-sm text-red-800">
                <ul class="list-disc list-inside">
                    @foreach($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- To'lov yuklash kerak bo'lgan arizalar --}}
        @foreach(($groupsAwaitingPayment ?? []) as $awaitingGroup)
            <div class="bg-amber-50 border-2 border-amber-300 rounded-xl p-4 mb-4"
                 x-data="{ paymentFile: null }">
                <div class="flex items-start gap-3">
                    <div class="flex-shrink-0 w-10 h-10 rounded-full bg-amber-200 flex items-center justify-center">
                        <svg class="w-5 h-5 text-amber-700" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <h3 class="text-sm font-semibold text-amber-900">
                            @if($awaitingGroup->payment_verification_status === 'rejected')
                                {{ __("To'lov chekingiz rad etildi — qaytadan yuklang") }}
                            @else
                                {{ __("To'lov chekingizni yuklang") }}
                            @endif
                        </h3>
                        <p class="text-xs text-amber-800 mt-1">
                            @if($awaitingGroup->payment_verification_status === 'rejected')
                                {{ __("Sabab") }}:
                                <span class="font-medium">{{ $awaitingGroup->payment_rejection_reason ?? '—' }}</span>
                            @else
                                {{ __("Dekan va registrator arizangizni tasdiqlashdi. Jarayonni davom ettirish uchun to'lov qog'ozini yuklang.") }}
                            @endif
                        </p>
                        <p class="text-[11px] text-amber-700 mt-1">
                            {{ __("Ariza") }} #{{ $awaitingGroup->id }} ·
                            {{ __("Summa") }}: <span class="font-semibold">{{ number_format($awaitingGroup->receipt_amount, 0, '.', ' ') }} UZS</span> ·
                            {{ $awaitingGroup->applications->where('dean_status','approved')->where('registrar_status','approved')->count() }} {{ __("ta fan") }}
                        </p>

                        <form method="POST"
                              action="{{ route('student.retake.upload-payment', $awaitingGroup->id) }}"
                              enctype="multipart/form-data"
                              class="mt-3 flex flex-wrap items-center gap-2">
                            @csrf
                            <input type="file"
                                   name="payment"
                                   accept=".pdf,.jpg,.jpeg,.png"
                                   required
                                   @change="paymentFile = $event.target.files[0]"
                                   class="block text-xs text-gray-700 file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-medium file:bg-amber-100 file:text-amber-800 hover:file:bg-amber-200">
                            <button type="submit"
                                    :disabled="!paymentFile"
                                    :class="paymentFile ? 'bg-amber-600 hover:bg-amber-700 text-white' : 'bg-gray-200 text-gray-400 cursor-not-allowed'"
                                    class="px-4 py-1.5 text-xs font-medium rounded-md">
                                {{ __("Yuborish") }}
                            </button>
                            <span class="text-[11px] text-amber-700">
                                PDF, JPG, PNG · max {{ $paymentMaxMb ?? 5 }} MB
                            </span>
                        </form>
                    </div>
                </div>
            </div>
        @endforeach

        {{-- To'lov tasdiqi kutilmoqda --}}
        @foreach(($groupsPaymentVerifying ?? []) as $verifyingGroup)
            <div class="bg-blue-50 border-2 border-blue-200 rounded-xl p-4 mb-4">
                <div class="flex items-start gap-3">
                    <div class="flex-shrink-0 w-10 h-10 rounded-full bg-blue-200 flex items-center justify-center">
                        <svg class="w-5 h-5 text-blue-700" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <h3 class="text-sm font-semibold text-blue-900">
                            {{ __("To'lov chekingiz tekshirilmoqda") }}
                        </h3>
                        <p class="text-xs text-blue-800 mt-1">
                            {{ __("Registrator ofisi to'lov chekingizning haqiqiyligini tekshirmoqda. Tasdiqlanganidan so'ng ariza o'quv bo'limiga jo'natiladi.") }}
                        </p>
                        <p class="text-[11px] text-blue-700 mt-1">
                            {{ __("Ariza") }} #{{ $verifyingGroup->id }} ·
                            {{ __("Yuklangan") }}: {{ $verifyingGroup->payment_uploaded_at->format('Y-m-d H:i') }}
                        </p>
                    </div>
                </div>
            </div>
        @endforeach

        {{-- Qarzdor fanlar --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mb-4">
            <div class="px-5 py-4 border-b border-gray-100">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-gray-900">{{ __("Qarzdor fanlar") }}</h2>
                    <span class="text-xs text-gray-500">{{ count($debts) }} {{ __("fan") }}</span>
                </div>
            </div>

            @if(count($debts) === 0)
                <div class="p-10 text-center">
                    <div class="w-16 h-16 rounded-full bg-green-100 flex items-center justify-center mx-auto mb-3">
                        <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    </div>
                    <p class="text-sm text-gray-700">{{ __("Sizda akademik qarzdorlik mavjud emas") }}</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100">
                        <thead class="bg-gray-50">
                        <tr>
                            <th class="w-10 px-3 py-2"></th>
                            <th class="px-3 py-2 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">{{ __("Semestr") }}</th>
                            <th class="px-3 py-2 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">{{ __("Fan") }}</th>
                            <th class="px-3 py-2 text-right text-[11px] font-medium text-gray-500 uppercase tracking-wider">{{ __("Kredit") }}</th>
                            <th class="px-3 py-2 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">{{ __("Holat") }}</th>
                        </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-100">
                        <template x-for="(d, i) in debts" :key="d.subject_id + '|' + d.semester_id">
                            <tr :class="{ 'bg-blue-50/30': isSelected(d) }">
                                <td class="px-3 py-2.5">
                                    <input type="checkbox"
                                           class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 disabled:opacity-50"
                                           :checked="isSelected(d)"
                                           :disabled="!canCheck(d)"
                                           @change="toggle(d)">
                                </td>
                                <td class="px-3 py-2.5 text-xs text-gray-700" x-text="d.semester_name"></td>
                                <td class="px-3 py-2.5">
                                    <span class="text-sm text-gray-900" x-text="d.subject_name"></span>
                                    <template x-if="d.is_active && d.final_status === 'approved' && d.retake_group">
                                        <div class="mt-1 text-[11px] text-gray-600 leading-relaxed">
                                            <div>
                                                <span class="text-gray-500">{{ __("O'qituvchi") }}:</span>
                                                <span class="font-medium text-gray-800" x-text="d.retake_group.teacher_name || '—'"></span>
                                            </div>
                                            <div x-show="d.retake_group.start_date">
                                                <span class="text-gray-500">{{ __("Sanalar") }}:</span>
                                                <span class="text-gray-800" x-text="d.retake_group.start_date + ' → ' + d.retake_group.end_date"></span>
                                            </div>
                                            <div x-show="d.retake_group.name">
                                                <span class="text-gray-500">{{ __("Guruh") }}:</span>
                                                <span class="text-gray-800" x-text="d.retake_group.name"></span>
                                            </div>
                                        </div>
                                    </template>
                                    <template x-if="d.is_active && d.final_status === 'approved' && !d.retake_group">
                                        <div class="mt-1 text-[11px] text-amber-700">
                                            {{ __("Guruh hali shakllantirilmagan — tez orada o'qituvchi va sanalar tayinlanadi") }}
                                        </div>
                                    </template>
                                </td>
                                <td class="px-3 py-2.5 text-right text-sm text-gray-700" x-text="d.credit.toFixed(1)"></td>
                                <td class="px-3 py-2.5">
                                    <template x-if="!d.is_active">
                                        <span class="text-xs text-gray-400">{{ __("Tanlash mumkin") }}</span>
                                    </template>
                                    <template x-if="d.is_active && d.final_status === 'pending'">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-yellow-100 text-yellow-800" x-text="d.active_status"></span>
                                    </template>
                                    <template x-if="d.is_active && d.final_status === 'approved'">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-green-100 text-green-800">{{ __("Tasdiqlangan") }} ✓</span>
                                    </template>
                                </td>
                            </tr>
                        </template>
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- Tanlangan fanlar va Yuborish --}}
        @if(count($debts) > 0 && $window && $window->isOpen())
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-4 sticky bottom-2">
                <div class="flex items-center justify-between flex-wrap gap-3">
                    <div class="text-sm">
                        <span class="text-gray-500">{{ __("Tanlangan") }}:</span>
                        <span class="font-semibold text-gray-900" x-text="selected.length + '/' + maxPerApplication"></span>
                        <span class="text-gray-400 mx-2">·</span>
                        <span class="text-gray-500">{{ __("Jami") }}:</span>
                        <span class="font-semibold text-gray-900" x-text="totalCredits.toFixed(1) + ' ' + '{{ __('kredit') }}'"></span>
                        <span class="text-gray-400 mx-2">·</span>
                        <span class="text-gray-500">{{ __("Summa") }}:</span>
                        <span class="font-semibold text-blue-700" x-text="formatMoney(totalAmount) + ' UZS'"></span>
                    </div>
                    <button type="button"
                            @click="openModal()"
                            :disabled="selected.length === 0"
                            :class="selected.length === 0 ? 'bg-gray-300 cursor-not-allowed' : 'bg-blue-600 hover:bg-blue-700'"
                            class="px-4 py-2 text-sm font-medium text-white rounded-lg transition-colors">
                        {{ __("Ariza yuborish") }}
                    </button>
                </div>
                <p class="text-[11px] text-gray-500 mt-2" x-show="remainingSlots < maxPerApplication">
                    {{ __("Ogohlantirish") }}:
                    <span x-text="remainingSlots"></span>
                    {{ __("ta bo'sh slot qoldi (aktiv arizalar bilan jami 3 dan oshmasligi kerak)") }}
                </p>
            </div>
        @endif

        {{-- Tarix --}}
        @if($history->count() > 0)
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h2 class="text-sm font-semibold text-gray-900">{{ __("Mening arizalarim tarixi") }}</h2>
                </div>
                <div class="divide-y divide-gray-100">
                    @foreach($history as $g)
                        <div class="p-4">
                            <div class="flex items-start justify-between mb-2">
                                <div>
                                    <p class="text-xs text-gray-500">{{ $g->created_at->format('Y-m-d H:i') }}</p>
                                    <p class="text-sm font-medium text-gray-900 mt-0.5">
                                        {{ $g->applications->count() }} {{ __("ta fan") }} ·
                                        {{ number_format($g->receipt_amount, 0, '.', ' ') }} UZS
                                    </p>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    @if($g->docx_path)
                                        <a href="{{ route('student.retake.download-docx', $g->id) }}"
                                           class="text-xs text-blue-600 hover:underline">DOCX</a>
                                    @endif
                                    @if($g->pdf_certificate_path)
                                        <a href="{{ route('student.retake.download-certificate', $g->id) }}?lang=uz"
                                           class="text-xs text-blue-600 hover:underline">{{ __("Ruxsatnoma PDF") }} (UZ)</a>
                                        <a href="{{ route('student.retake.download-certificate', $g->id) }}?lang=en"
                                           class="text-xs text-blue-600 hover:underline">{{ __("Permit PDF") }} (EN)</a>
                                    @endif
                                </div>
                            </div>
                            <div class="space-y-1">
                                @foreach($g->applications as $a)
                                    <div class="flex items-center justify-between text-xs">
                                        <span class="text-gray-700">
                                            {{ $a->semester_name }} — <span class="font-medium">{{ $a->subject_name }}</span>
                                            ({{ number_format($a->credit, 1) }} {{ __("kr") }})
                                        </span>
                                        @php
                                            $color = match($a->final_status) {
                                                'approved' => 'bg-green-100 text-green-800',
                                                'rejected' => 'bg-red-100 text-red-800',
                                                default => 'bg-yellow-100 text-yellow-800',
                                            };
                                        @endphp
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium {{ $color }}">
                                            {{ $a->studentDisplayStatus() }}
                                        </span>
                                    </div>
                                    @if($a->final_status === 'approved' && $a->retakeGroup)
                                        <div class="text-[11px] text-gray-500 ml-2">
                                            {{ __("Guruh") }}: {{ $a->retakeGroup->name }} ·
                                            {{ __("O'qituvchi") }}: {{ $a->retakeGroup->teacher_name ?? '—' }} ·
                                            {{ $a->retakeGroup->start_date?->format('Y-m-d') }} → {{ $a->retakeGroup->end_date?->format('Y-m-d') }}
                                        </div>
                                    @endif
                                    @if($a->final_status === 'rejected' && $a->rejectionReason())
                                        <div class="text-[11px] text-red-600 ml-2">
                                            {{ __("Sabab") }}: {{ $a->rejectionReason() }}
                                        </div>
                                    @endif

                                    {{-- Registrator tasdiqlagan bo'lsa: oldingi baholar va OSKE/TEST eslatmasi --}}
                                    @if($a->registrar_status === 'approved' && $a->previous_joriy_grade !== null)
                                        <div class="text-[11px] text-gray-700 ml-2 mt-1">
                                            <span class="text-gray-500">{{ __("Oldingi baholar") }}:</span>
                                            {{ __("Joriy") }} <span class="font-medium">{{ rtrim(rtrim(number_format($a->previous_joriy_grade, 2, '.', ''), '0'), '.') }}</span>,
                                            {{ __("Mustaqil") }} <span class="font-medium">{{ rtrim(rtrim(number_format($a->previous_mustaqil_grade, 2, '.', ''), '0'), '.') }}</span>
                                        </div>
                                    @endif
                                    @if($a->has_oske || $a->has_test)
                                        <div class="text-[11px] ml-2 mt-1 text-amber-800 bg-amber-50 border border-amber-200 rounded px-2 py-1">
                                            <span class="font-medium">{{ __("Eslatma") }}:</span>
                                            {{ __("Qayta o'qish davomida") }}
                                            @if($a->has_oske)<span class="font-semibold">OSKE</span>@endif
                                            @if($a->has_oske && $a->has_test) {{ __("va") }} @endif
                                            @if($a->has_test)<span class="font-semibold">TEST</span>@endif
                                            {{ __("qaytadan topshirilishi kerak.") }}
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @include('student.retake._submit_modal')
    </div>

    @push('scripts')
        <script>
            function retakeApplicationPage(initial) {
                return {
                    debts: initial.debts,
                    remainingSlots: initial.remainingSlots,
                    maxPerApplication: initial.maxPerApplication,
                    creditPrice: initial.creditPrice,
                    windowOpen: initial.windowOpen,
                    selected: [],
                    showModal: false,

                    get totalCredits() {
                        return this.selected.reduce((sum, s) => sum + s.credit, 0);
                    },

                    get totalAmount() {
                        return this.totalCredits * this.creditPrice;
                    },

                    isSelected(d) {
                        return this.selected.some(s => s.subject_id === d.subject_id && s.semester_id === d.semester_id);
                    },

                    canCheck(d) {
                        if (!this.windowOpen) return false;
                        if (d.is_active) return false;
                        if (this.isSelected(d)) return true;
                        if (this.selected.length >= this.maxPerApplication) return false;
                        if (this.selected.length >= this.remainingSlots) return false;
                        return true;
                    },

                    toggle(d) {
                        const idx = this.selected.findIndex(s =>
                            s.subject_id === d.subject_id && s.semester_id === d.semester_id);
                        if (idx >= 0) {
                            this.selected.splice(idx, 1);
                        } else {
                            if (this.selected.length >= this.maxPerApplication) {
                                alert("{{ __('Maksimal 3 ta fan tanlash mumkin') }}");
                                return;
                            }
                            this.selected.push(d);
                        }
                    },

                    formatMoney(n) {
                        return new Intl.NumberFormat('uz-UZ').format(Math.round(n));
                    },

                    openModal() {
                        if (this.selected.length === 0) return;
                        this.showModal = true;
                    },

                    closeModal() {
                        this.showModal = false;
                    },
                }
            }
        </script>
    @endpush
</x-student-app-layout>
