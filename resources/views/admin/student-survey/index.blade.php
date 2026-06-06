<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Talabalar so'rovnomasi</h2>
    </x-slot>

    @php
        $pct = $totalActive > 0 ? round($completedCount * 100 / $totalActive, 1) : 0;
        $announcementText = (new \App\Http\Controllers\Admin\StudentSurveyController())
            ->buildAnnouncementMessage($config['title'], $deadlineFormatted);
        $reminderText = (new \App\Http\Controllers\Admin\StudentSurveyController())
            ->buildReminderMessage($config['title'], $deadlineFormatted);
    @endphp

    <div class="py-6">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">

            @if(session('success'))
                <div class="bg-white rounded-xl border border-emerald-200 shadow-sm overflow-hidden">
                    <div class="px-5 py-3 flex items-center gap-3" style="background: linear-gradient(135deg, #ecfdf5, #d1fae5);">
                        <svg class="w-5 h-5 text-emerald-600 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span class="text-sm font-semibold text-emerald-800">{{ session('success') }}</span>
                    </div>
                </div>
            @endif

            {{-- HEADER CARD --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100" style="background: linear-gradient(135deg, #e8edf5, #dbe4ef);">
                    <div class="flex items-start justify-between gap-3 flex-wrap">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0" style="background: linear-gradient(135deg, #2b5ea7, #3b82f6);">
                                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                                </svg>
                            </div>
                            <div>
                                <div class="font-bold text-gray-800 text-sm">{{ $config['title'] }}</div>
                                <div class="text-xs text-gray-500 mt-0.5">Survey key: <code class="bg-gray-100 px-1 rounded">{{ $config['key'] }}</code></div>
                            </div>
                        </div>
                        <div class="flex items-center gap-4 flex-wrap">
                            <div class="text-xs" x-data="{ editing: false }">
                                <div class="text-gray-500 flex items-center gap-1.5">
                                    <span>Tugash muddati</span>
                                    <button type="button" @click="editing = !editing"
                                            class="text-blue-600 hover:text-blue-800" title="Tahrirlash">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                    </button>
                                </div>
                                <div class="font-semibold text-gray-800" x-show="!editing">{{ $deadlineFormatted }}</div>
                                <form method="POST" action="{{ route('admin.student-survey.deadline') }}"
                                      class="flex items-center gap-1 mt-0.5" x-show="editing" x-cloak>
                                    @csrf
                                    <input type="datetime-local" name="deadline" value="{{ $deadlineForInput }}" required
                                           class="text-xs border border-gray-300 rounded px-1.5 py-0.5 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <button type="submit" class="px-2 py-0.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded">Saqlash</button>
                                    <button type="button" @click="editing = false" class="px-2 py-0.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs rounded">×</button>
                                </form>
                            </div>
                            @if($deadlinePassed)
                                <span class="px-2 py-1 bg-red-100 text-red-700 rounded text-xs font-bold">Tugadi</span>
                            @else
                                <span class="px-2 py-1 bg-emerald-100 text-emerald-700 rounded text-xs font-bold">Faol</span>
                            @endif

                            {{-- Toggle: Survey ON/OFF (kichik, inline) --}}
                            <div class="flex items-center gap-2 pl-3 ml-1 border-l border-gray-300">
                                <span class="text-[11px] font-bold uppercase tracking-wide" id="sv-toggle-status"
                                      style="color: {{ $isActive ? '#059669' : '#9ca3af' }};">
                                    {{ $isActive ? 'Yoqilgan' : "O'chirilgan" }}
                                </span>
                                <button type="button" id="sv-toggle-btn" onclick="svToggleActive(this)"
                                        class="relative inline-block rounded-full transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-blue-500"
                                        data-enabled="{{ $isActive ? '1' : '0' }}"
                                        title="{{ $isActive ? "So'rovnomani o'chirish" : "So'rovnomani yoqish" }}"
                                        style="width:36px;height:20px;background: {{ $isActive ? '#10b981' : '#d1d5db' }};">
                                    <span class="absolute rounded-full bg-white shadow"
                                          style="width:14px;height:14px;top:3px;left:{{ $isActive ? '19px' : '3px' }};transition:left 0.2s;"></span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- STATS GRID --}}
                <div class="grid grid-cols-2 sm:grid-cols-4 divide-x divide-y sm:divide-y-0 divide-gray-100">
                    <div class="px-5 py-4">
                        <div class="text-[11px] text-gray-500 uppercase font-semibold tracking-wide">Faol talabalar</div>
                        <div class="text-2xl font-bold text-gray-800 mt-1">{{ number_format($totalActive) }}</div>
                    </div>
                    <div class="px-5 py-4">
                        <div class="text-[11px] text-emerald-700 uppercase font-semibold tracking-wide">Bajargan</div>
                        <div class="text-2xl font-bold text-emerald-600 mt-1">{{ number_format($completedCount) }}</div>
                    </div>
                    <div class="px-5 py-4">
                        <div class="text-[11px] text-amber-700 uppercase font-semibold tracking-wide">Bajarmagan</div>
                        <div class="text-2xl font-bold text-amber-600 mt-1">{{ number_format($pendingCount) }}</div>
                    </div>
                    <div class="px-5 py-4">
                        <div class="text-[11px] uppercase font-semibold tracking-wide" style="color:#2b5ea7;">Foiz</div>
                        <div class="text-2xl font-bold mt-1" style="color:#2b5ea7;">{{ $pct }}%</div>
                    </div>
                </div>

                <div class="px-5 pb-4">
                    <div class="h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                        <div class="h-full rounded-full transition-all" style="width:{{ $pct }}%;background:linear-gradient(90deg,#10b981,#34d399);"></div>
                    </div>
                </div>
            </div>

            {{-- TELEGRAM CARDS GRID --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

                {{-- 1. E'LON --}}
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                    <div class="px-5 py-3 border-b border-gray-100 flex items-center gap-3" style="background: linear-gradient(135deg, #ecfdf5, #d1fae5);">
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0" style="background: linear-gradient(135deg, #10b981, #059669);">
                            <span class="text-white text-xs font-bold">1</span>
                        </div>
                        <div>
                            <div class="font-bold text-gray-800 text-sm">E'lon yuborish</div>
                            <div class="text-xs text-gray-500 mt-0.5">Bir martalik — barcha talabalarga</div>
                        </div>
                    </div>
                    <div class="p-5">
                        <p class="text-xs text-gray-600 leading-relaxed mb-3">
                            Barcha faol talabalarga (Telegram tasdiqlangan) so'rovnoma boshlangani,
                            tugash muddati, oqibatlari (profilga kira olmaslik) va anonimlik haqida e'lon yuboriladi.
                        </p>
                        <details class="mb-4">
                            <summary class="cursor-pointer text-xs text-emerald-700 font-semibold hover:underline">Yuboriladigan matnni ko'rish</summary>
                            <pre class="mt-2 p-3 bg-emerald-50 border border-emerald-100 rounded-lg text-[11px] leading-snug whitespace-pre-wrap text-gray-700">{!! $announcementText !!}</pre>
                        </details>
                        <form method="POST" action="{{ route('admin.student-survey.send-announcement') }}"
                              onsubmit="return confirm('Barcha faol talabalarga e\'lon yuborilsinmi?');">
                            @csrf
                            <button type="submit"
                                    class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-white text-sm font-bold rounded-lg transition shadow-sm hover:shadow"
                                    style="background: linear-gradient(135deg, #10b981, #059669);">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/>
                                </svg>
                                E'lon yuborish
                            </button>
                        </form>
                    </div>
                </div>

                {{-- 2. ESLATMA --}}
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                    <div class="px-5 py-3 border-b border-gray-100 flex items-center gap-3" style="background: linear-gradient(135deg, #e8edf5, #dbe4ef);">
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0" style="background: linear-gradient(135deg, #2b5ea7, #3b82f6);">
                            <span class="text-white text-xs font-bold">2</span>
                        </div>
                        <div>
                            <div class="font-bold text-gray-800 text-sm">Eslatma yuborish</div>
                            <div class="text-xs text-gray-500 mt-0.5">Faqat bajarmaganlarga + har kuni 09:00 avto</div>
                        </div>
                    </div>
                    <div class="p-5">
                        <p class="text-xs text-gray-600 leading-relaxed mb-3">
                            So'rovnomani bajarmagan talabalarga eslatma yuboriladi. Avtomatik tarzda
                            <strong>har kuni 09:00 da</strong> bajaridi (deadline tugamaguncha).
                        </p>
                        <details class="mb-4">
                            <summary class="cursor-pointer text-xs font-semibold hover:underline" style="color:#2b5ea7;">Yuboriladigan matnni ko'rish</summary>
                            <pre class="mt-2 p-3 border rounded-lg text-[11px] leading-snug whitespace-pre-wrap text-gray-700" style="background:#f0f4fa;border-color:#dbe4ef;">{!! $reminderText !!}</pre>
                        </details>
                        <form method="POST" action="{{ route('admin.student-survey.send-telegram') }}"
                              onsubmit="return confirm('Bajarmagan talabalarga eslatma yuborilsinmi?');">
                            @csrf
                            <button type="submit"
                                    class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-white text-sm font-bold rounded-lg transition shadow-sm hover:shadow"
                                    style="background: linear-gradient(135deg, #2b5ea7, #3b82f6);">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                                </svg>
                                Eslatma yuborish
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            {{-- FAKULTET BO'YICHA --}}
            @if($facultyStats->isNotEmpty())
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="px-6 py-3 border-b border-gray-100" style="background: linear-gradient(135deg, #e8edf5, #dbe4ef);">
                    <div class="font-bold text-gray-800 text-sm">Fakultet bo'yicha bajarganlar</div>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="text-left px-5 py-2.5 text-xs font-semibold text-gray-600 uppercase tracking-wide">Fakultet</th>
                                <th class="text-right px-5 py-2.5 text-xs font-semibold text-gray-600 uppercase tracking-wide">Bajarganlar</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($facultyStats as $row)
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="px-5 py-2.5 text-sm text-gray-800">{{ $row->fakultet ?: '—' }}</td>
                                    <td class="px-5 py-2.5 text-right text-sm font-bold text-gray-900">{{ $row->soni }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif

            {{-- SAVOLLAR TAQSIMOTI --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="px-6 py-3 border-b border-gray-100" style="background: linear-gradient(135deg, #e8edf5, #dbe4ef);">
                    <div class="font-bold text-gray-800 text-sm">Savollar bo'yicha javoblar taqsimoti</div>
                </div>
                <div class="divide-y divide-gray-100">
                    @foreach($config['questions'] as $q)
                        @php $s = $stats[$q['id']]; @endphp
                        <div class="px-5 py-4">
                            <div class="flex items-start gap-2 mb-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold flex-shrink-0" style="background:#e0e7ff;color:#3730a3;">
                                    SAVOL {{ $q['id'] }}
                                </span>
                                <h4 class="text-sm font-semibold text-gray-800 leading-snug">{{ $q['text'] }}</h4>
                            </div>

                            @if($s['type'] === 'text')
                                <div class="text-xs text-gray-600">
                                    Erkin matnli javoblar: <strong class="text-gray-800">{{ $s['count'] }}</strong> ta
                                    @if(isset($textAnswers[$q['id']]) && $textAnswers[$q['id']]->isNotEmpty())
                                    <details class="mt-2">
                                        <summary class="cursor-pointer font-semibold hover:underline" style="color:#2b5ea7;">Javoblarni ko'rish</summary>
                                        <ul class="mt-2 space-y-1.5 max-h-72 overflow-y-auto">
                                            @foreach($textAnswers[$q['id']] as $t)
                                                <li class="text-xs text-gray-700 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2">
                                                    {{ $t->answer }}
                                                    <div class="text-[10px] text-gray-400 mt-1">{{ $t->created_at->format('d.m.Y H:i') }}</div>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </details>
                                    @endif
                                </div>
                            @else
                                @php
                                    $opts = $s['options'];
                                    if (!isset($opts['other'])) $opts['other'] = 'Boshqa';
                                @endphp
                                <div class="space-y-2">
                                    @foreach($opts as $optId => $optText)
                                        @php
                                            $cnt = $s['totals'][$optId] ?? 0;
                                            $pctOpt = $s['responders'] > 0 ? round($cnt * 100 / $s['responders'], 1) : 0;
                                        @endphp
                                        @if($optId === 'other' && $cnt === 0)
                                            @continue
                                        @endif
                                        <div>
                                            <div class="flex items-center justify-between text-xs mb-1">
                                                <span class="text-gray-700 flex-1 pr-2">
                                                    @if($optId !== 'other')<strong class="mr-0.5" style="color:#2b5ea7;">{{ $optId }})</strong>@endif
                                                    {{ $optText }}
                                                </span>
                                                <span class="font-bold text-gray-800 whitespace-nowrap">
                                                    {{ $cnt }} <span class="text-gray-400 font-normal">({{ $pctOpt }}%)</span>
                                                </span>
                                            </div>
                                            <div class="h-1.5 w-full bg-gray-100 rounded-full overflow-hidden">
                                                <div class="h-full" style="width:{{ $pctOpt }}%;background:linear-gradient(90deg,#2b5ea7,#3b82f6);"></div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                                <div class="mt-3 text-[11px] text-gray-500">
                                    Javob berganlar: <strong class="text-gray-700">{{ $s['responders'] }}</strong>
                                    @if(!empty($s['otherTexts']))
                                        · <details class="inline">
                                            <summary class="cursor-pointer font-semibold hover:underline" style="color:#2b5ea7;">"Boshqa" matnlarini ko'rish ({{ count($s['otherTexts']) }})</summary>
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

    <script>
        function svToggleActive(btn) {
            btn.disabled = true;
            const newState = btn.dataset.enabled !== '1';

            fetch('{{ route("admin.student-survey.toggle") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ enabled: newState }),
            })
            .then(r => r.json())
            .then(data => {
                btn.disabled = false;
                if (!data.success) { alert(data.message || 'Xatolik'); return; }
                btn.dataset.enabled = newState ? '1' : '0';
                btn.style.background = newState ? '#10b981' : '#d1d5db';
                btn.title = newState ? "So'rovnomani o'chirish" : "So'rovnomani yoqish";
                btn.querySelector('span').style.left = newState ? '19px' : '3px';

                const status = document.getElementById('sv-toggle-status');
                status.textContent = newState ? 'Yoqilgan' : "O'chirilgan";
                status.style.color = newState ? '#059669' : '#9ca3af';
            })
            .catch(() => { btn.disabled = false; alert('Tarmoq xatosi'); });
        }
    </script>
</x-app-layout>
