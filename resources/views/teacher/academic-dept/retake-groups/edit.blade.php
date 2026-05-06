<x-teacher-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __("Guruhni tahrirlash") }}
            <a href="{{ route('admin.retake-groups.index') }}" class="text-sm text-blue-600 hover:underline ml-2">← {{ __('Orqaga') }}</a>
        </h2>
    </x-slot>

    @include('partials._retake_tom_select')

    <div class="py-6 px-4 sm:px-6 lg:px-8 w-full">

        @if(session('success'))
            <div class="bg-green-50 border border-green-200 rounded-lg p-3 mb-4 text-sm text-green-800">{{ session('success') }}</div>
        @endif

        @if($errors->any())
            <div class="bg-red-50 border border-red-200 rounded-lg p-3 mb-4 text-sm text-red-800">
                <ul class="list-disc list-inside">@foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach</ul>
            </div>
        @endif

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-4">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-base font-bold text-gray-900">{{ $group->name }}</h3>
                @php
                    $colors = [
                        'forming' => 'bg-gray-100 text-gray-700',
                        'scheduled' => 'bg-blue-100 text-blue-800',
                        'in_progress' => 'bg-green-100 text-green-800',
                        'completed' => 'bg-purple-100 text-purple-800',
                    ];
                @endphp
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium {{ $colors[$group->status] }}">
                    {{ $group->statusLabel() }}
                </span>
            </div>

            <p class="text-sm text-gray-700 mb-1">
                <span class="text-gray-500">{{ __("Fan") }}:</span> {{ $group->subject_name }}
                <span class="text-gray-400 mx-1">·</span>
                <span class="text-gray-500">{{ __("Semestr") }}:</span> {{ $group->semester_name }}
            </p>

            @php $editable = $group->isEditable() || $canOverride; @endphp

            <form method="POST" action="{{ route('admin.retake-groups.update', $group->id) }}" class="mt-4 space-y-3">
                @csrf @method('PUT')

                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">{{ __("Guruh nomi") }}</label>
                    <input type="text" name="name" value="{{ $group->name }}"
                           {{ $editable ? '' : 'disabled' }}
                           class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg disabled:bg-gray-50">
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">{{ __("O'qituvchi") }}</label>
                    <select name="teacher_id" {{ $editable ? '' : 'disabled' }}
                            class="tom-select w-full px-3 py-2 text-sm border border-gray-300 rounded-lg disabled:bg-gray-50">
                        @foreach($teachers as $t)
                            <option value="{{ $t->id }}" {{ $group->teacher_id == $t->id ? 'selected' : '' }}>
                                {{ $t->full_name }}{{ $t->department ? ' — ' . $t->department : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">{{ __("Boshlanish") }}</label>
                        <input type="date" name="start_date" value="{{ $group->start_date->format('Y-m-d') }}"
                               {{ $editable ? '' : 'disabled' }}
                               class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg disabled:bg-gray-50">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">{{ __("Tugash") }}</label>
                        <input type="date" name="end_date" value="{{ $group->end_date->format('Y-m-d') }}"
                               {{ $editable ? '' : 'disabled' }}
                               class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg disabled:bg-gray-50">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">{{ __("Maks. talabalar") }}</label>
                        <input type="number" name="max_students" value="{{ $group->max_students }}" min="1"
                               {{ $editable ? '' : 'disabled' }}
                               class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg disabled:bg-gray-50">
                    </div>
                </div>

                @if($editable)
                    <div class="flex gap-2 pt-2">
                        <button type="submit" class="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                            {{ __("Saqlash") }}
                        </button>
                        @if($group->status === 'forming')
                            <button type="submit" formaction="{{ route('admin.retake-groups.publish', $group->id) }}"
                                    class="px-4 py-2 text-sm bg-green-600 text-white rounded-lg hover:bg-green-700">
                                {{ __("Tasdiqlash (publish)") }}
                            </button>
                        @endif
                    </div>
                @else
                    <p class="text-xs text-gray-500">⚠️ {{ __("Tahrirlash imkoniyati mavjud emas") }}</p>
                @endif
            </form>

            @if($canOverride)
                <div class="mt-6 pt-4 border-t border-gray-100">
                    <p class="text-xs text-gray-500 mb-2">{{ __("Holatni o'zgartirish") }}</p>
                    <form method="POST" action="{{ route('admin.retake-groups.override-status', $group->id) }}" class="flex gap-2">
                        @csrf
                        <select name="status" class="px-3 py-1.5 text-xs border border-gray-300 rounded">
                            <option value="forming" {{ $group->status === 'forming' ? 'selected' : '' }}>forming</option>
                            <option value="scheduled" {{ $group->status === 'scheduled' ? 'selected' : '' }}>scheduled</option>
                            <option value="in_progress" {{ $group->status === 'in_progress' ? 'selected' : '' }}>in_progress</option>
                            <option value="completed" {{ $group->status === 'completed' ? 'selected' : '' }}>completed</option>
                        </select>
                        <button type="submit" class="px-3 py-1.5 text-xs bg-orange-600 text-white rounded">{{ __("Override") }}</button>
                    </form>
                </div>
            @endif
        </div>

        {{-- Talabalar ro'yxati --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100">
                <h3 class="text-sm font-semibold text-gray-900">{{ __("Talabalar") }} ({{ $group->applications->count() }})</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100">
                    <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-2 text-left text-[11px] font-medium text-gray-500 uppercase">F.I.Sh</th>
                        <th class="px-3 py-2 text-left text-[11px] font-medium text-gray-500 uppercase">HEMIS</th>
                        <th class="px-3 py-2 text-left text-[11px] font-medium text-gray-500 uppercase">{{ __("Yo'nalish") }}</th>
                        <th class="px-3 py-2 text-right text-[11px] font-medium text-gray-500 uppercase">{{ __("Kredit") }}</th>
                        <th class="px-3 py-2 text-left text-[11px] font-medium text-gray-500 uppercase">{{ __("Holat") }}</th>
                    </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-100">
                    @foreach($group->applications as $app)
                        @php $student = $app->group->student ?? null; @endphp
                        <tr>
                            <td class="px-3 py-2 text-sm text-gray-900">{{ $student?->full_name ?? '—' }}</td>
                            <td class="px-3 py-2 text-xs text-gray-700">{{ $app->student_hemis_id }}</td>
                            <td class="px-3 py-2 text-xs text-gray-700">{{ $student?->specialty_name ?? '' }} · {{ $student?->level_name ?? $student?->level_code }}</td>
                            <td class="px-3 py-2 text-sm text-gray-700 text-right">{{ number_format($app->credit, 1) }}</td>
                            <td class="px-3 py-2">
                                @php
                                    $appColors = [
                                        'pending' => 'bg-yellow-100 text-yellow-800',
                                        'approved' => 'bg-green-100 text-green-800',
                                        'rejected' => 'bg-red-100 text-red-800',
                                    ];
                                @endphp
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium {{ $appColors[$app->final_status] }}">
                                    {{ $app->final_status }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-teacher-app-layout>
