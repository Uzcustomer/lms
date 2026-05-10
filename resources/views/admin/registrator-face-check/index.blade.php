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
                    <th class="text-left px-4 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($students as $student)
                <tr class="border-b hover:bg-gray-50">
                    <td class="px-4 py-2">{{ $student->full_name }}</td>
                    <td class="px-4 py-2 font-mono">{{ $student->student_id_number }}</td>
                    <td class="px-4 py-2">{{ $student->group_name }}</td>
                    <td class="px-4 py-2 text-right">
                        <a href="{{ route('admin.registrator.face-check.show', $student->student_id_number) }}"
                           class="px-3 py-1 bg-emerald-600 text-white rounded text-xs hover:bg-emerald-700">
                            Tekshirish
                        </a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="4" class="px-4 py-6 text-center text-gray-500">Topilmadi</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $students->links() }}</div>
</div>
</x-app-layout>
