<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            {{ __('Jurnal') }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="px-4">
            <!-- Filterlar -->
            <div class="bg-white rounded-lg shadow-md p-4 mb-6">
                <form method="GET" action="{{ route('admin.jurnal.index') }}" class="flex flex-wrap gap-4 items-end">
                    <!-- Ta'lim turi -->
                    <div class="flex-1 min-w-[150px]">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Ta'lim turi</label>
                        <select name="education_type" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                            <option value="">Barchasi</option>
                            @foreach($educationTypes as $type)
                                <option value="{{ $type->education_type_code }}" {{ request('education_type') == $type->education_type_code ? 'selected' : '' }}>
                                    {{ $type->education_type_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- O'quv yili -->
                    <div class="flex-1 min-w-[150px]">
                        <label class="block text-sm font-medium text-gray-700 mb-1">O'quv yili</label>
                        <select name="education_year" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                            <option value="">Barchasi</option>
                            @foreach($educationYears as $year)
                                <option value="{{ $year->education_year_code }}" {{ request('education_year') == $year->education_year_code ? 'selected' : '' }}>
                                    {{ $year->education_year_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Fakultet -->
                    <div class="flex-1 min-w-[180px]">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Fakultet</label>
                        <select name="department_id" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                            <option value="">Barchasi</option>
                            @foreach($departments as $dept)
                                <option value="{{ $dept->id }}" {{ request('department_id') == $dept->id ? 'selected' : '' }}>
                                    {{ $dept->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Kurs -->
                    <div class="flex-1 min-w-[120px]">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Kurs</label>
                        <select name="level_code" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                            <option value="">Barchasi</option>
                            @foreach($levelCodes as $code => $name)
                                <option value="{{ $code }}" {{ request('level_code') == $code ? 'selected' : '' }}>
                                    {{ $name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Filter button -->
                    <div>
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 text-sm">
                            <i class="fas fa-filter mr-2"></i> Filtrlash
                        </button>
                    </div>

                    <!-- Reset button -->
                    <div>
                        <a href="{{ route('admin.jurnal.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-100 border border-gray-300 rounded-md font-medium text-gray-700 hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 text-sm">
                            <i class="fas fa-times mr-2"></i> Tozalash
                        </a>
                    </div>
                </form>
            </div>

            <!-- Jadval -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">T/R</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ta'lim turi</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">O'quv yili</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fakultet</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Yo'nalish</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kurs</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Semestr</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fan</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Guruh</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Amallar</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($pagination as $index => $item)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                        {{ ($pagination->currentPage() - 1) * $pagination->perPage() + $index + 1 }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                        {{ $item['talim_turi'] }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                        {{ $item['oquv_yili'] }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-900">
                                        {{ $item['fakultet'] }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-900">
                                        {{ $item['yonalish'] }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                        {{ $item['kurs'] }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                        {{ $item['semester_name'] }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-900">
                                        {{ $item['subject_name'] }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-blue-600">
                                        {{ $item['group_name'] }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-center text-sm">
                                        <a href="{{ route('admin.jurnal.show', ['group_id' => $item['group_id'], 'semester_id' => $item['semester_id'], 'subject_id' => $item['subject_id']]) }}"
                                           class="inline-flex items-center px-2 py-1 bg-blue-100 text-blue-700 rounded hover:bg-blue-200 mr-1"
                                           title="Ko'rish">
                                            <i class="fas fa-eye text-xs"></i>
                                        </a>
                                        <a href="{{ route('admin.jurnal.show', ['group_id' => $item['group_id'], 'semester_id' => $item['semester_id'], 'subject_id' => $item['subject_id'], 'edit' => 1]) }}"
                                           class="inline-flex items-center px-2 py-1 bg-green-100 text-green-700 rounded hover:bg-green-200"
                                           title="Tahrirlash">
                                            <i class="fas fa-edit text-xs"></i>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="px-4 py-8 text-center text-gray-500">
                                        <i class="fas fa-book-open text-4xl mb-3 block text-gray-300"></i>
                                        <p>Ma'lumot topilmadi</p>
                                        <p class="text-sm text-gray-400 mt-1">Filterlarni o'zgartiring yoki yangi ma'lumot qo'shing</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                @if($pagination->hasPages())
                <div class="px-4 py-3 border-t border-gray-200">
                    {{ $pagination->links() }}
                </div>
                @endif

                <!-- Ma'lumot soni -->
                <div class="px-4 py-2 bg-gray-50 border-t border-gray-200 text-sm text-gray-600">
                    Jami: {{ $pagination->total() }} ta yozuv
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
