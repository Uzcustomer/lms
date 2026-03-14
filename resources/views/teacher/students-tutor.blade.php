<x-teacher-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ Auth::guard('teacher')->user()->short_name }} ga tegishli Talabalar ro'yxati
        </h2>
    </x-slot>

    <div style="padding: 16px 0;">
        <div class="max-w-full mx-auto sm:px-4 lg:px-6">

            {{-- Filtrlar --}}
            <div style="background: #fff; border-radius: 12px; padding: 16px; margin-bottom: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); border: 1px solid #e2e8f0;">
                <form action="{{ route('teacher.students') }}" method="GET" style="display: flex; flex-wrap: wrap; gap: 10px; align-items: flex-end;">
                    <div style="flex: 2; min-width: 200px;">
                        <label style="display: block; font-size: 11px; font-weight: 600; color: #64748b; margin-bottom: 4px; text-transform: uppercase;">Qidirish</label>
                        <input type="text" name="search" value="{{ request('search') }}" placeholder="F.I.O yoki ID raqam..." style="width: 100%; height: 36px; border: 1px solid #d1d5db; border-radius: 8px; padding: 0 10px; font-size: 13px; color: #1e293b; outline: none;">
                    </div>
                    <div style="min-width: 160px;">
                        <label style="display: block; font-size: 11px; font-weight: 600; color: #64748b; margin-bottom: 4px; text-transform: uppercase;">Guruh</label>
                        <select name="group" style="width: 100%; height: 36px; border: 1px solid #d1d5db; border-radius: 8px; padding: 0 8px; font-size: 13px; color: #1e293b; background: #fff; outline: none;">
                            <option value="">Barchasi</option>
                            @foreach($tutorGroups as $group)
                                <option value="{{ $group->group_hemis_id }}" {{ request('group') == $group->group_hemis_id ? 'selected' : '' }}>{{ $group->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div style="min-width: 120px;">
                        <label style="display: block; font-size: 11px; font-weight: 600; color: #64748b; margin-bottom: 4px; text-transform: uppercase;">Jinsi</label>
                        <select name="gender" style="width: 100%; height: 36px; border: 1px solid #d1d5db; border-radius: 8px; padding: 0 8px; font-size: 13px; color: #1e293b; background: #fff; outline: none;">
                            <option value="">Barchasi</option>
                            <option value="11" {{ request('gender') == '11' ? 'selected' : '' }}>Erkak</option>
                            <option value="12" {{ request('gender') == '12' ? 'selected' : '' }}>Ayol</option>
                        </select>
                    </div>
                    <div style="min-width: 160px;">
                        <label style="display: block; font-size: 11px; font-weight: 600; color: #64748b; margin-bottom: 4px; text-transform: uppercase;">Viloyat</label>
                        <select name="province" style="width: 100%; height: 36px; border: 1px solid #d1d5db; border-radius: 8px; padding: 0 8px; font-size: 13px; color: #1e293b; background: #fff; outline: none;">
                            <option value="">Barchasi</option>
                            @foreach($provinces as $province)
                                <option value="{{ $province }}" {{ request('province') == $province ? 'selected' : '' }}>{{ $province }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div style="display: flex; gap: 6px;">
                        <button type="submit" style="height: 36px; padding: 0 16px; background: #1e293b; color: #fff; border: none; border-radius: 8px; font-size: 12px; font-weight: 600; cursor: pointer;">
                            Qidirish
                        </button>
                        @if(request()->hasAny(['search', 'group', 'gender', 'province']))
                            <a href="{{ route('teacher.students') }}" style="height: 36px; padding: 0 12px; display: inline-flex; align-items: center; background: #f1f5f9; color: #64748b; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 12px; font-weight: 600; text-decoration: none;">
                                Tozalash
                            </a>
                        @endif
                    </div>
                </form>
            </div>

            {{-- Natija soni --}}
            <div style="margin-bottom: 10px; font-size: 12px; color: #64748b;">
                Jami: <strong style="color: #1e293b;">{{ $students->total() }}</strong> ta talaba
            </div>

            {{-- Talabalar jadvali --}}
            <div style="background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); border: 1px solid #e2e8f0; overflow: hidden;">
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                        <thead>
                            <tr style="background: #f8fafc;">
                                <th style="padding: 10px 14px; text-align: left; font-weight: 600; color: #64748b; border-bottom: 2px solid #e2e8f0; font-size: 11px; text-transform: uppercase;">#</th>
                                <th style="padding: 10px 14px; text-align: left; font-weight: 600; color: #64748b; border-bottom: 2px solid #e2e8f0; font-size: 11px; text-transform: uppercase;">F.I.O</th>
                                <th style="padding: 10px 14px; text-align: left; font-weight: 600; color: #64748b; border-bottom: 2px solid #e2e8f0; font-size: 11px; text-transform: uppercase;">ID raqam</th>
                                <th style="padding: 10px 14px; text-align: left; font-weight: 600; color: #64748b; border-bottom: 2px solid #e2e8f0; font-size: 11px; text-transform: uppercase;">Guruh</th>
                                <th style="padding: 10px 14px; text-align: center; font-weight: 600; color: #64748b; border-bottom: 2px solid #e2e8f0; font-size: 11px; text-transform: uppercase;">Jinsi</th>
                                <th style="padding: 10px 14px; text-align: left; font-weight: 600; color: #64748b; border-bottom: 2px solid #e2e8f0; font-size: 11px; text-transform: uppercase;">Viloyat</th>
                                <th style="padding: 10px 14px; text-align: center; font-weight: 600; color: #64748b; border-bottom: 2px solid #e2e8f0; font-size: 11px; text-transform: uppercase;">GPA</th>
                                <th style="padding: 10px 14px; text-align: left; font-weight: 600; color: #64748b; border-bottom: 2px solid #e2e8f0; font-size: 11px; text-transform: uppercase;">Holat</th>
                                <th style="padding: 10px 14px; text-align: left; font-weight: 600; color: #64748b; border-bottom: 2px solid #e2e8f0; font-size: 11px; text-transform: uppercase;">Telefon</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($students as $index => $student)
                                <tr style="border-bottom: 1px solid #f1f5f9; transition: background 0.15s;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='transparent'">
                                    <td style="padding: 10px 14px; color: #94a3b8; font-size: 12px;">{{ $students->firstItem() + $index }}</td>
                                    <td style="padding: 10px 14px;">
                                        <a href="{{ route('teacher.students.show', $student) }}" style="font-weight: 600; color: #1e293b; text-decoration: none; transition: color 0.15s;" onmouseover="this.style.color='#2563eb'" onmouseout="this.style.color='#1e293b'">{{ $student->full_name }}</a>
                                    </td>
                                    <td style="padding: 10px 14px; color: #64748b; font-family: monospace; font-size: 12px;">{{ $student->student_id_number }}</td>
                                    <td style="padding: 10px 14px;">
                                        <span style="padding: 2px 8px; background: #eff6ff; color: #1e40af; border-radius: 6px; font-size: 11px; font-weight: 600;">{{ $student->group_name }}</span>
                                    </td>
                                    <td style="padding: 10px 14px; text-align: center;">
                                        @if($student->gender_code == '11')
                                            <span style="padding: 2px 8px; background: #dbeafe; color: #1e40af; border-radius: 6px; font-size: 11px; font-weight: 600;">E</span>
                                        @elseif($student->gender_code == '12')
                                            <span style="padding: 2px 8px; background: #fce7f3; color: #9d174d; border-radius: 6px; font-size: 11px; font-weight: 600;">A</span>
                                        @else
                                            <span style="color: #94a3b8;">-</span>
                                        @endif
                                    </td>
                                    <td style="padding: 10px 14px; font-size: 12px; color: #475569;">{{ $student->province_name ?? '-' }}</td>
                                    <td style="padding: 10px 14px; text-align: center;">
                                        @if($student->avg_gpa)
                                            <span style="padding: 2px 6px; border-radius: 4px; font-weight: 700; font-size: 12px; {{ $student->avg_gpa >= 3.5 ? 'background: #dcfce7; color: #166534;' : ($student->avg_gpa >= 2.5 ? 'background: #fef9c3; color: #854d0e;' : 'background: #fee2e2; color: #991b1b;') }}">
                                                {{ number_format($student->avg_gpa, 2) }}
                                            </span>
                                        @else
                                            <span style="color: #94a3b8;">-</span>
                                        @endif
                                    </td>
                                    <td style="padding: 10px 14px;">
                                        @if($student->student_status_code == '11' || $student->student_status_name == 'Faol')
                                            <span style="padding: 2px 8px; background: #dcfce7; color: #166534; border-radius: 6px; font-size: 10px; font-weight: 600;">{{ $student->student_status_name ?? 'Faol' }}</span>
                                        @else
                                            <span style="padding: 2px 8px; background: #fef9c3; color: #854d0e; border-radius: 6px; font-size: 10px; font-weight: 600;">{{ $student->student_status_name ?? '-' }}</span>
                                        @endif
                                    </td>
                                    <td style="padding: 10px 14px; font-size: 12px; color: #475569;">
                                        @if($student->phone)
                                            {{ $student->phone }}
                                        @else
                                            <span style="color: #cbd5e1;">-</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" style="padding: 40px; text-align: center; color: #94a3b8; font-size: 14px;">
                                        Talabalar topilmadi
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($students->hasPages())
                    <div style="padding: 12px 16px; border-top: 1px solid #e2e8f0;">
                        {{ $students->links() }}
                    </div>
                @endif
            </div>

        </div>
    </div>
</x-teacher-app-layout>
