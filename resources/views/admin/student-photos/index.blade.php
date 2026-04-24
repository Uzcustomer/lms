<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Talaba rasmlari
        </h2>
    </x-slot>

    <div class="py-6" x-data="{
            selected: [],
            toggleAllOnPage(el) {
                const checkboxes = document.querySelectorAll('.sp-row-check');
                if (el.checked) {
                    checkboxes.forEach(cb => {
                        const id = Number(cb.value);
                        if (!this.selected.includes(id)) this.selected.push(id);
                        cb.checked = true;
                    });
                } else {
                    checkboxes.forEach(cb => { cb.checked = false; });
                    this.selected = [];
                }
            },
            async selectAllMatchingFilter() {
                const params = new URLSearchParams(new FormData(document.getElementById('sp-filter-form')));
                params.set('rerun', '1');
                try {
                    const res = await fetch(`/admin/student-photos/pending-ids?${params.toString()}`, { headers: { 'Accept': 'application/json' } });
                    const data = await res.json();
                    this.selected = data.ids || [];
                    document.querySelectorAll('.sp-row-check').forEach(cb => {
                        cb.checked = this.selected.includes(Number(cb.value));
                    });
                    alert(`Filtr bo'yicha ${this.selected.length} ta rasm tanlandi`);
                } catch (e) {
                    alert('Xatolik: ' + e.message);
                }
            },
            clearSelection() {
                this.selected = [];
                document.querySelectorAll('.sp-row-check').forEach(cb => { cb.checked = false; });
                const all = document.getElementById('sp-select-all');
                if (all) all.checked = false;
            },
            lightbox: { open: false, src: '', alt: '' },
            compare: {
                open: false, profile: '', uploaded: '', title: '',
                photoId: null, loading: false, result: null, error: null,
                quality: null, qualityLoading: false, qualityError: null,
            },
            reject: { open: false, id: null, name: '' },
            bulk: {
                open: false, phase: 'confirm', // confirm | running | done
                ids: [], total: 0, processed: 0, succeeded: 0, failed: 0,
                currentName: '', cancel: false, errors: [],
                runQuality: true,
            },
            openLightbox(src, alt) { this.lightbox = { open: true, src, alt }; },
            openCompare(photoId, profile, uploaded, title, existing, existingQuality) {
                this.compare = {
                    open: true, profile, uploaded, title,
                    photoId, loading: false, result: existing || null, error: null,
                    quality: existingQuality || null, qualityLoading: false, qualityError: null,
                };
            },
            async runQualityCheck() {
                this.compare.qualityLoading = true;
                this.compare.qualityError = null;
                try {
                    const res = await fetch(`/admin/student-photos/${this.compare.photoId}/check-quality`, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                            'Accept': 'application/json',
                        },
                    });
                    const data = await res.json();
                    if (!res.ok) {
                        this.compare.qualityError = data.error || 'Nomaʼlum xatolik';
                    } else {
                        this.compare.quality = data;
                    }
                } catch (e) {
                    this.compare.qualityError = e.message;
                } finally {
                    this.compare.qualityLoading = false;
                }
            },
            openReject(id, name) { this.reject = { open: true, id, name }; },
            async runSimilarityCheck() {
                this.compare.loading = true;
                this.compare.error = null;
                try {
                    const res = await fetch(`/admin/student-photos/${this.compare.photoId}/check-similarity`, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                            'Accept': 'application/json',
                        },
                    });
                    const data = await res.json();
                    if (!res.ok) {
                        this.compare.error = data.error || 'Nomaʼlum xatolik';
                    } else {
                        this.compare.result = data;
                    }
                } catch (e) {
                    this.compare.error = e.message;
                } finally {
                    this.compare.loading = false;
                }
            },
            openBulk() {
                if (!this.selected.length) {
                    alert('Avval pastdagi ☑ belgi bilan rasmlarni tanlang');
                    return;
                }
                this.bulk = {
                    open: true, phase: 'confirm',
                    ids: [...this.selected],
                    total: this.selected.length,
                    processed: 0, succeeded: 0, failed: 0,
                    currentName: '', cancel: false, errors: [],
                    runQuality: true, runSimilarity: true,
                };
            },
            async runBulk() {
                this.bulk.phase = 'running';
                const csrf = document.querySelector('meta[name=csrf-token]').content;
                for (let i = 0; i < this.bulk.ids.length; i++) {
                    if (this.bulk.cancel) break;
                    const id = this.bulk.ids[i];
                    this.bulk.currentName = `#${id} (${i + 1}/${this.bulk.total})`;
                    let okOne = true;
                    try {
                        if (this.bulk.runSimilarity) {
                            const r1 = await fetch(`/admin/student-photos/${id}/check-similarity`, {
                                method: 'POST',
                                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                            });
                            if (!r1.ok) {
                                okOne = false;
                                const err = await r1.json().catch(() => ({}));
                                this.bulk.errors.push(`#${id} (similarity): ${err.error || ('HTTP ' + r1.status)}`);
                            }
                        }
                        if (this.bulk.runQuality) {
                            const r2 = await fetch(`/admin/student-photos/${id}/check-quality`, {
                                method: 'POST',
                                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                            });
                            if (!r2.ok) {
                                okOne = false;
                                const err = await r2.json().catch(() => ({}));
                                this.bulk.errors.push(`#${id} (quality): ${err.error || ('HTTP ' + r2.status)}`);
                            }
                        }
                        if (okOne) { this.bulk.succeeded++; } else { this.bulk.failed++; }
                    } catch (e) {
                        this.bulk.failed++;
                        this.bulk.errors.push(`#${id}: ${e.message}`);
                    }
                    this.bulk.processed = i + 1;
                }
                this.bulk.phase = 'done';
            },
            rowQuality: { id: null, loading: false, error: null },
            async runRowQuality(photoId) {
                this.rowQuality = { id: photoId, loading: true, error: null };
                try {
                    const res = await fetch(`/admin/student-photos/${photoId}/check-quality`, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                            'Accept': 'application/json',
                        },
                    });
                    const data = await res.json();
                    if (!res.ok) {
                        this.rowQuality.error = data.error || 'Xatolik';
                        alert('Sifat tekshiruvi xato: ' + this.rowQuality.error);
                    } else {
                        location.reload();
                    }
                } catch (e) {
                    this.rowQuality.error = e.message;
                    alert('Xatolik: ' + e.message);
                } finally {
                    this.rowQuality.loading = false;
                }
            },
            review: {
                open: false, mode: 'approve', phase: 'confirm',
                ids: [], total: 0, processed: 0, succeeded: 0, failed: 0,
                reason: '', cancel: false, errors: [],
            },
            openReview(mode) {
                if (!this.selected.length) {
                    alert('Avval pastdagi ☑ belgi bilan rasmlarni tanlang');
                    return;
                }
                this.review = {
                    open: true, mode: mode, phase: 'confirm',
                    ids: [...this.selected],
                    total: this.selected.length,
                    processed: 0, succeeded: 0, failed: 0,
                    reason: '', cancel: false, errors: [],
                };
            },
            async runReview() {
                if (this.review.mode === 'reject' && !this.review.reason.trim()) {
                    this.review.errors.push('Rad etish sababi kiritilishi shart.');
                    return;
                }
                this.review.phase = 'running';
                const endpoint = this.review.mode === 'approve' ? 'approve'
                                : this.review.mode === 'revert' ? 'revert'
                                : 'reject';
                const csrf = document.querySelector('meta[name=csrf-token]').content;
                for (let i = 0; i < this.review.ids.length; i++) {
                    if (this.review.cancel) break;
                    const id = this.review.ids[i];
                    try {
                        const body = new URLSearchParams();
                        body.append('_token', csrf);
                        if (this.review.mode === 'reject') {
                            body.append('rejection_reason', this.review.reason);
                        }
                        const res = await fetch(`/admin/student-photos/${id}/${endpoint}`, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': csrf,
                                'Accept': 'application/json',
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: body,
                        });
                        if (res.ok) { this.review.succeeded++; }
                        else {
                            this.review.failed++;
                            const err = await res.json().catch(() => ({}));
                            this.review.errors.push(`#${id}: ${err.message || err.error || ('HTTP ' + res.status)}`);
                        }
                    } catch (e) {
                        this.review.failed++;
                        this.review.errors.push(`#${id}: ${e.message}`);
                    }
                    this.review.processed = i + 1;
                }
                this.review.phase = 'done';
            },
        }">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">

            @if(session('success'))
                <div class="mb-4 rounded-md bg-green-50 border border-green-200 text-green-800 px-4 py-3 text-sm">
                    {{ session('success') }}
                </div>
            @endif
            @if(session('error'))
                <div class="mb-4 rounded-md bg-red-50 border border-red-200 text-red-800 px-4 py-3 text-sm">
                    {{ session('error') }}
                </div>
            @endif

            {{-- Statistika --}}
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-white shadow-sm rounded-lg p-4">
                    <div class="text-sm text-gray-500">Jami rasmlar</div>
                    <div class="text-2xl font-semibold text-gray-900">{{ number_format($stats['total']) }}</div>
                </div>
                <div class="bg-white shadow-sm rounded-lg p-4">
                    <div class="text-sm text-gray-500">Kutilmoqda</div>
                    <div class="text-2xl font-semibold text-yellow-600">{{ number_format($stats['pending']) }}</div>
                </div>
                <div class="bg-white shadow-sm rounded-lg p-4">
                    <div class="text-sm text-gray-500">Tasdiqlangan</div>
                    <div class="text-2xl font-semibold text-green-600">{{ number_format($stats['approved']) }}</div>
                </div>
                <div class="bg-white shadow-sm rounded-lg p-4">
                    <div class="text-sm text-gray-500">Rad etilgan</div>
                    <div class="text-2xl font-semibold text-red-600">{{ number_format($stats['rejected']) }}</div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">

                {{-- Filtrlar (JN o'zlashtirish stilida) --}}
                <form id="sp-filter-form" method="GET" action="{{ route('admin.student-photos.index') }}" class="filter-container">
                    <div class="filter-row">
                        <div class="filter-item" style="flex: 1; min-width: 220px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#3b82f6;"></span> Qidiruv (FISH / ID)</label>
                            <input type="text" name="search" value="{{ request('search') }}" placeholder="Ism yoki talaba ID" class="sp-text-input" />
                        </div>
                        <div class="filter-item" style="min-width: 150px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#ef4444;"></span> Holat</label>
                            <select name="status" class="select2-sp" style="width: 100%;">
                                <option value="">Barchasi</option>
                                <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Kutilmoqda</option>
                                <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>Tasdiqlangan</option>
                                <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>Rad etilgan</option>
                            </select>
                        </div>
                        <div class="filter-item" style="flex: 1; min-width: 200px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#10b981;"></span> Fakultet</label>
                            <select name="department" class="select2-sp" style="width: 100%;">
                                <option value="">Barchasi</option>
                                @foreach($departments as $d)
                                    <option value="{{ $d }}" {{ request('department') == $d ? 'selected' : '' }}>{{ $d }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="filter-item" style="flex: 1; min-width: 220px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#06b6d4;"></span> Yo'nalish</label>
                            <select name="specialty" class="select2-sp" style="width: 100%;">
                                <option value="">Barchasi</option>
                                @foreach($specialties as $s)
                                    <option value="{{ $s }}" {{ request('specialty') == $s ? 'selected' : '' }}>{{ $s }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="filter-item" style="min-width: 100px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#94a3b8;"></span> Sahifada</label>
                            <select name="per_page" class="select2-sp" style="width: 100%;">
                                @foreach([10, 25, 30, 50, 100, 200] as $ps)
                                    <option value="{{ $ps }}" {{ request('per_page', 30) == $ps ? 'selected' : '' }}>{{ $ps }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="filter-row">
                        <div class="filter-item" style="min-width: 120px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#8b5cf6;"></span> Kurs</label>
                            <select name="level" class="select2-sp" style="width: 100%;">
                                <option value="">Barchasi</option>
                                @foreach($levels as $l)
                                    <option value="{{ $l }}" {{ request('level') == $l ? 'selected' : '' }}>{{ $l }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="filter-item" style="min-width: 170px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#1a3268;"></span> Guruh</label>
                            <select name="group" class="select2-sp" style="width: 100%;">
                                <option value="">Barchasi</option>
                                @foreach($groups as $g)
                                    <option value="{{ $g }}" {{ request('group') == $g ? 'selected' : '' }}>{{ $g }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="filter-item" style="flex: 1; min-width: 200px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#f59e0b;"></span> Tyutor</label>
                            <select name="tutor" class="select2-sp" style="width: 100%;">
                                <option value="">Barchasi</option>
                                @foreach($tutors as $t)
                                    <option value="{{ $t }}" {{ request('tutor') == $t ? 'selected' : '' }}>{{ $t }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="filter-item" style="min-width: 160px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#6366f1;"></span> AI tekshiruvi</label>
                            <select name="similarity" class="select2-sp" style="width: 100%;">
                                <option value="">Barchasi</option>
                                <option value="match" {{ request('similarity') == 'match' ? 'selected' : '' }}>O'xshash</option>
                                <option value="mismatch" {{ request('similarity') == 'mismatch' ? 'selected' : '' }}>Farqli</option>
                                <option value="unchecked" {{ request('similarity') == 'unchecked' ? 'selected' : '' }}>Tekshirilmagan</option>
                            </select>
                        </div>
                        <div class="filter-item" style="min-width: 200px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#16a34a;"></span> AI % (chegara)</label>
                            <div style="display:flex;gap:4px;align-items:stretch;">
                                <select name="similarity_op" class="sp-op-select">
                                    <option value="">—</option>
                                    <option value=">" {{ request('similarity_op') == '>' ? 'selected' : '' }}>&gt;</option>
                                    <option value=">=" {{ request('similarity_op') == '>=' ? 'selected' : '' }}>&ge;</option>
                                    <option value="<" {{ request('similarity_op') == '<' ? 'selected' : '' }}>&lt;</option>
                                    <option value="<=" {{ request('similarity_op') == '<=' ? 'selected' : '' }}>&le;</option>
                                </select>
                                <input type="number" name="similarity_value" value="{{ request('similarity_value') }}"
                                       min="0" max="100" step="0.1" placeholder="foiz"
                                       class="sp-text-input" style="flex:1;min-width:0;" />
                            </div>
                        </div>
                        <div class="filter-item" style="min-width: 150px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#0ea5e9;"></span> Sifat holati</label>
                            <select name="quality" class="select2-sp" style="width: 100%;">
                                <option value="">Barchasi</option>
                                <option value="passed" {{ request('quality') == 'passed' ? 'selected' : '' }}>Maqbul</option>
                                <option value="failed" {{ request('quality') == 'failed' ? 'selected' : '' }}>Xato</option>
                                <option value="unchecked" {{ request('quality') == 'unchecked' ? 'selected' : '' }}>Tekshirilmagan</option>
                            </select>
                        </div>
                        <div class="filter-item" style="min-width: 200px;">
                            <label class="filter-label"><span class="fl-dot" style="background:#14b8a6;"></span> Sifat % (chegara)</label>
                            <div style="display:flex;gap:4px;align-items:stretch;">
                                <select name="quality_op" class="sp-op-select">
                                    <option value="">—</option>
                                    <option value=">" {{ request('quality_op') == '>' ? 'selected' : '' }}>&gt;</option>
                                    <option value=">=" {{ request('quality_op') == '>=' ? 'selected' : '' }}>&ge;</option>
                                    <option value="<" {{ request('quality_op') == '<' ? 'selected' : '' }}>&lt;</option>
                                    <option value="<=" {{ request('quality_op') == '<=' ? 'selected' : '' }}>&le;</option>
                                </select>
                                <input type="number" name="quality_value" value="{{ request('quality_value') }}"
                                       min="0" max="100" step="0.1" placeholder="foiz"
                                       class="sp-text-input" style="flex:1;min-width:0;" />
                            </div>
                        </div>
                        <div class="filter-item" style="flex: 1; min-width: 180px;">
                            <label class="filter-label">&nbsp;</label>
                            <div style="display:flex;gap:6px;flex-wrap:wrap;">
                                <a href="{{ route('admin.student-photos.index') }}" class="btn-clear">Tozalash</a>
                                <button type="button" @click="selectAllMatchingFilter()" class="btn-clear"
                                        style="background:#eef2ff;border-color:#c7d2fe;color:#4338ca;"
                                        title="Filtr bo'yicha barcha rasmlarni tanlash">
                                    Filtrni tanlash
                                </button>
                            </div>
                        </div>
                    </div>
                </form>

                {{-- Bulk amallar toolbar --}}
                <div class="px-6 pt-4 pb-2 border-t border-gray-100 bg-gray-50 flex items-center gap-2 flex-wrap">
                    <span class="text-xs font-bold uppercase text-gray-500">Bulk amallar:</span>
                    <span class="text-sm text-gray-700">
                        <strong x-text="selected.length"></strong> ta tanlangan
                    </span>
                    <div class="flex gap-2 ml-auto flex-wrap">
                        <button type="button" @click="openBulk()" :disabled="selected.length === 0"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-indigo-600 text-white text-xs font-semibold rounded-md hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                            AI tahlil
                        </button>
                        <button type="button" @click="openReview('approve')" :disabled="selected.length === 0"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-green-600 text-white text-xs font-semibold rounded-md hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            Bulk qabul
                        </button>
                        <button type="button" @click="openReview('reject')" :disabled="selected.length === 0"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-red-600 text-white text-xs font-semibold rounded-md hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            Bulk rad
                        </button>
                        <button type="button" @click="openReview('revert')" :disabled="selected.length === 0"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-orange-500 text-white text-xs font-semibold rounded-md hover:bg-orange-600 disabled:opacity-50 disabled:cursor-not-allowed"
                                title="Tasdiqlangan/rad etilgan rasmlarni 'kutilmoqda' holatiga qaytarish">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                            Qaytarish
                        </button>
                        <button type="button" x-show="selected.length > 0" @click="clearSelection()"
                                class="inline-flex items-center gap-1 px-2 py-1.5 text-xs text-gray-500 hover:text-gray-700">
                            Tanlovni tozalash
                        </button>
                    </div>
                </div>

                <div class="p-6 pt-3">
                    {{-- Jadval --}}
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-2 text-center font-medium text-gray-600">
                                        <input type="checkbox" id="sp-select-all"
                                               @click="toggleAllOnPage($event.target)"
                                               class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                               title="Sahifadagi barchasini tanlash">
                                    </th>
                                    <th class="px-3 py-2 text-left font-medium text-gray-600"><a href="?{{ http_build_query(array_merge(request()->except(['sort', 'dir', 'page']), ['sort' => 'id', 'dir' => (request('sort') == 'id' && request('dir') == 'asc') ? 'desc' : 'asc'])) }}" class="sp-sort-link">#{!! request('sort') == 'id' ? (request('dir') == 'asc' ? ' ▲' : ' ▼') : '' !!}</a></th>
                                    <th class="px-3 py-2 text-left font-medium text-gray-600"><a href="?{{ http_build_query(array_merge(request()->except(['sort', 'dir', 'page']), ['sort' => 'full_name', 'dir' => (request('sort') == 'full_name' && request('dir') == 'asc') ? 'desc' : 'asc'])) }}" class="sp-sort-link">FISH{!! request('sort') == 'full_name' ? (request('dir') == 'asc' ? ' ▲' : ' ▼') : '' !!}</a></th>
                                    <th class="px-3 py-2 text-left font-medium text-gray-600"><a href="?{{ http_build_query(array_merge(request()->except(['sort', 'dir', 'page']), ['sort' => 'student_id_number', 'dir' => (request('sort') == 'student_id_number' && request('dir') == 'asc') ? 'desc' : 'asc'])) }}" class="sp-sort-link">Talaba ID{!! request('sort') == 'student_id_number' ? (request('dir') == 'asc' ? ' ▲' : ' ▼') : '' !!}</a></th>
                                    <th class="px-3 py-2 text-left font-medium text-gray-600"><a href="?{{ http_build_query(array_merge(request()->except(['sort', 'dir', 'page']), ['sort' => 'department_name', 'dir' => (request('sort') == 'department_name' && request('dir') == 'asc') ? 'desc' : 'asc'])) }}" class="sp-sort-link">Fakultet{!! request('sort') == 'department_name' ? (request('dir') == 'asc' ? ' ▲' : ' ▼') : '' !!}</a></th>
                                    <th class="px-3 py-2 text-left font-medium text-gray-600"><a href="?{{ http_build_query(array_merge(request()->except(['sort', 'dir', 'page']), ['sort' => 'specialty_name', 'dir' => (request('sort') == 'specialty_name' && request('dir') == 'asc') ? 'desc' : 'asc'])) }}" class="sp-sort-link">Yo'nalish{!! request('sort') == 'specialty_name' ? (request('dir') == 'asc' ? ' ▲' : ' ▼') : '' !!}</a></th>
                                    <th class="px-3 py-2 text-left font-medium text-gray-600"><a href="?{{ http_build_query(array_merge(request()->except(['sort', 'dir', 'page']), ['sort' => 'level_name', 'dir' => (request('sort') == 'level_name' && request('dir') == 'asc') ? 'desc' : 'asc'])) }}" class="sp-sort-link">Kurs{!! request('sort') == 'level_name' ? (request('dir') == 'asc' ? ' ▲' : ' ▼') : '' !!}</a></th>
                                    <th class="px-3 py-2 text-left font-medium text-gray-600"><a href="?{{ http_build_query(array_merge(request()->except(['sort', 'dir', 'page']), ['sort' => 'group_name', 'dir' => (request('sort') == 'group_name' && request('dir') == 'asc') ? 'desc' : 'asc'])) }}" class="sp-sort-link">Guruh{!! request('sort') == 'group_name' ? (request('dir') == 'asc' ? ' ▲' : ' ▼') : '' !!}</a></th>
                                    <th class="px-3 py-2 text-center font-medium text-gray-600">Rasm</th>
                                    <th class="px-3 py-2 text-left font-medium text-gray-600"><a href="?{{ http_build_query(array_merge(request()->except(['sort', 'dir', 'page']), ['sort' => 'uploaded_by', 'dir' => (request('sort') == 'uploaded_by' && request('dir') == 'asc') ? 'desc' : 'asc'])) }}" class="sp-sort-link">Tyutor{!! request('sort') == 'uploaded_by' ? (request('dir') == 'asc' ? ' ▲' : ' ▼') : '' !!}</a></th>
                                    <th class="px-3 py-2 text-center font-medium text-gray-600"><a href="?{{ http_build_query(array_merge(request()->except(['sort', 'dir', 'page']), ['sort' => 'similarity_score', 'dir' => (request('sort') == 'similarity_score' && request('dir') == 'asc') ? 'desc' : 'asc'])) }}" class="sp-sort-link">AI %{!! request('sort') == 'similarity_score' ? (request('dir') == 'asc' ? ' ▲' : ' ▼') : '' !!}</a></th>
                                    <th class="px-3 py-2 text-center font-medium text-gray-600"><a href="?{{ http_build_query(array_merge(request()->except(['sort', 'dir', 'page']), ['sort' => 'quality_score', 'dir' => (request('sort') == 'quality_score' && request('dir') == 'asc') ? 'desc' : 'asc'])) }}" class="sp-sort-link">Sifat{!! request('sort') == 'quality_score' ? (request('dir') == 'asc' ? ' ▲' : ' ▼') : '' !!}</a></th>
                                    <th class="px-3 py-2 text-center font-medium text-gray-600">Solishtirish</th>
                                    <th class="px-3 py-2 text-center font-medium text-gray-600"><a href="?{{ http_build_query(array_merge(request()->except(['sort', 'dir', 'page']), ['sort' => 'status', 'dir' => (request('sort') == 'status' && request('dir') == 'asc') ? 'desc' : 'asc'])) }}" class="sp-sort-link">Ruxsat{!! request('sort') == 'status' ? (request('dir') == 'asc' ? ' ▲' : ' ▼') : '' !!}</a></th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse($photos as $i => $photo)
                                    @php
                                        $groupName = $photo->student_group_name ?? $photo->group_name;
                                        $uploadedUrl = asset($photo->photo_path);
                                        $profileUrl = $photo->student_profile_image ?: null;
                                    @endphp
                                    <tr class="hover:bg-gray-50" :class="selected.includes({{ $photo->id }}) ? 'bg-indigo-50' : ''">
                                        <td class="px-3 py-2 text-center">
                                            <input type="checkbox" class="sp-row-check rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                                   value="{{ $photo->id }}"
                                                   :checked="selected.includes({{ $photo->id }})"
                                                   @change="if ($event.target.checked) { if (!selected.includes({{ $photo->id }})) selected.push({{ $photo->id }}); } else { selected = selected.filter(x => x !== {{ $photo->id }}); }">
                                        </td>
                                        <td class="px-3 py-2 text-gray-500">{{ $photos->firstItem() + $i }}</td>
                                        <td class="px-3 py-2 font-medium text-gray-900">{{ $photo->full_name }}</td>
                                        <td class="px-3 py-2 text-gray-700">{{ $photo->student_id_number }}</td>
                                        <td class="px-3 py-2 text-gray-700">{{ $photo->department_name ?? '—' }}</td>
                                        <td class="px-3 py-2 text-gray-700">{{ $photo->specialty_name ?? '—' }}</td>
                                        <td class="px-3 py-2 text-gray-700">{{ $photo->level_name ?? '—' }}</td>
                                        <td class="px-3 py-2 text-gray-700">{{ $groupName ?? '—' }}</td>
                                        <td class="px-3 py-2 text-center">
                                            <img src="{{ $uploadedUrl }}"
                                                 alt="{{ $photo->full_name }}"
                                                 class="inline-block w-12 h-16 object-cover rounded border border-gray-200 cursor-pointer hover:ring-2 hover:ring-blue-400"
                                                 @click="openLightbox('{{ $uploadedUrl }}', {{ Js::from($photo->full_name) }})"
                                                 loading="lazy">
                                        </td>
                                        <td class="px-3 py-2 text-gray-700">{{ $photo->uploaded_by }}</td>
                                        <td class="px-3 py-2 text-center">
                                            @if($photo->similarity_score !== null)
                                                @php
                                                    $pct = (float) $photo->similarity_score;
                                                    $cls = $photo->similarity_status === 'match'
                                                        ? 'bg-green-100 text-green-800'
                                                        : ($photo->similarity_status === 'mismatch' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-700');
                                                @endphp
                                                <span class="inline-block px-2 py-1 rounded text-sm font-semibold {{ $cls }}">
                                                    {{ number_format($pct, 1) }}%
                                                </span>
                                            @else
                                                <span class="text-xs text-gray-400">—</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 text-center">
                                            @if($photo->quality_score !== null)
                                                @php
                                                    $qscore = (float) $photo->quality_score;
                                                    $qcls = $photo->quality_passed
                                                        ? 'bg-green-100 text-green-800'
                                                        : 'bg-red-100 text-red-800';
                                                    $qissues = is_array($photo->quality_issues) ? $photo->quality_issues : [];
                                                @endphp
                                                <div class="inline-flex items-center gap-1">
                                                    <span class="inline-block px-2 py-1 rounded text-sm font-semibold {{ $qcls }}"
                                                          title="{{ implode(' · ', $qissues) }}">
                                                        {{ number_format($qscore, 0) }}%
                                                    </span>
                                                    <button type="button"
                                                            @click="runRowQuality({{ $photo->id }})"
                                                            :disabled="rowQuality.loading && rowQuality.id === {{ $photo->id }}"
                                                            class="inline-flex items-center justify-center w-6 h-6 rounded border border-gray-300 bg-white text-gray-500 hover:bg-gray-50 hover:text-teal-700 disabled:opacity-60"
                                                            title="Qayta tekshirish">
                                                        <svg x-show="!(rowQuality.loading && rowQuality.id === {{ $photo->id }})" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                                        <svg x-show="rowQuality.loading && rowQuality.id === {{ $photo->id }}" class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg>
                                                    </button>
                                                </div>
                                                @if(!empty($qissues))
                                                    <div class="text-[10px] text-red-700 mt-0.5 max-w-[160px] truncate" title="{{ implode(' · ', $qissues) }}">
                                                        {{ $qissues[0] }}
                                                    </div>
                                                @endif
                                            @else
                                                <button type="button"
                                                        @click="runRowQuality({{ $photo->id }})"
                                                        :disabled="rowQuality.loading && rowQuality.id === {{ $photo->id }}"
                                                        class="inline-flex items-center gap-1 px-2 py-1 text-xs rounded border border-teal-300 bg-teal-50 text-teal-700 hover:bg-teal-100 disabled:opacity-60"
                                                        title="Rasm sifatini tekshirish">
                                                    <svg x-show="!(rowQuality.loading && rowQuality.id === {{ $photo->id }})" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4"/></svg>
                                                    <svg x-show="rowQuality.loading && rowQuality.id === {{ $photo->id }}" class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg>
                                                    Tekshirish
                                                </button>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 text-center">
                                            @php
                                                $existingSimilarity = $photo->similarity_score !== null ? [
                                                    'similarity_percent' => (float) $photo->similarity_score,
                                                    'match' => $photo->similarity_status === 'match',
                                                    'status' => $photo->similarity_status,
                                                    'checked_at' => optional($photo->similarity_checked_at)->toIso8601String(),
                                                ] : null;
                                                $existingQuality = $photo->quality_score !== null ? [
                                                    'quality_score' => (float) $photo->quality_score,
                                                    'passed' => (bool) $photo->quality_passed,
                                                    'issues' => is_array($photo->quality_issues) ? $photo->quality_issues : [],
                                                    'ok' => is_array($photo->quality_ok) ? $photo->quality_ok : [],
                                                    'checked_at' => optional($photo->quality_checked_at)->toIso8601String(),
                                                ] : null;
                                            @endphp
                                            @if($profileUrl)
                                                <button type="button"
                                                        @click="openCompare({{ $photo->id }}, '{{ $profileUrl }}', '{{ $uploadedUrl }}', {{ Js::from($photo->full_name) }}, {{ Js::from($existingSimilarity) }}, {{ Js::from($existingQuality) }})"
                                                        class="inline-flex items-center gap-1.5 px-2.5 py-1.5 text-xs rounded-md border border-gray-300 bg-white hover:bg-gray-50">
                                                    <img src="{{ $profileUrl }}" class="w-6 h-8 object-cover rounded-sm" alt="profile">
                                                    <span class="text-gray-700">Solishtirish</span>
                                                </button>
                                            @else
                                                <button type="button"
                                                        @click="openCompare({{ $photo->id }}, '', '{{ $uploadedUrl }}', {{ Js::from($photo->full_name) }}, {{ Js::from($existingSimilarity) }}, {{ Js::from($existingQuality) }})"
                                                        class="inline-flex items-center gap-1.5 px-2.5 py-1.5 text-xs rounded-md border border-gray-300 bg-white hover:bg-gray-50">
                                                    <span class="text-gray-700">Sifat</span>
                                                </button>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 text-center">
                                            @if($photo->status === 'pending')
                                                <div class="flex gap-1 justify-center">
                                                    <form method="POST" action="{{ route('admin.student-photos.approve', $photo->id) }}"
                                                          onsubmit="return confirm('Rasmni tasdiqlaysizmi?')">
                                                        @csrf
                                                        <button type="submit"
                                                                class="inline-flex items-center px-2.5 py-1.5 bg-green-600 text-white text-xs font-medium rounded hover:bg-green-700"
                                                                title="Qabul qilish">
                                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                                        </button>
                                                    </form>
                                                    <button type="button"
                                                            @click="openReject({{ $photo->id }}, {{ Js::from($photo->full_name) }})"
                                                            class="inline-flex items-center px-2.5 py-1.5 bg-red-600 text-white text-xs font-medium rounded hover:bg-red-700"
                                                            title="Rad etish">
                                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                                    </button>
                                                </div>
                                            @elseif($photo->status === 'approved')
                                                <span class="inline-block px-2 py-0.5 rounded-full bg-green-100 text-green-800 text-xs font-medium">Tasdiqlangan</span>
                                                @if($photo->reviewed_by_name)
                                                    <div class="text-[11px] text-gray-500 mt-0.5">{{ $photo->reviewed_by_name }}</div>
                                                @endif
                                            @elseif($photo->status === 'rejected')
                                                <span class="inline-block px-2 py-0.5 rounded-full bg-red-100 text-red-800 text-xs font-medium" title="{{ $photo->rejection_reason }}">Rad etilgan</span>
                                                @if($photo->rejection_reason)
                                                    <div class="text-[11px] text-gray-500 mt-0.5 max-w-[180px] truncate" title="{{ $photo->rejection_reason }}">{{ $photo->rejection_reason }}</div>
                                                @endif
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="14" class="px-3 py-8 text-center text-gray-500">Ma'lumot topilmadi</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4">
                        {{ $photos->links() }}
                    </div>
                </div>
            </div>
        </div>

        {{-- Lightbox: tanlangan rasmni katta ko'rsatish --}}
        <div x-show="lightbox.open" x-cloak x-transition.opacity
             class="fixed inset-0 z-50 bg-black/80 flex items-center justify-center p-4"
             @click.self="lightbox.open = false"
             @keydown.escape.window="lightbox.open = false">
            <button type="button" @click="lightbox.open = false"
                    class="absolute top-4 right-4 text-white hover:text-gray-300">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
            <img :src="lightbox.src" :alt="lightbox.alt" class="max-h-[90vh] max-w-[90vw] object-contain rounded-lg shadow-2xl">
        </div>

        {{-- Solishtirish modali --}}
        <div x-show="compare.open" x-cloak x-transition.opacity
             class="fixed inset-0 z-50 bg-black/80 flex items-center justify-center p-4"
             @click.self="compare.open = false"
             @keydown.escape.window="compare.open = false">
            <div class="bg-white rounded-lg shadow-2xl max-w-4xl w-full p-6 relative">
                <button type="button" @click="compare.open = false"
                        class="absolute top-3 right-3 text-gray-400 hover:text-gray-700">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
                <h3 class="text-lg font-semibold text-gray-900 mb-4" x-text="compare.title"></h3>
                <div class="grid grid-cols-2 gap-4">
                    <div class="text-center">
                        <div class="text-sm text-gray-500 mb-2">Profil rasmi (HEMIS)</div>
                        <img :src="compare.profile" class="mx-auto max-h-[55vh] rounded border border-gray-200" alt="profile">
                    </div>
                    <div class="text-center">
                        <div class="text-sm text-gray-500 mb-2">Tyutor yuklagan rasm</div>
                        <img :src="compare.uploaded" class="mx-auto max-h-[55vh] rounded border border-gray-200" alt="uploaded">
                    </div>
                </div>

                {{-- AI solishtirish bo'limi --}}
                <div class="mt-5 pt-4 border-t border-gray-200">
                    <div class="flex items-center justify-between gap-4 flex-wrap">
                        <div class="text-sm text-gray-700">
                            <strong>AI yuz tahlili</strong> — ArcFace modeli ikki rasmdagi yuzni solishtiradi
                        </div>
                        <button type="button"
                                @click="runSimilarityCheck()"
                                :disabled="compare.loading"
                                class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700 disabled:opacity-60 disabled:cursor-not-allowed">
                            <svg x-show="!compare.loading" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                            <svg x-show="compare.loading" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg>
                            <span x-text="compare.loading ? 'Tahlil qilinmoqda...' : (compare.result ? 'Qayta tekshirish' : 'AI bilan tekshirish')"></span>
                        </button>
                    </div>

                    <template x-if="compare.error">
                        <div class="mt-3 rounded-md bg-red-50 border border-red-200 text-red-700 px-3 py-2 text-sm" x-text="compare.error"></div>
                    </template>

                    <template x-if="compare.result">
                        <div class="mt-3 rounded-md p-4 border"
                             :class="compare.result.match ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200'">
                            <div class="flex items-center gap-3">
                                <div class="text-3xl font-bold" :class="compare.result.match ? 'text-green-700' : 'text-red-700'">
                                    <span x-text="compare.result.similarity_percent + '%'"></span>
                                </div>
                                <div class="flex-1">
                                    <div class="font-semibold" :class="compare.result.match ? 'text-green-800' : 'text-red-800'">
                                        <span x-text="compare.result.match ? 'O\'xshash — ehtimol bir xil shaxs' : 'Farqli — shubhali, e\'tiborga oling'"></span>
                                    </div>
                                    <div class="text-xs text-gray-600 mt-0.5">
                                        Masofa: <span x-text="compare.result.distance"></span>,
                                        chegara: <span x-text="compare.result.threshold"></span>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-2 text-xs text-gray-500">
                                Bu — yordamchi baho. Yakuniy qaror admin tomonidan qabul qilinadi.
                            </div>
                        </div>
                    </template>

                    <template x-if="!compare.result && !compare.loading && !compare.error">
                        <div class="mt-3 text-xs text-gray-500">
                            Tugmani bosing — tahlil 2-5 soniya oladi.
                        </div>
                    </template>
                </div>

                {{-- AI rasm sifati tekshiruvi --}}
                <div class="mt-5 pt-4 border-t border-gray-200">
                    <div class="flex items-center justify-between gap-4 flex-wrap">
                        <div class="text-sm text-gray-700">
                            <strong>AI rasm sifati</strong> — markaz, framing, oq xalat, yoritish
                        </div>
                        <button type="button"
                                @click="runQualityCheck()"
                                :disabled="compare.qualityLoading"
                                class="inline-flex items-center gap-2 px-4 py-2 bg-teal-600 text-white text-sm font-medium rounded-md hover:bg-teal-700 disabled:opacity-60 disabled:cursor-not-allowed">
                            <svg x-show="!compare.qualityLoading" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                            <svg x-show="compare.qualityLoading" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg>
                            <span x-text="compare.qualityLoading ? 'Tekshirilmoqda...' : (compare.quality ? 'Qayta tekshirish' : 'Sifatni tekshirish')"></span>
                        </button>
                    </div>

                    <template x-if="compare.qualityError">
                        <div class="mt-3 rounded-md bg-red-50 border border-red-200 text-red-700 px-3 py-2 text-sm" x-text="compare.qualityError"></div>
                    </template>

                    <template x-if="compare.quality">
                        <div class="mt-3 rounded-md p-4 border"
                             :class="compare.quality.passed ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200'">
                            <div class="flex items-center gap-3 mb-3">
                                <div class="text-3xl font-bold" :class="compare.quality.passed ? 'text-green-700' : 'text-red-700'">
                                    <span x-text="compare.quality.quality_score + '%'"></span>
                                </div>
                                <div class="flex-1">
                                    <div class="font-semibold" :class="compare.quality.passed ? 'text-green-800' : 'text-red-800'">
                                        <span x-text="compare.quality.passed ? 'Maqbul — standartlarga mos' : 'Xato — standartlarga mos emas'"></span>
                                    </div>
                                </div>
                            </div>
                            <template x-if="compare.quality.issues && compare.quality.issues.length > 0">
                                <div class="mb-2">
                                    <div class="text-xs font-bold text-red-800 mb-1">Muammolar:</div>
                                    <ul class="text-xs text-red-700 space-y-0.5 pl-4 list-disc">
                                        <template x-for="issue in compare.quality.issues" :key="issue">
                                            <li x-text="issue"></li>
                                        </template>
                                    </ul>
                                </div>
                            </template>
                            <template x-if="compare.quality.ok && compare.quality.ok.length > 0">
                                <div>
                                    <div class="text-xs font-bold text-green-800 mb-1">Muvaffaqiyatli tekshiruvlar:</div>
                                    <ul class="text-xs text-green-700 space-y-0.5 pl-4 list-disc">
                                        <template x-for="okItem in compare.quality.ok" :key="okItem">
                                            <li x-text="okItem"></li>
                                        </template>
                                    </ul>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        {{-- Rad etish modali --}}
        <div x-show="reject.open" x-cloak x-transition.opacity
             class="fixed inset-0 z-50 bg-black/60 flex items-center justify-center p-4"
             @click.self="reject.open = false"
             @keydown.escape.window="reject.open = false">
            <div class="bg-white rounded-lg shadow-2xl max-w-md w-full p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Rasmni rad etish</h3>
                <p class="text-sm text-gray-600 mb-4" x-text="reject.name"></p>
                <form method="POST" :action="`{{ url('admin/student-photos') }}/${reject.id}/reject`">
                    @csrf
                    <label class="block text-sm font-medium text-gray-700 mb-2">Rad etish sababi</label>
                    <textarea name="rejection_reason" rows="3" required maxlength="500"
                              class="w-full rounded-md border-gray-300 shadow-sm text-sm mb-4"
                              placeholder="Masalan: Rasm sifati past, yuz aniq ko'rinmaydi, talabaga o'xshamaydi..."></textarea>
                    <div class="flex justify-end gap-2">
                        <button type="button" @click="reject.open = false"
                                class="px-4 py-2 bg-gray-100 text-gray-700 text-sm rounded-md hover:bg-gray-200">
                            Bekor qilish
                        </button>
                        <button type="submit"
                                class="px-4 py-2 bg-red-600 text-white text-sm font-semibold rounded-md hover:bg-red-700">
                            Rad etish
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Floating action bar (when rows are selected) --}}
        <div x-show="selected.length > 0" x-cloak x-transition
             class="fixed bottom-4 left-1/2 -translate-x-1/2 z-40 bg-white shadow-2xl border border-gray-200 rounded-full px-4 py-2 flex items-center gap-2">
            <span class="text-sm text-gray-700 pr-2">
                <strong x-text="selected.length"></strong> ta tanlangan
            </span>
            <button type="button" @click="openBulk()"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-indigo-600 text-white text-xs font-semibold rounded-full hover:bg-indigo-700">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                AI tahlil
            </button>
            <button type="button" @click="openReview('approve')"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-green-600 text-white text-xs font-semibold rounded-full hover:bg-green-700">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                Qabul
            </button>
            <button type="button" @click="openReview('reject')"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-red-600 text-white text-xs font-semibold rounded-full hover:bg-red-700">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                Rad
            </button>
            <button type="button" @click="openReview('revert')"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-orange-500 text-white text-xs font-semibold rounded-full hover:bg-orange-600"
                    title="Kutilmoqda holatiga qaytarish">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                Qaytarish
            </button>
            <button type="button" @click="clearSelection()"
                    class="inline-flex items-center px-2 py-1.5 text-gray-500 hover:text-gray-700"
                    title="Tanlovni tozalash">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        {{-- Bulk qabul / rad etish modali --}}
        <div x-show="review.open" x-cloak x-transition.opacity
             class="fixed inset-0 z-50 bg-black/60 flex items-center justify-center p-4"
             @click.self="if (review.phase !== 'running') review.open = false">
            <div class="bg-white rounded-lg shadow-2xl max-w-lg w-full p-6">
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">
                            <span x-show="review.mode === 'approve'">Bulk qabul qilish</span>
                            <span x-show="review.mode === 'reject'">Bulk rad etish</span>
                            <span x-show="review.mode === 'revert'">Bulk qaytarish</span>
                        </h3>
                        <p class="text-xs text-gray-500 mt-0.5">
                            <span x-show="review.mode !== 'revert'">Tanlangan <strong>kutilmoqda</strong> holatidagi rasmlarga qo'llanadi.</span>
                            <span x-show="review.mode === 'revert'">Tasdiqlangan/rad etilgan rasmlar <strong>kutilmoqda</strong> holatiga qaytariladi (xato tasdiq/rad bekor qilinadi).</span>
                            Tyutor'larga Telegram orqali xabar yuboriladi.
                        </p>
                    </div>
                    <button type="button" @click="if (review.phase !== 'running') review.open = false"
                            class="text-gray-400 hover:text-gray-700" :class="review.phase === 'running' ? 'opacity-40 cursor-not-allowed' : ''">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                {{-- Confirm phase --}}
                <template x-if="review.phase === 'confirm'">
                    <div>
                        <template x-if="review.total === 0">
                            <div class="rounded-md bg-yellow-50 border border-yellow-200 text-yellow-800 px-4 py-3 text-sm">
                                Hozirgi filtr bo'yicha "kutilmoqda" holatidagi rasm topilmadi. Filtrni tekshiring.
                            </div>
                        </template>
                        <template x-if="review.total > 0">
                            <div class="space-y-4">
                                <div class="rounded-md px-4 py-3 text-sm border"
                                     :class="review.mode === 'approve' ? 'bg-green-50 border-green-200 text-green-900'
                                          : review.mode === 'revert' ? 'bg-orange-50 border-orange-200 text-orange-900'
                                          : 'bg-red-50 border-red-200 text-red-900'">
                                    <strong x-text="review.total"></strong> ta rasm
                                    <span x-show="review.mode === 'approve'">tasdiqlanadi</span>
                                    <span x-show="review.mode === 'reject'">rad etiladi</span>
                                    <span x-show="review.mode === 'revert'">kutilmoqda holatiga qaytariladi</span>.
                                    Har bir rasm uchun tyutor'ga avtomat Telegram xabari yuboriladi.
                                </div>

                                <template x-if="review.mode === 'reject'">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Rad etish sababi (hammasiga bir xil qo'llanadi)</label>
                                        <textarea x-model="review.reason" rows="3" maxlength="500"
                                                  class="w-full rounded-md border-gray-300 shadow-sm text-sm"
                                                  placeholder="Masalan: Rasm standartlariga javob bermaydi — tirsakdan yuqori, oq xalatda qayta yuklang"></textarea>
                                    </div>
                                </template>

                                <div class="flex justify-end gap-2 pt-2">
                                    <button type="button" @click="review.open = false"
                                            class="px-4 py-2 bg-gray-100 text-gray-700 text-sm rounded-md hover:bg-gray-200">
                                        Bekor qilish
                                    </button>
                                    <button type="button" @click="runReview()"
                                            :class="review.mode === 'approve' ? 'bg-green-600 hover:bg-green-700'
                                                 : review.mode === 'revert' ? 'bg-orange-500 hover:bg-orange-600'
                                                 : 'bg-red-600 hover:bg-red-700'"
                                            class="px-4 py-2 text-white text-sm font-semibold rounded-md">
                                        <span x-show="review.mode === 'approve'">Tasdiqlashni boshlash</span>
                                        <span x-show="review.mode === 'reject'">Rad etishni boshlash</span>
                                        <span x-show="review.mode === 'revert'">Qaytarishni boshlash</span>
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>

                {{-- Running phase --}}
                <template x-if="review.phase === 'running'">
                    <div class="space-y-4">
                        <div class="text-sm text-gray-700">
                            Jarayonda: <span class="font-semibold" x-text="review.processed + ' / ' + review.total"></span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-3 overflow-hidden">
                            <div class="h-3 transition-all duration-200"
                                 :class="review.mode === 'approve' ? 'bg-green-600' : review.mode === 'revert' ? 'bg-orange-500' : 'bg-red-600'"
                                 :style="`width: ${review.total ? (review.processed / review.total * 100) : 0}%`"></div>
                        </div>
                        <div class="flex justify-between text-xs text-gray-600">
                            <span>
                                <span class="text-green-700">✓ <span x-text="review.succeeded"></span></span>
                                &nbsp;·&nbsp;
                                <span class="text-red-700">✗ <span x-text="review.failed"></span></span>
                            </span>
                            <button type="button" @click="review.cancel = true"
                                    :disabled="review.cancel"
                                    class="px-3 py-1 bg-gray-100 text-gray-700 text-xs rounded-md hover:bg-gray-200 disabled:opacity-60">
                                <span x-text="review.cancel ? 'To\'xtatilmoqda...' : 'To\'xtatish'"></span>
                            </button>
                        </div>
                    </div>
                </template>

                {{-- Done phase --}}
                <template x-if="review.phase === 'done'">
                    <div class="space-y-4">
                        <div class="rounded-md bg-green-50 border border-green-200 text-green-900 px-4 py-3 text-sm">
                            Jarayon yakunlandi. <strong x-text="review.succeeded"></strong> ta muvaffaqiyatli,
                            <strong x-text="review.failed"></strong> ta xatolik.
                        </div>
                        <template x-if="review.errors.length > 0">
                            <details class="rounded-md bg-red-50 border border-red-200 text-red-800 px-4 py-2 text-xs">
                                <summary class="cursor-pointer font-semibold">Xatoliklar (<span x-text="review.errors.length"></span>)</summary>
                                <ul class="mt-2 space-y-0.5 max-h-40 overflow-y-auto">
                                    <template x-for="err in review.errors" :key="err">
                                        <li x-text="err"></li>
                                    </template>
                                </ul>
                            </details>
                        </template>
                        <div class="flex justify-end gap-2">
                            <button type="button" @click="review.open = false"
                                    class="px-4 py-2 bg-gray-100 text-gray-700 text-sm rounded-md hover:bg-gray-200">
                                Yopish
                            </button>
                            <button type="button" @click="location.reload()"
                                    class="px-4 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-md hover:bg-indigo-700">
                                Sahifani yangilash
                            </button>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        {{-- Bulk AI tahlil modali --}}
        <div x-show="bulk.open" x-cloak x-transition.opacity
             class="fixed inset-0 z-50 bg-black/60 flex items-center justify-center p-4"
             @click.self="if (bulk.phase !== 'running') bulk.open = false">
            <div class="bg-white rounded-lg shadow-2xl max-w-lg w-full p-6">
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Bulk AI yuz tahlili</h3>
                        <p class="text-xs text-gray-500 mt-0.5">Tanlangan filtr bo'yicha rasmlarni ArcFace bilan solishtiradi</p>
                    </div>
                    <button type="button" @click="if (bulk.phase !== 'running') bulk.open = false"
                            class="text-gray-400 hover:text-gray-700" :class="bulk.phase === 'running' ? 'opacity-40 cursor-not-allowed' : ''">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                {{-- Confirm phase --}}
                <template x-if="bulk.phase === 'confirm'">
                    <div class="space-y-4">
                        <div class="rounded-md bg-indigo-50 border border-indigo-200 text-indigo-900 px-4 py-3 text-sm">
                            <strong x-text="bulk.total"></strong> ta tanlangan rasmga tahlil qo'llanadi.
                        </div>
                        <div class="space-y-2 rounded-md border border-gray-200 p-3 bg-gray-50">
                            <div class="text-xs font-bold uppercase text-gray-500 mb-1">Qaysi tahlilni bajarish</div>
                            <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                                <input type="checkbox" x-model="bulk.runSimilarity"
                                       class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                <span>O'xshashlik tekshiruvi (ArcFace — HEMIS profili bilan solishtirish)</span>
                            </label>
                            <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                                <input type="checkbox" x-model="bulk.runQuality"
                                       class="rounded border-gray-300 text-teal-600 focus:ring-teal-500">
                                <span>Rasm sifati tekshiruvi (markaz, framing, oq xalat, yoritish)</span>
                            </label>
                        </div>
                        <div class="text-xs text-gray-500">
                            Har bir rasm
                            <span x-text="((bulk.runSimilarity ? 3 : 0) + (bulk.runQuality ? 3 : 0)) + '-' + ((bulk.runSimilarity ? 5 : 0) + (bulk.runQuality ? 5 : 0))"></span>
                            soniya oladi. Taxminiy vaqt: <strong x-text="Math.ceil(bulk.total * ((bulk.runSimilarity ? 3 : 0) + (bulk.runQuality ? 4 : 0)) / 60) + ' daqiqa'"></strong>.
                            Natija avtomat bazaga saqlanadi. Tahlil chog'ida oynani yopmang.
                        </div>
                        <div class="flex justify-end gap-2 pt-2">
                            <button type="button" @click="bulk.open = false"
                                    class="px-4 py-2 bg-gray-100 text-gray-700 text-sm rounded-md hover:bg-gray-200">
                                Bekor qilish
                            </button>
                            <button type="button" @click="runBulk()"
                                    :disabled="!bulk.runSimilarity && !bulk.runQuality"
                                    class="px-4 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-md hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed">
                                Boshlash
                            </button>
                        </div>
                    </div>
                </template>

                {{-- Running phase --}}
                <template x-if="bulk.phase === 'running'">
                    <div class="space-y-4">
                        <div class="text-sm text-gray-700">
                            Jarayonda: <span class="font-semibold" x-text="bulk.currentName"></span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-3 overflow-hidden">
                            <div class="bg-indigo-600 h-3 transition-all duration-200"
                                 :style="`width: ${bulk.total ? (bulk.processed / bulk.total * 100) : 0}%`"></div>
                        </div>
                        <div class="flex justify-between text-xs text-gray-600">
                            <span><strong x-text="bulk.processed"></strong> / <span x-text="bulk.total"></span></span>
                            <span>
                                <span class="text-green-700">✓ <span x-text="bulk.succeeded"></span></span>
                                &nbsp;·&nbsp;
                                <span class="text-red-700">✗ <span x-text="bulk.failed"></span></span>
                            </span>
                        </div>
                        <div class="flex justify-end">
                            <button type="button" @click="bulk.cancel = true"
                                    :disabled="bulk.cancel"
                                    class="px-4 py-2 bg-gray-100 text-gray-700 text-sm rounded-md hover:bg-gray-200 disabled:opacity-60">
                                <span x-text="bulk.cancel ? 'To\'xtatilmoqda...' : 'To\'xtatish'"></span>
                            </button>
                        </div>
                    </div>
                </template>

                {{-- Done phase --}}
                <template x-if="bulk.phase === 'done'">
                    <div class="space-y-4">
                        <div class="rounded-md bg-green-50 border border-green-200 text-green-900 px-4 py-3 text-sm">
                            Tahlil yakunlandi.
                            <strong x-text="bulk.succeeded"></strong> ta muvaffaqiyatli,
                            <strong x-text="bulk.failed"></strong> ta xatolik.
                        </div>
                        <template x-if="bulk.errors.length > 0">
                            <details class="rounded-md bg-red-50 border border-red-200 text-red-800 px-4 py-2 text-xs">
                                <summary class="cursor-pointer font-semibold">Xatoliklar (<span x-text="bulk.errors.length"></span>)</summary>
                                <ul class="mt-2 space-y-0.5 max-h-40 overflow-y-auto">
                                    <template x-for="err in bulk.errors" :key="err">
                                        <li x-text="err"></li>
                                    </template>
                                </ul>
                            </details>
                        </template>
                        <div class="flex justify-end gap-2">
                            <button type="button" @click="bulk.open = false"
                                    class="px-4 py-2 bg-gray-100 text-gray-700 text-sm rounded-md hover:bg-gray-200">
                                Yopish
                            </button>
                            <button type="button" @click="location.reload()"
                                    class="px-4 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-md hover:bg-indigo-700">
                                Sahifani yangilash
                            </button>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>

    {{-- Select2 va stil (JN o'zlashtirish hisobotidan) --}}
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
        function stripSpecialChars(s) { return s.replace(/[\/\(\),\-\.\s]/g, '').toLowerCase(); }
        function fuzzyMatcher(params, data) {
            if ($.trim(params.term) === '') return data;
            if (typeof data.text === 'undefined') return null;
            if (stripSpecialChars(data.text).indexOf(stripSpecialChars(params.term)) > -1) return $.extend({}, data, true);
            if (data.text.toLowerCase().indexOf(params.term.toLowerCase()) > -1) return $.extend({}, data, true);
            return null;
        }
        $(document).ready(function() {
            var $form = $('#sp-filter-form');

            $('.select2-sp').each(function() {
                $(this).select2({
                    theme: 'classic',
                    width: '100%',
                    allowClear: true,
                    placeholder: $(this).find('option:first').text(),
                    matcher: fuzzyMatcher
                });
            });

            // Avto-submit: har qanday select o'zgarganda filtr qayta qo'llanadi
            $('.select2-sp, .sp-op-select').on('change', function() {
                $form.trigger('submit');
            });

            // Raqamli inputlar (AI min/max %) — debounce bilan auto-submit
            var numberDebounce;
            $form.find('input[type=number]').on('input', function() {
                clearTimeout(numberDebounce);
                numberDebounce = setTimeout(function() { $form.trigger('submit'); }, 600);
            });

            // Matn qidiruv — Enter bosilganda submit bo'ladi (default brauzer xatti-harakati)
        });
    </script>

    <style>
        .filter-container { padding: 16px 20px 12px; background: linear-gradient(135deg, #f0f4f8, #e8edf5); border-bottom: 2px solid #dbe4ef; }
        .filter-row { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 10px; align-items: flex-end; }
        .filter-row:last-child { margin-bottom: 0; }
        .filter-label { display: flex; align-items: center; gap: 5px; margin-bottom: 4px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: #475569; }
        .fl-dot { width: 7px; height: 7px; border-radius: 50%; display: inline-block; flex-shrink: 0; }

        .sp-text-input { width: 100%; height: 36px; padding: 0 10px; border: 1px solid #cbd5e1; border-radius: 8px; background: #fff; font-size: 13px; color: #1e293b; box-shadow: 0 1px 2px rgba(0,0,0,0.04); }
        .sp-text-input:focus { outline: none; border-color: #2b5ea7; box-shadow: 0 0 0 2px rgba(43,94,167,0.1); }

        .sp-op-select { width: 60px; flex: 0 0 60px; height: 36px; padding: 0 6px; border: 1px solid #cbd5e1; border-radius: 8px; background: #fff; font-size: 14px; font-weight: 700; color: #1e293b; text-align: center; cursor: pointer; }
        .sp-op-select:focus { outline: none; border-color: #2b5ea7; box-shadow: 0 0 0 2px rgba(43,94,167,0.1); }

        .btn-calc { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; background: linear-gradient(135deg, #2b5ea7, #3b7ddb); color: #fff; border: none; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 8px rgba(43,94,167,0.3); height: 36px; }
        .btn-calc:hover { background: linear-gradient(135deg, #1e4b8a, #2b5ea7); box-shadow: 0 4px 12px rgba(43,94,167,0.4); transform: translateY(-1px); }

        .btn-bulk { display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; background: linear-gradient(135deg, #6366f1, #818cf8); color: #fff; border: none; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 8px rgba(99,102,241,0.3); height: 36px; }
        .btn-bulk:hover { background: linear-gradient(135deg, #4f46e5, #6366f1); box-shadow: 0 4px 12px rgba(99,102,241,0.4); transform: translateY(-1px); }

        .btn-approve { display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; background: linear-gradient(135deg, #16a34a, #22c55e); color: #fff; border: none; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 8px rgba(22,163,74,0.3); height: 36px; }
        .btn-approve:hover { background: linear-gradient(135deg, #15803d, #16a34a); box-shadow: 0 4px 12px rgba(22,163,74,0.4); transform: translateY(-1px); }

        .btn-reject { display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; background: linear-gradient(135deg, #dc2626, #ef4444); color: #fff; border: none; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 8px rgba(220,38,38,0.3); height: 36px; }
        .btn-reject:hover { background: linear-gradient(135deg, #b91c1c, #dc2626); box-shadow: 0 4px 12px rgba(220,38,38,0.4); transform: translateY(-1px); }

        .btn-clear { display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; background: #f1f5f9; color: #334155; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; transition: all 0.2s; height: 36px; text-decoration: none; }
        .btn-clear:hover { background: #e2e8f0; }

        .sp-sort-link { color: inherit; text-decoration: none; cursor: pointer; }
        .sp-sort-link:hover { color: #4f46e5; text-decoration: underline; }

        .select2-container--classic .select2-selection--single { height: 36px; border: 1px solid #cbd5e1; border-radius: 8px; background: #fff; transition: all 0.2s; box-shadow: 0 1px 2px rgba(0,0,0,0.04); }
        .select2-container--classic .select2-selection--single:hover { border-color: #2b5ea7; box-shadow: 0 0 0 2px rgba(43,94,167,0.1); }
        .select2-container--classic .select2-selection--single .select2-selection__rendered { line-height: 34px; padding-left: 10px; padding-right: 52px; color: #1e293b; font-size: 0.8rem; font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .select2-container--classic .select2-selection--single .select2-selection__arrow { height: 34px; width: 22px; background: transparent; border-left: none; right: 0; }
        .select2-container--classic .select2-selection--single .select2-selection__clear { position: absolute; right: 22px; top: 50%; transform: translateY(-50%); font-size: 16px; font-weight: bold; color: #94a3b8; cursor: pointer; padding: 2px 6px; z-index: 2; background: #fff; border-radius: 50%; line-height: 1; transition: all 0.15s; }
        .select2-container--classic .select2-selection--single .select2-selection__clear:hover { color: #fff; background: #ef4444; }
        .select2-dropdown { font-size: 0.8rem; border-radius: 8px; border: 1px solid #cbd5e1; box-shadow: 0 8px 24px rgba(0,0,0,0.12); }
        .select2-container--classic .select2-results__option--highlighted { background-color: #2b5ea7; }
    </style>
</x-app-layout>
