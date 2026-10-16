<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">Monitoring — kontrakt bo'yicha tavsiya etilganlar (A-5), {{ $year }}</h2>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            @include('admin.monitoring.partials.nav')

            <p class="text-sm text-gray-600 mb-3">
                To'lov chegarasi: oddiy kontrakt — yillik summaning kamida 25 %, qo'shimcha va tabaqalashtirilgan — birinchi yilning kamida 50 % (VMQ 578).
                To'lov ma'lumoti HEMIS kontraktlar ro'yxatidan (har kuni 23:00 da yangilanadi).
                F.I.Sh. bo'yicha topilgan mosliklarni tekshirib tasdiqlang.
            </p>

            <form method="GET" class="bg-white rounded-lg shadow p-4 mb-4 flex flex-wrap gap-3 items-end text-sm">
                <div>
                    <label class="block text-gray-600">O'quv yili</label>
                    <input type="text" name="yil" value="{{ $year }}" class="border-gray-300 rounded">
                </div>
                <div>
                    <label class="block text-gray-600">Moslik</label>
                    <select name="moslik" class="border-gray-300 rounded">
                        <option value="">— hammasi —</option>
                        <option value="yoq" @selected(request('moslik') === 'yoq')>HEMIS'da topilmagan</option>
                        <option value="tasdiqlanmagan" @selected(request('moslik') === 'tasdiqlanmagan')>Tasdiqlanmagan</option>
                    </select>
                </div>
                <div>
                    <label class="block text-gray-600">Qidirish</label>
                    <input type="text" name="q" value="{{ request('q') }}" class="border-gray-300 rounded">
                </div>
                <button class="px-4 py-2 bg-blue-600 text-white rounded">Filtrlash</button>
            </form>

            <div class="bg-white rounded-lg shadow overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-left text-gray-600">
                    <tr><th class="px-3 py-2">Abituriyent</th><th class="px-3 py-2">Kontrakt turi</th><th class="px-3 py-2">HEMIS talaba</th><th class="px-3 py-2">To'lov</th><th class="px-3 py-2"></th></tr>
                    </thead>
                    <tbody>
                    @forelse($recommendations as $rec)
                        @php
                            $contract = $rec->student_hemis_id ? ($contracts[$rec->student_hemis_id] ?? null) : null;
                            $pct = ($contract && (float) $contract->edu_contract_sum > 0) ? round((float) $contract->paid_credit_amount / (float) $contract->edu_contract_sum * 100, 1) : null;
                            $need = $rec->isSpecialContract() ? 50 : 25;
                        @endphp
                        <tr class="border-t align-top">
                            <td class="px-3 py-2">{{ $rec->full_name }}<div class="text-xs text-gray-500">{{ $rec->applicant_id }} · {{ $rec->specialty_name }}</div></td>
                            <td class="px-3 py-2">{{ \App\Models\MonitoringAdmissionRecommendation::CONTRACT_TYPES[$rec->contract_type] ?? $rec->contract_type }}</td>
                            <td class="px-3 py-2">
                                @if($rec->student)
                                    {{ $rec->student->full_name }} <span class="text-xs text-gray-500">({{ $rec->student->student_id_number }}, {{ $rec->student->group_name }})</span>
                                    <div class="text-xs {{ $rec->match_confirmed ? 'text-green-700' : 'text-yellow-700' }}">
                                        {{ \App\Models\MonitoringAdmissionRecommendation::MATCH_METHODS[$rec->match_method] ?? $rec->match_method }}{{ $rec->match_confirmed ? ' · tasdiqlangan' : ' · tasdiqlanmagan' }}
                                    </div>
                                @else
                                    <span class="text-gray-500">topilmadi</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap">
                                @if($pct === null) — @else
                                    <span class="{{ $pct >= $need ? 'text-green-700' : 'text-red-700' }}">{{ $pct }} %</span> <span class="text-xs text-gray-500">/ {{ $need }} %</span>
                                @endif
                            </td>
                            <td class="px-3 py-2">
                                @if($canWork)
                                    <form method="POST" action="{{ route('admin.monitoring.tavsiyalar.link', $rec) }}" class="flex flex-wrap gap-1 text-xs">
                                        @csrf
                                        @if($rec->student_hemis_id && !$rec->match_confirmed)
                                            <button name="action" value="confirm" class="px-2 py-1 bg-green-600 text-white rounded">Tasdiqlash</button>
                                        @endif
                                        @if($rec->student_hemis_id)
                                            <button name="action" value="unlink" class="px-2 py-1 bg-gray-200 rounded">Bog'lamaslik</button>
                                        @endif
                                        <input type="text" name="student_id_number" placeholder="Talaba ID raqami" class="border-gray-300 rounded text-xs" style="width: 140px;">
                                        <button name="action" value="link" class="px-2 py-1 bg-blue-600 text-white rounded">Bog'lash</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-3 py-4 text-gray-500">Bu o'quv yili uchun ro'yxat yuklanmagan — «Importlar» bo'limidan mandatni yuklang.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $recommendations->links() }}</div>
        </div>
    </div>
</x-app-layout>
