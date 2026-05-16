<x-app-layout>
<x-slot name="header">
    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Registrator Face ID tekshiruvi
    </h2>
</x-slot>

<div class="container mx-auto px-4 py-6">

    <div class="flex items-center justify-between mb-5">
        <div>
            <h1 class="text-xl font-bold text-gray-800">🆔 Test markaziga kirish — webcam tekshiruv</h1>
            <p class="text-sm text-gray-500">Talabaning yuzini bu yerda webcam orqali tekshirib, test markazidagi muammolarning oldini oling.</p>
        </div>
    </div>

    <form method="GET" class="bg-white rounded-lg border p-4 mb-4 flex gap-3 items-end">
        <div class="flex-1">
            <label class="block text-xs text-gray-500 mb-1">Talaba (ism, ID raqam yoki passport)</label>
            <input type="text" name="q" value="{{ $query }}"
                   class="w-full border border-gray-300 rounded px-3 py-1.5 text-sm"
                   placeholder="Familiya yoki ID..." />
        </div>
        <button type="submit" class="px-4 py-1.5 bg-blue-600 text-white rounded text-sm hover:bg-blue-700">
            Qidirish
        </button>
    </form>

    <div class="bg-white rounded-lg border overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="text-left px-4 py-2">F.I.O</th>
                    <th class="text-left px-4 py-2">ID raqam</th>
                    <th class="text-left px-4 py-2">Guruh</th>
                    <th class="text-left px-4 py-2">Holati</th>
                    <th class="text-left px-4 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($students as $student)
                @php($isApproved = isset($approvedIds[(string) $student->student_id_number]))
                <tr class="border-b hover:bg-gray-50">
                    <td class="px-4 py-2">{{ $student->full_name }}</td>
                    <td class="px-4 py-2 font-mono">{{ $student->student_id_number }}</td>
                    <td class="px-4 py-2">{{ $student->group_name }}</td>
                    <td class="px-4 py-2">
                        @if($isApproved)
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 border border-emerald-200">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                                Tasdiqlandi
                            </span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600 border border-gray-200">
                                Tekshirilmagan
                            </span>
                        @endif
                    </td>
                    <td class="px-4 py-2 text-right">
                        <a href="{{ route('admin.registrator.face-check.show', $student->student_id_number) }}"
                           class="px-3 py-1 {{ $isApproved ? 'bg-gray-500 hover:bg-gray-600' : 'bg-emerald-600 hover:bg-emerald-700' }} text-white rounded text-xs">
                            {{ $isApproved ? 'Qayta tekshirish' : 'Tekshirish' }}
                        </a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" class="px-4 py-6 text-center text-gray-500">Topilmadi</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $students->links() }}</div>
</div>
</x-app-layout>
