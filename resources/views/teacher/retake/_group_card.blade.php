@php
    $student = $group->student;
    $myStatusField = $role === 'dean' ? 'dean_status' : 'registrar_status';
    $otherStatusField = $role === 'dean' ? 'registrar_status' : 'dean_status';
    $otherLabel = $role === 'dean' ? __('Registrator') : __('Dekan');
@endphp

<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden"
     x-data="{ openReject: null }">
    <div class="p-4 border-b border-gray-100 flex items-start justify-between flex-wrap gap-3">
        <div>
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
            <div class="p-4">
                <div class="flex items-start justify-between flex-wrap gap-3">
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
                            @if($otherStatus === 'rejected')
                                <span class="text-gray-500">— {{ $role === 'dean' ? $app->registrar_reason : $app->dean_reason }}</span>
                            @endif
                        </p>

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
                        @if($myStatus === 'pending')
                            <div class="flex gap-2">
                                <form method="POST" action="{{ route('admin.retake.decide', $app->id) }}" class="inline">
                                    @csrf
                                    <input type="hidden" name="decision" value="approved">
                                    <button type="submit"
                                            class="px-3 py-1.5 text-xs font-medium bg-green-600 text-white rounded-md hover:bg-green-700">
                                        {{ __('Tasdiqlash') }}
                                    </button>
                                </form>
                                <button type="button"
                                        @click="openReject = {{ $app->id }}"
                                        class="px-3 py-1.5 text-xs font-medium bg-red-600 text-white rounded-md hover:bg-red-700">
                                    {{ __('Rad etish') }}
                                </button>
                            </div>
                        @else
                            <span class="px-3 py-1 text-xs font-medium rounded-full
                                {{ $myStatus === 'approved' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                {{ $myStatus === 'approved' ? __('✓ Siz tasdiqlagansiz') : __('✗ Siz rad etgansiz') }}
                            </span>
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
            </div>
        @endforeach
    </div>
</div>
