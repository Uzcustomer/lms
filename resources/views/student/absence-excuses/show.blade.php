<x-student-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Ariza #{{ $excuse->id }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">

                    {{-- Status badge --}}
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-lg font-medium text-gray-900">Ariza tafsilotlari</h3>
                        <span class="px-3 py-1 inline-flex text-sm leading-5 font-semibold rounded-full
                            bg-{{ $excuse->status_color }}-100 text-{{ $excuse->status_color }}-800">
                            {{ $excuse->status_label }}
                        </span>
                    </div>

                    <div class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-500">Sabab</label>
                                <p class="mt-1 text-sm text-gray-900">{{ $excuse->reason_label }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-500">Sanalar</label>
                                <p class="mt-1 text-sm text-gray-900">
                                    {{ $excuse->start_date->format('d.m.Y') }} - {{ $excuse->end_date->format('d.m.Y') }}
                                </p>
                            </div>
                        </div>

                        @if($excuse->description)
                            <div>
                                <label class="block text-sm font-medium text-gray-500">Izoh</label>
                                <p class="mt-1 text-sm text-gray-900">{{ $excuse->description }}</p>
                            </div>
                        @endif

                        <div>
                            <label class="block text-sm font-medium text-gray-500">Yuklangan hujjat</label>
                            <div class="mt-1">
                                <a href="{{ route('student.absence-excuses.download', $excuse->id) }}"
                                   class="inline-flex items-center px-3 py-1.5 text-sm text-indigo-600 bg-indigo-50 rounded-md hover:bg-indigo-100 transition">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                    </svg>
                                    {{ $excuse->file_original_name }}
                                </a>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-500">Yuborilgan sana</label>
                            <p class="mt-1 text-sm text-gray-900">{{ $excuse->created_at->format('d.m.Y H:i') }}</p>
                        </div>

                        @if($excuse->isApproved())
                            <div class="border-t pt-4 mt-4">
                                <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                                    <h4 class="text-sm font-medium text-green-800 mb-2">Tasdiqlangan</h4>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-sm text-green-700">
                                        <div>
                                            <span class="font-medium">Tasdiqlagan:</span> {{ $excuse->reviewed_by_name }}
                                        </div>
                                        <div>
                                            <span class="font-medium">Sana:</span> {{ $excuse->reviewed_at->format('d.m.Y H:i') }}
                                        </div>
                                    </div>
                                    @if($excuse->approved_pdf_path)
                                        <div class="mt-3">
                                            <a href="{{ route('student.absence-excuses.download-pdf', $excuse->id) }}"
                                               class="inline-flex items-center px-4 py-2 bg-green-600 text-white text-sm font-semibold rounded-md hover:bg-green-700 transition">
                                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                                </svg>
                                                PDF hujjatni yuklab olish
                                            </a>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endif

                        @if($excuse->isRejected())
                            <div class="border-t pt-4 mt-4">
                                <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                                    <h4 class="text-sm font-medium text-red-800 mb-2">Rad etilgan</h4>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-sm text-red-700 mb-2">
                                        <div>
                                            <span class="font-medium">Rad etgan:</span> {{ $excuse->reviewed_by_name }}
                                        </div>
                                        <div>
                                            <span class="font-medium">Sana:</span> {{ $excuse->reviewed_at->format('d.m.Y H:i') }}
                                        </div>
                                    </div>
                                    @if($excuse->rejection_reason)
                                        <div class="text-sm text-red-700">
                                            <span class="font-medium">Sabab:</span> {{ $excuse->rejection_reason }}
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endif

                        {{-- Qayta topshirish sanalari --}}
                        @if($excuse->makeups->isNotEmpty())
                            <div class="border-t pt-4 mt-4">
                                <div class="flex items-center justify-between mb-3">
                                    <h4 class="text-sm font-medium text-gray-900">O'tkazib yuborilgan nazoratlar va qayta topshirish</h4>
                                    @if($excuse->makeups->contains(fn($m) => !$m->makeup_date))
                                        <a href="{{ route('student.absence-excuses.schedule-check', $excuse->id) }}"
                                           class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">
                                            Sanalarni tanlash
                                        </a>
                                    @endif
                                </div>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Fan</th>
                                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Nazorat turi</th>
                                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Asl sana</th>
                                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Qayta topshirish</th>
                                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Holat</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            @foreach($excuse->makeups as $makeup)
                                                <tr>
                                                    <td class="px-3 py-2 text-gray-900">{{ $makeup->subject_name }}</td>
                                                    <td class="px-3 py-2">
                                                        @php
                                                            $typeColors = [
                                                                'jn' => 'bg-blue-100 text-blue-800',
                                                                'mt' => 'bg-purple-100 text-purple-800',
                                                                'oski' => 'bg-orange-100 text-orange-800',
                                                                'test' => 'bg-red-100 text-red-800',
                                                            ];
                                                        @endphp
                                                        <span class="px-2 py-0.5 text-xs font-medium rounded-full {{ $typeColors[$makeup->assessment_type] ?? 'bg-gray-100 text-gray-800' }}">
                                                            {{ $makeup->assessment_type_label }}
                                                        </span>
                                                    </td>
                                                    <td class="px-3 py-2 text-gray-500">{{ $makeup->original_date->format('d.m.Y') }}</td>
                                                    <td class="px-3 py-2">
                                                        @if($makeup->makeup_date)
                                                            <span class="font-medium text-indigo-600">{{ $makeup->makeup_date->format('d.m.Y') }}</span>
                                                        @else
                                                            <span class="text-amber-500 italic">Tanlanmagan</span>
                                                        @endif
                                                    </td>
                                                    <td class="px-3 py-2">
                                                        <span class="px-2 py-0.5 text-xs font-medium rounded-full bg-{{ $makeup->status_color }}-100 text-{{ $makeup->status_color }}-800">
                                                            {{ $makeup->status_label }}
                                                        </span>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="mt-6 pt-4 border-t flex items-center justify-between">
                        <a href="{{ route('student.absence-excuses.index') }}"
                           class="text-gray-600 hover:text-gray-800 text-sm">
                            &larr; Orqaga qaytish
                        </a>
                        @if($excuse->makeups->isNotEmpty() && $excuse->makeups->contains(fn($m) => !$m->makeup_date))
                            <a href="{{ route('student.absence-excuses.schedule-check', $excuse->id) }}"
                               class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-md hover:bg-indigo-700 transition">
                                Qayta topshirish sanalarini tanlash
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-student-app-layout>
