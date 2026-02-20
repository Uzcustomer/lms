<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">Hujjat shablonlari</h2>
            <a href="{{ route('admin.document-templates.create') }}"
               class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Yangi shablon
            </a>
        </div>
    </x-slot>

    <div class="py-6 px-4 sm:px-6 lg:px-8">

        @if(session('success'))
            <div class="mb-4 p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg text-green-700 dark:text-green-300">
                {{ session('success') }}
            </div>
        @endif

        {{-- Filter --}}
        <div class="mb-6 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
            <form method="GET" class="flex items-center gap-4">
                <select name="type" class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm">
                    <option value="">Barcha turlar</option>
                    @foreach($types as $key => $label)
                        <option value="{{ $key }}" {{ request('type') === $key ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                <button type="submit" class="px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 text-sm rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition">
                    Filtrlash
                </button>
                @if(request()->hasAny(['type']))
                    <a href="{{ route('admin.document-templates.index') }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400">Tozalash</a>
                @endif
            </form>
        </div>

        {{-- Jadval --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-700/50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">#</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Nomi</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Turi</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Fayl</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Holati</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Yaratilgan</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Amallar</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($templates as $template)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $template->id }}</td>
                            <td class="px-4 py-3">
                                <a href="{{ route('admin.document-templates.show', $template) }}" class="text-blue-600 dark:text-blue-400 hover:underline font-medium">
                                    {{ $template->name }}
                                </a>
                                @if($template->description)
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ Str::limit($template->description, 60) }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300">
                                    {{ \App\Models\DocumentTemplate::typeLabels()[$template->type] ?? $template->type }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <a href="{{ route('admin.document-templates.download', $template) }}" class="text-blue-600 dark:text-blue-400 hover:underline text-xs">
                                    {{ $template->file_original_name }}
                                </a>
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if($template->is_active)
                                    <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300">Faol</span>
                                @else
                                    <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full bg-gray-100 dark:bg-gray-900/30 text-gray-500 dark:text-gray-400">Nofaol</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400 text-xs">
                                {{ $template->created_at->format('d.m.Y H:i') }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                <div class="flex items-center justify-center gap-2">
                                    @if(!$template->is_active)
                                        <form method="POST" action="{{ route('admin.document-templates.activate', $template) }}">
                                            @csrf
                                            <button type="submit" class="text-green-600 hover:text-green-800 dark:text-green-400 text-xs font-medium" title="Faollashtirish">
                                                Faollashtirish
                                            </button>
                                        </form>
                                    @endif
                                    <a href="{{ route('admin.document-templates.edit', $template) }}" class="text-yellow-600 hover:text-yellow-800 dark:text-yellow-400" title="Tahrirlash">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                    </a>
                                    <form method="POST" action="{{ route('admin.document-templates.destroy', $template) }}" onsubmit="return confirm('Rostdan ham o\'chirmoqchimisiz?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:text-red-800 dark:text-red-400" title="O'chirish">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                                Hali shablon yuklanmagan.
                                <a href="{{ route('admin.document-templates.create') }}" class="text-blue-600 hover:underline">Birinchi shablonni yuklang</a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            @if($templates->hasPages())
                <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700">
                    {{ $templates->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
