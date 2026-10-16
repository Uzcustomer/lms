<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">Monitoring — menga biriktirilgan istisnolar</h2>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            @include('admin.monitoring.partials.nav')

            <p class="text-sm text-gray-600 mb-3">
                Har bir istisno bo'yicha javob yozing va dalil (buyruq nusxasi, xat, skan) yuklang. «Bajarildi» deb belgilaganingizdan
                keyin istisnoni monitoring administratori yopadi.
                <a href="{{ request()->fullUrlWithQuery(['holat' => request('holat') === 'hammasi' ? null : 'hammasi']) }}">
                    {{ request('holat') === 'hammasi' ? 'Faqat ochiqlarini ko\'rsatish' : 'Yopilganlarini ham ko\'rsatish' }}
                </a>
            </p>

            <div class="bg-white rounded-lg shadow overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-left text-gray-600">
                    <tr>
                        <th class="px-3 py-2">Detektor</th>
                        <th class="px-3 py-2">Obyekt</th>
                        <th class="px-3 py-2">Holat</th>
                        <th class="px-3 py-2">Javob muddati</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($istisnolar as $i)
                        <tr class="border-t hover:bg-gray-50">
                            <td class="px-3 py-2 font-semibold">{{ $i->detector?->code }}</td>
                            <td class="px-3 py-2"><a href="{{ route('admin.monitoring.istisnolar.show', $i) }}">{{ $i->subject_label }}</a></td>
                            <td class="px-3 py-2">@include('admin.monitoring.partials.status', ['istisno' => $i])</td>
                            <td class="px-3 py-2">{{ $i->due_at?->format('d.m.Y') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-3 py-4 text-gray-500">Sizga biriktirilgan ochiq istisno yo'q.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $istisnolar->links() }}</div>
        </div>
    </div>
</x-app-layout>
