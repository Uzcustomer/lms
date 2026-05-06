<x-teacher-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-2 flex-wrap">
            <a href="{{ route('admin.retake-groups.index') }}" class="text-sm text-blue-600 hover:underline">
                ← {{ __("Guruhlar") }}
            </a>
            <span class="text-gray-300">/</span>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __("Tarix — arxivlangan guruhlar") }}
            </h2>
        </div>
    </x-slot>

    <div class="py-6 px-4 sm:px-6 lg:px-8 w-full"
         x-data="{ selected: [], allChecked: false,
                    toggleAll(e) {
                        this.selected = e.target.checked ? @js($groups->pluck('id')->toArray()) : [];
                        this.allChecked = e.target.checked;
                    } }">
        @if(session('success'))
            <div class="bg-green-50 border border-green-200 rounded-lg p-3 mb-4 text-sm text-green-800">
                {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="bg-red-50 border border-red-200 rounded-lg p-3 mb-4 text-sm text-red-800">
                <ul class="list-disc list-inside">
                    @foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach
                </ul>
            </div>
        @endif

        @if(!$groups->isEmpty() && $canForceDelete)
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-3 mb-3 flex items-center justify-between flex-wrap gap-3">
                <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                    <input type="checkbox" :checked="allChecked" @change="toggleAll($event)"
                           class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                    <span>{{ __("Hammasini tanlash") }}</span>
                    <span class="text-xs text-gray-500" x-show="selected.length > 0">
                        (<span x-text="selected.length"></span> {{ __("ta tanlangan") }})
                    </span>
                </label>
                <form method="POST"
                      action="{{ route('admin.retake-groups.bulk-force-delete') }}"
                      onsubmit="return confirm('{{ __("Tanlangan guruhlarni butunlay o'chirishni tasdiqlaysizmi? Tarixda qolmaydi.") }}')">
                    @csrf
                    <template x-for="id in selected" :key="id">
                        <input type="hidden" name="group_ids[]" :value="id">
                    </template>
                    <button type="submit"
                            :disabled="selected.length === 0"
                            :class="selected.length === 0 ? 'bg-gray-200 text-gray-400 cursor-not-allowed' : 'bg-rose-700 text-white hover:bg-rose-800 ring-2 ring-rose-200'"
                            class="px-4 py-2 text-sm font-bold rounded-lg">
                        💀 {{ __("Tanlanganlarni butunlay o'chirish") }}
                        <span x-show="selected.length > 0">(<span x-text="selected.length"></span>)</span>
                    </button>
                </form>
            </div>
        @endif

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            @if($groups->isEmpty())
                <div class="p-10 text-center text-gray-500 text-sm">
                    {{ __("Tarixda hech qanday guruh yo'q") }}
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100">
                        <thead class="bg-gray-50">
                        <tr>
                            @if($canForceDelete)
                                <th class="px-3 py-2 w-10"></th>
                            @endif
                            <th class="px-3 py-2 text-left text-[11px] font-medium text-gray-500 uppercase">{{ __("Nom") }}</th>
                            <th class="px-3 py-2 text-left text-[11px] font-medium text-gray-500 uppercase">{{ __("Fan") }}</th>
                            <th class="px-3 py-2 text-left text-[11px] font-medium text-gray-500 uppercase">{{ __("O'qituvchi") }}</th>
                            <th class="px-3 py-2 text-left text-[11px] font-medium text-gray-500 uppercase">{{ __("Talabalar") }}</th>
                            <th class="px-3 py-2 text-left text-[11px] font-medium text-gray-500 uppercase">{{ __("Arxivga ko'chirilgan") }}</th>
                            <th class="px-3 py-2 text-right text-[11px] font-medium text-gray-500 uppercase"></th>
                        </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-100">
                        @foreach($groups as $g)
                            <tr class="bg-gray-50/30">
                                @if($canForceDelete)
                                    <td class="px-3 py-2.5 w-10">
                                        <input type="checkbox"
                                               :checked="selected.includes({{ $g->id }})"
                                               @change="if ($event.target.checked) {
                                                    if (!selected.includes({{ $g->id }})) selected.push({{ $g->id }});
                                               } else {
                                                    selected = selected.filter(x => x !== {{ $g->id }});
                                                    allChecked = false;
                                               }"
                                               class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                    </td>
                                @endif
                                <td class="px-3 py-2.5 text-sm text-gray-700">{{ $g->name }}</td>
                                <td class="px-3 py-2.5 text-sm text-gray-700">
                                    {{ $g->subject_name }}
                                    <span class="block text-[11px] text-gray-500">{{ $g->semester_name }}</span>
                                </td>
                                <td class="px-3 py-2.5 text-sm text-gray-700">{{ $g->teacher_name ?? '—' }}</td>
                                <td class="px-3 py-2.5 text-sm text-gray-700">{{ $g->students_count }}</td>
                                <td class="px-3 py-2.5 text-xs text-gray-500">
                                    {{ $g->deleted_at?->format('Y-m-d H:i') }}
                                </td>
                                <td class="px-3 py-2.5 text-right whitespace-nowrap">
                                    <form method="POST" action="{{ route('admin.retake-groups.restore', $g->id) }}" class="inline">
                                        @csrf
                                        <button type="submit"
                                                class="px-3 py-1.5 text-xs bg-blue-50 text-blue-700 rounded hover:bg-blue-100">
                                            ↩ {{ __("Tiklash") }}
                                        </button>
                                    </form>
                                    @if($canForceDelete)
                                        <form method="POST"
                                              action="{{ route('admin.retake-groups.force-destroy', $g->id) }}"
                                              onsubmit="return confirm('{{ __("Guruhni butunlay (qayta tiklab bo'lmaydigan tarzda) o'chirishni tasdiqlaysizmi?") }}')"
                                              class="inline">
                                            @csrf @method('DELETE')
                                            <button type="submit"
                                                    class="px-3 py-1.5 text-xs bg-red-50 text-red-700 rounded hover:bg-red-100">
                                                ✗ {{ __("O'chirish") }}
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-teacher-app-layout>
