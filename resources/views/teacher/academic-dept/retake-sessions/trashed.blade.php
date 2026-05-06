<x-teacher-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-2 flex-wrap">
            <a href="{{ route('admin.retake-sessions.index') }}" class="text-sm text-blue-600 hover:underline">
                ← {{ __("Sessiyalar") }}
            </a>
            <span class="text-gray-300">/</span>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __("Tarix — arxivlangan sessiyalar") }}
            </h2>
        </div>
    </x-slot>

    <div class="py-6 px-4 sm:px-6 lg:px-8 w-full"
         x-data="{ selected: [], allChecked: false,
                    toggleAll(e) {
                        this.selected = e.target.checked ? @js($sessions->pluck('id')->toArray()) : [];
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

        @if(!$sessions->isEmpty() && $canForceDelete)
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
                      action="{{ route('admin.retake-sessions.bulk-force-delete') }}"
                      onsubmit="return confirm('{{ __("Tanlangan sessiyalarni butunlay o'chirishni tasdiqlaysizmi? Tarixda qolmaydi.") }}')">
                    @csrf
                    <template x-for="id in selected" :key="id">
                        <input type="hidden" name="session_ids[]" :value="id">
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
            @if($sessions->isEmpty())
                <div class="p-10 text-center text-gray-500 text-sm">
                    {{ __("Tarixda hech qanday sessiya yo'q") }}
                </div>
            @else
                <div class="divide-y divide-gray-100">
                    @foreach($sessions as $session)
                        <div class="p-4 flex items-center justify-between flex-wrap gap-3 bg-gray-50/50">
                            <div class="flex items-center gap-3 flex-1 min-w-0">
                                @if($canForceDelete)
                                    <input type="checkbox"
                                           :checked="selected.includes({{ $session->id }})"
                                           @change="if ($event.target.checked) {
                                                if (!selected.includes({{ $session->id }})) selected.push({{ $session->id }});
                                           } else {
                                                selected = selected.filter(x => x !== {{ $session->id }});
                                                allChecked = false;
                                           }"
                                           class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                @endif
                                <div class="w-10 h-10 rounded-full flex items-center justify-center bg-gray-200 text-gray-500">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-gray-700 truncate">{{ $session->name }}</p>
                                    <div class="text-xs text-gray-500 mt-0.5">
                                        {{ $session->windows_count ?? 0 }} {{ __("ta oyna") }}
                                        @if($session->created_by_name)
                                            · {{ $session->created_by_name }}
                                        @endif
                                        · {{ __("Yaratilgan") }}: {{ $session->created_at->format('Y-m-d') }}
                                        · {{ __("Arxivga ko'chirilgan") }}: {{ $session->deleted_at?->format('Y-m-d H:i') }}
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <form method="POST" action="{{ route('admin.retake-sessions.restore', $session->id) }}" class="inline">
                                    @csrf
                                    <button type="submit"
                                            class="px-3 py-1.5 text-xs bg-blue-50 text-blue-700 rounded hover:bg-blue-100">
                                        ↩ {{ __("Tiklash") }}
                                    </button>
                                </form>
                                @if($canForceDelete)
                                    <form method="POST"
                                          action="{{ route('admin.retake-sessions.force-destroy', $session->id) }}"
                                          onsubmit="return confirm('{{ __("Sessiyani butunlay (qayta tiklab bo'lmaydigan tarzda) o'chirishni tasdiqlaysizmi?") }}')"
                                          class="inline">
                                        @csrf @method('DELETE')
                                        <button type="submit"
                                                class="px-3 py-1.5 text-xs bg-red-50 text-red-700 rounded hover:bg-red-100">
                                            ✗ {{ __("O'chirish") }}
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-teacher-app-layout>
