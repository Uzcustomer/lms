<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Funksiya sozlamalari</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">

            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100" style="background: linear-gradient(135deg, #e8edf5, #dbe4ef);">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background: linear-gradient(135deg, #2b5ea7, #3b82f6);">
                            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        </div>
                        <div>
                            <div class="font-bold text-gray-800 text-sm">Superadmin funksiyalar paneli</div>
                            <div class="text-xs text-gray-500">Funksiyalarni yoqish/o'chirish — kod o'zgartirishsiz</div>
                        </div>
                    </div>
                </div>

                @foreach($toggles as $toggle)
                    <div class="px-6 py-5 flex items-center justify-between border-b border-gray-50 hover:bg-gray-50 transition" id="row-{{ $toggle['key'] }}">
                        <div>
                            <div class="font-semibold text-gray-800 text-sm">{{ $toggle['label'] }}</div>
                            <div class="text-xs text-gray-400 mt-0.5">{{ $toggle['description'] }}</div>
                        </div>
                        <div class="flex items-center gap-3 flex-shrink-0">
                            <span class="text-xs font-semibold" id="status-{{ $toggle['key'] }}" style="color: {{ $toggle['enabled'] ? '#059669' : '#9ca3af' }};">
                                {{ $toggle['enabled'] ? 'Yoqilgan' : 'O\'chirilgan' }}
                            </span>
                            <button type="button"
                                    id="btn-{{ $toggle['key'] }}"
                                    onclick="toggleFeature('{{ $toggle['key'] }}', this)"
                                    class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                                    style="background: {{ $toggle['enabled'] ? '#2b5ea7' : '#d1d5db' }};">
                                <span class="inline-block h-4 w-4 rounded-full bg-white shadow transform transition-transform duration-200"
                                      style="transform: translateX({{ $toggle['enabled'] ? '22px' : '4px' }});"></span>
                            </button>
                        </div>
                    </div>
                @endforeach

                @if(count($toggles) === 0)
                    <div class="px-6 py-8 text-center text-gray-400 text-sm">Funksiyalar mavjud emas</div>
                @endif
            </div>

        </div>
    </div>

    <script>
        function toggleFeature(key, btn) {
            btn.disabled = true;
            const currentlyEnabled = btn.style.background.includes('5ea7');
            const newState = !currentlyEnabled;

            fetch('{{ route("admin.feature-toggles.update") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ key: key, enabled: newState })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    btn.style.background = newState ? '#2b5ea7' : '#d1d5db';
                    btn.querySelector('span').style.transform = newState ? 'translateX(22px)' : 'translateX(4px)';
                    const status = document.getElementById('status-' + key);
                    status.textContent = newState ? 'Yoqilgan' : 'O\'chirilgan';
                    status.style.color = newState ? '#059669' : '#9ca3af';
                } else {
                    alert(data.message || 'Xatolik');
                }
                btn.disabled = false;
            })
            .catch(() => { alert('Tarmoq xatosi'); btn.disabled = false; });
        }
    </script>
</x-app-layout>
