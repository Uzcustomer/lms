<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">Monitoring — detektorlar katalogi</h2>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            @include('admin.monitoring.partials.nav')

            <p class="text-sm text-gray-600 mb-4">
                Katalog <code>docs/detectors.yaml</code> dan yuklanadi. Muddat va chegaralar shu yerda o'zgartiriladi va keyingi
                ishga tushirishda kuchga kiradi; har bir o'zgarish audit jurnaliga yoziladi. Detektorlar har kuni 05:30 da ishlaydi.
            </p>

            @foreach($detectors as $group => $items)
                <div class="bg-white rounded-lg shadow mb-4 overflow-x-auto">
                    <div class="px-4 py-2 bg-gray-50 font-semibold text-sm">{{ $group }}</div>
                    <table class="min-w-full text-sm">
                        <tbody>
                        @foreach($items as $d)
                            <tr class="border-t align-top">
                                <td class="px-3 py-2 font-semibold whitespace-nowrap">{{ $d->code }} <span class="font-normal">{{ $d->catalog_status }}</span></td>
                                <td class="px-3 py-2">
                                    {{ $d->title }}
                                    <div class="text-xs text-gray-500">{{ $d->legal_basis }}</div>
                                </td>
                                <td class="px-3 py-2 whitespace-nowrap">
                                    @if(!$d->isImplemented())
                                        <span class="text-xs text-gray-500">hali amalga oshirilmagan</span>
                                    @elseif($d->enabled)
                                        <span class="text-xs text-green-700">yoqilgan</span>@if($d->warning_mode) <span class="text-xs text-yellow-700">· ogohlantirish</span>@endif
                                    @else
                                        <span class="text-xs text-gray-500">o'chirilgan</span>
                                    @endif
                                    @if($d->pilot)<div class="text-xs text-blue-700">pilot</div>@endif
                                </td>
                                <td class="px-3 py-2 whitespace-nowrap text-right">
                                    @if($d->open_count)<a href="{{ route('admin.monitoring.istisnolar.index', ['detector_id' => $d->id]) }}">{{ $d->open_count }} ochiq</a>@endif
                                    @if($d->lastRun)
                                        <div class="text-xs {{ $d->lastRun->status === 'xato' ? 'text-red-700' : 'text-gray-500' }}">{{ $d->lastRun->started_at->format('d.m H:i') }} · {{ $d->lastRun->status }}</div>
                                    @endif
                                </td>
                                <td class="px-3 py-2 whitespace-nowrap text-right">
                                    @if($canManage)
                                        <a href="{{ route('admin.monitoring.detektorlar.edit', $d) }}" class="text-blue-700">Sozlash</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endforeach
        </div>
    </div>
</x-app-layout>
