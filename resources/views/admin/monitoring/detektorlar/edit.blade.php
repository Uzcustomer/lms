<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">Detektor {{ $detector->code }} — sozlamalar</h2>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-5xl sm:px-6 lg:px-8">
            @include('admin.monitoring.partials.nav')

            <div class="bg-white rounded-lg shadow p-5 mb-6 text-sm">
                <h3 class="font-semibold mb-1">{{ $detector->title }}</h3>
                <p class="mb-2">{{ $detector->rule_text }}</p>
                <p class="text-gray-600"><b>Asos:</b> {{ $detector->legal_basis ?? '—' }}</p>
                <p class="text-gray-600"><b>Aniqlash:</b> {{ $detector->detection_text ?? '—' }}</p>
                @unless($detector->isImplemented())
                    <p class="mt-2 text-yellow-700">Bu detektor hali amalga oshirilmagan (keyingi bosqichlarda).</p>
                @endunless
            </div>

            <form method="POST" action="{{ route('admin.monitoring.detektorlar.update', $detector) }}" class="bg-white rounded-lg shadow p-5 mb-6 space-y-4 text-sm">
                @csrf
                @method('PUT')

                <div class="flex flex-wrap gap-6">
                    <label><input type="checkbox" name="enabled" value="1" @checked($detector->enabled)> Yoqilgan</label>
                    <label><input type="checkbox" name="warning_mode" value="1" @checked($detector->warning_mode)> Faqat ogohlantirish (mas'ulga yuborilmaydi)</label>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-gray-600">Javob muddati (ish kuni)</label>
                        <input type="number" name="due_workdays" min="1" max="60" value="{{ old('due_workdays', $detector->due_workdays) }}" class="w-full border-gray-300 rounded">
                    </div>
                    <div>
                        <label class="block text-gray-600">Muhimlik</label>
                        <select name="severity" class="w-full border-gray-300 rounded">
                            @foreach(\App\Models\MonitoringDetector::SEVERITIES as $value => $label)
                                <option value="{{ $value }}" @selected($detector->severity === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-gray-600">Standart mas'ul
                            @if($detector->default_owner_role)<span class="text-xs">(avtomatik: {{ \App\Enums\ProjectRole::tryFrom($detector->default_owner_role)?->label() ?? $detector->default_owner_role }})</span>@endif
                        </label>
                        <select name="default_owner" class="mon-staff-select">
                            @if($detector->defaultOwner)<option value="{{ \App\Services\Monitoring\MonitoringAccess::ownerKey($detector->defaultOwner) }}" selected>{{ \App\Services\Monitoring\MonitoringAccess::displayName($detector->defaultOwner) }}</option>@endif
                        </select>
                        <p class="text-xs text-gray-500">Dekan yoki kafedra mudiri aniqlanmagan istisnolar shu xodimga biriktiriladi.</p>
                    </div>
                </div>

                @if($paramFields)
                    <div>
                        <h4 class="font-semibold mb-2">Parametrlar (chegara va muddatlar)</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            @foreach($paramFields as $key => $field)
                                <div>
                                    <label class="block text-gray-600"><code>{{ $key }}</code></label>
                                    @switch($field['type'])
                                        @case('bool')
                                            <input type="hidden" name="params[{{ $key }}]" value="0">
                                            <label><input type="checkbox" name="params[{{ $key }}]" value="1" @checked($field['value'])> ha</label>
                                            @break
                                        @case('int')
                                        @case('float')
                                            <input type="number" step="{{ $field['type'] === 'int' ? 1 : 'any' }}" name="params[{{ $key }}]" value="{{ $field['value'] }}" class="w-full border-gray-300 rounded">
                                            @break
                                        @case('list')
                                            <input type="text" name="params[{{ $key }}]" value="{{ implode(', ', (array) $field['value']) }}" class="w-full border-gray-300 rounded">
                                            <span class="text-xs text-gray-500">vergul bilan ajrating</span>
                                            @break
                                        @case('staff')
                                            <select name="params[{{ $key }}]" class="mon-staff-select">
                                                @if($field['value'])<option value="{{ $field['value'] }}" selected>{{ $field['label'] }}</option>@endif
                                            </select>
                                            @break
                                        @default
                                            <input type="text" name="params[{{ $key }}]" value="{{ $field['value'] }}" class="w-full border-gray-300 rounded">
                                    @endswitch
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="flex gap-2">
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded">Saqlash</button>
                </div>
            </form>

            @if($detector->isImplemented())
                <form method="POST" action="{{ route('admin.monitoring.detektorlar.run', $detector) }}" class="mb-6">
                    @csrf
                    <button type="submit" class="px-4 py-2 bg-gray-800 text-white rounded text-sm">Hozir ishga tushirish</button>
                </form>
            @endif

            <div class="bg-white rounded-lg shadow p-5 text-sm">
                <h4 class="font-semibold mb-2">Oxirgi ishga tushirishlar</h4>
                <table class="min-w-full">
                    <thead><tr class="text-left text-gray-500"><th>Vaqt</th><th>Holat</th><th>Topildi</th><th>Yangi</th><th>Yangilandi</th><th>Yopildi</th><th>Qayta ochildi</th></tr></thead>
                    <tbody>
                    @forelse($runs as $run)
                        <tr class="border-t">
                            <td class="py-1">{{ $run->started_at->format('d.m.Y H:i') }}</td>
                            <td class="py-1 {{ $run->status === 'xato' ? 'text-red-700' : '' }}" title="{{ $run->error_message }}">{{ $run->status }}</td>
                            <td class="py-1">{{ $run->found_count }}</td>
                            <td class="py-1">{{ $run->opened_count }}</td>
                            <td class="py-1">{{ $run->updated_count }}</td>
                            <td class="py-1">{{ $run->closed_count }}</td>
                            <td class="py-1">{{ $run->reopened_count }}</td>
                        </tr>
                        @if($run->status === 'xato')
                            <tr><td colspan="7" class="pb-2 text-xs text-red-700">{{ \Illuminate\Support\Str::limit($run->error_message, 300) }}</td></tr>
                        @endif
                    @empty
                        <tr><td colspan="7" class="py-2 text-gray-500">Hali ishga tushirilmagan.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @include('admin.monitoring.partials.staff-select-script')
</x-app-layout>
