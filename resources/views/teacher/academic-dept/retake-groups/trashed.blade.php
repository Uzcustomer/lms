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

    <div class="py-6 px-4 sm:px-6 lg:px-8 w-full">
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
