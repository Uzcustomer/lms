<x-teacher-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Dashboard</h2>
    </x-slot>

    <div style="padding: 16px 0;">
        <div class="max-w-full mx-auto sm:px-4 lg:px-6">

            @if(isset($gradingTimeStats) && $gradingTimeStats && $gradingTimeStats['total'] > 0)
                {{-- Baho qo'yish vaqti statistikasi (joriy semestr) --}}
                <div style="margin-bottom: 20px;">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px;">
                        <h3 style="font-size: 15px; font-weight: 700; color: #1e293b; margin: 0;">
                            Baho qo'yish vaqti tahlili
                            <span style="font-size: 11px; font-weight: 500; color: #64748b; margin-left: 6px;">(joriy semestr, faqat joriy baholar; otrabotka, OSKI, test, ON, mustaqil chiqarilgan)</span>
                        </h3>
                        <span style="font-size: 12px; color: #64748b;">
                            Jami: <strong style="color: #1e293b;">{{ $gradingTimeStats['total'] }}</strong> ta baho
                        </span>
                    </div>
                    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px;">
                        {{-- Dars vaqtida --}}
                        <div class="stat-card" style="background: linear-gradient(135deg, #065f46, #10b981);">
                            <div class="stat-icon">
                                <svg style="width: 28px; height: 28px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                </svg>
                            </div>
                            <div class="stat-number">{{ $gradingTimeStats['during_class'] }}</div>
                            <div class="stat-label">Dars vaqtida ({{ $gradingTimeStats['during_class_percent'] }}%)</div>
                        </div>

                        {{-- Ish vaqtida 18:00 ga qadar --}}
                        <div class="stat-card" style="background: linear-gradient(135deg, #78350f, #f59e0b);">
                            <div class="stat-icon">
                                <svg style="width: 28px; height: 28px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <div class="stat-number">{{ $gradingTimeStats['work_hours'] }}</div>
                            <div class="stat-label">18:00 ga qadar ({{ $gradingTimeStats['work_hours_percent'] }}%)</div>
                        </div>

                        {{-- 18:00 dan so'ng --}}
                        <div class="stat-card" style="background: linear-gradient(135deg, #7f1d1d, #ef4444);">
                            <div class="stat-icon">
                                <svg style="width: 28px; height: 28px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
                                </svg>
                            </div>
                            <div class="stat-number">{{ $gradingTimeStats['after_hours'] }}</div>
                            <div class="stat-label">18:00 dan so'ng ({{ $gradingTimeStats['after_hours_percent'] }}%)</div>
                        </div>

                        {{-- Reyting --}}
                        <div class="stat-card" style="background: linear-gradient(135deg, #4c1d95, #8b5cf6);">
                            <div class="stat-icon">
                                <svg style="width: 28px; height: 28px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.196-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118L2.055 10.1c-.783-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                                </svg>
                            </div>
                            <div class="stat-number">
                                @if($gradingTimeStats['rank'])
                                    {{ $gradingTimeStats['rank'] }}
                                    <span style="font-size: 14px; font-weight: 500; opacity: 0.8;">/ {{ $gradingTimeStats['total_teachers'] }}</span>
                                @else
                                    -
                                @endif
                            </div>
                            <div class="stat-label">Umumiy reyting o'rni</div>
                        </div>
                    </div>

                    {{-- Progress bar visualization --}}
                    <div style="margin-top: 12px; background: #fff; border-radius: 10px; padding: 14px 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); border: 1px solid #e2e8f0;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                            <span style="font-size: 12px; font-weight: 600; color: #475569;">Taqsimot</span>
                            <span style="font-size: 11px; color: #94a3b8;">
                                Dars vaqtida: juftlik tugaguncha &nbsp;|&nbsp; Ish vaqtida: juftlik tugagandan 18:00 gacha
                            </span>
                        </div>
                        <div style="display: flex; height: 22px; border-radius: 6px; overflow: hidden; background: #f1f5f9;">
                            @if($gradingTimeStats['during_class'] > 0)
                                <div title="Dars vaqtida: {{ $gradingTimeStats['during_class'] }}"
                                     style="width: {{ $gradingTimeStats['during_class_percent'] }}%; background: linear-gradient(135deg, #10b981, #34d399); display: flex; align-items: center; justify-content: center; font-size: 10px; font-weight: 700; color: #fff;">
                                    @if($gradingTimeStats['during_class_percent'] >= 7){{ $gradingTimeStats['during_class_percent'] }}%@endif
                                </div>
                            @endif
                            @if($gradingTimeStats['work_hours'] > 0)
                                <div title="18:00 ga qadar: {{ $gradingTimeStats['work_hours'] }}"
                                     style="width: {{ $gradingTimeStats['work_hours_percent'] }}%; background: linear-gradient(135deg, #f59e0b, #fbbf24); display: flex; align-items: center; justify-content: center; font-size: 10px; font-weight: 700; color: #fff;">
                                    @if($gradingTimeStats['work_hours_percent'] >= 7){{ $gradingTimeStats['work_hours_percent'] }}%@endif
                                </div>
                            @endif
                            @if($gradingTimeStats['after_hours'] > 0)
                                <div title="18:00 dan so'ng: {{ $gradingTimeStats['after_hours'] }}"
                                     style="width: {{ $gradingTimeStats['after_hours_percent'] }}%; background: linear-gradient(135deg, #ef4444, #f87171); display: flex; align-items: center; justify-content: center; font-size: 10px; font-weight: 700; color: #fff;">
                                    @if($gradingTimeStats['after_hours_percent'] >= 7){{ $gradingTimeStats['after_hours_percent'] }}%@endif
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Top 100 o'qituvchilar --}}
                    @if(!empty($gradingTimeStats['top_list']))
                        <div style="margin-top: 16px; background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); border: 1px solid #e2e8f0; overflow: hidden;">
                            <div style="padding: 14px 16px; border-bottom: 2px solid #ede9fe; background: linear-gradient(135deg, #f5f3ff, #faf5ff); display: flex; justify-content: space-between; align-items: center;">
                                <h3 style="font-size: 14px; font-weight: 700; color: #5b21b6; margin: 0;">
                                    Top 100 o'qituvchilar
                                    <span style="font-size: 11px; font-weight: 500; color: #7c3aed; margin-left: 6px;">(joriy semestr)</span>
                                </h3>
                                <span style="font-size: 11px; color: #7c3aed;">
                                    Ball = Dars vaqtida×1 + 18:00 gacha×0.5 + 18:00 dan so'ng×0
                                </span>
                            </div>
                            <div style="overflow-x: auto;">
                                <table style="width: 100%; border-collapse: collapse; font-size: 12px;">
                                    <thead>
                                        <tr style="background: #f8fafc; color: #475569; text-align: left;">
                                            <th style="padding: 10px 12px; font-weight: 700; border-bottom: 1px solid #e2e8f0; width: 50px;">#</th>
                                            <th style="padding: 10px 12px; font-weight: 700; border-bottom: 1px solid #e2e8f0;">Kafedra</th>
                                            <th style="padding: 10px 12px; font-weight: 700; border-bottom: 1px solid #e2e8f0;">O'qituvchi F.I.SH</th>
                                            <th style="padding: 10px 12px; font-weight: 700; border-bottom: 1px solid #e2e8f0; text-align: center; color: #065f46;" title="Juftlik tugagunicha qo'yilgan">Dars vaqtida</th>
                                            <th style="padding: 10px 12px; font-weight: 700; border-bottom: 1px solid #e2e8f0; text-align: center; color: #92400e;" title="Juftlik tugaganidan keyin 18:00 gacha">18:00 gacha</th>
                                            <th style="padding: 10px 12px; font-weight: 700; border-bottom: 1px solid #e2e8f0; text-align: center; color: #991b1b;" title="Shu kun 18:00 dan keyin">18:00 dan so'ng</th>
                                            <th style="padding: 10px 12px; font-weight: 700; border-bottom: 1px solid #e2e8f0; text-align: center;">Jami</th>
                                            <th style="padding: 10px 12px; font-weight: 700; border-bottom: 1px solid #e2e8f0; text-align: center; color: #5b21b6;">Ball</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($gradingTimeStats['top_list'] as $row)
                                            <tr style="{{ $row['is_me'] ? 'background: #eff6ff; font-weight: 600;' : '' }}">
                                                <td style="padding: 8px 12px; border-bottom: 1px solid #f1f5f9; color: #64748b;">
                                                    @if($row['rank'] === 1)
                                                        <span style="display: inline-block; width: 22px; height: 22px; line-height: 22px; text-align: center; border-radius: 50%; background: linear-gradient(135deg, #f59e0b, #fbbf24); color: #fff; font-weight: 700;">1</span>
                                                    @elseif($row['rank'] === 2)
                                                        <span style="display: inline-block; width: 22px; height: 22px; line-height: 22px; text-align: center; border-radius: 50%; background: linear-gradient(135deg, #94a3b8, #cbd5e1); color: #fff; font-weight: 700;">2</span>
                                                    @elseif($row['rank'] === 3)
                                                        <span style="display: inline-block; width: 22px; height: 22px; line-height: 22px; text-align: center; border-radius: 50%; background: linear-gradient(135deg, #b45309, #d97706); color: #fff; font-weight: 700;">3</span>
                                                    @else
                                                        {{ $row['rank'] }}
                                                    @endif
                                                </td>
                                                <td style="padding: 8px 12px; border-bottom: 1px solid #f1f5f9; color: #475569;">{{ $row['department'] }}</td>
                                                <td style="padding: 8px 12px; border-bottom: 1px solid #f1f5f9; color: #1e293b;">
                                                    {{ $row['full_name'] }}
                                                    @if($row['is_me'])
                                                        <span style="display: inline-block; padding: 1px 6px; font-size: 10px; background: #3b82f6; color: #fff; border-radius: 4px; margin-left: 4px;">siz</span>
                                                    @endif
                                                </td>
                                                <td style="padding: 8px 12px; border-bottom: 1px solid #f1f5f9; text-align: center;">
                                                    <span style="color: #065f46; font-weight: 600;">{{ $row['during_class'] }}</span>
                                                    <span style="color: #94a3b8; font-size: 10px;">({{ $row['during_class_percent'] }}%)</span>
                                                </td>
                                                <td style="padding: 8px 12px; border-bottom: 1px solid #f1f5f9; text-align: center;">
                                                    <span style="color: #92400e; font-weight: 600;">{{ $row['work_hours'] }}</span>
                                                    <span style="color: #94a3b8; font-size: 10px;">({{ $row['work_hours_percent'] }}%)</span>
                                                </td>
                                                <td style="padding: 8px 12px; border-bottom: 1px solid #f1f5f9; text-align: center;">
                                                    <span style="color: #991b1b; font-weight: 600;">{{ $row['after_hours'] }}</span>
                                                    <span style="color: #94a3b8; font-size: 10px;">({{ $row['after_hours_percent'] }}%)</span>
                                                </td>
                                                <td style="padding: 8px 12px; border-bottom: 1px solid #f1f5f9; text-align: center; color: #1e293b; font-weight: 600;">{{ $row['total'] }}</td>
                                                <td style="padding: 8px 12px; border-bottom: 1px solid #f1f5f9; text-align: center; color: #5b21b6; font-weight: 700;">{{ $row['score'] }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif
                </div>
            @endif

            @if(isset($tutorStats) && $tutorStats)
                {{-- Umumiy kartalar --}}
                <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 20px;">
                    <div class="stat-card" style="background: linear-gradient(135deg, #1e40af, #3b82f6);">
                        <div class="stat-icon">
                            <svg style="width: 28px; height: 28px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                            </svg>
                        </div>
                        <div class="stat-number">{{ $tutorStats['totalGroups'] }}</div>
                        <div class="stat-label">Guruhlar</div>
                    </div>
                    <div class="stat-card" style="background: linear-gradient(135deg, #065f46, #10b981);">
                        <div class="stat-icon">
                            <svg style="width: 28px; height: 28px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                            </svg>
                        </div>
                        <div class="stat-number">{{ $tutorStats['totalStudents'] }}</div>
                        <div class="stat-label">Jami talabalar</div>
                    </div>
                    <div class="stat-card" style="background: linear-gradient(135deg, #1e3a5f, #0ea5e9);">
                        <div class="stat-icon">
                            <svg style="width: 28px; height: 28px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                        </div>
                        <div class="stat-number">{{ $tutorStats['maleCount'] }}</div>
                        <div class="stat-label">O'g'il bolalar</div>
                    </div>
                    <div class="stat-card" style="background: linear-gradient(135deg, #831843, #ec4899);">
                        <div class="stat-icon">
                            <svg style="width: 28px; height: 28px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                        </div>
                        <div class="stat-number">{{ $tutorStats['femaleCount'] }}</div>
                        <div class="stat-label">Qiz bolalar</div>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    {{-- Guruhlar bo'yicha statistika --}}
                    <div style="background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); border: 1px solid #e2e8f0; overflow: hidden;">
                        <div style="padding: 14px 16px; border-bottom: 2px solid #dbeafe; background: linear-gradient(135deg, #eff6ff, #f0f9ff);">
                            <h3 style="font-size: 14px; font-weight: 700; color: #1e40af; margin: 0;">Guruhlar bo'yicha</h3>
                        </div>
                        <div style="padding: 16px;">
                            @foreach($tutorStats['groupStats'] as $group)
                                <div style="margin-bottom: 16px;">
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                                        <span style="font-size: 13px; font-weight: 600; color: #1e293b;">{{ $group['name'] }}</span>
                                        <span style="font-size: 12px; color: #64748b;">{{ $group['total'] }} ta</span>
                                    </div>
                                    <div style="display: flex; gap: 4px; height: 24px; border-radius: 6px; overflow: hidden; background: #f1f5f9;">
                                        @if($group['total'] > 0)
                                            <div style="width: {{ ($group['male'] / $group['total']) * 100 }}%; background: linear-gradient(135deg, #3b82f6, #60a5fa); display: flex; align-items: center; justify-content: center; font-size: 10px; font-weight: 700; color: #fff; min-width: {{ $group['male'] > 0 ? '30px' : '0' }};">
                                                @if($group['male'] > 0){{ $group['male'] }}@endif
                                            </div>
                                            <div style="width: {{ ($group['female'] / $group['total']) * 100 }}%; background: linear-gradient(135deg, #ec4899, #f472b6); display: flex; align-items: center; justify-content: center; font-size: 10px; font-weight: 700; color: #fff; min-width: {{ $group['female'] > 0 ? '30px' : '0' }};">
                                                @if($group['female'] > 0){{ $group['female'] }}@endif
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                            <div style="display: flex; gap: 16px; margin-top: 12px; padding-top: 12px; border-top: 1px solid #e2e8f0;">
                                <div style="display: flex; align-items: center; gap: 6px;">
                                    <span style="width: 12px; height: 12px; border-radius: 3px; background: linear-gradient(135deg, #3b82f6, #60a5fa);"></span>
                                    <span style="font-size: 11px; color: #64748b;">O'g'il ({{ $tutorStats['maleCount'] }})</span>
                                </div>
                                <div style="display: flex; align-items: center; gap: 6px;">
                                    <span style="width: 12px; height: 12px; border-radius: 3px; background: linear-gradient(135deg, #ec4899, #f472b6);"></span>
                                    <span style="font-size: 11px; color: #64748b;">Qiz ({{ $tutorStats['femaleCount'] }})</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Viloyatlar bo'yicha statistika --}}
                    <div style="background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); border: 1px solid #e2e8f0; overflow: hidden;">
                        <div style="padding: 14px 16px; border-bottom: 2px solid #fde68a; background: linear-gradient(135deg, #fffbeb, #fef3c7);">
                            <h3 style="font-size: 14px; font-weight: 700; color: #92400e; margin: 0;">Viloyatlar bo'yicha</h3>
                        </div>
                        <div style="padding: 16px;">
                            @php $maxProvince = collect($tutorStats['provinceStats'])->max('count') ?: 1; @endphp
                            @foreach($tutorStats['provinceStats'] as $province)
                                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
                                    <span style="font-size: 12px; color: #1e293b; min-width: 140px; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ $province['name'] }}</span>
                                    <div style="flex: 1; height: 20px; background: #f1f5f9; border-radius: 4px; overflow: hidden;">
                                        <div style="height: 100%; width: {{ ($province['count'] / $maxProvince) * 100 }}%; background: linear-gradient(135deg, #f59e0b, #fbbf24); border-radius: 4px; display: flex; align-items: center; justify-content: flex-end; padding-right: 6px; min-width: 28px;">
                                            <span style="font-size: 10px; font-weight: 700; color: #fff;">{{ $province['count'] }}</span>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- Jinsi bo'yicha donut chart --}}
                <div style="margin-top: 16px; background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); border: 1px solid #e2e8f0; overflow: hidden;">
                    <div style="padding: 14px 16px; border-bottom: 2px solid #d1fae5; background: linear-gradient(135deg, #ecfdf5, #f0fdf4);">
                        <h3 style="font-size: 14px; font-weight: 700; color: #065f46; margin: 0;">Jinsi bo'yicha nisbat</h3>
                    </div>
                    <div style="padding: 24px; display: flex; align-items: center; justify-content: center; gap: 40px;">
                        @php
                            $total = $tutorStats['totalStudents'] ?: 1;
                            $malePercent = round(($tutorStats['maleCount'] / $total) * 100);
                            $femalePercent = 100 - $malePercent;
                            $maleDeg = ($malePercent / 100) * 360;
                        @endphp
                        <div style="position: relative; width: 160px; height: 160px;">
                            <svg viewBox="0 0 36 36" style="width: 160px; height: 160px; transform: rotate(-90deg);">
                                <circle cx="18" cy="18" r="14" fill="none" stroke="#f472b6" stroke-width="5" stroke-dasharray="{{ $femalePercent }} {{ $malePercent }}" stroke-dashoffset="0"/>
                                <circle cx="18" cy="18" r="14" fill="none" stroke="#3b82f6" stroke-width="5" stroke-dasharray="{{ $malePercent }} {{ $femalePercent }}" stroke-dashoffset="-{{ $femalePercent }}"/>
                            </svg>
                            <div style="position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; flex-direction: column;">
                                <span style="font-size: 22px; font-weight: 800; color: #1e293b;">{{ $tutorStats['totalStudents'] }}</span>
                                <span style="font-size: 10px; color: #64748b;">talaba</span>
                            </div>
                        </div>
                        <div style="display: flex; flex-direction: column; gap: 12px;">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <span style="width: 16px; height: 16px; border-radius: 4px; background: #3b82f6;"></span>
                                <div>
                                    <span style="font-size: 18px; font-weight: 800; color: #1e293b;">{{ $tutorStats['maleCount'] }}</span>
                                    <span style="font-size: 12px; color: #64748b; margin-left: 4px;">o'g'il ({{ $malePercent }}%)</span>
                                </div>
                            </div>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <span style="width: 16px; height: 16px; border-radius: 4px; background: #ec4899;"></span>
                                <div>
                                    <span style="font-size: 18px; font-weight: 800; color: #1e293b;">{{ $tutorStats['femaleCount'] }}</span>
                                    <span style="font-size: 12px; color: #64748b; margin-left: 4px;">qiz ({{ $femalePercent }}%)</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            @elseif(!isset($gradingTimeStats) || !$gradingTimeStats || $gradingTimeStats['total'] == 0)
                <div style="background: #fff; border-radius: 12px; padding: 40px; text-align: center; box-shadow: 0 1px 3px rgba(0,0,0,0.08);">
                    <svg style="width: 48px; height: 48px; color: #94a3b8; margin: 0 auto 12px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                    <p style="font-size: 14px; color: #64748b;">Tizimga kirish muvaffaqiyatli amalga oshirildi!</p>
                    <p style="font-size: 12px; color: #94a3b8; margin-top: 4px;">Hozircha sizga biriktirilgan guruhlar yo'q.</p>
                </div>
            @endif

        </div>
    </div>

    <style>
        .stat-card {
            border-radius: 12px;
            padding: 20px;
            color: #fff;
            position: relative;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .stat-icon {
            opacity: 0.7;
            margin-bottom: 8px;
        }
        .stat-number {
            font-size: 32px;
            font-weight: 800;
            line-height: 1.1;
        }
        .stat-label {
            font-size: 12px;
            font-weight: 500;
            opacity: 0.85;
            margin-top: 4px;
        }
        @media (max-width: 768px) {
            .stat-card { padding: 14px; }
            .stat-number { font-size: 24px; }
        }
    </style>
</x-teacher-app-layout>
