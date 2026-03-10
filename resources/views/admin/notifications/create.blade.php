<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('notifications.notifications') }}
        </h2>
    </x-slot>

    <div class="py-4 sm:py-6">
        <div class="max-w-full mx-auto px-2 sm:px-6 lg:px-8">
            @if($errors->any())
            <div class="mb-3 flex items-start gap-2.5 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-sm">
                <svg class="w-4 h-4 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <ul class="space-y-0.5">
                    @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
            @endif

            <div class="bg-white rounded-2xl shadow-sm border border-gray-200/60 overflow-hidden flex" style="min-height: 580px;">
                {{-- Chap panel --}}
                <div class="hidden sm:flex flex-col w-56 border-r border-gray-200/60 bg-gray-50/80 flex-shrink-0">
                    <div class="p-3">
                        <a href="{{ route('admin.notifications.create') }}"
                           class="flex items-center justify-center gap-2 w-full px-4 py-2.5 bg-blue-600 text-white text-sm font-semibold rounded-xl hover:bg-blue-700 transition-colors shadow-sm">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                            {{ __('notifications.compose') }}
                        </a>
                    </div>
                    <nav class="flex-1 px-2 pb-3 space-y-0.5">
                        <a href="{{ route('admin.notifications.index', ['tab' => 'inbox']) }}"
                           class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-sm text-gray-600 hover:bg-gray-100 font-medium transition-colors">
                            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path></svg>
                            <span class="flex-1">{{ __('notifications.inbox') }}</span>
                            @if($unreadCount > 0)
                            <span class="text-[11px] font-semibold bg-blue-600 text-white px-1.5 py-0.5 rounded-full min-w-[20px] text-center">{{ $unreadCount }}</span>
                            @endif
                        </a>
                        <a href="{{ route('admin.notifications.index', ['tab' => 'sent']) }}"
                           class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-sm text-gray-600 hover:bg-gray-100 font-medium transition-colors">
                            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
                            <span class="flex-1">{{ __('notifications.sent') }}</span>
                            <span class="text-xs text-gray-400">{{ $sentCount }}</span>
                        </a>
                        <a href="{{ route('admin.notifications.index', ['tab' => 'drafts']) }}"
                           class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-sm text-gray-600 hover:bg-gray-100 font-medium transition-colors">
                            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                            <span class="flex-1">{{ __('notifications.drafts') }}</span>
                            <span class="text-xs text-gray-400">{{ $draftsCount }}</span>
                        </a>
                    </nav>
                </div>

                {{-- Xabar yozish formasi --}}
                <div class="flex-1 flex flex-col min-w-0" x-data="composeForm()">
                    {{-- Toolbar --}}
                    <div class="flex items-center justify-between px-4 py-2.5 border-b border-gray-100 bg-white">
                        <a href="{{ route('admin.notifications.index') }}"
                           class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-700 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                            {{ __('notifications.back_to_list') }}
                        </a>
                        <span class="text-xs text-gray-400" x-show="selectedIds.length > 0">
                            <span class="font-semibold text-blue-600" x-text="selectedIds.length"></span> {{ __('notifications.selected_count') }}
                        </span>
                    </div>

                    <form method="POST" action="{{ route('admin.notifications.store') }}" class="flex-1 flex flex-col">
                        @csrf

                        <div class="flex-1 overflow-y-auto">
                            {{-- Qabul qiluvchi tanlash --}}
                            <div class="border-b border-gray-100 px-4 sm:px-5 py-3">
                                {{-- Rol filtr + barchasi tanlash + qidiruv — bir qatorda --}}
                                <div class="flex items-center gap-2 mb-2">
                                    <label class="text-sm text-gray-500 font-medium flex-shrink-0">{{ __('notifications.to') }}:</label>
                                    <select x-model="selectedRole" @change="onRoleChange()"
                                            class="border border-gray-200 focus:border-blue-400 focus:ring-1 focus:ring-blue-400 rounded-lg text-xs py-1.5 px-2.5 w-40">
                                        <option value="">{{ __('notifications.all') }} ({{ __('notifications.role') }})</option>
                                        @foreach($roles as $role)
                                        <option value="{{ $role['value'] }}">{{ $role['label'] }}</option>
                                        @endforeach
                                    </select>
                                    <div class="relative flex-1">
                                        <svg class="w-3.5 h-3.5 absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                                        <input type="text" x-model="searchQuery"
                                               placeholder="{{ __('notifications.search_recipients') }}"
                                               class="w-full pl-8 pr-3 py-1.5 text-xs border border-gray-200 rounded-lg bg-gray-50 focus:bg-white focus:border-blue-400 focus:ring-1 focus:ring-blue-400 transition-colors">
                                    </div>
                                    <button type="button" @click="selectAll()"
                                            class="text-xs font-medium px-2.5 py-1.5 rounded-lg transition-colors whitespace-nowrap flex-shrink-0"
                                            :class="allFilteredSelected ? 'bg-red-50 text-red-600 hover:bg-red-100' : 'bg-blue-50 text-blue-600 hover:bg-blue-100'">
                                        <span x-text="allFilteredSelected ? '{{ __('notifications.deselect_all') }}' : '{{ __('notifications.select_all') }}'"></span>
                                    </button>
                                </div>

                                {{-- Tanlangan qabul qiluvchilar (ixcham ko'rinish) --}}
                                <div x-show="selectedIds.length > 0" class="mb-2">
                                    {{-- 5 tagacha chip ko'rsatish, qolganlari "+N ta" --}}
                                    <div class="flex items-center flex-wrap gap-1">
                                        <template x-for="id in selectedIds.slice(0, 5)" :key="id">
                                            <span class="inline-flex items-center gap-0.5 pl-1 pr-1.5 py-0.5 bg-blue-50 border border-blue-200/60 text-blue-700 text-[11px] font-medium rounded-md">
                                                <span class="w-4 h-4 rounded-full flex items-center justify-center text-white text-[8px] font-bold flex-shrink-0"
                                                      :class="['bg-blue-500','bg-green-500','bg-purple-500','bg-orange-500','bg-pink-500','bg-teal-500','bg-indigo-500'][id % 7]"
                                                      x-text="getTeacherName(id).charAt(0).toUpperCase()"></span>
                                                <span x-text="getTeacherName(id)" class="max-w-[100px] truncate"></span>
                                                <button type="button" @click="removeSelected(id)" class="text-blue-400 hover:text-blue-600">
                                                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                                </button>
                                            </span>
                                        </template>
                                        <span x-show="selectedIds.length > 5"
                                              @click="showAllSelected = !showAllSelected"
                                              class="inline-flex items-center px-2 py-0.5 bg-gray-100 text-gray-600 text-[11px] font-medium rounded-md cursor-pointer hover:bg-gray-200 transition-colors">
                                            +<span x-text="selectedIds.length - 5"></span> ta
                                            <svg class="w-3 h-3 ml-0.5 transition-transform" :class="showAllSelected ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                        </span>
                                        <button type="button" x-show="selectedIds.length > 1" @click="selectedIds = []"
                                                class="text-[11px] text-red-400 hover:text-red-600 ml-1">{{ __('notifications.deselect_all') }}</button>
                                    </div>
                                    {{-- Kengaytirilgan ro'yxat --}}
                                    <div x-show="showAllSelected && selectedIds.length > 5" x-collapse
                                         class="flex items-center flex-wrap gap-1 mt-1 max-h-20 overflow-y-auto">
                                        <template x-for="id in selectedIds.slice(5)" :key="'extra_' + id">
                                            <span class="inline-flex items-center gap-0.5 pl-1 pr-1.5 py-0.5 bg-blue-50 border border-blue-200/60 text-blue-700 text-[11px] font-medium rounded-md">
                                                <span class="w-4 h-4 rounded-full flex items-center justify-center text-white text-[8px] font-bold flex-shrink-0"
                                                      :class="['bg-blue-500','bg-green-500','bg-purple-500','bg-orange-500','bg-pink-500','bg-teal-500','bg-indigo-500'][id % 7]"
                                                      x-text="getTeacherName(id).charAt(0).toUpperCase()"></span>
                                                <span x-text="getTeacherName(id)" class="max-w-[100px] truncate"></span>
                                                <button type="button" @click="removeSelected(id)" class="text-blue-400 hover:text-blue-600">
                                                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                                </button>
                                            </span>
                                        </template>
                                    </div>
                                </div>

                                {{-- Xodimlar ro'yxati (ixcham, scroll bilan) --}}
                                <div class="border border-gray-200 rounded-lg overflow-hidden">
                                    <div class="max-h-48 overflow-y-auto divide-y divide-gray-50">
                                        <template x-for="teacher in filteredTeachers" :key="teacher.id">
                                            <label class="flex items-center gap-2 px-3 py-1.5 cursor-pointer transition-colors"
                                                   :class="isSelected(teacher.id) ? 'bg-blue-50/60' : 'hover:bg-gray-50'">
                                                <input type="checkbox" :value="teacher.id" @change="toggle(teacher.id)"
                                                       :checked="isSelected(teacher.id)"
                                                       class="w-3.5 h-3.5 rounded border-gray-300 text-blue-600 focus:ring-blue-500 flex-shrink-0">
                                                <div class="w-6 h-6 rounded-full flex items-center justify-center text-white text-[9px] font-bold flex-shrink-0"
                                                     :class="['bg-blue-500','bg-green-500','bg-purple-500','bg-orange-500','bg-pink-500','bg-teal-500','bg-indigo-500'][teacher.id % 7]">
                                                    <span x-text="teacher.name.charAt(0).toUpperCase()"></span>
                                                </div>
                                                <span class="text-sm text-gray-800 truncate flex-1" x-text="teacher.name"></span>
                                                <span class="text-[10px] text-gray-400 truncate max-w-[140px] flex-shrink-0" x-text="teacher.position || teacher.roles.join(', ') || ''"></span>
                                            </label>
                                        </template>
                                    </div>
                                    <div x-show="filteredTeachers.length === 0" class="px-4 py-3 text-center text-xs text-gray-400">
                                        {{ __('notifications.no_messages') }}
                                    </div>
                                    {{-- Ro'yxat sonini ko'rsatish --}}
                                    <div class="flex items-center justify-between px-3 py-1.5 bg-gray-50 border-t border-gray-100 text-[11px] text-gray-400">
                                        <span x-text="filteredTeachers.length + ' ta xodim'"></span>
                                        <span x-show="selectedIds.length > 0" class="text-blue-600 font-medium" x-text="selectedIds.length + ' ta tanlandi'"></span>
                                    </div>
                                </div>
                            </div>

                            {{-- Mavzu --}}
                            <div class="flex items-center gap-3 border-b border-gray-100 px-4 sm:px-5 py-2.5">
                                <label class="text-sm text-gray-500 font-medium w-16 flex-shrink-0">{{ __('notifications.subject') }}</label>
                                <input type="text" name="subject" value="{{ old('subject') }}"
                                       class="flex-1 border-0 focus:ring-0 text-sm text-gray-900 py-1 px-0 placeholder-gray-400"
                                       placeholder="{{ __('notifications.subject_placeholder') }}" required>
                            </div>

                            {{-- Matn --}}
                            <div class="px-4 sm:px-5 pt-3 pb-2">
                                <textarea name="body" rows="10"
                                          class="w-full border-0 focus:ring-0 text-sm text-gray-800 leading-relaxed resize-none p-0 placeholder-gray-400"
                                          placeholder="{{ __('notifications.body_placeholder') }}">{{ old('body') }}</textarea>
                            </div>
                        </div>

                        {{-- Hidden inputs --}}
                        <template x-for="id in selectedIds" :key="'input_' + id">
                            <input type="hidden" name="recipient_ids[]" :value="id">
                        </template>

                        {{-- Amallar --}}
                        <div class="px-4 sm:px-5 py-3 border-t border-gray-100 flex items-center gap-2 bg-white">
                            <button type="submit" :disabled="selectedIds.length === 0"
                                    class="inline-flex items-center gap-1.5 px-5 py-2 text-sm font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors shadow-sm disabled:opacity-40 disabled:cursor-not-allowed">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
                                {{ __('notifications.send') }}
                                <span x-show="selectedIds.length > 1" class="ml-0.5 text-blue-200" x-text="'(' + selectedIds.length + ')'"></span>
                            </button>
                            <button type="submit" name="save_draft" value="1" :disabled="selectedIds.length === 0"
                                    class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg transition-colors disabled:opacity-40 disabled:cursor-not-allowed">
                                {{ __('notifications.save_draft') }}
                            </button>
                            <div class="flex-1"></div>
                            <a href="{{ route('admin.notifications.index') }}"
                               class="p-2 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition-colors" title="{{ __('notifications.delete') }}">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        function composeForm() {
            return {
                allTeachers: @json($teachersJson),
                selectedRole: '',
                searchQuery: '',
                selectedIds: [],
                showAllSelected: false,

                get filteredTeachers() {
                    var role = this.selectedRole;
                    var query = this.searchQuery.toLowerCase();
                    return this.allTeachers.filter(function(t) {
                        var matchRole = !role || t.roles.indexOf(role) !== -1;
                        var matchSearch = !query || t.name.toLowerCase().indexOf(query) !== -1 || (t.position && t.position.toLowerCase().indexOf(query) !== -1);
                        return matchRole && matchSearch;
                    });
                },

                get allFilteredSelected() {
                    var self = this;
                    var filtered = this.filteredTeachers;
                    if (filtered.length === 0) return false;
                    return filtered.every(function(t) { return self.selectedIds.indexOf(t.id) !== -1; });
                },

                onRoleChange() {
                    this.searchQuery = '';
                },

                selectAll() {
                    var self = this;
                    var filteredIds = this.filteredTeachers.map(function(t) { return t.id; });
                    if (this.allFilteredSelected) {
                        this.selectedIds = this.selectedIds.filter(function(id) { return filteredIds.indexOf(id) === -1; });
                    } else {
                        filteredIds.forEach(function(id) {
                            if (self.selectedIds.indexOf(id) === -1) {
                                self.selectedIds.push(id);
                            }
                        });
                    }
                },

                toggle(id) {
                    var idx = this.selectedIds.indexOf(id);
                    if (idx === -1) {
                        this.selectedIds.push(id);
                    } else {
                        this.selectedIds.splice(idx, 1);
                    }
                },

                isSelected(id) {
                    return this.selectedIds.indexOf(id) !== -1;
                },

                removeSelected(id) {
                    this.selectedIds = this.selectedIds.filter(function(i) { return i !== id; });
                },

                getTeacherName(id) {
                    var t = this.allTeachers.find(function(t) { return t.id === id; });
                    return t ? t.name : '';
                }
            }
        }
    </script>
    @endpush
</x-app-layout>
