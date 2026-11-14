{{-- O'qituvchining o'z kichik guruh(lar)i: har biri o'z jurnaliga; ✓ — shu shakl qaydnomasini tasdiqlagan --}}
@if(!empty($row->my_groups))
    <span class="yq-mine-list">
        <span class="yq-mine-label">Sizning guruhingiz:</span>
        @foreach($row->my_groups as $g)
            @if($g->pk)
                <a class="yq-mine {{ $g->confirmed ? 'is-done' : '' }}"
                   href="{{ route('admin.journal.show', [$g->pk, $row->subject_id, $row->semester_code] + ($row->form !== '12' ? ['ynq_form' => $row->form] : [])) }}"
                   title="{{ $g->confirmed ? 'Tasdiqlagansiz' : 'Hali tasdiqlamagansiz — jurnalni ochish' }}">{{ $g->name }}@if($g->confirmed) ✓@endif</a>
            @else
                <span class="yq-mine {{ $g->confirmed ? 'is-done' : '' }}">{{ $g->name }}@if($g->confirmed) ✓@endif</span>
            @endif
        @endforeach
    </span>
@endif
