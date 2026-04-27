<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.international-students.show', $student) }}" style="color:#94a3b8;transition:color 0.15s;" onmouseover="this.style.color='#475569'" onmouseout="this.style.color='#94a3b8'">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
            </a>
            <h2 style="font-weight:600;font-size:14px;color:#1e293b;">{{ $student->full_name }} — Tarix</h2>
        </div>
    </x-slot>

    <div style="padding:20px 0;">
        <div style="max-width:1100px;margin:0 auto;padding:0 16px;">
            <div style="background:#fff;border-radius:10px;border:1px solid #e2e8f0;padding:14px 18px;display:flex;align-items:center;gap:14px;margin-bottom:14px;">
                <div style="width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,#4f46e5,#6366f1);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;">{{ mb_substr($student->full_name, 0, 1) }}</div>
                <div style="flex:1;">
                    <div style="font-weight:700;color:#0f172a;font-size:15px;">{{ $student->full_name }}</div>
                    <div style="font-size:12px;color:#64748b;margin-top:2px;">
                        <span style="color:#4f46e5;font-weight:600;">{{ $student->group_name }}</span> · {{ $student->level_name }} · {{ $student->country_name ?? '-' }}
                    </div>
                </div>
                <div style="font-size:12px;color:#94a3b8;">Jami: <b style="color:#334155;">{{ $history->count() }}</b></div>
            </div>

            @include('admin.international-students._history-list', ['history' => $history, 'student' => $student])
        </div>
    </div>
</x-app-layout>
