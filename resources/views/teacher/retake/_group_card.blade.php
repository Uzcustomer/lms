@php
    $student = $group->student;
    $myStatusField = $role === 'dean' ? 'dean_status' : 'registrar_status';
    $otherStatusField = $role === 'dean' ? 'registrar_status' : 'dean_status';
    $otherLabel = $role === 'dean' ? __('Registrator') : __('Dekan');
    $canBulkDelete = $canBulkDelete ?? false;
    $canBulkDecide = $canBulkDecide ?? false;
@endphp

<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden"
     x-data="{ openReject: null, openApprove: null, openVerifyReject: false }">
    <div class="p-4 border-b border-gray-100 flex items-start justify-between flex-wrap gap-3">
        @if($canBulkDelete)
            <label class="flex items-center pt-1 cursor-pointer">
                <input type="checkbox"
                       :checked="selected.includes({{ $group->id }})"
                       @change="if ($event.target.checked) {
                            if (!selected.includes({{ $group->id }})) selected.push({{ $group->id }});
                        } else {
                            const idx = selected.indexOf({{ $group->id }});
                            if (idx > -1) selected.splice(idx, 1);
                        }"
                       class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
            </label>
        @endif
        <div class="flex-1">
            <p class="text-sm font-semibold text-gray-900">
                {{ $student?->full_name ?? '— talaba topilmadi —' }}
                <span class="text-xs text-gray-500 font-normal">
                    · HEMIS {{ $group->student_hemis_id }}
                </span>
            </p>
            <p class="text-xs text-gray-600 mt-0.5">
                {{ $student?->department_name ?? '' }} ·
                {{ $student?->specialty_name ?? '' }} ·
                {{ $student?->level_name ?? $student?->level_code }} ·
                {{ $student?->group_name ?? '' }}
            </p>
            <p class="text-[11px] text-gray-400 mt-0.5">
                {{ __('Yuborilgan') }}: {{ $group->created_at->format('Y-m-d H:i') }}
                · {{ __('Summa') }}: {{ number_format($group->receipt_amount, 0, '.', ' ') }} UZS
            </p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('admin.retake.receipt', $group->id) }}"
               target="_blank"
               class="text-xs text-blue-600 hover:underline">{{ __('Kvitansiya') }}</a>
        </div>
    </div>

    @if($group->comment)
        <div class="px-4 pt-3 pb-1">
            <p class="text-xs text-gray-700">
                <span class="font-medium">{{ __('Talaba izohi') }}:</span>
                {{ $group->comment }}
            </p>
        </div>
    @endif

    {{-- Fanlar --}}
    <div class="divide-y divide-gray-100">
        @foreach($group->applications as $app)
            @php
                $myAppStatus = $app->{$myStatusField};
                $isPendingForMe = $myAppStatus === 'pending' && $app->final_status === 'pending';
            @endphp
            <div class="p-4">
                <div class="flex items-start justify-between flex-wrap gap-3">
                    @if($canBulkDecide && $isPendingForMe)
                        <label class="flex items-center pt-1 cursor-pointer">
                            <input type="checkbox"
                                   :checked="bulkApps.includes({{ $app->id }})"
                                   @change="if ($event.target.checked) {
                                        if (!bulkApps.includes({{ $app->id }})) bulkApps.push({{ $app->id }});
                                   } else {
                                        const idx = bulkApps.indexOf({{ $app->id }});
                                        if (idx > -1) bulkApps.splice(idx, 1);
                                   }"
                                   class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        </label>
                    @endif
                    <div class="flex-1 min-w-[200px]">
                        <p class="text-sm font-medium text-gray-900">
                            {{ $app->subject_name }}
                            <span class="text-xs text-gray-500 font-normal">
                                · {{ $app->semester_name }}
                                · {{ number_format($app->credit, 1) }} {{ __('kr') }}
                            </span>
                        </p>

                        {{-- Boshqa rolning hozirgi holati --}}
                        <p class="text-[11px] mt-1">
                            <span class="text-gray-500">{{ $otherLabel }}:</span>
                            @php
                                $otherStatus = $app->{$otherStatusField};
                                $otherUserName = $role === 'dean' ? $app->registrar_user_name : $app->dean_user_name;
                                $otherDecisionAt = $role === 'dean' ? $app->registrar_decision_at : $app->dean_decision_at;
                                $otherColor = match($otherStatus) {
                                    'approved' => 'text-green-700',
                                    'rejected' => 'text-red-700',
                                    default => 'text-yellow-700',
                                };
                                $otherText = match($otherStatus) {
                                    'approved' => '✓ Tasdiqlagan',
                                    'rejected' => '✗ Rad etgan',
                                    default => '⏳ Kutmoqda',
                                };
                            @endphp
                            <span class="font-medium {{ $otherColor }}">{{ $otherText }}</span>
                            @if($otherStatus !== 'pending' && $otherUserName)
                                <span class="text-gray-700">— {{ $otherUserName }}</span>
                                @if($otherDecisionAt)
                                    <span class="text-gray-400">({{ \Carbon\Carbon::parse($otherDecisionAt)->format('Y-m-d H:i') }})</span>
                                @endif
                            @endif
                            @if($otherStatus === 'rejected')
                                <br>
                                <span class="text-gray-500">{{ __('Sabab') }}: {{ $role === 'dean' ? $app->registrar_reason : $app->dean_reason }}</span>
                            @endif
                        </p>

                        {{-- O'quv bo'limi qarori (agar belgilangan bo'lsa) --}}
                        @if($app->academic_dept_status !== 'pending' && $app->academic_dept_user_name)
                            <p class="text-[11px] mt-1">
                                <span class="text-gray-500">{{ __("O'quv bo'limi") }}:</span>
                                @php
                                    $adColor = $app->academic_dept_status === 'approved' ? 'text-green-700' : 'text-red-700';
                                    $adText = $app->academic_dept_status === 'approved' ? '✓ Tasdiqlagan' : '✗ Rad etgan';
                                @endphp
                                <span class="font-medium {{ $adColor }}">{{ $adText }}</span>
                                <span class="text-gray-700">— {{ $app->academic_dept_user_name }}</span>
                                @if($app->academic_dept_decision_at)
                                    <span class="text-gray-400">({{ \Carbon\Carbon::parse($app->academic_dept_decision_at)->format('Y-m-d H:i') }})</span>
                                @endif
                                @if($app->academic_dept_status === 'rejected' && $app->academic_dept_reason)
                                    <br>
                                    <span class="text-gray-500">{{ __('Sabab') }}: {{ $app->academic_dept_reason }}</span>
                                @endif
                            </p>
                        @endif

                        {{-- Yakuniy holat (agar belgilangan bo'lsa) --}}
                        @if($app->final_status === 'rejected')
                            <p class="text-[11px] mt-1 text-red-700 font-medium">
                                {{ __('Yakuniy: Rad etilgan') }} ({{ $app->rejected_by }})
                            </p>
                        @elseif($app->final_status === 'approved')
                            <p class="text-[11px] mt-1 text-green-700 font-medium">
                                {{ __('Yakuniy: Tasdiqlangan ✓') }}
                            </p>
                        @endif
                    </div>

                    {{-- Mening qarorim --}}
                    <div class="flex flex-col items-end gap-1.5">
                        @php $myStatus = $app->{$myStatusField}; @endphp
                        @if($myStatus === 'pending' && $app->final_status === 'pending')
                            <div class="flex gap-2">
                                @if($role === 'registrar')
                                    {{-- Registrator: grade va OSKE/TEST modali orqali tasdiqlaydi --}}
                                    <button type="button"
                                            @click="openApprove = {{ $app->id }}"
                                            class="px-3 py-1.5 text-xs font-medium bg-green-600 text-white rounded-md hover:bg-green-700">
                                        {{ __('Tasdiqlash') }}
                                    </button>
                                @else
                                    {{-- Dekan: darhol tasdiqlaydi --}}
                                    <form method="POST" action="{{ route('admin.retake.decide', $app->id) }}" class="inline">
                                        @csrf
                                        <input type="hidden" name="decision" value="approved">
                                        <button type="submit"
                                                class="px-3 py-1.5 text-xs font-medium bg-green-600 text-white rounded-md hover:bg-green-700">
                                            {{ __('Tasdiqlash') }}
                                        </button>
                                    </form>
                                @endif
                                <button type="button"
                                        @click="openReject = {{ $app->id }}"
                                        class="px-3 py-1.5 text-xs font-medium bg-red-600 text-white rounded-md hover:bg-red-700">
                                    {{ __('Rad etish') }}
                                </button>
                            </div>
                        @elseif($myStatus === 'pending' && $app->final_status === 'rejected')
                            {{-- Boshqa rol rad etgan, mening qarorimga ehtiyoj yo'q --}}
                            <span class="px-3 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-700">
                                {{ __("Qaror talab qilinmaydi") }}
                            </span>
                            <span class="text-[11px] text-gray-500 max-w-xs text-right">
                                {{ __("Boshqa rol arizani rad etgan") }}
                            </span>
                        @else
                            @php
                                $myUserName = $role === 'dean' ? $app->dean_user_name : $app->registrar_user_name;
                                $myDecisionAt = $role === 'dean' ? $app->dean_decision_at : $app->registrar_decision_at;
                            @endphp
                            <span class="px-3 py-1 text-xs font-medium rounded-full
                                {{ $myStatus === 'approved' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                {{ $myStatus === 'approved' ? __('✓ Siz tasdiqlagansiz') : __('✗ Siz rad etgansiz') }}
                            </span>
                            @if($myUserName)
                                <span class="text-[11px] text-gray-600 mt-0.5">{{ $myUserName }}</span>
                            @endif
                            @if($myDecisionAt)
                                <span class="text-[10px] text-gray-400">{{ \Carbon\Carbon::parse($myDecisionAt)->format('Y-m-d H:i') }}</span>
                            @endif
                            @if($myStatus === 'rejected' && $app->{$role . '_reason'})
                                <span class="text-[11px] text-gray-500 max-w-xs text-right">
                                    {{ $role === 'dean' ? $app->dean_reason : $app->registrar_reason }}
                                </span>
                            @endif
                        @endif
                    </div>
                </div>

                {{-- Rad etish modal (har fan uchun) --}}
                <div x-show="openReject === {{ $app->id }}"
                     x-cloak
                     class="fixed inset-0 z-50 flex items-center justify-center p-4"
                     @keydown.escape.window="openReject = null">
                    <div class="fixed inset-0 bg-black bg-opacity-50" @click="openReject = null"></div>
                    <div class="relative bg-white rounded-xl shadow-xl max-w-md w-full p-5 z-10">
                        <h4 class="text-sm font-bold text-gray-900 mb-3">
                            {{ __('Rad etish sababi') }}
                        </h4>
                        <p class="text-xs text-gray-500 mb-3">
                            {{ $app->subject_name }} ({{ $app->semester_name }})
                        </p>
                        <form method="POST" action="{{ route('admin.retake.decide', $app->id) }}">
                            @csrf
                            <input type="hidden" name="decision" value="rejected">
                            <textarea name="reason"
                                      rows="4"
                                      required
                                      minlength="{{ $minReasonLength }}"
                                      maxlength="1000"
                                      placeholder="{{ __('Sababni yozing (eng kamida') }} {{ $minReasonLength }} {{ __('belgi)') }}"
                                      class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500"></textarea>
                            <div class="flex gap-2 mt-3">
                                <button type="button"
                                        @click="openReject = null"
                                        class="flex-1 px-3 py-2 text-xs bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">
                                    {{ __('Bekor qilish') }}
                                </button>
                                <button type="submit"
                                        class="flex-1 px-3 py-2 text-xs bg-red-600 text-white rounded-lg hover:bg-red-700">
                                    {{ __('Rad etish') }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                {{-- Registrator tasdiqlash modal: baho va OSKE/TEST flaglari --}}
                @if($role === 'registrar')
                    <div x-show="openApprove === {{ $app->id }}"
                         x-cloak
                         class="fixed inset-0 z-50 flex items-center justify-center p-4"
                         @keydown.escape.window="openApprove = null">
                        <div class="fixed inset-0 bg-black bg-opacity-50" @click="openApprove = null"></div>
                        <div class="relative bg-white rounded-xl shadow-xl max-w-md w-full p-5 z-10">
                            <h4 class="text-sm font-bold text-gray-900 mb-2">
                                {{ __('Tasdiqlash — oldingi baholar') }}
                            </h4>
                            <p class="text-xs text-gray-500 mb-4">
                                {{ $app->subject_name }} ({{ $app->semester_name }})
                            </p>
                            <form method="POST" action="{{ route('admin.retake.decide', $app->id) }}" class="space-y-3">
                                @csrf
                                <input type="hidden" name="decision" value="approved">

                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 mb-1">
                                            {{ __('Joriy bahosi') }} <span class="text-red-500">*</span>
                                        </label>
                                        <input type="number"
                                               name="previous_joriy_grade"
                                               step="0.1"
                                               min="0"
                                               max="100"
                                               required
                                               class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 mb-1">
                                            {{ __('Mustaqil ta\'lim bahosi') }} <span class="text-red-500">*</span>
                                        </label>
                                        <input type="number"
                                               name="previous_mustaqil_grade"
                                               step="0.1"
                                               min="0"
                                               max="100"
                                               required
                                               class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500">
                                    </div>
                                </div>

                                <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 space-y-2">
                                    <p class="text-[11px] font-medium text-amber-900">
                                        {{ __("Qayta o'qishda topshiriladi") }}:
                                    </p>
                                    <label class="flex items-center gap-2 text-xs text-gray-700">
                                        <input type="checkbox" name="has_oske" value="1"
                                               class="w-4 h-4 rounded border-gray-300 text-amber-600 focus:ring-amber-500">
                                        <span>{{ __('OSKE') }}</span>
                                    </label>
                                    <label class="flex items-center gap-2 text-xs text-gray-700">
                                        <input type="checkbox" name="has_test" value="1"
                                               class="w-4 h-4 rounded border-gray-300 text-amber-600 focus:ring-amber-500">
                                        <span>{{ __('TEST') }}</span>
                                    </label>
                                    <label class="flex items-center gap-2 text-xs text-gray-700">
                                        <input type="checkbox" name="has_sinov" value="1"
                                               class="w-4 h-4 rounded border-gray-300 text-amber-600 focus:ring-amber-500">
                                        <span>{{ __('Sinov fan') }}</span>
                                    </label>
                                </div>

                                <div class="flex gap-2 pt-2">
                                    <button type="button"
                                            @click="openApprove = null"
                                            class="flex-1 px-3 py-2 text-xs bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">
                                        {{ __('Bekor qilish') }}
                                    </button>
                                    <button type="submit"
                                            class="flex-1 px-3 py-2 text-xs bg-green-600 text-white rounded-lg hover:bg-green-700">
                                        {{ __('Tasdiqlash') }}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                @endif

                {{-- Tasdiqlangandan keyin: oldingi baholar va OSKE/TEST/Sinov fan flaglarini ko'rsatish --}}
                @if($app->registrar_status === 'approved' && ($app->previous_joriy_grade !== null || $app->has_oske || $app->has_test || $app->has_sinov))
                    <div class="mt-2 text-[11px] text-gray-700 bg-gray-50 rounded-md px-2 py-1.5">
                        @if($app->previous_joriy_grade !== null)
                            <span>{{ __('Joriy') }}: <span class="font-medium">{{ rtrim(rtrim(number_format($app->previous_joriy_grade, 2, '.', ''), '0'), '.') }}</span></span>
                            <span class="mx-1 text-gray-300">·</span>
                            <span>{{ __('Mustaqil') }}: <span class="font-medium">{{ rtrim(rtrim(number_format($app->previous_mustaqil_grade, 2, '.', ''), '0'), '.') }}</span></span>
                        @endif
                        @if($app->has_oske || $app->has_test || $app->has_sinov)
                            @php
                                $tags = [];
                                if ($app->has_oske) $tags[] = 'OSKE';
                                if ($app->has_test) $tags[] = 'TEST';
                                if ($app->has_sinov) $tags[] = __('Sinov fan');
                            @endphp
                            <span class="mx-1 text-gray-300">·</span>
                            <span class="text-amber-700">{{ __("Qayta topshiriladi") }}:
                                <span class="font-medium">{{ implode(', ', $tags) }}</span>
                            </span>
                        @endif
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    {{-- To'lov cheki tasdiqlash bloki (faqat registrator) --}}
    @if($role === 'registrar' && $group->payment_uploaded_at)
        <div class="border-t-2 px-4 py-3
                    {{ $group->payment_verification_status === 'pending' ? 'bg-amber-50 border-amber-200' : '' }}
                    {{ $group->payment_verification_status === 'approved' ? 'bg-green-50 border-green-200' : '' }}
                    {{ $group->payment_verification_status === 'rejected' ? 'bg-red-50 border-red-200' : '' }}">
            <div class="flex items-center justify-between flex-wrap gap-2">
                <div class="text-xs">
                    <span class="font-semibold">{{ __("To'lov cheki") }}:</span>
                    <a href="{{ route('admin.retake.payment-receipt', $group->id) }}"
                       target="_blank"
                       class="text-blue-600 hover:underline">{{ __("Ko'rish") }}</a>
                    <span class="text-gray-500 ml-2">
                        ({{ __("Yuklangan") }}: {{ $group->payment_uploaded_at->format('Y-m-d H:i') }})
                    </span>

                    @if($group->payment_verification_status === 'approved')
                        <span class="ml-2 text-green-700 font-medium">✓ {{ __("Tasdiqlangan") }}</span>
                        @if($group->payment_verified_by_name)
                            <span class="text-gray-600">— {{ $group->payment_verified_by_name }}</span>
                        @endif
                    @elseif($group->payment_verification_status === 'rejected')
                        <span class="ml-2 text-red-700 font-medium">✗ {{ __("Rad etilgan") }}</span>
                        @if($group->payment_rejection_reason)
                            <div class="text-gray-700 mt-1">{{ __("Sabab") }}: {{ $group->payment_rejection_reason }}</div>
                        @endif
                    @endif
                </div>

                @if($group->payment_verification_status === 'pending')
                    <div class="flex gap-2">
                        <form method="POST" action="{{ route('admin.retake.verify-payment', $group->id) }}" class="inline">
                            @csrf
                            <input type="hidden" name="decision" value="approved">
                            <button type="submit"
                                    class="px-3 py-1.5 text-xs font-medium bg-green-600 text-white rounded-md hover:bg-green-700">
                                {{ __("Haqiqiy") }}
                            </button>
                        </form>
                        <button type="button"
                                @click="openVerifyReject = true"
                                class="px-3 py-1.5 text-xs font-medium bg-red-600 text-white rounded-md hover:bg-red-700">
                            {{ __("Rad etish") }}
                        </button>
                    </div>
                @endif
            </div>
        </div>

        {{-- To'lov rad etish modal --}}
        <div x-show="openVerifyReject"
             x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center p-4"
             @keydown.escape.window="openVerifyReject = false">
            <div class="fixed inset-0 bg-black bg-opacity-50" @click="openVerifyReject = false"></div>
            <div class="relative bg-white rounded-xl shadow-xl max-w-md w-full p-5 z-10">
                <h4 class="text-sm font-bold text-gray-900 mb-3">
                    {{ __("To'lov chekini rad etish sababi") }}
                </h4>
                <form method="POST" action="{{ route('admin.retake.verify-payment', $group->id) }}">
                    @csrf
                    <input type="hidden" name="decision" value="rejected">
                    <textarea name="reason"
                              rows="4"
                              required
                              minlength="{{ $minReasonLength }}"
                              maxlength="1000"
                              placeholder="{{ __('Sababni yozing (eng kamida') }} {{ $minReasonLength }} {{ __('belgi)') }}"
                              class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500"></textarea>
                    <div class="flex gap-2 mt-3">
                        <button type="button"
                                @click="openVerifyReject = false"
                                class="flex-1 px-3 py-2 text-xs bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">
                            {{ __('Bekor qilish') }}
                        </button>
                        <button type="submit"
                                class="flex-1 px-3 py-2 text-xs bg-red-600 text-white rounded-lg hover:bg-red-700">
                            {{ __('Rad etish') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
