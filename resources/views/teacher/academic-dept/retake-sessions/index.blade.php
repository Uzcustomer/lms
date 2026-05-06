<x-teacher-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __("Qayta o'qish — Sessiyalar") }}
        </h2>
    </x-slot>

    @php
        $deletableIds = array_values(array_diff(
            $sessions->pluck('id')->all(),
            $sessionsWithApps ?? []
        ));
    @endphp
    <div class="py-6 px-4 sm:px-6 lg:px-8 w-full"
         x-data="{
            showCreate: false,
            selected: [],
            get deletableIds() { return @js($deletableIds); },
            get allChecked() { return this.deletableIds.length > 0 && this.deletableIds.every(id => this.selected.includes(id)); },
            toggleAll(ev) {
                if (ev.target.checked) {
                    this.deletableIds.forEach(id => { if (!this.selected.includes(id)) this.selected.push(id); });
                } else {
                    this.deletableIds.forEach(id => {
                        const idx = this.selected.indexOf(id);
                        if (idx > -1) this.selected.splice(idx, 1);
                    });
                }
            },
            confirmBulkDelete(ev) {
                if (this.selected.length === 0) { ev.preventDefault(); return; }
                if (!confirm(this.selected.length + ' ta sessiyani o\'chirishni tasdiqlaysizmi? Bu amal qaytarib bo\'lmaydi.')) {
                    ev.preventDefault();
                }
            }
         }">

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

        <div class="flex justify-between items-center mb-4 flex-wrap gap-2">
            <p class="text-sm text-gray-500">
                {{ __("Har o'quv yili / muddat uchun alohida sessiya yarating va uning ichida fakultet/kurs/yo'nalish bo'yicha oynalar oching") }}
            </p>
            <div class="flex gap-2">
                <a href="{{ route('admin.retake-sessions.trashed') }}"
                   class="px-4 py-2 text-sm bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">
                    📦 {{ __("Tarix") }}
                </a>
                <button type="button"
                        @click="showCreate = true"
                        class="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                    + {{ __("Yangi sessiya") }}
                </button>
            </div>
        </div>

        {{-- Bulk delete panel --}}
        @if(!$sessions->isEmpty() && !empty($deletableIds))
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-3 mb-3 flex items-center justify-between flex-wrap gap-3">
                <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                    <input type="checkbox"
                           :checked="allChecked"
                           @change="toggleAll($event)"
                           class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                    <span>{{ __("Arizasiz sessiyalarning barchasini tanlash") }}</span>
                    <span class="text-xs text-gray-500" x-show="selected.length > 0">
                        (<span x-text="selected.length"></span> {{ __("ta tanlangan") }})
                    </span>
                </label>

                <form method="POST"
                      action="{{ route('admin.retake-sessions.bulk-delete') }}"
                      @submit="confirmBulkDelete($event)">
                    @csrf
                    <template x-for="id in selected" :key="id">
                        <input type="hidden" name="session_ids[]" :value="id">
                    </template>
                    <button type="submit"
                            :disabled="selected.length === 0"
                            :class="selected.length === 0 ? 'bg-gray-200 text-gray-400 cursor-not-allowed' : 'bg-red-600 text-white hover:bg-red-700'"
                            class="px-4 py-2 text-sm font-medium rounded-lg">
                        {{ __("Tanlanganlarni o'chirish") }}
                        <span x-show="selected.length > 0">(<span x-text="selected.length"></span>)</span>
                    </button>
                </form>
            </div>
        @endif

        {{-- Sessiyalar ro'yxati --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            @if($sessions->isEmpty())
                <div class="p-10 text-center text-gray-500 text-sm">
                    {{ __("Hali sessiya yaratilmagan. Yuqoridagi tugma orqali yarating.") }}
                </div>
            @else
                <div class="divide-y divide-gray-100">
                    @foreach($sessions as $session)
                        @php $isDeletable = !in_array($session->id, $sessionsWithApps ?? [], true); @endphp
                        <div class="p-4 hover:bg-gray-50 transition flex items-center justify-between flex-wrap gap-3">
                            <div class="flex items-center gap-3 flex-1 min-w-0">
                                @if($isDeletable)
                                    <label class="flex items-center pt-1 cursor-pointer">
                                        <input type="checkbox"
                                               :checked="selected.includes({{ $session->id }})"
                                               @change="if ($event.target.checked) {
                                                    if (!selected.includes({{ $session->id }})) selected.push({{ $session->id }});
                                               } else {
                                                    const idx = selected.indexOf({{ $session->id }});
                                                    if (idx > -1) selected.splice(idx, 1);
                                               }"
                                               class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                    </label>
                                @else
                                    <div class="w-4"></div>
                                @endif
                                <div class="w-10 h-10 rounded-full flex items-center justify-center
                                            {{ $session->is_closed ? 'bg-gray-200 text-gray-500' : 'bg-green-100 text-green-700' }}">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5a2 2 0 00-2 2v6a2 2 0 002 2h14a2 2 0 002-2v-6a2 2 0 00-2-2zM7 11V7a5 5 0 0110 0v4"/>
                                    </svg>
                                </div>
                                <div class="min-w-0">
                                    <a href="{{ route('admin.retake-sessions.show', $session->id) }}"
                                       class="text-sm font-semibold text-gray-900 hover:text-blue-600 truncate block">
                                        {{ $session->name }}
                                    </a>
                                    <div class="text-xs text-gray-500 mt-0.5">
                                        {{ $session->windows_count ?? 0 }} {{ __("ta oyna") }}
                                        @if($session->created_by_name)
                                            · {{ $session->created_by_name }}
                                        @endif
                                        · {{ $session->created_at->format('Y-m-d') }}
                                        @if($session->is_closed)
                                            <span class="inline-block ml-2 px-2 py-0.5 text-[10px] font-medium bg-gray-200 text-gray-700 rounded-full">
                                                {{ __("Yopilgan") }}
                                            </span>
                                        @else
                                            <span class="inline-block ml-2 px-2 py-0.5 text-[10px] font-medium bg-green-100 text-green-800 rounded-full">
                                                {{ __("Ochiq") }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <a href="{{ route('admin.retake-sessions.show', $session->id) }}"
                                   class="px-3 py-1.5 text-xs bg-blue-50 text-blue-700 rounded hover:bg-blue-100">
                                    {{ __("Oynalar") }}
                                </a>
                                @if(!$session->is_closed)
                                    <form method="POST"
                                          action="{{ route('admin.retake-sessions.close', $session->id) }}"
                                          onsubmit="return confirm('{{ __("Sessiyani yopishni tasdiqlaysizmi? Yangi oynalar ochib bo'lmaydi.") }}')"
                                          class="inline">
                                        @csrf
                                        <button type="submit"
                                                class="px-3 py-1.5 text-xs bg-gray-100 text-gray-700 rounded hover:bg-gray-200">
                                            {{ __("Yopish") }}
                                        </button>
                                    </form>
                                @endif
                                @if($isDeletable)
                                    <form method="POST"
                                          action="{{ route('admin.retake-sessions.destroy', $session->id) }}"
                                          onsubmit="return confirm('{{ __("Sessiyani butunlay o'chirishni tasdiqlaysizmi? Bu amal qaytarib bo'lmaydi.") }}')"
                                          class="inline">
                                        @csrf @method('DELETE')
                                        <button type="submit"
                                                class="px-3 py-1.5 text-xs bg-red-50 text-red-700 rounded hover:bg-red-100">
                                            {{ __("O'chirish") }}
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Create modal --}}
        <div x-show="showCreate"
             x-cloak
             class="fixed inset-0 z-50 overflow-y-auto"
             x-transition:enter="ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             @keydown.escape.window="showCreate = false">
            <div class="flex min-h-screen items-center justify-center p-4">
                {{-- Backdrop --}}
                <div class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm" @click="showCreate = false"></div>

                {{-- Dialog --}}
                <div class="relative bg-white rounded-2xl shadow-2xl max-w-md w-full overflow-hidden z-10"
                     x-transition:enter="ease-out duration-200"
                     x-transition:enter-start="opacity-0 scale-95 translate-y-4"
                     x-transition:enter-end="opacity-100 scale-100 translate-y-0">

                    {{-- Header --}}
                    <div class="px-6 pt-6 pb-4 border-b border-gray-100 flex items-start justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-blue-100 text-blue-600 flex items-center justify-center flex-shrink-0">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-base font-semibold text-gray-900 leading-tight">
                                    {{ __("Yangi sessiya yaratish") }}
                                </h3>
                                <p class="text-xs text-gray-500 mt-0.5">
                                    {{ __("Bir o'quv yili / muddat uchun konteyner") }}
                                </p>
                            </div>
                        </div>
                        <button type="button"
                                @click="showCreate = false"
                                class="text-gray-400 hover:text-gray-600 transition p-1 rounded-lg hover:bg-gray-100">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <form method="POST" action="{{ route('admin.retake-sessions.store') }}">
                        @csrf

                        {{-- Body --}}
                        <div class="px-6 py-5 space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-800 mb-1.5">
                                    {{ __("Sessiya nomi") }} <span class="text-red-500">*</span>
                                </label>
                                <input type="text"
                                       name="name"
                                       required
                                       maxlength="255"
                                       autofocus
                                       placeholder="{{ __("Masalan: 2026-2027 Bahor semestri") }}"
                                       class="w-full px-3.5 py-2.5 text-sm border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition placeholder:text-gray-400">
                                <p class="text-[11px] text-gray-500 mt-1.5">
                                    {{ __("Sessiyani keyingi o'quv yilida farqlash uchun aniq nom yozing") }}
                                </p>
                            </div>

                            <div class="bg-blue-50 border border-blue-100 rounded-lg p-3 flex gap-2.5">
                                <svg class="w-4 h-4 text-blue-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <p class="text-[11px] text-blue-900 leading-relaxed">
                                    {{ __("Sessiya yaratilgandan so'ng ichiga fakultet/kurs/yo'nalish kesimida bosqichma-bosqich oynalar ochishingiz mumkin.") }}
                                </p>
                            </div>
                        </div>

                        {{-- Footer --}}
                        <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex items-center justify-end gap-2">
                            <button type="button"
                                    @click="showCreate = false"
                                    class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                                {{ __("Bekor qilish") }}
                            </button>
                            <button type="submit"
                                    class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition shadow-sm">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                </svg>
                                {{ __("Yaratish") }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-teacher-app-layout>
