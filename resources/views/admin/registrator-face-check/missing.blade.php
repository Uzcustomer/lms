<x-app-layout>
<x-slot name="header">
    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Face ID yo'q talabalar
    </h2>
</x-slot>

{{-- Stillar shu sahifada: Tailwind faqat Docker build'da yig'iladi --}}
<style>
    .fm-wrap { max-width: 1280px; margin: 0 auto; padding: 24px 16px; }
    .fm-head { display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between; gap: 12px; margin-bottom: 16px; }
    .fm-head h1 { font-size: 20px; font-weight: 700; color: #1f2937; margin: 0; }
    .fm-head p { font-size: 13px; color: #6b7280; margin: 4px 0 0; max-width: 720px; line-height: 1.5; }
    .fm-total { font-size: 28px; font-weight: 800; color: #b91c1c; font-variant-numeric: tabular-nums; line-height: 1; }
    .fm-total small { display: block; font-size: 12px; font-weight: 600; color: #6b7280; margin-top: 4px; text-align: right; }

    .fm-states { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 16px; }
    .fm-state { display: inline-flex; align-items: center; gap: 8px; padding: 7px 12px; border-radius: 999px; border: 1px solid #e5e7eb;
        background: #fff; color: #374151; font-size: 13px; font-weight: 600; text-decoration: none; }
    .fm-state:hover { border-color: #93c5fd; }
    .fm-state b { font-variant-numeric: tabular-nums; }
    .fm-state.active { background: #1e3a8a; border-color: #1e3a8a; color: #fff; }

    .fm-filter { display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end; background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 14px; margin-bottom: 16px; }
    .fm-filter label { display: block; font-size: 12px; color: #6b7280; margin-bottom: 4px; }
    .fm-filter input, .fm-filter select { border: 1px solid #d1d5db; border-radius: 6px; padding: 6px 10px; font-size: 14px; background: #fff; min-width: 160px; }
    .fm-filter .fm-grow { flex: 1; min-width: 200px; }
    .fm-filter .fm-grow input { width: 100%; box-sizing: border-box; }
    .fm-btn { padding: 7px 16px; border: none; border-radius: 6px; background: #2563eb; color: #fff; font-size: 14px; cursor: pointer; }
    .fm-btn:hover { background: #1d4ed8; }
    .fm-reset { font-size: 13px; color: #6b7280; padding: 7px 4px; text-decoration: none; }

    .fm-table-wrap { background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; overflow-x: auto; }
    .fm-table { width: 100%; border-collapse: collapse; font-size: 14px; }
    .fm-table th { text-align: left; padding: 9px 12px; background: #f9fafb; border-bottom: 1px solid #e5e7eb; font-size: 12px; font-weight: 600; color: #6b7280; white-space: nowrap; }
    .fm-table td { padding: 9px 12px; border-bottom: 1px solid #f3f4f6; color: #1f2937; vertical-align: top; }
    .fm-table tr:last-child td { border-bottom: none; }
    .fm-table tr:hover td { background: #f9fafb; }
    .fm-num { color: #9ca3af; font-variant-numeric: tabular-nums; }
    .fm-id { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 13px; }
    .fm-badge { display: inline-block; padding: 2px 9px; border-radius: 999px; font-size: 12px; font-weight: 600; border: 1px solid; white-space: nowrap; }
    .fm-badge.no_photo { background: #fef2f2; color: #b91c1c; border-color: #fecaca; }
    .fm-badge.rejected { background: #fff7ed; color: #c2410c; border-color: #fed7aa; }
    .fm-badge.pending { background: #eff6ff; color: #1d4ed8; border-color: #bfdbfe; }
    .fm-badge.moodle_failed { background: #fdf2f8; color: #be185d; border-color: #fbcfe8; }
    .fm-badge.processing { background: #eef2ff; color: #4338ca; border-color: #c7d2fe; }
    .fm-note { display: block; margin-top: 3px; font-size: 11px; color: #6b7280; max-width: 260px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .fm-go { display: inline-block; padding: 4px 12px; border-radius: 6px; background: #059669; color: #fff; font-size: 12px; text-decoration: none; white-space: nowrap; }
    .fm-go:hover { background: #047857; }
    .fm-empty { padding: 28px 12px; text-align: center; color: #6b7280; }
</style>

<div class="fm-wrap">
    <div class="fm-head">
        <div>
            <h1>Face ID yo'q talabalar</h1>
            <p>
                Test markazida yuz orqali kira olmaydigan faol talabalar: tasdiqlangan rasmi yo'q yoki Moodle yuz deskriptorini hali tasdiqlamagan.
                "Tekshirish" orqali registrator webcam bilan Face ID qo'shishi mumkin.
            </p>
        </div>
        <div class="fm-total">{{ number_format($total, 0, '.', ' ') }}<small>talaba</small></div>
    </div>

    @php
        $stateUrl = fn (?string $key) => request()->fullUrlWithQuery(['state' => $key, 'page' => null]);
    @endphp
    <nav class="fm-states" aria-label="Holat bo'yicha">
        <a href="{{ $stateUrl(null) }}" class="fm-state {{ $state === null ? 'active' : '' }}">Hammasi <b>{{ $total }}</b></a>
        @foreach(\App\Http\Controllers\Admin\RegistratorFaceCheckController::MISSING_STATES as $key => $label)
            <a href="{{ $stateUrl($key) }}" class="fm-state {{ $state === $key ? 'active' : '' }}">{{ $label }} <b>{{ $counts[$key] ?? 0 }}</b></a>
        @endforeach
    </nav>

    <form method="GET" class="fm-filter">
        @if($state)<input type="hidden" name="state" value="{{ $state }}">@endif
        <div class="fm-grow">
            <label for="fm-q">Talaba (F.I.O yoki ID raqam)</label>
            <input id="fm-q" type="text" name="q" value="{{ $search }}" placeholder="Familiya yoki ID...">
        </div>
        <div>
            <label for="fm-department">Fakultet</label>
            <select id="fm-department" name="department">
                <option value="">Hammasi</option>
                @foreach($departments as $department)
                    <option value="{{ $department }}" @selected(request('department') === $department)>{{ $department }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="fm-level">Kurs</label>
            <select id="fm-level" name="level" style="min-width:110px;">
                <option value="">Hammasi</option>
                @foreach($levels as $level)
                    <option value="{{ $level->level_code }}" @selected((string) request('level') === (string) $level->level_code)>{{ $level->level_name ?: $level->level_code }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="fm-group">Guruh</label>
            <input id="fm-group" type="text" name="group" value="{{ request('group') }}" placeholder="Masalan, 101" style="min-width:120px;">
        </div>
        <button type="submit" class="fm-btn">Ko'rsatish</button>
        @if(request()->hasAny(['q', 'department', 'level', 'group', 'state']))
            <a href="{{ route('admin.registrator.face-check.missing') }}" class="fm-reset">Tozalash</a>
        @endif
    </form>

    <div class="fm-table-wrap">
        <table class="fm-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>F.I.O</th>
                    <th>ID raqam</th>
                    <th>Fakultet</th>
                    <th>Kurs</th>
                    <th>Guruh</th>
                    <th>Holat</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($students as $i => $student)
                    @php $st = $states[(string) $student->student_id_number]; @endphp
                    <tr>
                        <td class="fm-num">{{ $students->firstItem() + $i }}</td>
                        <td>{{ $student->full_name }}</td>
                        <td class="fm-id">{{ $student->student_id_number }}</td>
                        <td>{{ $student->department_name }}</td>
                        <td>{{ $student->level_name }}</td>
                        <td>{{ $student->group_name }}</td>
                        <td>
                            <span class="fm-badge {{ $st['key'] }}">{{ $st['label'] }}</span>
                            @if($st['note'])<span class="fm-note" title="{{ $st['note'] }}">{{ $st['note'] }}</span>@endif
                        </td>
                        <td style="text-align:right;">
                            <a href="{{ route('admin.registrator.face-check.show', $student->student_id_number) }}" class="fm-go">Tekshirish</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="fm-empty">Face ID yo'q talaba topilmadi.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:16px;">{{ $students->links() }}</div>
</div>
</x-app-layout>
