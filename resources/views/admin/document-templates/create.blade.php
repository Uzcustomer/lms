<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.document-templates.index') }}" class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
            </a>
            <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">Yangi shablon yuklash</h2>
        </div>
    </x-slot>

    <div class="py-6 px-4 sm:px-6 lg:px-8 max-w-4xl">

        @if($errors->any())
            <div class="mb-4 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg text-red-700 dark:text-red-300">
                <ul class="list-disc list-inside text-sm">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.document-templates.store') }}" enctype="multipart/form-data"
              class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 space-y-6">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Shablon nomi *</label>
                    <input type="text" name="name" value="{{ old('name') }}" required
                           class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                           placeholder="Masalan: Sababli ariza farmoyishi">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Shablon turi *</label>
                    <select name="type" id="templateType" required
                            class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                        <option value="">Tanlang...</option>
                        @foreach($types as $key => $label)
                            <option value="{{ $key }}" {{ old('type') === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Izoh</label>
                <textarea name="description" rows="2"
                          class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                          placeholder="Shablon haqida qisqacha izoh...">{{ old('description') }}</textarea>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Word fayl (.docx) *</label>
                <input type="file" name="file" accept=".docx" required
                       class="w-full text-sm text-gray-500 dark:text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 dark:file:bg-blue-900/30 dark:file:text-blue-300">
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Faqat .docx formatdagi fayllar. Placeholder'larni quyidagi ro'yxatdan foydalaning.</p>
            </div>

            <div class="flex items-center">
                <label class="flex items-center cursor-pointer">
                    <input type="checkbox" name="is_active" value="1" {{ old('is_active') ? 'checked' : '' }}
                           class="rounded border-gray-300 dark:border-gray-600 text-blue-600 focus:ring-blue-500">
                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Darhol faollashtirish (shu turdagi boshqa shablonlar nofaol bo'ladi)</span>
                </label>
            </div>

            {{-- Placeholder'lar ro'yxati --}}
            <div id="placeholdersSection" class="hidden">
                <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Mavjud placeholder'lar</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">Word shablonda quyidagi placeholder'larni ishlatishingiz mumkin. Ularni shablonning kerakli joyiga yozing:</p>
                <div id="placeholdersList" class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-4">
                    <table class="w-full text-sm">
                        <thead>
                            <tr>
                                <th class="text-left text-xs font-medium text-gray-500 dark:text-gray-400 pb-2">Placeholder</th>
                                <th class="text-left text-xs font-medium text-gray-500 dark:text-gray-400 pb-2">Tavsif</th>
                            </tr>
                        </thead>
                        <tbody id="placeholdersBody"></tbody>
                    </table>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" class="px-6 py-2.5 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition">
                    Yuklash
                </button>
                <a href="{{ route('admin.document-templates.index') }}" class="px-6 py-2.5 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 text-sm rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition">
                    Bekor qilish
                </a>
            </div>
        </form>
    </div>

    @push('scripts')
    <script>
        const typePlaceholders = @json(\App\Models\DocumentTemplate::TYPES);

        document.getElementById('templateType').addEventListener('change', function() {
            const section = document.getElementById('placeholdersSection');
            const tbody = document.getElementById('placeholdersBody');
            const type = this.value;

            if (type && typePlaceholders[type]) {
                tbody.innerHTML = '';
                const placeholders = typePlaceholders[type].placeholders;
                for (const [key, desc] of Object.entries(placeholders)) {
                    tbody.innerHTML += `<tr class="border-t border-gray-200 dark:border-gray-600">
                        <td class="py-2 font-mono text-xs text-blue-600 dark:text-blue-400">${key}</td>
                        <td class="py-2 text-gray-600 dark:text-gray-300 text-xs">${desc}</td>
                    </tr>`;
                }
                section.classList.remove('hidden');
            } else {
                section.classList.add('hidden');
            }
        });

        // Sahifa yuklanganda trigger
        if (document.getElementById('templateType').value) {
            document.getElementById('templateType').dispatchEvent(new Event('change'));
        }
    </script>
    @endpush
</x-app-layout>
