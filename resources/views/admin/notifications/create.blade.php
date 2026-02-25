<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('notifications.compose') }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="mb-4">
                <a href="{{ route('admin.notifications.index') }}"
                   class="inline-flex items-center text-sm text-blue-600 hover:text-blue-800">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                    {{ __('notifications.back_to_list') }}
                </a>
            </div>

            @if($errors->any())
            <div class="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">
                <ul class="list-disc list-inside">
                    @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <form method="POST" action="{{ route('admin.notifications.store') }}">
                    @csrf
                    <div class="px-6 py-4 space-y-4">
                        <!-- Recipient Type -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('notifications.recipient_type') }}</label>
                            <select name="recipient_type" id="recipient_type"
                                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                                    onchange="toggleRecipientList()">
                                <option value="App\Models\User">{{ __('notifications.admin_user') }}</option>
                                <option value="App\Models\Teacher">{{ __('notifications.teacher') }}</option>
                            </select>
                        </div>

                        <!-- Recipient -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('notifications.recipient') }}</label>
                            <select name="recipient_id" id="recipient_user" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                                <option value="">-- {{ __('notifications.select_recipient') }} --</option>
                                @foreach($users as $u)
                                <option value="{{ $u->id }}">{{ $u->name }}</option>
                                @endforeach
                            </select>
                            <select name="recipient_id" id="recipient_teacher" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm" style="display: none;">
                                <option value="">-- {{ __('notifications.select_recipient') }} --</option>
                                @foreach($teachers as $t)
                                <option value="{{ $t->id }}">{{ $t->full_name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Subject -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('notifications.subject') }}</label>
                            <input type="text" name="subject" value="{{ old('subject') }}"
                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                                   placeholder="{{ __('notifications.subject_placeholder') }}" required>
                        </div>

                        <!-- Body -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('notifications.body') }}</label>
                            <textarea name="body" rows="8"
                                      class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                                      placeholder="{{ __('notifications.body_placeholder') }}">{{ old('body') }}</textarea>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex items-center justify-end gap-3">
                        <button type="submit" name="save_draft" value="1"
                                class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                            {{ __('notifications.save_draft') }}
                        </button>
                        <button type="submit"
                                class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors">
                            <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
    </script>
    @endpush
</x-app-layout>
