<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('notifications.compose') }}
        </h2>
    </x-slot>

    <div class="py-4 sm:py-6">
        <div class="max-w-3xl mx-auto px-2 sm:px-6 lg:px-8">
            <!-- Back link -->
            <div class="mb-4">
                <a href="{{ route('admin.notifications.index') }}"
                   class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-700 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                    {{ __('notifications.back_to_list') }}
                </a>
            </div>

            @if($errors->any())
            <div class="mb-4 flex items-start gap-2.5 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-sm">
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

            <div class="bg-white rounded-xl shadow-sm border border-gray-200/60 overflow-hidden">
                <form method="POST" action="{{ route('admin.notifications.store') }}">
                    @csrf

                    <!-- Compose header -->
                    <div class="px-5 sm:px-6 py-4 border-b border-gray-100 bg-gray-50/50">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center flex-shrink-0">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-sm font-semibold text-gray-800">{{ __('notifications.compose') }}</h3>
                                <p class="text-xs text-gray-400">{{ __('notifications.compose') }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="px-5 sm:px-6 py-5 space-y-4">
                        <!-- Recipient Type + Recipient in one row on desktop -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">{{ __('notifications.recipient_type') }}</label>
                                <select name="recipient_type" id="recipient_type"
                                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm py-2.5"
                                        onchange="toggleRecipientList()">
                                    <option value="App\Models\User" {{ old('recipient_type', 'App\Models\User') === 'App\Models\User' ? 'selected' : '' }}>{{ __('notifications.admin_user') }}</option>
                                    <option value="App\Models\Teacher" {{ old('recipient_type') === 'App\Models\Teacher' ? 'selected' : '' }}>{{ __('notifications.teacher') }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">{{ __('notifications.recipient') }}</label>
                                <select name="recipient_id" id="recipient_user" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm py-2.5">
                                    <option value="">-- {{ __('notifications.select_recipient') }} --</option>
                                    @foreach($users as $u)
                                    <option value="{{ $u->id }}">{{ $u->name }}</option>
                                    @endforeach
                                </select>
                                <select name="" id="recipient_teacher" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm py-2.5" style="display: none;">
                                    <option value="">-- {{ __('notifications.select_recipient') }} --</option>
                                    @foreach($teachers as $t)
                                    <option value="{{ $t->id }}">{{ $t->full_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <!-- Subject -->
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">{{ __('notifications.subject') }}</label>
                            <input type="text" name="subject" value="{{ old('subject') }}"
                                   class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm py-2.5"
                                   placeholder="{{ __('notifications.subject_placeholder') }}" required>
                        </div>

                        <!-- Body -->
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">{{ __('notifications.body') }}</label>
                            <textarea name="body" rows="10"
                                      class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm py-2.5 leading-relaxed"
                                      placeholder="{{ __('notifications.body_placeholder') }}">{{ old('body') }}</textarea>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="px-5 sm:px-6 py-4 bg-gray-50/50 border-t border-gray-100 flex flex-col-reverse sm:flex-row items-center justify-end gap-2.5">
                        <a href="{{ route('admin.notifications.index') }}"
                           class="w-full sm:w-auto px-4 py-2.5 text-sm font-medium text-gray-500 hover:text-gray-700 text-center transition-colors">
                            {{ __('notifications.back_to_list') }}
                        </a>
                        <button type="submit" name="save_draft" value="1"
                                class="w-full sm:w-auto inline-flex items-center justify-center gap-1.5 px-5 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-all shadow-sm">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                            </svg>
                            {{ __('notifications.save_draft') }}
                        </button>
                        <button type="submit"
                                class="w-full sm:w-auto inline-flex items-center justify-center gap-1.5 px-6 py-2.5 text-sm font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-all shadow-sm hover:shadow">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                            </svg>
                            {{ __('notifications.send') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        function toggleRecipientList() {
            var type = document.getElementById('recipient_type').value;
            var userSelect = document.getElementById('recipient_user');
            var teacherSelect = document.getElementById('recipient_teacher');

            if (type === 'App\\Models\\Teacher') {
                userSelect.style.display = 'none';
                userSelect.name = '';
                teacherSelect.style.display = 'block';
                teacherSelect.name = 'recipient_id';
            } else {
                teacherSelect.style.display = 'none';
                teacherSelect.name = '';
                userSelect.style.display = 'block';
                userSelect.name = 'recipient_id';
            }
        }
        document.addEventListener('DOMContentLoaded', toggleRecipientList);
    </script>
    @endpush
</x-app-layout>
