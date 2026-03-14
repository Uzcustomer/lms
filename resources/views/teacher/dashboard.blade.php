<x-teacher-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Dashboard</h2>
    </x-slot>

    <div style="padding: 16px 0;">
        <div class="max-w-full mx-auto sm:px-4 lg:px-6">

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

            @else
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
