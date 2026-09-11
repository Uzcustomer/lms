<x-student-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-sm text-gray-800 leading-tight">
            {{ __("Guruh ma'lumotlarim") }}
        </h2>
    </x-slot>

    <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 px-3 py-4 space-y-4">

        {{-- Guruh --}}
        <div class="bg-white border border-slate-200 rounded-3xl overflow-hidden shadow-sm">
            <div class="px-5 py-5 bg-gradient-to-r from-teal-50 via-white to-emerald-50 border-b border-slate-100">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Guruhingiz') }}</p>
                <h1 class="mt-1 text-2xl font-bold text-slate-900">{{ $groupName ?: '—' }}</h1>
                @if($student->specialty_name || $student->level_name)
                    <p class="mt-1 text-sm text-slate-500">
                        {{ collect([$student->specialty_name, $student->level_name])->filter()->implode(' · ') }}
                    </p>
                @endif
            </div>

            {{-- Taqsimotda o'tkazilgan talaba: eski guruh va izoh --}}
            @if($draft)
                <div class="px-5 py-4 flex items-center gap-3 flex-wrap">
                    <span class="px-3 py-1.5 rounded-xl bg-slate-100 text-slate-500 text-sm font-semibold line-through decoration-slate-400">
                        {{ $draft->from_group_name ?: '—' }}
                    </span>
                    <svg class="w-5 h-5 text-emerald-600 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                    </svg>
                    <span class="px-3 py-1.5 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm font-bold">
                        {{ $draft->to_group_name }}
                    </span>
                    <p class="w-full text-sm text-slate-500 leading-relaxed">
                        {{ __("Siz :group guruhiga o'tkazildingiz. Ma'lumot tez orada yangilanadi.", ['group' => $draft->to_group_name]) }}
                    </p>
                </div>
            @endif
        </div>

        {{-- Tyutor --}}
        <div class="bg-white border border-slate-200 rounded-3xl overflow-hidden shadow-sm">
            <div class="px-5 py-4 border-b border-slate-100">
                <h2 class="text-base font-bold text-slate-900">{{ __('Tyutor') }}</h2>
            </div>

            @forelse($tutors as $tutor)
                @php
                    $phone = trim((string) $tutor->phone);
                    $phoneDigits = preg_replace('/\D+/', '', $phone);
                    $tg = ltrim(trim((string) $tutor->telegram_username), '@');
                @endphp
                <div class="px-5 py-4 flex items-center gap-4 {{ !$loop->last ? 'border-b border-slate-100' : '' }}">
                    <div class="w-12 h-12 rounded-2xl bg-teal-100 text-teal-700 flex items-center justify-center flex-shrink-0 text-lg font-bold">
                        {{ mb_substr(trim((string) $tutor->full_name), 0, 1) }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-base font-semibold text-slate-900 leading-tight">{{ $tutor->full_name }}</p>
                        @if($phone !== '')
                            <p class="mt-1 text-sm text-slate-600">{{ $phone }}</p>
                        @else
                            <p class="mt-1 text-sm text-slate-400">{{ __('Telefon raqami kiritilmagan') }}</p>
                        @endif
                    </div>
                    <div class="flex items-center gap-2 flex-shrink-0">
                        @if($phoneDigits !== '')
                            <a href="tel:+{{ strlen($phoneDigits) === 9 ? '998' . $phoneDigits : $phoneDigits }}"
                               class="w-10 h-10 rounded-xl bg-emerald-600 text-white flex items-center justify-center hover:bg-emerald-700"
                               title="{{ __("Qo'ng'iroq qilish") }}">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z" />
                                </svg>
                            </a>
                        @endif
                        @if($tg !== '')
                            <a href="https://t.me/{{ $tg }}" target="_blank" rel="noopener noreferrer"
                               class="w-10 h-10 rounded-xl bg-sky-500 text-white flex items-center justify-center hover:bg-sky-600"
                               title="Telegram">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M9.78 18.65l.28-4.23 7.68-6.92c.34-.31-.07-.46-.52-.19L7.74 13.3 3.64 12c-.88-.25-.89-.86.2-1.3l15.97-6.16c.73-.33 1.43.18 1.15 1.3l-2.72 12.81c-.19.91-.74 1.13-1.5.71L12.6 16.3l-1.99 1.93c-.23.23-.42.42-.83.42z"/>
                                </svg>
                            </a>
                        @endif
                    </div>
                </div>
            @empty
                <div class="px-5 py-10 text-center">
                    <p class="text-sm font-semibold text-slate-700">{{ __('Tyutor biriktirilmagan') }}</p>
                    <p class="mt-1 text-sm text-slate-500">{{ __("Guruhingizga hali tyutor biriktirilmagan. Savol bo'lsa dekanatga murojaat qiling.") }}</p>
                </div>
            @endforelse
        </div>
    </div>
</x-student-app-layout>
