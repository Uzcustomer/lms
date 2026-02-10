<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            Sozlamalar
        </h2>
    </x-slot>

    <div class="py-8">
        <div style="max-width: 1280px; margin: 0 auto; padding: 0 24px;">

            @if (session('success'))
                <div style="padding: 12px 16px; margin-bottom: 16px; font-size: 14px; color: #15803d; background-color: #f0fdf4; border-radius: 8px; border: 1px solid #bbf7d0;">
                    {{ session('success') }}
                </div>
            @endif

            @if (session('error'))
                <div style="padding: 12px 16px; margin-bottom: 16px; font-size: 14px; color: #b91c1c; background-color: #fef2f2; border-radius: 8px; border: 1px solid #fecaca;">
                    {{ session('error') }}
                </div>
            @endif

            <div style="display: grid; grid-template-columns: 220px 1fr; gap: 32px;">
                {{-- Sidebar --}}
                <nav style="display: flex; flex-direction: column; gap: 4px; position: sticky; top: 24px;">
                    <a href="{{ route('admin.settings', ['tab' => 'deadlines']) }}"
                       style="display: flex; align-items: center; padding: 10px 14px; font-size: 14px; font-weight: 500; border-radius: 6px; text-decoration: none; {{ $tab === 'deadlines' ? 'background-color: #eff6ff; color: #1d4ed8; border-left: 3px solid #2563eb;' : 'color: #374151; border-left: 3px solid transparent;' }}"
                       onmouseover="if('{{ $tab }}' !== 'deadlines') this.style.backgroundColor='#f3f4f6'" onmouseout="if('{{ $tab }}' !== 'deadlines') this.style.backgroundColor='transparent'">
                        <svg style="width: 20px; height: 20px; margin-right: 12px; flex-shrink: 0; color: {{ $tab === 'deadlines' ? '#2563eb' : '#9ca3af' }};" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                        Muddatlar
                    </a>

                    <a href="{{ route('admin.settings', ['tab' => 'password']) }}"
                       style="display: flex; align-items: center; padding: 10px 14px; font-size: 14px; font-weight: 500; border-radius: 6px; text-decoration: none; {{ $tab === 'password' ? 'background-color: #eff6ff; color: #1d4ed8; border-left: 3px solid #2563eb;' : 'color: #374151; border-left: 3px solid transparent;' }}"
                       onmouseover="if('{{ $tab }}' !== 'password') this.style.backgroundColor='#f3f4f6'" onmouseout="if('{{ $tab }}' !== 'password') this.style.backgroundColor='transparent'">
                        <svg style="width: 20px; height: 20px; margin-right: 12px; flex-shrink: 0; color: {{ $tab === 'password' ? '#2563eb' : '#9ca3af' }};" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                        </svg>
                        Parol sozlamalari
                    </a>

                    <a href="{{ route('admin.settings', ['tab' => 'telegram']) }}"
                       style="display: flex; align-items: center; padding: 10px 14px; font-size: 14px; font-weight: 500; border-radius: 6px; text-decoration: none; {{ $tab === 'telegram' ? 'background-color: #eff6ff; color: #1d4ed8; border-left: 3px solid #2563eb;' : 'color: #374151; border-left: 3px solid transparent;' }}"
                       onmouseover="if('{{ $tab }}' !== 'telegram') this.style.backgroundColor='#f3f4f6'" onmouseout="if('{{ $tab }}' !== 'telegram') this.style.backgroundColor='transparent'">
                        <svg style="width: 20px; height: 20px; margin-right: 12px; flex-shrink: 0; color: {{ $tab === 'telegram' ? '#2563eb' : '#9ca3af' }};" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                        </svg>
                        Telegram
                    </a>

                    <a href="{{ route('admin.settings', ['tab' => 'sync']) }}"
                       style="display: flex; align-items: center; padding: 10px 14px; font-size: 14px; font-weight: 500; border-radius: 6px; text-decoration: none; {{ $tab === 'sync' ? 'background-color: #eff6ff; color: #1d4ed8; border-left: 3px solid #2563eb;' : 'color: #374151; border-left: 3px solid transparent;' }}"
                       onmouseover="if('{{ $tab }}' !== 'sync') this.style.backgroundColor='#f3f4f6'" onmouseout="if('{{ $tab }}' !== 'sync') this.style.backgroundColor='transparent'">
                        <svg style="width: 20px; height: 20px; margin-right: 12px; flex-shrink: 0; color: {{ $tab === 'sync' ? '#2563eb' : '#9ca3af' }};" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                        Sinxronizatsiya
                    </a>
                </nav>

                {{-- Content --}}
                <div style="min-width: 0;">
                    @if($tab === 'deadlines')
                        @include('admin.settings.partials.deadlines')
                    @elseif($tab === 'password')
                        @include('admin.settings.partials.password')
                    @elseif($tab === 'telegram')
                        @include('admin.settings.partials.telegram')
                    @elseif($tab === 'sync')
                        @include('admin.settings.partials.sync')
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
