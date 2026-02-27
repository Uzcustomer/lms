<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('notifications.notifications') }}
        </h2>
    </x-slot>

    <div class="py-4 sm:py-6">
        <div class="max-w-full mx-auto px-2 sm:px-6 lg:px-8">
            @if($errors->any())
            <div class="mb-3 flex items-start gap-2.5 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">
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

            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden flex" style="min-height: 520px;">
                <!-- Chap panel -->
                <div class="hidden sm:flex flex-col w-52 border-r border-gray-200 bg-gray-50/80 flex-shrink-0">
                    <div class="p-3">
                        <a href="{{ route('admin.notifications.create') }}"
                           class="flex items-center justify-center gap-2 w-full px-4 py-2.5 bg-blue-600 text-white text-sm font-semibold rounded-lg hover:bg-blue-700 transition-colors shadow-sm">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path>
                            </svg>
                            {{ __('notifications.compose') }}
                        </a>
                    </div>
                    <nav class="flex-1 px-2 pb-3 space-y-0.5">
                        <a href="{{ route('admin.notifications.index', ['tab' => 'inbox']) }}"
                           class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-sm transition-colors text-gray-600 hover:bg-gray-100 font-medium">
                            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                            </svg>
                            <span class="flex-1">{{ __('notifications.inbox') }}</span>
                            @if($unreadCount > 0)
                            <span class="text-xs font-bold text-gray-500">{{ $unreadCount }}</span>
                            @endif
                        </a>
                        <a href="{{ route('admin.notifications.index', ['tab' => 'sent']) }}"
                           class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-sm transition-colors text-gray-600 hover:bg-gray-100 font-medium">
                            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                            </svg>
                            <span class="flex-1">{{ __('notifications.sent') }}</span>
                            <span class="text-xs text-gray-400">{{ $sentCount }}</span>
                        </a>
                        <a href="{{ route('admin.notifications.index', ['tab' => 'drafts']) }}"
                           class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-sm transition-colors text-gray-600 hover:bg-gray-100 font-medium">
                            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                            </svg>
                            <span class="flex-1">{{ __('notifications.drafts') }}</span>
                            <span class="text-xs text-gray-400">{{ $draftsCount }}</span>
                        </a>
                    </nav>
                </div>

                <!-- Xabar yozish formasi -->
                <div class="flex-1 flex flex-col min-w-0" x-data="composeForm()">
                    <!-- Toolbar -->
                    <div class="flex items-center justify-between px-3 sm:px-4 py-2 border-b border-gray-200 bg-white">
                        <a href="{{ route('admin.notifications.index') }}"
                           class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-700 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                            </svg>
                            {{ __('notifications.back_to_list') }}
                        </a>
                    </div>

                    <form method="POST" action="{{ route('admin.notifications.store') }}" class="flex-1 flex flex-col">
                        @csrf

                        <div class="flex-1 overflow-y-auto">
                            <div class="divide-y divide-gray-100">
                                <!-- Recipient Type row -->
                                <div class="flex items-center px-5 py-2.5">
                                    <label class="w-20 text-sm text-gray-500 flex-shrink-0">{{ __('notifications.recipient_type') }}</label>
                                    <select name="recipient_type"
                                            class="flex-1 border-0 focus:ring-0 text-sm text-gray-900 py-1 px-0"
                                            x-model="recipientType" @change="onTypeChange()">
                                        <option value="user">{{ __('notifications.admin_user') }}</option>
                                        <option value="teacher">{{ __('notifications.teacher') }}</option>
                                    </select>
                                </div>

                                <!-- Role filter row (only for User type) -->
                                <div class="flex items-center px-5 py-2.5" x-show="recipientType === 'user'">
                                    <label class="w-20 text-sm text-gray-500 flex-shrink-0">{{ __('notifications.role') }}</label>
                                    <select class="flex-1 border-0 focus:ring-0 text-sm text-gray-900 py-1 px-0"
                                            x-model="selectedRole" @change="filterByRole()">
                                        <option value="">{{ __('notifications.all') }}</option>
                                        @foreach($roles as $role)
                                        <option value="{{ $role['value'] }}">{{ $role['label'] }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <!-- Recipient row -->
                                <div class="flex items-center px-5 py-2.5">
                                    <label class="w-20 text-sm text-gray-500 flex-shrink-0">{{ __('notifications.recipient') }}</label>
                                    <div class="flex-1">
                                        <select name="recipient_id" id="recipient_user"
                                                class="w-full border-0 focus:ring-0 text-sm text-gray-900 py-1 px-0"
                                                x-show="recipientType === 'user'">
                                            <option value="">-- {{ __('notifications.select_recipient') }} --</option>
                                            @foreach($users as $u)
                                            <option value="{{ $u->id }}" data-roles="{{ $u->getRoleNames()->implode(',') }}">{{ $u->name }}</option>
                                            @endforeach
                                        </select>
                                        <select id="recipient_teacher"
                                                class="w-full border-0 focus:ring-0 text-sm text-gray-900 py-1 px-0"
                                                x-show="recipientType === 'teacher'">
                                            <option value="">-- {{ __('notifications.select_recipient') }} --</option>
                                            @foreach($teachers as $t)
                                            <option value="{{ $t->id }}">{{ $t->full_name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <!-- Subject row -->
                                <div class="flex items-center px-5 py-2.5">
                                    <label class="w-20 text-sm text-gray-500 flex-shrink-0">{{ __('notifications.subject') }}</label>
                                    <input type="text" name="subject" value="{{ old('subject') }}"
                                           class="flex-1 border-0 focus:ring-0 text-sm text-gray-900 py-1 px-0"
                                           placeholder="{{ __('notifications.subject_placeholder') }}" required>
                                </div>
                            </div>

                            <!-- Body -->
                            <div class="px-5 pt-3 pb-2">
                                <textarea name="body" rows="12"
                                          class="w-full border-0 focus:ring-0 text-sm text-gray-800 leading-relaxed resize-none p-0"
                                          placeholder="{{ __('notifications.body_placeholder') }}">{{ old('body') }}</textarea>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="px-5 py-3 border-t border-gray-100 flex items-center gap-2">
                            <button type="submit"
                                    class="inline-flex items-center gap-1.5 px-5 py-2 text-sm font-semibold text-white bg-blue-600 rounded-full hover:bg-blue-700 transition-all shadow-sm hover:shadow">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                                </svg>
                                {{ __('notifications.send') }}
                            </button>
                            <button type="submit" name="save_draft" value="1"
                                    class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-full transition-colors">
                                {{ __('notifications.save_draft') }}
                            </button>
                            <div class="flex-1"></div>
                            <a href="{{ route('admin.notifications.index') }}"
                               class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-full transition-colors" title="{{ __('notifications.delete') }}">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                </svg>
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
                recipientType: '{{ old("recipient_type", "user") }}',
                selectedRole: '',
                onTypeChange() {
                    var userSelect = document.getElementById('recipient_user');
                    var teacherSelect = document.getElementById('recipient_teacher');

                    if (this.recipientType === 'teacher') {
                        userSelect.name = '';
                        teacherSelect.name = 'recipient_id';
                    } else {
                        teacherSelect.name = '';
                        userSelect.name = 'recipient_id';
                    }
                    this.selectedRole = '';
                    this.filterByRole();
                },
                filterByRole() {
                    var select = document.getElementById('recipient_user');
                    var role = this.selectedRole;
                    var options = select.querySelectorAll('option[data-roles]');

                    options.forEach(function(opt) {
                        var roles = opt.getAttribute('data-roles') || '';
                        if (!role || roles.split(',').indexOf(role) !== -1) {
                            opt.style.display = '';
                        } else {
                            opt.style.display = 'none';
                            if (opt.selected) {
                                select.value = '';
                            }
                        }
                    });
                },
                init() {
                    this.onTypeChange();
                }
            }
        }
    </script>
    @endpush
</x-app-layout>
