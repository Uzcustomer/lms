<x-app-layout>
    <div class="p-4 sm:ml-64">
        <div class="mt-14">

            <div class="flex items-center justify-between mb-6">
                <h1 class="text-2xl font-bold text-gray-800">Bitiruvchi shartnomalar</h1>
            </div>

            @if(session('success'))
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    {{ session('error') }}
                </div>
            @endif

            {{-- Status badges --}}
            <div class="flex flex-wrap gap-2 mb-4">
                <a href="{{ route('admin.student-contracts.index') }}"
                   class="px-3 py-1.5 rounded-full text-xs font-semibold {{ !request('status') ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                    Barchasi ({{ $statusCounts['all'] }})
                </a>
                <a href="{{ route('admin.student-contracts.index', ['status' => 'pending']) }}"
                   class="px-3 py-1.5 rounded-full text-xs font-semibold {{ request('status') === 'pending' ? 'bg-yellow-500 text-white' : 'bg-yellow-50 text-yellow-700 hover:bg-yellow-100' }}">
                    Kutilmoqda ({{ $statusCounts['pending'] }})
                </a>
                <a href="{{ route('admin.student-contracts.index', ['status' => 'registrar_review']) }}"
                   class="px-3 py-1.5 rounded-full text-xs font-semibold {{ request('status') === 'registrar_review' ? 'bg-blue-500 text-white' : 'bg-blue-50 text-blue-700 hover:bg-blue-100' }}">
                    Ko'rib chiqilmoqda ({{ $statusCounts['registrar_review'] }})
                </a>
                <a href="{{ route('admin.student-contracts.index', ['status' => 'approved']) }}"
                   class="px-3 py-1.5 rounded-full text-xs font-semibold {{ request('status') === 'approved' ? 'bg-green-600 text-white' : 'bg-green-50 text-green-700 hover:bg-green-100' }}">
                    Tasdiqlangan ({{ $statusCounts['approved'] }})
                </a>
                <a href="{{ route('admin.student-contracts.index', ['status' => 'rejected']) }}"
                   class="px-3 py-1.5 rounded-full text-xs font-semibold {{ request('status') === 'rejected' ? 'bg-red-600 text-white' : 'bg-red-50 text-red-700 hover:bg-red-100' }}">
                    Rad etilgan ({{ $statusCounts['rejected'] }})
                </a>
            </div>

            {{-- Filters --}}
            <form method="GET" action="{{ route('admin.student-contracts.index') }}" class="bg-white rounded-lg shadow-sm p-4 mb-4">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Qidiruv</label>
                        <input type="text" name="search" value="{{ request('search') }}" placeholder="FIO, HEMIS ID, guruh..."
                               class="w-full rounded-lg border-gray-300 text-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Shartnoma turi</label>
                        <select name="contract_type" class="w-full rounded-lg border-gray-300 text-sm focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Barchasi</option>
                            <option value="3_tomonlama" {{ request('contract_type') === '3_tomonlama' ? 'selected' : '' }}>3 tomonlama</option>
                            <option value="4_tomonlama" {{ request('contract_type') === '4_tomonlama' ? 'selected' : '' }}>4 tomonlama</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Fakultet</label>
                        <select name="department" class="w-full rounded-lg border-gray-300 text-sm focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Barchasi</option>
                            @foreach($departments as $dept)
                                <option value="{{ $dept }}" {{ request('department') === $dept ? 'selected' : '' }}>{{ $dept }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button type="submit" class="w-full px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition">
                            Qidirish
                        </button>
                    </div>
                </div>
                @if(request('status'))
                    <input type="hidden" name="status" value="{{ request('status') }}">
                @endif
            </form>

            {{-- Table --}}
            <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">#</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Talaba</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Guruh</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fakultet</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tur</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Holat</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Sana</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amallar</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($contracts as $i => $contract)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 text-sm text-gray-500">{{ $contracts->firstItem() + $i }}</td>
                                    <td class="px-4 py-3">
                                        <div class="text-sm font-medium text-gray-900">{{ $contract->student_full_name }}</div>
                                        <div class="text-xs text-gray-500">{{ $contract->student_hemis_id }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600">{{ $contract->group_name }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600">{{ $contract->department_name }}</td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full {{ $contract->contract_type === '4_tomonlama' ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700' }}">
                                            {{ $contract->type_label }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        @php
                                            $statusColors = [
                                                'pending' => 'bg-yellow-100 text-yellow-700',
                                                'registrar_review' => 'bg-blue-100 text-blue-700',
                                                'approved' => 'bg-green-100 text-green-700',
                                                'rejected' => 'bg-red-100 text-red-700',
                                            ];
                                        @endphp
                                        <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full {{ $statusColors[$contract->status] ?? 'bg-gray-100 text-gray-700' }}">
                                            {{ $contract->status_label }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-500">{{ $contract->created_at->format('d.m.Y H:i') }}</td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-2">
                                            @if(in_array($contract->status, ['pending', 'registrar_review']))
                                                <a href="{{ route('admin.student-contracts.review', $contract) }}"
                                                   class="inline-flex items-center px-2.5 py-1 bg-blue-600 text-white text-xs font-medium rounded hover:bg-blue-700 transition">
                                                    Ko'rib chiqish
                                                </a>
                                            @else
                                                <a href="{{ route('admin.student-contracts.show', $contract) }}"
                                                   class="inline-flex items-center px-2.5 py-1 bg-gray-100 text-gray-700 text-xs font-medium rounded hover:bg-gray-200 transition">
                                                    Ko'rish
                                                </a>
                                            @endif

                                            @if($contract->status === 'approved' && $contract->document_path)
                                                <a href="{{ route('admin.student-contracts.download', $contract) }}"
                                                   class="inline-flex items-center px-2.5 py-1 bg-green-600 text-white text-xs font-medium rounded hover:bg-green-700 transition">
                                                    <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                                    Yuklab olish
                                                </a>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-4 py-12 text-center text-gray-500">
                                        <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                        <p class="text-sm font-medium">Shartnoma topilmadi</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($contracts->hasPages())
                    <div class="px-4 py-3 border-t border-gray-200">
                        {{ $contracts->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
