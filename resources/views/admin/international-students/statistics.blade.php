<x-app-layout>
    <x-slot name="header">
        <div style="display:flex;align-items:center;gap:12px;">
            <a href="{{ route('admin.international-students.index') }}" style="color:#94a3b8;">
                <svg style="width:20px;height:20px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
            </a>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Xalqaro talabalar — Statistika</h2>
        </div>
    </x-slot>

    <div style="padding:20px 0;">
        <div style="max-width:1200px;margin:0 auto;padding:0 16px;">

            {{-- Asosiy raqamlar --}}
            <div style="display:grid;grid-template-columns:repeat(6,1fr);gap:12px;margin-bottom:20px;">
                @foreach([
                    ['Jami', $total, '#2b5ea7', '#eff6ff'],
                    ['Kiritgan', $filled, '#16a34a', '#f0fdf4'],
                    ['Kiritmagan', $notFilled, '#dc2626', '#fef2f2'],
                    ['Tasdiqlangan', $approved, '#059669', '#ecfdf5'],
                    ['Kutilmoqda', $pending, '#d97706', '#fffbeb'],
                    ['Rad etilgan', $rejected, '#dc2626', '#fef2f2'],
                ] as $card)
                <div style="background:{{ $card[3] }};border-radius:10px;padding:16px;text-align:center;">
                    <div style="font-size:28px;font-weight:800;color:{{ $card[2] }};">{{ $card[1] }}</div>
                    <div style="font-size:11px;font-weight:600;color:#64748b;margin-top:4px;">{{ $card[0] }}</div>
                </div>
                @endforeach
            </div>

            {{-- Grafiklar --}}
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px;">
                {{-- Davlat bo'yicha --}}
                <div style="background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:20px;">
                    <div style="font-size:13px;font-weight:700;color:#1e293b;margin-bottom:12px;">Davlat bo'yicha taqsimot</div>
                    <canvas id="countryChart" height="200"></canvas>
                </div>

                {{-- Kurs bo'yicha --}}
                <div style="background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:20px;">
                    <div style="font-size:13px;font-weight:700;color:#1e293b;margin-bottom:12px;">Kurs bo'yicha taqsimot</div>
                    <canvas id="levelChart" height="200"></canvas>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px;">
                {{-- Ma'lumot holati --}}
                <div style="background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:20px;">
                    <div style="font-size:13px;font-weight:700;color:#1e293b;margin-bottom:12px;">Ma'lumot holati</div>
                    <canvas id="statusChart" height="200"></canvas>
                </div>

                {{-- Firma bo'yicha --}}
                <div style="background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:20px;">
                    <div style="font-size:13px;font-weight:700;color:#1e293b;margin-bottom:12px;">Firma bo'yicha taqsimot</div>
                    <canvas id="firmChart" height="200"></canvas>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin-bottom:20px;">
                {{-- Viza muddati --}}
                <div style="background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:20px;">
                    <div style="font-size:13px;font-weight:700;color:#1e293b;margin-bottom:12px;">Viza muddati holati</div>
                    <canvas id="visaChart" height="180"></canvas>
                </div>

                {{-- Registratsiya muddati --}}
                <div style="background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:20px;">
                    <div style="font-size:13px;font-weight:700;color:#1e293b;margin-bottom:12px;">Registratsiya muddati holati</div>
                    <canvas id="regChart" height="180"></canvas>
                </div>

                {{-- Fakultet --}}
                <div style="background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:20px;">
                    <div style="font-size:13px;font-weight:700;color:#1e293b;margin-bottom:12px;">Fakultet bo'yicha</div>
                    <canvas id="deptChart" height="180"></canvas>
                </div>
            </div>
        </div>
    </div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
var colors = ['#4f46e5','#06b6d4','#8b5cf6','#f59e0b','#10b981','#ef4444','#ec4899','#14b8a6','#f97316','#6366f1','#84cc16','#d946ef','#0ea5e9','#a855f7','#22c55e'];

// Davlat
new Chart(document.getElementById('countryChart'), {
    type: 'bar',
    data: {
        labels: {!! json_encode($byCountry->keys()) !!},
        datasets: [{
            data: {!! json_encode($byCountry->values()) !!},
            backgroundColor: colors.slice(0, {{ $byCountry->count() }}),
            borderRadius: 6,
            barThickness: 28,
        }]
    },
    options: { plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true,ticks:{stepSize:1}}} }
});

// Kurs
new Chart(document.getElementById('levelChart'), {
    type: 'bar',
    data: {
        labels: {!! json_encode($byLevel->keys()) !!},
        datasets: [{
            data: {!! json_encode($byLevel->values()) !!},
            backgroundColor: ['#4f46e5','#06b6d4','#8b5cf6','#f59e0b','#10b981','#ef4444'],
            borderRadius: 6,
            barThickness: 36,
        }]
    },
    options: { plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true,ticks:{stepSize:1}}} }
});

// Ma'lumot holati
new Chart(document.getElementById('statusChart'), {
    type: 'doughnut',
    data: {
        labels: ['Kiritgan','Kiritmagan','Tasdiqlangan','Kutilmoqda','Rad etilgan'],
        datasets: [{
            data: [{{ $filled }},{{ $notFilled }},{{ $approved }},{{ $pending }},{{ $rejected }}],
            backgroundColor: ['#10b981','#ef4444','#059669','#f59e0b','#dc2626'],
        }]
    },
    options: { plugins:{legend:{position:'bottom',labels:{font:{size:11}}}} }
});

// Firma
new Chart(document.getElementById('firmChart'), {
    type: 'pie',
    data: {
        labels: {!! json_encode($byFirm->keys()->map(fn($k) => \App\Models\StudentVisaInfo::FIRM_OPTIONS[$k] ?? $k)) !!},
        datasets: [{
            data: {!! json_encode($byFirm->values()) !!},
            backgroundColor: ['#4f46e5','#06b6d4','#f59e0b','#10b981','#ef4444','#8b5cf6'],
        }]
    },
    options: { plugins:{legend:{position:'bottom',labels:{font:{size:11}}}} }
});

// Viza muddati
new Chart(document.getElementById('visaChart'), {
    type: 'doughnut',
    data: {
        labels: ['Muddati tugagan','30 kun ichida','Normal'],
        datasets: [{
            data: [{{ $visaExpired }},{{ $visa30 }},{{ $visaOk }}],
            backgroundColor: ['#dc2626','#f59e0b','#10b981'],
        }]
    },
    options: { plugins:{legend:{position:'bottom',labels:{font:{size:11}}}} }
});

// Registratsiya muddati
new Chart(document.getElementById('regChart'), {
    type: 'doughnut',
    data: {
        labels: ['Muddati tugagan','7 kun ichida','Normal'],
        datasets: [{
            data: [{{ $regExpired }},{{ $reg7 }},{{ $regOk }}],
            backgroundColor: ['#dc2626','#f59e0b','#10b981'],
        }]
    },
    options: { plugins:{legend:{position:'bottom',labels:{font:{size:11}}}} }
});

// Fakultet
new Chart(document.getElementById('deptChart'), {
    type: 'bar',
    data: {
        labels: {!! json_encode($byDept->keys()->map(fn($k) => mb_substr($k, 0, 20))) !!},
        datasets: [{
            data: {!! json_encode($byDept->values()) !!},
            backgroundColor: colors.slice(0, {{ $byDept->count() }}),
            borderRadius: 6,
        }]
    },
    options: { indexAxis:'y', plugins:{legend:{display:false}}, scales:{x:{beginAtZero:true,ticks:{stepSize:1}}} }
});
</script>
</x-app-layout>
