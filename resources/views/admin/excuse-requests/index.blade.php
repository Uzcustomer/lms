<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Sababli arizalar') }}
            </h2>
        </div>
    </x-slot>

    @if(session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
            <strong class="font-bold">Muvaffaqiyatli!</strong>
            <span class="block sm:inline">{{ session('success') }}</span>
        </div>
    @endif

    <div class="py-12">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                <!-- Filters -->
                <form method="GET" action="{{ route('admin.excuse-requests.index') }}" class="p-6 bg-gray-50">
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
                        <div>
                            <label class="block mb-2 text-sm font-medium">Qidirish</label>
                            <input type="text" name="search" value="{{ request('search') }}"
                                   placeholder="Talaba, guruh yoki fan nomi..."
                                   class="w-full border-gray-300 rounded-md shadow-sm">
                        </div>
                        <div>
                            <label class="block mb-2 text-sm font-medium">Holat</label>
                            <select name="status" class="w-full border-gray-300 rounded-md shadow-sm">
                                <option value="">Barchasi</option>
                                <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Kutilmoqda</option>
                                <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>Qabul qilingan</option>
                                <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>Rad etilgan</option>
                            </select>
                        </div>
                        <div>
                            <label class="block mb-2 text-sm font-medium">Turi</label>
                            <select name="type" class="w-full border-gray-300 rounded-md shadow-sm">
                                <option value="">Barchasi</option>
                                <option value="exam_test" {{ request('type') == 'exam_test' ? 'selected' : '' }}>Yakuniy test</option>
                                <option value="oski" {{ request('type') == 'oski' ? 'selected' : '' }}>OSKI</option>
                            </select>
                        </div>
                        <div class="flex items-end">
                            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700">
                                Qidirish
                            </button>
                        </div>
                    </div>
                </form>

                <!-- Table -->
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">#</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Talaba</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Guruh</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Turi</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fan</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Sabab</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fayl</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Sana</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Holat</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amallar</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($requests as $req)
                                <tr>
                                    <td class="px-4 py-3 text-sm">{{ $req->id }}</td>
                                    <td class="px-4 py-3 text-sm font-medium">{{ $req->student_name }}</td>
                                    <td class="px-4 py-3 text-sm">{{ $req->group_name }}</td>
                                    <td class="px-4 py-3 text-sm">
                                        @if($req->type === 'exam_test')
                                            <span class="px-2 py-1 text-xs bg-blue-100 text-blue-800 rounded-full">Yakuniy test</span>
                                        @else
                                            <span class="px-2 py-1 text-xs bg-purple-100 text-purple-800 rounded-full">OSKI</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-sm">{{ $req->subject_name }}</td>
                                    <td class="px-4 py-3 text-sm max-w-xs truncate" title="{{ $req->reason }}">{{ $req->reason }}</td>
                                    <td class="px-4 py-3 text-sm">
                                        @if($req->file_path)
                                            <a href="{{ asset('storage/' . $req->file_path) }}" target="_blank"
                                               class="text-blue-600 hover:underline">
                                                {{ $req->file_original_name ?? 'Fayl' }}
                                            </a>
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-sm">{{ $req->created_at->format('d.m.Y H:i') }}</td>
                                    <td class="px-4 py-3 text-sm">
                                        @if($req->status === 'pending')
                                            <span class="px-2 py-1 text-xs bg-yellow-100 text-yellow-800 rounded-full">Kutilmoqda</span>
                                        @elseif($req->status === 'approved')
                                            <span class="px-2 py-1 text-xs bg-green-100 text-green-800 rounded-full">Qabul qilingan</span>
                                        @else
                                            <span class="px-2 py-1 text-xs bg-red-100 text-red-800 rounded-full">Rad etilgan</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-sm">
                                        @if($req->status === 'pending')
                                            <div class="flex gap-1">
                                                <form method="POST" action="{{ route('admin.excuse-requests.update-status', $req->id) }}" class="inline">
                                                    @csrf
                                                    @method('PUT')
                                                    <input type="hidden" name="status" value="approved">
                                                    <button type="submit" class="px-3 py-1 text-xs bg-green-600 text-white rounded hover:bg-green-700"
                                                            onclick="return confirm('Arizani qabul qilasizmi?')">
                                                        Qabul
                                                    </button>
                                                </form>
                                                <button type="button" class="px-3 py-1 text-xs bg-red-600 text-white rounded hover:bg-red-700"
                                                        onclick="openRejectModal({{ $req->id }})">
                                                    Rad etish
                                                </button>
                                            </div>
                                        @else
                                            <span class="text-xs text-gray-500">
                                                {{ $req->reviewed_at?->format('d.m.Y') }}
                                                @if($req->admin_comment)
                                                    <br><i>{{ $req->admin_comment }}</i>
                                                @endif
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="px-4 py-8 text-center text-gray-500">
                                        Arizalar topilmadi.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="p-4">
                    {{ $requests->withQueryString()->links() }}
                </div>
            </div>
        </div>
    </div>

    <!-- Reject Modal -->
    <div id="rejectModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black bg-opacity-50">
        <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md mx-4">
            <h3 class="text-lg font-semibold mb-4">Arizani rad etish</h3>
            <form id="rejectForm" method="POST">
                @csrf
                @method('PUT')
                <input type="hidden" name="status" value="rejected">
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-2">Izoh (ixtiyoriy)</label>
                    <textarea name="admin_comment" rows="3"
                              class="w-full border-gray-300 rounded-md shadow-sm"
                              placeholder="Rad etish sababini yozing..."></textarea>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" onclick="closeRejectModal()"
                            class="px-4 py-2 text-sm bg-gray-200 rounded hover:bg-gray-300">
                        Bekor qilish
                    </button>
                    <button type="submit"
                            class="px-4 py-2 text-sm bg-red-600 text-white rounded hover:bg-red-700">
                        Rad etish
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openRejectModal(id) {
            const modal = document.getElementById('rejectModal');
            const form = document.getElementById('rejectForm');
            form.action = '{{ route("admin.excuse-requests.update-status", "") }}/' + id;
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function closeRejectModal() {
            const modal = document.getElementById('rejectModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }
    </script>
</x-app-layout>
