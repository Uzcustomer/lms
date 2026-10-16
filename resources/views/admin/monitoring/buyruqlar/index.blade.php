<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">Monitoring — buyruqlar reyestri va xabarnomalar (E-1)</h2>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            @include('admin.monitoring.partials.nav')

            <p class="text-sm text-gray-600 mb-3">
                HEMIS buyruqlari mark'ka sinxronlanmaydi — reyestr «Importlar» orqali to'ldiriladi.
                Ijarada yashovchi talabalar bo'yicha akademik ta'til, chetlashtirish va kursda qoldirish buyruqlari haqida
                ijara kotibi va buxgalteriyaga 1 ish kunida xabar berilishi kerak: «Xabar yuborish» tugmasi xabarni mark (va Telegram) orqali yuboradi va qayd etadi.
            </p>

            <form method="GET" class="bg-white rounded-lg shadow p-4 mb-4 flex flex-wrap gap-3 items-end text-sm">
                <div>
                    <label class="block text-gray-600">Turi</label>
                    <select name="turi" class="border-gray-300 rounded">
                        <option value="">— hammasi —</option>
                        @foreach(\App\Models\MonitoringOrder::typeLabels() as $key => $label)
                            <option value="{{ $key }}" @selected(request('turi') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <label><input type="checkbox" name="xabar" value="kerak" @checked(request('xabar') === 'kerak')> faqat xabarnoma talab qilinadiganlar</label>
                <div>
                    <label class="block text-gray-600">Qidirish</label>
                    <input type="text" name="q" value="{{ request('q') }}" class="border-gray-300 rounded" placeholder="F.I.Sh., raqam, ID">
                </div>
                <button class="px-4 py-2 bg-blue-600 text-white rounded">Filtrlash</button>
            </form>

            <div class="bg-white rounded-lg shadow overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-left text-gray-600">
                    <tr><th class="px-3 py-2">Buyruq</th><th class="px-3 py-2">Talaba</th><th class="px-3 py-2">Turi</th><th class="px-3 py-2">Xabarnoma (M3)</th></tr>
                    </thead>
                    <tbody>
                    @forelse($orders as $order)
                        @php
                            $applies = \App\Services\Monitoring\Detectors\E1OrderNotice::appliesTo($order->student, $e1Params);
                            $needsNotice = in_array($order->order_type, $noticeTypes, true) && $applies !== false;
                        @endphp
                        <tr class="border-t align-top">
                            <td class="px-3 py-2 whitespace-nowrap">№ {{ $order->order_number }}<div class="text-xs text-gray-500">{{ $order->order_date->format('d.m.Y') }}</div></td>
                            <td class="px-3 py-2">{{ $order->full_name }}<div class="text-xs text-gray-500">{{ $order->student_id_number }} @unless($order->student_hemis_id)· HEMIS'da topilmadi @endunless @if($order->student?->accommodation_name)· {{ $order->student->accommodation_name }} @endif</div></td>
                            <td class="px-3 py-2">{{ $order->typeLabel() }}<div class="text-xs text-gray-500">{{ $order->order_type_raw }}</div></td>
                            <td class="px-3 py-2">
                                @if($needsNotice)
                                    @foreach($recipients as $kind)
                                        @php $log = $order->noticeLogs->where('recipient_kind', $kind)->sortBy('sent_at')->first(); @endphp
                                        <div>
                                            {{ \App\Models\MonitoringNoticeLog::RECIPIENT_KINDS[$kind] ?? $kind }}:
                                            @if($log)
                                                <span class="text-green-700">{{ $log->sent_at->format('d.m.Y H:i') }}</span>
                                                <span class="text-xs text-gray-500">({{ $log->recipient_name ?? '—' }}, {{ \App\Models\MonitoringNoticeLog::METHODS[$log->method] ?? $log->method }})</span>
                                            @else
                                                <span class="text-red-700">yo'q</span>
                                            @endif
                                        </div>
                                    @endforeach
                                    @if($canWork)
                                        <div class="flex flex-wrap gap-2 mt-2">
                                            <form method="POST" action="{{ route('admin.monitoring.buyruqlar.send-notice', $order) }}">
                                                @csrf
                                                @foreach($recipients as $kind)
                                                    @unless($order->noticeLogs->where('recipient_kind', $kind)->count())
                                                        <input type="hidden" name="kinds[]" value="{{ $kind }}">
                                                    @endunless
                                                @endforeach
                                                <button class="px-2 py-1 bg-blue-600 text-white rounded text-xs">Xabar yuborish</button>
                                            </form>
                                            <details>
                                                <summary class="text-xs text-blue-700 cursor-pointer">Qo'lda qayd etish</summary>
                                                <form method="POST" action="{{ route('admin.monitoring.buyruqlar.record-notice', $order) }}" enctype="multipart/form-data" class="mt-2 space-y-1 text-xs">
                                                    @csrf
                                                    <select name="recipient_kind" class="border-gray-300 rounded text-xs">
                                                        @foreach($recipients as $kind)<option value="{{ $kind }}">{{ \App\Models\MonitoringNoticeLog::RECIPIENT_KINDS[$kind] ?? $kind }}</option>@endforeach
                                                    </select>
                                                    <input type="text" name="recipient_name" placeholder="Kimga" class="border-gray-300 rounded text-xs">
                                                    <input type="datetime-local" name="sent_at" required class="border-gray-300 rounded text-xs">
                                                    <select name="method" class="border-gray-300 rounded text-xs">
                                                        <option value="qogoz">Qog'oz / imzo bilan</option>
                                                        <option value="boshqa">Boshqa</option>
                                                    </select>
                                                    <input type="text" name="note" placeholder="Izoh" class="border-gray-300 rounded text-xs">
                                                    <input type="file" name="evidence" accept=".pdf,.jpg,.jpeg,.png">
                                                    <button class="px-2 py-1 bg-gray-800 text-white rounded">Saqlash</button>
                                                </form>
                                            </details>
                                        </div>
                                    @endif
                                @else
                                    <span class="text-xs text-gray-500">talab qilinmaydi{{ in_array($order->order_type, $noticeTypes, true) ? ' (ijarada yashamaydi)' : '' }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-3 py-4 text-gray-500">Buyruqlar reyestri bo'sh — «Importlar» bo'limidan yuklang.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $orders->links() }}</div>
        </div>
    </div>
</x-app-layout>
