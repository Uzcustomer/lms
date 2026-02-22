<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Server Debug & Monitoring
        </h2>
    </x-slot>

    @if(session('success'))
        <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded">
            {{ session('success') }}
        </div>
    @endif

    {{-- Jonli Ping --}}
    <div class="mb-6 bg-white dark:bg-gray-800 shadow rounded-lg p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Jonli Server Holati</h3>
            <div class="flex gap-2">
                <button onclick="pingServer()" id="pingBtn" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 text-sm">
                    Ping qilish
                </button>
                <button onclick="toggleAutoPing()" id="autoPingBtn" class="px-4 py-2 bg-gray-600 text-white rounded hover:bg-gray-700 text-sm">
                    Auto-ping: OFF
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-4" id="statusCards">
            {{-- Database --}}
            <div class="p-4 rounded-lg border-2" id="card-db">
                <div class="flex items-center justify-between">
                    <span class="font-medium text-gray-700 dark:text-gray-300">Database</span>
                    <span id="status-db" class="text-2xl">
                        @if($server_status['database']['ok']) &#9989; @else &#10060; @endif
                    </span>
                </div>
                <p class="text-sm text-gray-500 mt-1" id="detail-db">
                    @if($server_status['database']['ok'])
                        Ping: {{ $server_status['database']['ping_ms'] ?? '-' }} ms
                    @else
                        {{ $server_status['database']['error'] ?? 'Ulanish yo\'q' }}
                    @endif
                </p>
            </div>

            {{-- Cache --}}
            <div class="p-4 rounded-lg border-2" id="card-cache">
                <div class="flex items-center justify-between">
                    <span class="font-medium text-gray-700 dark:text-gray-300">Cache</span>
                    <span id="status-cache" class="text-2xl">
                        @if($server_status['cache']['ok']) &#9989; @else &#10060; @endif
                    </span>
                </div>
                <p class="text-sm text-gray-500 mt-1" id="detail-cache">Driver: {{ config('cache.default') }}</p>
            </div>

            {{-- Disk --}}
            <div class="p-4 rounded-lg border-2" id="card-disk">
                <div class="flex items-center justify-between">
                    <span class="font-medium text-gray-700 dark:text-gray-300">Disk</span>
                    <span id="status-disk" class="text-2xl">
                        @if($server_status['disk']['ok']) &#9989; @else &#9888;&#65039; @endif
                    </span>
                </div>
                <p class="text-sm text-gray-500 mt-1" id="detail-disk">
                    Bo'sh: {{ $server_status['disk']['free_gb'] }}GB / {{ $server_status['disk']['total_gb'] }}GB
                    ({{ $server_status['disk']['used_percent'] }}%)
                </p>
            </div>

            {{-- Memory --}}
            <div class="p-4 rounded-lg border-2" id="card-memory">
                <div class="flex items-center justify-between">
                    <span class="font-medium text-gray-700 dark:text-gray-300">Memory</span>
                    <span id="status-memory" class="text-2xl">&#9989;</span>
                </div>
                <p class="text-sm text-gray-500 mt-1" id="detail-memory">-</p>
            </div>
        </div>

        <p class="text-xs text-gray-400 mt-3" id="lastPingTime">Oxirgi tekshirish: -</p>
    </div>

    {{-- Database Ma'lumotlari --}}
    <div class="mb-6 bg-white dark:bg-gray-800 shadow rounded-lg p-6">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Database Ma'lumotlari</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <table class="min-w-full text-sm">
                <tbody>
                    @foreach($db_info as $key => $value)
                        <tr class="border-b">
                            <td class="py-2 px-3 font-medium text-gray-700 dark:text-gray-300">{{ $key }}</td>
                            <td class="py-2 px-3 text-gray-600 dark:text-gray-400">{{ $value }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <table class="min-w-full text-sm">
                <tbody>
                    @foreach($php_info as $key => $value)
                        <tr class="border-b">
                            <td class="py-2 px-3 font-medium text-gray-700 dark:text-gray-300">{{ $key }}</td>
                            <td class="py-2 px-3 text-gray-600 dark:text-gray-400">{{ $value }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Connection Debug Loglari --}}
    <div class="mb-6 bg-white dark:bg-gray-800 shadow rounded-lg p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Connection Debug Loglari</h3>
            <form action="{{ route('admin.server-debug.clear-logs') }}" method="POST"
                  onsubmit="return confirm('Loglarni tozalashni tasdiqlaysizmi?')">
                @csrf
                <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700 text-sm">
                    Loglarni tozalash
                </button>
            </form>
        </div>

        @if(empty($recent_logs))
            <p class="text-gray-500 text-center py-8">Hozircha debug log mavjud emas. Muammo yuz berganda loglar shu yerda ko'rinadi.</p>
        @else
            <div class="overflow-x-auto max-h-96 overflow-y-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-700 sticky top-0">
                        <tr>
                            <th class="py-2 px-3 text-left text-gray-700 dark:text-gray-300">Vaqt</th>
                            <th class="py-2 px-3 text-left text-gray-700 dark:text-gray-300">Daraja</th>
                            <th class="py-2 px-3 text-left text-gray-700 dark:text-gray-300">Xabar</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recent_logs as $log)
                            <tr class="border-b {{ $log['level'] === 'ERROR' ? 'bg-red-50 dark:bg-red-900/20' : ($log['level'] === 'WARNING' ? 'bg-yellow-50 dark:bg-yellow-900/20' : '') }}">
                                <td class="py-2 px-3 text-gray-600 dark:text-gray-400 whitespace-nowrap">{{ $log['time'] }}</td>
                                <td class="py-2 px-3">
                                    @if($log['level'] === 'ERROR')
                                        <span class="px-2 py-1 bg-red-100 text-red-800 rounded text-xs font-medium">ERROR</span>
                                    @elseif($log['level'] === 'WARNING')
                                        <span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded text-xs font-medium">WARNING</span>
                                    @else
                                        <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs font-medium">{{ $log['level'] }}</span>
                                    @endif
                                </td>
                                <td class="py-2 px-3 text-gray-700 dark:text-gray-300 text-xs font-mono">{{ $log['message'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Maslahatlar --}}
    <div class="mb-6 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-6">
        <h3 class="text-lg font-semibold text-blue-800 dark:text-blue-200 mb-3">Muammo aniqlash bo'yicha maslahatlar</h3>
        <ul class="list-disc list-inside text-sm text-blue-700 dark:text-blue-300 space-y-2">
            <li><strong>DATABASE ULANISH YO'QOLDI</strong> — MySQL server to'xtagan yoki ulanishlar limiti to'lgan. <code>Aborted_connects</code> qiymatini tekshiring.</li>
            <li><strong>SEKIN REQUEST</strong> — 3 sekunddan oshgan requestlar. Ko'p query yoki katta data yuklanishi sabab bo'lishi mumkin.</li>
            <li><strong>SESSION/CSRF (419)</strong> — Foydalanuvchi sessiyasi tugagan. Session lifetime ({{ config('session.lifetime') }} daqiqa) ni oshirish kerak bo'lishi mumkin.</li>
            <li><strong>SERVER XATOLIK (5xx)</strong> — PHP xatolik. <code>storage/logs/laravel.log</code> faylini tekshiring.</li>
            <li><strong>Terminal buyrug'i:</strong> <code>php artisan server:health-check --continuous --log</code> — doimiy monitoring rejimi.</li>
        </ul>
    </div>

    @push('scripts')
    <script>
        let autoPingInterval = null;

        function pingServer() {
            const btn = document.getElementById('pingBtn');
            btn.disabled = true;
            btn.textContent = 'Tekshirilmoqda...';

            fetch('{{ route("admin.server-debug.ping") }}')
                .then(response => response.json())
                .then(data => {
                    // Database
                    updateCard('db', data.database.status === 'ok',
                        data.database.status === 'ok'
                            ? 'Ping: ' + data.database.ping_ms + ' ms'
                            : data.database.message);

                    // Cache
                    updateCard('cache', data.cache.status === 'ok',
                        data.cache.status === 'ok'
                            ? 'Ping: ' + data.cache.ping_ms + ' ms'
                            : data.cache.message);

                    // Disk
                    updateCard('disk', data.disk.status === 'ok',
                        'Bo\'sh: ' + data.disk.free_gb + ' GB');

                    // Memory
                    updateCard('memory', true,
                        'Joriy: ' + data.memory.usage_mb + 'MB, Pik: ' + data.memory.peak_mb + 'MB');

                    document.getElementById('lastPingTime').textContent = 'Oxirgi tekshirish: ' + data.timestamp;
                })
                .catch(error => {
                    updateCard('db', false, 'Serverga ulanib bo\'lmadi: ' + error.message);
                    document.getElementById('lastPingTime').textContent = 'XATOLIK: Serverga ulanish yo\'q! ' + new Date().toLocaleString();
                })
                .finally(() => {
                    btn.disabled = false;
                    btn.textContent = 'Ping qilish';
                });
        }

        function updateCard(id, isOk, detail) {
            const card = document.getElementById('card-' + id);
            const status = document.getElementById('status-' + id);
            const detailEl = document.getElementById('detail-' + id);

            status.innerHTML = isOk ? '&#9989;' : '&#10060;';
            detailEl.textContent = detail;
            card.className = 'p-4 rounded-lg border-2 ' + (isOk ? 'border-green-200 bg-green-50' : 'border-red-200 bg-red-50');
        }

        function toggleAutoPing() {
            const btn = document.getElementById('autoPingBtn');
            if (autoPingInterval) {
                clearInterval(autoPingInterval);
                autoPingInterval = null;
                btn.textContent = 'Auto-ping: OFF';
                btn.className = 'px-4 py-2 bg-gray-600 text-white rounded hover:bg-gray-700 text-sm';
            } else {
                pingServer();
                autoPingInterval = setInterval(pingServer, 10000); // Har 10 sekundda
                btn.textContent = 'Auto-ping: ON (10s)';
                btn.className = 'px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 text-sm';
            }
        }
    </script>
    @endpush
</x-app-layout>
