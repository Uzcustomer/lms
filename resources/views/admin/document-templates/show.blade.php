<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <a href="{{ route('admin.document-templates.index') }}" class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                </a>
                <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">{{ $documentTemplate->name }}</h2>
                @if($documentTemplate->is_active)
                    <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300">Faol</span>
                @endif
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.document-templates.download', $documentTemplate) }}"
                   class="inline-flex items-center px-4 py-2 bg-green-600 text-white text-sm rounded-lg hover:bg-green-700 transition">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Yuklab olish
                </a>
                <a href="{{ route('admin.document-templates.edit', $documentTemplate) }}"
                   class="inline-flex items-center px-4 py-2 bg-yellow-500 text-white text-sm rounded-lg hover:bg-yellow-600 transition">
                    Tahrirlash
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6 px-4 sm:px-6 lg:px-8 max-w-4xl">

        {{-- Ma'lumotlar --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 mb-6">
            <div class="grid grid-cols-2 gap-4 text-sm">
                <div>
                    <span class="text-gray-500 dark:text-gray-400">Turi:</span>
                    <span class="ml-2 font-medium text-gray-800 dark:text-gray-200">
                        {{ \App\Models\DocumentTemplate::typeLabels()[$documentTemplate->type] ?? $documentTemplate->type }}
                    </span>
                </div>
                <div>
                    <span class="text-gray-500 dark:text-gray-400">Fayl:</span>
                    <span class="ml-2 font-medium text-gray-800 dark:text-gray-200">{{ $documentTemplate->file_original_name }}</span>
                </div>
                <div>
                    <span class="text-gray-500 dark:text-gray-400">Yaratilgan:</span>
                    <span class="ml-2 text-gray-800 dark:text-gray-200">{{ $documentTemplate->created_at->format('d.m.Y H:i') }}</span>
                </div>
                <div>
                    <span class="text-gray-500 dark:text-gray-400">Yangilangan:</span>
                    <span class="ml-2 text-gray-800 dark:text-gray-200">{{ $documentTemplate->updated_at->format('d.m.Y H:i') }}</span>
                </div>
                @if($documentTemplate->description)
                    <div class="col-span-2">
                        <span class="text-gray-500 dark:text-gray-400">Izoh:</span>
                        <p class="mt-1 text-gray-800 dark:text-gray-200">{{ $documentTemplate->description }}</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- Placeholder'lar ro'yxati --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-1">Placeholder'lar</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Quyidagi placeholder'larni Word shablonda ishlatishingiz mumkin. Ular avtomatik almashtiriladi.</p>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-700/50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Placeholder</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Tavsif</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($placeholders as $key => $description)
                            <tr>
                                <td class="px-4 py-2.5">
                                    <code class="px-2 py-0.5 bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 rounded text-xs font-mono">{{ $key }}</code>
                                </td>
                                <td class="px-4 py-2.5 text-gray-600 dark:text-gray-300">{{ $description }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4 p-3 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg">
                <p class="text-xs text-yellow-700 dark:text-yellow-300">
                    <strong>Eslatma:</strong> <code class="bg-yellow-100 dark:bg-yellow-900/50 px-1 rounded">${qr_code}</code> placeholder o'rniga avtomatik QR kod rasmi qo'yiladi.
                    Boshqa placeholder'lar matn sifatida almashtiriladi.
                    Word shablonda placeholder'larni aynan shu ko'rinishda yozing: <code class="bg-yellow-100 dark:bg-yellow-900/50 px-1 rounded">${placeholder_nomi}</code>
                </p>
            </div>
        </div>
    </div>
</x-app-layout>
