<x-app-layout>

{{-- Inline CSS --}}
<style>
.fi-tab-btn { padding:8px 18px; border:1px solid #e2e8f0; border-radius:8px; background:#f8fafc; color:#64748b; font-size:13px; cursor:pointer; transition:all .15s; }
.fi-tab-btn.active { background:#6366f1; color:#fff; border-color:#6366f1; font-weight:600; }
.fi-tab-pane { display:none; }
.fi-tab-pane.active { display:block; }
.fi-metric { background:#1e293b; border-radius:8px; padding:10px; }
.fi-metric-label { font-size:10px; color:#64748b; margin-bottom:4px; }
.fi-metric-val { font-size:1.3rem; font-weight:700; }
.fi-metric-sub { font-size:10px; color:#64748b; margin-top:2px; }
.fi-person-row { display:flex; align-items:center; gap:8px; padding:7px 8px; border-radius:8px; background:#f8fafc; margin-bottom:5px; border:1px solid #e2e8f0; }
.fi-person-row.match { background:#dcfce7; border-color:#86efac; }
.fi-badge { font-size:10px; padding:2px 7px; border-radius:10px; font-weight:600; }
.fi-badge-s { background:#ede9fe; color:#5b21b6; }
.fi-badge-t { background:#fff7ed; color:#9a3412; }
</style>

<div style="max-width:1160px; margin:0 auto; padding:20px 16px; font-family:sans-serif;">

    {{-- Sarlavha --}}
    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:16px;">
        <div>
            <h1 style="font-size:1.25rem; font-weight:700; color:#1e293b; margin:0;">🧪 Face ID — Sinov sahifasi</h1>
            <p style="font-size:12px; color:#64748b; margin:3px 0 0;">Faqat admin uchun · Login amalga oshirilmaydi</p>
        </div>
        <div style="display:flex; gap:8px; align-items:center;">
            <span style="font-size:11px; color:#94a3b8;">{{ $enrolledCount }} descriptor · {{ $totalStudents }} talaba · {{ $totalTeachers }} xodim</span>
            <a href="{{ route('admin.face-id.settings') }}" style="padding:7px 14px; background:#f1f5f9; color:#475569; border-radius:8px; font-size:13px; text-decoration:none; border:1px solid #e2e8f0;">⚙️ Sozlamalar</a>
        </div>
    </div>

    {{-- Tablar --}}
    <div style="display:flex; gap:8px; margin-bottom:14px;">
        <button class="fi-tab-btn active" data-tab="debug">🔬 Sinov / Debug</button>
        <button class="fi-tab-btn" data-tab="recognize">👥 Yuzlarni tanish</button>
    </div>

    {{-- ═══════ TAB 1: DEBUG ═══════ --}}
    <div class="fi-tab-pane active" id="fi-tab-debug">
    <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; align-items:start;">

        {{-- Chap: Kamera --}}
        <div>
            {{-- Talaba tanlash --}}
            <div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:14px; margin-bottom:12px;">
                <label style="font-size:12px; font-weight:600; color:#475569; display:block; margin-bottom:6px;">Talaba ID raqami</label>
                <div style="display:flex; gap:8px;">
                    <input type="text" id="fi-inp-student" placeholder="Masalan: 20202005010028"
                           style="flex:1; padding:8px 10px; border:1px solid #cbd5e1; border-radius:8px; font-size:13px; outline:none;">
                    <button id="fi-btn-load-student" style="padding:8px 14px; background:#3b82f6; color:#fff; border:none; border-radius:8px; font-size:13px; cursor:pointer; white-space:nowrap;">Yuklash</button>
                </div>
                <div id="fi-student-info" style="display:none; margin-top:10px; padding:10px; background:#f0f9ff; border-radius:8px; border:1px solid #bae6fd;">
                    <div style="display:flex; align-items:center; gap:10px;">
                        <img id="fi-ref-img" src="" style="width:48px;height:48px;border-radius:50%;object-fit:cover;border:2px solid #38bdf8;" onerror="this.style.opacity=0.3">
                        <div>
                            <div id="fi-student-name" style="font-weight:600;font-size:13px;color:#0c4a6e;"></div>
                            <div id="fi-student-enroll" style="font-size:11px;color:#0369a1;margin-top:2px;"></div>
                        </div>
                    </div>
                </div>
                <div id="fi-student-err" style="display:none; margin-top:8px; padding:8px 10px; background:#fef2f2; color:#b91c1c; border-radius:8px; font-size:12px;"></div>
            </div>

            {{-- Kamera --}}
            <div style="background:#000;border-radius:12px;overflow:hidden;position:relative;aspect-ratio:4/3;">
                <video id="fi-video" autoplay playsinline muted style="width:100%;height:100%;object-fit:cover;transform:scaleX(-1);display:block;"></video>
                <canvas id="fi-overlay" style="position:absolute;inset:0;width:100%;height:100%;transform:scaleX(-1);pointer-events:none;"></canvas>
                <div id="fi-oval" style="position:absolute;top:50%;left:50%;transform:translate(-50%,-55%);width:42%;padding-bottom:53%;border:3px solid rgba(255,255,255,0.4);border-radius:50%;pointer-events:none;transition:border-color 0.3s;box-sizing:border-box;"></div>
                <div id="fi-no-cam" style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;color:#94a3b8;font-size:13px;">Kamera yuklanmagan</div>
            </div>

            <div style="display:flex;gap:8px;margin-top:10px;">
                <button id="fi-btn-cam-on" style="flex:1;padding:9px;background:#10b981;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;">📷 Kamerani yoqish</button>
                <button id="fi-btn-capture" disabled style="flex:1;padding:9px;background:#6366f1;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;opacity:0.5;">🔍 Taqqoslash</button>
                <button id="fi-btn-cam-off" style="padding:9px 14px;background:#f1f5f9;color:#475569;border:1px solid #e2e8f0;border-radius:8px;font-size:13px;cursor:pointer;">⏹</button>
            </div>

            {{-- Liveness --}}
            <div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:14px;margin-top:12px;">
                <div style="font-size:12px;font-weight:600;color:#475569;margin-bottom:10px;">Liveness test</div>
                <div style="display:flex;gap:8px;">
                    <button id="fi-btn-blink" style="flex:1;padding:8px;background:#fef9c3;color:#713f12;border:1px solid #fde047;border-radius:8px;font-size:12px;cursor:pointer;">👁 Ko'z</button>
                    <button id="fi-btn-head"  style="flex:1;padding:8px;background:#ede9fe;color:#4c1d95;border:1px solid #c4b5fd;border-radius:8px;font-size:12px;cursor:pointer;">🔄 Bosh</button>
                    <button id="fi-btn-full"  style="flex:1;padding:8px;background:#dcfce7;color:#14532d;border:1px solid #86efac;border-radius:8px;font-size:12px;cursor:pointer;">✅ To'liq</button>
                </div>
                <div id="fi-liveness-status" style="margin-top:10px;padding:10px;background:#f8fafc;border-radius:8px;font-size:13px;text-align:center;min-height:36px;color:#475569;">Liveness testi boshlanmagan</div>
                <div style="margin-top:8px;background:#e2e8f0;border-radius:4px;height:6px;">
                    <div id="fi-liveness-bar" style="height:6px;border-radius:4px;background:#6366f1;width:0%;transition:width 0.3s;"></div>
                </div>
            </div>
        </div>

        {{-- O'ng: Debug panel --}}
        <div>
            {{-- Parametrlar --}}
            <div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:14px;margin-bottom:12px;">
                <div style="font-size:12px;font-weight:600;color:#475569;margin-bottom:12px;">⚙️ Parametrlar (real-vaqt)</div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                    <div>
                        <label style="font-size:11px;color:#64748b;">Threshold</label>
                        <div style="display:flex;align-items:center;gap:6px;margin-top:3px;">
                            <input type="range" id="fi-cfg-thr" min="0.25" max="0.70" step="0.01" value="{{ $settings['threshold'] }}" style="flex:1;accent-color:#6366f1;">
                            <span id="fi-cfg-thr-val" style="font-size:12px;font-weight:700;color:#6366f1;min-width:36px;">{{ $settings['threshold'] }}</span>
                        </div>
                        <div id="fi-thr-pct" style="font-size:11px;color:#94a3b8;margin-top:1px;">≈ {{ round((1 - $settings['threshold']/0.6) * 100) }}% yaqinlik</div>
                    </div>
                    <div>
                        <label style="font-size:11px;color:#64748b;">Ko'z yumish</label>
                        <select id="fi-cfg-blinks" style="width:100%;margin-top:3px;padding:5px 8px;border:1px solid #cbd5e1;border-radius:6px;font-size:12px;">
                            @foreach([0,1,2,3] as $n)
                            <option value="{{ $n }}" {{ $settings['blinks_required'] == $n ? 'selected' : '' }}>{{ $n }} marta{{ $n===0?' (o\'ch.)':'' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label style="display:flex;align-items:center;gap:6px;margin-top:16px;cursor:pointer;">
                            <input type="checkbox" id="fi-cfg-head" {{ $settings['head_turn_required'] ? 'checked' : '' }} style="width:14px;height:14px;accent-color:#6366f1;">
                            <span style="font-size:12px;color:#475569;">Bosh burish</span>
                        </label>
                    </div>
                    <div>
                        <label style="font-size:11px;color:#64748b;">Timeout (s)</label>
                        <input type="number" id="fi-cfg-timeout" value="{{ $settings['liveness_timeout'] }}" min="5" max="120" style="width:100%;margin-top:3px;padding:5px 8px;border:1px solid #cbd5e1;border-radius:6px;font-size:12px;">
                    </div>
                </div>
            </div>

            {{-- Metrikalar --}}
            <div style="background:#0f172a;border-radius:12px;padding:14px;color:#e2e8f0;font-family:monospace;font-size:12px;">
                <div style="font-size:11px;font-weight:600;color:#94a3b8;margin-bottom:10px;font-family:sans-serif;">📊 Real-time metrikalar</div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:8px;">
                    <div class="fi-metric"><div class="fi-metric-label">YUZ TOPILDI</div><div id="fi-m-face" class="fi-metric-val">—</div></div>
                    <div class="fi-metric"><div class="fi-metric-label">TEZLIK</div><div id="fi-m-fps" class="fi-metric-val" style="color:#38bdf8;">—</div></div>
                    <div class="fi-metric"><div class="fi-metric-label">EAR (ko'z)</div><div id="fi-m-ear" class="fi-metric-val" style="color:#a78bfa;">—</div><div id="fi-m-ear-s" class="fi-metric-sub">—</div></div>
                    <div class="fi-metric"><div class="fi-metric-label">YAW (bosh)</div><div id="fi-m-yaw" class="fi-metric-val" style="color:#fb923c;">—</div><div id="fi-m-yaw-s" class="fi-metric-sub">—</div></div>
                    <div class="fi-metric"><div class="fi-metric-label">DISTANCE</div><div id="fi-m-dist" class="fi-metric-val">—</div><div id="fi-m-dist-s" class="fi-metric-sub">—</div></div>
                    <div class="fi-metric"><div class="fi-metric-label">YAQINLIK %</div><div id="fi-m-conf" class="fi-metric-val">—</div></div>
                </div>
                <div class="fi-metric" style="margin-bottom:8px;">
                    <div class="fi-metric-label">KO'Z YUMISH</div>
                    <div style="display:flex;align-items:center;gap:6px;margin-top:4px;">
                        <span id="fi-m-blink-n" style="font-size:1.2rem;font-weight:700;color:#4ade80;">0</span>
                        <span style="color:#475569;font-size:11px;">marta</span>
                        <span id="fi-m-blink-d" style="color:#4ade80;font-size:14px;letter-spacing:2px;"></span>
                    </div>
                </div>
                <div class="fi-metric">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                        <span class="fi-metric-label" style="margin:0;">LOG</span>
                        <button onclick="document.getElementById('fi-log').innerHTML=''" style="font-size:10px;color:#475569;background:none;border:none;cursor:pointer;">tozalash</button>
                    </div>
                    <div id="fi-log" style="height:120px;overflow-y:auto;font-size:11px;line-height:1.7;color:#94a3b8;"><span style="color:#475569;">Kamerani yoqing...</span></div>
                </div>
            </div>

            {{-- Taqqoslash natijasi --}}
            <div id="fi-result" style="display:none;background:#fff;border-radius:12px;padding:14px;margin-top:12px;border:2px solid #e2e8f0;">
                <div style="font-size:12px;font-weight:600;color:#475569;margin-bottom:10px;">🔍 Taqqoslash natijasi</div>
                <div id="fi-result-body" style="text-align:center;padding:10px;"></div>
                <div id="fi-snap-wrap" style="margin-top:10px;display:none;">
                    <div style="font-size:11px;color:#94a3b8;margin-bottom:4px;">Snapshot:</div>
                    <img id="fi-snap-img" style="width:100%;border-radius:8px;border:1px solid #e2e8f0;">
                </div>
            </div>
        </div>
    </div>
    </div>{{-- /fi-tab-debug --}}

    {{-- ═══════ TAB 2: TANISH ═══════ --}}
    <div class="fi-tab-pane" id="fi-tab-recognize">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;align-items:start;">

        {{-- Chap: Kamera + natija --}}
        <div>
            <div style="background:#000;border-radius:12px;overflow:hidden;position:relative;aspect-ratio:4/3;">
                <video id="fi-video2" autoplay playsinline muted style="width:100%;height:100%;object-fit:cover;transform:scaleX(-1);display:block;"></video>
                <canvas id="fi-overlay2" style="position:absolute;inset:0;width:100%;height:100%;transform:scaleX(-1);pointer-events:none;"></canvas>
                <div id="fi-no-cam2" style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;color:#94a3b8;font-size:13px;">Kamera yuklanmagan</div>
                <div id="fi-recog-overlay" style="position:absolute;bottom:0;left:0;right:0;padding:10px 14px;background:linear-gradient(transparent,rgba(0,0,0,0.7));display:none;">
                    <div id="fi-recog-name" style="font-size:1rem;font-weight:700;color:#fff;text-shadow:0 1px 3px rgba(0,0,0,0.8);"></div>
                    <div id="fi-recog-sub"  style="font-size:12px;color:rgba(255,255,255,0.8);margin-top:2px;"></div>
                </div>
            </div>

            <div style="display:flex;gap:8px;margin-top:10px;">
                <button id="fi-btn-recog-on" style="flex:1;padding:9px;background:#10b981;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;">📷 Kamerani yoqish</button>
                <button id="fi-btn-recog-off" style="padding:9px 14px;background:#f1f5f9;color:#475569;border:1px solid #e2e8f0;border-radius:8px;font-size:13px;cursor:pointer;">⏹ To'xtatish</button>
            </div>

            <div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:14px;margin-top:12px;">
                <div style="font-size:12px;font-weight:600;color:#475569;margin-bottom:10px;">
                    🎯 So'nggi tanishlar
                    <span id="fi-live-badge" style="margin-left:6px;padding:2px 8px;background:#dcfce7;color:#16a34a;border-radius:10px;font-size:10px;display:none;">● JONLI</span>
                </div>
                <div id="fi-recog-history" style="font-size:12px;color:#94a3b8;">Tanish hali boshlanmagan</div>
            </div>
        </div>

        {{-- O'ng: Pool --}}
        <div>
            <div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:14px;margin-bottom:12px;">
                <div style="font-size:12px;font-weight:600;color:#475569;margin-bottom:10px;">📚 Talabalar bazadan (enrolled)</div>
                <div style="display:flex;gap:8px;align-items:center;margin-bottom:8px;">
                    <div style="flex:1;font-size:12px;color:#64748b;">DB da <strong>{{ $enrolledCount }}</strong> ta descriptor</div>
                    <button id="fi-btn-load-all" style="padding:8px 14px;background:#6366f1;color:#fff;border:none;border-radius:8px;font-size:12px;cursor:pointer;white-space:nowrap;">⬇️ Yuklash</button>
                </div>
                <div id="fi-students-status" style="font-size:11px;color:#94a3b8;"></div>
                <div style="margin-top:6px;background:#e2e8f0;border-radius:4px;height:4px;">
                    <div id="fi-students-bar" style="height:4px;border-radius:4px;background:#6366f1;width:0%;transition:width 0.3s;"></div>
                </div>
            </div>

            <div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:14px;margin-bottom:12px;">
                <div style="font-size:12px;font-weight:600;color:#475569;margin-bottom:10px;">👷 Xodim qo'shish</div>
                <div style="display:flex;gap:8px;">
                    <input type="text" id="fi-inp-teacher" placeholder="employee_id_number"
                           style="flex:1;padding:8px 10px;border:1px solid #cbd5e1;border-radius:8px;font-size:13px;outline:none;">
                    <button id="fi-btn-add-teacher" style="padding:8px 14px;background:#f59e0b;color:#fff;border:none;border-radius:8px;font-size:13px;cursor:pointer;white-space:nowrap;">➕ Qo'shish</button>
                </div>
                <div id="fi-teacher-msg" style="display:none;margin-top:8px;padding:8px;border-radius:6px;font-size:12px;"></div>
            </div>

            <div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:14px;margin-bottom:12px;">
                <div style="font-size:11px;font-weight:600;color:#475569;margin-bottom:8px;">Tanish threshold</div>
                <div style="display:flex;align-items:center;gap:8px;">
                    <input type="range" id="fi-recog-thr" min="0.25" max="0.70" step="0.01" value="{{ $settings['threshold'] }}" style="flex:1;accent-color:#6366f1;">
                    <span id="fi-recog-thr-val" style="font-size:12px;font-weight:700;color:#6366f1;min-width:36px;">{{ $settings['threshold'] }}</span>
                </div>
            </div>

            <div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:14px;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
                    <div style="font-size:12px;font-weight:600;color:#475569;">
                        👥 Pool
                        <span id="fi-pool-count" style="margin-left:6px;padding:2px 8px;background:#ede9fe;color:#5b21b6;border-radius:10px;font-size:11px;">0</span>
                    </div>
                    <button id="fi-btn-clear-pool" style="font-size:11px;color:#ef4444;background:none;border:none;cursor:pointer;">🗑 Tozalash</button>
                </div>
                <div id="fi-pool-list" style="max-height:280px;overflow-y:auto;">
                    <div style="font-size:12px;color:#94a3b8;text-align:center;padding:20px;">Hali hech kim yuklanmagan</div>
                </div>
            </div>
        </div>
    </div>
    </div>{{-- /fi-tab-recognize --}}

    {{-- Statistika --}}
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-top:16px;">
        <div style="background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:12px;text-align:center;">
            <div style="font-size:1.4rem;font-weight:700;color:#6366f1;">{{ $enrolledCount }}</div>
            <div style="font-size:11px;color:#64748b;margin-top:3px;">Descriptor saqlangan</div>
        </div>
        <div style="background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:12px;text-align:center;">
            <div style="font-size:1.4rem;font-weight:700;color:#0ea5e9;">{{ $totalStudents }}</div>
            <div style="font-size:11px;color:#64748b;margin-top:3px;">Rasmi bor talabalar</div>
        </div>
        <div style="background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:12px;text-align:center;">
            <div style="font-size:1.4rem;font-weight:700;color:#f59e0b;">{{ $totalTeachers }}</div>
            <div style="font-size:11px;color:#64748b;margin-top:3px;">Rasmi bor xodimlar</div>
        </div>
        <div style="background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:12px;text-align:center;">
            <div style="font-size:1.4rem;font-weight:700;color:#10b981;" id="fi-stat-ok">0</div>
            <div style="font-size:11px;color:#64748b;margin-top:3px;">Seans muvaffaqiyatli</div>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
<script>
(function(){
'use strict';

const MODELS   = '/face-models';
const CSRF     = document.querySelector('meta[name="csrf-token"]')?.content || '';
const URL_STUDENT_CHECK = '{{ route("student.face-id.check-student") }}';
const URL_SAVE_DESC     = '{{ route("student.face-id.save-descriptor") }}';
const URL_TEACHER_CHECK = '{{ route("admin.face-id.check-teacher") }}';
const URL_ALL_DESC      = '{{ route("admin.face-id.all-descriptors") }}';

const EAR_CLOSED = 0.22, EAR_OPEN = 0.28, YAW_THRESH = 0.18;
const G = id => document.getElementById(id);
const OPTS = sz => new faceapi.TinyFaceDetectorOptions({ inputSize: sz, scoreThreshold: 0.4 });

let modelsLoaded = false;
let debugStream = null, debugLoop = null;
let recogStream = null, recogLoop = null;
let refDesc = null, blinkCount = 0, eyeClosed = false, sessionOk = 0;
let pool = [];
let liveness = { running:false, phase:'idle', blinksDone:0, _eyeClosed:false, headDone:false, dir:'left', startTs:0 };
let lastFps = 0, lastTs = 0;
const recogHistory = [];

// ── Tabs ──────────────────────────────────────────────────────────────────
document.querySelectorAll('.fi-tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.fi-tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.fi-tab-pane').forEach(p => p.classList.remove('active'));
        btn.classList.add('active');
        G('fi-tab-' + btn.dataset.tab).classList.add('active');
    });
});

// ── Sliders ───────────────────────────────────────────────────────────────
G('fi-cfg-thr').addEventListener('input', function(){
    G('fi-cfg-thr-val').textContent = parseFloat(this.value).toFixed(2);
    G('fi-thr-pct').textContent = '≈ ' + Math.round((1 - parseFloat(this.value)/0.6)*100) + '% yaqinlik';
});
G('fi-recog-thr').addEventListener('input', function(){
    G('fi-recog-thr-val').textContent = parseFloat(this.value).toFixed(2);
});

// ── Logger ────────────────────────────────────────────────────────────────
function log(msg, c='#94a3b8'){
    const b = G('fi-log'), t = new Date().toLocaleTimeString('uz');
    b.innerHTML += '<span style="color:'+c+'">['+t+'] '+msg+'</span>\n';
    b.scrollTop = b.scrollHeight;
}

// ── Models ────────────────────────────────────────────────────────────────
async function loadModels(){
    if(modelsLoaded) return;
    log('Modellar yuklanmoqda...','#38bdf8');
    await Promise.all([
        faceapi.nets.tinyFaceDetector.loadFromUri(MODELS),
        faceapi.nets.faceLandmark68Net.loadFromUri(MODELS),
        faceapi.nets.faceRecognitionNet.loadFromUri(MODELS),
    ]);
    modelsLoaded = true;
    log('Modellar tayyor','#4ade80');
}

// ── Helpers ───────────────────────────────────────────────────────────────
function d2(a,b){ return Math.hypot(a.x-b.x,a.y-b.y); }
function ear(lm){
    const p=lm.positions;
    const e=(i1,i2,i3,i4,i5,i6)=>(d2(p[i2],p[i6])+d2(p[i3],p[i5]))/(2*d2(p[i1],p[i4]));
    return (e(36,37,38,39,40,41)+e(42,43,44,45,46,47))/2;
}
function yaw(lm){
    const p=lm.positions, cx=(p[39].x+p[42].x)/2, fw=Math.abs(p[42].x-p[39].x);
    return fw>0?(p[30].x-cx)/fw:0;
}
function eucl(a,b){ let s=0; for(let i=0;i<128;i++){const d=a[i]-b[i];s+=d*d;} return Math.sqrt(s); }
function cfg(){ return { thr:parseFloat(G('fi-cfg-thr').value), blinks:parseInt(G('fi-cfg-blinks').value), head:G('fi-cfg-head').checked, timeout:parseInt(G('fi-cfg-timeout').value)*1000 }; }

// ── DEBUG CAMERA ──────────────────────────────────────────────────────────
G('fi-btn-cam-on').addEventListener('click', async ()=>{
    G('fi-no-cam').style.display='none';
    try{
        debugStream = await navigator.mediaDevices.getUserMedia({video:{width:{ideal:640},height:{ideal:480},facingMode:'user'}});
        G('fi-video').srcObject = debugStream;
        await new Promise(r=>G('fi-video').onloadedmetadata=r);
        G('fi-overlay').width=G('fi-video').videoWidth;
        G('fi-overlay').height=G('fi-video').videoHeight;
        log('Kamera yoqildi','#4ade80');
        if(!modelsLoaded) await loadModels();
        startDebug();
        G('fi-btn-capture').disabled=false;
        G('fi-btn-capture').style.opacity='1';
    }catch(e){ log('Kamera xato: '+e.message,'#f87171'); }
});
G('fi-btn-cam-off').addEventListener('click',()=>{ stopDebug(); log('Kamera to\'xtatildi','#94a3b8'); });

function stopDebug(){
    if(debugLoop){clearInterval(debugLoop);debugLoop=null;}
    if(debugStream){debugStream.getTracks().forEach(t=>t.stop());debugStream=null;}
    G('fi-btn-capture').disabled=true;
    G('fi-btn-capture').style.opacity='0.5';
}

function startDebug(){
    if(debugLoop) clearInterval(debugLoop);
    const ctx=G('fi-overlay').getContext('2d');
    debugLoop=setInterval(async()=>{
        if(!debugStream||!G('fi-video').videoWidth) return;
        const now=performance.now();
        lastFps=now-lastTs>0?Math.round(1000/(now-lastTs)):lastFps;
        lastTs=now;
        G('fi-m-fps').textContent=lastFps+' fps';
        ctx.clearRect(0,0,G('fi-overlay').width,G('fi-overlay').height);
        let det;
        try{ det=await faceapi.detectSingleFace(G('fi-video'),OPTS(224)).withFaceLandmarks().withFaceDescriptor(); }catch(e){return;}
        if(!det){
            G('fi-m-face').textContent='❌'; G('fi-m-face').style.color='#f87171';
            G('fi-oval').style.borderColor='rgba(255,255,255,0.3)'; return;
        }
        G('fi-m-face').textContent='✅'; G('fi-m-face').style.color='#4ade80';
        G('fi-oval').style.borderColor='#4ade80';
        ctx.fillStyle='rgba(99,102,241,0.7)';
        det.landmarks.positions.forEach(p=>{ctx.beginPath();ctx.arc(p.x,p.y,1.5,0,2*Math.PI);ctx.fill();});

        const E=ear(det.landmarks), Y=yaw(det.landmarks);
        G('fi-m-ear').textContent=E.toFixed(3);
        if(E<EAR_CLOSED){G('fi-m-ear').style.color='#fb923c';G('fi-m-ear-s').textContent='YUMIQ';}
        else if(E<EAR_OPEN){G('fi-m-ear').style.color='#fbbf24';G('fi-m-ear-s').textContent='O\'tish';}
        else{G('fi-m-ear').style.color='#a78bfa';G('fi-m-ear-s').textContent='OCHIQ';}

        const ec=E<EAR_CLOSED;
        if(ec&&!eyeClosed){eyeClosed=true;}
        if(!ec&&eyeClosed){eyeClosed=false;blinkCount++;G('fi-m-blink-n').textContent=blinkCount;G('fi-m-blink-d').textContent='●'.repeat(Math.min(blinkCount,10));log('Ko\'z yumdi #'+blinkCount+' EAR='+E.toFixed(3),'#c4b5fd');}

        G('fi-m-yaw').textContent=Y.toFixed(3);
        if(Y<-YAW_THRESH){G('fi-m-yaw').style.color='#fb923c';G('fi-m-yaw-s').textContent='← Chap';}
        else if(Y>YAW_THRESH){G('fi-m-yaw').style.color='#fb923c';G('fi-m-yaw-s').textContent='→ O\'ng';}
        else{G('fi-m-yaw').style.color='#94a3b8';G('fi-m-yaw-s').textContent='To\'g\'ri';}

        if(refDesc){
            const dist=eucl(det.descriptor,refDesc), conf=Math.max(0,Math.min(100,(1-dist/0.6)*100)), thr=cfg().thr;
            G('fi-m-dist').textContent=dist.toFixed(3);
            G('fi-m-conf').textContent=conf.toFixed(1)+'%';
            if(dist<=thr){G('fi-m-dist').style.color='#4ade80';G('fi-m-dist-s').style.color='#4ade80';G('fi-m-dist-s').textContent='MOS (< '+thr+')';}
            else{G('fi-m-dist').style.color='#f87171';G('fi-m-dist-s').style.color='#f87171';G('fi-m-dist-s').textContent='MOS EMAS (> '+thr+')';}
        }
        if(liveness.running) doLiveness(det,E,Y);
    }, 150);
}

// ── LIVENESS ──────────────────────────────────────────────────────────────
function doLiveness(det,E,Y){
    const c=cfg(), elapsed=Date.now()-liveness.startTs;
    if(elapsed>c.timeout){liveness.running=false;setLv('⏰ Vaqt tugadi','#f87171',0);return;}
    if(c.blinks>0&&liveness.phase==='blink'){
        const ec=E<EAR_CLOSED;
        if(ec&&!liveness._eyeClosed)liveness._eyeClosed=true;
        if(!ec&&liveness._eyeClosed){liveness._eyeClosed=false;liveness.blinksDone++;setLv('Ko\'z: '+liveness.blinksDone+'/'+c.blinks,'#6366f1',Math.round(liveness.blinksDone/c.blinks*45));}
        if(liveness.blinksDone>=c.blinks){liveness.phase=c.head?'head':'done';if(c.head){liveness.dir=Math.random()>.5?'left':'right';setLv('Boshni '+(liveness.dir==='left'?'chapga':'o\'ngga')+' burting','#f59e0b',55);}}
    }
    if(c.head&&liveness.phase==='head'){
        const t=liveness.dir==='left'?(Y<-YAW_THRESH):(Y>YAW_THRESH);
        if(t&&!liveness.headDone){liveness.headDone=true;setLv('Endi to\'g\'ri qarang','#10b981',85);}
        if(liveness.headDone&&Math.abs(Y)<0.08)liveness.phase='done';
    }
    if(liveness.phase==='done'){liveness.running=false;setLv('✅ Liveness o\'tdi!','#10b981',100);log('LIVENESS PASSED','#4ade80');}
}
function setLv(t,c,p){G('fi-liveness-status').textContent=t;G('fi-liveness-status').style.color=c;G('fi-liveness-bar').style.width=p+'%';G('fi-liveness-bar').style.background=p>=100?'#10b981':'#6366f1';}

// ── LOAD STUDENT (debug) ─────────────────────────────────────────────────
G('fi-btn-load-student').addEventListener('click', async()=>{
    const id=G('fi-inp-student').value.trim(); if(!id)return;
    G('fi-student-err').style.display='none'; G('fi-student-info').style.display='none';
    G('fi-btn-load-student').textContent='...';
    try{
        const r=await fetch(URL_STUDENT_CHECK,{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF},body:JSON.stringify({student_id_number:id})});
        const d=await r.json(); if(!r.ok)throw new Error(d.error||'Xato');
        G('fi-student-name').textContent=d.full_name;
        G('fi-student-enroll').textContent=d.has_descriptor?'✅ Descriptor DB da':'⚠️ Descriptor yo\'q';
        G('fi-ref-img').src=d.photo_url; G('fi-student-info').style.display='block';
        log('Yuklandi: '+d.full_name,'#38bdf8');
        if(!modelsLoaded)await loadModels();
        const img=await faceapi.fetchImage(d.photo_url);
        const det=await faceapi.detectSingleFace(img,OPTS(320)).withFaceLandmarks().withFaceDescriptor();
        if(!det){log('Rasmda yuz topilmadi','#fbbf24');}
        else{
            refDesc=det.descriptor; log('Reference descriptor tayyor','#4ade80');
            fetch(URL_SAVE_DESC,{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF},body:JSON.stringify({student_id:d.student_id,descriptor:Array.from(refDesc),source_url:d.photo_url})}).catch(()=>{});
        }
    }catch(e){G('fi-student-err').textContent=e.message;G('fi-student-err').style.display='block';log('Xato: '+e.message,'#f87171');}
    finally{G('fi-btn-load-student').textContent='Yuklash';}
});
G('fi-inp-student').addEventListener('keydown',e=>{if(e.key==='Enter')G('fi-btn-load-student').click();});

// ── CAPTURE (debug) ───────────────────────────────────────────────────────
G('fi-btn-capture').addEventListener('click', async()=>{
    if(!refDesc){log('Avval talabani tanlang','#fbbf24');return;}
    const det=await faceapi.detectSingleFace(G('fi-video'),OPTS(320)).withFaceLandmarks().withFaceDescriptor();
    if(!det){log('Yuz topilmadi','#f87171');return;}
    const dist=eucl(det.descriptor,refDesc), conf=Math.max(0,Math.min(100,(1-dist/0.6)*100)), thr=cfg().thr, pass=dist<=thr;
    if(pass){sessionOk++;G('fi-stat-ok').textContent=sessionOk;}
    const sc=document.createElement('canvas');
    sc.width=Math.min(G('fi-video').videoWidth,320);
    sc.height=Math.round(G('fi-video').videoHeight*sc.width/G('fi-video').videoWidth);
    sc.getContext('2d').drawImage(G('fi-video'),0,0,sc.width,sc.height);
    G('fi-snap-img').src=sc.toDataURL('image/jpeg',0.7); G('fi-snap-wrap').style.display='block';
    const col=pass?'#10b981':'#ef4444', ic=pass?'✅':'❌';
    G('fi-result-body').innerHTML='<div style="font-size:2rem;margin-bottom:8px;">'+ic+'</div><div style="font-weight:700;color:'+col+';font-size:1rem;margin-bottom:8px;">'+(pass?'MOS KELDI':'MOS KELMADI')+'</div><div style="font-size:12px;color:#64748b;">distance='+dist.toFixed(4)+' | conf='+conf.toFixed(1)+'% | thr='+thr+'</div>';
    G('fi-result').style.display='block'; G('fi-result').style.borderColor=col;
    log(ic+' dist='+dist.toFixed(3)+' conf='+conf.toFixed(1)+'%',pass?'#4ade80':'#f87171');
});

// ── LIVENESS BUTTONS ──────────────────────────────────────────────────────
function startLv(phase){
    if(!debugStream){log('Avval kamerani yoqing','#fbbf24');return;}
    liveness={running:true,phase,blinksDone:0,_eyeClosed:false,headDone:false,dir:Math.random()>.5?'left':'right',startTs:Date.now()};
    const c=cfg();
    if(phase==='blink')setLv('Ko\'zni '+c.blinks+' marta yumib-oching','#6366f1',5);
    else setLv('Boshni '+(liveness.dir==='left'?'chapga':'o\'ngga')+' burting','#f59e0b',5);
}
G('fi-btn-blink').addEventListener('click',()=>{blinkCount=0;G('fi-m-blink-n').textContent='0';G('fi-m-blink-d').textContent='';startLv('blink');});
G('fi-btn-head').addEventListener('click',()=>startLv('head'));
G('fi-btn-full').addEventListener('click',()=>{blinkCount=0;G('fi-m-blink-n').textContent='0';G('fi-m-blink-d').textContent='';const c=cfg();startLv(c.blinks>0?'blink':(c.head?'head':'done'));});

// ── RECOG CAMERA ──────────────────────────────────────────────────────────
G('fi-btn-recog-on').addEventListener('click', async()=>{
    G('fi-no-cam2').style.display='none';
    try{
        recogStream=await navigator.mediaDevices.getUserMedia({video:{width:{ideal:640},height:{ideal:480},facingMode:'user'}});
        G('fi-video2').srcObject=recogStream;
        await new Promise(r=>G('fi-video2').onloadedmetadata=r);
        G('fi-overlay2').width=G('fi-video2').videoWidth; G('fi-overlay2').height=G('fi-video2').videoHeight;
        if(!modelsLoaded)await loadModels();
        startRecog(); G('fi-live-badge').style.display='inline';
    }catch(e){alert('Kamera xato: '+e.message);}
});
G('fi-btn-recog-off').addEventListener('click',()=>{
    if(recogLoop){clearInterval(recogLoop);recogLoop=null;}
    if(recogStream){recogStream.getTracks().forEach(t=>t.stop());recogStream=null;}
    G('fi-live-badge').style.display='none'; G('fi-recog-overlay').style.display='none';
});

function startRecog(){
    if(recogLoop)clearInterval(recogLoop);
    const ctx=G('fi-overlay2').getContext('2d');
    let tick=0;
    recogLoop=setInterval(async()=>{
        if(!recogStream||!G('fi-video2').videoWidth)return;
        if(tick++%2!==0)return;
        ctx.clearRect(0,0,G('fi-overlay2').width,G('fi-overlay2').height);
        let det;
        try{det=await faceapi.detectSingleFace(G('fi-video2'),OPTS(224)).withFaceLandmarks().withFaceDescriptor();}catch(e){return;}
        if(!det){G('fi-recog-overlay').style.display='none';return;}
        ctx.fillStyle='rgba(99,102,241,0.6)';
        det.landmarks.positions.forEach(p=>{ctx.beginPath();ctx.arc(p.x,p.y,1.5,0,2*Math.PI);ctx.fill();});
        if(pool.length===0){G('fi-recog-overlay').style.display='block';G('fi-recog-name').textContent='Pool bo\'sh — odamlarni yuklang';G('fi-recog-sub').textContent='';return;}
        const thr=parseFloat(G('fi-recog-thr').value);
        let best=null, bestD=Infinity;
        pool.forEach(p=>{const d=eucl(det.descriptor,p.descriptor);if(d<bestD){bestD=d;best=p;}});
        const conf=Math.max(0,Math.min(100,(1-bestD/0.6)*100));
        G('fi-recog-overlay').style.display='block';
        const box=det.detection.box;
        if(bestD<=thr){
            G('fi-recog-name').textContent=best.name; G('fi-recog-name').style.color='#fff';
            G('fi-recog-sub').textContent=(best.type==='teacher'?'👷 Xodim':'🎓 Talaba')+' · '+conf.toFixed(1)+'% · d='+bestD.toFixed(3);
            ctx.strokeStyle='#4ade80';ctx.lineWidth=2;ctx.strokeRect(box.x,box.y,box.width,box.height);
            ctx.fillStyle='rgba(74,222,128,0.1)';ctx.fillRect(box.x,box.y,box.width,box.height);
            const last=recogHistory[recogHistory.length-1];
            if(!last||last.id!==best.id||Date.now()-last.ts>3000){
                recogHistory.push({id:best.id,name:best.name,type:best.type,conf:conf.toFixed(1),ts:Date.now()});
                if(recogHistory.length>10)recogHistory.shift();
                renderHistory(); sessionOk++;G('fi-stat-ok').textContent=sessionOk;
            }
        }else{
            G('fi-recog-name').textContent='Noma\'lum shaxs'; G('fi-recog-name').style.color='#fbbf24';
            G('fi-recog-sub').textContent='Eng yaqin: '+(best?.name||'—')+' · d='+bestD.toFixed(3)+' (> '+thr+')';
            ctx.strokeStyle='#f87171';ctx.lineWidth=2;ctx.strokeRect(box.x,box.y,box.width,box.height);
        }
    },150);
}

function renderHistory(){
    const el=G('fi-recog-history');
    if(!recogHistory.length){el.innerHTML='<div style="font-size:12px;color:#94a3b8;">Tanish hali boshlanmagan</div>';return;}
    el.innerHTML=[...recogHistory].reverse().slice(0,8).map(r=>{
        const t=new Date(r.ts).toLocaleTimeString('uz');
        return '<div class="fi-person-row match"><span class="fi-badge '+(r.type==='teacher'?'fi-badge-t':'fi-badge-s')+'">'+(r.type==='teacher'?'Xodim':'Talaba')+'</span><span style="flex:1;font-size:12px;font-weight:600;color:#1e293b;">'+r.name+'</span><span style="font-size:11px;color:#64748b;">'+r.conf+'%</span><span style="font-size:10px;color:#94a3b8;margin-left:6px;">'+t+'</span></div>';
    }).join('');
}

// ── LOAD ALL STUDENTS ─────────────────────────────────────────────────────
G('fi-btn-load-all').addEventListener('click', async()=>{
    G('fi-btn-load-all').textContent='⏳...'; G('fi-btn-load-all').disabled=true;
    G('fi-students-bar').style.width='10%'; G('fi-students-status').textContent='Server so\'rovi...';
    try{
        const r=await fetch(URL_ALL_DESC,{headers:{'X-CSRF-TOKEN':CSRF}});
        const d=await r.json();
        G('fi-students-bar').style.width='70%';
        let added=0;
        d.people.forEach(p=>{
            if(!pool.find(x=>x.id===p.id&&x.type==='student')){
                pool.push({id:p.id,name:p.name,idNumber:p.id_number,type:'student',descriptor:new Float32Array(p.descriptor),photoUrl:p.photo_url});
                added++;
            }
        });
        G('fi-students-bar').style.width='100%';
        G('fi-students-status').textContent='✅ '+added+' talaba qo\'shildi (jami '+pool.length+')';
        renderPool();
    }catch(e){G('fi-students-status').textContent='❌ '+e.message;G('fi-students-bar').style.background='#ef4444';}
    finally{G('fi-btn-load-all').textContent='⬇️ Yuklash';G('fi-btn-load-all').disabled=false;}
});

// ── ADD TEACHER ───────────────────────────────────────────────────────────
G('fi-btn-add-teacher').addEventListener('click', async()=>{
    const empId=G('fi-inp-teacher').value.trim(); if(!empId)return;
    showTMsg('⏳ Yuklanmoqda...','#64748b','#f1f5f9'); G('fi-btn-add-teacher').disabled=true;
    try{
        const r=await fetch(URL_TEACHER_CHECK,{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF},body:JSON.stringify({employee_id_number:empId})});
        const d=await r.json(); if(!r.ok)throw new Error(d.error||'Xato');
        if(!d.has_photo)throw new Error('Bu xodimning rasmi yo\'q');
        showTMsg('🖼 '+d.full_name+' — descriptor hisoblanmoqda...','#0369a1','#f0f9ff');
        if(!modelsLoaded)await loadModels();
        const img=await faceapi.fetchImage(d.photo_url);
        const det=await faceapi.detectSingleFace(img,OPTS(320)).withFaceLandmarks().withFaceDescriptor();
        if(!det)throw new Error('Rasmda yuz aniqlanmadi');
        const idx=pool.findIndex(p=>p.id===d.teacher_id&&p.type==='teacher');
        if(idx>=0)pool.splice(idx,1);
        pool.push({id:d.teacher_id,name:d.full_name,idNumber:empId,type:'teacher',descriptor:det.descriptor,photoUrl:d.photo_url,position:d.position});
        showTMsg('✅ '+d.full_name+' qo\'shildi','#15803d','#dcfce7');
        G('fi-inp-teacher').value=''; renderPool();
    }catch(e){showTMsg('❌ '+e.message,'#b91c1c','#fef2f2');}
    finally{G('fi-btn-add-teacher').disabled=false;}
});
G('fi-inp-teacher').addEventListener('keydown',e=>{if(e.key==='Enter')G('fi-btn-add-teacher').click();});
function showTMsg(t,c,bg){const el=G('fi-teacher-msg');el.style.display='block';el.style.color=c;el.style.background=bg;el.textContent=t;}

// ── POOL RENDER ───────────────────────────────────────────────────────────
function renderPool(){
    G('fi-pool-count').textContent=pool.length;
    const el=G('fi-pool-list');
    if(!pool.length){el.innerHTML='<div style="font-size:12px;color:#94a3b8;text-align:center;padding:20px;">Hali hech kim yuklanmagan</div>';return;}
    const teachers=pool.filter(p=>p.type==='teacher'), students=pool.filter(p=>p.type==='student');
    let h='';
    if(teachers.length){
        h+='<div style="font-size:10px;font-weight:600;color:#94a3b8;margin-bottom:4px;margin-top:4px;">XODIMLAR ('+teachers.length+')</div>';
        teachers.forEach(p=>{h+='<div class="fi-person-row" style="border-color:#fed7aa;"><img src="'+p.photoUrl+'" style="width:30px;height:30px;border-radius:50%;object-fit:cover;border:2px solid #f59e0b;" onerror="this.style.opacity=0.3"><div style="flex:1;min-width:0;"><div style="font-size:12px;font-weight:600;color:#1e293b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">'+p.name+'</div><div style="font-size:10px;color:#94a3b8;">'+(p.position||p.idNumber)+'</div></div><span class="fi-badge fi-badge-t">Xodim</span><button onclick="removePerson('+p.id+',\'teacher\')" style="background:none;border:none;color:#ef4444;cursor:pointer;font-size:14px;padding:0 4px;">×</button></div>';});
    }
    if(students.length){
        h+='<div style="font-size:10px;font-weight:600;color:#94a3b8;margin-top:8px;margin-bottom:4px;">TALABALAR ('+students.length+')</div>';
        students.slice(0,20).forEach(p=>{h+='<div class="fi-person-row"><img src="'+p.photoUrl+'" style="width:30px;height:30px;border-radius:50%;object-fit:cover;" onerror="this.style.opacity=0.3"><div style="flex:1;min-width:0;"><div style="font-size:12px;font-weight:600;color:#1e293b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">'+p.name+'</div><div style="font-size:10px;color:#94a3b8;">'+p.idNumber+'</div></div><span class="fi-badge fi-badge-s">Talaba</span><button onclick="removePerson('+p.id+',\'student\')" style="background:none;border:none;color:#ef4444;cursor:pointer;font-size:14px;padding:0 4px;">×</button></div>';});
        if(students.length>20)h+='<div style="font-size:11px;color:#94a3b8;text-align:center;padding:8px;">...va yana '+(students.length-20)+' ta</div>';
    }
    el.innerHTML=h;
}
window.removePerson=(id,type)=>{const i=pool.findIndex(p=>p.id===id&&p.type===type);if(i>=0)pool.splice(i,1);renderPool();};
G('fi-btn-clear-pool').addEventListener('click',()=>{if(!confirm('Barcha o\'chirilsinmi?'))return;pool=[];renderPool();recogHistory.length=0;renderHistory();});

})();
</script>

</x-app-layout>
