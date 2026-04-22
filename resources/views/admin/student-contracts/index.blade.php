<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Ishga joylashish shartnomasi</h2>
    </x-slot>

    <style>
        .sc-stat { cursor: pointer; transition: all 0.2s; position: relative; }
        .sc-stat:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        .sc-stat.active::after { content: ''; position: absolute; bottom: -2px; left: 15%; right: 15%; height: 3px; border-radius: 3px; }
        .sc-table { font-size: 13px; border-collapse: separate; width: 100%; }
        .sc-table thead { background: linear-gradient(135deg, #e8edf5, #dbe4ef); position: sticky; top: 0; z-index: 1; }
        .sc-table th { padding: 10px 14px; font-weight: 600; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #475569; border-bottom: 2px solid #cbd5e1; }
        .sc-table tbody tr { transition: all 0.15s; }
        .sc-table tbody tr:nth-child(even) { background: #f8fafc; }
        .sc-table tbody tr:hover { background: #eff6ff; box-shadow: inset 4px 0 0 #2b5ea7; }
        .sc-table td { padding: 10px 14px; border-bottom: 1px solid #f1f5f9; }
    </style>

    <div class="py-4">
        <div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8">

            @if(session('success'))
                <div class="mb-4 p-3 bg-emerald-50 border border-emerald-200 rounded-xl text-sm text-emerald-700 font-medium">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-xl text-sm text-red-700 font-medium">{{ session('error') }}</div>
            @endif

            {{-- Stat cards --}}
            <div style="display: flex; gap: 12px; margin-bottom: 20px;">
                <a href="{{ route('admin.student-contracts.index') }}" class="sc-stat {{ !request('status') ? 'bg-blue-50 border-blue-300 active shadow-sm' : 'bg-white border-gray-200' }}" style="flex: 1; display: flex; align-items: center; gap: 12px; padding: 18px 16px; border-radius: 12px; border-width: 1px; text-decoration: none;">
                    <div style="width: 40px; height: 40px; border-radius: 8px; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #2b5ea7, #3b82f6); flex-shrink: 0;">
                        <svg style="width: 20px; height: 20px; color: #fff;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                    </div>
                    <div>
                        <div style="font-size: 20px; font-weight: 700; color: #1a3268;">{{ $statusCounts['all'] }}</div>
                        <div style="font-size: 11px; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em;">Jami</div>
                    </div>
                </a>
                <a href="{{ route('admin.student-contracts.index', ['status' => 'pending']) }}" class="sc-stat {{ request('status') === 'pending' ? 'bg-amber-50 border-amber-300 active shadow-sm' : 'bg-white border-gray-200' }}" style="flex: 1; display: flex; align-items: center; gap: 12px; padding: 18px 16px; border-radius: 12px; border-width: 1px; text-decoration: none;">
                    <div style="width: 40px; height: 40px; border-radius: 8px; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #d97706, #f59e0b); flex-shrink: 0;">
                        <svg style="width: 20px; height: 20px; color: #fff;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <div>
                        <div style="font-size: 20px; font-weight: 700; color: #b45309;">{{ $statusCounts['pending'] }}</div>
                        <div style="font-size: 11px; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em;">Kutilmoqda</div>
                    </div>
                </a>
                <a href="{{ route('admin.student-contracts.index', ['status' => 'registrar_review']) }}" class="sc-stat {{ request('status') === 'registrar_review' ? 'bg-blue-50 border-blue-300 active shadow-sm' : 'bg-white border-gray-200' }}" style="flex: 1; display: flex; align-items: center; gap: 12px; padding: 18px 16px; border-radius: 12px; border-width: 1px; text-decoration: none;">
                    <div style="width: 40px; height: 40px; border-radius: 8px; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #1e40af, #3b82f6); flex-shrink: 0;">
                        <svg style="width: 20px; height: 20px; color: #fff;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                    </div>
                    <div>
                        <div style="font-size: 20px; font-weight: 700; color: #1e40af;">{{ $statusCounts['registrar_review'] }}</div>
                        <div style="font-size: 11px; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em;">Ko'rilmoqda</div>
                    </div>
                </a>
                <a href="{{ route('admin.student-contracts.index', ['status' => 'approved']) }}" class="sc-stat {{ request('status') === 'approved' ? 'bg-emerald-50 border-emerald-300 active shadow-sm' : 'bg-white border-gray-200' }}" style="flex: 1; display: flex; align-items: center; gap: 12px; padding: 18px 16px; border-radius: 12px; border-width: 1px; text-decoration: none;">
                    <div style="width: 40px; height: 40px; border-radius: 8px; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #059669, #10b981); flex-shrink: 0;">
                        <svg style="width: 20px; height: 20px; color: #fff;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <div>
                        <div style="font-size: 20px; font-weight: 700; color: #059669;">{{ $statusCounts['approved'] }}</div>
                        <div style="font-size: 11px; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em;">Tasdiqlangan</div>
                    </div>
                </a>
                <a href="{{ route('admin.student-contracts.index', ['status' => 'rejected']) }}" class="sc-stat {{ request('status') === 'rejected' ? 'bg-red-50 border-red-300 active shadow-sm' : 'bg-white border-gray-200' }}" style="flex: 1; display: flex; align-items: center; gap: 12px; padding: 18px 16px; border-radius: 12px; border-width: 1px; text-decoration: none;">
                    <div style="width: 40px; height: 40px; border-radius: 8px; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #dc2626, #ef4444); flex-shrink: 0;">
                        <svg style="width: 20px; height: 20px; color: #fff;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <div>
                        <div style="font-size: 20px; font-weight: 700; color: #dc2626;">{{ $statusCounts['rejected'] }}</div>
                        <div style="font-size: 11px; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em;">Rad etilgan</div>
                    </div>
                </a>
            </div>

            {{-- Filter --}}
            <form method="GET" action="{{ route('admin.student-contracts.index') }}" class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 mb-4" style="background: linear-gradient(135deg, #f0f4f8, #e8edf5); border-bottom: 2px solid #dbe4ef;">
                <div class="flex gap-3 items-end">
                    <div class="flex-1">
                        <label class="text-[11px] font-bold text-gray-500 uppercase tracking-wide mb-1 block">Qidiruv</label>
                        <input type="text" name="search" value="{{ request('search') }}" placeholder="FIO, HEMIS ID, guruh..."
                               style="height: 36px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; padding: 0 12px; width: 100%; outline: none;"
                               onfocus="this.style.borderColor='#2b5ea7'; this.style.boxShadow='0 0 0 2px rgba(43,94,167,0.15)'" onblur="this.style.borderColor='#cbd5e1'; this.style.boxShadow='none'">
                    </div>
                    <div style="width: 180px;">
                        <label class="text-[11px] font-bold text-gray-500 uppercase tracking-wide mb-1 block">Shartnoma turi</label>
                        <select name="contract_type" style="height: 36px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; padding: 0 10px; width: 100%; outline: none; background: #fff;">
                            <option value="">Barchasi</option>
                            <option value="3_tomonlama" {{ request('contract_type') === '3_tomonlama' ? 'selected' : '' }}>3 tomonlama</option>
                            <option value="4_tomonlama" {{ request('contract_type') === '4_tomonlama' ? 'selected' : '' }}>4 tomonlama</option>
                        </select>
                    </div>
                    <div style="width: 220px;">
                        <label class="text-[11px] font-bold text-gray-500 uppercase tracking-wide mb-1 block">Fakultet</label>
                        <select name="department" style="height: 36px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; padding: 0 10px; width: 100%; outline: none; background: #fff;">
                            <option value="">Barchasi</option>
                            @foreach($departments as $dept)
                                <option value="{{ $dept }}" {{ request('department') === $dept ? 'selected' : '' }}>{{ $dept }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <button type="submit" style="height: 36px; padding: 0 24px; background: linear-gradient(135deg, #2b5ea7, #3b82f6); color: #fff; font-size: 12px; font-weight: 600; border: none; border-radius: 8px; cursor: pointer; transition: all 0.2s;"
                                onmouseover="this.style.boxShadow='0 4px 12px rgba(43,94,167,0.3)'" onmouseout="this.style.boxShadow='none'">
                            Qidirish
                        </button>
                    </div>
                </div>
                @if(request('status'))
                    <input type="hidden" name="status" value="{{ request('status') }}">
                @endif
            </form>

            {{-- Table --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="sc-table">
                        <thead>
                            <tr>
                                <th class="text-center" style="width: 40px;">#</th>
                                <th class="text-left">Talaba</th>
                                <th class="text-left">Guruh</th>
                                <th class="text-left">Fakultet</th>
                                <th class="text-center">Turi</th>
                                <th class="text-center">Holati</th>
                                <th class="text-left">Sana</th>
                                <th class="text-center">Amallar</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($contracts as $i => $contract)
                                <tr>
                                    <td class="text-center text-gray-400 font-medium">{{ $contracts->firstItem() + $i }}</td>
                                    <td>
                                        <div class="font-semibold text-gray-800">{{ $contract->student_full_name }}</div>
                                        <div class="text-[11px] text-gray-400">{{ $contract->student_hemis_id }}</div>
                                    </td>
                                    <td class="text-gray-600">{{ $contract->group_name }}</td>
                                    <td class="text-gray-500 text-xs">{{ $contract->department_name }}</td>
                                    <td class="text-center">
                                        <span class="inline-flex px-2.5 py-1 rounded-md text-[11px] font-semibold {{ $contract->contract_type === '4_tomonlama' ? 'bg-purple-50 text-purple-700' : 'bg-blue-50 text-blue-700' }}">
                                            {{ $contract->type_label }}
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        @php
                                            $sc = [
                                                'pending' => ['bg' => '#fef3c7', 'color' => '#92400e'],
                                                'registrar_review' => ['bg' => '#dbeafe', 'color' => '#1e40af'],
                                                'approved' => ['bg' => '#d1fae5', 'color' => '#065f46'],
                                                'rejected' => ['bg' => '#fee2e2', 'color' => '#991b1b'],
                                            ];
                                            $s = $sc[$contract->status] ?? $sc['pending'];
                                        @endphp
                                        <span class="inline-flex px-2.5 py-1 rounded-md text-[11px] font-semibold" style="background: {{ $s['bg'] }}; color: {{ $s['color'] }};">{{ $contract->status_label }}</span>
                                    </td>
                                    <td class="text-gray-500 text-xs">{{ $contract->created_at->format('d.m.Y H:i') }}</td>
                                    <td class="text-center">
                                        <div class="flex items-center justify-center gap-2">
                                            @if(in_array($contract->status, ['pending', 'registrar_review']))
                                                <a href="{{ route('admin.student-contracts.review', $contract) }}" class="inline-flex items-center gap-1 px-3 py-1.5 text-[11px] font-semibold rounded-md text-white" style="background: linear-gradient(135deg, #2b5ea7, #3b82f6);">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                                    Ko'rib chiqish
                                                </a>
                                            @else
                                                <a href="{{ route('admin.student-contracts.show', $contract) }}" class="inline-flex items-center gap-1 px-3 py-1.5 text-[11px] font-semibold rounded-md text-gray-600 bg-gray-100 hover:bg-gray-200 transition">Ko'rish</a>
                                            @endif
                                            @if($contract->status === 'approved' && $contract->document_path)
                                                <a href="{{ route('admin.student-contracts.download', $contract) }}" class="inline-flex items-center gap-1 px-3 py-1.5 text-[11px] font-semibold rounded-md text-white" style="background: linear-gradient(135deg, #059669, #10b981);">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                                                    Yuklab olish
                                                </a>
                                            @endif
                                            <form method="POST" action="{{ route('admin.student-contracts.destroy', $contract) }}" onsubmit="return confirm('Haqiqatan o\'chirmoqchimisiz? Talabadan ham o\'chadi.')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="inline-flex items-center gap-1 px-3 py-1.5 text-[11px] font-semibold rounded-md text-red-600 bg-red-50 hover:bg-red-100 transition border border-red-200">O'chirish</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center" style="padding: 40px;">
                                        <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                        <div class="text-gray-400 text-sm">Shartnoma topilmadi</div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($contracts->hasPages())
                    <div class="px-4 py-3 border-t border-gray-200">{{ $contracts->links() }}</div>
                @endif
            </div>

        </div>
    </div>
</x-app-layout>
