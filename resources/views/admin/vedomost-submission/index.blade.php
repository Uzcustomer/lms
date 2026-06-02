<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            Vedomost topshirilish holati
        </h2>
    </x-slot>

    @php
        $statusBadge = [
            'pending'   => ['Kutilmoqda', '#475569', '#f1f5f9'],
            'received'  => ['Qabul qilindi', '#1d4ed8', '#dbeafe'],
            'reviewing' => ['Tekshirilmoqda', '#b45309', '#fef3c7'],
            'approved'  => ['Tasdiqlandi', '#166534', '#dcfce7'],
            'rejected'  => ['Rad etildi', '#b91c1c', '#fee2e2'],
        ];
        $closingFormLabels = [
            'oski' => 'Faqat OSKI', 'test' => 'Faqat Test', 'oski_test' => 'OSKI + Test',
            'normativ' => 'Normativ', 'sinov' => 'Sinov (test)',
        ];
    @endphp

    <div class="py-4">
        <div class="max-w-full mx-auto sm:px-4 lg:px-6">
            @if(session('success'))
                <div style="background:#dcfce7;color:#166534;padding:10px 16px;border-radius:8px;margin-bottom:12px;border:1px solid #bbf7d0;">
                    {{ session('success') }}
                </div>
            @endif
            @if(session('error'))
                <div style="background:#fee2e2;color:#b91c1c;padding:10px 16px;border-radius:8px;margin-bottom:12px;border:1px solid #fecaca;">
                    {{ session('error') }}
                </div>
            @endif

            {{-- Statistika --}}
            <div style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:14px;">
                @foreach($statusBadge as $key => $b)
                    <div style="flex:1;min-width:130px;background:{{ $b[2] }};border-radius:10px;padding:12px 16px;">
                        <div style="font-size:12px;color:{{ $b[1] }};font-weight:600;">{{ $b[0] }}</div>
                        <div style="font-size:22px;font-weight:700;color:{{ $b[1] }};">{{ $stats[$key] ?? 0 }}</div>
                    </div>
                @endforeach
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-100" style="padding:16px;">
                {{-- Filtrlar --}}
                <form method="GET" action="{{ route('admin.vedomost-submission.index') }}"
                      style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;margin-bottom:14px;">
                    <div style="min-width:220px;">
                        <label style="font-size:12px;color:#64748b;">Fakultet</label>
                        <select name="faculty" class="select2" style="width:100%;">
                            <option value="">Barchasi</option>
                            @foreach($faculties as $f)
                                <option value="{{ $f->id }}" {{ request('faculty') == $f->id ? 'selected' : '' }}>{{ $f->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div style="min-width:160px;">
                        <label style="font-size:12px;color:#64748b;">Status</label>
                        <select name="status" class="select2" style="width:100%;">
                            <option value="">Barchasi</option>
                            @foreach(\App\Models\VedomostSubmission::statusLabels() as $k => $label)
                                <option value="{{ $k }}" {{ request('status') === $k ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div style="min-width:160px;">
                        <label style="font-size:12px;color:#64748b;">Yopilish shakli</label>
                        <select name="closing_form" class="select2" style="width:100%;">
                            <option value="">Barchasi</option>
                            @foreach($closingForms as $k => $label)
                                <option value="{{ $k }}" {{ request('closing_form') === $k ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div style="min-width:220px;">
                        <label style="font-size:12px;color:#64748b;">Qidirish (fan/guruh/o'qituvchi)</label>
                        <input type="text" name="search" value="{{ request('search') }}" placeholder="Qidirish..."
                               style="width:100%;padding:7px 10px;border:1px solid #e2e8f0;border-radius:8px;">
                    </div>
                    <label style="display:flex;align-items:center;gap:6px;font-size:13px;color:#334155;padding-bottom:8px;">
                        <input type="checkbox" name="overdue" value="1" {{ request('overdue') ? 'checked' : '' }}>
                        Faqat kechikkanlar
                    </label>
                    <button type="submit" class="btn-search"
                            style="background:#1a3268;color:#fff;border:none;padding:8px 18px;border-radius:8px;cursor:pointer;">
                        Qidirish
                    </button>
                </form>

                {{-- Generatsiya --}}
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
                    <span style="font-size:13px;color:#64748b;">Jami: {{ $submissions->total() }} ta</span>
                    <form method="POST" action="{{ route('admin.vedomost-submission.sync', request()->query()) }}"
                          onsubmit="return confirm('Joriy semestr bo\'yicha vedomost yozuvlari yangilansinmi?');">
                        @csrf
                        <button type="submit"
                                style="background:#1d6f42;color:#fff;border:none;padding:8px 16px;border-radius:8px;cursor:pointer;">
                            ↻ Joriy semestr bo'yicha yangilash
                        </button>
                    </form>
                </div>

                <div style="overflow-x:auto;">
                    <table class="journal-table" style="width:100%;border-collapse:collapse;font-size:13px;">
                        <thead>
                            <tr style="background:#f8fafc;text-align:left;">
                                <th style="padding:8px;">#</th>
                                <th style="padding:8px;">Guruh</th>
                                <th style="padding:8px;">Fan</th>
                                <th style="padding:8px;">Kafedra</th>
                                <th style="padding:8px;">O'qituvchi</th>
                                <th style="padding:8px;">Yopilish</th>
                                <th style="padding:8px;">Asos sana</th>
                                <th style="padding:8px;">Muddat (deadline)</th>
                                <th style="padding:8px;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($submissions as $i => $v)
                                <tr style="border-top:1px solid #f1f5f9;">
                                    <td style="padding:8px;color:#94a3b8;">{{ $submissions->firstItem() + $i }}</td>
                                    <td style="padding:8px;font-weight:600;">{{ $v->group_name }}</td>
                                    <td style="padding:8px;">{{ $v->subject_name }}</td>
                                    <td style="padding:8px;color:#64748b;">{{ $v->department_name }}</td>
                                    <td style="padding:8px;color:#64748b;">{{ $v->teacher_name ?? '—' }}</td>
                                    <td style="padding:8px;">{{ $closingFormLabels[$v->closing_form] ?? $v->closing_form }}</td>
                                    <td style="padding:8px;">
                                        {{ $v->base_date ? $v->base_date->format('d.m.Y') : '—' }}
                                        <div style="font-size:10px;color:#94a3b8;">
                                            {{ $v->base_type === 'lesson' ? 'oxirgi dars' : ($v->base_type === 'exam' ? 'YN sanasi' : '') }}
                                        </div>
                                    </td>
                                    <td style="padding:8px;">
                                        @if($v->deadline)
                                            <span style="{{ $v->is_overdue ? 'color:#b91c1c;font-weight:700;' : 'color:#334155;' }}">
                                                {{ $v->deadline->format('d.m.Y') }}
                                            </span>
                                            @if($v->is_overdue)
                                                <div style="font-size:10px;color:#b91c1c;">kechikkan</div>
                                            @endif
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td style="padding:8px;">
                                        @php $b = $statusBadge[$v->status] ?? ['—','#475569','#f1f5f9']; @endphp
                                        <span style="background:{{ $b[2] }};color:{{ $b[1] }};padding:3px 10px;border-radius:999px;font-size:12px;font-weight:600;white-space:nowrap;">
                                            {{ $b[0] }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" style="padding:40px;text-align:center;color:#94a3b8;">
                                        Ma'lumot yo'q. "Joriy semestr bo'yicha yangilash" tugmasini bosing.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div style="margin-top:14px;">
                    {{ $submissions->links() }}
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
