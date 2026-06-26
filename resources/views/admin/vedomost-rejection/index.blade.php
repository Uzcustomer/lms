<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            📥 Vedomost rad etilish bildirgilari
        </h2>
    </x-slot>

    @php
        $formBadge = [
            '12'  => ['#1a3268', '#e0e7ff'],
            '12a' => ['#9a3412', '#ffedd5'],
            '12b' => ['#9d174d', '#fce7f3'],
        ];
        $fmtDate = fn($d) => $d ? \Carbon\Carbon::parse($d)->format('d.m.Y H:i') : '—';
    @endphp

    <div class="py-4">
        <div class="max-w-5xl mx-auto sm:px-4 lg:px-6">

            @if(session('success'))
                <div style="background:#dcfce7;color:#166534;padding:10px 16px;border-radius:8px;margin-bottom:12px;border:1px solid #bbf7d0;">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div style="background:#fee2e2;color:#b91c1c;padding:10px 16px;border-radius:8px;margin-bottom:12px;border:1px solid #fecaca;">{{ session('error') }}</div>
            @endif

            <div class="bg-white rounded-xl shadow-sm border border-gray-100" style="overflow:hidden;">
                {{-- Toolbar --}}
                <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 16px;border-bottom:1px solid #eef2f7;flex-wrap:wrap;">
                    <div style="display:flex;gap:8px;align-items:center;">
                        <a href="{{ route('admin.vedomost-rejections.index') }}"
                           style="padding:6px 14px;border-radius:8px;font-size:13px;text-decoration:none;{{ $filter !== 'unread' ? 'background:#1a3268;color:#fff;' : 'background:#f1f5f9;color:#475569;' }}">
                            Hammasi
                        </a>
                        <a href="{{ route('admin.vedomost-rejections.index', ['filter' => 'unread']) }}"
                           style="padding:6px 14px;border-radius:8px;font-size:13px;text-decoration:none;{{ $filter === 'unread' ? 'background:#1a3268;color:#fff;' : 'background:#f1f5f9;color:#475569;' }}">
                            O'qilmagan
                            @if($unreadCount > 0)
                                <span style="background:#b91c1c;color:#fff;border-radius:999px;padding:1px 7px;font-size:11px;margin-left:4px;">{{ $unreadCount }}</span>
                            @endif
                        </a>
                    </div>
                    @if($unreadCount > 0)
                        <form method="POST" action="{{ route('admin.vedomost-rejections.read-all') }}">
                            @csrf
                            <button type="submit" style="background:#f1f5f9;color:#475569;border:none;padding:6px 14px;border-radius:8px;cursor:pointer;font-size:13px;">
                                ✓ Hammasini o'qilgan deb belgilash
                            </button>
                        </form>
                    @endif
                </div>

                {{-- Ro'yxat (Gmail uslubi) --}}
                @forelse($rows as $r)
                    @php
                        $fb = $formBadge[$r->form_type ?? '12'] ?? $formBadge['12'];
                        $unread = !($r->is_read ?? false);
                    @endphp
                    <div style="display:flex;gap:12px;align-items:flex-start;padding:12px 16px;border-bottom:1px solid #f3f4f6;{{ $unread ? 'background:#fffdf6;' : 'background:#fff;' }}">
                        {{-- O'qilmagan nuqtasi --}}
                        <div style="width:10px;flex-shrink:0;padding-top:5px;">
                            @if($unread)
                                <span style="display:inline-block;width:9px;height:9px;border-radius:50%;background:#2563eb;"></span>
                            @endif
                        </div>

                        <a href="{{ route('admin.vedomost-submission.show', $r->id) }}" style="flex:1;min-width:0;text-decoration:none;color:#1e293b;">
                            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                                <span style="font-weight:{{ $unread ? '700' : '500' }};font-size:14px;">{{ $r->group_name }}</span>
                                <span style="background:{{ $fb[1] }};color:{{ $fb[0] }};padding:1px 8px;border-radius:999px;font-size:11px;font-weight:700;">{{ \App\Models\VedomostSubmission::formLabel($r->form_type ?? '12') }}</span>
                                <span style="color:#94a3b8;font-size:13px;">·</span>
                                <span style="font-weight:{{ $unread ? '700' : '500' }};font-size:14px;">{{ $r->subject_name }}</span>
                            </div>
                            <div style="color:#64748b;font-size:13px;margin-top:2px;">
                                {{ $r->specialty_name }} · {{ $r->department_name ?? '—' }}
                                @if($r->teacher_name) · O'qituvchi: {{ $r->teacher_name }} @endif
                            </div>
                            @if($r->rejection_reason)
                                <div style="color:#b91c1c;font-size:13px;margin-top:4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                    ✕ {{ \Illuminate\Support\Str::limit($r->rejection_reason, 140) }}
                                </div>
                            @endif
                            @if($r->reupload_allowed_at)
                                <div style="color:#047857;font-size:12px;margin-top:3px;">🔓 Qayta yuklashga ruxsat berilgan{{ $r->reupload_allowed_by_name ? ' — '.$r->reupload_allowed_by_name : '' }}</div>
                            @endif
                        </a>

                        <div style="flex-shrink:0;text-align:right;display:flex;flex-direction:column;align-items:flex-end;gap:6px;">
                            <span style="color:#94a3b8;font-size:12px;white-space:nowrap;">{{ $fmtDate($r->reviewed_at) }}</span>
                            @if($unread)
                                <form method="POST" action="{{ route('admin.vedomost-rejections.read', $r->id) }}">
                                    @csrf
                                    <button type="submit"
                                            style="background:#f1f5f9;color:#475569;border:none;padding:3px 10px;border-radius:6px;cursor:pointer;font-size:11px;">
                                        o'qildi
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                @empty
                    <div style="padding:48px 16px;text-align:center;color:#94a3b8;">
                        @if($filter === 'unread')
                            🎉 O'qilmagan bildirgilar yo'q.
                        @else
                            Hozircha rad etilgan vedomostlar yo'q.
                        @endif
                    </div>
                @endforelse
            </div>

            <div style="color:#94a3b8;font-size:12px;margin-top:10px;">
                Bu bildirgilar Telegram sozlamasidan qat'i nazar har doim ko'rsatiladi.
                Vedomostni ochsangiz — avtomatik "o'qilgan" bo'ladi. Rad etilganni qayta yuklash uchun
                vedomost sahifasida "qayta yuklashga ruxsat berish" tugmasidan foydalaning.
            </div>
        </div>
    </div>
</x-app-layout>
