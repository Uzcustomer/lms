<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-sm text-gray-800 leading-tight">
            {{ __("Talabalar so'rovnomasi natijalari") }}
        </h2>
    </x-slot>

    @php
        $pct = $totalActive > 0 ? round($completedCount * 100 / $totalActive, 1) : 0;
    @endphp

    <div class="py-4">
        <div class="max-w-7xl mx-auto sm:px-4 lg:px-6 space-y-4">

            {{-- SARLAVHA + UMUMIY --}}
            <div class="bg-white shadow rounded-lg p-4 sm:p-6">
                <div class="flex flex-wrap items-start justify-between gap-3 mb-3">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900">{{ $config['title'] }}</h3>
                        <p class="text-xs text-gray-500 mt-1">Survey key: <code>{{ $config['key'] }}</code></p>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-xs text-gray-500">Tugash:</span>
                        <span class="text-xs font-bold {{ $deadlinePassed ? 'text-red-700' : 'text-emerald-700' }}">
                            {{ $deadlineFormatted }}
                            @if($deadlinePassed) <span class="ml-1 px-1.5 py-0.5 bg-red-100 text-red-700 rounded">Muddat tugadi</span>
                            @else <span class="ml-1 px-1.5 py-0.5 bg-emerald-100 text-emerald-700 rounded">Faol</span>
                            @endif
                        </span>
                    </div>
                </div>

                @if(session('success'))
                    <div class="mb-3 px-3 py-2 bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm rounded">
                        {{ session('success') }}
                    </div>
                @endif

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <div class="border border-gray-200 rounded-lg p-3">
                        <div class="text-[11px] text-gray-500 uppercase font-semibold">Faol talabalar</div>
                        <div class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($totalActive) }}</div>
                    </div>
                    <div class="border border-emerald-200 bg-emerald-50/40 rounded-lg p-3">
                        <div class="text-[11px] text-emerald-700 uppercase font-semibold">Bajargan</div>
                        <div class="text-2xl font-bold text-emerald-700 mt-1">{{ number_format($completedCount) }}</div>
                    </div>
                    <div class="border border-amber-200 bg-amber-50/40 rounded-lg p-3">
                        <div class="text-[11px] text-amber-700 uppercase font-semibold">Bajarmagan</div>
                        <div class="text-2xl font-bold text-amber-700 mt-1">{{ number_format($pendingCount) }}</div>
                    </div>
                    <div class="border border-indigo-200 bg-indigo-50/40 rounded-lg p-3">
                        <div class="text-[11px] text-indigo-700 uppercase font-semibold">Foiz</div>
                        <div class="text-2xl font-bold text-indigo-700 mt-1">{{ $pct }}%</div>
                    </div>
                </div>

                <div class="mt-3 h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                    <div class="h-full rounded-full" style="width:{{ $pct }}%;background:linear-gradient(90deg,#34d399,#10b981);"></div>
                </div>
            </div>

            @php
                $announcementText = (new \App\Http\Controllers\Admin\StudentSurveyController())
                    ->buildAnnouncementMessage($config['title'], $deadlineFormatted);
                $reminderText = (new \App\Http\Controllers\Admin\StudentSurveyController())
                    ->buildReminderMessage($config['title'], $deadlineFormatted);
            @endphp

            {{-- 1. E'LON — barcha talabalarga --}}
            <div class="bg-white shadow rounded-lg p-4 sm:p-6 border-l-4 border-emerald-400">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-700">1-QADAM</span>
                            <h3 class="text-base font-bold text-gray-900">E'lon: so'rovnoma boshlandi</h3>
                        </div>
                        <p class="text-xs text-gray-600 mt-1 leading-relaxed">
                            <strong>Barcha</strong> faol talabalarga (Telegram tasdiqlangan) bir martalik e'lon yuboriladi:
                            so'rov mavzusi, tugash muddati, oqibatlari (profilga kira olmaslik) va anonimlik haqida.
                        </p>
                        <details class="mt-2 text-xs text-gray-600">
                            <summary class="cursor-pointer text-emerald-700 font-semibold">Yuboriladigan matnni ko'rish</summary>
                            <pre class="mt-2 p-3 bg-emerald-50 border border-emerald-200 rounded text-[11px] leading-snug whitespace-pre-wrap">{!! $announcementText !!}</pre>
                        </details>
                    </div>
                    <form method="POST" action="{{ route('admin.student-survey.send-announcement') }}"
                          onsubmit="return confirm('Barcha faol talabalarga e\'lon yuborilsinmi?');">
                        @csrf
                        <button type="submit"
                                class="inline-flex items-center gap-2 px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold rounded-lg transition">
                            📣 E'lon yuborish
                        </button>
                    </form>
                </div>
            </div>

            {{-- 2. ESLATMA — bajarmaganlarga --}}
            <div class="bg-white shadow rounded-lg p-4 sm:p-6 border-l-4 border-indigo-400">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-indigo-100 text-indigo-700">2-QADAM</span>
                            <h3 class="text-base font-bold text-gray-900">Eslatma: bajarmaganlarga</h3>
                        </div>
                        <p class="text-xs text-gray-600 mt-1 leading-relaxed">
                            <strong>Faqat bajarmagan</strong> talabalarga eslatma yuboriladi. Bu tugma qo'lda. Avtomatik tarzda
                            har kuni <strong>09:00 da</strong> ham yuboriladi (deadline tugamaguncha).
                        </p>
                        <details class="mt-2 text-xs text-gray-600">
                            <summary class="cursor-pointer text-indigo-700 font-semibold">Yuboriladigan matnni ko'rish</summary>
                            <pre class="mt-2 p-3 bg-indigo-50 border border-indigo-200 rounded text-[11px] leading-snug whitespace-pre-wrap">{!! $reminderText !!}</pre>
                        </details>
                    </div>
                    <form method="POST" action="{{ route('admin.student-survey.send-telegram') }}"
                          onsubmit="return confirm('Bajarmagan talabalarga eslatma yuborilsinmi?');">
                        @csrf
                        <button type="submit"
                                class="inline-flex items-center gap-2 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold rounded-lg transition">
                            🔔 Eslatma yuborish
                        </button>
                    </form>
                </div>
            </div>

            {{-- FAKULTET BO'YICHA --}}
            @if($facultyStats->isNotEmpty())
            <div class="bg-white shadow rounded-lg p-4 sm:p-6">
                <h3 class="text-base font-bold text-gray-900 mb-3">Fakultet bo'yicha bajarganlar</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="text-left px-3 py-2 font-semibold text-gray-700">Fakultet</th>
                                <th class="text-right px-3 py-2 font-semibold text-gray-700">Soni</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($facultyStats as $row)
                                <tr>
                                    <td class="px-3 py-2 text-gray-800">{{ $row->fakultet ?: '—' }}</td>
                                    <td class="px-3 py-2 text-right font-bold text-gray-900">{{ $row->soni }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif

            {{-- HAR SAVOL TAQSIMOTI --}}
            <div class="bg-white shadow rounded-lg p-4 sm:p-6">
                <h3 class="text-base font-bold text-gray-900 mb-4">Savollar bo'yicha javoblar taqsimoti</h3>

                <div class="space-y-5">
                    @foreach($config['questions'] as $q)
                        @php $s = $stats[$q['id']]; @endphp
                        <div class="border border-gray-200 rounded-lg p-3 sm:p-4">
                            <div class="flex items-start gap-2 mb-2">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-indigo-100 text-indigo-700 flex-shrink-0">
                                    SAVOL {{ $q['id'] }}
                                </span>
                                <h4 class="text-sm font-semibold text-gray-800 leading-snug">{{ $q['text'] }}</h4>
                            </div>

                            @if($s['type'] === 'text')
                                <div class="text-xs text-gray-600">
                                    Erkin matnli javoblar: <strong>{{ $s['count'] }}</strong> ta
                                    @if(isset($textAnswers[$q['id']]) && $textAnswers[$q['id']]->isNotEmpty())
                                    <details class="mt-2">
                                        <summary class="cursor-pointer text-indigo-600 font-semibold">Javoblarni ko'rish</summary>
                                        <ul class="mt-2 space-y-1.5 max-h-72 overflow-y-auto">
                                            @foreach($textAnswers[$q['id']] as $t)
                                                <li class="text-xs text-gray-700 bg-gray-50 border border-gray-200 rounded px-2 py-1.5">
                                                    {{ $t->answer }}
                                                    <span class="text-[10px] text-gray-400 ml-1">[{{ $t->created_at->format('d.m.Y H:i') }}]</span>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </details>
                                    @endif
                                </div>
                            @else
                                @php
                                    $respondersN = $s['responders'] ?: 1;
                                    $opts = $s['options'];
                                    if (!isset($opts['other'])) $opts['other'] = 'Boshqa';
                                @endphp
                                <div class="space-y-1.5">
                                    @foreach($opts as $optId => $optText)
                                        @php
                                            $cnt = $s['totals'][$optId] ?? 0;
                                            $pctOpt = $s['responders'] > 0 ? round($cnt * 100 / $s['responders'], 1) : 0;
                                        @endphp
                                        @if($optId === 'other' && $cnt === 0)
                                            @continue
                                        @endif
                                        <div>
                                            <div class="flex items-center justify-between text-xs">
                                                <span class="text-gray-700 flex-1 pr-2">
                                                    @if($optId !== 'other')<strong class="text-indigo-600 mr-0.5">{{ $optId }})</strong>@endif
                                                    {{ $optText }}
                                                </span>
                                                <span class="font-bold text-gray-900 whitespace-nowrap">{{ $cnt }} <span class="text-gray-500 font-normal">({{ $pctOpt }}%)</span></span>
                                            </div>
                                            <div class="h-1.5 mt-0.5 w-full bg-gray-100 rounded-full overflow-hidden">
                                                <div class="h-full" style="width:{{ $pctOpt }}%;background:linear-gradient(90deg,#6366f1,#4f46e5);"></div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                                <div class="mt-2 text-[11px] text-gray-500">
                                    Javob berganlar: <strong>{{ $s['responders'] }}</strong>
                                    @if(!empty($s['otherTexts']))
                                        · <details class="inline">
                                            <summary class="cursor-pointer text-indigo-600 font-semibold">"Boshqa" matnlarini ko'rish ({{ count($s['otherTexts']) }})</summary>
                                            <ul class="mt-2 space-y-1 ml-2">
                                                @foreach($s['otherTexts'] as $ot)
                                                    <li class="text-xs text-gray-700 bg-gray-50 border border-gray-200 rounded px-2 py-1">{{ $ot }}</li>
                                                @endforeach
                                            </ul>
                                        </details>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
