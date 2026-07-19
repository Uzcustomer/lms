<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Jamlangan solishtirma — barcha semestrlar
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">

            <div class="mb-4 flex items-center justify-between flex-wrap gap-3">
                <a href="{{ route('admin.oquv-reja.index') }}#solishtirish" class="text-blue-600 hover:underline text-sm">&larr; O'quv reja to'g'riligi</a>
                <a href="{{ route('admin.oquv-reja.compare-group-export', ['reference_id' => $reference->id]) }}"
                   class="px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-md hover:bg-green-700">
                    Excelga yuklab olish
                </a>
            </div>

            {{-- Namunaviy reja va guruhga kirgan ishchi rejalar --}}
            <div class="bg-white shadow-sm rounded-lg p-4 mb-6">
                <div class="text-sm text-gray-600 mb-3">
                    <span class="px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 mr-1">Namunaviy</span>
                    <span class="font-semibold text-blue-700">{{ $reference->name }}</span>
                    <span class="mx-2">&harr;</span>
                    <span class="px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800 mr-1">Ishchi</span>
                    <span class="font-semibold text-purple-700">{{ $workings->count() }} ta reja (jamlangan)</span>
                </div>

                {{-- Har bir ishchi reja bo'yicha qisqa xulosa --}}
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-1.5 text-left font-medium text-gray-600">Semestr</th>
                            <th class="px-3 py-1.5 text-left font-medium text-gray-600">Ishchi reja</th>
                            <th class="px-3 py-1.5 text-left font-medium text-gray-600">Reja yili</th>
                            <th class="px-3 py-1.5 text-right font-medium text-gray-600">Fanlar</th>
                            <th class="px-3 py-1.5 text-right font-medium text-gray-600">Soat</th>
                            <th class="px-3 py-1.5 text-right font-medium text-gray-600">Kredit</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                        @foreach($comparison['plans'] as $plan)
                            <tr>
                                <td class="px-3 py-1.5">
                                    <span class="px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800">
                                        {{ $plan['semester'] !== null ? $plan['semester'] . '-semestr' : '—' }}
                                    </span>
                                </td>
                                <td class="px-3 py-1.5">
                                    <a href="{{ route('admin.oquv-reja.show', $plan['id']) }}" class="text-blue-600 hover:underline">{{ $plan['name'] }}</a>
                                </td>
                                <td class="px-3 py-1.5 text-gray-500">{{ $plan['plan_year'] ?: '—' }}</td>
                                <td class="px-3 py-1.5 text-right">{{ $plan['subjects'] }}</td>
                                <td class="px-3 py-1.5 text-right">{{ $plan['hours'] }}</td>
                                <td class="px-3 py-1.5 text-right">{{ $plan['credit'] }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-3 flex flex-wrap items-center gap-2 text-sm">
                    @if(!empty($comparison['covered_semesters']))
                        <span class="text-gray-500">Qamrab olingan semestrlar:</span>
                        @foreach($comparison['covered_semesters'] as $sem)
                            <span class="px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">{{ $sem }}-semestr</span>
                        @endforeach
                    @endif
                    @if(!empty($missingSemesters))
                        <span class="text-gray-500 ml-2">Hali yuklanmagan:</span>
                        @foreach($missingSemesters as $sem)
                            <span class="px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-500">{{ $sem }}-semestr</span>
                        @endforeach
                    @endif
                </div>

                @if(!empty($comparison['covered_semesters']))
                    <p class="mt-2 text-xs text-gray-500">
                        Solishtirish faqat yuklangan semestrlar bo'yicha: namunaviy rejaning
                        {{ implode(', ', $comparison['covered_semesters']) }}-semestr qatorlari olindi.
                        Yangi semestr ishchi rejasi yuklansa, u avtomatik shu jadvalga qo'shiladi.
                    </p>
                @endif
            </div>

            @include('admin.oquv-reja._compare-table', ['comparison' => $comparison])

        </div>
    </div>
</x-app-layout>
