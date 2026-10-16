<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">Monitoring — istisnolar</h2>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            @include('admin.monitoring.partials.nav')

            <form method="GET" action="{{ route('admin.monitoring.istisnolar.index') }}" class="bg-white rounded-lg shadow p-4 mb-4 grid grid-cols-1 md:grid-cols-4 gap-3 text-sm">
                <div>
                    <label class="block text-gray-600">Holat</label>
                    <select name="holat" class="w-full border-gray-300 rounded">
                        @foreach(['ochiq' => 'Ochiq (hammasi)', 'yuborilgan' => "Ochiq (mas'ulga yuborilgan)", 'muddati_otgan' => "Muddati o'tgan", 'ogohlantirish' => 'Ogohlantirish', 'javob_berildi' => 'Javob berildi', 'qayta_ochildi' => 'Qayta ochildi', 'yopildi' => 'Yopildi', 'asosli_istisno' => 'Asosli istisno', 'hammasi' => 'Hammasi'] as $value => $label)
                            <option value="{{ $value }}" @selected(request('holat', 'ochiq') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-gray-600">Detektor</label>
                    <select name="detector_id" class="w-full border-gray-300 rounded">
                        <option value="">— hammasi —</option>
                        @foreach($detectors as $d)
                            <option value="{{ $d->id }}" @selected((string) request('detector_id') === (string) $d->id)>{{ $d->code }} — {{ \Illuminate\Support\Str::limit($d->title, 50) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-gray-600">Detektor guruhi</label>
                    <select name="guruh" class="w-full border-gray-300 rounded">
                        <option value="">— hammasi —</option>
                        @foreach($groups as $g)
                            <option value="{{ $g }}" @selected(request('guruh') === $g)>{{ $g }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-gray-600">Fakultet</label>
                    <select name="fakultet" class="w-full border-gray-300 rounded">
                        <option value="">— hammasi —</option>
                        @foreach($faculties as $f)
                            <option value="{{ $f->department_hemis_id }}" @selected((string) request('fakultet') === (string) $f->department_hemis_id)>{{ $f->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-gray-600">Mas'ul</label>
                    <select name="owner" class="mon-staff-select w-full" data-placeholder="Mas'ul bo'yicha">
                        @if(request('owner'))<option value="{{ request('owner') }}" selected>{{ $ownerLabel ?? request('owner') }}</option>@endif
                    </select>
                    <label class="text-xs text-gray-500"><input type="checkbox" name="owner" value="yoq" @checked(request('owner') === 'yoq')> faqat biriktirilmaganlar</label>
                </div>
                <div>
                    <label class="block text-gray-600">Muddat (dan — gacha)</label>
                    <div class="flex gap-1">
                        <input type="date" name="muddat_dan" value="{{ request('muddat_dan') }}" class="w-full border-gray-300 rounded">
                        <input type="date" name="muddat_gacha" value="{{ request('muddat_gacha') }}" class="w-full border-gray-300 rounded">
                    </div>
                </div>
                <div>
                    <label class="block text-gray-600">Qidirish</label>
                    <input type="text" name="q" value="{{ request('q') }}" placeholder="F.I.Sh., guruh, buyruq…" class="w-full border-gray-300 rounded">
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded">Filtrlash</button>
                    <a href="{{ request()->fullUrlWithQuery(['export' => 'excel']) }}" class="px-4 py-2 bg-green-600 text-white rounded" style="text-decoration:none;">Excel</a>
                </div>
            </form>

            <form method="POST" action="{{ route('admin.monitoring.istisnolar.bulk-assign') }}">
                @csrf
                <div class="bg-white rounded-lg shadow overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-left text-gray-600">
                        <tr>
                            @if($canAssign)<th class="px-3 py-2"><input type="checkbox" onclick="document.querySelectorAll('.mon-row-check').forEach(c => c.checked = this.checked)"></th>@endif
                            <th class="px-3 py-2">Detektor</th>
                            <th class="px-3 py-2">Obyekt</th>
                            <th class="px-3 py-2">Holat</th>
                            <th class="px-3 py-2">Topilgan</th>
                            <th class="px-3 py-2">Muddat</th>
                            <th class="px-3 py-2">Mas'ul</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($istisnolar as $i)
                            <tr class="border-t hover:bg-gray-50">
                                @if($canAssign)<td class="px-3 py-2"><input type="checkbox" class="mon-row-check" name="ids[]" value="{{ $i->id }}"></td>@endif
                                <td class="px-3 py-2 font-semibold whitespace-nowrap">{{ $i->detector?->code }}</td>
                                <td class="px-3 py-2"><a href="{{ route('admin.monitoring.istisnolar.show', $i) }}">{{ $i->subject_label }}</a></td>
                                <td class="px-3 py-2 whitespace-nowrap">@include('admin.monitoring.partials.status', ['istisno' => $i])</td>
                                <td class="px-3 py-2 whitespace-nowrap">{{ $i->found_at?->format('d.m.Y') }}</td>
                                <td class="px-3 py-2 whitespace-nowrap">{{ $i->due_at?->format('d.m.Y') }}</td>
                                <td class="px-3 py-2">{{ \App\Services\Monitoring\MonitoringAccess::displayName($i->owner) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-3 py-4 text-gray-500">Istisno topilmadi.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                @if($canAssign && $istisnolar->count())
                    <div class="mt-3 flex flex-wrap items-center gap-2 text-sm">
                        <span>Belgilanganlarga mas'ul:</span>
                        <div style="min-width: 320px;"><select name="owner" class="mon-staff-select"></select></div>
                        <button type="submit" class="px-4 py-2 bg-gray-800 text-white rounded">Biriktirish</button>
                    </div>
                @endif
            </form>

            <div class="mt-4">{{ $istisnolar->links() }}</div>
        </div>
    </div>

    @include('admin.monitoring.partials.staff-select-script')
</x-app-layout>
