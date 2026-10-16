<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">Monitoring — ko'chirib kelganlar yig'majildi (E-7, M8)</h2>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            @include('admin.monitoring.partials.nav')

            <p class="text-sm text-gray-600 mb-3">
                Yig'majildda 6 majburiy hujjat bo'lishi va komissiya asoslovchi hujjatlar aslini tekshirishi shart (VMQ 578 3-il. 53).
                «Ko'chirib kelish» buyrug'i import qilinganda cheklist avtomatik ochiladi; kerak bo'lsa qo'lda ham qo'shiladi.
                @if($requireOriginal) Asli tekshirilgani belgilanmagan hujjat to'liq hisoblanmaydi. @endif
            </p>

            @if($canWork)
                <form method="POST" action="{{ route('admin.monitoring.yigmajild.store') }}" class="bg-white rounded-lg shadow p-4 mb-4 flex flex-wrap gap-3 items-end text-sm">
                    @csrf
                    <div>
                        <label class="block text-gray-600">Talaba ID raqami</label>
                        <input type="text" name="student_id_number" value="{{ old('student_id_number') }}" class="border-gray-300 rounded">
                    </div>
                    <div>
                        <label class="block text-gray-600">F.I.Sh. (ID topilmasa)</label>
                        <input type="text" name="full_name" value="{{ old('full_name') }}" class="border-gray-300 rounded">
                    </div>
                    <div>
                        <label class="block text-gray-600">Kelgan sana</label>
                        <input type="date" name="arrived_at" value="{{ old('arrived_at') }}" class="border-gray-300 rounded">
                    </div>
                    <button class="px-4 py-2 bg-blue-600 text-white rounded">Cheklist ochish</button>
                </form>
            @endif

            <form method="GET" class="mb-4 flex flex-wrap gap-3 items-end text-sm">
                <label><input type="checkbox" name="holat" value="toliq_emas" @checked(request('holat') === 'toliq_emas')> faqat to'liq emaslar</label>
                <input type="text" name="q" value="{{ request('q') }}" placeholder="F.I.Sh." class="border-gray-300 rounded">
                <button class="px-3 py-1.5 bg-gray-200 rounded">Filtrlash</button>
            </form>

            @forelse($checklists as $checklist)
                @php $missing = $checklist->missingDocuments($requireOriginal); @endphp
                <form method="POST" action="{{ route('admin.monitoring.yigmajild.update', $checklist) }}" class="bg-white rounded-lg shadow p-4 mb-4 text-sm">
                    @csrf
                    @method('PUT')
                    <div class="flex flex-wrap justify-between gap-2 mb-2">
                        <div>
                            <b>{{ $checklist->full_name }}</b>
                            <span class="text-gray-500">{{ $checklist->student_id_number }}</span>
                            @if($checklist->order) · buyruq № {{ $checklist->order->order_number }} ({{ $checklist->order->order_date->format('d.m.Y') }}) @endif
                            @if($checklist->arrived_at) · kelgan {{ $checklist->arrived_at->format('d.m.Y') }} @endif
                        </div>
                        <span class="{{ $missing ? 'text-red-700' : 'text-green-700' }}">
                            {{ count($documents) - count($missing) }}/{{ count($documents) }} {{ $missing ? "— to'liq emas" : "— to'liq" }}
                        </span>
                    </div>
                    <table class="min-w-full">
                        <thead><tr class="text-left text-gray-500"><th class="py-1">Hujjat</th><th class="py-1">Bor</th><th class="py-1">Asli tekshirildi</th><th class="py-1">Izoh</th></tr></thead>
                        <tbody>
                        @foreach($documents as $key => $label)
                            @php $item = $checklist->items[$key] ?? []; @endphp
                            <tr class="border-t">
                                <td class="py-1">{{ $label }}</td>
                                <td class="py-1"><input type="checkbox" name="items[{{ $key }}][bor]" value="1" @checked(!empty($item['bor'])) @disabled(!$canWork)></td>
                                <td class="py-1"><input type="checkbox" name="items[{{ $key }}][asli_tekshirildi]" value="1" @checked(!empty($item['asli_tekshirildi'])) @disabled(!$canWork)></td>
                                <td class="py-1"><input type="text" name="items[{{ $key }}][izoh]" value="{{ $item['izoh'] ?? '' }}" class="border-gray-300 rounded text-xs w-full" @disabled(!$canWork)></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                    <textarea name="note" rows="1" class="w-full border-gray-300 rounded text-xs mt-2" placeholder="Umumiy izoh" @disabled(!$canWork)>{{ $checklist->note }}</textarea>
                    @if($canWork)<button class="mt-2 px-3 py-1.5 bg-blue-600 text-white rounded text-xs">Saqlash</button>@endif
                </form>
            @empty
                <div class="bg-white rounded-lg shadow p-4 text-sm text-gray-500">Cheklist yo'q.</div>
            @endforelse
            <div class="mt-4">{{ $checklists->links() }}</div>
        </div>
    </div>
</x-app-layout>
