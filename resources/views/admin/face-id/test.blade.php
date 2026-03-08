@extends('layouts.app')
@section('title', 'Face ID — Sinov sahifasi')

@push('styles')
<style>
.tab-btn { padding:8px 16px; border:1px solid #e2e8f0; border-radius:8px; background:#f8fafc; color:#64748b; font-size:13px; cursor:pointer; transition:all .15s; }
.tab-btn.active { background:#6366f1; color:#fff; border-color:#6366f1; font-weight:600; }
.tab-pane { display:none; }
.tab-pane.active { display:block; }
.metric-card { background:#1e293b; border-radius:8px; padding:10px; }
.metric-label { font-size:10px; color:#64748b; margin-bottom:4px; }
.metric-val { font-size:1.35rem; font-weight:700; }
.metric-sub { font-size:10px; color:#64748b; margin-top:2px; }
.person-row { display:flex; align-items:center; gap:8px; padding:7px 8px; border-radius:8px; background:#f8fafc; margin-bottom:5px; }
.person-row.match { background:#dcfce7; border:1px solid #86efac; }
.person-row.fail  { background:#fef2f2; border:1px solid #fecaca; }
.person-badge { font-size:10px; padding:2px 6px; border-radius:10px; font-weight:600; }
.badge-student { background:#ede9fe; color:#5b21b6; }
.badge-teacher { background:#fff7ed; color:#9a3412; }
</style>
@endpush

@section('content')
<div style="max-width:1180px; margin:0 auto; padding:20px 16px; font-family:sans-serif;">

    <!-- Sarlavha -->
    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:16px;">
        <div>
            <h1 style="font-size:1.3rem; font-weight:700; color:#1e293b; margin:0;">🧪 Face ID — Sinov sahifasi</h1>
            <p style="font-size:12px; color:#64748b; margin:3px 0 0;">Faqat admin uchun · Hech qanday login amalga oshirilmaydi</p>
        </div>
        <div style="display:flex; gap:8px; align-items:center;">
            <span style="font-size:11px; color:#94a3b8;">
                {{ $enrolledCount }} descriptor · {{ $totalStudents }} talaba · {{ $totalTeachers }} xodim
            </span>
            <a href="{{ route('admin.face-id.settings') }}"
               style="padding:7px 14px; background:#f1f5f9; color:#475569; border-radius:8px; font-size:13px; text-decoration:none; border:1px solid #e2e8f0;">
                ⚙️ Sozlamalar
            </a>
        </div>
    </div>

    <!-- Tablar -->
    <div style="display:flex; gap:8px; margin-bottom:14px;">
        <button class="tab-btn active" data-tab="debug">🔬 Sinov / Debug</button>
        <button class="tab-btn" data-tab="recognize">👥 Yuzlarni tanish</button>
    </div>

    <!-- ═══════════ TAB 1: DEBUG ═══════════ -->
    <div class="tab-pane active" id="tab-debug">
    <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; align-items:start;">

        <!-- Chap: Kamera -->
        <div>
            <!-- Talaba tanlash -->
            <div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:14px; margin-bottom:12px;">
                <label style="font-size:12px; font-weight:600; color:#475569; display:block; margin-bottom:6px;">Talaba ID raqami</label>
                <div style="display:flex; gap:8px;">
                    <input type="text" id="inp-student-id" placeholder="Masalan: 20202005010028"
                           style="flex:1; padding:8px 10px; border:1px solid #cbd5e1; border-radius:8px; font-size:13px; outline:none;">
                    <button id="btn-load-student"
                            style="padding:8px 14px; background:#3b82f6; color:#fff; border:none; border-radius:8px; font-size:13px; cursor:pointer; white-space:nowrap;">
                        Yuklash
                    </button>
                </div>
                <div id="student-info-box" style="display:none; margin-top:10px; padding:10px; background:#f0f9ff; border-radius:8px; border:1px solid #bae6fd;">
                    <div style="display:flex; align-items:center; gap:10px;">
                        <img id="ref-img-preview" src="" alt="" style="width:48px; height:48px; border-radius:50%; object-fit:cover; border:2px solid #38bdf8;" onerror="this.style.opacity=0.3">
                        <div>
                            <div id="student-name-label" style="font-weight:600; font-size:13px; color:#0c4a6e;"></div>
                            <div id="student-enroll-label" style="font-size:11px; color:#0369a1; margin-top:2px;"></div>
                        </div>
                    </div>
                </div>
                <div id="student-error" style="display:none; margin-top:8px; padding:8px 10px; background:#fef2f2; color:#b91c1c; border-radius:8px; font-size:12px;"></div>
            </div>

            <!-- Kamera -->
            <div style="background:#000; border-radius:12px; overflow:hidden; position:relative; aspect-ratio:4/3;">
                <video id="video" autoplay playsinline muted style="width:100%; height:100%; object-fit:cover; transform:scaleX(-1); display:block;"></video>
                <canvas id="overlay" style="position:absolute; inset:0; width:100%; height:100%; transform:scaleX(-1); pointer-events:none;"></canvas>
                <div id="oval" style="position:absolute; top:50%; left:50%; transform:translate(-50%,-55%);
                     width:42%; padding-bottom:53%; border:3px solid rgba(255,255,255,0.4);
                     border-radius:50%; pointer-events:none; transition:border-color 0.3s; box-sizing:border-box;"></div>
                <div id="no-camera-msg" style="position:absolute; inset:0; display:flex; align-items:center; justify-content:center; color:#94a3b8; font-size:13px;">
                    Kamera yuklanmagan
                </div>
            </div>

            <!-- Tugmalar -->
            <div style="display:flex; gap:8px; margin-top:10px;">
                <button id="btn-start-camera" style="flex:1; padding:9px; background:#10b981; color:#fff; border:none; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer;">📷 Kamerani yoqish</button>
                <button id="btn-capture" disabled style="flex:1; padding:9px; background:#6366f1; color:#fff; border:none; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer; opacity:0.5;">🔍 Taqqoslash</button>
                <button id="btn-stop-camera" style="padding:9px 14px; background:#f1f5f9; color:#475569; border:1px solid #e2e8f0; border-radius:8px; font-size:13px; cursor:pointer;">⏹</button>
            </div>

            <!-- Liveness -->
            <div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:14px; margin-top:12px;">
                <div style="font-size:12px; font-weight:600; color:#475569; margin-bottom:10px;">Liveness test</div>
                <div style="display:flex; gap:8px;">
                    <button id="btn-test-blink" style="flex:1; padding:8px; background:#fef9c3; color:#713f12; border:1px solid #fde047; border-radius:8px; font-size:12px; cursor:pointer;">👁 Ko'z</button>
                    <button id="btn-test-head"  style="flex:1; padding:8px; background:#ede9fe; color:#4c1d95; border:1px solid #c4b5fd; border-radius:8px; font-size:12px; cursor:pointer;">🔄 Bosh</button>
                    <button id="btn-test-full"  style="flex:1; padding:8px; background:#dcfce7; color:#14532d; border:1px solid #86efac; border-radius:8px; font-size:12px; cursor:pointer;">✅ To'liq</button>
                </div>
                <div id="liveness-status" style="margin-top:10px; padding:10px; background:#f8fafc; border-radius:8px; font-size:13px; text-align:center; min-height:36px; color:#475569;">Liveness testi boshlanmagan</div>
                <div style="margin-top:8px; background:#e2e8f0; border-radius:4px; height:6px;">
                    <div id="liveness-bar" style="height:6px; border-radius:4px; background:#6366f1; width:0%; transition:width 0.3s;"></div>
                </div>
            </div>
        </div>

        <!-- O'ng: Debug panel -->
        <div>
            <!-- Parametrlar -->
            <div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:14px; margin-bottom:12px;">
                <div style="font-size:12px; font-weight:600; color:#475569; margin-bottom:12px;">⚙️ Parametrlar (real-vaqt)</div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                    <div>
                        <label style="font-size:11px; color:#64748b;">Threshold (distance)</label>
                        <div style="display:flex; align-items:center; gap:6px; margin-top:3px;">
                            <input type="range" id="cfg-threshold" min="0.25" max="0.70" step="0.01" value="{{ $settings['threshold'] }}" style="flex:1; accent-color:#6366f1;">
                            <span id="cfg-threshold-val" style="font-size:12px; font-weight:700; color:#6366f1; min-width:36px;">{{ $settings['threshold'] }}</span>
                        </div>
                        <div id="threshold-pct" style="font-size:11px; color:#94a3b8; margin-top:1px;">≈ {{ round((1 - $settings['threshold']/0.6) * 100) }}% yaqinlik</div>
                    </div>
                    <div>
                        <label style="font-size:11px; color:#64748b;">Ko'z yumish soni</label>
                        <select id="cfg-blinks" style="width:100%; margin-top:3px; padding:5px 8px; border:1px solid #cbd5e1; border-radius:6px; font-size:12px;">
                            @foreach([0,1,2,3] as $n)
                            <option value="{{ $n }}" {{ $settings['blinks_required'] == $n ? 'selected' : '' }}>
                                {{ $n }} marta{{ $n===0?' (o\'ch.)':'' }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label style="font-size:11px; color:#64748b;">Bosh burish</label>
                        <label style="display:flex; align-items:center; gap:6px; margin-top:5px; cursor:pointer;">
                            <input type="checkbox" id="cfg-head-turn" {{ $settings['head_turn_required'] ? 'checked' : '' }} style="width:14px; height:14px; accent-color:#6366f1;">
                            <span style="font-size:12px; color:#475569;">Talab qilinsin</span>
                        </label>
                    </div>
                    <div>
                        <label style="font-size:11px; color:#64748b;">Vaqt chegarasi (s)</label>
                        <input type="number" id="cfg-timeout" value="{{ $settings['liveness_timeout'] }}" min="5" max="120"
                               style="width:100%; margin-top:3px; padding:5px 8px; border:1px solid #cbd5e1; border-radius:6px; font-size:12px;">
                    </div>
                </div>
            </div>

            <!-- Metrikalar -->
            <div style="background:#0f172a; border-radius:12px; padding:14px; color:#e2e8f0; font-family:monospace; font-size:12px;">
                <div style="font-size:11px; font-weight:600; color:#94a3b8; margin-bottom:10px; font-family:sans-serif;">📊 Real-time metrikalar</div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-bottom:10px;">
                    <div class="metric-card">
                        <div class="metric-label">YUZ TOPILDI</div>
                        <div id="m-face" class="metric-val">—</div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-label">TEZLIK</div>
                        <div id="m-fps" class="metric-val" style="color:#38bdf8;">—</div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-label">EAR (ko'z)</div>
                        <div id="m-ear" class="metric-val" style="color:#a78bfa;">—</div>
                        <div id="m-ear-status" class="metric-sub">—</div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-label">YAW (bosh)</div>
                        <div id="m-yaw" class="metric-val" style="color:#fb923c;">—</div>
                        <div id="m-yaw-status" class="metric-sub">—</div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-label">DISTANCE</div>
                        <div id="m-distance" class="metric-val">—</div>
                        <div id="m-distance-result" class="metric-sub">—</div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-label">YAQINLIK %</div>
                        <div id="m-conf" class="metric-val">—</div>
                    </div>
                </div>
                <div class="metric-card" style="margin-bottom:8px;">
                    <div class="metric-label">KO'Z YUMISH</div>
                    <div style="display:flex; align-items:center; gap:6px; flex-wrap:wrap; margin-top:4px;">
                        <span id="m-blink-count" style="font-size:1.2rem; font-weight:700; color:#4ade80;">0</span>
                        <span style="color:#475569; font-size:11px;">marta</span>
                        <span id="m-blink-dots" style="color:#4ade80; font-size:16px; letter-spacing:2px;"></span>
                    </div>
                </div>
                <div class="metric-card">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                        <span class="metric-label" style="margin:0;">LOG</span>
                        <button onclick="document.getElementById('log-box').innerHTML=''" style="font-size:10px; color:#475569; background:none; border:none; cursor:pointer;">tozalash</button>
                    </div>
                    <div id="log-box" style="height:130px; overflow-y:auto; font-size:11px; line-height:1.7; color:#94a3b8;">
                        <span style="color:#475569;">Kamerani yoqing...</span>
                    </div>
                </div>
            </div>

            <!-- Taqqoslash natijasi -->
            <div id="result-box" style="display:none; background:#fff; border-radius:12px; padding:14px; margin-top:12px; border:2px solid #e2e8f0;">
                <div style="font-size:12px; font-weight:600; color:#475569; margin-bottom:10px;">🔍 Taqqoslash natijasi</div>
                <div id="result-content" style="text-align:center; padding:10px;"></div>
                <div id="snapshot-preview" style="margin-top:10px; display:none;">
                    <div style="font-size:11px; color:#94a3b8; margin-bottom:4px;">Snapshot:</div>
                    <img id="snapshot-img" style="width:100%; border-radius:8px; border:1px solid #e2e8f0;">
                </div>
            </div>
        </div>
    </div>
    </div>{{-- /tab-debug --}}

    <!-- ═══════════ TAB 2: TANISH ═══════════ -->
    <div class="tab-pane" id="tab-recognize">
    <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; align-items:start;">

        <!-- Chap: Kamera + tanish natijasi -->
        <div>
            <!-- Kamera (umumiy bo'lsa ham bu tabda ham ishlaydi) -->
            <div style="background:#000; border-radius:12px; overflow:hidden; position:relative; aspect-ratio:4/3;">
                <video id="video2" autoplay playsinline muted style="width:100%; height:100%; object-fit:cover; transform:scaleX(-1); display:block;"></video>
                <canvas id="overlay2" style="position:absolute; inset:0; width:100%; height:100%; transform:scaleX(-1); pointer-events:none;"></canvas>
                <div id="no-camera-msg2" style="position:absolute; inset:0; display:flex; align-items:center; justify-content:center; color:#94a3b8; font-size:13px;">
                    Kamera yuklanmagan
                </div>
                <!-- Tanish natijasi overlay -->
                <div id="recog-overlay" style="position:absolute; bottom:0; left:0; right:0; padding:10px 14px;
                     background:linear-gradient(transparent, rgba(0,0,0,0.7)); display:none;">
                    <div id="recog-name" style="font-size:1rem; font-weight:700; color:#fff; text-shadow:0 1px 3px rgba(0,0,0,0.8);"></div>
                    <div id="recog-sub"  style="font-size:12px; color:rgba(255,255,255,0.8); margin-top:2px;"></div>
                </div>
            </div>

            <div style="display:flex; gap:8px; margin-top:10px;">
                <button id="btn-start-recog"
                        style="flex:1; padding:9px; background:#10b981; color:#fff; border:none; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer;">
                    📷 Kamerani yoqish
                </button>
                <button id="btn-stop-recog"
                        style="padding:9px 14px; background:#f1f5f9; color:#475569; border:1px solid #e2e8f0; border-radius:8px; font-size:13px; cursor:pointer;">
                    ⏹ To'xtatish
                </button>
            </div>

            <!-- Joriy tanish natijalari -->
            <div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:14px; margin-top:12px;">
                <div style="font-size:12px; font-weight:600; color:#475569; margin-bottom:10px;">
                    🎯 So'nggi tanishlar
                    <span id="recog-live-badge" style="margin-left:6px; padding:2px 8px; background:#dcfce7; color:#16a34a; border-radius:10px; font-size:10px; display:none;">● JONLI</span>
                </div>
                <div id="recog-history" style="font-size:12px; color:#94a3b8;">Tanish hali boshlanmagan</div>
            </div>
        </div>

        <!-- O'ng: Pool boshqaruv -->
        <div>

            <!-- Talabalar yuklab olish -->
            <div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:14px; margin-bottom:12px;">
                <div style="font-size:12px; font-weight:600; color:#475569; margin-bottom:10px;">📚 Talabalar bazadan (enrolled)</div>
                <div style="display:flex; gap:8px; align-items:center; margin-bottom:8px;">
                    <div style="flex:1; font-size:12px; color:#64748b;">
                        DB da <strong>{{ $enrolledCount }}</strong> ta descriptor mavjud
                    </div>
                    <button id="btn-load-all-students"
                            style="padding:8px 14px; background:#6366f1; color:#fff; border:none; border-radius:8px; font-size:12px; cursor:pointer; white-space:nowrap;">
                        ⬇️ Yuklash
                    </button>
                </div>
                <div id="students-load-status" style="font-size:11px; color:#94a3b8;"></div>
                <div style="margin-top:6px; background:#e2e8f0; border-radius:4px; height:4px;">
                    <div id="students-load-bar" style="height:4px; border-radius:4px; background:#6366f1; width:0%; transition:width 0.3s;"></div>
                </div>
            </div>

            <!-- Xodim qo'shish -->
            <div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:14px; margin-bottom:12px;">
                <div style="font-size:12px; font-weight:600; color:#475569; margin-bottom:10px;">👷 Xodim qo'shish</div>
                <div style="display:flex; gap:8px;">
                    <input type="text" id="inp-teacher-id" placeholder="Xodim ID (employee_id_number)"
                           style="flex:1; padding:8px 10px; border:1px solid #cbd5e1; border-radius:8px; font-size:13px; outline:none;">
                    <button id="btn-add-teacher"
                            style="padding:8px 14px; background:#f59e0b; color:#fff; border:none; border-radius:8px; font-size:13px; cursor:pointer; white-space:nowrap;">
                        ➕ Qo'shish
                    </button>
                </div>
                <div id="teacher-msg" style="display:none; margin-top:8px; padding:8px; border-radius:6px; font-size:12px;"></div>
            </div>

            <!-- Pool jadvali -->
            <div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:14px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                    <div style="font-size:12px; font-weight:600; color:#475569;">
                        👥 Yuklangan shaxslar
                        <span id="pool-count-badge" style="margin-left:6px; padding:2px 8px; background:#ede9fe; color:#5b21b6; border-radius:10px; font-size:11px;">0</span>
                    </div>
                    <button id="btn-clear-pool" style="font-size:11px; color:#ef4444; background:none; border:none; cursor:pointer;">🗑 Tozalash</button>
                </div>
                <div id="pool-list" style="max-height:300px; overflow-y:auto;">
                    <div style="font-size:12px; color:#94a3b8; text-align:center; padding:20px;">
                        Hali hech kim yuklanmagan
                    </div>
                </div>
            </div>

            <!-- Tanish sozlamalari -->
            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:12px; margin-top:12px;">
                <div style="font-size:11px; font-weight:600; color:#475569; margin-bottom:8px;">Tanish threshold</div>
                <div style="display:flex; align-items:center; gap:8px;">
                    <input type="range" id="recog-threshold" min="0.25" max="0.70" step="0.01" value="{{ $settings['threshold'] }}"
                           style="flex:1; accent-color:#6366f1;">
                    <span id="recog-threshold-val" style="font-size:12px; font-weight:700; color:#6366f1; min-width:36px;">{{ $settings['threshold'] }}</span>
                </div>
                <div style="font-size:11px; color:#94a3b8; margin-top:4px;">
                    Bu qiymatdan katta distance = "Noma'lum" deb hisoblanadi
                </div>
            </div>
        </div>
    </div>
    </div>{{-- /tab-recognize --}}

    <!-- Statistika -->
    <div style="display:grid; grid-template-columns:repeat(4,1fr); gap:10px; margin-top:16px;">
        <div style="background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:12px; text-align:center;">
            <div style="font-size:1.4rem; font-weight:700; color:#6366f1;">{{ $enrolledCount }}</div>
            <div style="font-size:11px; color:#64748b; margin-top:3px;">Descriptor saqlangan</div>
        </div>
        <div style="background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:12px; text-align:center;">
            <div style="font-size:1.4rem; font-weight:700; color:#0ea5e9;">{{ $totalStudents }}</div>
            <div style="font-size:11px; color:#64748b; margin-top:3px;">Rasmi bor talabalar</div>
        </div>
        <div style="background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:12px; text-align:center;">
            <div style="font-size:1.4rem; font-weight:700; color:#f59e0b;">{{ $totalTeachers }}</div>
            <div style="font-size:11px; color:#64748b; margin-top:3px;">Rasmi bor xodimlar</div>
        </div>
        <div style="background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:12px; text-align:center;">
            <div style="font-size:1.4rem; font-weight:700; color:#10b981;" id="stat-session-ok">0</div>
            <div style="font-size:11px; color:#64748b; margin-top:3px;">Seans muvaffaqiyatli</div>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
<script>
(function () {
'use strict';

const MODELS_PATH    = '/face-models';
const CSRF           = document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}';
const CHECK_STUDENT  = '{{ route('student.face-id.check-student') }}';
const PHOTO_STUDENT  = '{{ route('student.face-id.photo', ['id' => ':id']) }}';
const SAVE_DESC_URL  = '{{ route('student.face-id.save-descriptor') }}';
const CHECK_TEACHER  = '{{ route('admin.face-id.check-teacher') }}';
const ALL_DESC_URL   = '{{ route('admin.face-id.all-descriptors') }}';

// ─── Shared state ────────────────────────────────────────────────────────────
let modelsLoaded = false;
let debugStream  = null;   // debug tab stream
let recogStream  = null;   // recognize tab stream
let debugLoop    = null;
let recogLoop    = null;

let refDescriptor = null;  // debug mode: single person
let blinkCount    = 0;
let eyeClosed     = false;
let sessionOk     = 0;

// Recognition pool: [{id, name, idNumber, type, descriptor: Float32Array, photoUrl}]
let pool = [];

// Liveness state (debug tab)
let liveness = { running: false, phase: 'idle', blinksDone: 0, _eyeClosed: false,
                 headDone: false, dir: 'left', startTs: 0, passed: false };

let lastFrameTs = 0;
const EAR_CLOSED = 0.22, EAR_OPEN = 0.28, YAW_THRESH = 0.18;

const $ = id => document.getElementById(id);
const OPTS224 = () => new faceapi.TinyFaceDetectorOptions({ inputSize:224, scoreThreshold:0.4 });
const OPTS320 = () => new faceapi.TinyFaceDetectorOptions({ inputSize:320, scoreThreshold:0.5 });

// ─── Tabs ───────────────────────────────────────────────────────────────────
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
        btn.classList.add('active');
        $('tab-' + btn.dataset.tab).classList.add('active');
    });
});

// ─── Logger ─────────────────────────────────────────────────────────────────
function log(msg, color = '#94a3b8') {
    const box = $('log-box');
    const now  = new Date().toLocaleTimeString('uz');
    box.innerHTML += `<span style="color:${color}">[${now}] ${msg}</span>\n`;
    box.scrollTop  = box.scrollHeight;
}

// ─── Models ─────────────────────────────────────────────────────────────────
async function loadModels() {
    if (modelsLoaded) return;
    log('Modellar yuklanmoqda...', '#38bdf8');
    await Promise.all([
        faceapi.nets.tinyFaceDetector.loadFromUri(MODELS_PATH),
        faceapi.nets.faceLandmark68Net.loadFromUri(MODELS_PATH),
        faceapi.nets.faceRecognitionNet.loadFromUri(MODELS_PATH),
    ]);
    modelsLoaded = true;
    log('✅ Modellar yuklandi', '#4ade80');
}

// ─── Helpers ─────────────────────────────────────────────────────────────────
function dist2d(a, b) { return Math.hypot(a.x-b.x, a.y-b.y); }

function computeEAR(lm) {
    const p = lm.positions;
    const ear = (i1,i2,i3,i4,i5,i6) => {
        return (dist2d(p[i2],p[i6]) + dist2d(p[i3],p[i5])) / (2*dist2d(p[i1],p[i4]));
    };
    return (ear(36,37,38,39,40,41) + ear(42,43,44,45,46,47)) / 2;
}

function computeYaw(lm) {
    const p = lm.positions;
    const cx = (p[39].x+p[42].x)/2, fw = Math.abs(p[42].x-p[39].x);
    return fw > 0 ? (p[30].x - cx) / fw : 0;
}

function euclidean(a, b) {
    let s = 0;
    for (let i = 0; i < 128; i++) { const d = a[i]-b[i]; s += d*d; }
    return Math.sqrt(s);
}

function getCfg() {
    return {
        threshold: parseFloat($('cfg-threshold').value),
        blinks:    parseInt($('cfg-blinks').value),
        headTurn:  $('cfg-head-turn').checked,
        timeout:   parseInt($('cfg-timeout').value) * 1000,
    };
}

// ─── Slider labels ───────────────────────────────────────────────────────────
$('cfg-threshold').addEventListener('input', function() {
    const v = parseFloat(this.value);
    $('cfg-threshold-val').textContent = v.toFixed(2);
    $('threshold-pct').textContent = '≈ ' + Math.round((1-v/0.6)*100) + '% yaqinlik';
});
$('recog-threshold').addEventListener('input', function() {
    $('recog-threshold-val').textContent = parseFloat(this.value).toFixed(2);
});

// ══════════════════════════════════════════════════════════════════════════════
//  DEBUG TAB
// ══════════════════════════════════════════════════════════════════════════════

$('btn-start-camera').addEventListener('click', async () => {
    $('no-camera-msg').style.display = 'none';
    try {
        debugStream = await navigator.mediaDevices.getUserMedia({ video:{width:{ideal:640},height:{ideal:480},facingMode:'user'} });
        $('video').srcObject = debugStream;
        await new Promise(res => $('video').onloadedmetadata = res);
        $('overlay').width  = $('video').videoWidth;
        $('overlay').height = $('video').videoHeight;
        log('📷 Kamera yoqildi', '#4ade80');
        if (!modelsLoaded) await loadModels();
        startDebugLoop();
        $('btn-capture').disabled = false;
        $('btn-capture').style.opacity = '1';
    } catch(e) {
        log('❌ Kamera xatosi: ' + e.message, '#f87171');
    }
});

$('btn-stop-camera').addEventListener('click', () => {
    stopDebugStream();
    log('⏹ Kamera to\'xtatildi', '#94a3b8');
});

function stopDebugStream() {
    if (debugLoop) { clearInterval(debugLoop); debugLoop = null; }
    if (debugStream) { debugStream.getTracks().forEach(t=>t.stop()); debugStream = null; }
    $('btn-capture').disabled = true;
    $('btn-capture').style.opacity = '0.5';
}

function startDebugLoop() {
    if (debugLoop) clearInterval(debugLoop);
    const ctx = $('overlay').getContext('2d');

    debugLoop = setInterval(async () => {
        if (!debugStream || !$('video').videoWidth) return;

        const now = performance.now();
        const fps = now - lastFrameTs > 0 ? Math.round(1000/(now-lastFrameTs)) : 0;
        lastFrameTs = now;
        $('m-fps').textContent = fps + ' fps';

        ctx.clearRect(0, 0, $('overlay').width, $('overlay').height);

        let det;
        try { det = await faceapi.detectSingleFace($('video'), OPTS224()).withFaceLandmarks().withFaceDescriptor(); }
        catch(e) { return; }

        if (!det) {
            $('m-face').textContent = '❌'; $('m-face').style.color = '#f87171';
            $('oval').style.borderColor = 'rgba(255,255,255,0.3)'; return;
        }

        $('m-face').textContent = '✅'; $('m-face').style.color = '#4ade80';
        $('oval').style.borderColor = '#4ade80';

        ctx.fillStyle = 'rgba(99,102,241,0.7)';
        det.landmarks.positions.forEach(p => {
            ctx.beginPath(); ctx.arc(p.x, p.y, 1.5, 0, 2*Math.PI); ctx.fill();
        });

        const ear = computeEAR(det.landmarks);
        const yaw = computeYaw(det.landmarks);

        // EAR
        $('m-ear').textContent = ear.toFixed(3);
        if (ear < EAR_CLOSED) {
            $('m-ear').style.color = '#fb923c'; $('m-ear-status').textContent = '👁‍🗨 YUMIQ';
        } else if (ear < EAR_OPEN) {
            $('m-ear').style.color = '#fbbf24'; $('m-ear-status').textContent = '〰 O\'tish';
        } else {
            $('m-ear').style.color = '#a78bfa'; $('m-ear-status').textContent = '👁 OCHIQ';
        }

        // Blink counter
        const ec = ear < EAR_CLOSED;
        if (ec && !eyeClosed) { eyeClosed = true; }
        if (!ec && eyeClosed) {
            eyeClosed = false; blinkCount++;
            $('m-blink-count').textContent = blinkCount;
            $('m-blink-dots').textContent  = '●'.repeat(Math.min(blinkCount,10));
            log(`👁 Ko'z yumdi #${blinkCount} (EAR=${ear.toFixed(3)})`, '#c4b5fd');
        }

        // Yaw
        $('m-yaw').textContent = yaw.toFixed(3);
        if (yaw < -YAW_THRESH) {
            $('m-yaw').style.color = '#fb923c'; $('m-yaw-status').textContent = '← Chap';
        } else if (yaw > YAW_THRESH) {
            $('m-yaw').style.color = '#fb923c'; $('m-yaw-status').textContent = '→ O\'ng';
        } else {
            $('m-yaw').style.color = '#94a3b8'; $('m-yaw-status').textContent = 'To\'g\'ri';
        }

        // Distance vs ref
        if (refDescriptor) {
            const d    = euclidean(det.descriptor, refDescriptor);
            const conf = Math.max(0, Math.min(100, (1-d/0.6)*100));
            const thr  = getCfg().threshold;
            $('m-distance').textContent = d.toFixed(3);
            $('m-conf').textContent     = conf.toFixed(1) + '%';
            if (d <= thr) {
                $('m-distance').style.color = '#4ade80';
                $('m-distance-result').style.color = '#4ade80';
                $('m-distance-result').textContent = `✅ MOS (< ${thr})`;
            } else {
                $('m-distance').style.color = '#f87171';
                $('m-distance-result').style.color = '#f87171';
                $('m-distance-result').textContent = `❌ MOS EMAS (> ${thr})`;
            }
        }

        if (liveness.running) processLiveness(det, ear, yaw);
    }, 150);
}

// ─── Liveness ────────────────────────────────────────────────────────────────
function processLiveness(det, ear, yaw) {
    const cfg     = getCfg();
    const elapsed = Date.now() - liveness.startTs;
    if (elapsed > cfg.timeout) {
        liveness.running = false;
        setLivenessStatus('⏰ Vaqt tugadi! Qayta bosing.', '#f87171', 0);
        return;
    }

    if (cfg.blinks > 0 && liveness.phase === 'blink') {
        const ec = ear < EAR_CLOSED;
        if (ec && !liveness._eyeClosed) liveness._eyeClosed = true;
        if (!ec && liveness._eyeClosed) {
            liveness._eyeClosed = false; liveness.blinksDone++;
            setLivenessStatus(`Ko'z: ${liveness.blinksDone}/${cfg.blinks}`, '#6366f1', Math.round(liveness.blinksDone/cfg.blinks*45));
            log(`👁 Liveness blink ${liveness.blinksDone}/${cfg.blinks}`, '#c4b5fd');
        }
        if (liveness.blinksDone >= cfg.blinks) {
            liveness.phase = cfg.headTurn ? 'head' : 'done';
            if (cfg.headTurn) {
                liveness.dir = Math.random()>.5 ? 'left' : 'right';
                setLivenessStatus(`Boshni ${liveness.dir==='left'?'← chapga':'→ o\'ngga'} burting`, '#f59e0b', 55);
            }
        }
    }

    if (cfg.headTurn && liveness.phase === 'head') {
        const turned = liveness.dir === 'left' ? (yaw < -YAW_THRESH) : (yaw > YAW_THRESH);
        if (turned && !liveness.headDone) { liveness.headDone = true; setLivenessStatus('Endi to\'g\'ri qarang', '#10b981', 85); }
        if (liveness.headDone && Math.abs(yaw) < 0.08) liveness.phase = 'done';
    }

    if (liveness.phase === 'done') {
        liveness.running = false; liveness.passed = true;
        setLivenessStatus('✅ Liveness o\'tdi!', '#10b981', 100);
        log('✅ LIVENESS PASSED', '#4ade80');
    }
}

function setLivenessStatus(text, color, pct) {
    $('liveness-status').textContent = text;
    $('liveness-status').style.color = color;
    $('liveness-bar').style.width    = pct + '%';
    $('liveness-bar').style.background = pct>=100 ? '#10b981' : '#6366f1';
}

// ─── Talaba yuklash (debug) ───────────────────────────────────────────────────
$('btn-load-student').addEventListener('click', async () => {
    const id = $('inp-student-id').value.trim();
    if (!id) return;
    $('student-error').style.display = 'none';
    $('student-info-box').style.display = 'none';
    $('btn-load-student').textContent = '...';

    try {
        const resp = await fetch(CHECK_STUDENT, {
            method:'POST',
            headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF},
            body: JSON.stringify({ student_id_number: id }),
        });
        const data = await resp.json();
        if (!resp.ok) throw new Error(data.error || 'Xato');

        $('student-name-label').textContent  = data.full_name;
        $('student-enroll-label').textContent = data.has_descriptor
            ? '✅ Descriptor DB da mavjud' : '⚠️ Descriptor yo\'q — HEMIS dan olinadi';
        $('ref-img-preview').src             = data.photo_url;
        $('student-info-box').style.display  = 'block';

        log(`👤 Yuklandi: ${data.full_name}`, '#38bdf8');

        if (!modelsLoaded) await loadModels();
        log('🖼 Descriptor hisoblanmoqda...', '#38bdf8');

        const img = await faceapi.fetchImage(data.photo_url);
        const det = await faceapi.detectSingleFace(img, OPTS320()).withFaceLandmarks().withFaceDescriptor();

        if (!det) {
            log('⚠️ HEMIS rasmda yuz topilmadi', '#fbbf24');
        } else {
            refDescriptor = det.descriptor;
            log('✅ Reference descriptor tayyor', '#4ade80');

            fetch(SAVE_DESC_URL, {
                method:'POST',
                headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF},
                body: JSON.stringify({ student_id: data.student_id, descriptor: Array.from(refDescriptor), source_url: data.photo_url }),
            }).then(() => log('💾 Descriptor serverga saqlandi', '#4ade80'))
              .catch(e => log('⚠️ Saqlanmadi: '+e.message, '#fbbf24'));
        }
    } catch(e) {
        $('student-error').textContent      = e.message;
        $('student-error').style.display    = 'block';
        log('❌ ' + e.message, '#f87171');
    } finally {
        $('btn-load-student').textContent = 'Yuklash';
    }
});

$('inp-student-id').addEventListener('keydown', e => { if(e.key==='Enter') $('btn-load-student').click(); });

// ─── Taqqoslash (debug) ───────────────────────────────────────────────────────
$('btn-capture').addEventListener('click', async () => {
    if (!refDescriptor) { log('⚠️ Avval talabani tanlang', '#fbbf24'); return; }
    log('📸 Skanerlanmoqda...', '#38bdf8');
    const det = await faceapi.detectSingleFace($('video'), OPTS320()).withFaceLandmarks().withFaceDescriptor();
    if (!det) { log('❌ Yuz topilmadi', '#f87171'); return; }

    const d    = euclidean(det.descriptor, refDescriptor);
    const conf = Math.max(0, Math.min(100, (1-d/0.6)*100));
    const thr  = getCfg().threshold;
    const pass = d <= thr;
    if (pass) { sessionOk++; $('stat-session-ok').textContent = sessionOk; }

    const sc = document.createElement('canvas');
    sc.width  = Math.min($('video').videoWidth, 320);
    sc.height = Math.round($('video').videoHeight * sc.width / $('video').videoWidth);
    sc.getContext('2d').drawImage($('video'), 0, 0, sc.width, sc.height);
    const snap = sc.toDataURL('image/jpeg', 0.7);

    $('snapshot-img').src = snap;
    $('snapshot-preview').style.display = 'block';

    const color = pass ? '#10b981' : '#ef4444', icon = pass ? '✅' : '❌';
    $('result-content').innerHTML = `
        <div style="font-size:2rem; margin-bottom:8px;">${icon}</div>
        <div style="font-size:1rem; font-weight:700; color:${color}; margin-bottom:6px;">${pass?'MOS KELDI':'MOS KELMADI'}</div>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-top:10px; text-align:left;">
            <div style="padding:8px; background:#f8fafc; border-radius:6px; font-size:12px;">
                <div style="color:#64748b; font-size:10px; margin-bottom:2px;">DISTANCE</div>
                <span style="font-weight:700; color:${color}; font-size:1.1rem;">${d.toFixed(4)}</span>
                <div style="color:#94a3b8; font-size:10px;">threshold: ${thr}</div>
            </div>
            <div style="padding:8px; background:#f8fafc; border-radius:6px; font-size:12px;">
                <div style="color:#64748b; font-size:10px; margin-bottom:2px;">YAQINLIK</div>
                <span style="font-weight:700; color:${color}; font-size:1.1rem;">${conf.toFixed(1)}%</span>
            </div>
        </div>`;
    $('result-box').style.display      = 'block';
    $('result-box').style.borderColor  = color;
    log(`${icon} distance=${d.toFixed(3)}, conf=${conf.toFixed(1)}%, thr=${thr}`, pass?'#4ade80':'#f87171');
});

// ─── Liveness tugmalar ────────────────────────────────────────────────────────
function startLiveness(phase) {
    liveness = { running:true, passed:false, phase, blinksDone:0, _eyeClosed:false,
                 headDone:false, dir: Math.random()>.5?'left':'right', startTs:Date.now() };
    const cfg = getCfg();
    const dir = liveness.dir==='left'?'← chapga':'→ o\'ngga';
    if (phase==='blink') setLivenessStatus(`Ko'zni ${cfg.blinks} marta yumib-oching`, '#6366f1', 5);
    else                 setLivenessStatus(`Boshni ${dir} burting`, '#f59e0b', 5);
    log(`▶ Liveness: ${phase}`, '#38bdf8');
}

$('btn-test-blink').addEventListener('click', () => {
    if (!debugStream) { log('⚠️ Avval kamerani yoqing', '#fbbf24'); return; }
    blinkCount = 0; $('m-blink-count').textContent='0'; $('m-blink-dots').textContent='';
    startLiveness('blink');
});
$('btn-test-head').addEventListener('click', () => {
    if (!debugStream) { log('⚠️ Avval kamerani yoqing', '#fbbf24'); return; }
    startLiveness('head');
});
$('btn-test-full').addEventListener('click', () => {
    if (!debugStream) { log('⚠️ Avval kamerani yoqing', '#fbbf24'); return; }
    blinkCount = 0; $('m-blink-count').textContent='0'; $('m-blink-dots').textContent='';
    const cfg = getCfg();
    startLiveness(cfg.blinks>0 ? 'blink' : (cfg.headTurn?'head':'done'));
});

// ══════════════════════════════════════════════════════════════════════════════
//  RECOGNITION TAB
// ══════════════════════════════════════════════════════════════════════════════

$('btn-start-recog').addEventListener('click', async () => {
    $('no-camera-msg2').style.display = 'none';
    try {
        recogStream = await navigator.mediaDevices.getUserMedia({ video:{width:{ideal:640},height:{ideal:480},facingMode:'user'} });
        $('video2').srcObject = recogStream;
        await new Promise(res => $('video2').onloadedmetadata = res);
        $('overlay2').width  = $('video2').videoWidth;
        $('overlay2').height = $('video2').videoHeight;
        if (!modelsLoaded) await loadModels();
        startRecogLoop();
        $('recog-live-badge').style.display = 'inline';
    } catch(e) {
        alert('Kamera xatosi: ' + e.message);
    }
});

$('btn-stop-recog').addEventListener('click', () => {
    if (recogLoop) { clearInterval(recogLoop); recogLoop = null; }
    if (recogStream) { recogStream.getTracks().forEach(t=>t.stop()); recogStream = null; }
    $('recog-live-badge').style.display = 'none';
    $('recog-overlay').style.display = 'none';
});

// Recognition history
const recogHistory = [];

function startRecogLoop() {
    if (recogLoop) clearInterval(recogLoop);
    const ctx = $('overlay2').getContext('2d');
    let skipTick = 0;

    recogLoop = setInterval(async () => {
        if (!recogStream || !$('video2').videoWidth) return;
        skipTick++;
        if (skipTick % 2 !== 0) return; // every 2nd tick → ~300ms

        ctx.clearRect(0, 0, $('overlay2').width, $('overlay2').height);

        let det;
        try { det = await faceapi.detectSingleFace($('video2'), OPTS224()).withFaceLandmarks().withFaceDescriptor(); }
        catch(e) { return; }

        if (!det) {
            $('recog-overlay').style.display = 'none'; return;
        }

        // Landmark overlay
        ctx.fillStyle = 'rgba(99,102,241,0.6)';
        det.landmarks.positions.forEach(p => {
            ctx.beginPath(); ctx.arc(p.x, p.y, 1.5, 0, 2*Math.PI); ctx.fill();
        });

        if (pool.length === 0) {
            $('recog-overlay').style.display = 'block';
            $('recog-name').textContent = '⚠️ Pool bo\'sh — odamlarni yuklang';
            $('recog-sub').textContent  = '';
            return;
        }

        // Find closest match
        const thr  = parseFloat($('recog-threshold').value);
        let best   = null, bestDist = Infinity;

        for (const person of pool) {
            const d = euclidean(det.descriptor, person.descriptor);
            if (d < bestDist) { bestDist = d; best = person; }
        }

        const conf = Math.max(0, Math.min(100, (1-bestDist/0.6)*100));
        $('recog-overlay').style.display = 'block';

        if (bestDist <= thr) {
            const typeLabel = best.type === 'teacher' ? '👷 Xodim' : '🎓 Talaba';
            $('recog-name').textContent = best.name;
            $('recog-sub').textContent  = `${typeLabel} · ${conf.toFixed(1)}% yaqinlik · d=${bestDist.toFixed(3)}`;
            $('recog-name').style.color = '#fff';

            // Bounding box
            const box = det.detection.box;
            ctx.strokeStyle = '#4ade80'; ctx.lineWidth = 2;
            ctx.strokeRect(box.x, box.y, box.width, box.height);
            ctx.fillStyle = 'rgba(74,222,128,0.15)';
            ctx.fillRect(box.x, box.y, box.width, box.height);

            // Add to history (deduplicate last 3s)
            const last = recogHistory[recogHistory.length - 1];
            if (!last || last.id !== best.id || Date.now() - last.ts > 3000) {
                recogHistory.push({ id: best.id, name: best.name, type: best.type, conf: conf.toFixed(1), ts: Date.now() });
                if (recogHistory.length > 10) recogHistory.shift();
                renderRecogHistory();
                sessionOk++;
                $('stat-session-ok').textContent = sessionOk;
            }
        } else {
            $('recog-name').textContent = '❓ Noma\'lum shaxs';
            $('recog-sub').textContent  = `Eng yaqin: ${best?.name ?? '—'} · d=${bestDist.toFixed(3)} (>${thr})`;
            $('recog-name').style.color = '#fbbf24';

            const box = det.detection.box;
            ctx.strokeStyle = '#f87171'; ctx.lineWidth = 2;
            ctx.strokeRect(box.x, box.y, box.width, box.height);
        }
    }, 150);
}

function renderRecogHistory() {
    const el = $('recog-history');
    if (recogHistory.length === 0) {
        el.innerHTML = '<div style="font-size:12px; color:#94a3b8;">Tanish hali boshlanmagan</div>';
        return;
    }
    const items = [...recogHistory].reverse().slice(0,8).map(r => {
        const typeLabel = r.type === 'teacher' ? '👷 Xodim' : '🎓 Talaba';
        const time = new Date(r.ts).toLocaleTimeString('uz');
        return `<div class="person-row match">
            <span class="person-badge ${r.type==='teacher'?'badge-teacher':'badge-student'}">${typeLabel}</span>
            <span style="flex:1; font-size:12px; font-weight:600; color:#1e293b;">${r.name}</span>
            <span style="font-size:11px; color:#64748b;">${r.conf}%</span>
            <span style="font-size:10px; color:#94a3b8; margin-left:6px;">${time}</span>
        </div>`;
    }).join('');
    el.innerHTML = items;
}

// ─── Pool: barcha talabalar DB dan ───────────────────────────────────────────
$('btn-load-all-students').addEventListener('click', async () => {
    $('btn-load-all-students').textContent = '⏳ Yuklanmoqda...';
    $('btn-load-all-students').disabled = true;
    $('students-load-bar').style.width = '10%';
    $('students-load-status').textContent = 'Server so\'rovi...';

    try {
        const resp = await fetch(ALL_DESC_URL, { headers:{'X-CSRF-TOKEN':CSRF} });
        const data = await resp.json();
        $('students-load-bar').style.width = '50%';
        $('students-load-status').textContent = `${data.count} ta descriptor olinmoqda...`;

        let added = 0;
        for (const person of data.people) {
            // Agar pool da yo'q bo'lsa
            if (!pool.find(p => p.id === person.id && p.type === 'student')) {
                pool.push({
                    id:          person.id,
                    name:        person.name,
                    idNumber:    person.id_number,
                    type:        'student',
                    descriptor:  new Float32Array(person.descriptor),
                    photoUrl:    person.photo_url,
                });
                added++;
            }
        }

        $('students-load-bar').style.width = '100%';
        $('students-load-status').textContent = `✅ ${added} yangi talaba qo'shildi (jami ${pool.length})`;
        renderPool();
    } catch(e) {
        $('students-load-status').textContent = '❌ Xato: ' + e.message;
        $('students-load-bar').style.background = '#ef4444';
    } finally {
        $('btn-load-all-students').textContent = '⬇️ Yuklash';
        $('btn-load-all-students').disabled = false;
    }
});

// ─── Pool: xodim qo'shish ────────────────────────────────────────────────────
$('btn-add-teacher').addEventListener('click', async () => {
    const empId = $('inp-teacher-id').value.trim();
    if (!empId) return;
    showTeacherMsg('⏳ Yuklanmoqda...', '#64748b', '#f1f5f9');
    $('btn-add-teacher').disabled = true;

    try {
        // 1. Xodim ma'lumotlarini olish
        const resp = await fetch(CHECK_TEACHER, {
            method:'POST',
            headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF},
            body: JSON.stringify({ employee_id_number: empId }),
        });
        const data = await resp.json();
        if (!resp.ok) throw new Error(data.error || 'Xato');

        if (!data.has_photo) throw new Error('Bu xodimning rasmi yo\'q');

        showTeacherMsg(`🖼 ${data.full_name} — descriptor hisoblanmoqda...`, '#0369a1', '#f0f9ff');

        if (!modelsLoaded) await loadModels();

        // 2. Rasmdan descriptor hisoblash
        const img = await faceapi.fetchImage(data.photo_url);
        const det = await faceapi.detectSingleFace(img, OPTS320()).withFaceLandmarks().withFaceDescriptor();

        if (!det) throw new Error('Rasmda yuz aniqlanmadi');

        // 3. Poolga qo'shish
        const existing = pool.findIndex(p => p.id === data.teacher_id && p.type === 'teacher');
        if (existing >= 0) pool.splice(existing, 1);

        pool.push({
            id:         data.teacher_id,
            name:       data.full_name,
            idNumber:   empId,
            type:       'teacher',
            descriptor: det.descriptor,
            photoUrl:   data.photo_url,
            position:   data.position,
        });

        showTeacherMsg(`✅ ${data.full_name} qo'shildi`, '#15803d', '#dcfce7');
        $('inp-teacher-id').value = '';
        renderPool();
    } catch(e) {
        showTeacherMsg('❌ ' + e.message, '#b91c1c', '#fef2f2');
    } finally {
        $('btn-add-teacher').disabled = false;
    }
});

$('inp-teacher-id').addEventListener('keydown', e => { if(e.key==='Enter') $('btn-add-teacher').click(); });

function showTeacherMsg(text, color, bg) {
    const el = $('teacher-msg');
    el.style.display = 'block';
    el.style.color   = color;
    el.style.background = bg;
    el.textContent   = text;
}

// ─── Pool render ─────────────────────────────────────────────────────────────
function renderPool() {
    $('pool-count-badge').textContent = pool.length;
    const el = $('pool-list');
    if (pool.length === 0) {
        el.innerHTML = '<div style="font-size:12px; color:#94a3b8; text-align:center; padding:20px;">Hali hech kim yuklanmagan</div>';
        return;
    }
    const students = pool.filter(p=>p.type==='student');
    const teachers = pool.filter(p=>p.type==='teacher');

    let html = '';

    if (teachers.length > 0) {
        html += `<div style="font-size:10px; font-weight:600; color:#94a3b8; margin-bottom:5px; margin-top:5px;">XODIMLAR (${teachers.length})</div>`;
        teachers.forEach(p => {
            html += `<div class="person-row" style="border:1px solid #fed7aa;">
                <img src="${p.photoUrl}" style="width:32px; height:32px; border-radius:50%; object-fit:cover; border:2px solid #f59e0b;" onerror="this.style.opacity=0.3">
                <div style="flex:1; min-width:0;">
                    <div style="font-size:12px; font-weight:600; color:#1e293b; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${p.name}</div>
                    <div style="font-size:10px; color:#94a3b8;">${p.position || p.idNumber}</div>
                </div>
                <span class="person-badge badge-teacher">Xodim</span>
                <button onclick="removeFromPool(${p.id},'teacher')" style="background:none;border:none;color:#ef4444;cursor:pointer;font-size:14px;padding:0 4px;">×</button>
            </div>`;
        });
    }

    if (students.length > 0) {
        html += `<div style="font-size:10px; font-weight:600; color:#94a3b8; margin-top:10px; margin-bottom:5px;">TALABALAR (${students.length})</div>`;
        const shown = students.slice(0, 20);
        shown.forEach(p => {
            html += `<div class="person-row">
                <img src="${p.photoUrl}" style="width:32px; height:32px; border-radius:50%; object-fit:cover; border:2px solid #e2e8f0;" onerror="this.style.opacity=0.3">
                <div style="flex:1; min-width:0;">
                    <div style="font-size:12px; font-weight:600; color:#1e293b; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${p.name}</div>
                    <div style="font-size:10px; color:#94a3b8;">${p.idNumber}</div>
                </div>
                <span class="person-badge badge-student">Talaba</span>
                <button onclick="removeFromPool(${p.id},'student')" style="background:none;border:none;color:#ef4444;cursor:pointer;font-size:14px;padding:0 4px;">×</button>
            </div>`;
        });
        if (students.length > 20) {
            html += `<div style="font-size:11px; color:#94a3b8; text-align:center; padding:8px;">...va yana ${students.length-20} ta talaba</div>`;
        }
    }

    el.innerHTML = html;
}

window.removeFromPool = function(id, type) {
    const i = pool.findIndex(p => p.id===id && p.type===type);
    if (i >= 0) pool.splice(i, 1);
    renderPool();
};

$('btn-clear-pool').addEventListener('click', () => {
    if (!confirm('Barcha yuklangan shaxslarni o\'chirish?')) return;
    pool = []; renderPool();
    recogHistory.length = 0; renderRecogHistory();
});

})();
</script>
@endsection
